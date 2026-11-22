<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GuiasRepository.php';
require_once __DIR__ . '/../repositories/GuiasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/GuiasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/SalarioMinimoRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../helpers/CartorioTarefaStatusPrincipalHelper.php';
require_once __DIR__ . '/../helpers/GuiasCadastroTransactionalGuard.php';
require_once __DIR__ . '/../helpers/GuiasIdempotencia.php';

final class GuiasService
{
    private static ?WeakMap $unsafeConnections = null;
    private bool $idempotencyReplayed = false;
    private PDO $connection;
    private GuiasRepository $repository;
    private GuiasSubtarefasRepository $subtarefasRepository;
    private GuiasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private PartesRepository $partesRepository;
    private ClassesRepository $classesRepository;
    private SalarioMinimoRepository $salarioMinimoRepository;

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
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->salarioMinimoRepository = new SalarioMinimoRepository($connection);
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

    /**
     * Garante cálculo automático de valor_multa quando já existem data_fato e dias_multa.
     * Também atualiza em banco quando encontrar guia com valor ausente.
     */
    private function sincronizarValorMultaSePossivel(array $guia): array
    {
        $idGuia = trim((string) ($guia['id'] ?? $guia['IdGuia'] ?? ''));
        $dataFato = trim((string) ($guia['data_fato'] ?? ''));
        $diasMulta = isset($guia['dias_multa']) && $guia['dias_multa'] !== null ? (int) $guia['dias_multa'] : 0;
        $valorAtual = $guia['valor_multa'] ?? null;
        $valorAusente = $valorAtual === null || $valorAtual === '';

        if ($idGuia === '' || $dataFato === '' || $diasMulta <= 0 || !$valorAusente) {
            return $guia;
        }

        $valorCalculado = $this->calcularValorPenaMulta($dataFato, $diasMulta);
        if ($valorCalculado === null) {
            return $guia;
        }

        $this->repository->update($idGuia, ['valor_multa' => $valorCalculado]);
        $guia['valor_multa'] = $valorCalculado;
        return $guia;
    }

    public function listGuias(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $rows[$i] = $this->sincronizarValorMultaSePossivel($row);
            $row = $rows[$i];
            $idGuia = (string) ($row['IdGuia'] ?? '');
            if ($idGuia !== '') {
                $this->subtarefasRepository->ensureOitoSubtarefas($idGuia);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByGuiaId($idGuia);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByGuiaId($idGuia);
                $alocadosRaw = $this->alocadosRepository->findByGuiaId($idGuia);
                $alocados = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $tipo = $this->normalizeTipoAlocado((string) ($a['tipo'] ?? ''));
                    $alocados[] = [
                        'alocado_id'    => $a['id'] ?? '',
                        'iniciais'      => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id'    => $a['usuario_id'] ?? '',
                        'tipo'          => $tipo,
                    ];
                }
                usort($alocados, static function (array $a, array $b): int {
                    static $order = ['rule' => 0, 'all' => 1, 'specific' => 2];
                    return ($order[$a['tipo']] ?? 9) <=> ($order[$b['tipo']] ?? 9);
                });
                $rows[$i]['Alocados'] = $alocados;
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
        $guia = $this->repository->findById($id);
        if ($guia === null) {
            return null;
        }

        return $this->sincronizarValorMultaSePossivel($guia);
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
        $this->idempotencyReplayed = false;
        if (isset(self::$unsafeConnections[$this->connection])) throw new RuntimeException('Conexão indisponível para cadastro ou edição de guia.');
        $numero = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if (!preg_match('/^(?:\d{20}|\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4})$/D', $numero)) {
            throw new InvalidArgumentException('Informe o número do processo no formato CNJ completo.');
        }
        CnjValidator::digits20OrThrow($numero);
        $regime = trim((string) ($payload['regime'] ?? ''));
        if ($regime !== '' && !in_array($regime, self::REGIMES_VALIDOS, true)) {
            throw new InvalidArgumentException('Regime inválido. Use: Aberto, Semiaberto ou Fechado.');
        }
        $parte = trim((string) ($payload['parte_id'] ?? $payload['reu_id'] ?? ''));
        $nome = trim((string) ($payload['denunciado'] ?? ''));
        $cpfInformado = trim((string) ($payload['cpf'] ?? ''));
        if ($parte === '' && $nome === '' && $cpfInformado === '') throw new InvalidArgumentException('Informe ou selecione a pessoa.');
        if ($parte === '' && $cpfInformado === '' && ($payload['confirmar_nova_pessoa'] ?? false) !== true) {
            throw new InvalidArgumentException('Selecione uma pessoa existente ou confirme o cadastro de uma nova pessoa.');
        }
        $key = strtolower(trim((string)($payload['idempotency_key'] ?? '')));
        $actor = trim((string)($payload['usuario_cadastro_id'] ?? ''));
        if ($key !== '' && ($actor === '' || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1)) throw new InvalidArgumentException('Chave de envio inválida.');
        $hash = $key !== '' ? GuiasIdempotencia::payloadHash($payload) : '';
        $cpf = preg_replace('/\D+/', '', (string) ($payload['cpf'] ?? '')) ?? '';
        if (trim((string) ($payload['cpf'] ?? '')) !== '' && !Security::validarCPF($cpf)) {
            throw new InvalidArgumentException('CPF inválido. Verifique os dígitos.');
        }
        return $this->comTravaCpf($cpf, fn(): string => $this->executarTransacaoCadastroEdicao(function () use ($payload, $key, $actor, $hash): string {
            if ($key === '') return $this->createTransacional($payload);
            $candidate = IdGenerator::generateUniquePrefixedId($this->connection, $this->repository->getTableName(), 'id', 'GUI', 30);
            $reservation = GuiasIdempotencia::reserve($this->connection, $actor, $key, $hash, $candidate);
            $this->idempotencyReplayed = $reservation['replayed'];
            if ($reservation['replayed']) return $reservation['id'];
            return $this->createTransacional($payload, $candidate);
        }, $key !== '', $cpf !== ''));
    }

