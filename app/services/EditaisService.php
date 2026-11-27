<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/EditaisRepository.php';
require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/EditaisAlocadosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/FeriadosRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/CartorioTarefaStatusPrincipalHelper.php';

final class EditaisService
{
    private PDO $connection;
    private EditaisRepository $repository;
    private EditaisSubtarefasRepository $subtarefasRepository;
    private EditaisAlocadosRepository $alocadosRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private FeriadosRepository $feriadosRepository;


    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new EditaisRepository($connection);
        $this->subtarefasRepository = new EditaisSubtarefasRepository($connection);
        $this->alocadosRepository = new EditaisAlocadosRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->feriadosRepository = new FeriadosRepository($connection);
    }

    public function listEditais(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idEdital = (string) ($row['IdEdital'] ?? '');
            if ($idEdital !== '') {
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByEditalId($idEdital);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByEditalId($idEdital);
                $rows[$i]['DecursoInfo'] = $this->getDecursoInfo($idEdital);
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['DecursoInfo'] = [
                    'data_publicacao' => '',
                    'data_inicio_contagem' => '',
                    'data_decurso_prazo' => '',
                    'data_limite_formatada' => '',
                    'prazo_cumprido' => false,
                    'libera_conclusao' => false,
                    'dias_restantes' => 0,
                ];
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

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['processo'] ?? $payload['numero_processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $tipoEdital = trim((string) ($payload['tipo_edital'] ?? ''));
        if ($tipoEdital === '') {
            throw new InvalidArgumentException('O tipo de edital é obrigatório.');
        }

        $prazoDias = isset($payload['prazo_dias']) ? (int) $payload['prazo_dias'] : 0;
        if ($prazoDias < 1) {
            throw new InvalidArgumentException('O prazo do edital é obrigatório e deve ser de pelo menos 1 dia.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '' && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $idempotencyKey)) {
            throw new InvalidArgumentException('Chave de criação inválida. Recarregue a página e tente novamente.');
        }

        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = $ownsTransaction ? null : 'edital_create_' . bin2hex(random_bytes(8));
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }

        $attemptedNewEditalInsert = false;
        try {
            $idEdital = $idempotencyKey !== ''
                ? 'EDI-' . strtoupper(substr(hash('sha256', strtolower($idempotencyKey)), 0, 26))
                : IdGenerator::generateUniquePrefixedId(
                    $this->connection,
                    $this->repository->getTableName(),
                    'id',
                    'EDI',
                    30
                );

            $rowExistente = $idempotencyKey !== '' ? $this->repository->findById($idEdital) : null;
            if ($rowExistente !== null) {
                // Resolve only existing state before deciding whether this is a
                // replay. In particular, do not let ensureProcess change the
                // process class for a key that was submitted with other data.
                $processoExistente = $this->processosRepository->findByNumero($numeroFormatado);
                $processoId = $processoExistente !== null ? (string) $processoExistente['id'] : null;
                $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
                $classeExistente = $classeCodigo !== '' ? $this->classesRepository->findByCodigo($classeCodigo) : null;
                $sameRequest = (string) ($rowExistente['processo_id'] ?? '') === (string) ($processoId ?? '')
                    && (string) ($rowExistente['tipo_edital'] ?? '') === $tipoEdital
                    && (int) ($rowExistente['prazo_dias'] ?? 0) === $prazoDias
                    && trim((string) ($rowExistente['observacoes'] ?? '')) === trim((string) ($payload['observacoes'] ?? ''));
                if ($sameRequest && $classeCodigo !== '') {
                    $sameRequest = $classeExistente !== null
                        && (string) ($processoExistente['classe_id'] ?? '') === (string) ($classeExistente['id'] ?? '');
                }
                if (!$sameRequest) {
                    throw new InvalidArgumentException('Esta tentativa de criação já foi usada com outros dados. Recarregue a página para iniciar um novo cadastro.');
                }
                // A repetição pode chegar depois de uma confirmação de commit cuja
                // resposta HTTP se perdeu. Reconciliar as etapas sob o mesmo ID.
                $this->criarPrimeiraSubtarefa($idEdital);
            } else {
                if (strlen($digits) === 20) {
                    $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
                    $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
                    $classeId = null;
                    if ($classeCodigo !== '') {
                        $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
                        $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
                    }
                    $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, null);
                } else {
                    $processoId = null;
                    $existingProcess = $this->processosRepository->findByNumero($numeroFormatado);
                    if ($existingProcess !== null) {
                        $processoId = (string) $existingProcess['id'];
                    }
                }

                $row = [
                    'id' => $idEdital,
                    'processo_id' => $processoId,
                    'status' => 'pendente',
                    'tipo_edital' => $tipoEdital,
                    'prazo_dias' => $prazoDias,
                    'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
                ];

                $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
                if ($usuarioCadastroId !== '') {
                    $row['criado_por'] = $usuarioCadastroId;
                }

                $attemptedNewEditalInsert = true;
                $this->repository->insert($row);
                // The main record, both workflow stages and the derived status are
                // committed together. The endpoint's later retry remains harmless.
                $this->criarPrimeiraSubtarefa($idEdital);
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $idEdital;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            } elseif (!$ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            if ($ownsTransaction && $attemptedNewEditalInsert && $idempotencyKey !== '' && $this->isUniqueConstraintViolation($exception)) {
                // A concurrent request with the same key may have committed after
                // our initial lookup and before this INSERT. Recover only when the
                // deterministic ID now identifies the exact same request; otherwise
                // preserve the original constraint error instead of retrying blindly.
                $committed = $this->repository->findById($idEdital);
                $committedProcess = $this->processosRepository->findByNumero($numeroFormatado);
                $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
                $committedClass = $classeCodigo !== '' ? $this->classesRepository->findByCodigo($classeCodigo) : null;
                $sameRequest = $committed !== null
                    && (string) ($committed['processo_id'] ?? '') === (string) ($processoId ?? '')
                    && (string) ($committed['tipo_edital'] ?? '') === $tipoEdital
                    && (int) ($committed['prazo_dias'] ?? 0) === $prazoDias
                    && trim((string) ($committed['observacoes'] ?? '')) === trim((string) ($payload['observacoes'] ?? ''));
                if ($sameRequest && $classeCodigo !== '') {
                    $sameRequest = $committedProcess !== null
                        && $committedClass !== null
                        && (string) ($committedProcess['classe_id'] ?? '') === (string) ($committedClass['id'] ?? '');
                }
                if ($sameRequest) {
                    $this->criarPrimeiraSubtarefa($idEdital);
                    return $idEdital;
                }
            }
            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }
        $errorInfo = $exception->errorInfo ?? [];
        return ($errorInfo[0] ?? '') === '23000'
            && (int) ($errorInfo[1] ?? 0) === 1062;
    }

    public function criarPrimeiraSubtarefa(string $editalId): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            throw new InvalidArgumentException('Identificador do edital é obrigatório.');
        }
        $this->reconciliarComEditalBloqueado($editalId, function () use ($editalId): void {
            if ($this->repository->findById($editalId) === null) {
                throw new InvalidArgumentException('Edital não encontrado.');
            }

            $repo = $this->subtarefasRepository;
            $existentes = $repo->findByEditalId($editalId);
            $ordensExistentes = [];
            foreach ($existentes as $subtarefa) {
                $tipo = trim((string) ($subtarefa['tipo'] ?? ''));
                $ordem = (int) ($subtarefa['ordem'] ?? 0);
                if ($tipo === $repo::TIPO_PUBLICACAO || $ordem === $repo::ORDEM_PUBLICACAO) {
                    $ordensExistentes[$repo::ORDEM_PUBLICACAO] = true;
                } elseif ($tipo === $repo::TIPO_DECURSO || $ordem === $repo::ORDEM_DECURSO) {
                    $ordensExistentes[$repo::ORDEM_DECURSO] = true;
                }
            }

            if (!isset($ordensExistentes[$repo::ORDEM_PUBLICACAO])) {
                $repo->create($editalId, $repo::TIPO_PUBLICACAO, $repo::ORDEM_PUBLICACAO, 'em_andamento', null);
            }
            if (!isset($ordensExistentes[$repo::ORDEM_DECURSO])) {
                $repo->create($editalId, $repo::TIPO_DECURSO, $repo::ORDEM_DECURSO, 'pendente', null);
            }

            $todas = $repo->findByEditalId($editalId);
            require_once __DIR__ . '/../helpers/EditaisUiCopy.php';
            $status = EditaisUiCopy::statusPrincipalFromSubtarefas($todas);
            if ($status === 'concluída' || $status === 'concluida') {
                $status = 'concluída';
            }
            $this->repository->update($editalId, ['status' => $status]);
            $this->tentarFinalizacaoAutomaticaPorDecurso($editalId);
        });
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);

        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $editalId = trim((string) ($subtarefa['edital_id'] ?? ''));
        if ($editalId === '') throw new InvalidArgumentException('Edital da subtarefa não identificado.');
        $result = $this->reconciliarComEditalBloqueado($editalId, function () use ($subtarefaId, $dadosJson, $editalId): array {
            $current = $this->subtarefasRepository->findById($subtarefaId, true);
            if ($current === null || (string) $current['edital_id'] !== $editalId) {
                throw new InvalidArgumentException('Subtarefa não encontrada no edital.');
            }
            return $this->concluirSubtarefaSobLock($current, $subtarefaId, $dadosJson, $editalId);
        });
        if (!is_array($result)) throw new InvalidArgumentException('Edital da subtarefa não encontrado.');
        return $result;
    }

    private function concluirSubtarefaSobLock(array $subtarefa, string $subtarefaId, ?string $dadosJson, string $editalId): array
    {
        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $tipoSub = trim((string) ($subtarefa['tipo'] ?? ''));
        if ($tipoSub === EditaisSubtarefasRepository::TIPO_PUBLICACAO) {
            $ordem = EditaisSubtarefasRepository::ORDEM_PUBLICACAO;
        } elseif ($tipoSub === EditaisSubtarefasRepository::TIPO_DECURSO) {
            $ordem = EditaisSubtarefasRepository::ORDEM_DECURSO;
        } elseif ($ordem === 3) {
            $ordem = EditaisSubtarefasRepository::ORDEM_PUBLICACAO;
        } elseif ($ordem === 4) {
            $ordem = EditaisSubtarefasRepository::ORDEM_DECURSO;
        }
        if ($ordem === EditaisSubtarefasRepository::ORDEM_PUBLICACAO) {
            $dadosArray = [];
            if ($dadosJson !== null && $dadosJson !== '') {
                $decoded = json_decode($dadosJson, true);
                if (is_array($decoded)) {
                    $dadosArray = $decoded;
                }
            }
            $dataPublicacao = trim((string) ($dadosArray['data_publicacao'] ?? ''));
            if ($dataPublicacao === '') {
                throw new InvalidArgumentException('Informe a data da publicação para concluir a certificação da publicação.');
            }
            $dtPublicacao = \DateTime::createFromFormat('Y-m-d', $dataPublicacao);
            if ($dtPublicacao === false || $dtPublicacao->format('Y-m-d') !== $dataPublicacao) {
                throw new InvalidArgumentException('Data da publicação inválida.');
            }
            $edital = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? $this->repository->findByIdForUpdate($editalId) : $this->repository->findById($editalId);
            if ($edital === null) {
                throw new InvalidArgumentException('Edital não encontrado para calcular o decurso.');
            }
            $situacao = strtolower(trim((string) ($edital['status'] ?? '')));
            $situacaoEtapa = strtolower(trim((string) ($subtarefa['status'] ?? '')));
            if ($situacao === 'cancelada') {
                throw new InvalidArgumentException('Não é possível certificar publicação de edital cancelado.');
            }
            if (in_array($situacaoEtapa, ['concluida', 'concluída'], true)) {
                if ((string) ($edital['data_publicacao'] ?? '') === $dataPublicacao) {
                    return [
                        'tarefa_principal_concluida' => in_array($situacao, ['concluida', 'concluída'], true),
                        'replayed' => true,
                    ];
                }
                throw new InvalidArgumentException('Publicação já certificada com outra data. Nenhum dado foi alterado.');
            }
            if (in_array($situacao, ['concluida', 'concluída'], true) || $situacaoEtapa !== 'em_andamento') {
                throw new InvalidArgumentException('A etapa de publicação não está disponível para conclusão.');
            }
            $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
            if ($prazoDias < 1) {
                throw new InvalidArgumentException('Prazo do edital inválido para calcular o decurso.');
            }
            [$inicioContagem, $dataDecurso] = $this->calcularInicioEDataDecurso($dataPublicacao, $prazoDias);
            $this->repository->update($editalId, [
                'data_publicacao' => $dataPublicacao,
                'data_inicio_contagem' => $inicioContagem,
                'data_decurso_prazo' => $dataDecurso,
            ]);
        }

        if ($ordem === EditaisSubtarefasRepository::ORDEM_DECURSO) {
            $this->validarDecursoParaCertificacao($editalId);
        }

        // Persist submitted metadata only after all business validation has passed.
        // The API wraps this transition and dashboard registration in one transaction.
        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, 'concluida');

        require_once __DIR__ . '/../helpers/EditaisUiCopy.php';
        $todas = $this->subtarefasRepository->findByEditalId($editalId, true);
        $proximaEncontrada = null;
        if ($ordem === EditaisSubtarefasRepository::ORDEM_PUBLICACAO) {
            foreach ($todas as $st) {
                if ((int) ($st['ordem'] ?? 0) === EditaisSubtarefasRepository::ORDEM_DECURSO
                    || (string) ($st['tipo'] ?? '') === EditaisSubtarefasRepository::TIPO_DECURSO) {
                    $proximaEncontrada = $st;
                    break;
                }
            }
        }

        if ($proximaEncontrada !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proximaEncontrada['id'], 'em_andamento');
            $this->repository->update($editalId, ['status' => EditaisUiCopy::STATUS_AGUARDANDO_DECURSO]);
            $this->tentarFinalizacaoAutomaticaPorDecurso($editalId, $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');

            return ['tarefa_principal_concluida' => false];
        }

        $this->repository->update($editalId, ['status' => 'concluída']);
        $edital = $this->repository->findById($editalId);
        $processo = trim((string) ($edital['numero_processo'] ?? ''));
        $tipoEdital = trim((string) ($edital['tipo_edital'] ?? ''));
        $prazoDias = isset($edital['prazo_dias']) ? (int) $edital['prazo_dias'] : 0;
        $msg = 'Tarefa principal concluída automaticamente: edital do processo '
            . ($processo !== '' ? $processo : 'não informado')
            . ' (' . ($tipoEdital !== '' ? $tipoEdital : 'tipo não informado')
            . ', prazo de ' . ($prazoDias > 0 ? $prazoDias : 0) . ' dias).';

        return [
            'tarefa_principal_concluida' => true,
            'tarefa_principal_mensagem' => $msg,
        ];
    }

    /**
     * Conclui automaticamente decurso e tarefa principal quando o prazo já encerrou (dia após data de decurso).
     */
    public function tentarFinalizacaoAutomaticaPorDecurso(string $editalId, bool $usarLeituraAtual = false): bool
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return false;
        }

        $edital = $usarLeituraAtual
            ? $this->repository->findByIdForUpdate($editalId)
            : $this->repository->findById($editalId);
        if ($edital === null) {
            return false;
        }

        $status = strtolower(trim((string) ($edital['status'] ?? '')));
        if ($status === 'concluída') {
            $status = 'concluida';
        }
        if (in_array($status, ['concluida', 'cancelada'], true)) {
            return false;
        }

        $decurso = $this->getDecursoInfo($editalId, true, $usarLeituraAtual);
        if (empty($decurso['libera_conclusao'])) {
            return false;
        }

        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId, $usarLeituraAtual);
        $subPublicacao = null;
        $subDecurso = null;
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordemSt = (int) ($st['ordem'] ?? 0);
            if ($tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO || $ordemSt === EditaisSubtarefasRepository::ORDEM_PUBLICACAO || $ordemSt === 3) {
                $subPublicacao = $st;
            }
            if ($tipo === EditaisSubtarefasRepository::TIPO_DECURSO || $ordemSt === EditaisSubtarefasRepository::ORDEM_DECURSO || $ordemSt === 4) {
                $subDecurso = $st;
            }
        }

        if ($subPublicacao === null) {
            return false;
        }

        $statusPub = strtolower(trim((string) ($subPublicacao['status'] ?? '')));
        if ($statusPub === 'concluída') {
            $statusPub = 'concluida';
        }
        if ($statusPub !== 'concluida') {
            return false;
        }

        if ($subDecurso !== null) {
            $statusDec = strtolower(trim((string) ($subDecurso['status'] ?? '')));
            if ($statusDec === 'concluída') {
                $statusDec = 'concluida';
            }
            if ($statusDec !== 'concluida') {
                $this->subtarefasRepository->atualizarStatus((string) $subDecurso['id'], 'concluida');
            }
        }

        $this->repository->update($editalId, ['status' => 'concluída']);

        return true;
    }

    /**
     * Valida se o prazo do edital já decorreu (data_publicacao + prazo_dias <= hoje).
     * Usado ao concluir a subtarefa de decurso do prazo.
     */
    private function validarDecursoParaCertificacao(string $editalId): void
    {
        $edital = $this->repository->findById($editalId);
        if ($edital === null) {
            throw new InvalidArgumentException('Edital não encontrado.');
        }
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        if ($prazoDias < 1) {
            throw new InvalidArgumentException('Edital sem prazo definido. Não é possível certificar o decurso.');
        }

        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId, $usarLeituraAtual);
        $subPublicacao = null;
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordemSt = (int) ($st['ordem'] ?? 0);
            if ($tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO || $ordemSt === EditaisSubtarefasRepository::ORDEM_PUBLICACAO || $ordemSt === 3) {
                $subPublicacao = $st;
                break;
            }
        }
        $statusPub = strtolower(trim((string) ($subPublicacao['status'] ?? '')));
        if ($statusPub === 'concluída') {
            $statusPub = 'concluida';
        }
        if ($subPublicacao === null || $statusPub !== 'concluida') {
            throw new InvalidArgumentException('A certificação da publicação deve estar concluída antes de certificar o decurso do prazo.');
        }

        $dataPublicacao = trim((string) ($edital['data_publicacao'] ?? ''));
        if ($dataPublicacao === '') {
            $dadosJson = $subPublicacao['dados_especificos'] ?? null;
            if ($dadosJson !== null && $dadosJson !== '') {
                $dados = json_decode($dadosJson, true);
                if (is_array($dados)) {
                    $dataPublicacao = trim((string) ($dados['data_publicacao'] ?? ''));
                }
            }
        }
        if ($dataPublicacao === '') {
            throw new InvalidArgumentException('Data da publicação não informada na subtarefa anterior. Não é possível calcular o decurso do prazo.');
        }

        $dataDecurso = trim((string) ($edital['data_decurso_prazo'] ?? ''));
        if ($dataDecurso === '') {
            throw new InvalidArgumentException('Data de decurso não calculada. Refaça a certificação da publicação.');
        }
        $dtPub = \DateTime::createFromFormat('!Y-m-d', $dataDecurso);
        if ($dtPub === false || $dtPub->format('Y-m-d') !== $dataDecurso) {
            throw new InvalidArgumentException('Data do decurso inválida.');
        }
        $hoje = new \DateTime('today');
        if ($hoje <= $dtPub) {
            $decursoFormatado = $dtPub->format('d/m/Y');
            throw new InvalidArgumentException('O prazo do edital ainda não decorreu. Só é possível certificar o decurso a partir do dia seguinte a ' . $decursoFormatado . '.');
        }
    }

    /**
     * Retorna informações para exibição do decurso do prazo (data limite e se já pode certificar).
     * data_publicacao (Y-m-d), data_limite_decurso (Y-m-d), data_limite_formatada (d/m/Y), prazo_cumprido (bool).
     */
    public function getDecursoInfo(string $editalId, bool $persistirCalculo = false, bool $usarLeituraAtual = false): array
    {
        $edital = $usarLeituraAtual
            ? $this->repository->findByIdForUpdate($editalId)
            : $this->repository->findById($editalId);
        if ($edital === null) {
            return [
                'data_publicacao' => '',
                'data_inicio_contagem' => '',
                'data_limite_decurso' => '',
                'data_limite_formatada' => '',
                'prazo_cumprido' => false,
                'libera_conclusao' => false,
                'dias_restantes' => 0,
            ];
        }
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        $dataPublicacao = $this->resolverDataPublicacaoParaDecurso($edital);
        $dataInicioContagem = trim((string) ($edital['data_inicio_contagem'] ?? ''));
        $dataDecurso = trim((string) ($edital['data_decurso_prazo'] ?? ''));
        if ($dataPublicacao === '' || $prazoDias < 1) {
            return [
                'data_publicacao' => $dataPublicacao,
                'data_inicio_contagem' => '',
                'data_limite_decurso' => '',
                'data_limite_formatada' => '',
                'prazo_cumprido' => false,
                'libera_conclusao' => false,
                'dias_restantes' => 0,
            ];
        }

        if ($dataInicioContagem === '' || $dataDecurso === '') {
            [$dataInicioContagem, $dataDecurso] = $this->calcularInicioEDataDecurso($dataPublicacao, $prazoDias);
            if ($persistirCalculo) {
                $this->repository->update($editalId, [
                    'data_inicio_contagem' => $dataInicioContagem,
                    'data_decurso_prazo' => $dataDecurso,
                ]);
            }
        }
        $dtDecurso = \DateTime::createFromFormat('!Y-m-d', $dataDecurso);
        if ($dtDecurso === false || $dtDecurso->format('Y-m-d') !== $dataDecurso) {
            return [
                'data_publicacao' => $dataPublicacao,
                'data_inicio_contagem' => $dataInicioContagem,
                'data_limite_decurso' => '',
                'data_limite_formatada' => '',
                'prazo_cumprido' => false,
                'libera_conclusao' => false,
                'dias_restantes' => 0,
            ];
        }
        $dtDecursoImutavel = \DateTimeImmutable::createFromMutable($dtDecurso);
        [$prazoCumprido, $liberaConclusao, $diasRestantes] = self::estadoDecurso($dtDecursoImutavel, new \DateTimeImmutable('now'));
        return [
            'data_publicacao' => $dataPublicacao,
            'data_inicio_contagem' => $dataInicioContagem,
            'data_limite_decurso' => $dataDecurso,
            'data_limite_formatada' => $dtDecursoImutavel->format('d/m/Y'),
            'prazo_cumprido' => $prazoCumprido,
            'libera_conclusao' => $liberaConclusao,
            'dias_restantes' => $diasRestantes,
        ];
    }

    /** Executa alteração sob transação e lock do edital pai antes de tocar as etapas. */
    private function reconciliarComEditalBloqueado(string $editalId, callable $operation): mixed
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = $ownsTransaction ? null : 'edital_reconciliar_' . bin2hex(random_bytes(8));
        if ($ownsTransaction) {
            if (!$this->connection->beginTransaction()) {
                throw new RuntimeException('Não foi possível iniciar a transação de reconciliação do edital.');
            }
        } else {
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }
        try {
            $lockSuffix = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $statement = $this->connection->prepare('SELECT `id` FROM `editais` WHERE `id` = :id' . $lockSuffix);
            $statement->execute([':id' => $editalId]);
            $exists = $statement->fetchColumn() !== false;
            $statement->closeCursor();
            if (!$exists) {
                if ($ownsTransaction) {
                    $this->connection->commit();
                } else {
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
                return false;
            }
            $result = $operation();
            if ($ownsTransaction) {
                if (!$this->connection->commit()) {
                    throw new RuntimeException('Não foi possível confirmar a transação de reconciliação do edital.');
                }
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                if ($ownsTransaction) {
                    $this->connection->rollBack();
                } else {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }
            throw $exception;
        }
    }

    /** Executa manutenção legada apenas após ação explícita e autorizada do Cartório. */
    public function reconciliarEditais(array $editalIds): array
    {
        if (count($editalIds) > 15) {
            throw new InvalidArgumentException('A atualização aceita no máximo 15 editais por vez.');
        }
        $ids = [];
        foreach ($editalIds as $value) {
            if (!is_string($value) && !is_int($value)) {
                throw new InvalidArgumentException('A lista contém um identificador inválido.');
            }
            $id = trim((string) $value);
            if ($id === '' || strlen($id) > 128) {
                throw new InvalidArgumentException('A lista contém um identificador inválido.');
            }
            $ids[$id] = true;
        }
        if (count($ids) > 15) {
            throw new InvalidArgumentException('A atualização aceita no máximo 15 editais por vez.');
        }

        $mysql = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql && $this->connection->inTransaction()) {
            throw new LogicException('A reconciliação de editais não pode herdar uma transação externa no MySQL.');
        }
        $lockAcquired = false;
        if ($mysql) {
            $lock = $this->connection->prepare('SELECT GET_LOCK(?, 15)');
            $lock->execute(['fokus-law-feriados-recalc']);
            $lockAcquired = (int) $lock->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new RuntimeException('Não foi possível obter o bloqueio do calendário para reconciliar editais.');
            }
        }

        try {
            $ownsTransaction = !$this->connection->inTransaction();
            $savepoint = $ownsTransaction ? null : 'edital_lote_reconciliar_' . bin2hex(random_bytes(8));
            if ($ownsTransaction) {
                if (!$this->connection->beginTransaction()) {
                    throw new RuntimeException('Não foi possível iniciar a transação de reconciliação dos editais.');
                }
            } else {
                $this->connection->exec('SAVEPOINT ' . $savepoint);
            }

            $result = [
                'solicitados' => count($ids),
                'editais_alterados' => 0,
                'editais_com_etapas_criadas' => 0,
                'etapas_criadas' => 0,
                'editais_com_datas_calculadas' => 0,
                'inalterados' => 0,
                'nao_encontrados' => 0,
                'finalizados' => 0,
                'falhas' => 0,
            ];
            try {
                $orderedIds = array_keys($ids);
                sort($orderedIds, SORT_STRING);
                foreach ($orderedIds as $id) {
                    $outcome = $this->reconciliarComEditalBloqueado($id, function () use ($id): array {
                        $edital = $this->repository->findByIdForUpdate($id);
                        if ($edital === null) {
                            return ['encontrado' => false, 'etapas_criadas' => 0, 'finalizado' => false];
                        }
                        $status = strtolower(trim((string) ($edital['status'] ?? '')));
                        if ($status === 'concluída') {
                            $status = 'concluida';
                        }
                        if (in_array($status, ['concluida', 'cancelada'], true)) {
                            return ['encontrado' => true, 'etapas_criadas' => 0, 'finalizado' => false];
                        }
                        $subtarefasAntes = count($this->subtarefasRepository->findByEditalId($id));
                        $this->subtarefasRepository->ensureSubtarefasAtivas($id);
                        $subtarefasDepois = count($this->subtarefasRepository->findByEditalId($id));
                        $datasAntes = [
                            trim((string) ($edital['data_inicio_contagem'] ?? '')),
                            trim((string) ($edital['data_decurso_prazo'] ?? '')),
                        ];
                        $finalizado = $this->tentarFinalizacaoAutomaticaPorDecurso($id, true);
                        $editalAtualizado = $this->repository->findByIdForUpdate($id);
                        $datasDepois = [
                            trim((string) ($editalAtualizado['data_inicio_contagem'] ?? '')),
                            trim((string) ($editalAtualizado['data_decurso_prazo'] ?? '')),
                        ];
                        return [
                            'encontrado' => true,
                            'etapas_criadas' => max(0, $subtarefasDepois - $subtarefasAntes),
                            'datas_calculadas' => $datasDepois !== $datasAntes
                                && $datasDepois[0] !== ''
                                && $datasDepois[1] !== '',
                            'finalizado' => $finalizado,
                        ];
                    });
                    if (!is_array($outcome)) {
                        $result['nao_encontrados']++;
                        continue;
                    }
                    if (empty($outcome['encontrado'])) {
                        $result['nao_encontrados']++;
                        continue;
                    }
                    $created = max(0, (int) ($outcome['etapas_criadas'] ?? 0));
                    if ($created > 0) {
                        $result['editais_com_etapas_criadas']++;
                        $result['etapas_criadas'] += $created;
                    }
                    if (!empty($outcome['finalizado'])) {
                        $result['finalizados']++;
                    }
                    if (!empty($outcome['datas_calculadas'])) {
                        $result['editais_com_datas_calculadas']++;
                    }
                    if ($created > 0 || !empty($outcome['datas_calculadas']) || !empty($outcome['finalizado'])) {
                        $result['editais_alterados']++;
                    }
                    if ($created === 0 && empty($outcome['datas_calculadas']) && empty($outcome['finalizado'])) {
                        $result['inalterados']++;
                    }
                }
                if ($ownsTransaction) {
                    if (!$this->connection->commit()) {
                        throw new RuntimeException('Não foi possível confirmar a transação de reconciliação dos editais.');
                    }
                } else {
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $exception) {
                if ($this->connection->inTransaction()) {
                    if ($ownsTransaction) {
                        $this->connection->rollBack();
                    } else {
                        $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                        $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                    }
                }
                throw $exception;
            }
            return $result;
        } finally {
            if ($lockAcquired) {
                try {
                    $release = $this->connection->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute(['fokus-law-feriados-recalc']);
                    if ((int) $release->fetchColumn() !== 1) {
                        error_log('Falha ao liberar mutex do calendário após reconciliação de editais.');
                    }
                } catch (Throwable $releaseError) {
                    error_log('Falha ao liberar mutex do calendário após reconciliação de editais: ' . $releaseError->getMessage());
                }
            }
        }
    }

    /** Compara dias civis no fuso configurado, sem antecipar a certificação. */
    private static function estadoDecurso(\DateTimeImmutable $dataLimite, \DateTimeImmutable $agora): array
    {
        $hoje = $agora->setTime(0, 0);
        $limite = $dataLimite->setTime(0, 0);
        return [$hoje >= $limite, $hoje > $limite, $hoje < $limite ? (int) $hoje->diff($limite)->days : 0];
    }

    /**
     * Recalcula e persiste `data_inicio_contagem` e `data_decurso_prazo` nos editais em fluxo
     * cujo intervalo de contagem intercepta o período do feriado (inclusive extremos).
     */
    public function recalcularDecursosPorSobreposicaoFeriado(string $ferIni, string $ferFim): int
    {
        $ids = $this->repository->findIdsComDecursoPorSobreposicaoFeriado($ferIni, $ferFim);

        return $this->recalcularDecursoParaEditalIds($ids);
    }

    /**
     * @param list<array{0:string,1:string}> $intervalos Lista de [data_inicio, data_fim] do feriado (Y-m-d)
     */
    public function recalcularDecursosPorIntervalosFeriado(array $intervalos): int
    {
        $intervalosValidos = [];
        foreach ($intervalos as $par) {
            if (!is_array($par) || count($par) < 2) {
                continue;
            }
            $ini = trim((string) ($par[0] ?? ''));
            $fim = trim((string) ($par[1] ?? ''));
            if ($ini === '' || $fim === '' || $fim < $ini) {
                continue;
            }
            $intervalosValidos[$ini . '/' . $fim] = [$ini, $fim];
        }
        if ($intervalosValidos === []) {
            return 0;
        }

        $ids = $this->repository->findIdsComDecursoPorIntervalosFeriado(array_values($intervalosValidos));
        $unicos = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $unicos[$id] = true;
            }
        }

        return $this->recalcularDecursoParaEditalIds(array_keys($unicos));
    }

    /**
     * @param list<string> $ids
     */
    public function recalcularDecursoParaEditalIds(array $ids): int
    {
        $n = 0;
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            if ($this->recalcularESalvarDecursoEdital($id)) {
                $n++;
            }
        }

        return $n;
    }

    private function recalcularESalvarDecursoEdital(string $editalId): bool
    {
        $edital = $this->repository->findById($editalId);
        if ($edital === null) {
            return false;
        }
        $dataPublicacao = $this->resolverDataPublicacaoParaDecurso($edital);
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        if ($dataPublicacao === '' || $prazoDias < 1) {
            return false;
        }
        try {
            [$inicioContagem, $dataDecurso] = $this->calcularInicioEDataDecurso($dataPublicacao, $prazoDias);
        } catch (InvalidArgumentException $e) {
            return false;
        }
        $this->repository->update($editalId, [
            'data_inicio_contagem' => $inicioContagem,
            'data_decurso_prazo' => $dataDecurso,
        ]);

        return true;
    }

    /**
     * Data de publicação na tabela `editais` ou, se vazia, em dados_especificos da subtarefa de publicação concluída.
     */
    private function resolverDataPublicacaoParaDecurso(array $edital): string
    {
        $dataPublicacao = trim((string) ($edital['data_publicacao'] ?? ''));
        if ($dataPublicacao !== '') {
            return $dataPublicacao;
        }
        $editalId = trim((string) ($edital['id'] ?? ''));
        if ($editalId === '') {
            return '';
        }
        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId);
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordemSt = (int) ($st['ordem'] ?? 0);
            $statusSt = strtolower(trim((string) ($st['status'] ?? '')));
            if ($statusSt === 'concluída') {
                $statusSt = 'concluida';
            }
            $ehPublicacao = $tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO
                || $ordemSt === EditaisSubtarefasRepository::ORDEM_PUBLICACAO
                || $ordemSt === 3;
            if (!$ehPublicacao || $statusSt !== 'concluida') {
                continue;
            }
            $dadosJson = $st['dados_especificos'] ?? null;
            if ($dadosJson !== null && $dadosJson !== '') {
                $dados = json_decode((string) $dadosJson, true);
                if (is_array($dados)) {
                    return trim((string) ($dados['data_publicacao'] ?? ''));
                }
            }
            break;
        }

        return '';
    }

    /**
     * Regras definidas:
     * - início = dia seguinte da publicação, ajustado para próximo dia útil;
     * - contagem processual = dia inicial não conta => soma exatamente prazo_dias ao início;
     * - vencimento ajustado para próximo dia útil se cair em sábado/domingo/feriado.
     *
     * @return array{0:string,1:string} [data_inicio_contagem, data_decurso_prazo]
     */
    private function calcularInicioEDataDecurso(string $dataPublicacao, int $prazoDias): array
    {
        $publicacao = \DateTimeImmutable::createFromFormat('Y-m-d', $dataPublicacao);
        if ($publicacao === false || $publicacao->format('Y-m-d') !== $dataPublicacao) {
            throw new InvalidArgumentException('Data da publicação inválida para cálculo do decurso.');
        }

        $inicio = $publicacao->modify('+1 day');
        $inicio = $this->proximoDiaUtil($inicio);

        $decurso = $inicio->modify('+' . $prazoDias . ' days');
        $decurso = $this->proximoDiaUtil($decurso);

        return [$inicio->format('Y-m-d'), $decurso->format('Y-m-d')];
    }

    private function proximoDiaUtil(\DateTimeImmutable $data): \DateTimeImmutable
    {
        $cursor = $data;
        while ($this->isDiaNaoUtil($cursor)) {
            $cursor = $cursor->modify('+1 day');
        }
        return $cursor;
    }

    private function isDiaNaoUtil(\DateTimeImmutable $data): bool
    {
        $diaSemana = (int) $data->format('N');
        if ($diaSemana >= 6) {
            return true;
        }

        return $this->feriadosRepository->isDiaNaoUtil($data);
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

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $allowed = ['observacoes', 'status'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }
        if (empty($data)) {
            return false;
        }
        return $this->repository->update($id, $data);
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

        if ($this->connection->inTransaction()) {
            throw new RuntimeException('Exclusão requer conexão sem transação anterior.');
        }
        $this->connection->beginTransaction();
        try {
            $lock = $this->connection->prepare('SELECT `id` FROM `editais` WHERE `id` = :id FOR UPDATE');
            $lock->execute([':id' => $id]);
            $exists = $lock->fetchColumn() !== false;
            $lock->closeCursor();
            $messages = [];
            if ($exists) {
                foreach ($this->subtarefasRepository->findByEditalId($id) as $subtask) {
                    $type = trim((string) ($subtask['tipo'] ?? 'subtarefa'));
                    if ($type !== '') $messages[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $type) . '.';
                }
                $this->subtarefasRepository->deleteByEditalId($id);
                $this->alocadosRepository->deleteByEditalId($id);
                if (!$this->repository->delete($id)) {
                    throw new RuntimeException('Não foi possível excluir o edital bloqueado.');
                }
            }
            $this->connection->commit();
            return [
                'deleted' => $exists,
                'subtarefas_toasts' => $messages,
                'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
                'subtarefas_orfas_removidas' => 0,
            ];
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }
}
