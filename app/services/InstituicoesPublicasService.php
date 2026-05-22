<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/InstituicoesPublicasRepository.php';
require_once __DIR__ . '/../repositories/InstituicoesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/InstituicoesSetoresRepository.php';

final class InstituicoesPublicasService
{
    private PDO $connection;
    private InstituicoesPublicasRepository $repository;
    private InstituicoesTelefonesRepository $telefonesRepository;
    private InstituicoesSetoresRepository $setoresRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new InstituicoesPublicasRepository($connection);
        $this->telefonesRepository = new InstituicoesTelefonesRepository($connection);
        $this->setoresRepository = new InstituicoesSetoresRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
    }

    public function find(string $id): ?array
    {
        $instituicao = $this->repository->findById($id);
        if ($instituicao === null) {
            return null;
        }
        $instituicao['telefones'] = $this->telefonesRepository->findByInstituicaoId($id);
        $instituicao['setores'] = $this->setoresRepository->findByInstituicaoId($id);
        return $instituicao;
    }

    public function create(array $payload): string
    {
        $this->connection->beginTransaction();
        try {
            $id = $this->repository->insert([
                'nome' => $payload['nome'] ?? '',
                'nome_fantasia' => $payload['nome_fantasia'] ?? null,
                'cnpj' => $payload['cnpj'] ?? null,
                'observacoes' => $payload['observacoes'] ?? null,
                'ativo' => $payload['ativo'] ?? 1,
            ]);
            $this->connection->commit();
            return $id;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function update(string $id, array $payload): bool
    {
        if ($this->repository->findById($id) === null) {
            return false;
        }
        $allowed = ['nome', 'nome_fantasia', 'cnpj', 'observacoes', 'ativo'];
        $data = array_intersect_key($payload, array_flip($allowed));
        if ($data === []) {
            return true;
        }
        return $this->repository->update($id, $data);
    }

    public function addTelefone(string $instituicaoId, array $row): string
    {
        $row['instituicao_id'] = $instituicaoId;
        return $this->telefonesRepository->insert($row);
    }

    public function updateTelefone(string $id, array $payload): bool
    {
        return $this->telefonesRepository->update($id, $payload);
    }

    public function deleteTelefone(string $id): bool
    {
        return $this->telefonesRepository->delete($id);
    }

    public function addSetor(string $instituicaoId, array $row): string
    {
        $row['instituicao_id'] = $instituicaoId;
        return $this->setoresRepository->insert($row);
    }

    public function updateSetor(string $id, array $payload): bool
    {
        return $this->setoresRepository->update($id, $payload);
    }

    public function deleteSetor(string $id): bool
    {
        return $this->setoresRepository->delete($id);
    }
}
