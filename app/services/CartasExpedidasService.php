<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasExpedidasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../repositories/ComarcasRepository.php';
require_once __DIR__ . '/../repositories/VarasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasAlocadosRepository.php';
require_once __DIR__ . '/DataJudService.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
require_once __DIR__ . '/../helpers/CentralMandadosCcm.php';
require_once __DIR__ . '/../helpers/IdempotencySchemaUnavailableException.php';

final class CartasExpedidasService
{
    private PDO $connection;
    private CartasExpedidasRepository $repository;
    private CartasExpedidasSubtarefasRepository $subtarefasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private TribunaisRepository $tribunaisRepository;
    private ComarcasRepository $comarcasRepository;
    private VarasRepository $varasRepository;
    private CartasExpedidasAlocadosRepository $alocadosRepository;
    private ?DataJudService $dataJudService;


    public function __construct(PDO $connection, ?DataJudService $dataJudService = null)
    {
        $this->connection = $connection;
        $this->repository = new CartasExpedidasRepository($connection);
        $this->subtarefasRepository = new CartasExpedidasSubtarefasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->tribunaisRepository = new TribunaisRepository($connection);
        $this->comarcasRepository = new ComarcasRepository($connection);
        $this->varasRepository = new VarasRepository($connection);
        $this->alocadosRepository = new CartasExpedidasAlocadosRepository($connection);
        $this->dataJudService = $dataJudService;
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idCarta = (string) ($row['IdCarta'] ?? '');
            if ($idCarta !== '') {
                $statusCarta = trim((string) ($row['Status'] ?? ''));
                if (!in_array($statusCarta, ['concluída', 'concluida', 'finalizada', 'cancelada'], true)) {
                    $this->subtarefasRepository->garantirSubtarefasFluxo($idCarta, $statusCarta);
                    $statusInferido = $this->subtarefasRepository->inferirStatusTarefaPrincipal($idCarta);
                    if ($statusInferido !== null && $statusInferido !== $statusCarta) {
                        $this->repository->update($idCarta, ['status' => $statusInferido]);
                        $rows[$i]['Status'] = $statusInferido;
                    }
                }
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByCartaId($idCarta);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByCartaId($idCarta);
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
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

    public function getClasseCounts(array $filters): array
    {
        return $this->repository->countByClasse($filters);
    }

    public function getFinalidadeCounts(array $filters): array
    {
        return $this->repository->countByFinalidade($filters);
    }

    private static function sigiloParaCriacao(array $payload): ?bool
    {
        return !empty($payload['nivel_sigilo']) ? true : null;
    }

    public function create(array $payload): string
    {
        $processo = trim((string) ($payload['processo'] ?? $payload['numero_processo'] ?? ''));
        if ($processo === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = $this->onlyDigits($processo);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('O número do processo está incompleto ou inválido (20 dígitos).');
        }

        $numeroFormatado = $this->formatarCNJ($digits);

        $idempotencyKey = strtolower(trim((string) ($payload['idempotency_key'] ?? '')));
        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        $payloadHash = null;
        if ($idempotencyKey !== '') {
            if (preg_match('/^[a-f0-9]{64}$/D', $idempotencyKey) !== 1 || $usuarioCadastroId === '') {
                throw new InvalidArgumentException('Chave de envio inválida. Atualize a página e tente novamente.');
            }
            try {
                $this->repository->assertIdempotencyColumnsAvailable();
            } catch (Throwable $exception) {
                throw new IdempotencySchemaUnavailableException('O envio seguro está temporariamente indisponível.', 0, $exception);
            }
            $payloadHash = self::hashCreatePayload($payload, $digits, $usuarioCadastroId);
            $existing = $this->repository->findByIdempotencyKey($usuarioCadastroId, $idempotencyKey);
            if ($existing !== null) {
                return self::resolveIdempotentResult($existing, $payloadHash);
            }
        }

        $ufDestino = strtoupper(trim((string) ($payload['estado'] ?? $payload['uf'] ?? '')));
        if (strlen($ufDestino) !== 2) {
            throw new InvalidArgumentException('O estado (UF) de destino é obrigatório (2 caracteres).');
        }

        $cidadeDestino = trim((string) ($payload['cidade'] ?? ''));
        if ($cidadeDestino === '') {
            throw new InvalidArgumentException('A cidade de destino é obrigatória.');
        }
        $cidadeDestino = TextFormatter::formatProperName($cidadeDestino, 120);

        if (CentralMandadosCcm::contains($cidadeDestino, $ufDestino)) {
            throw new InvalidArgumentException(CentralMandadosCcm::MESSAGE);
        }
        $cidadeDestinoNormalizada = $this->normalizeNomeCidade($cidadeDestino);
        if ($cidadeDestinoNormalizada === 'ilheus') {
            throw new InvalidArgumentException('O cadastro de carta expedida não é permitido para a cidade de Ilhéus (comarca de origem).');
        }

        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeNome = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        // Criar uma carta não autoriza retirar o sigilo de processo existente.
        $sigiloso = self::sigiloParaCriacao($payload);

        if ($this->dataJudService !== null) {
            try {
                $dataJud = $this->dataJudService->searchProcesso($digits);
                $classeCodigo = trim((string) ($dataJud['classe']['codigo'] ?? $classeCodigo));
                $classeNome = trim((string) ($dataJud['classe']['descricao'] ?? $classeNome));
            } catch (Throwable $e) {
                // Mantém classe do formulário em caso de falha no DataJud
            }
        }

        $classeInformada = $classeCodigo !== '' || $classeNome !== '';
        $classeNaoInformada = !$classeInformada
            || $this->normalizeClasseLabel($classeNome) === 'classe nao informada'
            || $this->normalizeClasseLabel($classeCodigo) === 'classe nao informada';

        // Quando a API não retorna a classe (ex.: processo sigiloso), permite cadastro e marca o processo como sigiloso
        if ($classeNaoInformada) {
            $sigiloso = true;
        }

        try {
            return $this->withAtomicCreate(function () use (
            $payload,
            $classeNaoInformada,
            $classeCodigo,
            $classeNome,
            $sigiloso,
            $numeroFormatado,
            $ufDestino,
            $cidadeDestino,
            $idempotencyKey,
            $payloadHash
        ): string {

        $tribunalSigla = 'TJBA';
        $ufOrigem = 'BA';
        $comarcaOrigemNome = 'Ilhéus';
        $varaOrigemNome = 'Primeira Vara Crime da Comarca de Ilhéus';

        $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça da Bahia');
        $comarcaOrigemId = $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, $comarcaOrigemNome);
        $varaOrigemId = $this->varasRepository->ensureVara($comarcaOrigemId, $varaOrigemNome, null);

        $classeId = null;
        if (!$classeNaoInformada && $classeCodigo !== '') {
            $classeId = $this->classesRepository->ensureClass((string) $classeCodigo, $classeNome !== '' ? TextFormatter::formatProperName((string) $classeNome, 200) : (string) $classeCodigo);
        }
        $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, $sigiloso);

        $tribunalDestinoSigla = 'TJ' . $ufDestino;
        $tribunalDestinoId = $this->tribunaisRepository->ensureTribunal($tribunalDestinoSigla, 'Tribunal de Justiça do Estado');
        $comarcaDestino = $this->comarcasRepository->findByTribunalUfNome($tribunalDestinoId, $ufDestino, $cidadeDestino);
        if ($comarcaDestino !== null) {
            $comarcaDestinoId = (string) $comarcaDestino['id'];
        } else {
            $comarcaDestinoId = $this->comarcasRepository->ensureComarca($tribunalDestinoId, $ufDestino, $cidadeDestino);
        }

        $finalidade = trim((string) ($payload['finalidade'] ?? ''));
        if ($finalidade === '') {
            throw new InvalidArgumentException('A finalidade é obrigatória.');
        }
        $prazoDias = (int) $this->sanitizePrazo((string) ($payload['prazo'] ?? $payload['prazo_dias'] ?? '30'));
        $idCarta = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CPE',
            30
        );

        $row = [
            'id' => $idCarta,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'finalidade' => $finalidade,
            'prazo_dias' => $prazoDias,
            'comarca_destino_id' => $comarcaDestinoId,
            'cidade_destino' => $cidadeDestino,
            'uf_destino' => $ufDestino,
            'numero_processo_destino' => null,
            'vara_destino_id' => null,
            'vara_destino_nome' => null,
            'status' => 'em_andamento',
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }
        if ($idempotencyKey !== '') {
            $row['idempotency_key'] = $idempotencyKey;
            $row['idempotency_payload_hash'] = $payloadHash;
        }

        $this->repository->insert($row);

        $this->subtarefasRepository->create(
            $idCarta,
            CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
            CartasExpedidasSubtarefasRepository::ORDEM_ENVIO,
            'em_andamento',
            null
        );
        $this->subtarefasRepository->create(
            $idCarta,
            CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO,
            CartasExpedidasSubtarefasRepository::ORDEM_DEVOLUCAO,
            'pendente',
            null
        );

        return $idCarta;
        });
        } catch (PDOException $exception) {
            if ($idempotencyKey === '' || !self::isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
            // Uma requisição concorrente pode ter confirmado a mesma chave
            // enquanto esta transação era revertida. FOR UPDATE faz leitura
            // atual mesmo se o chamador já estiver numa transação RR.
            $existing = $this->repository->findByIdempotencyKey($usuarioCadastroId, $idempotencyKey, true);
            if ($existing === null) {
                throw $exception;
            }
            return self::resolveIdempotentResult($existing, (string) $payloadHash);
        }
    }

