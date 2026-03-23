<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GabineteReuPresoRepository.php';
require_once __DIR__ . '/../repositories/GabineteReuPresoProcessoRepository.php';
require_once __DIR__ . '/../repositories/GabineteReuPresoMovimentacaoRepository.php';
require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class GabineteReusPresosService
{
    public const PRIORIDADE_REU_PRESO = 'reu-preso';

    /** Prefixos de tipos que disparam prazo de 90 dias para próxima revisão (CPP art. 316 — referência operacional). */
    public const PREFIXOS_MOVIMENTACAO_REVISAO_90_DIAS = [
        'prisao',
        'manutencao',
        'conversao',
    ];

    public const DIAS_PARA_REVISAO = 90;

    /** Dias até a data de reavaliação ≤ este valor: destaque “atenção” (amarelo). */
    public const DIAS_REVISAO_ATENCAO = 15;

    /** Dias até a data de reavaliação ≤ este valor: destaque “urgente” (vermelho). */
    public const DIAS_REVISAO_URGENTE = 7;

    private PDO $connection;
    private GabineteReuPresoRepository $reuRepo;
    private GabineteReuPresoProcessoRepository $procRepo;
    private GabineteReuPresoMovimentacaoRepository $movRepo;
    private ProcessoPrioridadesRepository $prioridadesRepo;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private PartesRepository $partesRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->reuRepo = new GabineteReuPresoRepository($connection);
        $this->procRepo = new GabineteReuPresoProcessoRepository($connection);
        $this->movRepo = new GabineteReuPresoMovimentacaoRepository($connection);
        $this->prioridadesRepo = new ProcessoPrioridadesRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
    }

    /**
     * @return array<string, string>
     */
    public static function tiposMovimentacao(): array
    {
        return [
            'decretacao_prisao_temporaria' => 'Decretação da prisão temporária',
            'decretacao_prisao_preventiva' => 'Decretação da prisão preventiva',
            'manutencao_prisao_preventiva' => 'Manutenção da prisão preventiva',
            'manutencao_prisao_temporaria' => 'Manutenção da prisão temporária',
            'prisao_em_flagrante' => 'Prisão em flagrante',
            'cumprimento_mandado_prisao' => 'Cumprimento de mandado de prisão',
            'conversao_temporaria_para_preventiva' => 'Conversão de prisão temporária para preventiva',
            'conversao_flagrante_para_preventiva' => 'Conversão de prisão em flagrante para preventiva',
            'soltura' => 'Soltura',
            'revogacao_prisao' => 'Revogação da prisão',
            'oferecimento_denuncia' => 'Oferecimento de denúncia',
            'recebimento_denuncia' => 'Recebimento de denúncia',
            'despacho' => 'Despacho',
            'decisao_interlocutoria' => 'Decisão interlocutória',
            'defesa_previa' => 'Defesa prévia',
            'alegacoes_finais' => 'Alegacões finais',
            'citacao_reu' => 'Citação do réu',
            'designacao_audiencia' => 'Designação de audiência',
            'sentenca' => 'Sentença',
            'anotacao' => 'Anotação',
            'outro' => 'Outro',
        ];
    }

    public function labelTipoMovimentacao(string $codigo): string
    {
        $map = self::tiposMovimentacao();

        return $map[$codigo] ?? $codigo;
    }

    public function create(array $input): string
    {
        $nome = TextFormatter::formatProperName(trim((string) ($input['nome'] ?? '')), 255);
        if ($nome === '') {
            throw new InvalidArgumentException('Nome do réu é obrigatório.');
        }

        $user = trim((string) ($input['user_cadastro'] ?? ''));

        $id = IdGenerator::generateUniquePrefixedId($this->connection, 'gabinete_reu_preso', 'id', 'GRP', 30);
        $this->reuRepo->insert([
            'id' => $id,
            'nome' => $nome,
            'status' => 'ativo',
            'arquivado_em' => null,
            'proxima_revisao' => null,
            'user_cadastro' => $user !== '' ? $user : null,
        ]);

        return $id;
    }

    public function updateNome(string $id, string $nome): void
    {
        $id = trim($id);
        $nomeFmt = TextFormatter::formatProperName(trim($nome), 255);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        if ($nomeFmt === '') {
            throw new InvalidArgumentException('Nome do réu é obrigatório.');
        }
        $row = $this->reuRepo->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Registro não encontrado.');
        }
        if (($row['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Não é possível editar um registro arquivado.');
        }
        $this->reuRepo->updateNome($id, $nomeFmt);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listAtivos(array $filters, int $limit, int $offset): array
    {
        $rows = $this->reuRepo->fetchListAtivos($filters, $limit, $offset);
        $ids = [];
        foreach ($rows as $r) {
            $rid = trim((string) ($r['id'] ?? ''));
            if ($rid !== '') {
                $ids[] = $rid;
            }
        }
        $subPorReu = $this->buildSubtarefaProcessosList($ids);

        foreach ($rows as $i => $r) {
            $rid = trim((string) ($r['id'] ?? ''));
            $rows[$i]['DataPrisao'] = $this->formatDataMovimentoBr((string) ($r['principal_data_prisao'] ?? ''));
            $rows[$i]['TempoCustodiaLabel'] = $this->formatDuracaoCustodia($r['principal_data_prisao'] ?? null);
            $rows[$i]['UltimaMovimentacaoLabel'] = $this->formatUltimaMovLabel($r['ultima_mov_raw'] ?? null);
            $rows[$i]['ProcessoPrincipalLabel'] = trim((string) ($r['processo_principal_numero'] ?? '')) !== ''
                ? (string) $r['processo_principal_numero']
                : '—';
            $rows[$i]['SubtarefaProcessos'] = $subPorReu[$rid] ?? [];
            $rows[$i]['RevisaoDestaque'] = $this->nivelDestaqueProximaRevisao($r['proxima_revisao'] ?? null);
            $rows[$i]['RevisaoDiasHint'] = $this->labelDiasAteRevisao($r['proxima_revisao'] ?? null);
        }

        return [
            'rows' => $rows,
            'total' => $this->reuRepo->countListAtivos($filters),
        ];
    }

    /**
     * Retorna nível visual para a data de próxima reavaliação: sem, normal, atencao, urgente, atrasada.
     */
    public function nivelDestaqueProximaRevisao(?string $proximaYmd): string
    {
        if ($proximaYmd === null || trim($proximaYmd) === '') {
            return 'sem';
        }
        $t = strtotime(trim($proximaYmd));
        if ($t === false) {
            return 'sem';
        }
        $hoje = strtotime('today');
        $diffDays = (int) floor(($t - $hoje) / 86400);
        if ($diffDays < 0) {
            return 'atrasada';
        }
        if ($diffDays <= self::DIAS_REVISAO_URGENTE) {
            return 'urgente';
        }
        if ($diffDays <= self::DIAS_REVISAO_ATENCAO) {
            return 'atencao';
        }

        return 'normal';
    }

    public function labelDiasAteRevisao(?string $proximaYmd): string
    {
        if ($proximaYmd === null || trim($proximaYmd) === '') {
            return '';
        }
        $t = strtotime(trim($proximaYmd));
        if ($t === false) {
            return '';
        }
        $hoje = strtotime('today');
        $diffDays = (int) floor(($t - $hoje) / 86400);
        if ($diffDays < 0) {
            return 'Prazo ultrapassado há ' . (string) abs($diffDays) . ' dia(s).';
        }
        if ($diffDays === 0) {
            return 'Reavaliação prevista para hoje.';
        }

        return 'Faltam ' . (string) $diffDays . ' dia(s) para a reavaliação.';
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $reu = $this->reuRepo->findById($id);
        if ($reu === null) {
            return null;
        }
        $processos = $this->procRepo->findByReuPresoIdWithNumero($id);
        foreach ($processos as $j => $p) {
            $processos[$j]['situacao_label'] = (($p['situacao'] ?? '') === 'solto') ? 'Solto' : 'Preso';
        }
        $principalLinkId = '';
        foreach ($processos as $p) {
            if (!empty($p['principal'])) {
                $principalLinkId = trim((string) ($p['id'] ?? ''));
                break;
            }
        }
        if ($principalLinkId === '' && !empty($processos)) {
            $principalLinkId = trim((string) ($processos[0]['id'] ?? ''));
        }
        $principalProcessoId = '';
        if ($principalLinkId !== '') {
            foreach ($processos as $p) {
                if ((string) ($p['id'] ?? '') === $principalLinkId) {
                    $principalProcessoId = trim((string) ($p['processo_id'] ?? ''));
                    break;
                }
            }
        }
        $movs = $principalProcessoId !== ''
            ? $this->movRepo->findByProcessoIdOrderDesc($principalProcessoId, 200)
            : [];
        foreach ($movs as $k => $m) {
            $movs[$k]['tipo_label'] = $this->labelTipoMovimentacao((string) ($m['tipo_codigo'] ?? ''));
        }

        return [
            'reu' => $reu,
            'processos' => $processos,
            'movimentacoes' => $movs,
        ];
    }

    /**
     * Vincula ou atualiza processo. Garante prioridade reu-preso quando situacao = preso.
     *
     * @param array<string, mixed> $input
     */
    public function saveProcessoLink(array $input): string
    {
        $reuId = trim((string) ($input['reu_preso_id'] ?? ''));
        $linkId = trim((string) ($input['link_id'] ?? ''));
        $numeroRaw = trim((string) ($input['numero_processo'] ?? ''));
        $situacao = trim((string) ($input['situacao'] ?? 'preso'));
        $principal = !empty($input['principal']);
        $dataPrisao = $this->normalizeDateOrNull($input['data_prisao'] ?? null);
        $dataFato = $this->normalizeDateOrNull($input['data_fato'] ?? null);
        $classeCodigo = trim((string) ($input['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($input['classe_descricao'] ?? ''));

        if ($reuId === '') {
            throw new InvalidArgumentException('Identificador do réu é obrigatório.');
        }
        $reu = $this->reuRepo->findById($reuId);
        if ($reu === null) {
            throw new InvalidArgumentException('Réu preso não encontrado.');
        }
        if (($reu['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Não é possível alterar processos de um registro arquivado.');
        }
        if (!in_array($situacao, ['preso', 'solto'], true)) {
            throw new InvalidArgumentException('Situação inválida.');
        }

        $digits = preg_replace('/\D+/', '', $numeroRaw);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos (CNJ).');
        }

        $classeId = null;
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }

        $processoId = $this->processosRepository->ensureProcess($numeroRaw, $classeId, null, false);

        if ($linkId !== '') {
            $existing = $this->procRepo->findById($linkId);
            if ($existing === null || (string) ($existing['reu_preso_id'] ?? '') !== $reuId) {
                throw new InvalidArgumentException('Vínculo inválido.');
            }
            $oldSituacao = (string) ($existing['situacao'] ?? '');
            $oldProc = (string) ($existing['processo_id'] ?? '');
            $this->procRepo->update($linkId, [
                'processo_id' => $processoId,
                'situacao' => $situacao,
                'data_prisao' => $dataPrisao,
                'data_fato' => $dataFato,
            ]);
            // Situação e datas são compartilhadas entre todos os processos vinculados do mesmo réu preso.
            $this->procRepo->updateDadosCustodiaByReuPresoId($reuId, $situacao, $dataPrisao, $dataFato);
            if ($oldProc !== $processoId) {
                $this->removePrioridadeIfPreso($oldProc, $oldSituacao);
            } elseif ($oldSituacao !== $situacao) {
                $this->removePrioridadeIfPreso($processoId, $oldSituacao);
            }
            $allByReu = $this->procRepo->findByReuPresoId($reuId);
            foreach ($allByReu as $ln) {
                $pid = trim((string) ($ln['processo_id'] ?? ''));
                if ($pid !== '') {
                    $this->applyPrioridadeForProcesso($pid, $situacao);
                }
            }
            if ($principal) {
                $this->setPrincipalInternal($reuId, $linkId);
            }
            $this->ensureReuParteVinculadaAoProcesso($reuId, $processoId);
            $this->recalcProximaRevisao($reuId);

            return $linkId;
        }

        $all = $this->procRepo->findByReuPresoId($reuId);
        foreach ($all as $a) {
            if ((string) ($a['processo_id'] ?? '') === $processoId) {
                throw new InvalidArgumentException('Este processo já está vinculado a este réu.');
            }
        }

        $newId = IdGenerator::generateUniquePrefixedId($this->connection, 'gabinete_reu_preso_processo', 'id', 'GPP', 30);
        $count = $this->procRepo->countByReuPresoId($reuId);
        $autoPrincipal = ($count === 0) || $principal;

        $this->procRepo->insert([
            'id' => $newId,
            'reu_preso_id' => $reuId,
            'processo_id' => $processoId,
            'principal' => $autoPrincipal ? 1 : 0,
            'situacao' => $situacao,
            'data_prisao' => $dataPrisao,
            'data_fato' => $dataFato,
        ]);

        $this->applyPrioridadeForProcesso($processoId, $situacao);

        if ($principal && !$autoPrincipal) {
            $this->setPrincipalInternal($reuId, $newId);
        } elseif ($autoPrincipal) {
            $this->procRepo->clearPrincipalForReu($reuId);
            $this->procRepo->update($newId, ['principal' => 1]);
        }
        $this->ensureReuParteVinculadaAoProcesso($reuId, $processoId);
        $this->recalcProximaRevisao($reuId);

        return $newId;
    }

    public function unlinkProcesso(string $linkId): void
    {
        $linkId = trim($linkId);
        if ($linkId === '') {
            throw new InvalidArgumentException('Vínculo obrigatório.');
        }
        $link = $this->procRepo->findById($linkId);
        if ($link === null) {
            throw new InvalidArgumentException('Vínculo não encontrado.');
        }
        $reuId = (string) ($link['reu_preso_id'] ?? '');
        $reu = $this->reuRepo->findById($reuId);
        if ($reu === null || ($reu['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Operação não permitida.');
        }

        $processoId = (string) ($link['processo_id'] ?? '');
        $situacao = (string) ($link['situacao'] ?? '');
        $wasPrincipal = !empty($link['principal']);

        $this->removePrioridadeIfPreso($processoId, $situacao);
        $this->procRepo->deleteById($linkId);

        $remaining = $this->procRepo->findByReuPresoId($reuId);
        if ($wasPrincipal && $remaining !== []) {
            $first = $remaining[0];
            $this->procRepo->update((string) $first['id'], ['principal' => 1]);
        }
    }

    public function setPrincipal(string $reuPresoId, string $linkId): void
    {
        $reuPresoId = trim($reuPresoId);
        $linkId = trim($linkId);
        if ($reuPresoId === '' || $linkId === '') {
            throw new InvalidArgumentException('Dados incompletos.');
        }
        $reu = $this->reuRepo->findById($reuPresoId);
        if ($reu === null || ($reu['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Operação não permitida.');
        }
        $link = $this->procRepo->findById($linkId);
        if ($link === null || (string) ($link['reu_preso_id'] ?? '') !== $reuPresoId) {
            throw new InvalidArgumentException('Vínculo inválido.');
        }
        $this->setPrincipalInternal($reuPresoId, $linkId);
    }

    private function setPrincipalInternal(string $reuPresoId, string $linkId): void
    {
        $this->procRepo->clearPrincipalForReu($reuPresoId);
        $this->procRepo->update($linkId, ['principal' => 1]);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addMovimentacao(array $input): int
    {
        $reuId = trim((string) ($input['reu_preso_id'] ?? ''));
        $tipo = trim((string) ($input['tipo_codigo'] ?? ''));
        $dataMov = $this->normalizeDateOrNull($input['data_movimentacao'] ?? null);
        $obs = trim((string) ($input['observacoes'] ?? ''));
        $user = trim((string) ($input['user_cadastro'] ?? ''));

        if ($reuId === '') {
            throw new InvalidArgumentException('Réu obrigatório.');
        }
        $reu = $this->reuRepo->findById($reuId);
        if ($reu === null) {
            throw new InvalidArgumentException('Réu não encontrado.');
        }
        if (($reu['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Não é possível incluir movimentação em registro arquivado.');
        }
        if ($tipo === '' || !array_key_exists($tipo, self::tiposMovimentacao())) {
            throw new InvalidArgumentException('Tipo de movimentação inválido.');
        }
        if ($dataMov === null || $dataMov === '') {
            throw new InvalidArgumentException('Data da movimentação é obrigatória.');
        }

        $links = $this->procRepo->findByReuPresoId($reuId);
        if ($links === []) {
            throw new InvalidArgumentException('Nenhum processo vinculado para registrar movimentação.');
        }

        $inserted = 0;
        foreach ($links as $link) {
            $linkFk = trim((string) ($link['id'] ?? ''));
            $processoId = trim((string) ($link['processo_id'] ?? ''));
            if ($linkFk === '') {
                continue;
            }
            if ($processoId !== '' && $this->movRepo->existsByProcessoTipoData($processoId, $tipo, $dataMov)) {
                continue;
            }
            $mid = IdGenerator::generateUniquePrefixedId($this->connection, 'gabinete_reu_preso_movimentacao', 'id', 'GPM', 30);
            $this->movRepo->insert([
                'id' => $mid,
                'reu_preso_id' => $reuId,
                'reu_preso_processo_id' => $linkFk,
                'processo_id' => $processoId !== '' ? $processoId : null,
                'tipo_codigo' => $tipo,
                'data_movimentacao' => $dataMov,
                'observacoes' => $obs !== '' ? $obs : null,
                'user_cadastro' => $user !== '' ? $user : null,
            ]);
            $inserted++;
        }

        if ($inserted > 0 && $this->tipoAfetaRevisao($tipo)) {
            $this->recalcProximaRevisao($reuId);
        }

        return $inserted;
    }

    public function deleteMovimentacao(string $reuId, string $movId): int
    {
        $reuId = trim($reuId);
        $movId = trim($movId);
        if ($reuId === '' || $movId === '') {
            throw new InvalidArgumentException('Dados incompletos.');
        }
        $reu = $this->reuRepo->findById($reuId);
        if ($reu === null) {
            throw new InvalidArgumentException('Réu não encontrado.');
        }
        if (($reu['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Não é possível excluir movimentação de registro arquivado.');
        }
        $mov = $this->movRepo->findById($movId);
        if ($mov === null || (string) ($mov['reu_preso_id'] ?? '') !== $reuId) {
            throw new InvalidArgumentException('Movimentação inválida.');
        }
        $tipo = trim((string) ($mov['tipo_codigo'] ?? ''));
        $data = trim((string) ($mov['data_movimentacao'] ?? ''));
        $processoId = trim((string) ($mov['processo_id'] ?? ''));
        if ($processoId === '') {
            $linkFk = trim((string) ($mov['reu_preso_processo_id'] ?? ''));
            if ($linkFk !== '') {
                $lk = $this->procRepo->findById($linkFk);
                if ($lk !== null) {
                    $processoId = trim((string) ($lk['processo_id'] ?? ''));
                }
            }
        }
        if ($tipo === '' || $data === '') {
            throw new InvalidArgumentException('Movimentação inválida.');
        }
        if ($processoId === '') {
            throw new InvalidArgumentException('Movimentação sem processo vinculado.');
        }
        $deleted = $this->movRepo->deleteByProcessoTipoData($processoId, $tipo, $data);
        if ($deleted > 0 && $this->tipoAfetaRevisao($tipo)) {
            $this->recalcProximaRevisao($reuId);
        }

        return $deleted;
    }

    public function archive(string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $reu = $this->reuRepo->findById($id);
        if ($reu === null) {
            throw new InvalidArgumentException('Registro não encontrado.');
        }
        if (($reu['status'] ?? '') !== 'ativo') {
            return;
        }

        $links = $this->procRepo->findByReuPresoId($id);
        foreach ($links as $ln) {
            if (($ln['situacao'] ?? '') === 'preso') {
                $this->prioridadesRepo->deleteByProcessoIdAndPrioridade(
                    (string) $ln['processo_id'],
                    self::PRIORIDADE_REU_PRESO
                );
            }
        }

        $this->reuRepo->updateStatusArquivado($id);
    }

    public function recalcProximaRevisao(string $reuId): void
    {
        $reuId = trim($reuId);
        if ($reuId === '') {
            return;
        }
        $links = $this->procRepo->findByReuPresoId($reuId);
        $processoIds = [];
        foreach ($links as $ln) {
            $pid = trim((string) ($ln['processo_id'] ?? ''));
            if ($pid !== '') {
                $processoIds[] = $pid;
            }
        }
        $max = $this->movRepo->maxDataMovimentacaoForTipoPrefixesByProcessos($processoIds, self::PREFIXOS_MOVIMENTACAO_REVISAO_90_DIAS);
        if ($max === null) {
            $basePrisao = null;
            $linksWithNumero = $this->procRepo->findByReuPresoIdWithNumero($reuId);
            foreach ($linksWithNumero as $ln) {
                if (!empty($ln['principal'])) {
                    $basePrisao = $this->normalizeDateOrNull($ln['data_prisao'] ?? null);
                    break;
                }
            }
            if ($basePrisao === null) {
                foreach ($linksWithNumero as $ln) {
                    $basePrisao = $this->normalizeDateOrNull($ln['data_prisao'] ?? null);
                    if ($basePrisao !== null) {
                        break;
                    }
                }
            }
            if ($basePrisao === null) {
                $this->reuRepo->updateProximaRevisao($reuId, null);
                return;
            }
            $dBase = new DateTimeImmutable($basePrisao . ' 00:00:00');
            $proxBase = $dBase->modify('+' . self::DIAS_PARA_REVISAO . ' days')->format('Y-m-d');
            $this->reuRepo->updateProximaRevisao($reuId, $proxBase);

            return;
        }
        $d = new DateTimeImmutable($max . ' 00:00:00');
        $prox = $d->modify('+' . self::DIAS_PARA_REVISAO . ' days')->format('Y-m-d');
        $this->reuRepo->updateProximaRevisao($reuId, $prox);
    }

    private function tipoAfetaRevisao(string $tipoCodigo): bool
    {
        $tipo = mb_strtolower(trim($tipoCodigo), 'UTF-8');
        if ($tipo === '') {
            return false;
        }
        foreach (self::PREFIXOS_MOVIMENTACAO_REVISAO_90_DIAS as $prefixo) {
            if (str_contains($tipo, mb_strtolower((string) $prefixo, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function applyPrioridadeForProcesso(string $processoId, string $situacao): void
    {
        if ($situacao === 'preso') {
            $this->prioridadesRepo->insert($processoId, self::PRIORIDADE_REU_PRESO);
        } else {
            $this->prioridadesRepo->deleteByProcessoIdAndPrioridade($processoId, self::PRIORIDADE_REU_PRESO);
        }
    }

    /** Remove prioridade reu-preso quando o vínculo estava como preso (troca de processo ou situação). */
    private function removePrioridadeIfPreso(string $processoId, string $situacao): void
    {
        if ($situacao === 'preso') {
            $this->prioridadesRepo->deleteByProcessoIdAndPrioridade($processoId, self::PRIORIDADE_REU_PRESO);
        }
    }

    /** @param mixed $v */
    private function normalizeDateOrNull($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $s);

        return $d !== false ? $d->format('Y-m-d') : null;
    }

    public function formatDuracaoCustodia(?string $dataPrisaoYmd): string
    {
        if ($dataPrisaoYmd === null || $dataPrisaoYmd === '') {
            return '—';
        }
        $d1 = DateTime::createFromFormat('Y-m-d', (string) $dataPrisaoYmd);
        if ($d1 === false) {
            return '—';
        }
        $d1->setTime(0, 0, 0);
        $hoje = new DateTime('today');
        if ($hoje < $d1) {
            return '—';
        }
        $n = (int) $d1->diff($hoje)->days + 1;

        return $n >= 0 ? (string) $n . ' dias' : '—';
    }

    private function formatUltimaMovLabel(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        $parts = explode('|', $raw, 2);
        $tipo = $parts[0] ?? '';
        $data = $parts[1] ?? '';
        $label = $this->labelTipoMovimentacao($tipo);
        $dataBr = '';
        if ($data !== '') {
            $t = strtotime($data);
            $dataBr = $t !== false ? date('d/m/Y', $t) : $data;
        }

        return trim($label . ($dataBr !== '' ? ' — ' . $dataBr : ''));
    }

    /**
     * Linhas da subtarefa (expandir): um processo por linha; principal com colunas esq./dir.; demais compactos.
     *
     * @param list<string> $reuIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildSubtarefaProcessosList(array $reuIds): array
    {
        $byReu = [];
        foreach ($reuIds as $rid) {
            $t = trim((string) $rid);
            if ($t !== '') {
                $byReu[$t] = [];
            }
        }
        if ($byReu === []) {
            return [];
        }

        $procRows = $this->procRepo->fetchProcessosSubtarefaPorReuIds(array_keys($byReu));
        $processoIds = [];
        foreach ($procRows as $pr) {
            $pid = trim((string) ($pr['processo_id'] ?? ''));
            if ($pid !== '') {
                $processoIds[] = $pid;
            }
        }
        $ultMap = $this->movRepo->fetchUltimaPorProcessoIds($processoIds);

        foreach ($procRows as $pr) {
            $rid = trim((string) ($pr['reu_preso_id'] ?? ''));
            $lid = trim((string) ($pr['id'] ?? ''));
            $pid = trim((string) ($pr['processo_id'] ?? ''));
            if ($rid === '' || $lid === '' || !isset($byReu[$rid])) {
                continue;
            }
            $principal = !empty($pr['principal']);
            $numero = trim((string) ($pr['numero_processo'] ?? ''));
            $classe = trim((string) ($pr['classe_nome'] ?? ''));
            $numeroLabel = $numero !== '' ? $numero : '—';
            $classeLabel = $classe !== '' ? $classe : 'Classe não informada';
            $mov = $pid !== '' ? ($ultMap[$pid] ?? null) : null;
            $tipoLabel = $mov !== null ? $this->labelTipoMovimentacao((string) ($mov['tipo_codigo'] ?? '')) : '—';
            $dataBr = $mov !== null ? $this->formatDataMovimentoBr((string) ($mov['data_movimentacao'] ?? '')) : '—';
            $compacto = $numero !== '' && $classe !== ''
                ? $numero . ' (' . $classe . ')'
                : ($numero !== '' ? $numero : '—');

            $byReu[$rid][] = [
                'link_id' => $lid,
                'principal' => $principal,
                'numero_processo' => $numeroLabel,
                'classe_nome' => $classeLabel,
                'texto_compacto' => $compacto,
                'ultima_movimentacao' => $tipoLabel,
                'ultima_movimentacao_data' => $dataBr,
            ];
        }

        return $byReu;
    }

    private function formatDataMovimentoBr(string $ymd): string
    {
        if ($ymd === '') {
            return '—';
        }
        $t = strtotime($ymd);

        return $t !== false ? date('d/m/Y', $t) : '—';
    }

    private function ensureReuParteVinculadaAoProcesso(string $reuId, string $processoId): void
    {
        $reu = $this->reuRepo->findById($reuId);
        if ($reu === null) {
            return;
        }
        $nomeReu = trim((string) ($reu['nome'] ?? ''));
        if ($nomeReu === '') {
            return;
        }
        $parteId = $this->partesRepository->ensureParteByNomeOuCpf($nomeReu, null);
        $existente = $this->processosPartesRepository->findByProcessoAndParte($processoId, $parteId);
        if ($existente !== null) {
            return;
        }
        $this->processosPartesRepository->addParte(
            $processoId,
            $parteId,
            ProcessosPartesRepository::TIPO_REU,
            ProcessosPartesRepository::POLO_PASSIVO
        );
    }
}
