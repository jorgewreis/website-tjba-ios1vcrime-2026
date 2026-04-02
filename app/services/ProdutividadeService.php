<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/ProdutividadeRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLancamentosRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLogRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeMetasRepository.php';

final class ProdutividadeService
{
    private PDO $connection;
    private ProdutividadeRegrasRepository $regrasRepository;
    private ProdutividadeLancamentosRepository $lancamentosRepository;
    private ProdutividadeLogRepository $logRepository;
    private ProdutividadeMetasRepository $metasRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->regrasRepository = new ProdutividadeRegrasRepository($connection);
        $this->lancamentosRepository = new ProdutividadeLancamentosRepository($connection);
        $this->logRepository = new ProdutividadeLogRepository($connection);
        $this->metasRepository = new ProdutividadeMetasRepository($connection);
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

        $ownerId = trim((string) ($row['usuario_id'] ?? ''));
        $criadoEm = trim((string) ($row['criado_em'] ?? ''));
        $isGestao = $this->isGestaoUser($actorUserId);
        if ($ownerId !== $actorUserId && !$isGestao) {
            throw new InvalidArgumentException('Você não tem permissão para anular este lançamento.');
        }

        if (!$isGestao && $ownerId === $actorUserId) {
            $limite = strtotime($criadoEm . ' +1 hour');
            if ($limite === false || time() > $limite) {
                throw new InvalidArgumentException('Prazo de 1 hora para anulação do próprio lançamento expirou.');
            }
        }

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

