<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CejuscRegistrosRepository.php';

final class CejuscRegistrosService
{
    public function __construct(private PDO $connection)
    {
        $this->repository = new CejuscRegistrosRepository($connection);
    }

    private CejuscRegistrosRepository $repository;

    public function list(array $filters, int $limit, int $offset): array
    {
        $filters = $this->normalizeFilters($filters);
        return ['rows' => $this->repository->list($filters, $limit, $offset), 'total' => $this->repository->count($filters)];
    }

    public function listAll(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $total = $this->repository->count($normalized);
        return $total > 0 ? $this->repository->list($normalized, $total, 0) : [];
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $payload): string
    {
        return $this->repository->insert($this->validate($payload));
    }

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Identificador do registro é obrigatório.');
        }
        return $this->repository->update($id, $this->validate($payload));
    }

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Identificador do registro é obrigatório.');
        }
        return $this->repository->delete($id);
    }

    private function validate(array $payload): array
    {
        $processo = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        $parte = trim((string) ($payload['nome_parte'] ?? $payload['parte'] ?? ''));
        $data = trim((string) ($payload['data_envio_cejusc'] ?? $payload['data_envio'] ?? ''));
        if ($processo === '') throw new InvalidArgumentException('O número do processo é obrigatório.');
        if ($parte === '') throw new InvalidArgumentException('O nome da parte é obrigatório.');
        if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new InvalidArgumentException('A data de envio é obrigatória e deve ser válida.');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $data));
        if (!checkdate($month, $day, $year)) throw new InvalidArgumentException('A data de envio é inválida.');
        $digits = preg_replace('/\D+/', '', $processo) ?? '';
        if (strlen($digits) === 20) {
            $processo = sprintf('%s-%s.%s.%s.%s.%s', substr($digits, 0, 7), substr($digits, 7, 2), substr($digits, 9, 4), substr($digits, 13, 1), substr($digits, 14, 2), substr($digits, 16, 4));
        }
        return ['numero_processo' => $processo, 'nome_parte' => $parte, 'data_envio_cejusc' => $data, 'usuario_id' => trim((string) ($payload['usuario_id'] ?? $_SESSION['id_usuario'] ?? ''))];
    }

    private function normalizeFilters(array $filters): array
    {
        $month = trim((string) ($filters['mes_envio'] ?? ''));
        if ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = '';
        }
        return ['q' => trim((string) ($filters['q'] ?? '')), 'mes_envio' => $month];
    }
}
