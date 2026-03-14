<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/PartesEnderecosRepository.php';
require_once __DIR__ . '/../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';

final class PessoasService
{
    private PDO $connection;
    private PartesRepository $partesRepository;
    private PartesTelefonesRepository $telefonesRepository;
    private PartesEnderecosRepository $enderecosRepository;
    private PartesInstituicoesRepository $partesInstituicoesRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->partesRepository = new PartesRepository($connection);
        $this->telefonesRepository = new PartesTelefonesRepository($connection);
        $this->enderecosRepository = new PartesEnderecosRepository($connection);
        $this->partesInstituicoesRepository = new PartesInstituicoesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->partesRepository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $parteId = (string) ($row['id'] ?? '');
            $rows[$i]['total_processos'] = $parteId !== '' ? count($this->processosPartesRepository->findByParteId($parteId)) : 0;
            $vinculosInst = $parteId !== '' ? $this->partesInstituicoesRepository->findByParteId($parteId, true) : [];
            $rows[$i]['total_instituicoes'] = count($vinculosInst);
            $rows[$i]['telefones'] = $parteId !== '' ? $this->telefonesRepository->findByParteId($parteId) : [];
            $rows[$i]['instituicoes'] = array_map(function (array $v): array {
                return [
                    'nome' => $v['instituicao_nome'] ?? '',
                    'sigla' => $v['instituicao_sigla'] ?? '',
                    'cargo_funcao' => $v['cargo_funcao'] ?? '',
                ];
            }, $vinculosInst);
        }
        return [
            'rows' => $rows,
            'total' => $this->partesRepository->count($filters),
        ];
    }

    public function find(string $id): ?array
    {
        $parte = $this->partesRepository->findById($id);
        if ($parte === null) {
            return null;
        }
        $parte['telefones'] = $this->telefonesRepository->findByParteId($id);
        $parte['enderecos'] = $this->enderecosRepository->findByParteId($id);
        $parte['vinculos_instituicoes'] = $this->partesInstituicoesRepository->findByParteId($id, null);
        $parte['vinculos_processos'] = $this->processosPartesRepository->findByParteId($id);
        return $parte;
    }

    public function create(array $payload): string
    {
        $this->connection->beginTransaction();
        try {
            $id = $this->partesRepository->insert([
                'cpf' => $payload['cpf'] ?? '',
                'nome_completo' => $payload['nome_completo'] ?? '',
                'sexo' => $payload['sexo'] ?? '',
                'data_nascimento' => $payload['data_nascimento'] ?? '',
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
        if ($this->partesRepository->findById($id) === null) {
            return false;
        }
        $allowed = ['cpf', 'nome_completo', 'sexo', 'data_nascimento'];
        $data = array_intersect_key($payload, array_flip($allowed));
        if ($data === []) {
            return true;
        }
        return $this->partesRepository->update($id, $data);
    }

    public function addTelefone(string $parteId, array $row): string
    {
        $row['parte_id'] = $parteId;
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

    public function addEndereco(string $parteId, array $row): string
    {
        $row['parte_id'] = $parteId;
        return $this->enderecosRepository->insert($row);
    }

    public function updateEndereco(string $id, array $payload): bool
    {
        return $this->enderecosRepository->update($id, $payload);
    }

    public function deleteEndereco(string $id): bool
    {
        return $this->enderecosRepository->delete($id);
    }

    public function addVinculoInstituicao(string $parteId, array $row): string
    {
        $row['parte_id'] = $parteId;
        return $this->partesInstituicoesRepository->insert($row);
    }

    public function updateVinculoInstituicao(string $id, array $payload): bool
    {
        return $this->partesInstituicoesRepository->update($id, $payload);
    }

    public function deleteVinculoInstituicao(string $id): bool
    {
        return $this->partesInstituicoesRepository->delete($id);
    }
}
