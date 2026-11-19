<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasRecebidasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../repositories/ComarcasRepository.php';
require_once __DIR__ . '/../repositories/VarasRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartasRecebidasUiCopy.php';

final class CartasRecebidasService
{
    private PDO $connection;
    private CartasRecebidasRepository $repository;
    private CartasRecebidasSubtarefasRepository $subtarefasRepository;
    private CartasRecebidasAlocadosRepository $alocadosRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private TribunaisRepository $tribunaisRepository;
    private ComarcasRepository $comarcasRepository;
    private VarasRepository $varasRepository;


    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CartasRecebidasRepository($connection);
        $this->subtarefasRepository = new CartasRecebidasSubtarefasRepository($connection);
        $this->alocadosRepository = new CartasRecebidasAlocadosRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->tribunaisRepository = new TribunaisRepository($connection);
        $this->comarcasRepository = new ComarcasRepository($connection);
        $this->varasRepository = new VarasRepository($connection);
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    public function getClasseCounts(array $filters): array
    {
        return $this->repository->countByClasse($filters);
    }

    public function getFinalidadeCounts(array $filters): array
    {
        return $this->repository->countByFinalidade($filters);
    }

    public function create(array $payload): string
    {
        $numero=trim((string)($payload['numero_processo_origem']??$payload['processo']??''));
        $digits=preg_replace('/\D+/', '', $numero);
        if(strlen($digits)!==20)throw new InvalidArgumentException('O número do processo de origem deve conter os 20 dígitos do CNJ.');
        $lockName='cr_cnj_'.substr(hash('sha256',$digits),0,56);
        $lock=$this->connection->prepare('SELECT GET_LOCK(?, 10)');$lock->execute([$lockName]);
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Não foi possível verificar o cadastro da carta. Tente novamente.');
        $ownsTransaction=!$this->connection->inTransaction();
        try {
            if($ownsTransaction)$this->connection->beginTransaction();
            if($this->repository->existsByNumeroProcessoOrigem($digits)){
                throw new InvalidArgumentException('Já existe uma carta recebida cadastrada para o processo '.$this->formatarCNJ($digits).'. Reinicie o registro existente.');
            }
            $id=$this->createCarta($payload);
            if($ownsTransaction)$this->connection->commit();
            return $id;
        } catch(Throwable $e) {
            if($ownsTransaction&&$this->connection->inTransaction())$this->connection->rollBack();
            throw $e;
        } finally {
            try {$release=$this->connection->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}
            catch(Throwable $releaseError){error_log('liberar bloqueio carta recebida: '.$releaseError->getMessage());}
        }
    }

    private function createCarta(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo de origem é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20
            ? $this->formatarCNJ($digits)
            : $numeroProcesso;

        $ufOrigem = strtoupper(trim((string) ($payload['uf_origem'] ?? $payload['estado'] ?? $payload['uf'] ?? '')));
        if (strlen($ufOrigem) !== 2) {
            throw new InvalidArgumentException('A UF de origem é obrigatória (2 caracteres).');
        }

        $cidadeOrigem = trim((string) ($payload['cidade_origem'] ?? $payload['cidade'] ?? ''));
        if ($cidadeOrigem === '') {
            throw new InvalidArgumentException('A cidade de origem é obrigatória.');
        }
        $cidadeOrigem = TextFormatter::formatProperName($cidadeOrigem, 120);

        $tribunalSigla = 'TJ' . $ufOrigem;
        $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça do Estado');
        $comarcaOrigem = $this->comarcasRepository->findByTribunalUfNome($tribunalId, $ufOrigem, $cidadeOrigem);
        $comarcaOrigemId = $comarcaOrigem !== null
            ? (string) $comarcaOrigem['id']
            : $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, $cidadeOrigem);

        $dataRecebimento = $this->parseDate($payload['data_recebimento'] ?? null);
        $finalidade = trim((string) ($payload['finalidade'] ?? ''));
        if ($finalidade === '') {
            throw new InvalidArgumentException('A finalidade é obrigatória.');
        }
        $prazoDias = (int) $this->sanitizePrazo((string) ($payload['prazo_dias'] ?? $payload['prazo'] ?? '30'));

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        $classesCartaRecebida = [
            '355' => 'Carta Precatória Criminal',
            '335' => 'Carta de Ordem Criminal',
            '375' => 'Carta Rogatória Criminal',
        ];
        if (!isset($classesCartaRecebida[$classeCodigo])) {
            throw new InvalidArgumentException('Selecione uma das classes permitidas para carta recebida.');
        }
        $classeId = $this->classesRepository->ensureClass($classeCodigo, $classesCartaRecebida[$classeCodigo]);

