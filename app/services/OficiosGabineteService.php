<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/OficiosGabineteRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class OficiosGabineteService
{
    private PDO $connection;
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
        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }
        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos (formato CNJ).');
        }
        $numeroFormatado = $this->formatarCNJ($digits);

        $tipoOficio = trim((string) ($payload['tipo_oficio'] ?? ''));
        if ($tipoOficio === '') {
            throw new InvalidArgumentException('O tipo de ofício é obrigatório.');
        }
        $tipoOficio = TextFormatter::formatProperName($tipoOficio, 255);

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }
        $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);

        $ano = (int) date('Y');

        $criadoPor = trim((string) ($payload['criado_por'] ?? ''));
        $criadoPor = $criadoPor !== '' ? $criadoPor : null;

        $this->connection->beginTransaction();
        try {
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
            ]);
            $this->connection->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function cancel(string $id): bool
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
        return $this->repository->updateStatus($id, 'cancelada');
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
        $seq = (int) ($row['numero_sequencial'] ?? 0);
        $max = $this->repository->getMaxSequencialForYear($ano);
        if ($seq !== $max || $max === 0) {
            throw new InvalidArgumentException('Só é possível excluir o último ofício criado no ano (maior número sequencial).');
        }
        $deleted = $this->repository->delete($id);
        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Ofício excluído com sucesso.' : 'Nenhum registro removido.',
        ];
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
}