    private static function hashCreatePayload(array $payload, string $processDigits, string $userId): string
    {
        $canonical = [
            'version' => 1,
            'user_id' => $userId,
            'process_digits' => $processDigits,
            'finalidade' => trim((string) ($payload['finalidade'] ?? '')),
            'prazo' => (int) preg_replace('/\D+/', '', (string) ($payload['prazo'] ?? $payload['prazo_dias'] ?? '30')),
            'uf' => strtoupper(trim((string) ($payload['estado'] ?? $payload['uf'] ?? ''))),
            'cidade' => TextFormatter::formatProperName((string) ($payload['cidade'] ?? ''), 120),
            'classe_codigo' => trim((string) ($payload['classe_codigo'] ?? '')),
            'classe_descricao' => trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? '')),
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
            'sigilo_solicitado' => !empty($payload['nivel_sigilo']) ? 1 : 0,
        ];
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array{id:string,idempotency_payload_hash:?string} $existing */
    private static function resolveIdempotentResult(array $existing, string $payloadHash): string
    {
        $storedHash = trim((string) ($existing['idempotency_payload_hash'] ?? ''));
        if ($storedHash === '' || !hash_equals($storedHash, $payloadHash)) {
            throw new DomainException('Esta chave de envio já foi usada com outros dados. Atualize a página antes de tentar novamente.');
        }
        return (string) $existing['id'];
    }

    private static function isUniqueConstraintViolation(PDOException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        return $exception->getCode() === '23000' && (int) $driverCode === 1062;
    }

    private function withAtomicCreate(callable $operation): string
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = 'cartas_expedidas_create_' . bin2hex(random_bytes(8));
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $id = $operation();
            if ($ownsTransaction) {
                $this->connection->commit();
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $id;
        } catch (Throwable $exception) {
            try {
                if ($ownsTransaction && $this->connection->inTransaction()) {
                    $this->connection->rollBack();
                } elseif (!$ownsTransaction && $this->connection->inTransaction()) {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $rollbackException) {
                throw new RuntimeException('Falha ao reverter a criação da carta expedida.', 0, $rollbackException);
            }
            throw $exception;
        }
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);

        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $cartaId = trim((string) ($subtarefa['carta_expedida_id'] ?? ''));
        $tipoSubtarefaAtual = trim((string) ($subtarefa['tipo'] ?? ''));

        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        if (!CartasExpedidasSubtarefasRepository::isOrdemAtiva($ordem)
            || !CartasExpedidasSubtarefasRepository::isTipoAtivo($tipoSubtarefaAtual)) {
            throw new InvalidArgumentException('Subtarefa inválida para o fluxo atual. Atualize a página.');
        }

        $this->subtarefasRepository->marcarConcluidaComDados($subtarefaId, $dadosJson);

        $proximaOrdem = $this->subtarefasRepository->obterProximaOrdem($ordem);
        if ($proximaOrdem !== null) {
            $proximoTipo = $this->subtarefasRepository->obterProximoTipo($ordem);
            $existente = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, $proximaOrdem);
            if ($existente === null) {
                $this->subtarefasRepository->create($cartaId, $proximoTipo, $proximaOrdem, 'pendente', null);
            } else {
                $statusProx = trim((string) ($existente['status'] ?? ''));
                if ($statusProx !== 'concluida') {
                    $this->subtarefasRepository->atualizarStatus((string) ($existente['id'] ?? ''), 'pendente');
                }
            }
            $this->repository->update($cartaId, ['status' => 'aguardando cumprimento']);
            return ['tarefa_principal_concluida' => false];
        }

        $this->repository->update($cartaId, ['status' => 'concluída']);
        $carta = $this->repository->find($cartaId);
        $processo = trim((string) ($carta['numero_processo_origem'] ?? ''));
        $finalidade = trim((string) ($carta['finalidade'] ?? ''));
        $cidade = trim((string) ($carta['cidade_destino'] ?? ''));
        $uf = trim((string) ($carta['uf_destino'] ?? ''));
        $msg = 'Tarefa principal concluída automaticamente: carta expedida do processo '
            . ($processo !== '' ? $processo : 'não informado')
            . ' (' . ($finalidade !== '' ? $finalidade : 'finalidade não informada')
            . ' para ' . (($cidade !== '' ? $cidade : 'comarca não informada') . ($uf !== '' ? '/' . $uf : '')) . ').';

        return [
            'tarefa_principal_concluida' => true,
            'tarefa_principal_mensagem' => $msg,
        ];
    }

    private function normalizeTribunalSigla(string $sigla): string
    {
        $s = strtoupper(preg_replace('/\s+/', '', $sigla));
        return $s !== '' ? $s : 'TJBA';
    }

    private function tribunalSiglaToUf(string $sigla): string
    {
        if (preg_match('/^(TJ|TRF)([A-Z]{2})$/', $sigla, $m)) {
            return $m[2];
        }
        if (strlen($sigla) >= 2) {
            return substr($sigla, -2);
        }
        return 'BA';
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
            if ($ts > 2147483647 || $ts < -2147483648) {
                return null;
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

    private function normalizeNomeCidade(string $nome): string
    {
        $n = trim($nome);
        $n = mb_strtolower($n, 'UTF-8');
        $n = preg_replace('/[àáâãäå]/u', 'a', $n);
        $n = preg_replace('/[èéêë]/u', 'e', $n);
        $n = preg_replace('/[ìíîï]/u', 'i', $n);
        $n = preg_replace('/[òóôõö]/u', 'o', $n);
        $n = preg_replace('/[ùúûü]/u', 'u', $n);
        $n = preg_replace('/[ç]/u', 'c', $n);
        return $n;
    }

    private function normalizeClasseLabel(string $valor): string
    {
        return $this->normalizeNomeCidade($valor);
    }

    private function sanitizePrazo(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor);
        if ($digits === '') {
            return '30';
        }
        return substr($digits, 0, 3);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
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

        return $this->repository->update($id, $payload);
    }

    /**
     * Altera apenas o status da tarefa principal para "cancelada". Não exclui tarefa nem subtarefas.
     */
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

    /**
     * Cria as subtarefas iniciais (envio + devolução pendente) quando a carta ainda não possui nenhuma.
     *
     * @throws InvalidArgumentException Se a carta não existir ou já possuir subtarefas.
     */
    public function criarPrimeiraSubtarefa(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            throw new InvalidArgumentException('Identificador da carta é obrigatório.');
        }
        if ($this->repository->find($cartaId) === null) {
            throw new InvalidArgumentException('Carta expedida não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCartaId($cartaId);
        if (count($existentes) > 0) {
            throw new InvalidArgumentException('Esta carta já possui subtarefas.');
        }
        $this->subtarefasRepository->create(
            $cartaId,
            CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
            CartasExpedidasSubtarefasRepository::ORDEM_ENVIO,
            'em_andamento',
            null
        );
        $this->subtarefasRepository->create(
            $cartaId,
            CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO,
            CartasExpedidasSubtarefasRepository::ORDEM_DEVOLUCAO,
            'pendente',
            null
        );
        $this->repository->update($cartaId, ['status' => 'em_andamento']);
    }
}
