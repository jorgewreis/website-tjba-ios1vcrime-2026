<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CejuscRegistrosRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/ProcessoPrioridadesService.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/ProcessoDataJudSyncQueue.php';

final class CejuscRegistroDuplicadoException extends RuntimeException
{
    public function __construct(private array $duplicate)
    {
        parent::__construct('Já existe cadastro CEJUSC para este processo e este réu.');
    }

    public function duplicate(): array
    {
        return $this->duplicate;
    }
}

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

    public function create(array $payload, string $actorId): string
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Usuário autenticado obrigatório.');
        }
        $data = $this->validate($payload);
        $data['usuario_id'] = $actorId;
        if (empty($data['force_duplicate'])) {
            $duplicado = $this->repository->findDuplicateByProcessoAndParte($data['numero_processo'], $data['nome_parte']);
            if ($duplicado !== null) {
                throw new CejuscRegistroDuplicadoException($duplicado);
            }
        }
        $this->connection->beginTransaction();
        try {
            $data['nome_parte'] = $this->ensureParteVinculada($data, true);
            $id = $this->repository->insert($data);
            $this->connection->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $error;
        }
    }

    public function update(string $id, array $payload, string $actorId): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Identificador do registro é obrigatório.');
        }
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Usuário autenticado obrigatório.');
        }
        $data = $this->validate($payload);
        $data['usuario_id'] = $actorId;
        $this->connection->beginTransaction();
        try {
            if ($this->repository->findForUpdate($id) === null) {
                $this->connection->rollBack();
                return false;
            }
            $data['nome_parte'] = $this->ensureParteVinculada($data, false);
            if (!$this->repository->update($id, $data)) {
                throw new RuntimeException('Não foi possível atualizar o registro CEJUSC.');
            }
            $this->connection->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $error;
        }
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
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        $parte = trim((string) ($payload['nome_parte'] ?? $payload['parte'] ?? ''));
        $parteId = trim((string) ($payload['parte_id'] ?? ''));
        $data = trim((string) ($payload['data_envio_cejusc'] ?? $payload['data_envio'] ?? ''));
        $forceDuplicate = !empty($payload['force_duplicate']);
        if ($processo === '') throw new InvalidArgumentException('O número do processo é obrigatório.');
        if ($parte === '') throw new InvalidArgumentException('O nome da parte é obrigatório.');
        if (mb_strlen($parte) > 180) throw new InvalidArgumentException('O nome da parte deve ter no máximo 180 caracteres.');
        if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new InvalidArgumentException('A data de envio é obrigatória e deve ser válida.');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $data));
        if (!checkdate($month, $day, $year)) throw new InvalidArgumentException('A data de envio é inválida.');
        $digits = CnjValidator::digits20OrThrow($processo);
        $processo = sprintf('%s-%s.%s.%s.%s.%s', substr($digits, 0, 7), substr($digits, 7, 2), substr($digits, 9, 4), substr($digits, 13, 1), substr($digits, 14, 2), substr($digits, 16, 4));
        if (mb_strlen($classeCodigo) > 30) throw new InvalidArgumentException('O código da classe processual é inválido.');
        if (mb_strlen($classeDescricao) > 255) throw new InvalidArgumentException('A descrição da classe processual é inválida.');
        return ['numero_processo' => $processo, 'classe_codigo' => $classeCodigo !== '' ? $classeCodigo : null, 'classe_descricao' => $classeDescricao !== '' ? $classeDescricao : null, 'nome_parte' => $parte, 'parte_id' => $parteId, 'data_envio_cejusc' => $data, 'force_duplicate' => $forceDuplicate];
    }

    private function ensureParteVinculada(array $data, bool $garantirPrioridadeCejusc): string
    {
        $processos = new ProcessosRepository($this->connection);
        $processoId = $processos->ensureProcess($data['numero_processo']);
        ProcessoDataJudSyncQueue::enqueueBestEffort($this->connection, $processoId);
        if ($garantirPrioridadeCejusc) {
            (new ProcessoPrioridadesService($this->connection))->registrarViolenciaDomesticaCejusc($processoId);
        }
        $partes = new PartesRepository($this->connection);
        $parteId = trim((string) ($data['parte_id'] ?? ''));
        if ($parteId !== '') {
            $parte = $partes->findById($parteId);
            if ($parte === null) {
                throw new InvalidArgumentException('A parte selecionada não foi encontrada. Selecione novamente o réu.');
            }
        } else {
            $parteId = $partes->ensureParteByNomeOuCpf($data['nome_parte']);
            $parte = $partes->findById($parteId);
        }
        if ($parte === null) {
            throw new InvalidArgumentException('Não foi possível confirmar o cadastro da parte.');
        }

        $vinculos = new ProcessosPartesRepository($this->connection);
        $vinculoExistente = $vinculos->findByProcessoAndParte($processoId, $parteId);
        if (($data['parte_id'] ?? '') !== '' && $vinculoExistente === null) {
            throw new InvalidArgumentException('A parte selecionada não está vinculada ao processo informado. Consulte o processo novamente.');
        }
        if ($vinculoExistente === null) {
            $vinculos->addParte($processoId, $parteId, ProcessosPartesRepository::TIPO_REU, ProcessosPartesRepository::POLO_PASSIVO);
        }
        return trim((string) ($parte['nome_completo'] ?? $data['nome_parte']));
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
