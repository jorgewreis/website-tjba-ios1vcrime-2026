<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/ProcessoAssuntosRepository.php';
require_once __DIR__ . '/../helpers/ProcessoBlocoListagemHelper.php';

final class ConsultaProcessosService
{
    private PDO $connection;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private ClassesRepository $classesRepository;
    private ProcessoAssuntosRepository $processoAssuntosRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->processosRepository = new ProcessosRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->processoAssuntosRepository = new ProcessoAssuntosRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->processosRepository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $processoId = (string) ($row['id'] ?? '');
            $rows[$i]['processo_id'] = $processoId;
            $rows[$i]['classe_nome'] = '';
            if (!empty($row['classe_id'])) {
                $classe = $this->classesRepository->findById((string) $row['classe_id']);
                $rows[$i]['classe_nome'] = $classe['nome'] ?? '';
            }
        }

        $rows = ProcessoBlocoListagemHelper::appendBlocoData($this->connection, $rows);

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
        $processo['assuntos'] = $this->processoAssuntosRepository->findByProcessoId($id);
        return $processo;
    }
}
