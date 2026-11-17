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
        $id = trim($id);
        return $this->operacaoComLock(function () use ($id, $payload): bool {
            if ($this->repository->findByIdForUpdate($id) === null) {
                return false;
            }

            $allowed = ['nome', 'nome_fantasia', 'cnpj', 'observacoes', 'ativo'];
            $data = array_intersect_key($payload, array_flip($allowed));
            if ($data === []) {
                return true;
            }

            return $this->repository->update($id, $data);
        });
    }

    private function operacaoComLock(callable $operation): mixed
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = null;

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        } else {
            $savepoint = 'instituicao_update_' . bin2hex(random_bytes(8));
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->connection->commit();
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $error) {
            try {
                if ($ownsTransaction) {
                    if ($this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                } elseif ($this->connection->inTransaction()) {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable $rollbackError) {
                error_log('Instituições públicas: falha ao reverter atualização (' . get_class($rollbackError) . ').');
                throw new RuntimeException(
                    'Falha ao reverter atualização; o estado da transação pode estar inválido.',
                    0,
                    $error
                );
            }
            throw $error;
        }
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
