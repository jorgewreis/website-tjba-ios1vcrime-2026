<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GabineteReuPresoRepository.php';
require_once __DIR__ . '/../repositories/GabineteReuPresoProcessoRepository.php';
require_once __DIR__ . '/../repositories/GabineteReuPresoMovimentacaoRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/ProcessoPrioridadesService.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/ProcessoBlocoListagemHelper.php';

final class GabineteReusPresosService
{
    /** Prefixos de tipos que disparam prazo de 90 dias para próxima revisão (CPP art. 316 — referência operacional). */
    public const PREFIXOS_MOVIMENTACAO_REVISAO_90_DIAS = [
        'decretacao',
        'manutencao',
        'prisao',
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
    private SalaAudienciasPautaRepository $pautaRepo;
    private ?ProcessoPrioridadesService $processoPrioridadesService = null;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private PartesRepository $partesRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $connection, bool $substituto = false)
    {
        $this->connection = $connection;
        $this->reuRepo = new GabineteReuPresoRepository($connection, 'gabinete_reu_preso', $substituto ? 'substituto' : 'principal');
        $this->procRepo = new GabineteReuPresoProcessoRepository($connection);
        $this->movRepo = new GabineteReuPresoMovimentacaoRepository($connection);
        $this->pautaRepo = new SalaAudienciasPautaRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
    }

    private function processoPrioridadesService(): ProcessoPrioridadesService
    {
        if ($this->processoPrioridadesService === null) {
            $this->processoPrioridadesService = new ProcessoPrioridadesService($this->connection);
        }

        return $this->processoPrioridadesService;
    }