        $processoOrigemId = null;
        if (strlen($digits) === 20) {
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, null);
        }

        $idCarta = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CPR',
            30
        );

        $row = [
            'id' => $idCarta,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'comarca_origem_id' => $comarcaOrigemId,
            'cidade_origem' => $cidadeOrigem,
            'uf_origem' => $ufOrigem,
            'data_recebimento' => $dataRecebimento,
            'finalidade' => $finalidade,
            'prazo_dias' => $prazoDias,
            'status' => 'aguardando cumprimento',
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }

        $this->repository->insert($row);

        $repo = $this->subtarefasRepository;
        $repo->create($idCarta, $repo::TIPO_CUMPRIMENTO, $repo::ORDEM_CUMPRIMENTO, 'em_andamento', null);
        $repo->create($idCarta, $repo::TIPO_DEVOLUCAO, $repo::ORDEM_DEVOLUCAO, 'pendente', null);

        $this->repository->update($idCarta, ['status' => CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO]);

        return $idCarta;
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);
        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = $ownsTransaction ? null : 'carta_recebida_conclusao_' . bin2hex(random_bytes(8));

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            // A carta é o mutex por fluxo: duas requisições concorrentes para
            // cumprimento/devolução serializam antes de validar e gravar.
            $cartaLookup = $this->connection->prepare('SELECT `id` FROM `cartas_recebidas` WHERE `id` = :id LIMIT 1 FOR UPDATE');
            $cartaLookup->execute([':id' => trim($this->subtarefasRepository->findById($subtarefaId)['carta_recebida_id'] ?? '')]);
            $cartaLockId = $cartaLookup->fetchColumn();
            if ($cartaLockId === false || trim((string) $cartaLockId) === '') {
                throw new InvalidArgumentException('Subtarefa não encontrada.');
            }

            $taskLookup = $this->connection->prepare('SELECT `id`, `carta_recebida_id`, `tipo`, `ordem`, `status` FROM `cartas_recebidas_subtarefas` WHERE `id` = :id LIMIT 1 FOR UPDATE');
            $taskLookup->execute([':id' => trim($subtarefaId)]);
            $subtarefa = $taskLookup->fetch(PDO::FETCH_ASSOC);
            if ($subtarefa === false) {
                throw new InvalidArgumentException('Subtarefa não encontrada.');
            }

            $ordem = (int) ($subtarefa['ordem'] ?? 0);
            $cartaId = trim((string) ($subtarefa['carta_recebida_id'] ?? ''));
            $tipoAtual = trim((string) ($subtarefa['tipo'] ?? ''));
            $statusAtual = strtolower(trim((string) ($subtarefa['status'] ?? '')));

            if ($cartaId === '' || $cartaId !== trim((string) $cartaLockId)) {
                throw new InvalidArgumentException('Carta da subtarefa não identificada.');
            }
            if (!CartasRecebidasSubtarefasRepository::isOrdemAtiva($ordem)
                || !CartasRecebidasSubtarefasRepository::isTipoAtivo($tipoAtual)
                || CartasRecebidasSubtarefasRepository::tipoPorOrdem($ordem) !== $tipoAtual) {
                throw new InvalidArgumentException('Subtarefa inválida para o fluxo atual.');
            }

            if ($ordem > CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO) {
                $anterior = $this->connection->prepare('SELECT `id`, `status` FROM `cartas_recebidas_subtarefas` WHERE `carta_recebida_id` = :carta_id AND `ordem` = :ordem ORDER BY `updated_at` DESC LIMIT 1 FOR UPDATE');
                $anterior->execute([
                    ':carta_id' => $cartaId,
                    ':ordem' => CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO,
                ]);
                $anterior = $anterior->fetch(PDO::FETCH_ASSOC);
                if ($anterior === false || strtolower(trim((string) ($anterior['status'] ?? ''))) !== 'concluida') {
                    throw new InvalidArgumentException('Conclua o cumprimento antes de devolver a carta.');
                }
            }

            $jaConcluida = in_array($statusAtual, ['concluida', 'concluída'], true);
            if (!$jaConcluida && $statusAtual !== 'em_andamento') {
                throw new InvalidArgumentException('Somente a subtarefa em andamento pode ser concluída.');
            }

            if (!$jaConcluida) {
                $this->subtarefasRepository->marcarConcluidaComDados($subtarefaId, $dadosJson);

                $proximaOrdem = $this->subtarefasRepository->obterProximaOrdem($ordem);
                if ($proximaOrdem !== null) {
                    $proximoTipo = $this->subtarefasRepository->obterProximoTipo($ordem);
                    $existente = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, $proximaOrdem);
                    if ($existente === null) {
                        $this->subtarefasRepository->create($cartaId, (string) $proximoTipo, $proximaOrdem, 'em_andamento', null);
                    } else {
                        $statusProx = strtolower(trim((string) ($existente['status'] ?? '')));
                        if ($statusProx === 'concluída') {
                            $statusProx = 'concluida';
                        }
                        if ($statusProx !== 'concluida' && $statusProx !== CartasRecebidasSubtarefasRepository::STATUS_CANCELADA) {
                            $this->subtarefasRepository->atualizarStatus((string) ($existente['id'] ?? ''), 'em_andamento');
                        }
                    }
                    $this->repository->update($cartaId, ['status' => CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO]);
                    $result = ['tarefa_principal_concluida' => false];
                } else {
                    $this->repository->update($cartaId, ['status' => 'concluída']);
                    $result = $this->resultadoConclusaoCarta($cartaId);
                }
            } else {
                $result = $ordem === CartasRecebidasSubtarefasRepository::ORDEM_DEVOLUCAO
                    ? $this->resultadoConclusaoCarta($cartaId)
                    : ['tarefa_principal_concluida' => false];
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            } elseif (!$ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            throw $exception;
        }
    }

    private function resultadoConclusaoCarta(string $cartaId): array
    {
        $carta = $this->repository->find($cartaId);
        $processo = trim((string) ($carta['numero_processo_origem'] ?? ''));
        $finalidade = trim((string) ($carta['finalidade'] ?? ''));
        $cidade = trim((string) ($carta['cidade_origem'] ?? ''));
        $uf = trim((string) ($carta['uf_origem'] ?? ''));
        $msg = 'Tarefa principal concluída automaticamente: carta recebida do processo '
            . ($processo !== '' ? $processo : 'não informado')
            . ' (' . ($finalidade !== '' ? $finalidade : 'finalidade não informada')
            . ' de ' . (($cidade !== '' ? $cidade : 'comarca não informada') . ($uf !== '' ? '/' . $uf : '')) . ').';

        return ['tarefa_principal_concluida' => true, 'tarefa_principal_mensagem' => $msg];
    }

    public function cancelarSubtarefa(string $subtarefaId): void
    {
        throw new InvalidArgumentException('Cancelamento de subtarefa não está disponível neste fluxo.');
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

    private function sanitizePrazo(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor);
        return $digits === '' ? '30' : substr($digits, 0, 3);
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
        unset($payload['usuario_id'], $payload['alocar_usuario_ids']);

        $camposComarca = ['comarca_origem_id', 'cidade_origem', 'uf_origem'];
        if (array_intersect($camposComarca, array_keys($payload))) {
            foreach ($camposComarca as $campo) {
                if (!isset($payload[$campo]) || !is_string($payload[$campo]) || trim($payload[$campo]) === '') {
                    throw new InvalidArgumentException('Informe comarca, cidade e UF correspondentes.');
                }
            }
            if (!$this->comarcasRepository->correspondeIdCidadeUf($payload['comarca_origem_id'], $payload['cidade_origem'], $payload['uf_origem'])) {
                throw new InvalidArgumentException('A comarca não corresponde à cidade e UF informadas.');
            }
        }

        return $this->repository->update($id, $payload);
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

        $subtarefas = $this->subtarefasRepository->findByCartaId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByCartaId($id);
        $this->alocadosRepository->deleteByCartaId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    public function criarPrimeiraSubtarefa(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            throw new InvalidArgumentException('Identificador da carta é obrigatório.');
        }
        if ($this->repository->find($cartaId) === null) {
            throw new InvalidArgumentException('Carta recebida não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCartaId($cartaId);
        if (count($existentes) > 0) {
            throw new InvalidArgumentException('Esta carta já possui subtarefas.');
        }
        $repo = $this->subtarefasRepository;
        $repo->create($cartaId, $repo::TIPO_CUMPRIMENTO, $repo::ORDEM_CUMPRIMENTO, 'em_andamento', null);
        $repo->create($cartaId, $repo::TIPO_DEVOLUCAO, $repo::ORDEM_DEVOLUCAO, 'pendente', null);
        $this->repository->update($cartaId, ['status' => CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO]);
    }
}
