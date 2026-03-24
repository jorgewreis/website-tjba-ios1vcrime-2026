<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasTiposRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';

final class SalaAudienciasPautaService
{
    private PDO $connection;
    private SalaAudienciasPautaRepository $pautaRepository;
    private SalaAudienciasPautaParteRepository $parteRepository;
    private SalaAudienciasTiposRepository $tiposRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private PartesRepository $partesRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->pautaRepository = new SalaAudienciasPautaRepository($connection);
        $this->parteRepository = new SalaAudienciasPautaParteRepository($connection);
        $this->tiposRepository = new SalaAudienciasTiposRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
    }

    public function listTiposAtivos(): array
    {
        return $this->tiposRepository->listAtivos();
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->pautaRepository->fetchList($filters, $limit, $offset);
        $pautaIds = array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['id_pauta'] ?? ''));
        }, $rows), static function (string $id): bool {
            return $id !== '';
        }));
        $cumprimentosByPauta = $this->parteRepository->listCumprimentosByPautaIds($pautaIds);
        foreach ($rows as $idx => $row) {
            $idPauta = trim((string) ($row['id_pauta'] ?? ''));
            $rows[$idx]['partes_cumprimento'] = $idPauta !== '' ? ($cumprimentosByPauta[$idPauta] ?? []) : [];
        }

        return [
            'rows' => $rows,
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

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeDescricao !== '' || $classeCodigo !== '') {
            if ($classeCodigo === '') {
                $classeCodigo = strtoupper(substr(md5($classeDescricao), 0, 10));
            }
            $classeNome = $classeDescricao !== '' ? $classeDescricao : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }

        $processoId = $this->processosRepository->ensureProcess($numeroProcesso, $classeId, null, false);
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
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        $processoId = trim((string) ($pauta['processo'] ?? ''));
        if ($processoId === '') {
            throw new InvalidArgumentException('Processo vinculado à audiência é inválido.');
        }

        $nome = trim((string) ($payload['nome_parte'] ?? ''));
        $tipo = trim((string) ($payload['tipo_parte'] ?? ''));
        $maioridade = trim((string) ($payload['maioridade'] ?? ''));
        $expedicao = trim((string) ($payload['expedicao'] ?? 'Não expedido'));
        $cumprimento = trim((string) ($payload['cumprimento'] ?? 'pendente'));
        if ($nome === '' || $tipo === '' || $maioridade === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da parte.');
        }

        $this->connection->beginTransaction();
        try {
            $parteGlobalId = $this->partesRepository->ensureParteByNomeOuCpf($nome, null);
            [$tipoProcesso, $poloProcesso] = $this->mapPautaTipoToProcessoTipoEPolo($tipo);
            $processoParte = $this->processosPartesRepository->findByProcessoAndParte($processoId, $parteGlobalId);
            if ($processoParte === null) {
                $processoParteId = $this->processosPartesRepository->addParte($processoId, $parteGlobalId, $tipoProcesso, $poloProcesso);
            } else {
                $processoParteId = (string) ($processoParte['id'] ?? '');
                $this->processosPartesRepository->update($processoParteId, [
                    'tipo_parte' => $tipoProcesso,
                    'polo' => $poloProcesso,
                ]);
            }

            $idPartePauta = $this->parteRepository->insert([
                'pauta' => $idPauta,
                'parte_id_global' => $parteGlobalId,
                'processo_parte_id' => $processoParteId,
                'nome_parte' => $nome,
                'tipo_parte' => $tipo,
                'tipo_defensor' => trim((string) ($payload['tipo_defensor'] ?? '')) ?: null,
                'defensor_publico_id' => trim((string) ($payload['defensor_publico_id'] ?? '')) ?: null,
                'defensor_privado_id' => trim((string) ($payload['defensor_privado_id'] ?? '')) ?: null,
                'defensor_privado_outro' => trim((string) ($payload['defensor_privado_outro'] ?? '')) ?: null,
                'maioridade' => $maioridade,
                'expedicao' => $expedicao,
                'cumprimento' => $cumprimento,
                'id_documento_expedido' => trim((string) ($payload['id_documento_expedido'] ?? '')) ?: null,
                'id_documento_cumprido' => trim((string) ($payload['id_documento_cumprido'] ?? '')) ?: null,
                'criado_por' => $usuarioId,
                'atualizado_por' => $usuarioId,
            ]);

            $this->connection->commit();
            return $idPartePauta;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function updateParte(string $idParte, array $payload, ?string $usuarioId): bool
    {
        $parteAtual = $this->parteRepository->findById($idParte);
        if ($parteAtual === null) {
            throw new InvalidArgumentException('Parte da audiência não encontrada.');
        }

        $nome = trim((string) ($payload['nome_parte'] ?? ''));
        $tipo = trim((string) ($payload['tipo_parte'] ?? ''));
        $maioridade = trim((string) ($payload['maioridade'] ?? ''));
        $expedicao = trim((string) ($payload['expedicao'] ?? ''));
        $cumprimento = trim((string) ($payload['cumprimento'] ?? ''));
        if ($nome === '' || $tipo === '' || $maioridade === '' || $expedicao === '' || $cumprimento === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da parte.');
        }

        $this->connection->beginTransaction();
        try {
            $parteGlobalId = trim((string) ($payload['parte_id_global'] ?? ''));
            if ($parteGlobalId === '') {
                $parteGlobalId = trim((string) ($parteAtual['parte_id_global'] ?? ''));
            }
            $processoParteId = trim((string) ($payload['processo_parte_id'] ?? ''));
            if ($processoParteId === '') {
                $processoParteId = trim((string) ($parteAtual['processo_parte_id'] ?? ''));
            }
            if ($parteGlobalId === '') {
                $parteGlobalId = $this->partesRepository->ensureParteByNomeOuCpf($nome, null);
            } else {
                $this->partesRepository->update($parteGlobalId, ['nome_completo' => $nome]);
            }

            [$tipoProcesso, $poloProcesso] = $this->mapPautaTipoToProcessoTipoEPolo($tipo);
            if ($processoParteId !== '') {
                $this->processosPartesRepository->update($processoParteId, [
                    'tipo_parte' => $tipoProcesso,
                    'polo' => $poloProcesso,
                ]);
            } else {
                $processoId = trim((string) ($parteAtual['processo'] ?? ''));
                if ($processoId !== '') {
                    $vinculoAtual = $this->processosPartesRepository->findByProcessoAndParte($processoId, $parteGlobalId);
                    if ($vinculoAtual === null) {
                        $processoParteId = $this->processosPartesRepository->addParte($processoId, $parteGlobalId, $tipoProcesso, $poloProcesso);
                    } else {
                        $processoParteId = (string) ($vinculoAtual['id'] ?? '');
                        $this->processosPartesRepository->update($processoParteId, [
                            'tipo_parte' => $tipoProcesso,
                            'polo' => $poloProcesso,
                        ]);
                    }
                }
            }

            $ok = $this->parteRepository->update($idParte, [
                'parte_id_global' => $parteGlobalId,
                'processo_parte_id' => $processoParteId !== '' ? $processoParteId : null,
                'nome_parte' => $nome,
                'tipo_parte' => $tipo,
                'tipo_defensor' => trim((string) ($payload['tipo_defensor'] ?? '')) ?: null,
                'defensor_publico_id' => trim((string) ($payload['defensor_publico_id'] ?? '')) ?: null,
                'defensor_privado_id' => trim((string) ($payload['defensor_privado_id'] ?? '')) ?: null,
                'defensor_privado_outro' => trim((string) ($payload['defensor_privado_outro'] ?? '')) ?: null,
                'maioridade' => $maioridade,
                'expedicao' => $expedicao,
                'cumprimento' => $cumprimento,
                'id_documento_expedido' => trim((string) ($payload['id_documento_expedido'] ?? '')) ?: null,
                'id_documento_cumprido' => trim((string) ($payload['id_documento_cumprido'] ?? '')) ?: null,
                'atualizado_por' => $usuarioId,
            ]);

            $this->connection->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /** @return array{0:string,1:string} */
    private function mapPautaTipoToProcessoTipoEPolo(string $tipoPauta): array
    {
        $tipo = strtolower(trim($tipoPauta));
        if ($tipo === 'reu') {
            return [ProcessosPartesRepository::TIPO_REU, ProcessosPartesRepository::POLO_PASSIVO];
        }
        if ($tipo === 'advogado') {
            return [ProcessosPartesRepository::TIPO_ADVOGADO, ProcessosPartesRepository::POLO_TERCEIROS];
        }
        if ($tipo === 'defensor_publico' || $tipo === 'defensoria_publica') {
            return [ProcessosPartesRepository::TIPO_DEFENSOR_PUBLICO, ProcessosPartesRepository::POLO_TERCEIROS];
        }
        if ($tipo === 'vitima') {
            return [ProcessosPartesRepository::TIPO_VITIMA, ProcessosPartesRepository::POLO_ATIVO];
        }
        if ($tipo === 'testemunha_denuncia') {
            return [ProcessosPartesRepository::TIPO_TESTEMUNHA_DENUNCIA, ProcessosPartesRepository::POLO_TERCEIROS];
        }
        if ($tipo === 'testemunha_defesa') {
            return [ProcessosPartesRepository::TIPO_TESTEMUNHA_DEFESA, ProcessosPartesRepository::POLO_TERCEIROS];
        }

        return [ProcessosPartesRepository::TIPO_REQUERENTE, ProcessosPartesRepository::POLO_ATIVO];
    }

    public function deleteParte(string $idParte, ?string $usuarioId): bool
    {
        return $this->parteRepository->softDelete($idParte, $usuarioId);
    }
}

