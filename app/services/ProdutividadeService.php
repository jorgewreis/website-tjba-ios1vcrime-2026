<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/ProdutividadeRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLancamentosRepository.php';
require_once __DIR__ . '/../repositories/ProdutividadeLogRepository.php';
require_once __DIR__ . '/../access/AccessAreas.php';
require_once __DIR__ . '/../helpers/UsuarioPerfisAutorizacao.php';

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
                'Esta regra não está disponível para lançamento manual.'
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
}

