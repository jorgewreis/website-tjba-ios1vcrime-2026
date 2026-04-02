<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/ProdutividadeRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLancamentosRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLogRepository.php';

final class ProdutividadeService
{
    private PDO $connection;
    private ProdutividadeRegrasRepository $regrasRepository;
    private ProdutividadeLancamentosRepository $lancamentosRepository;
    private ProdutividadeLogRepository $logRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->regrasRepository = new ProdutividadeRegrasRepository($connection);
        $this->lancamentosRepository = new ProdutividadeLancamentosRepository($connection);
        $this->logRepository = new ProdutividadeLogRepository($connection);
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
}

