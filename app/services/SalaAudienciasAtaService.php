<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasAtaRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasEstudanteInscricaoRepository.php';
require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
require_once __DIR__ . '/../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../helpers/SalaAudienciasAtaSigiloHelper.php';

final class SalaAudienciasAtaService
{
    /** Limite definido pela magistratura: no máximo este número de estudantes inscritos por audiência. */
    public const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA = 5;

    /**
     * Audiências com data anterior a (hoje − N dias) e sem PDF ainda dentro do prazo de download
     * não entram na lista de atas (interno e página estudaata); inscrições seguem visíveis no painel com “Documento expirado”.
     */
    public const LISTAGEM_ATAS_OCULTAR_APOS_DIAS_SEM_DOWNLOAD = 40;

    private const DIAS_VALIDADE_ATA = 30;
    private const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

    private PDO $connection;
    private SalaAudienciasPautaRepository $pautaRepository;
    private SalaAudienciasAtaRepository $ataRepository;
    private SalaAudienciasEstudanteInscricaoRepository $inscricaoRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->pautaRepository = new SalaAudienciasPautaRepository($connection);
        $this->ataRepository = new SalaAudienciasAtaRepository($connection);
        $this->inscricaoRepository = new SalaAudienciasEstudanteInscricaoRepository($connection);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichRowsBlocoProcesso(array $rows): array
    {
        foreach ($rows as $idx => $row) {
            if (!isset($rows[$idx]['classe_processo']) || trim((string) $rows[$idx]['classe_processo']) === '') {
                $rows[$idx]['classe_processo'] = (string) ($row['classe_nome'] ?? '');
            }
        }

        return ProcessoBlocoListagemHelper::appendBlocoData($this->connection, $rows);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listRealizadasParaUpload(int $limit, int $offset): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $this->purgeAtasExpiradas();

        $dias = self::LISTAGEM_ATAS_OCULTAR_APOS_DIAS_SEM_DOWNLOAD;

        $rows = $this->ataRepository->listRealizadasComAta($limit, $offset, $dias);

        return [
            'rows' => $this->enrichRowsBlocoProcesso($rows),
            'total' => $this->ataRepository->countRealizadas($dias),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listHojeParaInscricaoEstudante(string $usuarioId): array
    {
        $rows = $this->inscricaoRepository->listHojeDesignadasElegiveis($usuarioId);
        $out = [];
        foreach ($rows as $row) {
            if (SalaAudienciasAtaSigiloHelper::linhaIndicaProcessoSigiloso($row)) {
                continue;
            }
            $processoSigiloso = (int) ($row['processo_sigiloso'] ?? 0) !== 0;
            $envolveMenor = (int) ($row['qtd_menor'] ?? 0) > 0;
            $temPrioridadeBloqueio = (int) ($row['tem_prioridade_bloqueio'] ?? 0) !== 0;
            $total = (int) ($row['total_inscritos_pauta'] ?? 0);
            $jaInscrito = (int) ($row['ja_inscrito'] ?? 0) === 1;
            $row['vagas_esgotadas'] = $total >= self::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA;
            $row['processo_sigiloso'] = $processoSigiloso;
            $row['envolve_menor'] = $envolveMenor;
            $row['tem_prioridade_bloqueio'] = $temPrioridadeBloqueio;
            $row['ja_inscrito'] = $jaInscrito;
            $row['pode_inscrever'] = !$jaInscrito
                && !$row['vagas_esgotadas']
                && !$processoSigiloso
                && !$envolveMenor
                && !$temPrioridadeBloqueio;
            $out[] = $row;
        }

        return $this->enrichRowsBlocoProcesso($out);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMinhasInscricoesEstudante(string $usuarioId): array
    {
        $this->purgeAtasExpiradas();

        return $this->enrichRowsBlocoProcesso($this->inscricaoRepository->listInscricoesVisiveisParaUsuario($usuarioId));
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    public function uploadPdf(string $idPauta, array $file, string $usuarioId): void
    {
        $idPauta = trim($idPauta);
        $usuarioId = trim($usuarioId);
        if ($idPauta === '' || $usuarioId === '') {
            throw new InvalidArgumentException('Dados inválidos.');
        }

        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        SalaAudienciasAtaSigiloHelper::assertPautaPermiteAtas($this->connection, $pauta);
        $st = trim((string) ($pauta['status'] ?? ''));
        if ($st === 'agendada') {
            $st = 'designada';
        }
        if ($st !== 'realizada' && $st !== 'finalizada') {
            throw new InvalidArgumentException('Só é permitido enviar ata para audiência com status Realizada ou Finalizada.');
        }

        $this->purgeAtasExpiradas();

        $existingAtaRow = $this->ataRepository->findByPauta($idPauta);
        if ($existingAtaRow !== null && trim((string) ($existingAtaRow['arquivo_relativo'] ?? '')) === '') {
            throw new InvalidArgumentException('O prazo de disponibilidade do PDF desta ata encerrou. Novo envio não é permitido.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Falha no envio do arquivo.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Arquivo inválido ou muito grande (máx. 2 MB).');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Upload inválido.');
        }

        if (!$this->isPdfUpload($tmp)) {
            throw new InvalidArgumentException('Envie apenas arquivo PDF.');
        }

        $basePath = dirname(__DIR__, 2);
        $dir = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sala-audiencias-atas';
        if (!is_dir($dir)) {
            error_log('[SalaAudienciasAtaService::uploadPdf] Diretório ausente: ' . $dir);
            throw new RuntimeException('Diretório de armazenamento de atas indisponível no servidor. Contate o administrador.');
        }
        if (!is_writable($dir)) {
            error_log('[SalaAudienciasAtaService::uploadPdf] Diretório sem permissão de escrita: ' . $dir);
            throw new RuntimeException('Diretório de armazenamento de atas sem permissão de escrita. Contate o administrador.');
        }

        if ($existingAtaRow !== null && !empty($existingAtaRow['arquivo_relativo'])) {
            $oldFull = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $existingAtaRow['arquivo_relativo']);
            if (is_file($oldFull)) {
                @unlink($oldFull);
            }
        }

        $pautaIdSafe = preg_replace('/[^A-Za-z0-9_-]/', '', $idPauta) ?: 'pauta';
        $auditDateRaw = trim((string) ($pauta['data_audiencia'] ?? ''));
        $auditDateSafe = preg_replace('/[^0-9]/', '', $auditDateRaw) ?: date('Ymd');
        $uniqueToken = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        $identificadorArquivo = 'ATA-' . $pautaIdSafe . '-' . $auditDateSafe . '-' . date('YmdHis') . '-' . $uniqueToken;
        $basename = 'ata_' . $pautaIdSafe . '_' . $auditDateSafe . '_' . date('YmdHis') . '_' . $uniqueToken . '.pdf';
        $relative = 'sala-audiencias-atas/' . $basename;
        $dest = $dir . DIRECTORY_SEPARATOR . $basename;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Falha ao salvar o PDF.');
        }

        $nomeOriginal = (string) ($file['name'] ?? 'ata.pdf');
        $nomeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s\(\)]/u', '', $nomeOriginal) ?: 'ata.pdf';
        if (function_exists('mb_substr')) {
            $nomeOriginal = mb_substr($nomeOriginal, 0, 255, 'UTF-8');
        } else {
            $nomeOriginal = substr($nomeOriginal, 0, 255);
        }

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::DIAS_VALIDADE_ATA . ' days'));