    public function listLancamentos(string $actorUserId, array $filters, int $limit, int $offset): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);
        if (!$isGestao) {
            $filters['usuario_id'] = $actorUserId;
        } else {
            $filtroUsuario = trim((string) ($filters['usuario_id'] ?? ''));
            if ($filtroUsuario !== '') {
                $filters['usuario_id'] = $filtroUsuario;
            }
        }

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

    public function listRanking(string $actorUserId, array $filters, int $limit = 200): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);
        $rankingFilters = $this->normalizeRankingFilters($filters);

        if (!$isGestao) {
            $setorCodigoArea = $this->findUserSetorCodigoArea($actorUserId);
            if ($setorCodigoArea !== '') {
                $rankingFilters['setor_codigo_area'] = $setorCodigoArea;
            } else {
                $rankingFilters['usuario_id'] = $actorUserId;
            }
        }

        $rows = $this->lancamentosRepository->fetchRanking($rankingFilters, $limit);
        $sectors = [];
        foreach ($rows as $row) {
            $codigo = strtoupper(trim((string) ($row['setor_codigo_area'] ?? '')));
            if ($codigo === '') {
                $codigo = '__SEM_SETOR__';
            }
            if (!isset($sectors[$codigo])) {
                $sectors[$codigo] = [
                    'codigo_area' => $codigo === '__SEM_SETOR__' ? '' : $codigo,
                    'setor_nome' => trim((string) ($row['setor_nome'] ?? 'Sem setor')),
                    'total_pontos' => 0.0,
                    'rows' => [],
                ];
            }
            $pontos = (float) ($row['pontos_total'] ?? 0);
            $sectors[$codigo]['rows'][] = [
                'usuario_id' => (string) ($row['usuario_id'] ?? ''),
                'usuario_nome' => trim((string) ($row['usuario_nome'] ?? '')) !== '' ? trim((string) $row['usuario_nome']) : (string) ($row['usuario_id'] ?? ''),
                'pontos_total' => $pontos,
                'total_lancamentos' => (int) ($row['total_lancamentos'] ?? 0),
            ];
            $sectors[$codigo]['total_pontos'] += $pontos;
        }

        $out = [];
        foreach ($sectors as $sector) {
            usort($sector['rows'], static function (array $a, array $b): int {
                if ($a['pontos_total'] === $b['pontos_total']) {
                    return strcmp((string) $a['usuario_nome'], (string) $b['usuario_nome']);
                }
                return $a['pontos_total'] > $b['pontos_total'] ? -1 : 1;
            });
            $position = 1;
            foreach ($sector['rows'] as &$item) {
                $item['posicao'] = $position;
                $position++;
            }
            unset($item);
            $out[] = $sector;
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp((string) $a['setor_nome'], (string) $b['setor_nome']);
        });

        return [
            'is_gestao' => $isGestao,
            'total_setores' => count($out),
            'setores' => $out,
        ];
    }

    /**
     * @return array{rows:list<array<string,mixed>>,sum_pontos:float,is_gestao:bool}
     */
    public function listLancamentosForExport(string $actorUserId, array $filters, int $limit = 5000): array
    {
        $this->assertAuthenticated($actorUserId);
        $isGestao = $this->isGestaoUser($actorUserId);

        if (!$isGestao) {
            $filters['usuario_id'] = $actorUserId;
        } else {
            $filtroUsuario = trim((string) ($filters['usuario_id'] ?? ''));
            if ($filtroUsuario !== '') {
                $filters['usuario_id'] = $filtroUsuario;
            }
        }

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
        $base = $filters;
        if (!$isGestao) {
            $base['usuario_id'] = $actorUserId;
        }

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
        $monthPrevious = $this->sumForRange($base, $monthStart->modify('-1 month')->modify('first day of this month'), $monthStart->modify('-1 month')->modify('last day of this month'));
        $quarterCurrent = $this->sumForRange($base, $quarterStart, $quarterEnd);
        $prevQuarterStart = $quarterStart->modify('-3 months');
        $prevQuarterEnd = $prevQuarterStart->modify('+2 months')->modify('last day of this month');
        $quarterPrevious = $this->sumForRange($base, $prevQuarterStart, $prevQuarterEnd);

        return [
            'week' => [
                'current' => $weekCurrent,
                'previous' => $weekPrevious,
                'variation_percent' => $this->calcVariationPercent($weekCurrent, $weekPrevious),
            ],
            'month' => [
                'current' => $monthCurrent,
                'previous' => $monthPrevious,
                'variation_percent' => $this->calcVariationPercent($monthCurrent, $monthPrevious),
            ],
            'quarter' => [
                'current' => $quarterCurrent,
                'previous' => $quarterPrevious,
                'variation_percent' => $this->calcVariationPercent($quarterCurrent, $quarterPrevious),
            ],
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
        $sql = 'SELECT DISTINCT UPPER(TRIM(`perfil`)) AS `perfil` FROM `usuario_perfil` WHERE `usuario` = :usuario AND `perfil` IS NOT NULL';
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':usuario', $userId);
        $statement->execute();
        $perfis = $statement->fetchAll(PDO::FETCH_COLUMN);
        foreach ($perfis as $perfil) {
            $p = strtoupper(trim((string) $perfil));
            if ($p === 'ADMIN' || $p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
                return true;
            }
        }

        return false;
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

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
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

    private function normalizeRankingFilters(array $filters): array
    {
        $out = [
            'status' => trim((string) ($filters['status'] ?? 'ativo')),
            'tipo_lancamento' => trim((string) ($filters['tipo_lancamento'] ?? '')),
            'data_inicio' => trim((string) ($filters['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($filters['data_fim'] ?? '')),
            'usuario_id' => trim((string) ($filters['usuario_id'] ?? '')),
            'setor_codigo_area' => strtoupper(trim((string) ($filters['setor_codigo_area'] ?? ''))),
        ];

        if ($out['data_inicio'] !== '' && $out['data_fim'] !== '') {
            $dtIni = DateTime::createFromFormat('Y-m-d', $out['data_inicio']);
            $dtFim = DateTime::createFromFormat('Y-m-d', $out['data_fim']);
            if ($dtIni && $dtFim) {
                if ($dtIni > $dtFim) {
                    throw new InvalidArgumentException('A data inicial não pode ser maior que a data final.');
                }
                $days = (int) $dtIni->diff($dtFim)->days;
                if ($days > 60) {
                    throw new InvalidArgumentException('O intervalo entre data inicial e data final não pode ultrapassar 60 dias.');
                }
            }
        }

        return $out;
    }

    private function sumForRange(array $filters, DateTimeImmutable $start, DateTimeImmutable $end): float
    {
        $rangeFilters = $filters;
        $rangeFilters['data_inicio'] = $start->format('Y-m-d');
        $rangeFilters['data_fim'] = $end->format('Y-m-d');
        return $this->lancamentosRepository->sumPontos($rangeFilters);
    }

    private function calcVariationPercent(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }
        return (($current - $previous) / $previous) * 100.0;
    }
}

