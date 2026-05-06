<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/ProdutividadeRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLancamentosRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLogRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeMetasRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeBadgesRepository.php';
require_once __DIR__ . '/../repositories/FeriadosRepository.php';
require_once __DIR__ . '/../access/AccessAreas.php';
require_once __DIR__ . '/../helpers/UsuarioPerfisAutorizacao.php';

final class ProdutividadeService
{
    private PDO $connection;
    private ProdutividadeRegrasRepository $regrasRepository;
    private ProdutividadeLancamentosRepository $lancamentosRepository;
    private ProdutividadeLogRepository $logRepository;
    private ProdutividadeMetasRepository $metasRepository;
    private ProdutividadeBadgesRepository $badgesRepository;
    /** @var array<string, array<string, true>> */
    private array $feriadosSetCache = [];
    private ?FeriadosRepository $feriadosRepository = null;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->regrasRepository = new ProdutividadeRegrasRepository($connection);
        $this->lancamentosRepository = new ProdutividadeLancamentosRepository($connection);
        $this->logRepository = new ProdutividadeLogRepository($connection);
        $this->metasRepository = new ProdutividadeMetasRepository($connection);
        $this->badgesRepository = new ProdutividadeBadgesRepository($connection);
    }

    public function listRules(string $actorUserId, array $filters, int $limit, int $offset): array
    {
        $this->assertGestao($actorUserId);
        return [
            'rows' => $this->regrasRepository->fetchList($filters, $limit, $offset),
            'total' => $this->regrasRepository->count($filters),
        ];
    }

    public function findRule(string $actorUserId, string $id): ?array
    {
        $this->assertGestao($actorUserId);
        return $this->regrasRepository->findById($id);
    }

    /**
     * @return array{tipos_ato:list<string>,tipos_documento:list<string>,sistemas_modulo:list<string>}
     */
    public function listRuleCatalogValues(string $actorUserId): array
    {
        $this->assertGestao($actorUserId);
        return $this->regrasRepository->listDistinctCatalogValues();
    }

    public function createRule(string $actorUserId, array $payload): string
    {
        $this->assertGestao($actorUserId);
        $row = $this->normalizeRulePayload($payload);
        $row['criado_por'] = $actorUserId;
        return $this->regrasRepository->insert($row);
    }

    public function updateRule(string $actorUserId, string $id, array $payload): bool
    {
        $this->assertGestao($actorUserId);
        $current = $this->regrasRepository->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('Regra não encontrada.');
        }
        $row = $this->normalizeRulePayload($payload, $current);
        return $this->regrasRepository->update($id, $row);
    }

    public function setRuleAtivo(string $actorUserId, string $id, bool $ativo): bool
    {
        $this->assertGestao($actorUserId);
        return $this->regrasRepository->setAtivo($id, $ativo);
    }

    /**
     * @return list<array{id:string,label:string,tipo_ato:string,tipo_documento:string,sistema_modulo:string,pontos_por_ato:string,pontos_multiplicador:string}>
     */
    public function searchRulesAtivas(string $actorUserId, string $query, int $limit = 10): array
    {
        $this->assertAuthenticated($actorUserId);
        return $this->regrasRepository->searchAtivas($query, $limit);
    }

    /**
     * Regras cujo `sistema_modulo` pertence a fluxos com pontuação exclusivamente automática
     * (cartório, sala de mídias) não entram na busca de lançamento manual nem podem ser salvas via API manual.
     *
     * @param array<string, mixed> $regra
     */
    private function regraReservadaAoLancamentoAutomatico(array $regra): bool
    {
        $modulo = trim((string) ($regra['sistema_modulo'] ?? ''));
        if ($modulo === '') {
            return false;
        }

        return str_starts_with($modulo, 'cartorio-') || str_starts_with($modulo, 'sala-midias');
    }

    public function createManualLancamento(string $actorUserId, array $payload): string
    {
        $this->assertAuthenticated($actorUserId);
        $regraId = trim((string) ($payload['regra_id'] ?? ''));
        if ($regraId === '') {
            throw new InvalidArgumentException('Regra é obrigatória.');
        }
        $regra = $this->regrasRepository->findAtivaById($regraId);
        if ($regra === null) {
            throw new InvalidArgumentException('Regra inválida ou inativa.');
        }
        if ($this->regraReservadaAoLancamentoAutomatico($regra)) {
            throw new InvalidArgumentException(
                'Esta tarefa é pontuada automaticamente pelo sistema e não pode ser lançada manualmente.'
            );
        }

        $quantidade = (int) ($payload['quantidade'] ?? 0);
        if ($quantidade <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? ''));
        if ($numeroProcesso !== '' && preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $numeroProcesso) !== 1) {
            throw new InvalidArgumentException('Número do processo deve estar no formato CNJ completo.');
        }

        $classeProcesso = trim((string) ($payload['classe_processo'] ?? ''));
        $observacoes = trim((string) ($payload['observacoes'] ?? ''));
        $dataHoraAto = trim((string) ($payload['data_hora_ato'] ?? ''));
        if ($dataHoraAto === '') {
            $dataHoraAto = date('Y-m-d H:i:s');
        } else {
            $ts = strtotime($dataHoraAto);
            if ($ts === false) {
                throw new InvalidArgumentException('Data/hora inválida.');
            }
            $dataHoraAto = date('Y-m-d H:i:s', $ts);
        }

        $pontosPorAto = (float) ($regra['pontos_por_ato'] ?? 0);
        $multiplicador = (float) ($regra['pontos_multiplicador'] ?? 1);
        $pontosCalculados = $quantidade * $pontosPorAto * $multiplicador;
        $contextoHash = null;
        if ($numeroProcesso !== '') {
            $contextoHash = hash('sha256', implode('|', [
                $actorUserId,
                $regraId,
                $numeroProcesso,
                date('Y-m-d H:i', strtotime($dataHoraAto)),
            ]));
        }

        $this->connection->beginTransaction();
        try {
            $lancamentoId = $this->lancamentosRepository->insert([
                'usuario_id' => $actorUserId,
                'regra_id' => $regraId,
                'tipo_lancamento' => 'manual',
                'status' => 'ativo',
                'quantidade' => $quantidade,
                'pontos_calculados' => number_format($pontosCalculados, 2, '.', ''),
                'contexto_hash' => $contextoHash,
                'referencia_item_id' => null,
                'referencia_sistema' => 'manual',
                'numero_processo' => $numeroProcesso !== '' ? $numeroProcesso : null,
                'classe_processo' => $classeProcesso !== '' ? $classeProcesso : null,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'data_hora_ato' => $dataHoraAto,
            ]);
            $this->logRepository->insert($lancamentoId, $actorUserId, 'criar_manual', [
                'regra_id' => $regraId,
                'quantidade' => $quantidade,
                'pontos_calculados' => $pontosCalculados,
                'numero_processo' => $numeroProcesso,
            ]);
            $this->connection->commit();
            try {
                $this->verificarBadges($actorUserId);
            } catch (Throwable $ignored) {
                // Badge não pode quebrar o fluxo principal do lançamento.
            }
            return $lancamentoId;
        } catch (PDOException $e) {
            $this->connection->rollBack();
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('Já existe um lançamento manual equivalente no mesmo minuto para esta regra e processo.');
            }
            throw $e;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function anularLancamentoManual(string $actorUserId, string $lancamentoId, string $motivo): bool
    {
        $this->assertAuthenticated($actorUserId);
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Motivo da anulação é obrigatório.');
        }

        $row = $this->lancamentosRepository->findById($lancamentoId);
        if ($row === null) {
            throw new InvalidArgumentException('Lançamento não encontrado.');
        }
        if ((string) ($row['tipo_lancamento'] ?? '') !== 'manual') {
            throw new InvalidArgumentException('Apenas lançamentos manuais podem ser anulados nesta fase.');
        }
        if ((string) ($row['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Lançamento já está anulado.');
        }

        $this->assertPodeAnularLancamentoManual($actorUserId, $row);

        $this->connection->beginTransaction();
        try {
            $ok = $this->lancamentosRepository->anular($lancamentoId, $actorUserId, $motivo);
            if (!$ok) {
                throw new RuntimeException('Não foi possível anular o lançamento.');
            }
            $this->logRepository->insert($lancamentoId, $actorUserId, 'anular_manual', [
                'motivo' => $motivo,
            ]);
            $this->connection->commit();
            return true;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Espelha as regras de {@see self::anularLancamentoManual} para exibir o botão na listagem.
     *
     * @param array<string, mixed> $row Linha com tipo_lancamento, status, usuario_id, criado_em
     */
    public function canActorAnularLancamentoManual(string $actorUserId, array $row): bool
    {
        $actorUserId = trim($actorUserId);
        if ($actorUserId === '') {
            return false;
        }
        if ((string) ($row['tipo_lancamento'] ?? '') !== 'manual') {
            return false;
        }
        if ((string) ($row['status'] ?? '') !== 'ativo') {
            return false;
        }

        return $this->actorPodeAnularLancamentoManualSemChecarTipoStatus($actorUserId, $row);
    }

    /**
     * @return array{created:bool,id:?string,reason:string}
     */
    public function registrarAuto(string $actorUserId, array $payload): array
    {
        $this->assertAuthenticated($actorUserId);

        $tipoAto = trim((string) ($payload['tipo_ato'] ?? ''));
        $tipoDocumento = trim((string) ($payload['tipo_documento'] ?? ''));
        $sistemaModulo = trim((string) ($payload['sistema_modulo'] ?? ''));

        $regraIdHint = trim((string) ($payload['regra_id'] ?? ''));
        $regra = null;
        if ($regraIdHint !== '') {
            $regra = $this->regrasRepository->findAtivaById($regraIdHint);
        }
        if ($regra === null) {
            $regra = $this->regrasRepository->findAtivaByContext($tipoAto, $tipoDocumento, $sistemaModulo);
        }
        if ($regra === null) {
            return ['created' => false, 'id' => null, 'reason' => 'regra_inexistente_ou_inativa'];
        }

        $quantidade = (int) ($payload['quantidade'] ?? 1);
        if ($quantidade <= 0) {
            $quantidade = 1;
        }

        $dataHoraAto = trim((string) ($payload['data_hora_ato'] ?? ''));
        if ($dataHoraAto === '') {
            $dataHoraAto = date('Y-m-d H:i:s');
        } else {
            $ts = strtotime($dataHoraAto);
            if ($ts === false) {
                $dataHoraAto = date('Y-m-d H:i:s');
            } else {
                $dataHoraAto = date('Y-m-d H:i:s', $ts);
            }
        }

        $referenciaItemId = trim((string) ($payload['referencia_item_id'] ?? ''));
        $referenciaSistema = trim((string) ($payload['referencia_sistema'] ?? $sistemaModulo));
        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? ''));
        $classeProcesso = trim((string) ($payload['classe_processo'] ?? ''));
        $observacoes = trim((string) ($payload['observacoes'] ?? ''));

        $contextoHash = null;
        if ($referenciaItemId !== '') {
            $forcedHash = trim((string) ($payload['contexto_hash_forced'] ?? ''));
            if ($forcedHash !== '') {
                $contextoHash = $forcedHash;
            } else {
                $contextoHash = hash('sha256', implode('|', [
                    $actorUserId,
                    $tipoAto,
                    $tipoDocumento,
                    $sistemaModulo,
                    $referenciaItemId,
                    date('Y-m-d H:i', strtotime($dataHoraAto)),
                ]));
            }
        }

        $pontosPorAto = (float) ($regra['pontos_por_ato'] ?? 0);
        $multiplicador = (float) ($regra['pontos_multiplicador'] ?? 1);
        if (array_key_exists('pontos_calculados_override', $payload)) {
            $pontosCalculados = (float) $payload['pontos_calculados_override'];
        } else {
            $pontosCalculados = $quantidade * $pontosPorAto * $multiplicador;
        }

        // Cartório (e outros) frequentemente chamam registrarAuto dentro de transação já aberta;
        // PDO não permite beginTransaction aninhado — participar da transação externa.
        $ownTransaction = !$this->connection->inTransaction();
        if ($ownTransaction) {
            $this->connection->beginTransaction();
        }
        try {
            $lancamentoId = $this->lancamentosRepository->insert([
                'usuario_id' => $actorUserId,
                'regra_id' => (string) ($regra['id'] ?? ''),
                'tipo_lancamento' => 'automatico',
                'status' => 'ativo',
                'quantidade' => $quantidade,
                'pontos_calculados' => number_format($pontosCalculados, 2, '.', ''),
                'contexto_hash' => $contextoHash,
                'referencia_item_id' => $referenciaItemId !== '' ? $referenciaItemId : null,
                'referencia_sistema' => $referenciaSistema !== '' ? $referenciaSistema : null,
                'numero_processo' => $numeroProcesso !== '' ? $numeroProcesso : null,
                'classe_processo' => $classeProcesso !== '' ? $classeProcesso : null,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'data_hora_ato' => $dataHoraAto,
            ]);
            $this->logRepository->insert($lancamentoId, $actorUserId, 'criar_automatico', [
                'regra_id' => (string) ($regra['id'] ?? ''),
                'quantidade' => $quantidade,
                'pontos_calculados' => $pontosCalculados,
                'referencia_item_id' => $referenciaItemId,
                'sistema_modulo' => $sistemaModulo,
                'tipo_ato' => $tipoAto,
                'tipo_documento' => $tipoDocumento,
            ]);
            if ($ownTransaction) {
                $this->connection->commit();
            }
        } catch (PDOException $e) {
            if ($ownTransaction) {
                $this->connection->rollBack();
            }
            if (($e->errorInfo[1] ?? null) === 1062) {
                return ['created' => false, 'id' => null, 'reason' => 'duplicado'];
            }
            throw $e;
        } catch (Throwable $e) {
            if ($ownTransaction) {
                $this->connection->rollBack();
            }
            throw $e;
        }

        try {
            $this->verificarBadges($actorUserId);
        } catch (Throwable $ignored) {
            // Badge não pode quebrar o fluxo principal do lançamento.
        }

        return ['created' => true, 'id' => $lancamentoId, 'reason' => 'ok'];
    }

    public function registrarEstornoSaldoReferencia(string $actorUserId, string $referenciaItemId, string $referenciaSistema, string $motivoObs = ''): void
    {
        $this->assertAuthenticated($actorUserId);
        $referenciaItemId = trim($referenciaItemId);
        $referenciaSistema = trim($referenciaSistema);
        if ($referenciaItemId === '' || $referenciaSistema === '') {
            return;
        }
        $net = $this->lancamentosRepository->sumNetAutomaticoAtivo($referenciaItemId, $referenciaSistema);
        if ($net <= 0.00001) {
            return;
        }
        $neg = -1 * $net;
        $obs = trim($motivoObs) !== '' ? trim($motivoObs) : 'Estorno de produtividade (encerramento de tarefa).';
        $hash = hash('sha256', 'estorno-neto|' . $referenciaItemId . '|' . $referenciaSistema);
        $this->registrarAuto($actorUserId, [
            'tipo_ato' => 'Estorno',
            'tipo_documento' => 'Espelho cartório',
            'sistema_modulo' => 'cartorio-interno',
            'quantidade' => 1,
            'referencia_item_id' => $referenciaItemId,
            'referencia_sistema' => $referenciaSistema,
            'observacoes' => $obs,
            'contexto_hash_forced' => $hash,
            'pontos_calculados_override' => $neg,
        ]);
    }

    public function listLancamentos(string $actorUserId, array $filters, int $limit, int $offset): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);
        $filters = $this->applyProdutividadeLancamentosEscopoFiltros($actorUserId, $filters, $isGestao);

        $rows = $this->lancamentosRepository->fetchList($filters, $limit, $offset);
        $total = $this->lancamentosRepository->count($filters);
        $sum = $this->lancamentosRepository->sumPontos($filters);

        return [
            'rows' => $rows,
            'total' => $total,
            'sum_pontos' => $sum,
            'is_gestao' => $isGestao,
        ];
    }

    public function listMetas(string $actorUserId, array $filters, int $limit, int $offset): array
    {
        $this->assertGestao($actorUserId);
        return [
            'rows' => $this->metasRepository->fetchList($filters, $limit, $offset),
            'total' => $this->metasRepository->count($filters),
        ];
    }

    public function findMeta(string $actorUserId, string $id): ?array
    {
        $this->assertGestao($actorUserId);
        return $this->metasRepository->findById($id);
    }

    public function createMeta(string $actorUserId, array $payload): string
    {
        $this->assertGestao($actorUserId);
        $row = $this->normalizeMetaPayload($payload);
        $row['criado_por'] = $actorUserId;
        return $this->metasRepository->insert($row);
    }

    public function updateMeta(string $actorUserId, string $id, array $payload): bool
    {
        $this->assertGestao($actorUserId);
        $current = $this->metasRepository->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('Meta não encontrada.');
        }
        $row = $this->normalizeMetaPayload($payload, $current);
        return $this->metasRepository->update($id, $row);
    }

    public function setMetaAtivo(string $actorUserId, string $id, bool $ativo): bool
    {
        $this->assertGestao($actorUserId);
        return $this->metasRepository->setAtivo($id, $ativo);
    }

    public function listBadges(string $actorUserId, array $filters, int $limit, int $offset): array
    {
        $this->assertGestao($actorUserId);
        return [
            'rows' => $this->badgesRepository->fetchList($filters, $limit, $offset),
            'total' => $this->badgesRepository->count($filters),
        ];
    }

    public function findBadge(string $actorUserId, string $id): ?array
    {
        $this->assertGestao($actorUserId);
        return $this->badgesRepository->findById($id);
    }

    public function createBadge(string $actorUserId, array $payload): string
    {
        $this->assertGestao($actorUserId);
        $row = $this->normalizeBadgePayload($payload);
        return $this->badgesRepository->insert($row);
    }

    public function updateBadge(string $actorUserId, string $id, array $payload): bool
    {
        $this->assertGestao($actorUserId);
        $current = $this->badgesRepository->findById($id);
        if ($current === null) {
            throw new InvalidArgumentException('Badge não encontrado.');
        }
        $row = $this->normalizeBadgePayload($payload, $current);
        return $this->badgesRepository->update($id, $row);
    }

    public function setBadgeAtivo(string $actorUserId, string $id, bool $ativo): bool
    {
        $this->assertGestao($actorUserId);
        return $this->badgesRepository->setAtivo($id, $ativo);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function verificarBadges(string $usuarioId): array
    {
        $this->assertAuthenticated($usuarioId);
        $concedidos = [];
        $badges = $this->badgesRepository->listAtivos();
        foreach ($badges as $badge) {
            $tipo = trim((string) ($badge['tipo'] ?? ''));
            $threshold = (float) ($badge['valor_threshold'] ?? 0);
            if ($threshold <= 0) {
                continue;
            }
            [$start, $end] = $this->resolveBadgePeriodRange((string) ($badge['periodo'] ?? 'total'));

            $atingiu = false;
            if ($tipo === 'pontuacao_cumulativa') {
                $atingiu = $this->sumPontosUsuarioPeriodo($usuarioId, $start, $end) >= $threshold;
            } elseif ($tipo === 'ato_especifico') {
                $regraId = trim((string) ($badge['regra_id'] ?? ''));
                if ($regraId !== '') {
                    $atingiu = $this->sumQuantidadeRegraUsuarioPeriodo($usuarioId, $regraId, $start, $end) >= $threshold;
                }
            }

            if (!$atingiu) {
                continue;
            }
            if ($this->badgesRepository->concederSeNaoExiste((string) ($badge['id'] ?? ''), $usuarioId)) {
                $concedidos[] = $badge;
            }
        }

        return $concedidos;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listBadgesPendentes(string $actorUserId, int $limit = 10): array
    {
        $this->assertAuthenticated($actorUserId);
        return $this->badgesRepository->listPendentesByUsuario($actorUserId, $limit);
    }

    public function marcarBadgeNotificado(string $actorUserId, string $concedidoId): bool
    {
        $this->assertAuthenticated($actorUserId);
        $concedidoId = trim($concedidoId);
        if ($concedidoId === '') {
            throw new InvalidArgumentException('ID da concessão é obrigatório.');
        }
        return $this->badgesRepository->marcarNotificado($concedidoId, $actorUserId);
    }

    public function listRanking(string $actorUserId, array $filters, int $limit = 200): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);

        $status = trim((string) ($filters['status'] ?? 'ativo'));
        if ($status === '') {
            $status = 'ativo';
        }

        $now = new DateTimeImmutable('now');
        $rankingFilters = [
            'status' => $status,
            'tipo_lancamento' => '',
            'usuario_id' => '',
            'data_inicio' => $now->modify('first day of this month')->format('Y-m-d'),
            'data_fim' => $now->modify('last day of this month')->format('Y-m-d'),
            'setor_codigo_area' => '',
        ];

        if (!$isGestao) {
            $setorCodigoArea = $this->findUserSetorCodigoArea($actorUserId);
            if ($setorCodigoArea === strtoupper(AccessAreas::AUDIENCIAS)) {
                return [
                    'is_gestao' => false,
                    'ranking_oculto' => true,
                    'rows' => [],
                ];
            }
        }

        $rows = $this->lancamentosRepository->fetchRanking($rankingFilters, $limit);
        $out = [];
        $position = 1;
        foreach ($rows as $row) {
            $pontos = (float) ($row['pontos_total'] ?? 0);
            $out[] = [
                'posicao' => $position,
                'usuario_id' => (string) ($row['usuario_id'] ?? ''),
                'usuario_nome' => trim((string) ($row['usuario_nome'] ?? '')) !== '' ? trim((string) $row['usuario_nome']) : (string) ($row['usuario_id'] ?? ''),
                'pontos_total' => $pontos,
                'total_lancamentos' => (int) ($row['total_lancamentos'] ?? 0),
            ];
            $position++;
        }

        return [
            'is_gestao' => $isGestao,
            'ranking_oculto' => false,
            'rows' => $out,
        ];
    }

    /**
     * @return array{rows:list<array<string,mixed>>,sum_pontos:float,is_gestao:bool}
     */
    public function listLancamentosForExport(string $actorUserId, array $filters, int $limit = 5000): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);
        $filters = $this->applyProdutividadeLancamentosEscopoFiltros($actorUserId, $filters, $isGestao);

        $rows = $this->lancamentosRepository->fetchForExport($filters, $limit);
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) ($row['pontos_calculados'] ?? 0);
        }

        return [
            'rows' => $rows,
            'sum_pontos' => $sum,
            'is_gestao' => $isGestao,
        ];
    }

    public function getResumoCards(string $actorUserId, array $filters): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);
        $base = $this->applyProdutividadeLancamentosEscopoFiltros($actorUserId, $filters, $isGestao);

        $now = new DateTimeImmutable('now');
        $weekStart = $now->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $monthStart = $now->modify('first day of this month');
        $monthEnd = $now->modify('last day of this month');
        $quarterStartMonth = ((int) floor((((int) $now->format('n')) - 1) / 3) * 3) + 1;
        $quarterStart = new DateTimeImmutable($now->format('Y') . '-' . str_pad((string) $quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
        $quarterEnd = $quarterStart->modify('+2 months')->modify('last day of this month');

        $weekCurrent = $this->sumForRange($base, $weekStart, $weekEnd);
        $weekPrevious = $this->sumForRange($base, $weekStart->modify('-7 days'), $weekEnd->modify('-7 days'));
        $monthCurrent = $this->sumForRange($base, $monthStart, $monthEnd);
        $monthCurrentToDate = $this->sumForRange($base, $monthStart, $now);
        $monthPrevious = $this->sumForRange($base, $monthStart->modify('-1 month')->modify('first day of this month'), $monthStart->modify('-1 month')->modify('last day of this month'));
        $quarterCurrent = $this->sumForRange($base, $quarterStart, $quarterEnd);
        $prevQuarterStart = $quarterStart->modify('-3 months');
        $prevQuarterEnd = $prevQuarterStart->modify('+2 months')->modify('last day of this month');
        $quarterPrevious = $this->sumForRange($base, $prevQuarterStart, $prevQuarterEnd);

        $metricas = $this->calcMetricasProporcionais($monthCurrentToDate, $monthPrevious, $now, $monthStart, $monthEnd);
        $bizEff = $metricas['business_days_effective_elapsed'];
        $bizCurTotal = $metricas['current_business_days_total'];
        $projected = ($bizEff > 0.0 && $bizCurTotal > 0)
            ? ($monthCurrentToDate / $bizEff) * $bizCurTotal
            : 0.0;

        $lancEnd = $now > $monthEnd ? $monthEnd : new DateTimeImmutable($now->format('Y-m-d'));
        $businessDaysWithLaunch = $this->countBusinessDaysWithLancamento($base, $monthStart, $lancEnd);

        $projection = [
            'projected_points' => $projected,
            'current_points' => $monthCurrentToDate,
            'business_days_total' => $bizCurTotal,
            'business_days_effective_elapsed' => $bizEff,
            'business_days_remaining' => $metricas['current_business_days_remaining'],
            'business_days_with_launch' => $businessDaysWithLaunch,
        ];

        return [
            'week' => [
                'current' => $weekCurrent,
                'previous' => $weekPrevious,
                'variation_percent' => $this->calcVariationPercent($weekCurrent, $weekPrevious),
            ],
            'month' => [
                'current' => $monthCurrent,
                'current_to_date' => $monthCurrentToDate,
                'previous' => $monthPrevious,
                'variation_percent' => $this->calcVariationPercent($monthCurrent, $monthPrevious),
                'variation_percent_proportional' => $metricas['variation_percent_proportional'],
                'expected_to_date' => $metricas['expected_to_date'],
                'delta_points' => $metricas['delta_points'],
                'prev_business_days' => $metricas['prev_business_days'],
                'current_business_days_total' => $metricas['current_business_days_total'],
                'current_business_days_full_elapsed' => $metricas['current_business_days_full_elapsed'],
                'current_business_days_effective_elapsed' => $metricas['business_days_effective_elapsed'],
                'current_business_days_remaining' => $metricas['current_business_days_remaining'],
                'today_fraction' => $metricas['today_fraction'],
                'today_is_business_day' => $metricas['today_is_business_day'],
            ],
            'quarter' => [
                'current' => $quarterCurrent,
                'previous' => $quarterPrevious,
                'variation_percent' => $this->calcVariationPercent($quarterCurrent, $quarterPrevious),
            ],
            'projection' => $projection,
        ];
    }

    /**
     * Projeção do mês civil corrente apenas para o usuário logado (ignora filtro de lista e escopo de gestão).
     *
     * @return array{projected_points:float,current_points:float,business_days_total:int,business_days_with_launch:int,business_days_effective_elapsed:float,business_days_remaining:int}
     */
    public function projetarPeriodoMensalUsuarioLogado(string $actorUserId): array
    {
        $this->assertAuthenticated($actorUserId);
        $now = new DateTimeImmutable('now');
        $monthStart = $now->modify('first day of this month');
        $monthEnd = $now->modify('last day of this month');
        $base = [
            'usuario_id' => $actorUserId,
            'status' => 'ativo',
            'tipo_lancamento' => '',
            'data_inicio' => $monthStart->format('Y-m-d'),
            'data_fim' => $monthEnd->format('Y-m-d'),
            'setor_codigo_area' => '',
        ];

        return $this->projetarPeriodoComFiltrosBase($base);
    }

    public function projetarPeriodo(string $actorUserId, array $filters): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);
        $base = $this->applyProdutividadeLancamentosEscopoFiltros($actorUserId, $filters, $isGestao);

        return $this->projetarPeriodoComFiltrosBase($base);
    }

    /**
     * @param array<string, mixed> $base Filtros já resolvidos (incl. data_inicio / data_fim)
     *
     * @return array{projected_points:float,current_points:float,business_days_total:int,business_days_with_launch:int,business_days_effective_elapsed:float,business_days_remaining:int}
     */
    private function projetarPeriodoComFiltrosBase(array $base): array
    {
        $empty = static fn (): array => [
            'projected_points' => 0.0,
            'current_points' => 0.0,
            'business_days_total' => 0,
            'business_days_with_launch' => 0,
            'business_days_effective_elapsed' => 0.0,
            'business_days_remaining' => 0,
        ];

        $start = trim((string) ($base['data_inicio'] ?? ''));
        $end = trim((string) ($base['data_fim'] ?? ''));
        if (!$this->isValidDate($start) || !$this->isValidDate($end)) {
            return $empty();
        }

        $dtStart = new DateTimeImmutable($start);
        $dtEnd = new DateTimeImmutable($end);
        if ($dtStart > $dtEnd) {
            return $empty();
        }

        $now = new DateTimeImmutable('now');
        $nowDate = new DateTimeImmutable($now->format('Y-m-d'));
        $dtStartDate = new DateTimeImmutable($dtStart->format('Y-m-d'));
        $dtEndDate = new DateTimeImmutable($dtEnd->format('Y-m-d'));

        if ($nowDate < $dtStartDate) {
            return $empty();
        }

        $feriadosSet = $this->getFeriadosSet($dtStart, $dtEnd);
        $businessDaysTotal = $this->countBusinessDays($dtStart, $dtEnd, $feriadosSet);

        if ($nowDate > $dtEndDate) {
            $fullPoints = $this->sumForRange($base, $dtStart, $dtEnd);

            return [
                'projected_points' => $fullPoints,
                'current_points' => $fullPoints,
                'business_days_total' => $businessDaysTotal,
                'business_days_with_launch' => $this->countBusinessDaysWithLancamento($base, $dtStart, $dtEnd),
                'business_days_effective_elapsed' => (float) $businessDaysTotal,
                'business_days_remaining' => 0,
            ];
        }

        $effectiveEndDate = $nowDate <= $dtEndDate ? $nowDate : $dtEndDate;
        $currentPoints = $this->sumForRange($base, $dtStart, $effectiveEndDate);

        if ($businessDaysTotal <= 0) {
            return [
                'projected_points' => $currentPoints,
                'current_points' => $currentPoints,
                'business_days_total' => 0,
                'business_days_with_launch' => 0,
                'business_days_effective_elapsed' => 0.0,
                'business_days_remaining' => 0,
            ];
        }

        $todayIsBiz = $this->isDiaUtil($nowDate, $feriadosSet);
        $dayFrac = ($todayIsBiz && $nowDate >= $dtStartDate && $nowDate <= $dtEndDate) ? $this->calcDayFraction($now) : 0.0;

        $bizFullElapsed = 0;
        if ($nowDate > $dtStartDate) {
            $until = $nowDate->modify('-1 day');
            if ($until >= $dtStartDate) {
                $bizFullElapsed = $this->countBusinessDays($dtStartDate, $until, $feriadosSet);
            }
        }
        $bizEffElapsed = $bizFullElapsed + (($todayIsBiz && $nowDate >= $dtStartDate && $nowDate <= $dtEndDate) ? $dayFrac : 0.0);
        $bizRemaining = max(0, $businessDaysTotal - $bizFullElapsed - ($todayIsBiz && $nowDate <= $dtEndDate ? 1 : 0));

        $businessDaysWithLaunch = $this->countBusinessDaysWithLancamento($base, $dtStart, $effectiveEndDate);

        $projected = ($bizEffElapsed > 0.0 && $businessDaysTotal > 0)
            ? ($currentPoints / $bizEffElapsed) * $businessDaysTotal
            : 0.0;

        return [
            'projected_points' => $projected,
            'current_points' => $currentPoints,
            'business_days_total' => $businessDaysTotal,
            'business_days_with_launch' => $businessDaysWithLaunch,
            'business_days_effective_elapsed' => $bizEffElapsed,
            'business_days_remaining' => $bizRemaining,
        ];
    }

    /** Ranking de produtividade: visível para Cartório e Gabinete; oculto para Sala de Audiências (gestão vê ranking global do mês). */
    public function usuarioPodeVerRankingProdutividade(string $userId): bool
    {
        $this->assertAuthenticated($userId);
        if ($this->isGestaoUser($userId)) {
            return true;
        }

        return $this->findUserSetorCodigoArea($userId) !== strtoupper(AccessAreas::AUDIENCIAS);
    }

    public function getMetaResumoUsuario(string $actorUserId, array $filters): array
    {
        $this->assertAuthenticated($actorUserId);
        $targetUserId = trim((string) ($filters['usuario_id'] ?? ''));
        if ($targetUserId === '' || !$this->isGestaoUser($actorUserId)) {
            $targetUserId = $actorUserId;
        }

        $periodoTipo = 'mes';
        $start = trim((string) ($filters['data_inicio'] ?? ''));
        $end = trim((string) ($filters['data_fim'] ?? ''));
        if ($this->isValidDate($start) && $this->isValidDate($end)) {
            $days = (int) ((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days);
            if ($days <= 7) {
                $periodoTipo = 'semana';
            } elseif ($days > 31) {
                $periodoTipo = 'trimestre';
            }
        }

        $meta = $this->findMetaAtivaParaUsuario($targetUserId, $periodoTipo);
        if ($meta === null) {
            return ['has_meta' => false];
        }

        $vigenciaInicio = (string) ($meta['vigencia_inicio'] ?? '');
        $vigenciaFim = trim((string) ($meta['vigencia_fim'] ?? ''));
        if (!$this->isValidDate($vigenciaInicio)) {
            return ['has_meta' => false];
        }
        $rangeStart = new DateTimeImmutable($vigenciaInicio);
        $rangeEnd = $this->isValidDate($vigenciaFim) ? new DateTimeImmutable($vigenciaFim) : new DateTimeImmutable('today');
        if ($rangeEnd < $rangeStart) {
            $rangeEnd = $rangeStart;
        }
        $pontos = $this->sumForRange(['usuario_id' => $targetUserId, 'status' => 'ativo'], $rangeStart, $rangeEnd);
        $metaPontos = (float) ($meta['pontos_meta'] ?? 0);
        $percent = $metaPontos > 0 ? min(100.0, ($pontos / $metaPontos) * 100.0) : 0.0;

        return [
            'has_meta' => true,
            'tipo_alvo' => (string) ($meta['tipo_alvo'] ?? ''),
            'alvo_valor' => (string) ($meta['alvo_valor'] ?? ''),
            'periodo_tipo' => (string) ($meta['periodo_tipo'] ?? ''),
            'meta_pontos' => $metaPontos,
            'pontos_atuais' => $pontos,
            'percentual' => $percent,
        ];
    }

    private function assertAuthenticated(string $actorUserId): void
    {
        if (trim($actorUserId) === '') {
            throw new InvalidArgumentException('Sessão expirada. Faça login novamente.');
        }
    }

    private function assertGestao(string $actorUserId): void
    {
        $this->assertAuthenticated($actorUserId);
        if (!$this->isGestaoUser($actorUserId)) {
            throw new InvalidArgumentException('Acesso permitido apenas para perfis de gestão.');
        }
    }

    private function isGestaoUser(string $userId): bool
    {
        $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
        foreach ($perfis as $perfil) {
            $p = strtoupper(trim((string) $perfil));
            if ($p === 'ADMIN' || $p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
                return true;
            }
        }

        return false;
    }

    /**
     * Anulação de lançamento manual: perfis de gestão ({@see self::isGestaoUser}: ADMIN, CHEFIA, DIRETORA e MAGISTRADA —
     * DIRETORA/MAGISTRADA enquadradas como chefia) sem janela; autor do lançamento dentro de 30 min após criado_em.
     *
     * @param array<string, mixed> $row Registro já validado como manual e ativo
     */
    private function assertPodeAnularLancamentoManual(string $actorUserId, array $row): void
    {
        if ($this->actorPodeAnularLancamentoManualSemChecarTipoStatus($actorUserId, $row)) {
            return;
        }

        $ownerId = trim((string) ($row['usuario_id'] ?? ''));
        if ($ownerId !== $actorUserId) {
            throw new InvalidArgumentException('Você não tem permissão para anular este lançamento.');
        }

        throw new InvalidArgumentException('Prazo de 30 minutos para anulação do próprio lançamento expirou.');
    }

    /**
     * Pré-condição: lançamento manual e ativo já validados.
     *
     * @param array<string, mixed> $row
     */
    private function actorPodeAnularLancamentoManualSemChecarTipoStatus(string $actorUserId, array $row): bool
    {
        if ($this->isGestaoUser($actorUserId)) {
            return true;
        }
        $ownerId = trim((string) ($row['usuario_id'] ?? ''));

        return $ownerId === $actorUserId && $this->isDentroJanelaAnulacaoAutor($row);
    }

    /**
     * @param array<string, mixed> $row Deve conter criado_em
     */
    private function isDentroJanelaAnulacaoAutor(array $row): bool
    {
        $criadoEm = trim((string) ($row['criado_em'] ?? ''));
        if ($criadoEm === '') {
            return false;
        }
        $limite = strtotime($criadoEm . ' +30 minutes');
        if ($limite === false) {
            return false;
        }

        return time() <= $limite;
    }

    /**
     * Gestão: vê todos (opcional filtro por usuário). Demais perfis: se lotados em setor com código de área,
     * veem lançamentos de todos os usuários daquela área (ex.: cartório); sem setor, apenas os próprios.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function applyProdutividadeLancamentosEscopoFiltros(string $actorUserId, array $filters, bool $isGestao): array
    {
        if ($isGestao) {
            unset($filters['setor_codigo_area']);
            $filtroUsuario = trim((string) ($filters['usuario_id'] ?? ''));
            if ($filtroUsuario === '') {
                unset($filters['usuario_id']);
            } else {
                $filters['usuario_id'] = $filtroUsuario;
            }

            return $filters;
        }

        unset($filters['setor_codigo_area'], $filters['usuario_id']);
        $setor = $this->findUserSetorCodigoArea($actorUserId);
        if ($setor !== '') {
            $filters['setor_codigo_area'] = $setor;
        } else {
            $filters['usuario_id'] = $actorUserId;
        }

        return $filters;
    }

    private function normalizeRulePayload(array $payload, ?array $current = null): array
    {
        $tipoAto = trim((string) ($payload['tipo_ato'] ?? ($current['tipo_ato'] ?? '')));
        if ($tipoAto === '') {
            throw new InvalidArgumentException('Tipo de ato é obrigatório.');
        }

        $tipoDocumento = trim((string) ($payload['tipo_documento'] ?? ($current['tipo_documento'] ?? '')));
        $sistemaModulo = trim((string) ($payload['sistema_modulo'] ?? ($current['sistema_modulo'] ?? '')));
        $observacoes = trim((string) ($payload['observacoes'] ?? ($current['observacoes'] ?? '')));

        $pontosPorAtoRaw = str_replace(',', '.', trim((string) ($payload['pontos_por_ato'] ?? ($current['pontos_por_ato'] ?? '0'))));
        $pontosPorAto = (float) $pontosPorAtoRaw;
        if ($pontosPorAto < 0) {
            throw new InvalidArgumentException('Pontos por ato deve ser maior ou igual a zero.');
        }

        $multiplicadorRaw = str_replace(',', '.', trim((string) ($payload['pontos_multiplicador'] ?? ($current['pontos_multiplicador'] ?? '1'))));
        $multiplicador = (float) $multiplicadorRaw;
        if ($multiplicador <= 0) {
            throw new InvalidArgumentException('Multiplicador deve ser maior que zero.');
        }

        $ativoIn = $payload['ativo'] ?? ($current['ativo'] ?? 1);
        $ativo = ((int) $ativoIn) === 1 ? 1 : 0;

        return [
            'tipo_ato' => $tipoAto,
            'tipo_documento' => $tipoDocumento,
            'sistema_modulo' => $sistemaModulo,
            'pontos_por_ato' => number_format($pontosPorAto, 2, '.', ''),
            'pontos_multiplicador' => number_format($multiplicador, 2, '.', ''),
            'ativo' => $ativo,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
        ];
    }

    private function normalizeMetaPayload(array $payload, ?array $current = null): array
    {
        $tipoAlvo = trim((string) ($payload['tipo_alvo'] ?? ($current['tipo_alvo'] ?? '')));
        if (!in_array($tipoAlvo, ['setor', 'perfil', 'usuario'], true)) {
            throw new InvalidArgumentException('Tipo de alvo inválido. Use setor, perfil ou usuário.');
        }

        $alvoValor = trim((string) ($payload['alvo_valor'] ?? ($current['alvo_valor'] ?? '')));
        if ($alvoValor === '') {
            throw new InvalidArgumentException('Alvo da meta é obrigatório.');
        }
        if ($tipoAlvo === 'setor') {
            $alvoValor = $this->normalizeMetaAlvoSetor($alvoValor);
        } elseif ($tipoAlvo === 'perfil') {
            $alvoValor = $this->normalizeMetaAlvoPerfil($alvoValor);
        }

        $periodoTipo = trim((string) ($payload['periodo_tipo'] ?? ($current['periodo_tipo'] ?? '')));
        if (!in_array($periodoTipo, ['semana', 'mes', 'trimestre'], true)) {
            throw new InvalidArgumentException('Período inválido. Use semana, mes ou trimestre.');
        }

        $vigenciaInicio = trim((string) ($payload['vigencia_inicio'] ?? ($current['vigencia_inicio'] ?? '')));
        if (!$this->isValidDate($vigenciaInicio)) {
            throw new InvalidArgumentException('Vigência inicial inválida.');
        }

        $vigenciaFim = trim((string) ($payload['vigencia_fim'] ?? ($current['vigencia_fim'] ?? '')));
        if ($vigenciaFim !== '' && !$this->isValidDate($vigenciaFim)) {
            throw new InvalidArgumentException('Vigência final inválida.');
        }
        if ($vigenciaFim !== '' && strtotime($vigenciaFim) < strtotime($vigenciaInicio)) {
            throw new InvalidArgumentException('Vigência final não pode ser anterior à inicial.');
        }

        $pontosMetaRaw = str_replace(',', '.', trim((string) ($payload['pontos_meta'] ?? ($current['pontos_meta'] ?? '0'))));
        $pontosMeta = (float) $pontosMetaRaw;
        if ($pontosMeta < 0) {
            throw new InvalidArgumentException('Pontos da meta deve ser maior ou igual a zero.');
        }

        $ativoIn = $payload['ativo'] ?? ($current['ativo'] ?? 1);
        $ativo = ((int) $ativoIn) === 1 ? 1 : 0;

        return [
            'tipo_alvo' => $tipoAlvo,
            'alvo_valor' => $alvoValor,
            'periodo_tipo' => $periodoTipo,
            'vigencia_inicio' => $vigenciaInicio,
            'vigencia_fim' => $vigenciaFim !== '' ? $vigenciaFim : null,
            'pontos_meta' => number_format($pontosMeta, 2, '.', ''),
            'ativo' => $ativo,
        ];
    }

    private function normalizeBadgePayload(array $payload, ?array $current = null): array
    {
        $nome = trim((string) ($payload['nome'] ?? ($current['nome'] ?? '')));
        if ($nome === '') {
            throw new InvalidArgumentException('Nome do badge é obrigatório.');
        }
        $tipo = trim((string) ($payload['tipo'] ?? ($current['tipo'] ?? '')));
        if (!in_array($tipo, ['pontuacao_cumulativa', 'ato_especifico'], true)) {
            throw new InvalidArgumentException('Tipo de badge inválido.');
        }
        $periodo = trim((string) ($payload['periodo'] ?? ($current['periodo'] ?? 'total')));
        if (!in_array($periodo, ['total', 'mensal', 'semanal', 'diario'], true)) {
            throw new InvalidArgumentException('Período do badge inválido.');
        }
        $thresholdRaw = trim((string) ($payload['valor_threshold'] ?? ($current['valor_threshold'] ?? '0')));
        $thresholdDigits = preg_replace('/[^0-9]/', '', str_replace(["\xc2\xa0", ' '], '', $thresholdRaw));
        $threshold = $thresholdDigits === '' ? 0.0 : (float) $thresholdDigits;
        if ($threshold <= 0) {
            throw new InvalidArgumentException('Valor de threshold deve ser maior que zero.');
        }
        $regraId = trim((string) ($payload['regra_id'] ?? ($current['regra_id'] ?? '')));
        if ($tipo === 'ato_especifico' && $regraId === '') {
            throw new InvalidArgumentException('Badge por ato específico exige uma regra vinculada.');
        }
        if ($regraId !== '' && $this->regrasRepository->findById($regraId) === null) {
            throw new InvalidArgumentException('Regra vinculada do badge não encontrada.');
        }
        $ativoIn = $payload['ativo'] ?? ($current['ativo'] ?? 1);
        $ativo = ((int) $ativoIn) === 1 ? 1 : 0;
        $descricao = trim((string) ($payload['descricao'] ?? ($current['descricao'] ?? '')));
        $icone = trim((string) ($payload['icone'] ?? ($current['icone'] ?? '')));

        return [
            'nome' => $nome,
            'descricao' => $descricao !== '' ? $descricao : null,
            'icone' => $icone !== '' ? $icone : null,
            'tipo' => $tipo,
            'regra_id' => $regraId !== '' ? $regraId : null,
            'valor_threshold' => number_format($threshold, 2, '.', ''),
            'periodo' => $periodo,
            'ativo' => $ativo,
        ];
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    private function normalizeMetaAlvoSetor(string $alvoValor): string
    {
        $u = strtoupper(trim($alvoValor));
        if (!in_array($u, AccessAreas::ALL, true)) {
            throw new InvalidArgumentException('Setor inválido. Escolha Cartório, Gabinete ou Sala de Audiências.');
        }

        return $u;
    }

    private function normalizeMetaAlvoPerfil(string $alvoValor): string
    {
        $statement = $this->connection->prepare(
            'SELECT TRIM(`perfil`) AS `p` FROM `usuario_perfil` WHERE UPPER(TRIM(`perfil`)) = UPPER(?) LIMIT 1'
        );
        $statement->execute([$alvoValor]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && trim((string) ($row['p'] ?? '')) !== '') {
            return trim((string) $row['p']);
        }

        $upper = strtoupper(str_replace([' ', '_'], '-', trim($alvoValor)));
        $static = [];
        foreach (['ESTAGIARIO', 'SERVIDOR'] as $cargo) {
            foreach (AccessAreas::ALL as $area) {
                $static[] = $cargo . '-' . $area;
            }
        }
        if (in_array($upper, $static, true)) {
            return $upper;
        }

        throw new InvalidArgumentException('Perfil inválido. Selecione um perfil da lista.');
    }

    private function findUserSetorCodigoArea(string $userId): string
    {
        $statement = $this->connection->prepare(
            'SELECT UPPER(TRIM(COALESCE(s.`codigo_area`, \'\'))) AS `codigo_area`
             FROM `usuario_setor` us
             INNER JOIN `setores` s ON s.`id_setor` = us.`setor`
             WHERE us.`usuario` = :usuario
               AND s.`ativo` = 1
             LIMIT 1'
        );
        $statement->bindValue(':usuario', $userId);
        $statement->execute();
        $value = $statement->fetchColumn();
        return strtoupper(trim((string) $value));
    }

    private function getFeriadosRepository(): FeriadosRepository
    {
        return $this->feriadosRepository ??= new FeriadosRepository($this->connection);
    }

    /**
     * @return array<string, true>
     */
    private function getFeriadosSet(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $key = $start->format('Y-m-d') . '|' . $end->format('Y-m-d');
        if (!isset($this->feriadosSetCache[$key])) {
            $this->feriadosSetCache[$key] = $this->getFeriadosRepository()->datasNaoUteisNoIntervalo($start, $end);
        }

        return $this->feriadosSetCache[$key];
    }

    private function calcDayFraction(DateTimeImmutable $now): float
    {
        $minutes = ((int) $now->format('G')) * 60 + (int) $now->format('i');
        $startMin = 8 * 60;
        $endMin = 18 * 60;
        if ($minutes <= $startMin) {
            return 0.0;
        }
        if ($minutes >= $endMin) {
            return 1.0;
        }

        return ($minutes - $startMin) / ($endMin - $startMin);
    }

    /**
     * @param array<string, true> $feriadosSet
     */
    private function isDiaUtil(DateTimeImmutable $d, array $feriadosSet): bool
    {
        if ((int) $d->format('N') > 5) {
            return false;
        }

        return !isset($feriadosSet[$d->format('Y-m-d')]);
    }

    /**
     * @return array{
     *   prev_business_days:int,
     *   current_business_days_total:int,
     *   current_business_days_full_elapsed:int,
     *   current_business_days_remaining:int,
     *   today_fraction:float,
     *   today_is_business_day:bool,
     *   business_days_effective_elapsed:float,
     *   expected_to_date:float,
     *   delta_points:float,
     *   variation_percent_proportional:float,
     *   prev_rate_per_biz_day:float
     * }
     */
    private function calcMetricasProporcionais(
        float $monthCurrentToDate,
        float $monthPrevious,
        DateTimeImmutable $now,
        DateTimeImmutable $monthStart,
        DateTimeImmutable $monthEnd
    ): array {
        $monthStartDate = new DateTimeImmutable($monthStart->format('Y-m-d'));
        $monthEndDate = new DateTimeImmutable($monthEnd->format('Y-m-d'));
        $prevStart = $monthStartDate->modify('-1 month')->modify('first day of this month');
        $prevEnd = $monthStartDate->modify('-1 day');
        $feriadosCur = $this->getFeriadosSet($monthStartDate, $monthEndDate);
        $feriadosPrev = $this->getFeriadosSet($prevStart, $prevEnd);

        $bizCurTotal = $this->countBusinessDays($monthStartDate, $monthEndDate, $feriadosCur);
        $bizPrevTotal = $this->countBusinessDays($prevStart, $prevEnd, $feriadosPrev);

        $nowDate = new DateTimeImmutable($now->format('Y-m-d'));
        $todayIsBiz = $this->isDiaUtil($nowDate, $feriadosCur);
        $dayFrac = $todayIsBiz ? $this->calcDayFraction($now) : 0.0;

        $bizFullElapsed = 0;
        if ($nowDate > $monthStartDate) {
            $until = $nowDate->modify('-1 day');
            if ($until >= $monthStartDate) {
                $bizFullElapsed = $this->countBusinessDays($monthStartDate, $until, $feriadosCur);
            }
        }

        $bizEffElapsed = $bizFullElapsed + $dayFrac;
        $bizRemaining = max(0, $bizCurTotal - $bizFullElapsed - ($todayIsBiz ? 1 : 0));

        $prevRate = $bizPrevTotal > 0 ? ($monthPrevious / $bizPrevTotal) : 0.0;
        $expected = $prevRate * $bizEffElapsed;
        $delta = $monthCurrentToDate - $expected;
        if ($expected > 0.0) {
            $vpa = ($delta / $expected) * 100.0;
        } else {
            $vpa = $monthCurrentToDate > 0.0 ? 100.0 : 0.0;
        }

        return [
            'prev_business_days' => $bizPrevTotal,
            'current_business_days_total' => $bizCurTotal,
            'current_business_days_full_elapsed' => $bizFullElapsed,
            'current_business_days_remaining' => $bizRemaining,
            'today_fraction' => $dayFrac,
            'today_is_business_day' => $todayIsBiz,
            'business_days_effective_elapsed' => $bizEffElapsed,
            'expected_to_date' => $expected,
            'delta_points' => $delta,
            'variation_percent_proportional' => $vpa,
            'prev_rate_per_biz_day' => $prevRate,
        ];
    }

    private function sumForRange(array $filters, DateTimeImmutable $start, DateTimeImmutable $end): float
    {
        $rangeFilters = $filters;
        $rangeFilters['data_inicio'] = $start->format('Y-m-d');
        $rangeFilters['data_fim'] = $end->format('Y-m-d');
        return $this->lancamentosRepository->sumPontos($rangeFilters);
    }

    /**
     * @param array<string, true>|null $excludeSet Datas Y-m-d não úteis (feriados) a excluir da contagem.
     */
    private function countBusinessDays(DateTimeImmutable $start, DateTimeImmutable $end, ?array $excludeSet = null): int
    {
        $startDate = new DateTimeImmutable($start->format('Y-m-d'));
        $endDate = new DateTimeImmutable($end->format('Y-m-d'));
        $count = 0;
        $current = $startDate;
        while ($current <= $endDate) {
            $dow = (int) $current->format('N');
            if ($dow <= 5) {
                $key = $current->format('Y-m-d');
                if ($excludeSet === null || !isset($excludeSet[$key])) {
                    $count++;
                }
            }
            $current = $current->modify('+1 day');
        }

        return $count;
    }

    private function countBusinessDaysWithLancamento(array $filters, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $local = $filters;
        $local['status'] = 'ativo';
        $local['data_inicio'] = $start->format('Y-m-d');
        $local['data_fim'] = $end->format('Y-m-d');

        $usuarioId = trim((string) ($local['usuario_id'] ?? ''));
        $setorCodigoArea = strtoupper(trim((string) ($local['setor_codigo_area'] ?? '')));

        $whereExtra = '';
        if ($usuarioId !== '') {
            $whereExtra .= ' AND l.`usuario_id` = :usuario_id';
        }
        if ($setorCodigoArea !== '') {
            $whereExtra .= ' AND EXISTS (
                SELECT 1 FROM `usuario_setor` us_f
                INNER JOIN `setores` s_f ON s_f.`id_setor` = us_f.`setor`
                WHERE us_f.`usuario` = l.`usuario_id`
                  AND s_f.`ativo` = 1
                  AND UPPER(TRIM(COALESCE(s_f.`codigo_area`, \'\'))) = :setor_codigo_area_proj
            )';
        }

        $sql = 'SELECT DATE(l.`data_hora_ato`) AS dia
                FROM `produtividade_lancamentos` l
                WHERE l.`status` = \'ativo\'
                  AND l.`data_hora_ato` >= :data_inicio
                  AND l.`data_hora_ato` <= :data_fim' . $whereExtra . '
                GROUP BY DATE(l.`data_hora_ato`)';
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':data_inicio', $start->format('Y-m-d') . ' 00:00:00');
        $statement->bindValue(':data_fim', $end->format('Y-m-d') . ' 23:59:59');
        if ($usuarioId !== '') {
            $statement->bindValue(':usuario_id', $usuarioId);
        }
        if ($setorCodigoArea !== '') {
            $statement->bindValue(':setor_codigo_area_proj', $setorCodigoArea);
        }
        $statement->execute();
        $dias = $statement->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($dias as $dia) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', (string) $dia);
            if ($dt !== false && (int) $dt->format('N') <= 5) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{0:?DateTimeImmutable,1:?DateTimeImmutable}
     */
    private function resolveBadgePeriodRange(string $periodo): array
    {
        $now = new DateTimeImmutable('now');
        if ($periodo === 'diario') {
            $start = $now->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
            return [$start, $end];
        }
        if ($periodo === 'semanal') {
            $start = $now->modify('monday this week')->setTime(0, 0, 0);
            $end = $start->modify('+6 days')->setTime(23, 59, 59);
            return [$start, $end];
        }
        if ($periodo === 'mensal') {
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
            $end = $now->modify('last day of this month')->setTime(23, 59, 59);
            return [$start, $end];
        }

        return [null, null];
    }

    private function sumPontosUsuarioPeriodo(string $usuarioId, ?DateTimeImmutable $start, ?DateTimeImmutable $end): float
    {
        $sql = 'SELECT COALESCE(SUM(`pontos_calculados`), 0)
                FROM `produtividade_lancamentos`
                WHERE `usuario_id` = :usuario_id
                  AND `status` = \'ativo\'';
        if ($start !== null && $end !== null) {
            $sql .= ' AND `data_hora_ato` >= :data_inicio AND `data_hora_ato` <= :data_fim';
        }
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':usuario_id', $usuarioId);
        if ($start !== null && $end !== null) {
            $statement->bindValue(':data_inicio', $start->format('Y-m-d H:i:s'));
            $statement->bindValue(':data_fim', $end->format('Y-m-d H:i:s'));
        }
        $statement->execute();
        return (float) $statement->fetchColumn();
    }

    private function sumQuantidadeRegraUsuarioPeriodo(string $usuarioId, string $regraId, ?DateTimeImmutable $start, ?DateTimeImmutable $end): float
    {
        $sql = 'SELECT COALESCE(SUM(`quantidade`), 0)
                FROM `produtividade_lancamentos`
                WHERE `usuario_id` = :usuario_id
                  AND `regra_id` = :regra_id
                  AND `status` = \'ativo\'';
        if ($start !== null && $end !== null) {
            $sql .= ' AND `data_hora_ato` >= :data_inicio AND `data_hora_ato` <= :data_fim';
        }
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->bindValue(':regra_id', $regraId);
        if ($start !== null && $end !== null) {
            $statement->bindValue(':data_inicio', $start->format('Y-m-d H:i:s'));
            $statement->bindValue(':data_fim', $end->format('Y-m-d H:i:s'));
        }
        $statement->execute();
        return (float) $statement->fetchColumn();
    }

    private function findMetaAtivaParaUsuario(string $usuarioId, string $periodoTipo): ?array
    {
        $today = date('Y-m-d');
        $setor = $this->findUserSetorCodigoArea($usuarioId);

        $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);

        $sql = 'SELECT *
                FROM `produtividade_metas`
                WHERE `ativo` = 1
                  AND `periodo_tipo` = :periodo_tipo
                  AND `vigencia_inicio` <= :today_inicio
                  AND (`vigencia_fim` IS NULL OR `vigencia_fim` >= :today_fim)
                ORDER BY
                    CASE `tipo_alvo`
                        WHEN \'usuario\' THEN 1
                        WHEN \'perfil\' THEN 2
                        WHEN \'setor\' THEN 3
                        ELSE 4
                    END ASC';
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':periodo_tipo', $periodoTipo);
        $statement->bindValue(':today_inicio', $today);
        $statement->bindValue(':today_fim', $today);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $tipo = trim((string) ($row['tipo_alvo'] ?? ''));
            $alvo = trim((string) ($row['alvo_valor'] ?? ''));
            if ($tipo === 'usuario' && $alvo === $usuarioId) {
                return $row;
            }
            if ($tipo === 'perfil' && in_array($alvo, $perfis, true)) {
                return $row;
            }
            if ($tipo === 'setor' && $setor !== '' && strtoupper($alvo) === strtoupper($setor)) {
                return $row;
            }
        }

        return null;
    }

    private function calcVariationPercent(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }
        return (($current - $previous) / $previous) * 100.0;
    }
}