        $this->ataRepository->upsertForPauta([
            'pauta' => $idPauta,
            'arquivo_relativo' => $relative,
            'nome_original' => $nomeOriginal,
            'mime' => 'application/pdf',
            'tamanho_bytes' => $size,
            'uploaded_by' => $usuarioId,
            'uploaded_at' => $now,
            'expires_at' => $expires,
            'identificador_arquivo' => $identificadorArquivo,
        ]);
    }

    /**
     * Valida PDF por MIME (fileinfo), com fallback para assinatura %PDF (Windows / servidores sem fileinfo).
     */
    private function isPdfUpload(string $tmpPath): bool
    {
        if (extension_loaded('fileinfo') && class_exists('finfo')) {
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpPath);
                if (in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // segue para assinatura
            }
        }

        $fh = fopen($tmpPath, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = fread($fh, 5);
        fclose($fh);

        return is_string($head) && strncmp($head, '%PDF', 4) === 0;
    }

    public function inscreverEstudante(string $idPauta, string $usuarioId): void
    {
        $idPauta = trim($idPauta);
        $usuarioId = trim($usuarioId);
        if ($idPauta === '' || $usuarioId === '') {
            throw new InvalidArgumentException('Dados inválidos.');
        }

        if ($this->inscricaoRepository->exists($idPauta, $usuarioId)) {
            throw new InvalidArgumentException('Você já está inscrito nesta audiência.');
        }

        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }

        $st = trim((string) ($pauta['status'] ?? ''));
        if ($st === 'agendada') {
            $st = 'designada';
        }
        if ($st !== 'designada') {
            throw new InvalidArgumentException('Inscrição só é permitida para audiências designadas.');
        }

        $dataAud = trim((string) ($pauta['data_audiencia'] ?? ''));
        if ($dataAud === '') {
            throw new InvalidArgumentException('Data da audiência inválida.');
        }
        $hoje = date('Y-m-d');
        if ($dataAud !== $hoje) {
            throw new InvalidArgumentException('Inscrição só é permitida no dia da audiência.');
        }

        SalaAudienciasAtaSigiloHelper::assertPautaPermiteAtas($this->connection, $pauta);

        $sqlMenor = 'SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` WHERE `pauta` = :p AND `ativo` = 1 AND `maioridade` = \'menor\'';
        $stc = $this->connection->prepare($sqlMenor);
        $stc->bindValue(':p', $idPauta);
        $stc->execute();
        if ((int) $stc->fetchColumn() > 0) {
            throw new InvalidArgumentException('Esta audiência envolve menor e não permite inscrição de estudantes.');
        }

        $processoIdPauta = trim((string) ($pauta['processo'] ?? ''));
        if ($processoIdPauta !== '') {
            $codigosBloqueio = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
            $prioridadesRepo = new ProcessoPrioridadesRepository($this->connection);
            foreach ($prioridadesRepo->findByProcessoId($processoIdPauta) as $prioridade) {
                $codigo = trim((string) ($prioridade['codigo'] ?? ''));
                if ($codigo !== '' && in_array($codigo, $codigosBloqueio, true)) {
                    throw new InvalidArgumentException('Esta audiência não está disponível para inscrição de estudantes.');
                }
            }
        }

        $totalInscritos = $this->inscricaoRepository->countInscritosPorPauta($idPauta);
        if ($totalInscritos >= self::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA) {
            throw new InvalidArgumentException(
                'O limite de ' . self::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA . ' estudantes por audiência foi atingido. Não é possível efetuar novas inscrições.'
            );
        }

        $this->inscricaoRepository->insert($idPauta, $usuarioId);
    }

    /**
     * @return array{full_path: string, nome_original: string, mime: string}
     */
    public function getDownloadPayloadForEstudante(string $idPauta, string $usuarioId): array
    {
        $idPauta = trim($idPauta);
        $usuarioId = trim($usuarioId);
        if ($idPauta === '' || $usuarioId === '') {
            throw new InvalidArgumentException('Dados inválidos.');
        }

        $this->purgeAtasExpiradas();

        if (!$this->inscricaoRepository->exists($idPauta, $usuarioId)) {
            throw new InvalidArgumentException('Acesso negado: inscrição não encontrada.');
        }

        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        SalaAudienciasAtaSigiloHelper::assertPautaPermiteAtas($this->connection, $pauta);

        $ata = $this->ataRepository->findByPauta($idPauta);
        if ($ata === null) {
            throw new InvalidArgumentException('Ata ainda não disponível.');
        }

        $expires = trim((string) ($ata['expires_at'] ?? ''));
        if ($expires === '' || strtotime($expires) <= time()) {
            throw new InvalidArgumentException('O prazo para download desta ata expirou.');
        }

        $rel = trim((string) ($ata['arquivo_relativo'] ?? ''));
        if ($rel === '') {
            throw new RuntimeException('Arquivo não localizado.');
        }

        $basePath = dirname(__DIR__, 2);
        $full = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        if (!is_file($full)) {
            throw new RuntimeException('Arquivo não encontrado no servidor.');
        }

        return [
            'full_path' => $full,
            'nome_original' => (string) ($ata['nome_original'] ?? 'ata.pdf'),
            'mime' => (string) ($ata['mime'] ?? 'application/pdf'),
        ];
    }

    /**
     * @return array{full_path: string, nome_original: string, mime: string}
     */
    public function getDownloadPayloadInterno(string $idPauta): array
    {
        $idPauta = trim($idPauta);
        if ($idPauta === '') {
            throw new InvalidArgumentException('Dados inválidos.');
        }

        $this->purgeAtasExpiradas();

        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        SalaAudienciasAtaSigiloHelper::assertPautaPermiteAtas($this->connection, $pauta);

        $ata = $this->ataRepository->findByPauta($idPauta);
        if ($ata === null) {
            throw new InvalidArgumentException('Ata ainda não disponível.');
        }

        $expiresInterno = trim((string) ($ata['expires_at'] ?? ''));
        if ($expiresInterno !== '' && strtotime($expiresInterno) <= time()) {
            throw new InvalidArgumentException('O prazo para download desta ata expirou.');
        }

        $rel = trim((string) ($ata['arquivo_relativo'] ?? ''));
        if ($rel === '') {
            throw new RuntimeException('Arquivo não localizado.');
        }

        $basePath = dirname(__DIR__, 2);
        $full = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        if (!is_file($full)) {
            throw new RuntimeException('Arquivo não encontrado no servidor.');
        }

        return [
            'full_path' => $full,
            'nome_original' => (string) ($ata['nome_original'] ?? 'ata.pdf'),
            'mime' => (string) ($ata['mime'] ?? 'application/pdf'),
        ];
    }

    /**
     * Remove o PDF do disco após o prazo de download e zera a referência no banco, mantendo a linha da ata
     * para a audiência não voltar à fila de “pendente de envio”.
     *
     * @return int número de atas processadas (arquivo removido + registro atualizado)
     */
    public function purgeAtasExpiradas(): int
    {
        $rows = $this->ataRepository->fetchExpiredBeforeNow();
        $basePath = dirname(__DIR__, 2);
        $n = 0;
        foreach ($rows as $row) {
            $rel = trim((string) ($row['arquivo_relativo'] ?? ''));
            if ($rel !== '') {
                $full = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
                if (is_file($full)) {
                    @unlink($full);
                }
            }
            $idAta = trim((string) ($row['id_ata'] ?? ''));
            if ($idAta !== '' && $this->ataRepository->clearArquivoAposExpiracaoRetencao($idAta)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * PDF da ata ainda baixável (referência no disco + prazo `expires_at`).
     *
     * @param array<string, mixed> $row Linha com `arquivo_relativo` e `expires_at` (ex.: join sala_audiencias_ata).
     */
    public static function ataPdfDownloadDisponivelNaRow(array $row): bool
    {
        $arquivo = trim((string) ($row['arquivo_relativo'] ?? ''));
        $exp = trim((string) ($row['expires_at'] ?? ''));
        if ($arquivo === '' || $exp === '') {
            return false;
        }
        $expTs = strtotime($exp);

        return $expTs !== false && $expTs > time();
    }

    /**
     * Data da audiência (campo `data_audiencia`) é estritamente anterior a (hoje − N dias), em calendário civil.
     *
     * @param array<string, mixed> $row
     */
    public static function audienciaDataAnteriorAHojeMenosDias(int $dias, array $row): bool
    {
        $dataAud = trim((string) ($row['data_audiencia'] ?? ''));
        if ($dataAud === '') {
            return false;
        }
        try {
            $d = new \DateTimeImmutable($dataAud . ' 00:00:00');
        } catch (\Throwable $e) {
            return false;
        }
        $limite = (new \DateTimeImmutable('today'))->modify('-' . max(0, $dias) . ' days');

        return $d < $limite;
    }

    /**
     * Ocultar da lista dedicada de atas (interno / estudaata): ocorrida há mais de N dias e sem download ativo.
     *
     * @param array<string, mixed> $row
     */
    public static function ocultarDaListagemAtasPorPrazoSemDownload(array $row, ?int $dias = null): bool
    {
        $dias = $dias ?? self::LISTAGEM_ATAS_OCULTAR_APOS_DIAS_SEM_DOWNLOAD;

        return self::audienciaDataAnteriorAHojeMenosDias($dias, $row) && !self::ataPdfDownloadDisponivelNaRow($row);
    }
}
