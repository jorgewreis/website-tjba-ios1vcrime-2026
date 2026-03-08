<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GuiasRepository.php';
require_once __DIR__ . '/../repositories/GuiasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/GuiasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/SalarioMinimoRepository.php';
require_once __DIR__ . '/../services/ReuManager.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class GuiasService
{
    private PDO $connection;
    private GuiasRepository $repository;
    private GuiasSubtarefasRepository $subtarefasRepository;
    private GuiasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private SalarioMinimoRepository $salarioMinimoRepository;
    private ReuManager $reuManager;

    private const REGRA_ALOCAR_GUIAS = 'ALLOCATE-GUIAS';

    private const REGIMES_VALIDOS = ['Aberto', 'Semiaberto', 'Fechado'];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new GuiasRepository($connection);
        $this->subtarefasRepository = new GuiasSubtarefasRepository($connection);
        $this->alocadosRepository = new GuiasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->salarioMinimoRepository = new SalarioMinimoRepository($connection);
        $this->reuManager = new ReuManager($connection);
    }

    /**
     * Calcula o valor da pena de multa em reais: (salário mínimo na data do fato × dias_multa) / 30.
     * Retorna o valor numérico para persistência em guias.valor_multa ou null se não houver salário vigente.
     */
    private function calcularValorPenaMulta(string $dataFato, int $diasMulta): ?float
    {
        if ($dataFato === '' || $diasMulta <= 0) {
            return null;
        }
        $salario = $this->salarioMinimoRepository->getValorPorData($dataFato);
        if ($salario === null) {
            return null;
        }
        return round(($salario * $diasMulta) / 30, 2);
    }

    public function listGuias(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idGuia = (string) ($row['IdGuia'] ?? '');
            if ($idGuia !== '') {
                $this->subtarefasRepository->ensureOitoSubtarefas($idGuia);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByGuiaId($idGuia);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByGuiaId($idGuia);
                $alocadosRaw = $this->alocadosRepository->findByGuiaId($idGuia);
                $rows[$i]['Alocados'] = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $rows[$i]['Alocados'][] = [
                        'iniciais' => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id' => $a['usuario_id'] ?? '',
                        'tipo' => $this->normalizeTipoAlocado($a['tipo'] ?? ''),
                    ];
                }
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['Alocados'] = [];
            }
        }
        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    public function getTipoCounts(array $filters): array
    {
        return $this->repository->countByTipo($filters);
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Verifica se já existe guia com o mesmo número do processo, mesmo denunciado (réu) e mesmo tipo.
     */
    public function existsGuiaComMesmoProcessoDenunciadoTipo(string $numeroProcesso, string $denunciado, string $tipo): bool
    {
        return $this->repository->existsByProcessoDenunciadoTipo($numeroProcesso, $denunciado, $tipo);
    }

    public function create(array $payload): string
    {
        // #region agent log
        $logPath = dirname(__DIR__, 2) . '/debug-64730a.log';
        @file_put_contents($logPath, json_encode(['sessionId' => '64730a', 'location' => 'GuiasService.php:create:entry', 'message' => 'create() started', 'data' => ['payloadKeys' => array_keys($payload), 'numeroProcessoLen' => strlen(trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? '')))], 'timestamp' => round(microtime(true) * 1000), 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion

        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        $reuId = trim((string) ($payload['reu_id'] ?? ''));
        $denunciado = trim((string) ($payload['denunciado'] ?? ''));
        if ($denunciado !== '') {
            $denunciado = TextFormatter::formatProperName($denunciado, 255);
        }
        if ($reuId === '' && $denunciado !== '' && strlen($digits) === 20) {
            $novoReuId = $this->reuManager->cadastrarReu($denunciado, null);
            if ($novoReuId !== null && $novoReuId !== '') {
                $this->reuManager->vincularReuProcesso($novoReuId, $numeroFormatado);
                $reuId = $novoReuId;
            }
        }

        $tipo = trim((string) ($payload['tipo'] ?? 'Guia - Tipo não informado'));
        if ($tipo === '') {
            $tipo = 'Guia - Tipo não informado';
        }

        $regime = trim((string) ($payload['regime'] ?? ''));
        if ($regime !== '' && !in_array($regime, self::REGIMES_VALIDOS, true)) {
            throw new InvalidArgumentException('Regime inválido. Use: Aberto, Semiaberto ou Fechado.');
        }

        $dataFato = $this->parseDate($payload['data_fato'] ?? null);
        if ($dataFato !== null && $dataFato > date('Y-m-d')) {
            throw new InvalidArgumentException('A data do fato deve ser anterior à data de hoje.');
        }

        $processoId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '' && strlen($digits) === 20) {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
            $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, $dataFato, false);
        } elseif (strlen($digits) === 20) {
            $processoId = $this->processosRepository->ensureProcess($numeroFormatado, null, $dataFato, false);
        }

        $idGuia = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'GUI',
            30
        );

        $row = [
            'id' => $idGuia,
            'numero_processo' => $numeroFormatado,
            'processo_id' => $processoId ?? $payload['processo_id'] ?? null,
            'denunciado' => $denunciado !== '' ? $denunciado : null,
            'reu_id' => $reuId !== '' ? $reuId : null,
            'tipo' => $tipo,
            'etapa' => null,
            'status' => 'pendente',
            'regime' => $regime !== '' ? $regime : null,
            'data_fato' => $dataFato,
            'dias_multa' => isset($payload['dias_multa']) ? (int) $payload['dias_multa'] : null,
            'observacoes' => null,
            'user_cadastro' => trim((string) ($payload['usuario_cadastro_id'] ?? '')),
        ];
        if ($row['user_cadastro'] === '') {
            $row['user_cadastro'] = null;
        }

        if ($row['data_fato'] !== null && isset($row['dias_multa']) && (int) $row['dias_multa'] > 0) {
            $valorMulta = $this->calcularValorPenaMulta((string) $row['data_fato'], (int) $row['dias_multa']);
            if ($valorMulta !== null) {
                $row['valor_multa'] = $valorMulta;
            }
        }

        // #region agent log
        $logPath = dirname(__DIR__, 2) . '/debug-64730a.log';
        @file_put_contents($logPath, json_encode(['sessionId' => '64730a', 'location' => 'GuiasService.php:create:beforeInsert', 'message' => 'row built', 'data' => ['idGuia' => $idGuia, 'rowKeys' => array_keys($row)], 'timestamp' => round(microtime(true) * 1000), 'hypothesisId' => 'D']) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion

        $this->repository->insert($row);

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));

        if ($usuarioCadastroId !== '') {
            try {
                $this->alocadosRepository->addAlocado($idGuia, $usuarioCadastroId, GuiasAlocadosRepository::TIPO_ALL);
            } catch (RuntimeException $e) {
                // limite já atingido ou duplicata, ignorar
            }
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_GUIAS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId) {
                try {
                    $this->alocadosRepository->addAlocado($idGuia, $usuarioIdRegra, GuiasAlocadosRepository::TIPO_RULE);
                } catch (RuntimeException $e) {
                    // limite ou duplicata
                }
            }
        }

        $repo = $this->subtarefasRepository;
        $repo->create($idGuia, $repo::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_2, 2, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_3, 3, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_4, 4, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_5, 5, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_6, 6, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_7, 7, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_8, 8, 'pendente', null);

        $this->repository->update($idGuia, ['status' => 'em_andamento']);

        // Se o cadastro já veio com data_fato e dias_multa, concluir a subtarefa 1 e ativar a 2
        $this->sincronizarDetalhamentoAposUpdate($idGuia, $usuarioCadastroId);

        return $idGuia;
    }

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $usuarioId = trim((string) ($payload['usuario_id'] ?? ''));
        unset($payload['usuario_id']);
        if ($usuarioId !== '') {
            try {
                $this->alocadosRepository->addAlocado($id, $usuarioId);
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }

        $allowed = [
            'numero_processo', 'processo_id', 'denunciado', 'reu_id', 'tipo', 'etapa', 'status',
            'regime', 'data_fato', 'dias_multa', 'observacoes', 'user_cadastro',
        ];
        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $filtered[$key] = $payload[$key];
            }
        }
        if (isset($filtered['denunciado']) && is_string($filtered['denunciado']) && $filtered['denunciado'] !== '') {
            $filtered['denunciado'] = TextFormatter::formatProperName($filtered['denunciado'], 255);
        }
        if (isset($filtered['regime'])) {
            $regime = trim((string) $filtered['regime']);
            if ($regime !== '' && !in_array($regime, self::REGIMES_VALIDOS, true)) {
                throw new InvalidArgumentException('Regime inválido. Use: Aberto, Semiaberto ou Fechado.');
            }
            $filtered['regime'] = $regime !== '' ? $regime : null;
        }
        if (isset($filtered['data_fato'])) {
            $filtered['data_fato'] = $this->parseDate($filtered['data_fato']);
            if ($filtered['data_fato'] !== null && $filtered['data_fato'] > date('Y-m-d')) {
                throw new InvalidArgumentException('A data do fato não pode ser futura.');
            }
        }

        $updated = $this->repository->update($id, $filtered);
        if ($updated && (array_key_exists('data_fato', $filtered) || array_key_exists('dias_multa', $filtered))) {
            $guia = $this->repository->findById($id);
            if ($guia !== null && isset($guia['data_fato']) && $guia['data_fato'] !== null && $guia['data_fato'] !== ''
                && isset($guia['dias_multa']) && (int) $guia['dias_multa'] > 0) {
                $valorMulta = $this->calcularValorPenaMulta((string) $guia['data_fato'], (int) $guia['dias_multa']);
                if ($valorMulta !== null) {
                    $this->repository->update($id, ['valor_multa' => $valorMulta]);
                }
            }
            $this->sincronizarDetalhamentoAposUpdate($id, $usuarioId);
        }
        return $updated;
    }

    /**
     * Se o cadastro da guia tiver data_fato e dias_multa preenchidos e a subtarefa atual for ordem 1 (Detalhamento),
     * conclui a subtarefa 1 e ativa a ordem 2, para que a subtarefa de Detalhamento não fique pendente à toa.
     */
    private function sincronizarDetalhamentoAposUpdate(string $idGuia, string $usuarioId = ''): void
    {
        $guia = $this->repository->findById($idGuia);
        if ($guia === null) {
            return;
        }
        $dataFato = isset($guia['data_fato']) && $guia['data_fato'] !== null && $guia['data_fato'] !== '' ? trim((string) $guia['data_fato']) : '';
        $diasMulta = isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : 0;
        if ($dataFato === '' || $diasMulta <= 0) {
            return;
        }

        $ordem1 = $this->subtarefasRepository->findByGuiaIdEOrdem($idGuia, 1);
        if ($ordem1 === null) {
            return;
        }
        $status1 = trim((string) ($ordem1['status'] ?? ''));
        if ($status1 !== 'pendente' && $status1 !== 'em_andamento') {
            return;
        }

        $nomeUsuario = '';
        if ($usuarioId !== '') {
            $stmt = $this->connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = :id LIMIT 1');
            $stmt->bindValue(':id', $usuarioId);
            $stmt->execute();
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u !== false) {
                $nome = trim((string) ($u['nome'] ?? ''));
                $sobrenome = trim((string) ($u['sobrenome'] ?? ''));
                $nomeUsuario = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            }
        }

        $dadosEspecificos = json_encode(array_filter([
            'data_fato' => $dataFato,
            'dias_multa' => $diasMulta,
            'nome' => $nomeUsuario,
            'usuario_id' => $usuarioId,
        ], static function ($v) { return $v !== '' && $v !== null; }));

        $subtarefa1Id = (string) ($ordem1['id'] ?? '');
        if ($subtarefa1Id !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefa1Id, $dadosEspecificos);
            $this->subtarefasRepository->marcarConcluida($subtarefa1Id);
        }

        $ordem2 = $this->subtarefasRepository->findByGuiaIdEOrdem($idGuia, 2);
        if ($ordem2 !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $ordem2['id'], 'em_andamento');
        }
        $updatePayload = ['status' => 'em_andamento'];
        $valorMulta = $this->calcularValorPenaMulta($dataFato, $diasMulta);
        if ($valorMulta !== null) {
            $updatePayload['valor_multa'] = $valorMulta;
        }
        $this->repository->update($idGuia, $updatePayload);
    }

    public function cancel(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }
        return $this->repository->update($id, ['status' => 'cancelada']);
    }

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $this->subtarefasRepository->deleteByGuiaId($id);
        $this->alocadosRepository->deleteByGuiaId($id);
        return $this->repository->delete($id);
    }

    /**
     * Conclui a subtarefa. Retorna array com chave valor_multa_calculado (valor formatado em R$) quando
     * o sistema calcular automaticamente a pena de multa (ordem 1 concluída com data_fato e dias_multa).
     *
     * @return array{valor_multa_calculado?: string}
     */
    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $guiaId = trim((string) ($subtarefa['guia_id'] ?? ''));
        $resultado = [];

        if ($guiaId === '') {
            throw new InvalidArgumentException('Guia da subtarefa não identificada.');
        }

        $usuarioIdMovimentacao = null;
        $dadosArray = [];
        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            }
        }
        if ($usuarioIdMovimentacao !== null && $usuarioIdMovimentacao !== '') {
            try {
                $this->alocadosRepository->addAlocado($guiaId, $usuarioIdMovimentacao);
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }

        if ($ordem === 2 && (!isset($dadosArray['valor_multa']) || $dadosArray['valor_multa'] === '' || $dadosArray['valor_multa'] === null)) {
            $guia = $this->repository->findById($guiaId);
            if ($guia !== null && isset($guia['valor_multa']) && $guia['valor_multa'] !== null && $guia['valor_multa'] !== '') {
                $dadosArray['valor_multa'] = 'R$ ' . number_format((float) $guia['valor_multa'], 2, ',', '.');
                $dadosJson = json_encode($dadosArray);
            }
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);

        if ($ordem === 1) {
            $dataFato = isset($dadosArray['data_fato']) ? trim((string) $dadosArray['data_fato']) : '';
            $diasMulta = isset($dadosArray['dias_multa']) ? (int) $dadosArray['dias_multa'] : 0;
            $temDados = $dataFato !== '' && $diasMulta > 0;
            $guia = $this->repository->findById($guiaId);
            if ($temDados && $guia !== null) {
                $payloadGuia = ['data_fato' => $dataFato, 'dias_multa' => $diasMulta];
                $valorMulta = $this->calcularValorPenaMulta($dataFato, $diasMulta);
                if ($valorMulta !== null) {
                    $payloadGuia['valor_multa'] = $valorMulta;
                    $resultado['valor_multa_calculado'] = 'R$ ' . number_format($valorMulta, 2, ',', '.');
                }
                $this->repository->update($guiaId, $payloadGuia);
            }
            if ($temDados) {
                $proxima = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 2);
                if ($proxima !== null) {
                    $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
                }
            } else {
                $ordem2 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 2);
                if ($ordem2 !== null) {
                    $this->subtarefasRepository->atualizarStatus((string) $ordem2['id'], GuiasSubtarefasRepository::STATUS_PULADA);
                }
                $ordem3 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 3);
                if ($ordem3 !== null) {
                    $this->subtarefasRepository->atualizarStatus((string) $ordem3['id'], 'em_andamento');
                }
            }
            $this->repository->update($guiaId, ['status' => 'em_andamento']);
            return $resultado;
        }

        if ($ordem < 8) {
            $proximaOrdem = $ordem + 1;
            $proxima = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, $proximaOrdem);
            if ($proxima !== null) {
                $statusProx = trim((string) ($proxima['status'] ?? ''));
                if ($statusProx !== 'concluida' && $statusProx !== GuiasSubtarefasRepository::STATUS_PULADA) {
                    $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
                } else {
                    $next = $proximaOrdem + 1;
                    while ($next <= 8) {
                        $seg = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, $next);
                        if ($seg !== null && trim((string) ($seg['status'] ?? '')) !== 'concluida' && trim((string) ($seg['status'] ?? '')) !== GuiasSubtarefasRepository::STATUS_PULADA) {
                            $this->subtarefasRepository->atualizarStatus((string) $seg['id'], 'em_andamento');
                            break;
                        }
                        $next++;
                    }
                }
            }
            $this->repository->update($guiaId, ['status' => 'em_andamento']);
        } else {
            $this->repository->update($guiaId, ['status' => 'concluída']);
        }

        return $resultado;
    }

    public function cancelarSubtarefa(string $subtarefaId): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        if ($ordem !== 1) {
            throw new InvalidArgumentException('Apenas a subtarefa "Detalhamento de informações" pode ser cancelada.');
        }

        $guiaId = trim((string) ($subtarefa['guia_id'] ?? ''));
        if ($guiaId === '') {
            throw new InvalidArgumentException('Guia da subtarefa não identificada.');
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, GuiasSubtarefasRepository::STATUS_CANCELADA);

        $ordem2 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 2);
        if ($ordem2 !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $ordem2['id'], GuiasSubtarefasRepository::STATUS_PULADA);
        }
        $ordem3 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 3);
        if ($ordem3 !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $ordem3['id'], 'em_andamento');
        }
        $this->repository->update($guiaId, ['status' => 'em_andamento']);
    }

    public function criarPrimeiraSubtarefa(string $guiaId, ?string $usuarioId = null): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            throw new InvalidArgumentException('Identificador da guia é obrigatório.');
        }
        if ($this->repository->findById($guiaId) === null) {
            throw new InvalidArgumentException('Guia não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByGuiaId($guiaId);
        if (count($existentes) === 0) {
            $this->subtarefasRepository->ensureOitoSubtarefas($guiaId);
            $this->repository->update($guiaId, ['status' => 'em_andamento']);
            if ($usuarioId !== null && trim($usuarioId) !== '') {
                try {
                    $this->alocadosRepository->addAlocado($guiaId, trim($usuarioId));
                } catch (RuntimeException $e) {
                    // limite ou duplicata
                }
            }
            return;
        }
        $ordem1 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 1);
        if ($ordem1 !== null && trim((string) ($ordem1['status'] ?? '')) !== 'concluida' && trim((string) ($ordem1['status'] ?? '')) !== GuiasSubtarefasRepository::STATUS_CANCELADA) {
            $this->subtarefasRepository->atualizarStatus((string) $ordem1['id'], 'em_andamento');
        }
        $this->repository->update($guiaId, ['status' => 'em_andamento']);
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            try {
                $this->alocadosRepository->addAlocado($guiaId, trim($usuarioId));
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === GuiasAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === GuiasAlocadosRepository::TIPO_ALL || $t === 'all') {
            return 'all';
        }
        return 'specific';
    }

    private function formatarCNJ(string $digits): string
    {
        if (strlen($digits) !== 20) {
            return $digits;
        }
        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digits, 0, 7),
            substr($digits, 7, 2),
            substr($digits, 9, 4),
            substr($digits, 13, 1),
            substr($digits, 14, 2),
            substr($digits, 16, 4)
        );
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1e12) {
                $ts = (int) floor($ts / 1000);
            }
            $date = date('Y-m-d', $ts);
            return $date !== false && $this->isValidDateYear($date) ? $date : null;
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return null;
        }
        $date = date('Y-m-d', $ts);
        return $date !== false && $this->isValidDateYear($date) ? $date : null;
    }

    private function isValidDateYear(string $ymd): bool
    {
        $y = (int) substr($ymd, 0, 4);
        return $y >= 1900 && $y <= 2100;
    }
}
