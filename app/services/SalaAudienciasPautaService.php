<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasTiposRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';

final class SalaAudienciasPautaService
{
    private PDO $connection;
    private SalaAudienciasPautaRepository $pautaRepository;
    private SalaAudienciasPautaParteRepository $parteRepository;
    private SalaAudienciasTiposRepository $tiposRepository;
    private ProcessosRepository $processosRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->pautaRepository = new SalaAudienciasPautaRepository($connection);
        $this->parteRepository = new SalaAudienciasPautaParteRepository($connection);
        $this->tiposRepository = new SalaAudienciasTiposRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
    }

    public function listTiposAtivos(): array
    {
        return $this->tiposRepository->listAtivos();
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        return [
            'rows' => $this->pautaRepository->fetchList($filters, $limit, $offset),
            'total' => $this->pautaRepository->count($filters),
            'statusCounts' => $this->pautaRepository->countByStatus($filters),
        ];
    }

    public function find(string $id): ?array
    {
        return $this->pautaRepository->findById($id);
    }

    public function findParte(string $idParte): ?array
    {
        return $this->parteRepository->findById($idParte);
    }

    public function listPartes(string $idPauta): array
    {
        return $this->parteRepository->listByPauta($idPauta);
    }

    public function create(array $payload, ?string $usuarioId): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('Número do processo é obrigatório.');
        }
        $tipo = trim((string) ($payload['tipo_audiencia'] ?? ''));
        $data = trim((string) ($payload['data_audiencia'] ?? ''));
        $hora = trim((string) ($payload['hora_audiencia'] ?? ''));
        $local = trim((string) ($payload['localizacao'] ?? ''));
        if ($tipo === '' || $data === '' || $hora === '' || $local === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da audiência.');
        }

        $processoId = $this->processosRepository->ensureProcess($numeroProcesso, null, null, false);
        $ano = (int) date('Y');
        $statusIn = trim((string) ($payload['status'] ?? 'agendada'));
        $status = $statusIn === 'designada' ? 'agendada' : $statusIn;
        if (!in_array($status, ['agendada', 'realizada', 'adiada', 'cancelada'], true)) {
            $status = 'agendada';
        }

        $this->connection->beginTransaction();
        try {
            $seq = $this->pautaRepository->nextSequencialForYear($ano);
            $id = $this->pautaRepository->insert([
                'numero_sequencial' => $seq,
                'ano' => $ano,
                'processo' => $processoId,
                'tipo_audiencia' => $tipo,
                'data_audiencia' => $data,
                'hora_audiencia' => $hora,
                'localizacao' => $local,
                'status' => $status,
                'observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                'criado_por' => $usuarioId,
                'atualizado_por' => $usuarioId,
            ]);
            $this->connection->commit();
            return $id;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function update(string $id, array $payload, ?string $usuarioId): bool
    {
        $tipo = trim((string) ($payload['tipo_audiencia'] ?? ''));
        $data = trim((string) ($payload['data_audiencia'] ?? ''));
        $hora = trim((string) ($payload['hora_audiencia'] ?? ''));
        $local = trim((string) ($payload['localizacao'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'agendada'));
        if ($tipo === '' || $data === '' || $hora === '' || $local === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da audiência.');
        }
        if (!in_array($status, ['agendada', 'realizada', 'adiada', 'cancelada'], true)) {
            $status = 'agendada';
        }
        return $this->pautaRepository->update($id, [
            'tipo_audiencia' => $tipo,
            'data_audiencia' => $data,
            'hora_audiencia' => $hora,
            'localizacao' => $local,
            'status' => $status,
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
            'atualizado_por' => $usuarioId,
        ]);
    }

    public function cancel(string $id, ?string $usuarioId): bool
    {
        return $this->pautaRepository->setStatus($id, 'cancelada', $usuarioId);
    }

    public function delete(string $id, ?string $usuarioId): bool
    {
        return $this->pautaRepository->softDelete($id, $usuarioId);
    }

    public function addParte(string $idPauta, array $payload, ?string $usuarioId): string
    {
        $nome = trim((string) ($payload['nome_parte'] ?? ''));
        $tipo = trim((string) ($payload['tipo_parte'] ?? ''));
        $maioridade = trim((string) ($payload['maioridade'] ?? ''));
        $expedicao = trim((string) ($payload['expedicao'] ?? 'Não expedido'));
        $cumprimento = trim((string) ($payload['cumprimento'] ?? 'pendente'));
        if ($nome === '' || $tipo === '' || $maioridade === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da parte.');
        }
        return $this->parteRepository->insert([
            'pauta' => $idPauta,
            'nome_parte' => $nome,
            'tipo_parte' => $tipo,
            'maioridade' => $maioridade,
            'expedicao' => $expedicao,
            'cumprimento' => $cumprimento,
            'id_documento_expedido' => trim((string) ($payload['id_documento_expedido'] ?? '')) ?: null,
            'id_documento_cumprido' => trim((string) ($payload['id_documento_cumprido'] ?? '')) ?: null,
            'criado_por' => $usuarioId,
            'atualizado_por' => $usuarioId,
        ]);
    }

    public function updateParte(string $idParte, array $payload, ?string $usuarioId): bool
    {
        $nome = trim((string) ($payload['nome_parte'] ?? ''));
        $tipo = trim((string) ($payload['tipo_parte'] ?? ''));
        $maioridade = trim((string) ($payload['maioridade'] ?? ''));
        $expedicao = trim((string) ($payload['expedicao'] ?? ''));
        $cumprimento = trim((string) ($payload['cumprimento'] ?? ''));
        if ($nome === '' || $tipo === '' || $maioridade === '' || $expedicao === '' || $cumprimento === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da parte.');
        }
        return $this->parteRepository->update($idParte, [
            'nome_parte' => $nome,
            'tipo_parte' => $tipo,
            'maioridade' => $maioridade,
            'expedicao' => $expedicao,
            'cumprimento' => $cumprimento,
            'id_documento_expedido' => trim((string) ($payload['id_documento_expedido'] ?? '')) ?: null,
            'id_documento_cumprido' => trim((string) ($payload['id_documento_cumprido'] ?? '')) ?: null,
            'atualizado_por' => $usuarioId,
        ]);
    }

    public function deleteParte(string $idParte, ?string $usuarioId): bool
    {
        return $this->parteRepository->softDelete($idParte, $usuarioId);
    }
}

