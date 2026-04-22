<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/SalaMidiasTarefasRepository.php';
require_once __DIR__ . '/../repositories/SalaMidiasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/SalaMidiasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../helpers/CartorioProdutividade.php';

final class SalaMidiasService
{
    private const REGRA_ALOCAR_MIDIAS = 'ALLOCATE-MIDIAS';

    private PDO $connection;
    private SalaMidiasTarefasRepository $tarefasRepository;
    private SalaMidiasSubtarefasRepository $subtarefasRepository;
    private SalaMidiasAlocadosRepository $alocadosRepository;
    private SalaAudienciasPautaRepository $pautaRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->tarefasRepository = new SalaMidiasTarefasRepository($connection);
        $this->subtarefasRepository = new SalaMidiasSubtarefasRepository($connection);
        $this->alocadosRepository = new SalaMidiasAlocadosRepository($connection);
        $this->pautaRepository = new SalaAudienciasPautaRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $filters = $this->normalizeListFilters($filters);
        $rows = $this->tarefasRepository->fetchList($filters, $limit, $offset);
        $ids = array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['id'] ?? ''));
        }, $rows)));
        $alocadosByMidia = $this->alocadosRepository->findByMidiaIds($ids);
        foreach ($rows as $index => $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $rows[$index]['Subtarefas'] = $id !== '' ? $this->subtarefasRepository->findByMidiaId($id) : [];
            $rows[$index]['Alocados'] = $id !== '' ? $this->formatAlocados($alocadosByMidia[$id] ?? []) : [];
        }

        return [
            'rows' => $rows,
            'total' => $this->tarefasRepository->count($filters),
        ];
    }

    public function find(string $id): ?array
    {
        $row = $this->tarefasRepository->findById($id);
        if ($row === null) {
            return null;
        }
        $row['Subtarefas'] = $this->subtarefasRepository->findByMidiaId($id);
        $alocadosByMidia = $this->alocadosRepository->findByMidiaIds([$id]);
        $row['Alocados'] = $this->formatAlocados($alocadosByMidia[$id] ?? []);

        return $row;
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->tarefasRepository->countByStatus($filters);
    }

    public function create(array $payload, string $usuarioId): string
    {
        $usuarioId = trim($usuarioId);
        if ($usuarioId === '') {
            throw new InvalidArgumentException('Sessão inválida.');
        }

        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? ''));
        $classe = trim((string) ($payload['classe_processo'] ?? $payload['classe'] ?? ''));
        $dataAudiencia = trim((string) ($payload['data_audiencia'] ?? ''));
        $tipoAudiencia = trim((string) ($payload['tipo_audiencia'] ?? ''));
        $pautaId = trim((string) ($payload['pauta_id'] ?? ''));
        $qtdMidias = (int) ($payload['qtd_midias'] ?? 1);
        $origem = trim((string) ($payload['origem'] ?? 'manual'));
        if ($qtdMidias < 1) {
            $qtdMidias = 1;
        }

        if ($pautaId !== '') {
            $pauta = $this->pautaRepository->findById($pautaId);
            if ($pauta === null) {
                throw new InvalidArgumentException('Audiência selecionada não encontrada.');
            }
            $numeroProcesso = trim((string) ($pauta['numero_processo'] ?? $numeroProcesso));
            $classe = trim((string) ($pauta['classe_nome'] ?? $classe));
            $dataAudiencia = trim((string) ($pauta['data_audiencia'] ?? $dataAudiencia));
            $tipoAudiencia = trim((string) ($pauta['tipo_audiencia'] ?? $tipoAudiencia));
        }

        if ($numeroProcesso === '' || $classe === '' || $dataAudiencia === '' || $tipoAudiencia === '') {
            throw new InvalidArgumentException('Preencha processo, classe, data e tipo de audiência.');
        }
        $this->assertDataNaoFutura($dataAudiencia);

        if ($pautaId !== '') {
            $existentePauta = $this->tarefasRepository->findAnyByPautaId($pautaId);
            if ($existentePauta !== null) {
                $ativo = (int) ($existentePauta['ativo'] ?? 0);
                if ($ativo === 1) {
                    throw new InvalidArgumentException('Já existe cadastro de mídias para esta audiência.');
                }
                throw new InvalidArgumentException(
                    'Esta audiência já possui registro de mídias (inativo). Não é possível cadastrar novamente o mesmo vínculo.'
                );
            }
        } else {
            $digits = preg_replace('/\D+/', '', $numeroProcesso);
            if ($digits !== '') {
                $tipoNorm = mb_strtolower(trim($tipoAudiencia), 'UTF-8');
                $dupManual = $this->tarefasRepository->findActiveManualDuplicate($digits, $dataAudiencia, $tipoNorm);
                if ($dupManual !== null) {
                    throw new InvalidArgumentException(
                        'Já existe cadastro de mídias para este processo, data e tipo de audiência.'
                    );
                }
            }
        }

        $this->connection->beginTransaction();
        try {
            try {
                $id = $this->tarefasRepository->create([
                    'pauta_id' => $pautaId !== '' ? $pautaId : null,
                    'processo_id' => null,
                    'numero_processo' => $numeroProcesso,
                    'classe_processo' => $classe,
                    'data_audiencia' => $dataAudiencia,
                    'tipo_audiencia' => $tipoAudiencia,
                    'qtd_midias' => $qtdMidias,
                    'origem' => $origem === 'automatico' ? 'automatico' : 'manual',
                    'status' => 'pendente',
                    'observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                    'criado_por' => $usuarioId,
                    'atualizado_por' => $usuarioId,
                ]);
            } catch (\PDOException $e) {
                $state = $e->errorInfo[0] ?? '';
                $msg = $e->getMessage();
                if ($state === '23000'
                    && (strpos($msg, 'uk_sala_midias_tarefa_pauta') !== false
                        || stripos($msg, 'Duplicate') !== false)) {
                    throw new InvalidArgumentException('Já existe cadastro de mídias para esta audiência.');
                }
                throw $e;
            }

            $this->inicializarSubtarefas($id);
            $this->aplicarAutoAlocacaoRegraECadastro($id, $usuarioId);
            AlocacaoTarefasPerfisHelper::registrarAlocacoesDirecionadasNoCadastro(
                $this->connection,
                $usuarioId,
                $payload,
                function (string $uid) use ($id): void {
                    $this->alocadosRepository->addAlocado($id, $uid, SalaMidiasAlocadosRepository::TIPO_SPECIFIC);
                }
            );

            $this->connection->commit();

            $origemSalva = $origem === 'automatico' ? 'automatico' : 'manual';
            $this->registrarProdutividadeCadastroTarefa($id, $usuarioId, $numeroProcesso, $classe, $tipoAudiencia, $origemSalva);

            return $id;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function update(string $id, array $payload, string $usuarioId): bool
    {
        $row = $this->tarefasRepository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Tarefa de mídia não encontrada.');
        }
        $qtdMidias = isset($payload['qtd_midias']) ? (int) $payload['qtd_midias'] : (int) ($row['qtd_midias'] ?? 1);
        if ($qtdMidias < 1) {
            $qtdMidias = 1;
        }
        $status = trim((string) ($payload['status'] ?? $row['status'] ?? 'em_andamento'));
        if (!in_array($status, ['pendente', 'em_andamento', 'concluida', 'cancelada'], true)) {
            $status = 'em_andamento';
        }
        $obsNova = trim((string) ($payload['observacoes'] ?? $row['observacoes'] ?? '')) ?: null;
        $obsVelha = trim((string) ($row['observacoes'] ?? '')) ?: null;
        $mudou = (int) ($row['qtd_midias'] ?? 1) !== $qtdMidias
            || trim((string) ($row['status'] ?? '')) !== $status
            || $obsVelha !== $obsNova;

        $ok = $this->tarefasRepository->update($id, [
            'qtd_midias' => $qtdMidias,
            'status' => $status,
            'observacoes' => $obsNova,
            'atualizado_por' => $usuarioId,
        ]);
        if ($ok && $mudou) {
            $atual = $this->tarefasRepository->findById($id) ?? $row;
            $this->registrarProdutividadeEdicaoTarefa(
                $id,
                $usuarioId,
                trim((string) ($atual['numero_processo'] ?? '')),
                trim((string) ($atual['classe_processo'] ?? '')),
                trim((string) ($atual['tipo_audiencia'] ?? ''))
            );
        }

        return $ok;
    }

    /**
     * Exclui (hard delete) a tarefa de mídia e todas as subtarefas/alocações vinculadas.
     *
     * @return array{deleted: bool, subtarefas_toasts: list<string>, tarefa_toast: string}
     */
    public function delete(string $id, ?string $usuarioId = null): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $subtarefaLabels = [
            'download_midia' => 'Download de Mídia',
            'sincronizacao_midia' => 'Sincronização de Mídia',
            'upload_midia' => 'Upload de Mídia',
            'certidao_upload_midia' => 'Certidão de Upload de Mídia',
        ];

        $subtarefas = $this->subtarefasRepository->findByMidiaId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $codigo = trim((string) ($subtarefa['codigo'] ?? ''));
            $titulo = trim((string) ($subtarefa['titulo'] ?? ''));
            $label = $subtarefaLabels[$codigo] ?? ($titulo !== '' ? $titulo : $codigo);
            if ($label !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . $label . '.';
            }
        }

        $this->connection->beginTransaction();
        try {
            $this->subtarefasRepository->deleteByMidiaId($id);
            $this->alocadosRepository->deleteByMidiaId($id);
            $deleted = $this->tarefasRepository->delete($id);
            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal de mídias excluída com sucesso.',
        ];
    }

    public function concluirSubtarefa(string $subtarefaId, string $usuarioId, array $dados = []): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }
        $midiaId = trim((string) ($subtarefa['midia_id'] ?? ''));
        $tarefa = $this->tarefasRepository->findById($midiaId);
        if ($tarefa === null) {
            throw new InvalidArgumentException('Tarefa principal não encontrada.');
        }

        $codigo = trim((string) ($subtarefa['codigo'] ?? ''));
        $qtd = max(1, (int) ($tarefa['qtd_midias'] ?? 1));
        if ($codigo === 'download_midia' && isset($dados['qtd_midias'])) {
            $qtdInformada = (int) $dados['qtd_midias'];
            if ($qtdInformada < 1) {
                throw new InvalidArgumentException('Quantidade de mídias inválida para concluir download.');
            }
            $qtd = $qtdInformada;
            $this->tarefasRepository->update($midiaId, [
                'qtd_midias' => $qtd,
                'status' => trim((string) ($tarefa['status'] ?? 'em_andamento')),
                'observacoes' => $tarefa['observacoes'] ?? null,
                'atualizado_por' => $usuarioId,
            ]);
            $tarefa['qtd_midias'] = $qtd;
        }
        $pontos = $this->calcularPontosSubtarefa($codigo, $qtd);

        $dadosJson = $dados === [] ? null : json_encode($dados, JSON_UNESCAPED_UNICODE);
        $this->subtarefasRepository->updateStatus(
            $subtarefaId,
            SalaMidiasSubtarefasRepository::STATUS_CONCLUIDA,
            $pontos,
            $dadosJson,
            $usuarioId
        );
        $this->alocadosRepository->addAlocado($midiaId, $usuarioId, SalaMidiasAlocadosRepository::TIPO_SPECIFIC);
        $this->registrarProdutividadeSubtarefa($codigo, $subtarefaId, $usuarioId, $tarefa, $qtd, $pontos);

        $ordemAtual = (int) ($subtarefa['ordem'] ?? 0);
        $proxima = $this->subtarefasRepository->findByMidiaIdAndOrdem($midiaId, $ordemAtual + 1);
        if ($proxima !== null && trim((string) ($proxima['status'] ?? '')) === SalaMidiasSubtarefasRepository::STATUS_PENDENTE) {
            $this->subtarefasRepository->updateStatus(
                (string) ($proxima['id'] ?? ''),
                SalaMidiasSubtarefasRepository::STATUS_EM_ANDAMENTO
            );
        }

        $subtarefas = $this->subtarefasRepository->findByMidiaId($midiaId);
        $nConcluidas = 0;
        foreach ($subtarefas as $st) {
            if (trim((string) ($st['status'] ?? '')) === SalaMidiasSubtarefasRepository::STATUS_CONCLUIDA) {
                $nConcluidas++;
            }
        }
        $totalSub = count($subtarefas);
        $tarefaAtual = $this->tarefasRepository->findById($midiaId) ?? $tarefa;
        $qtdTarefa = max(1, (int) ($tarefaAtual['qtd_midias'] ?? $qtd));

        if ($totalSub > 0 && $nConcluidas === $totalSub) {
            $this->tarefasRepository->update($midiaId, [
                'qtd_midias' => $qtdTarefa,
                'status' => 'concluida',
                'observacoes' => $tarefaAtual['observacoes'] ?? null,
                'atualizado_por' => $usuarioId,
            ]);
        } elseif ($nConcluidas >= 1) {
            $this->tarefasRepository->update($midiaId, [
                'qtd_midias' => $qtdTarefa,
                'status' => 'em_andamento',
                'observacoes' => $tarefaAtual['observacoes'] ?? null,
                'atualizado_por' => $usuarioId,
            ]);
        }
    }

    public function alocarUsuarioDirecionado(string $midiaId, string $targetUsuarioId, string $actorId): void
    {
        AlocacaoTarefasPerfisHelper::registrarAlocacaoDirecionadaNaEdicao(
            $this->connection,
            $actorId,
            $targetUsuarioId,
            function (string $uid) use ($midiaId): void {
                $this->alocadosRepository->addAlocado($midiaId, $uid, SalaMidiasAlocadosRepository::TIPO_SPECIFIC);
            }
        );
    }

    public function removeAlocado(string $alocadoId): bool
    {
        return $this->alocadosRepository->deleteById($alocadoId);
    }

    public function listPautasConcluidasPorData(string $dataYmd, string $numeroProcesso = ''): array
    {
        $dataYmd = trim($dataYmd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataYmd)) {
            throw new InvalidArgumentException('Data inválida.');
        }
        $sql = '
            SELECT
                p.`id_pauta`,
                p.`tipo_audiencia`,
                p.`data_audiencia`,
                p.`hora_audiencia`,
                p.`status`,
                pr.`numero_processo`,
                COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS classe_nome
            FROM `sala_audiencias_pauta` p
            LEFT JOIN `processos` pr ON pr.`id` = p.`processo`
            LEFT JOIN `classes` cl ON cl.`id` = pr.`classe_id`
            WHERE p.`ativo` = 1
              AND p.`data_audiencia` = :data_audiencia
              AND p.`status` = \'finalizada\'
              AND NOT EXISTS (
                  SELECT 1 FROM `sala_midias_tarefa` sm
                  WHERE sm.`pauta_id` = p.`id_pauta`
              )';
        $params = [':data_audiencia' => $dataYmd];
        $numeroProcesso = trim($numeroProcesso);
        if ($numeroProcesso !== '') {
            $sql .= ' AND pr.`numero_processo` = :numero_processo';
            $params[':numero_processo'] = $numeroProcesso;
        }
        $sql .= ' ORDER BY p.`hora_audiencia` ASC';

        $statement = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findUltimaPautaFinalizadaPorProcesso(string $numeroProcesso): ?array
    {
        $numeroProcesso = trim($numeroProcesso);
        if ($numeroProcesso === '') {
            return null;
        }

        $sql = '
            SELECT
                p.`id_pauta`,
                p.`tipo_audiencia`,
                p.`data_audiencia`,
                p.`hora_audiencia`,
                p.`status`,
                pr.`numero_processo`,
                COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS classe_nome
            FROM `sala_audiencias_pauta` p
            LEFT JOIN `processos` pr ON pr.`id` = p.`processo`
            LEFT JOIN `classes` cl ON cl.`id` = pr.`classe_id`
            WHERE p.`ativo` = 1
              AND p.`status` = \'finalizada\'
              AND pr.`numero_processo` = :numero_processo
              AND NOT EXISTS (
                  SELECT 1 FROM `sala_midias_tarefa` sm
                  WHERE sm.`pauta_id` = p.`id_pauta`
              )
            ORDER BY p.`data_audiencia` DESC, p.`hora_audiencia` DESC
            LIMIT 1';
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':numero_processo', $numeroProcesso);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function ensureAutoFromPautaIfEligible(string $pautaId, ?string $usuarioId): ?string
    {
        $pautaId = trim($pautaId);
        if ($pautaId === '') {
            return null;
        }
        $pauta = $this->pautaRepository->findById($pautaId);
        if ($pauta === null) {
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] pauta nao encontrada: ' . $pautaId);
            return null;
        }
        $status = strtolower(trim((string) ($pauta['status'] ?? '')));
        if (!in_array($status, ['realizada', 'finalizada'], true)) {
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] pauta ' . $pautaId . ' com status ' . ($status ?: '(vazio)') . ' nao elegivel (precisa ser realizada ou finalizada).');
            return null;
        }

        $actor = trim((string) $usuarioId);
        if ($actor === '') {
            $actor = trim((string) ($_SESSION['id_usuario'] ?? ''));
        }

        // Tarefa ja ativa para a pauta: nao faz nada.
        $existingAtivo = $this->tarefasRepository->findByPautaId($pautaId);
        if ($existingAtivo !== null) {
            return trim((string) ($existingAtivo['id'] ?? '')) ?: null;
        }

        // Reaproveita tarefa inativa (soft-deleted) reativando-a em vez de criar nova.
        $existingAny = $this->tarefasRepository->findAnyByPautaId($pautaId);
        if ($existingAny !== null) {
            $idExistente = trim((string) ($existingAny['id'] ?? ''));
            if ($idExistente !== '') {
                $this->tarefasRepository->reactivate($idExistente, $actor !== '' ? $actor : null);
                error_log('[SalaMidias.ensureAutoFromPautaIfEligible] tarefa inativa reativada: id=' . $idExistente . ' pauta=' . $pautaId);
                return $idExistente;
            }
        }

        $numeroProcesso = trim((string) ($pauta['numero_processo'] ?? ''));
        if ($numeroProcesso === '') {
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] pauta ' . $pautaId . ' sem numero_processo. Tarefa automatica de midia nao criada.');
            return null;
        }

        $classeProcesso = trim((string) ($pauta['classe_nome'] ?? ''));
        if ($classeProcesso === '') {
            $classeProcesso = 'Classe não informada';
        }

        $dataAudiencia = trim((string) ($pauta['data_audiencia'] ?? ''));
        $tipoAudiencia = trim((string) ($pauta['tipo_audiencia'] ?? ''));
        if ($dataAudiencia === '' || $tipoAudiencia === '') {
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] pauta ' . $pautaId . ' com data/tipo ausentes. Tarefa automatica nao criada.');
            return null;
        }

        if ($actor === '') {
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] pauta ' . $pautaId . ' sem usuario responsavel. Tarefa automatica nao criada.');
            return null;
        }

        /*
         * Criacao direta via repositorio + inicializacao de subtarefas. Nao usa
         * $this->create(...) porque la ha validacoes restritivas (findAnyByPautaId
         * bloqueando re-criacao, assertDataNaoFutura, etc.) que podem abortar o
         * hook automatico de forma silenciosa. A auto-criacao usa caminho enxuto.
         */
        $this->connection->beginTransaction();
        try {
            $novoId = $this->tarefasRepository->create([
                'pauta_id' => $pautaId,
                'processo_id' => null,
                'numero_processo' => $numeroProcesso,
                'classe_processo' => $classeProcesso,
                'data_audiencia' => $dataAudiencia,
                'tipo_audiencia' => $tipoAudiencia,
                'qtd_midias' => 1,
                'origem' => 'automatico',
                'status' => 'pendente',
                'observacoes' => null,
                'criado_por' => $actor,
                'atualizado_por' => $actor,
            ]);
            $this->inicializarSubtarefas($novoId);
            $this->aplicarAutoAlocacaoRegraECadastro($novoId, $actor);
            $this->connection->commit();

            $this->registrarProdutividadeCadastroTarefa(
                $novoId,
                $actor,
                $numeroProcesso,
                $classeProcesso,
                $tipoAudiencia,
                'automatico'
            );

            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] tarefa automatica criada: id=' . $novoId . ' pauta=' . $pautaId);
            return $novoId;
        } catch (PDOException $e) {
            $this->connection->rollBack();
            $msg = (string) $e->getMessage();
            if (strpos($msg, 'uk_sala_midias_tarefa_pauta') !== false || stripos($msg, 'Duplicate') !== false) {
                $dupe = $this->tarefasRepository->findByPautaId($pautaId);
                return $dupe !== null ? trim((string) ($dupe['id'] ?? '')) : null;
            }
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] erro PDO ao criar tarefa pauta=' . $pautaId . ': ' . $msg);
            throw $e;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            error_log('[SalaMidias.ensureAutoFromPautaIfEligible] erro geral ao criar tarefa pauta=' . $pautaId . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista padrão: sem filtro de status, oculta tarefas finalizadas (concluídas).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeListFilters(array $filters): array
    {
        $status = trim((string) ($filters['status'] ?? ''));
        $filters['excluir_concluida'] = $status === '';

        return $filters;
    }

    private function inicializarSubtarefas(string $midiaId): void
    {
        foreach ($this->catalogoSubtarefas() as $sub) {
            $this->subtarefasRepository->create([
                'midia_id' => $midiaId,
                'codigo' => $sub['codigo'],
                'titulo' => $sub['titulo'],
                'sistema' => $sub['sistema'],
                'ordem' => $sub['ordem'],
                'status' => $sub['ordem'] === 1
                    ? SalaMidiasSubtarefasRepository::STATUS_EM_ANDAMENTO
                    : SalaMidiasSubtarefasRepository::STATUS_PENDENTE,
                'pontos_calculados' => 0.0,
            ]);
        }
    }

    private function aplicarAutoAlocacaoRegraECadastro(string $midiaId, string $usuarioCadastroId): void
    {
        $regraRow = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_MIDIAS);
        $usuarioIdRegra = $regraRow !== null ? trim((string) ($regraRow['usuario_id'] ?? '')) : '';

        $chefiaCadastro = $usuarioCadastroId !== ''
            && AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioCadastroId);
        $chefiaRegra = $usuarioIdRegra !== ''
            && AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioIdRegra);

        if ($usuarioCadastroId !== '' && !$chefiaCadastro) {
            $mesmoUsuarioDaRegra = $usuarioIdRegra !== '' && $usuarioIdRegra === $usuarioCadastroId;
            if ($mesmoUsuarioDaRegra) {
                if (!$chefiaRegra) {
                    $this->alocadosRepository->addAlocado($midiaId, $usuarioCadastroId, SalaMidiasAlocadosRepository::TIPO_RULE);
                }
            } else {
                $this->alocadosRepository->addAlocado($midiaId, $usuarioCadastroId, SalaMidiasAlocadosRepository::TIPO_ALL);
            }
        }

        if ($usuarioIdRegra !== '' && !$chefiaRegra && $usuarioIdRegra !== $usuarioCadastroId) {
            $this->alocadosRepository->addAlocado($midiaId, $usuarioIdRegra, SalaMidiasAlocadosRepository::TIPO_RULE);
        }
    }

    private function calcularPontosSubtarefa(string $codigo, int $qtdMidias): float
    {
        return match ($codigo) {
            'download_midia' => 2.0,
            'sincronizacao_midia' => 3.0 * $qtdMidias,
            'upload_midia' => 2.0 * $qtdMidias,
            'certidao_upload_midia' => 2.0,
            default => 0.0,
        };
    }

    private function registrarProdutividadeSubtarefa(
        string $codigo,
        string $subtarefaId,
        string $usuarioId,
        array $tarefa,
        int $qtdMidias,
        float $pontos
    ): void {
        $eventByCodigo = [
            'download_midia' => 'sala-midias.subtarefa.download_midia',
            'sincronizacao_midia' => 'sala-midias.subtarefa.sincronizacao_midia',
            'upload_midia' => 'sala-midias.subtarefa.upload_midia',
            'certidao_upload_midia' => 'sala-midias.subtarefa.certidao_upload_midia',
        ];
        if (!isset($eventByCodigo[$codigo])) {
            return;
        }
        CartorioProdutividade::record($this->connection, $usuarioId, $eventByCodigo[$codigo], [
            'quantidade' => ($codigo === 'sincronizacao_midia' || $codigo === 'upload_midia') ? $qtdMidias : 1,
            'pontos_calculados_override' => $pontos,
            'referencia_item_id' => $subtarefaId,
            'referencia_sistema' => 'sala-midias',
            'numero_processo' => trim((string) ($tarefa['numero_processo'] ?? '')),
            'classe_processo' => trim((string) ($tarefa['classe_processo'] ?? '')),
            'qtd_midias' => $qtdMidias,
        ]);
    }

    private function registrarProdutividadeCadastroTarefa(
        string $midiaId,
        string $usuarioId,
        string $numeroProcesso,
        string $classeProcesso,
        string $tipoAudiencia,
        string $origem
    ): void {
        $origemLabel = $origem === 'automatico' ? 'automática' : 'manual';
        CartorioProdutividade::record($this->connection, $usuarioId, 'sala-midias.create', [
            'referencia_item_id' => $midiaId,
            'referencia_sistema' => 'sala-midias',
            'numero_processo' => $numeroProcesso,
            'classe_processo' => $classeProcesso,
            'tipo_audiencia' => $tipoAudiencia,
            'origem' => $origemLabel,
        ]);
    }

    private function registrarProdutividadeEdicaoTarefa(
        string $midiaId,
        string $usuarioId,
        string $numeroProcesso,
        string $classeProcesso,
        string $tipoAudiencia
    ): void {
        CartorioProdutividade::record($this->connection, $usuarioId, 'sala-midias.update', [
            'referencia_item_id' => $midiaId,
            'referencia_sistema' => 'sala-midias',
            'numero_processo' => $numeroProcesso,
            'classe_processo' => $classeProcesso,
            'tipo_audiencia' => $tipoAudiencia,
        ]);
    }

    private function assertDataNaoFutura(string $dataYmd): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataYmd)) {
            throw new InvalidArgumentException('Data da audiência inválida.');
        }
        $input = DateTimeImmutable::createFromFormat('Y-m-d', $dataYmd);
        if ($input === false) {
            throw new InvalidArgumentException('Data da audiência inválida.');
        }
        $hoje = new DateTimeImmutable('today');
        if ($input > $hoje) {
            throw new InvalidArgumentException('A data da audiência não pode ser futura.');
        }
    }

    private function catalogoSubtarefas(): array
    {
        return [
            ['ordem' => 1, 'codigo' => 'download_midia', 'titulo' => 'Download de Mídia', 'sistema' => 'Lifesize'],
            ['ordem' => 2, 'codigo' => 'sincronizacao_midia', 'titulo' => 'Sincronização de Mídia', 'sistema' => 'PJe'],
            ['ordem' => 3, 'codigo' => 'upload_midia', 'titulo' => 'Upload de Mídia', 'sistema' => 'Pasta Pública'],
            ['ordem' => 4, 'codigo' => 'certidao_upload_midia', 'titulo' => 'Certidão de Upload de Mídia', 'sistema' => 'PJe'],
        ];
    }

    private function formatAlocados(array $raw): array
    {
        $formatted = [];
        foreach ($raw as $alocado) {
            $tipo = trim((string) ($alocado['tipo'] ?? ''));
            if (!in_array($tipo, ['rule', 'all', 'specific'], true)) {
                $tipo = 'specific';
            }
            $formatted[] = [
                'alocado_id' => (string) ($alocado['id'] ?? ''),
                'iniciais' => (string) ($alocado['iniciais'] ?? '—'),
                'nome_completo' => (string) ($alocado['nome_completo'] ?? ''),
                'usuario_id' => (string) ($alocado['usuario_id'] ?? ''),
                'tipo' => $tipo,
            ];
        }
        usort($formatted, static function (array $a, array $b): int {
            static $order = ['rule' => 0, 'all' => 1, 'specific' => 2];

            return ($order[$a['tipo']] ?? 9) <=> ($order[$b['tipo']] ?? 9);
        });

        return $formatted;
    }
}