    /** Reavalia prioridades automáticas (réu preso, menor) após alterações no gabinete. */
    private function reconciliarPrioridadesProcesso(string $processoId): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }
        $proc = $this->processosRepository->findById($processoId);
        $numero = $proc !== null ? trim((string) ($proc['numero_processo'] ?? '')) : '';
        $this->processoPrioridadesService()->reconciliarAutomaticas($processoId, $numero, 0);
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
            foreach ($rows[$i]['SubtarefaProcessos'] as $subLinha) {
                if (!empty($subLinha['principal'])) {
                    $dataUlt = trim((string) ($subLinha['ultima_movimentacao_data'] ?? ''));
                    $tipoUlt = trim((string) ($subLinha['ultima_movimentacao'] ?? ''));
                    if ($dataUlt !== '' && $dataUlt !== '—' && $tipoUlt !== '' && $tipoUlt !== '—') {
                        $rows[$i]['UltimaMovimentacaoLabel'] = $tipoUlt . ' — ' . $dataUlt;
                    }
                    break;
                }
            }
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
        $processos = $this->appendBlocoProcessoEmLinhas($processos);
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
        $movs = $this->movRepo->findByReuPresoIdOrderDesc($id, 200);
        foreach ($movs as $k => $m) {
            $movs[$k]['tipo_label'] = $this->labelTipoMovimentacao((string) ($m['tipo_codigo'] ?? ''));
            $movs[$k]['is_virtual'] = false;
        }

        if ($principalProcessoId !== '') {
            foreach ($this->movimentacoesVirtuaisAudiencias($id, $principalProcessoId) as $movAud) {
                $movs[] = $movAud;
            }
        }

        usort($movs, static function (array $a, array $b): int {
            $ta = strtotime((string) ($a['data_movimentacao'] ?? ''));
            $tb = strtotime((string) ($b['data_movimentacao'] ?? ''));
            if ($ta === false) {
                $ta = 0;
            }
            if ($tb === false) {
                $tb = 0;
            }
            if ($ta === $tb) {
                $ca = strtotime((string) ($a['created_at'] ?? ''));
                $cb = strtotime((string) ($b['created_at'] ?? ''));
                if ($ca === false) {
                    $ca = 0;
                }
                if ($cb === false) {
                    $cb = 0;
                }

                return $cb <=> $ca;
            }

            return $tb <=> $ta;
        });

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

        $processoId = $this->processosRepository->ensureProcess($numeroRaw, $classeId, null, null);

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
            if ($oldProc !== $processoId && $oldProc !== '') {
                $this->reconciliarPrioridadesProcesso($oldProc);
            }
            $allByReu = $this->procRepo->findByReuPresoId($reuId);
            foreach ($allByReu as $ln) {
                $pid = trim((string) ($ln['processo_id'] ?? ''));
                if ($pid !== '') {
                    $this->reconciliarPrioridadesProcesso($pid);
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

        $this->reconciliarPrioridadesProcesso($processoId);

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

        $this->procRepo->deleteById($linkId);
        $this->reconciliarPrioridadesProcesso($processoId);

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
            if ($processoId !== '' && $this->movRepo->existsByReuProcessoTipoData($reuId, $processoId, $tipo, $dataMov)) {
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

        if ($this->tipoAfetaRevisao($tipo)) {
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
        if ($tipo === '' || $data === '') {
            throw new InvalidArgumentException('Movimentação inválida.');
        }
        $deleted = $this->movRepo->deleteById($movId);
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
        $processosParaReconciliar = [];
        foreach ($links as $ln) {
            $pid = trim((string) ($ln['processo_id'] ?? ''));
            if ($pid !== '') {
                $processosParaReconciliar[$pid] = true;
            }
        }

        $this->reuRepo->updateStatusArquivado($id);

        foreach (array_keys($processosParaReconciliar) as $pid) {
            $this->reconciliarPrioridadesProcesso($pid);
        }
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
        $max = $this->movRepo->maxDataMovimentacaoForTipoPrefixesByReuAndProcessos($reuId, $processoIds, self::PREFIXOS_MOVIMENTACAO_REVISAO_90_DIAS);
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
            if (str_starts_with($tipo, mb_strtolower((string) $prefixo, 'UTF-8'))) {
                return true;
            }
        }

        return false;
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
        $parts = explode('|', $raw, 3);
        $tipo = trim((string) ($parts[0] ?? ''));
        $data = trim((string) ($parts[1] ?? ''));
        $label = $this->labelTipoMovimentacao($tipo);
        $dataBr = '';
        if ($data !== '') {
            $t = strtotime($data);
            $dataBr = $t !== false ? date('d/m/Y', $t) : $data;
        }

        return trim($label . ($dataBr !== '' ? ' — ' . $dataBr : ''));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movimentacoesVirtuaisAudiencias(string $reuPresoId, string $processoId): array
    {
        $out = [];
        $audiencias = $this->pautaRepo->listByProcessoId($processoId);
        foreach ($audiencias as $aud) {
            $mov = $this->montarMovimentacaoVirtualAudiencia($reuPresoId, $aud);
            if ($mov !== null) {
                $out[] = $mov;
            }
        }

        return $out;
    }

    private function ultimaMovimentacaoVirtualAudiencia(string $reuPresoId, string $processoId): ?array
    {
        $movs = $this->movimentacoesVirtuaisAudiencias($reuPresoId, $processoId);
        if ($movs === []) {
            return null;
        }
        usort($movs, fn (array $a, array $b): int => $this->compararMovimentacoes($a, $b));

        return $movs[0];
    }

    private function montarMovimentacaoVirtualAudiencia(string $reuPresoId, array $aud): ?array
    {
        $statusRaw = strtolower(trim((string) ($aud['status'] ?? '')));
        if ($statusRaw === 'agendada') {
            $statusRaw = 'designada';
        } elseif ($statusRaw === 'adiada') {
            $statusRaw = 'redesignada';
        } elseif ($statusRaw === 'realizada') {
            $statusRaw = 'finalizada';
        }
        if ($statusRaw === '') {
            return null;
        }

        $tipoAud = trim((string) ($aud['tipo_audiencia'] ?? ''));
        $tipoTexto = 'Audiência' . ($tipoAud !== '' ? ' de ' . $tipoAud : '');
        $horaAudRaw = trim((string) ($aud['hora_audiencia'] ?? ''));
        $horaAud = '—';
        if (preg_match('/^(\d{1,2}):(\d{2})/', $horaAudRaw, $hm)) {
            $horaAud = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
        }

        $dataMov = trim((string) ($aud['data_audiencia'] ?? ''));
        $dataAudienciaBr = $this->formatDataMovimentoBr($dataMov);
        $createdAt = trim((string) ($aud['updated_at'] ?? ''));
        $dataAlteracaoBr = $createdAt !== '' && ($tAlt = strtotime($createdAt)) !== false ? date('d/m/Y', $tAlt) : '';

        if ($statusRaw === 'designada') {
            $descricao = $tipoTexto . ' designada para as ' . $horaAud;
            $createdAt = $dataMov !== '' ? $dataMov . ' ' . ($horaAud !== '—' ? $horaAud . ':00' : '00:00:00') : $createdAt;
        } elseif ($statusRaw === 'em_andamento') {
            $descricao = $tipoTexto . ' em andamento';
            $inicioEfetivo = trim((string) ($aud['data_hora_inicio_efetivo'] ?? ''));
            if ($inicioEfetivo !== '') {
                $dataMov = substr($inicioEfetivo, 0, 10);
                $createdAt = $inicioEfetivo;
            }
        } elseif ($statusRaw === 'finalizada') {
            $descricao = $tipoTexto . ' realizada';
            $fimEfetivo = trim((string) ($aud['data_hora_fim_efetivo'] ?? ''));
            if ($fimEfetivo !== '') {
                $dataMov = substr($fimEfetivo, 0, 10);
                $createdAt = $fimEfetivo;
            }
        } elseif ($statusRaw === 'redesignada') {
            $descricao = $tipoTexto . ' redesignada' . ($dataAudienciaBr !== '—' ? ' em ' . $dataAudienciaBr : '');
            if ($dataAlteracaoBr !== '') {
                $dataMov = date('Y-m-d', strtotime($createdAt));
            }
        } elseif ($statusRaw === 'cancelada') {
            $descricao = $tipoTexto . ' cancelada' . ($dataAudienciaBr !== '—' ? ' em ' . $dataAudienciaBr : '');
            if ($dataAlteracaoBr !== '') {
                $dataMov = date('Y-m-d', strtotime($createdAt));
            }
        } else {
            $descricao = $tipoTexto;
        }

        return [
            'id' => 'AUD-' . trim((string) ($aud['id_pauta'] ?? '')),
            'reu_preso_id' => $reuPresoId,
            'tipo_codigo' => 'audiencia_' . $statusRaw,
            'tipo_label' => $descricao,
            'data_movimentacao' => $dataMov,
            'observacoes' => null,
            'created_at' => $createdAt !== '' ? $createdAt : null,
            'is_virtual' => true,
        ];
    }

    private function compararMovimentacoes(?array $a, ?array $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }
        $ta = strtotime((string) ($a['data_movimentacao'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['data_movimentacao'] ?? '')) ?: 0;
        if ($ta !== $tb) {
            return $tb <=> $ta;
        }
        $ca = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $cb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

        return $cb <=> $ca;
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
        $pares = [];
        foreach ($procRows as $pr) {
            $rid = trim((string) ($pr['reu_preso_id'] ?? ''));
            $pid = trim((string) ($pr['processo_id'] ?? ''));
            if ($rid !== '' && $pid !== '') {
                $pares[] = [$rid, $pid];
            }
        }
        $ultMap = $this->movRepo->fetchUltimaPorReuPresoEProcessoIds($pares);

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
            $movKey = $rid !== '' && $pid !== '' ? $rid . '|' . $pid : '';
            $mov = $movKey !== '' ? ($ultMap[$movKey] ?? null) : null;
            $movAudiencia = $this->ultimaMovimentacaoVirtualAudiencia($rid, $pid);
            if ($movAudiencia !== null && $this->compararMovimentacoes($movAudiencia, $mov) < 0) {
                $mov = $movAudiencia;
            }
            $tipoLabel = $mov !== null ? $this->labelTipoMovimentacao((string) ($mov['tipo_codigo'] ?? '')) : '—';
            if ($mov !== null && !empty($mov['tipo_label'])) {
                $tipoLabel = (string) $mov['tipo_label'];
            }
            $dataBr = $mov !== null ? $this->formatDataMovimentoBr((string) ($mov['data_movimentacao'] ?? '')) : '—';
            $obsMov = $mov !== null ? trim((string) ($mov['observacoes'] ?? '')) : '';
            $compacto = $numero !== '' && $classe !== ''
                ? $numero . ' (' . $classe . ')'
                : ($numero !== '' ? $numero : '—');

            $byReu[$rid][] = [
                'link_id' => $lid,
                'processo_id' => $pid,
                'principal' => $principal,
                'numero_processo' => $numero !== '' ? $numero : $numeroLabel,
                'classe_nome' => $classeLabel,
                'classe_processo' => $classeLabel,
                'texto_compacto' => $compacto,
                'ultima_movimentacao' => $tipoLabel,
                'ultima_movimentacao_data' => $dataBr,
                'ultima_movimentacao_observacao' => $obsMov,
            ];
        }

        $processoIdsReconciliar = [];
        foreach ($procRows as $pr) {
            $pid = trim((string) ($pr['processo_id'] ?? ''));
            if ($pid !== '') {
                $processoIdsReconciliar[$pid] = true;
            }
        }
        foreach (array_keys($processoIdsReconciliar) as $pid) {
            $this->reconciliarPrioridadesProcesso($pid);
        }

        $flat = [];
        $flatKeys = [];
        foreach ($byReu as $rid => $lista) {
            foreach ($lista as $j => $linha) {
                $flat[] = $linha;
                $flatKeys[] = [$rid, $j];
            }
        }
        if ($flat !== []) {
            $flat = $this->appendBlocoProcessoEmLinhas($flat);
            foreach ($flat as $i => $enriched) {
                [$rid, $j] = $flatKeys[$i];
                $byReu[$rid][$j] = $enriched;
            }
        }

        return $byReu;
    }

    /**
     * Enriquece linhas com chaves `bloco_processo_*` para `_processo_bloco_listagem.php`.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function appendBlocoProcessoEmLinhas(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $input = [];
        foreach ($rows as $row) {
            $numero = trim((string) ($row['numero_processo'] ?? ''));
            if ($numero === '—' || $numero === '–') {
                $numero = '';
            }
            $input[] = array_merge($row, [
                'processo_id' => trim((string) ($row['processo_id'] ?? '')),
                'numero_processo' => $numero,
                'classe_processo' => trim((string) ($row['classe_processo'] ?? $row['classe_nome'] ?? '')),
            ]);
        }

        return ProcessoBlocoListagemHelper::appendBlocoData($this->connection, $input);
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
