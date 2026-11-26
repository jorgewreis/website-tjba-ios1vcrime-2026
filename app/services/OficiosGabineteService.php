<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/OficiosGabineteRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/ProcessoDataJudSyncQueue.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';

final class OficiosGabineteService
{
    /** Valores do optgroup "Administrativos" em gabinete-oficios-list (processo opcional). */
    private const TIPOS_OFICIO_ADMINISTRATIVOS = [
        'Solicitando Afastamento',
        'Solicitando Licença Prêmio',
        'Solicitando Férias',
        'Solicitando Reembolso',
        'Outros',
    ];

    /** Exibido em `numero_processo` quando não há vínculo com processo judicial. */
    private const NUMERO_PROCESSO_SEM_VINCULO = '—';

    private PDO $connection;
    private bool $sequenceLockUncertain = false;
    private OficiosGabineteRepository $repository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new OficiosGabineteRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    public function listOficios(array $filters, int $limit, int $offset): array
    {
        return [
            'rows' => $this->repository->fetchList($filters, $limit, $offset),
            'total' => $this->repository->count($filters),
        ];
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    /**
     * @return list<int>
     */
    public function listAnosComOficios(): array
    {
        return $this->repository->fetchDistinctAnos();
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload): string
    {
        $tipoOficioRaw = trim((string) ($payload['tipo_oficio'] ?? ''));
        if ($tipoOficioRaw === '') {
            throw new InvalidArgumentException('O tipo de ofício é obrigatório.');
        }
        $esAdministrativo = $this->isTipoOficioAdministrativo($tipoOficioRaw);

        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if (!$esAdministrativo && $numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $ano = (int) date('Y');
        return $this->withAnnualSequence($ano, fn (): string => $this->createInSequence($payload, $tipoOficioRaw, $numeroProcesso, $ano));
    }

    private function createInSequence(array $payload, string $tipoOficioRaw, string $numeroProcesso, int $ano): string
    {
        $processoOrigemId = null;
        $numeroFormatado = self::NUMERO_PROCESSO_SEM_VINCULO;

        if ($numeroProcesso !== '') {
            $digits = preg_replace('/\D+/', '', $numeroProcesso);
            if (strlen($digits) !== 20) {
                throw new InvalidArgumentException('Número do processo deve ter 20 dígitos (formato CNJ).');
            }
            if (!CnjValidator::isValid($digits)) {
                throw new InvalidArgumentException('Número do processo inválido. Verifique o dígito verificador.');
            }
            $numeroFormatado = $this->formatarCNJ($digits);

            $classeId = null;
            $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
            $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
            if ($classeCodigo !== '') {
                $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
                $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
            }
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, null);
            ProcessoDataJudSyncQueue::enqueueBestEffort($this->connection, $processoOrigemId);
        }

        $tipoOficio = TextFormatter::formatProperName($tipoOficioRaw, 255);

        $criadoPor = trim((string) ($payload['criado_por'] ?? ''));
        $criadoPor = $criadoPor !== '' ? $criadoPor : null;

        $seq = $this->repository->nextSequencialForYear($ano);
        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            'oficios_gabinete',
            'id',
            'OFG',
            30
        );
        $this->repository->insert([
            'id' => $id,
            'processo_id' => $processoOrigemId,
            'numero_processo' => $numeroFormatado,
            'ano' => $ano,
            'numero_sequencial' => $seq,
            'tipo_oficio' => $tipoOficio,
            'status' => 'finalizada',
            'criado_por' => $criadoPor,
            'atualizado_por' => $criadoPor,
        ]);
        return $id;
    }

    /**
     * @param list<string> $perfisUsuario perfis do usuário logado (validação de cancelamento)
     */
    public function cancel(string $id, ?string $atualizadoPor = null, array $perfisUsuario = []): bool
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $row = $this->repository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Ofício não encontrado.');
        }
        if (($row['status'] ?? '') === 'cancelada') {
            return true;
        }

        require_once __DIR__ . '/../helpers/OficioRegistroMetadataHelper.php';
        $podeCancelar = OficioRegistroMetadataHelper::podeCancelarGabinete(
            [
                'Status' => (string) ($row['status'] ?? ''),
                'Data' => (string) ($row['created_at'] ?? ''),
                'CriadoPor' => (string) ($row['criado_por'] ?? ''),
            ],
            [
                'usuario_id' => trim((string) ($atualizadoPor ?? '')),
                'perfis' => $perfisUsuario,
            ]
        );
        if (!$podeCancelar) {
            throw new InvalidArgumentException(
                'Não é possível cancelar este ofício. Verifique o prazo de 3 dias e se você é o autor ou possui perfil ADMIN/CHEFIA.'
            );
        }

        return $this->repository->updateStatus($id, 'cancelada', $atualizadoPor);
    }

    public function delete(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $row = $this->repository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Ofício não encontrado.');
        }
        $ano = (int) ($row['ano'] ?? 0);
        return $this->withAnnualSequence($ano, function () use ($id, $ano): array {
            // O primeiro SELECT serve só para descobrir o ano. Releia a
            // existência e o sequencial depois de adquirir o lock.
            $row = $this->repository->findById($id);
            if ($row === null || (int) $row['ano'] !== $ano) {
                throw new InvalidArgumentException('Ofício não encontrado.');
            }
            $max = $this->repository->getMaxSequencialForYear($ano);
            if ($max === 0 || (int) $row['numero_sequencial'] !== $max) {
                throw new InvalidArgumentException('Só é possível excluir o último ofício criado no ano (maior número sequencial).');
            }
            if (!$this->repository->delete($id)) {
                throw new RuntimeException('Falha ao excluir o ofício do Gabinete.');
            }
            return ['deleted' => true, 'message' => 'Ofício excluído com sucesso.'];
        });
    }

    private function withAnnualSequence(int $ano, callable $operation): mixed
    {
        if ($this->sequenceLockUncertain || $this->connection->inTransaction()) {
            throw new LogicException('O sequencial de ofícios exige transação própria.');
        }
        $lockName = null;
        if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $db = $this->connection->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($db) || $db === '') {
                throw new RuntimeException('Banco indisponível para reservar o sequencial de ofícios.');
            }
            $name = 'fokus_ofg_' . substr(hash('sha256', strtolower($db) . ':' . $ano), 0, 48);
            $lock = $this->connection->prepare('SELECT GET_LOCK(?, 5)');
            $lock->execute([$name]);
            $acquired = (int) $lock->fetchColumn() === 1;
            $lock->closeCursor();
            if (!$acquired) {
                throw new RuntimeException('O sequencial de ofícios está ocupado; tente novamente.');
            }
            $lockName = $name;
        }
        try {
            if (!$this->connection->beginTransaction()) {
                throw new RuntimeException('Não foi possível iniciar a transação de ofícios.');
            }
            $result = $operation();
            if (!$this->connection->commit()) {
                throw new RuntimeException('Não foi possível confirmar a operação de ofícios.');
            }
            return $result;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $error;
        } finally {
            if ($lockName !== null) {
                try {
                    $release = $this->connection->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                    if ($release->fetchColumn() === false) throw new RuntimeException('Liberação não confirmada.');
                    $release->closeCursor();
                } catch (Throwable) {
                    $this->sequenceLockUncertain = true;
                    error_log('Ofícios do Gabinete: liberação do sequencial não confirmada; sessão não persistente.');
                }
            }
        }
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

    private function isTipoOficioAdministrativo(string $tipoOficioRaw): bool
    {
        return in_array($tipoOficioRaw, self::TIPOS_OFICIO_ADMINISTRATIVOS, true);
    }
}
