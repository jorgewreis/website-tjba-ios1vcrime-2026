<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';

final class ConsultaProcessosService
{
    private PDO $connection;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private ClassesRepository $classesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->processosRepository = new ProcessosRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->processosRepository->fetchList($filters, $limit, $offset);
        $processoIds = [];
        foreach ($rows as $row) {
            $pid = trim((string) ($row['id'] ?? ''));
            if ($pid !== '') {
                $processoIds[] = $pid;
            }
        }
        $totalPartesByProcesso = $this->processosPartesRepository->countByProcessoIds($processoIds);
        foreach ($rows as $i => $row) {
            $processoId = (string) ($row['id'] ?? '');
            $rows[$i]['total_partes'] = $processoId !== '' ? (int) ($totalPartesByProcesso[$processoId] ?? 0) : 0;
            $rows[$i]['classe_nome'] = '';
            if (!empty($row['classe_id'])) {
                $classe = $this->classesRepository->findById((string) $row['classe_id']);
                $rows[$i]['classe_nome'] = $classe['nome'] ?? '';
            }
        }
        return [
            'rows' => $rows,
            'total' => $this->processosRepository->count($filters),
        ];
    }

    public function get(string $id): ?array
    {
        $processo = $this->processosRepository->findById($id);
        if ($processo === null) {
            return null;
        }
        $processo['partes'] = $this->processosPartesRepository->findByProcessoId($id);
        $processo['classe_nome'] = '';
        if (!empty($processo['classe_id'])) {
            $classe = $this->classesRepository->findById((string) $processo['classe_id']);
            $processo['classe_nome'] = $classe['nome'] ?? '';
        }
        return $processo;
    }
}