    /** A trava coordena somente os escritores que usam este protocolo. */
    private function comTravaCpf(string $cpf, callable $operation): mixed
    {
        if ($cpf === '' || $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return $operation();
        if (isset(self::$unsafeConnections[$this->connection]) || $this->connection->inTransaction()) throw new RuntimeException('Conexão indisponível para cadastro de guia.');
        $database = $this->connection->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database === '') throw new RuntimeException('Banco de dados indisponível.');
        $name = 'rei250_' . substr(hash('sha256', $database . ':' . $cpf), 0, 52);
        try {
            $statement = $this->connection->prepare('SELECT GET_LOCK(?, 5)');
            $statement->execute([$name]);
            $acquired = $statement->fetchColumn();
        } catch (Throwable $e) {
            // O servidor pode ter adquirido a trava mesmo sem entregar a resposta.
            self::$unsafeConnections ??= new WeakMap();
            self::$unsafeConnections[$this->connection] = true;
            throw $e;
        }
        if ((int) $acquired !== 1) throw new RuntimeException('Não foi possível obter a trava de identificação.');
        $failure = null; $result = null;
        try { $result = $operation(); } catch (Throwable $e) { $failure = $e; }
        try {
            $statement = $this->connection->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$name]);
            if ((int) $statement->fetchColumn() !== 1) throw new RuntimeException('Liberação da trava não confirmada.');
        } catch (Throwable $e) {
            self::$unsafeConnections ??= new WeakMap();
            self::$unsafeConnections[$this->connection] = true;
            if ($failure === null) $failure = $e;
            else error_log('GuiasService: liberação da trava de identificação não confirmada.');
        }
        if ($failure !== null) throw $failure;
        return $result;
    }

    public function wasIdempotencyReplay(): bool { return $this->idempotencyReplayed; }

    /** A resposta de criação/replay não deve atualizar a guia após o commit. */
    public function findForCreateResponse(string $id): ?array { return $this->repository->findById($id); }

    private function createTransacional(array $payload, ?string $reservedId = null): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        $parteId = trim((string) ($payload['parte_id'] ?? $payload['reu_id'] ?? ''));
        $denunciado = trim((string) ($payload['denunciado'] ?? ''));
        if ($denunciado !== '') {
            $denunciado = TextFormatter::formatProperName($denunciado, 255);
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

        [$parteId, $denunciado] = $this->resolverParteCriacao($parteId, $denunciado, $payload);

        $processoId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '' && strlen($digits) === 20) {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
            $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, $dataFato, null);
        } elseif (strlen($digits) === 20) {
            $processoId = $this->processosRepository->ensureProcess($numeroFormatado, null, $dataFato, null);
        }

        if ($parteId !== '' && $processoId !== null) {
            $existente = $this->processosPartesRepository->findByProcessoAndParte($processoId, $parteId);
            if ($existente === null) {
                $this->processosPartesRepository->addParte(
                    $processoId,
                    $parteId,
                    ProcessosPartesRepository::TIPO_SENTENCIADO,
                    ProcessosPartesRepository::POLO_PASSIVO
                );
            }
        }

        if ($denunciado === '' && $parteId !== '') {
            $parte = $this->partesRepository->findById($parteId);
            $denunciado = $parte !== null ? trim((string) ($parte['nome_completo'] ?? '')) : '';
        }

        $idGuia = $reservedId ?? IdGenerator::generateUniquePrefixedId(
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
            'parte_id' => $parteId !== '' ? $parteId : null,
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
        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }

        if ($row['data_fato'] !== null && isset($row['dias_multa']) && (int) $row['dias_multa'] > 0) {
            $valorMulta = $this->calcularValorPenaMulta((string) $row['data_fato'], (int) $row['dias_multa']);
            if ($valorMulta !== null) {
                $row['valor_multa'] = $valorMulta;
            }
        }

        $this->repository->insert($row);

        $regraRow = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_GUIAS);
        $usuarioIdRegra = ($regraRow !== null) ? trim((string) ($regraRow['usuario_id'] ?? '')) : '';

        $chefiaCadastro = $usuarioCadastroId !== ''
            && AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioCadastroId);
        $chefiaRegra = $usuarioIdRegra !== ''
            && AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioIdRegra);

        // Cadastrante: normalmente autoalocado como `all`; se for o mesmo usuário da regra ALLOCATE-GUIAS, usar `rule`
        // (evita duplicata e exibe chip “regra” conforme o cartório).
        if ($usuarioCadastroId !== '' && !$chefiaCadastro) {
            $mesmoUsuarioDaRegra = $usuarioIdRegra !== '' && $usuarioIdRegra === $usuarioCadastroId;
            try {
                if ($mesmoUsuarioDaRegra) {
                    if (!$chefiaRegra) {
                        $this->alocadosRepository->addAlocado($idGuia, $usuarioCadastroId, GuiasAlocadosRepository::TIPO_RULE);
                    }
                } else {
                    $this->alocadosRepository->addAlocado($idGuia, $usuarioCadastroId, GuiasAlocadosRepository::TIPO_ALL);
                }
            } catch (LimiteAlocadosException $e) {
                // limite já atingido ou duplicata, ignorar
            }
        }

        // Servidor definido na regra (outra pessoa além do cadastrante já coberto acima)
        if ($usuarioIdRegra !== '' && !$chefiaRegra && $usuarioIdRegra !== $usuarioCadastroId) {
            try {
                $this->alocadosRepository->addAlocado($idGuia, $usuarioIdRegra, GuiasAlocadosRepository::TIPO_RULE);
            } catch (LimiteAlocadosException $e) {
                // limite ou duplicata
            }
        }

        AlocacaoTarefasPerfisHelper::registrarAlocacoesDirecionadasNoCadastro(
            $this->connection,
            $usuarioCadastroId,
            $payload,
            function (string $uid) use ($idGuia): void {
                try {
                    $this->alocadosRepository->addAlocado($idGuia, $uid, GuiasAlocadosRepository::TIPO_SPECIFIC);
                } catch (LimiteAlocadosException $e) {
                    // limite ou duplicata
                }
            }
        );

        $repo = $this->subtarefasRepository;
        $repo->create($idGuia, $repo::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_2, 2, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_3, 3, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_4, 4, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_5, 5, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_6, 6, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_7, 7, 'pendente', null);
        $repo->create($idGuia, $repo::TIPO_ORDEM_8, 8, 'pendente', null);

        $this->repository->update($idGuia, ['status' => 'pendente']);

        // Se o cadastro já veio com data_fato e dias_multa, concluir a subtarefa 1 e ativar a 2
        $this->sincronizarDetalhamentoAposUpdate($idGuia, $usuarioCadastroId);

        return $idGuia;
    }

    public function update(string $id, array $payload): bool
    {
        return $this->executarTransacaoCadastroEdicao(fn(): bool => $this->updateTransacional($id, $payload));
    }

    /** @return array{string, string} */
    private function resolverParteCriacao(string $parteId, string $nome, array $payload): array
    {
        $cpf = preg_replace('/\D+/', '', (string) ($payload['cpf'] ?? '')) ?? '';
        if ($cpf !== '') {
            $statement = $this->connection->prepare("SELECT id FROM partes WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? UNION SELECT parte_id FROM partes_documentos WHERE UPPER(tipo) = 'CPF' AND REPLACE(REPLACE(REPLACE(numero, '.', ''), '-', ''), ' ', '') = ?");
            $statement->execute([$cpf, $cpf]);
            $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
            if (count($ids) > 1) throw new InvalidArgumentException('CPF associado a mais de uma pessoa. Corrija o cadastro antes de continuar.');
            if ($ids !== []) {
                if ($parteId !== '' && $parteId !== (string) $ids[0]) throw new InvalidArgumentException('CPF não corresponde à pessoa selecionada.');
                $parteId = (string) $ids[0];
            } elseif ($parteId !== '') {
                throw new InvalidArgumentException('CPF não corresponde à pessoa selecionada.');
            }
        }
        if ($parteId !== '') {
            $parte = $this->partesRepository->findById($parteId);
            if ($parte === null) throw new InvalidArgumentException('Pessoa selecionada não encontrada.');
            return [$parteId, trim((string) $parte['nome_completo'])];
        }
        if ($nome === '') throw new InvalidArgumentException('Informe o nome da pessoa.');
        if ($cpf === '' && ($payload['confirmar_nova_pessoa'] ?? false) !== true) {
            throw new InvalidArgumentException('Selecione uma pessoa existente ou confirme o cadastro de uma nova pessoa.');
        }
        $parteId = $this->partesRepository->insert(['nome_completo' => $nome, 'cpf' => $cpf]);
        if ($cpf !== '') {
            $statement = $this->connection->prepare("SELECT COUNT(*) FROM partes_documentos WHERE parte_id = ? AND UPPER(tipo) = 'CPF' AND numero = ?");
            $statement->execute([$parteId, $cpf]);
            if ((int) $statement->fetchColumn() !== 1) throw new RuntimeException('Persistência do documento não confirmada.');
        }
        return [$parteId, $nome];
    }

    /** @template T @param callable():T $operation @return T */
    private function executarTransacaoCadastroEdicao(callable $operation, bool $idempotency = false, bool $documentos = false): mixed
    {
        if (isset(self::$unsafeConnections[$this->connection])) {
            throw new RuntimeException('Conexão indisponível para cadastro ou edição de guia.');
        }
        if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return $this->executarTransacaoCadastroEdicaoInterna($operation, null, $idempotency, $documentos);
        }
        $timeout = $this->connection->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        if (!is_numeric($timeout) || (int)$timeout < 1) throw new RuntimeException('Timeout de linha inválido.');
        $timeout = (int)$timeout;
        $failure = null;
        $result = null;
        $restored = false;
        $restore = function () use ($timeout, &$restored): void {
            try {
                if ($this->connection->exec('SET SESSION innodb_lock_wait_timeout = ' . $timeout) === false) throw new RuntimeException('Não foi possível restaurar timeout de linha.');
                $restored = true;
            } catch (Throwable $e) {
                self::$unsafeConnections ??= new WeakMap();
                self::$unsafeConnections[$this->connection] = true;
                throw $e;
            }
        };
        try {
            if ($this->connection->exec('SET SESSION innodb_lock_wait_timeout = ' . min(5, $timeout)) === false) {
                throw new RuntimeException('Não foi possível limitar espera de linha.');
            }
            $result = $this->executarTransacaoCadastroEdicaoInterna($operation, $restore, $idempotency, $documentos);
        } catch (Throwable $e) {
            $failure = $e;
        } finally {
            try {
                if (!$restored) $restore();
            } catch (Throwable $restoreFailure) {
                self::$unsafeConnections ??= new WeakMap();
                self::$unsafeConnections[$this->connection] = true;
                if ($failure === null) $failure = $restoreFailure;
                else error_log('GuiasService: restauração de timeout de linha não confirmada.');
            }
        }
        if ($failure !== null) throw $failure;
        return $result;
    }

    /** @template T @param callable():T $operation @return T */
    private function executarTransacaoCadastroEdicaoInterna(callable $operation, ?callable $beforeCommit = null, bool $idempotency = false, bool $documentos = false): mixed
    {
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('Cadastro e edição de guias exigem transação própria.');
        }
        if (!$this->connection->beginTransaction()) {
            throw new RuntimeException('Não foi possível iniciar a transação de guia.');
        }
        try {
            GuiasCadastroTransactionalGuard::requireTransactionalTables($this->connection, $idempotency, $documentos);
            $result = $operation();
            if ($result === false) {
                try {
                    if (!$this->connection->rollBack()) throw new RuntimeException('Não foi possível reverter a transação de guia.');
                } catch (Throwable $rollbackFailure) {
                    self::$unsafeConnections ??= new WeakMap();
                    self::$unsafeConnections[$this->connection] = true;
                    throw $rollbackFailure;
                }
                return false;
            }
            try {
                if ($beforeCommit !== null) $beforeCommit();
                if (!$this->connection->commit()) throw new RuntimeException('Não foi possível confirmar a transação de guia.');
            } catch (Throwable $commitFailure) {
                self::$unsafeConnections ??= new WeakMap();
                self::$unsafeConnections[$this->connection] = true;
                throw $commitFailure;
            }
            return $result;
        } catch (Throwable $e) {
            try {
                if ($this->connection->inTransaction() && !$this->connection->rollBack()) throw new RuntimeException('Rollback não confirmado.');
            } catch (Throwable $rollbackFailure) {
                self::$unsafeConnections ??= new WeakMap();
                self::$unsafeConnections[$this->connection] = true;
                error_log('GuiasService: rollback não confirmado.');
            }
            throw $e;
        }
    }

    private function updateTransacional(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $usuarioId = trim((string) ($payload['usuario_id'] ?? ''));
        unset($payload['usuario_id']);

        $allowed = [
            'numero_processo', 'processo_id', 'denunciado', 'parte_id', 'tipo', 'etapa', 'status',
            'regime', 'data_fato', 'dias_multa', 'observacoes', 'user_cadastro',
        ];
        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $filtered[$key] = $payload[$key];
            }
        }
        if (array_key_exists('reu_id', $payload) && !array_key_exists('parte_id', $filtered)) {
            $filtered['parte_id'] = $payload['reu_id'];
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
        if (array_key_exists('dias_multa', $filtered)) {
            $dm = $filtered['dias_multa'];
            if ($dm === null || $dm === '') {
                $filtered['dias_multa'] = null;
            } else {
                $filtered['dias_multa'] = (int) $dm;
            }
        }
        if ($filtered === []) return false;

        // Lock the parent first: false from UPDATE can also mean no column
        // changed, so only absence of this row means the guide is missing.
        if ($this->repository->lockForUpdate($id) === null) return false;
        if ($usuarioId !== '') {
            try {
                $this->alocadosRepository->addAlocado($id, $usuarioId);
            } catch (LimiteAlocadosException $e) {
                // O limite de alocação é uma condição de negócio tolerada.
            }
        }
        $this->repository->update($id, $filtered);
        if (array_key_exists('data_fato', $filtered) || array_key_exists('dias_multa', $filtered)) {
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
        return true;
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

    public function delete(string $id): array
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $subtarefas = $this->subtarefasRepository->findByGuiaId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByGuiaId($id);
        $this->alocadosRepository->deleteByGuiaId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }

    /**
     * Conclui a subtarefa. Retorna array com chave valor_multa_calculado (valor formatado em R$) quando
     * o sistema calcular automaticamente a pena de multa (ordem 1 concluída com data_fato e dias_multa).
     *
     * @return array{valor_multa_calculado?: string}
     */
    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null, ?string $usuarioIdDashboard = null): array
    {
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('A conclusão da guia deve controlar sua própria transação.');
        }

        require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);
        $inicial = $this->subtarefasRepository->findById($subtarefaId);
        $guiaId = trim((string) ($inicial['guia_id'] ?? ''));
        if ($inicial === null || $guiaId === '') {
            throw new InvalidArgumentException('Subtarefa ou guia não encontrada.');
        }

        $this->connection->beginTransaction();
        try {
            $guia = $this->repository->lockForUpdate($guiaId);
            $atual = $this->subtarefasRepository->findById($subtarefaId);
            if ($guia === null || $atual === null || trim((string) ($atual['guia_id'] ?? '')) !== $guiaId) {
                throw new InvalidArgumentException('Subtarefa ou guia não encontrada.');
            }
            $guiaStatus = trim((string) ($guia['status'] ?? ''));
            $ordem = (int) ($atual['ordem'] ?? 0);
            $status = trim((string) ($atual['status'] ?? ''));
            if ($ordem < 1 || $ordem > 8) {
                throw new InvalidArgumentException('Ordem da subtarefa inválida.');
            }
            if ($status === 'concluida') {
                if ($ordem === 8 && $guiaStatus !== 'concluída') {
                    throw new RuntimeException('Estado inconsistente da conclusão da guia.');
                }
                $this->connection->commit();
                return $ordem === 8
                    ? ['tarefa_principal_concluida' => true, 'tarefa_principal_mensagem' => 'A guia já foi concluída.']
                    : ['tarefa_principal_concluida' => false];
            }
            if (in_array($guiaStatus, ['cancelada', 'concluída'], true)) {
                throw new InvalidArgumentException('Não é possível alterar subtarefas desta guia.');
            }
            if (!in_array($status, ['pendente', 'em_andamento'], true)) {
                throw new InvalidArgumentException('Esta subtarefa não pode ser concluída.');
            }

            $etapas = $this->subtarefasRepository->findByGuiaId($guiaId);
            for ($anterior = 1; $anterior < $ordem; $anterior++) {
                $encontrada = false;
                foreach ($etapas as $etapa) {
                    if ((int) ($etapa['ordem'] ?? 0) !== $anterior) {
                        continue;
                    }
                    $estado = trim((string) ($etapa['status'] ?? ''));
                    $permitida = $estado === 'concluida'
                        || ($anterior === 1 && $estado === GuiasSubtarefasRepository::STATUS_CANCELADA)
                        || ($anterior === 2 && $estado === GuiasSubtarefasRepository::STATUS_PULADA);
                    if (!$permitida) {
                        throw new InvalidArgumentException('Conclua as etapas anteriores da guia primeiro.');
                    }
                    $encontrada = true;
                }
                if (!$encontrada) {
                    throw new InvalidArgumentException('Etapa anterior da guia não encontrada.');
                }
            }

            $dados = $dadosJson === null || $dadosJson === '' ? [] : json_decode($dadosJson, true);
            if (!is_array($dados)) {
                throw new InvalidArgumentException('Dados da subtarefa inválidos.');
            }
            if ($ordem === 1 && trim((string) ($dados['data_fato'] ?? '')) !== '') {
                $data = trim((string) $dados['data_fato']);
                $dt = DateTime::createFromFormat('!Y-m-d', $data);
                if ($dt === false || $dt->format('Y-m-d') !== $data) {
                    throw new InvalidArgumentException('A data do fato informada é inválida.');
                }
                if ($dt >= (new DateTime('today'))->modify('-10 days')) {
                    throw new InvalidArgumentException('A data do fato deve ser anterior aos últimos 10 dias (não pode ser de hoje, futura ou dentro desse período).');
                }
            }
            if ($ordem === 3) {
                $tipoGuia = trim((string) ($dados['tipo_guia'] ?? ''));
                if (!in_array($tipoGuia, ['Guia de Execução Definitiva', 'Guia de Recolhimento Provisória', 'Guia de Recolhimento Definitiva'], true)) {
                    throw new InvalidArgumentException($tipoGuia === '' ? 'Selecione o tipo da guia expedida.' : 'Tipo de guia inválido. Selecione uma opção permitida.');
                }
            }
            if ($ordem === 5) {
                $juntada = mb_strtolower(trim((string) ($dados['tipo_juntada'] ?? '')), 'UTF-8');
                $juntada = str_replace(['á', 'à', 'â', 'ã', 'ä'], 'a', $juntada);
                if (!in_array($juntada, ['automatica', 'manual'], true)) {
                    throw new InvalidArgumentException($juntada === '' ? 'Selecione o tipo de juntada (automática ou manual).' : 'Tipo de juntada inválido. Selecione automática ou manual.');
                }
            }
            if ($ordem === 6) {
                $quantidade = filter_var($dados['qtd_docs'] ?? null, FILTER_VALIDATE_INT);
                if ($quantidade === false || $quantidade < 4) {
                    throw new InvalidArgumentException('Informe no mínimo 4 documentos.');
                }
            }
            if ($ordem === 7) {
                $tipoEnvio = trim((string) ($dados['tipo_envio'] ?? ''));
                $destinatario = trim((string) ($dados['destinatario'] ?? ''));
                if (!in_array($tipoEnvio, ['E-mail', 'Malote Digital', 'PJe', 'eSAJ', 'ePROC'], true)
                    || !in_array($destinatario, ['VEP', 'SEEU'], true)) {
                    throw new InvalidArgumentException('Selecione um tipo de envio e destinatário válidos.');
                }
            }

            $resultado = $this->concluirSubtarefaInterna($subtarefaId, $dadosJson);
            $usuarioId = trim((string) ($usuarioIdDashboard ?? $_SESSION['id_usuario'] ?? ''));
            if ($usuarioId !== '') {
                require_once __DIR__ . '/DashboardResumoService.php';
                DashboardResumoService::registrarConclusao($this->connection, ['id' => $subtarefaId, 'status' => $status], 'guias', 'CARTORIO', $usuarioId);
            }
            $this->connection->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e;
        }
    }

    private function concluirSubtarefaInterna(string $subtarefaId, ?string $dadosJson = null): array
    {
        require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);

        $tiposGuiaPermitidos = [
            'Guia de Execução Definitiva',
            'Guia de Recolhimento Provisória',
            'Guia de Recolhimento Definitiva',
        ];

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
            } catch (LimiteAlocadosException $e) {
                // Limite atingido; erros de persistência precisam reverter a transação.
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
            if ($dataFato !== '') {
                $dtFato = \DateTime::createFromFormat('Y-m-d', $dataFato);
                if ($dtFato === false) {
                    throw new InvalidArgumentException('A data do fato informada é inválida.');
                }
                $hoje = new \DateTime('today');
                $limiteInferior = (clone $hoje)->modify('-10 days');
                if ($dtFato >= $limiteInferior) {
                    throw new InvalidArgumentException('A data do fato deve ser anterior aos últimos 10 dias (não pode ser de hoje, futura ou dentro desse período).');
                }
            }
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
            $resultado['tarefa_principal_concluida'] = false;
            return $resultado;
        }

        if ($ordem < 8) {
            if ($ordem === 3) {
                $tipoGuiaRaw = trim((string) ($dadosArray['tipo_guia'] ?? ''));
                if ($tipoGuiaRaw === '') {
                    throw new InvalidArgumentException('Selecione o tipo da guia expedida.');
                }
                if (!in_array($tipoGuiaRaw, $tiposGuiaPermitidos, true)) {
                    throw new InvalidArgumentException('Tipo de guia inválido. Selecione uma opção permitida.');
                }
                $this->repository->update($guiaId, ['tipo' => $tipoGuiaRaw]);
            }
            if ($ordem === 5) {
                $tipoJuntadaRaw = trim((string) ($dadosArray['tipo_juntada'] ?? ''));
                if ($tipoJuntadaRaw === '') {
                    throw new InvalidArgumentException('Selecione o tipo de juntada (automática ou manual).');
                }
                $tipoJuntadaNormalizado = str_replace(['á', 'à', 'â', 'ã', 'ä'], 'a', mb_strtolower($tipoJuntadaRaw, 'UTF-8'));
                if (!in_array($tipoJuntadaNormalizado, ['automatica', 'manual'], true)) {
                    throw new InvalidArgumentException('Tipo de juntada inválido. Selecione automática ou manual.');
                }
            }
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
            $resultado['tarefa_principal_concluida'] = false;
        } else {
            $this->repository->update($guiaId, ['status' => 'concluída']);
            $guia = $this->repository->findById($guiaId);
            $processo = trim((string) ($guia['numero_processo'] ?? ''));
            $tipo = trim((string) ($guia['tipo'] ?? ''));
            $denunciado = trim((string) ($guia['denunciado'] ?? ''));
            $resultado['tarefa_principal_concluida'] = true;
            $resultado['tarefa_principal_mensagem'] =
                'Tarefa principal concluída automaticamente: guia do processo '
                . ($processo !== '' ? $processo : 'não informado')
                . ' (' . ($tipo !== '' ? $tipo : 'tipo não informado')
                . ' - ' . ($denunciado !== '' ? $denunciado : 'denunciado não informado') . ').';
        }

        return $resultado;
    }

    public function cancelarSubtarefa(string $subtarefaId): void
    {
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('O cancelamento da guia deve controlar sua própria transação.');
        }
        $inicial = $this->subtarefasRepository->findById($subtarefaId);
        if ($inicial === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }
        $guiaId = trim((string) ($inicial['guia_id'] ?? ''));
        if ($guiaId === '') {
            throw new InvalidArgumentException('Guia da subtarefa não identificada.');
        }

        $this->connection->beginTransaction();
        try {
            $guia = $this->repository->lockForUpdate($guiaId);
            $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
            if ($guia === null || $subtarefa === null || trim((string) ($subtarefa['guia_id'] ?? '')) !== $guiaId) {
                throw new InvalidArgumentException('Subtarefa ou guia não encontrada.');
            }
            if ((int) ($subtarefa['ordem'] ?? 0) !== 1) {
                throw new InvalidArgumentException('Apenas a subtarefa "Detalhamento de informações" pode ser cancelada.');
            }
            if (in_array(trim((string) ($guia['status'] ?? '')), ['cancelada', 'concluída'], true)) {
                throw new InvalidArgumentException('Não é possível alterar subtarefas desta guia.');
            }
            $status = trim((string) ($subtarefa['status'] ?? ''));
            if ($status === GuiasSubtarefasRepository::STATUS_CANCELADA) {
                $this->connection->commit();
                return;
            }
            if (!in_array($status, ['pendente', 'em_andamento'], true)) {
                throw new InvalidArgumentException('Esta subtarefa não pode ser cancelada.');
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
            $this->connection->commit();
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e;
        }
    }

    public function criarPrimeiraSubtarefa(string $guiaId, ?string $usuarioId = null): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            throw new InvalidArgumentException('Identificador da guia é obrigatório.');
        }
        if ($this->connection->inTransaction()) {
            throw new RuntimeException('A inicialização das subtarefas deve controlar sua própria transação.');
        }

        $this->connection->beginTransaction();
        try {
            // Lock before reading subtasks: completion/cancellation use this same
            // parent row to serialize every state transition for the guide.
            $guia = $this->repository->lockForUpdate($guiaId);
            if ($guia === null) {
                throw new InvalidArgumentException('Guia não encontrada.');
            }
            $statusGuia = trim((string) ($guia['status'] ?? ''));
            if (in_array($statusGuia, ['cancelada', 'concluída'], true)) {
                throw new InvalidArgumentException('Não é possível criar ou ativar subtarefas de uma guia encerrada.');
            }

            $existentes = $this->subtarefasRepository->findByGuiaId($guiaId);
            if (count($existentes) === 0) {
                $this->subtarefasRepository->ensureOitoSubtarefas($guiaId);
                $todas = $this->subtarefasRepository->findByGuiaId($guiaId);
                $status = CartorioTarefaStatusPrincipalHelper::principalStatusPadrao(
                    $todas,
                    ['concluida', GuiasSubtarefasRepository::STATUS_PULADA],
                    'concluída'
                );
                $this->repository->update($guiaId, ['status' => $status]);
            } else {
                $ordem1 = $this->subtarefasRepository->findByGuiaIdEOrdem($guiaId, 1);
                if ($ordem1 !== null && trim((string) ($ordem1['status'] ?? '')) !== 'concluida' && trim((string) ($ordem1['status'] ?? '')) !== GuiasSubtarefasRepository::STATUS_CANCELADA) {
                    $this->subtarefasRepository->atualizarStatus((string) $ordem1['id'], 'em_andamento');
                }
                $todas = $this->subtarefasRepository->findByGuiaId($guiaId);
                $status = CartorioTarefaStatusPrincipalHelper::principalStatusPadrao(
                    $todas,
                    ['concluida', GuiasSubtarefasRepository::STATUS_PULADA],
                    'concluída'
                );
                $this->repository->update($guiaId, ['status' => $status]);
            }

            if ($usuarioId !== null && trim($usuarioId) !== '') {
                try {
                    $this->alocadosRepository->addAlocado($guiaId, trim($usuarioId));
                } catch (LimiteAlocadosException $e) {
                    // Atingir o limite é permitido; persistência inválida deve reverter.
                }
            }
            $this->connection->commit();
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e;
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

    public function removeAlocado(string $alocadoId, string $tipo, string $requestUserId): bool
    {
        $alocadoId     = trim($alocadoId);
        $requestUserId = trim($requestUserId);
        if ($alocadoId === '' || $requestUserId === '') {
            return false;
        }
        return $this->alocadosRepository->deleteById($alocadoId);
    }

    public function alocarUsuarioDirecionado(string $guiaId, string $targetUsuarioId, string $actorId): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            throw new InvalidArgumentException('Identificador da guia inválido.');
        }
        $this->executarTransacaoCadastroEdicao(function () use ($guiaId, $targetUsuarioId, $actorId): bool {
            $row = $this->repository->lockForUpdate($guiaId);
            if ($row === null) throw new InvalidArgumentException('Guia não encontrada.');
            $st = trim((string) ($row['status'] ?? ''));
            if (in_array($st, ['cancelada', 'concluída'], true)) {
                throw new InvalidArgumentException('Não é possível alterar alocações neste status.');
            }
            AlocacaoTarefasPerfisHelper::registrarAlocacaoDirecionadaNaEdicao(
                $this->connection,
                $actorId,
                $targetUsuarioId,
                function (string $uid) use ($guiaId): void {
                    $this->alocadosRepository->addAlocado($guiaId, $uid, GuiasAlocadosRepository::TIPO_SPECIFIC);
                },
                true
            );
            return true;
        });
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
