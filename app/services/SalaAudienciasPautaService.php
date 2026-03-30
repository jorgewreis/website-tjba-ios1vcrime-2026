<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasTiposRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../helpers/SalaAudienciasDuracaoEstimadaHelper.php';
require_once __DIR__ . '/../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../helpers/SalaAudienciasReuPresoDetector.php';
require_once __DIR__ . '/ProcessoManager.php';

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
    private ?ProcessoManager $processoPrioridadesManager = null;

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

        $this->assertDataAudienciaNaoPassada($data);
        $hora = $this->normalizarHoraNaGradeCadastro($hora);

        if ($this->pautaRepository->existsActiveSlotEm($data, $hora, null)) {
            throw new InvalidArgumentException('Já existe audiência designada neste dia e horário. Escolha outro horário.');
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
        $status = $this->finalizeStatusPauta(trim((string) ($payload['status'] ?? '')), 'designada');

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
            $this->sincronizarDuracaoEHorariosPauta($id);
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
        $statusIn = trim((string) ($payload['status'] ?? ''));
        if ($tipo === '' || $data === '' || $hora === '' || $local === '') {
            throw new InvalidArgumentException('Preencha os campos obrigatórios da audiência.');
        }
        $atual = $this->pautaRepository->findById($id);
        if ($atual === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        $status = $this->finalizeStatusPauta($statusIn, trim((string) ($atual['status'] ?? 'designada')));

        if ($status === 'designada') {
            $this->assertDataAudienciaNaoPassada($data);
            $hora = $this->normalizarHoraNaGradeCadastro($hora);
            if ($this->pautaRepository->existsActiveSlotEm($data, $hora, $id)) {
                throw new InvalidArgumentException('Já existe audiência designada neste dia e horário. Escolha outro horário.');
            }
        } else {
            $hora = $this->normalizarHoraAudienciaArmazenamento($hora);
        }

        $ok = $this->pautaRepository->update($id, [
            'tipo_audiencia' => $tipo,
            'data_audiencia' => $data,
            'hora_audiencia' => $hora,
            'localizacao' => $local,
            'status' => $status,
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
            'atualizado_por' => $usuarioId,
        ]);
        if ($ok) {
            $this->sincronizarDuracaoEHorariosPauta($id);
        }

        return $ok;
    }

    /**
     * Ações rápidas na lista da pauta: reiniciar → designada, iniciar → em_andamento, encerrar → finalizada.
     * Atualiza `data_hora_inicio_efetivo` / `data_hora_fim_efetivo` conforme a ação.
     * Só permitido no dia da audiência; com outra em andamento no mesmo dia, demais ficam bloqueadas na UI.
     *
     * @param 'reiniciar'|'iniciar'|'encerrar' $acao
     */
    public function setStatusPainel(string $idPauta, string $acao, ?string $usuarioId): void
    {
        $idPauta = trim($idPauta);
        $acao = strtolower(trim($acao));
        if (!in_array($acao, ['reiniciar', 'iniciar', 'encerrar'], true)) {
            throw new InvalidArgumentException('Ação inválida.');
        }
        $hoje = date('Y-m-d');
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        $dataAud = trim((string) ($pauta['data_audiencia'] ?? ''));
        if ($dataAud !== $hoje) {
            throw new InvalidArgumentException('O painel só pode ser usado no dia da audiência.');
        }
        $stAtual = trim((string) ($pauta['status'] ?? ''));
        if ($stAtual === 'agendada') {
            $stAtual = 'designada';
        }
        if ($stAtual === 'adiada') {
            $stAtual = 'redesignada';
        }

        $outroEmAndamento = $this->pautaRepository->findIdEmAndamentoNaData($hoje);
        if ($outroEmAndamento !== null && $outroEmAndamento !== $idPauta) {
            throw new InvalidArgumentException('Já existe uma audiência em andamento. Conclua ou reinicie-a antes de movimentar outras.');
        }

        if ($acao === 'iniciar') {
            if ($stAtual !== 'designada') {
                throw new InvalidArgumentException('Só é possível iniciar audiências com status Designada.');
            }
        } elseif ($acao === 'reiniciar') {
            if ($stAtual !== 'em_andamento') {
                throw new InvalidArgumentException('Só é possível reiniciar uma audiência que estiver em andamento.');
            }
            $horaBruta = trim((string) ($pauta['hora_audiencia'] ?? ''));
            $this->assertDataAudienciaNaoPassada($dataAud);
            $horaNorm = $this->normalizarHoraNaGradeCadastro($horaBruta);
            if ($this->pautaRepository->existsActiveSlotEm($dataAud, $horaNorm, $idPauta)) {
                throw new InvalidArgumentException('Já existe audiência designada neste dia e horário. Não é possível reiniciar para Designada.');
            }
        } elseif ($acao === 'encerrar') {
            if ($stAtual !== 'em_andamento') {
                throw new InvalidArgumentException('Só é possível encerrar uma audiência em andamento.');
            }
        }

        $ok = $this->pautaRepository->applyPainelAcao($idPauta, $acao, $usuarioId);
        if (!$ok) {
            throw new RuntimeException('Não foi possível atualizar a audiência.');
        }
        $this->sincronizarDuracaoEHorariosPauta($idPauta);
    }

    public function findIdEmAndamentoNaData(string $dataYmd): ?string
    {
        return $this->pautaRepository->findIdEmAndamentoNaData($dataYmd);
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

        $this->normalizeDefensorPayloadForSave($payload, $tipo);

        $this->connection->beginTransaction();
        try {
            [$tipoProcesso, $poloProcesso] = $this->mapPautaTipoToProcessoTipoEPolo($tipo);
            $isTestemunha = $tipo === 'testemunha_defesa' || $tipo === 'testemunha_denuncia';

            // Para testemunhas: se já existir vínculo (processo↔parte) para esse nome,
            // reutilizamos o `parte_id` existente em vez de criar um novo cadastro global.
            $nomeNormRaw = function_exists('mb_strtolower') ? mb_strtolower($nome, 'UTF-8') : strtolower($nome);
            $nomeNorm = preg_replace('/\s+/u', ' ', trim($nomeNormRaw));
            $parteGlobalId = '';
            $processoParteId = '';

            if ($isTestemunha) {
                $vinculos = $this->processosPartesRepository->findByProcessoId($processoId, $tipoProcesso);
                foreach ($vinculos as $v) {
                    $vNome = trim((string) ($v['nome_completo'] ?? ''));
                    $vNomeNormRaw = function_exists('mb_strtolower') ? mb_strtolower($vNome, 'UTF-8') : strtolower($vNome);
                    $vNomeNorm = preg_replace('/\s+/u', ' ', trim($vNomeNormRaw));
                    if ($vNomeNorm !== '' && $nomeNorm !== '' && $vNomeNorm === $nomeNorm) {
                        $parteGlobalId = trim((string) ($v['parte_id'] ?? ''));
                        $processoParteId = trim((string) ($v['id'] ?? ''));
                        break;
                    }
                }
            }

            if ($parteGlobalId === '') {
                $parteGlobalId = $this->partesRepository->ensureParteByNomeOuCpf($nome, null);
            }

            if ($processoParteId === '') {
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
            } else {
                // Mesmo reaproveitando, garantimos o tipo/polo corretos no vínculo.
                $this->processosPartesRepository->update($processoParteId, [
                    'tipo_parte' => $tipoProcesso,
                    'polo' => $poloProcesso,
                ]);
            }

            $tipoDef = trim((string) ($payload['tipo_defensor'] ?? ''));
            if (($tipo === 'reu' || $tipo === 'vitima') && ($tipoDef === 'publico' || $tipoDef === 'privado')) {
                $defId = $tipoDef === 'publico'
                    ? trim((string) ($payload['defensor_publico_id'] ?? ''))
                    : trim((string) ($payload['defensor_privado_id'] ?? ''));
                $this->ensureDefensorVinculadoAoProcesso($processoId, $tipoDef, $defId);
            }

            $idPartePauta = $this->parteRepository->insert([
                'pauta' => $idPauta,
                'parte_id_global' => $parteGlobalId,
                'processo_parte_id' => $processoParteId,
                'nome_parte' => $nome,
                'tipo_parte' => $tipo,
                'tipo_defensor' => trim((string) ($payload['tipo_defensor'] ?? '')) ?: null,
                'defensor_publico_id' => trim((string) ($payload['defensor_publico_id'] ?? '')) ?: null,
                'defensor_publico_outro' => trim((string) ($payload['defensor_publico_outro'] ?? '')) ?: null,
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
            $this->sincronizarDuracaoEHorariosPauta($idPauta);
            $numeroProcPauta = trim((string) ($pauta['numero_processo'] ?? ''));
            $this->garantirPrioridadeCriancaOuAdolescenteSeMenor($numeroProcPauta, $maioridade);
            $this->sincronizarPrioridadeReuPresoNoProcesso($idPauta);

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

        $this->normalizeDefensorPayloadForSave($payload, $tipo);

        $maioridadeAnterior = strtolower(trim((string) ($parteAtual['maioridade'] ?? '')));

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

            $processoId = trim((string) ($parteAtual['processo'] ?? ''));
            $tipoDef = trim((string) ($payload['tipo_defensor'] ?? ''));
            if (($tipo === 'reu' || $tipo === 'vitima') && $processoId !== '' && ($tipoDef === 'publico' || $tipoDef === 'privado')) {
                $defId = $tipoDef === 'publico'
                    ? trim((string) ($payload['defensor_publico_id'] ?? ''))
                    : trim((string) ($payload['defensor_privado_id'] ?? ''));
                $this->ensureDefensorVinculadoAoProcesso($processoId, $tipoDef, $defId);
            }

            $ok = $this->parteRepository->update($idParte, [
                'parte_id_global' => $parteGlobalId,
                'processo_parte_id' => $processoParteId !== '' ? $processoParteId : null,
                'nome_parte' => $nome,
                'tipo_parte' => $tipo,
                'tipo_defensor' => trim((string) ($payload['tipo_defensor'] ?? '')) ?: null,
                'defensor_publico_id' => trim((string) ($payload['defensor_publico_id'] ?? '')) ?: null,
                'defensor_publico_outro' => trim((string) ($payload['defensor_publico_outro'] ?? '')) ?: null,
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
            $idPautaSync = trim((string) ($parteAtual['pauta'] ?? ''));
            if ($idPautaSync !== '') {
                $this->sincronizarDuracaoEHorariosPauta($idPautaSync);
            }
            $maioridadeNova = strtolower(trim($maioridade));
            $processoIdAtual = trim((string) ($parteAtual['processo'] ?? ''));
            $numeroProcAtual = trim((string) ($parteAtual['numero_processo'] ?? ''));
            if ($maioridadeNova === 'menor' && $maioridadeAnterior !== 'menor') {
                $this->garantirPrioridadeCriancaOuAdolescenteSeMenor($numeroProcAtual, $maioridade);
            }
            if ($maioridadeAnterior === 'menor' && $maioridadeNova !== 'menor') {
                $this->avaliarRemocaoPrioridadeCriancaAposMenor($processoIdAtual, $numeroProcAtual);
            }
            $this->sincronizarPrioridadeReuPresoNoProcesso($idPautaSync);

            return $ok;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Ajusta IDs de defensor (incl. opção "outro" → cadastro em `partes`) e zera campos não usados.
     */
    private function normalizeDefensorPayloadForSave(array &$payload, string $tipoParte): void
    {
        $tipoDef = trim((string) ($payload['tipo_defensor'] ?? ''));
        if ($tipoParte !== 'reu' && $tipoParte !== 'vitima') {
            $payload['tipo_defensor'] = null;
            $payload['defensor_publico_id'] = null;
            $payload['defensor_publico_outro'] = null;
            $payload['defensor_privado_id'] = null;
            $payload['defensor_privado_outro'] = null;

            return;
        }
        if ($tipoDef === 'publico') {
            $id = trim((string) ($payload['defensor_publico_id'] ?? ''));
            $outro = trim((string) ($payload['defensor_publico_outro'] ?? ''));
            if ($id === 'outro') {
                if ($outro === '') {
                    throw new InvalidArgumentException('Informe o nome do defensor público.');
                }
                $payload['defensor_publico_id'] = $this->partesRepository->ensureParteByNomeOuCpf($outro, null, PartesRepository::PROFISSAO_DEFENSOR_PUBLICO);
                $payload['defensor_publico_outro'] = null;
            } else {
                $payload['defensor_publico_outro'] = null;
            }
            $payload['defensor_privado_id'] = null;
            $payload['defensor_privado_outro'] = null;
        } elseif ($tipoDef === 'privado') {
            $id = trim((string) ($payload['defensor_privado_id'] ?? ''));
            $outro = trim((string) ($payload['defensor_privado_outro'] ?? ''));
            if ($id === 'outro') {
                if ($outro === '') {
                    throw new InvalidArgumentException('Informe o nome do defensor privado (advogado).');
                }
                $payload['defensor_privado_id'] = $this->partesRepository->ensureParteByNomeOuCpf($outro, null, PartesRepository::PROFISSAO_ADVOGADO);
                $payload['defensor_privado_outro'] = null;
            } else {
                $payload['defensor_privado_outro'] = null;
            }
            $payload['defensor_publico_id'] = null;
            $payload['defensor_publico_outro'] = null;
        } else {
            $payload['defensor_publico_id'] = null;
            $payload['defensor_publico_outro'] = null;
            $payload['defensor_privado_id'] = null;
            $payload['defensor_privado_outro'] = null;
        }
    }

    /**
     * Garante em `processos_partes` o vínculo do defensor/advogado ao processo (polo terceiros).
     */
    private function ensureDefensorVinculadoAoProcesso(string $processoId, string $tipoDefensor, string $defensorParteId): void
    {
        $defensorParteId = trim($defensorParteId);
        if ($processoId === '' || $defensorParteId === '' || $defensorParteId === 'outro') {
            return;
        }
        if (!$this->isParteGlobalId($defensorParteId)) {
            return;
        }
        $tipo = $tipoDefensor === 'publico'
            ? ProcessosPartesRepository::TIPO_DEFENSOR_PUBLICO
            : ProcessosPartesRepository::TIPO_ADVOGADO;
        $polo = ProcessosPartesRepository::POLO_TERCEIROS;
        $v = $this->processosPartesRepository->findByProcessoAndParte($processoId, $defensorParteId);
        if ($v === null) {
            $this->processosPartesRepository->addParte($processoId, $defensorParteId, $tipo, $polo);
        } else {
            $this->processosPartesRepository->update((string) $v['id'], ['tipo_parte' => $tipo, 'polo' => $polo]);
        }
    }

    private function isParteGlobalId(string $id): bool
    {
        return (bool) preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $id);
    }

    /**
     * @return 'designada'|'realizada'|'redesignada'|'cancelada'|'em_andamento'|'finalizada'
     */
    private function finalizeStatusPauta(string $statusIn, string $whenEmpty): string
    {
        $s = trim($statusIn);
        if ($s === '') {
            $s = trim($whenEmpty);
        }
        $s = $this->normalizeLegacyStatus($s);
        if (!in_array($s, ['designada', 'realizada', 'redesignada', 'cancelada', 'em_andamento', 'finalizada'], true)) {
            return 'designada';
        }

        return $s;
    }

    /** Compatível com valores antigos armazenados ou enviados pelo cliente (agendada / adiada). */
    private function normalizeLegacyStatus(string $s): string
    {
        if ($s === 'agendada') {
            return 'designada';
        }
        if ($s === 'adiada') {
            return 'redesignada';
        }

        return $s;
    }

    /**
     * Horários permitidos no cadastro: 09:00 a 16:00, de 10 em 10 minutos (24 h, formato HH:MM).
     *
     * @return list<string>
     */
    public static function horariosGradeCadastroAudiencia(): array
    {
        $slots = [];
        for ($min = 9 * 60; $min <= 16 * 60; $min += 10) {
            $slots[] = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        }
        return $slots;
    }

    private function assertDataAudienciaNaoPassada(string $data): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new InvalidArgumentException('Data da audiência inválida.');
        }
        $informada = \DateTimeImmutable::createFromFormat('Y-m-d', $data);
        if ($informada === false || $informada->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data da audiência inválida.');
        }
        $hoje = new \DateTimeImmutable('today');
        if ($informada < $hoje) {
            throw new InvalidArgumentException('A data da audiência não pode ser anterior a hoje.');
        }
    }

    private function normalizarHoraNaGradeCadastro(string $hora): string
    {
        $hora = trim($hora);
        $hm = null;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            $hm = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        } elseif (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $hora, $m)) {
            $hm = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        if ($hm === null) {
            throw new InvalidArgumentException('Hora da audiência inválida.');
        }
        if (!in_array($hm, self::horariosGradeCadastroAudiencia(), true)) {
            throw new InvalidArgumentException('Selecione um horário entre 09:00 e 16:00, em intervalos de 10 minutos.');
        }
        return $hm . ':00';
    }

    /** Normaliza hora para TIME no BD quando o status não exige a grade (redesignada, cancelada, realizada). */
    private function normalizarHoraAudienciaArmazenamento(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $hora, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        throw new InvalidArgumentException('Hora da audiência inválida.');
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
        $parte = $this->parteRepository->findById($idParte);
        if ($parte === null) {
            return false;
        }
        $idPauta = trim((string) ($parte['pauta'] ?? ''));
        $maioridadeAnt = strtolower(trim((string) ($parte['maioridade'] ?? '')));
        $processoIdParte = trim((string) ($parte['processo'] ?? ''));
        $numeroProcParte = trim((string) ($parte['numero_processo'] ?? ''));
        $ok = $this->parteRepository->softDelete($idParte, $usuarioId);
        if ($ok && $idPauta !== '') {
            $this->sincronizarDuracaoEHorariosPauta($idPauta);
        }
        if ($ok && $maioridadeAnt === 'menor' && $processoIdParte !== '' && $numeroProcParte !== '') {
            $this->avaliarRemocaoPrioridadeCriancaAposMenor($processoIdParte, $numeroProcParte);
        }
        if ($ok && $idPauta !== '') {
            $this->sincronizarPrioridadeReuPresoNoProcesso($idPauta);
        }

        return $ok;
    }

    /** Recalcula duração estimada e horários derivados (útil após migração ou ajuste manual). */
    public function refreshDuracaoEstimadaPauta(string $idPauta): void
    {
        $this->sincronizarDuracaoEHorariosPauta($idPauta);
    }

    private function sincronizarDuracaoEHorariosPauta(string $idPauta): void
    {
        $idPauta = trim($idPauta);
        if ($idPauta === '') {
            return;
        }
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            return;
        }
        $partes = $this->parteRepository->listByPauta($idPauta);
        $tipoAud = trim((string) ($pauta['tipo_audiencia'] ?? ''));
        $dur = SalaAudienciasDuracaoEstimadaHelper::totalEstimadoMinutos($tipoAud, $partes);
        $data = trim((string) ($pauta['data_audiencia'] ?? ''));
        $hora = trim((string) ($pauta['hora_audiencia'] ?? ''));
        $dataHoraDes = SalaAudienciasDuracaoEstimadaHelper::montarDataHoraDesignacao($data, $hora);
        $horaInicio = SalaAudienciasDuracaoEstimadaHelper::normalizarHoraParaSql($hora);
        $horaFim = SalaAudienciasDuracaoEstimadaHelper::horaFimApartirDeInicio($data, $horaInicio, $dur);
        $this->pautaRepository->updateTimingEstimado($idPauta, $dataHoraDes, $horaInicio, $horaFim, $dur);
    }

    private function processoPrioridadesManager(): ProcessoManager
    {
        if ($this->processoPrioridadesManager === null) {
            $this->processoPrioridadesManager = new ProcessoManager($this->connection);
        }

        return $this->processoPrioridadesManager;
    }

    private function garantirPrioridadeCriancaOuAdolescenteSeMenor(string $numeroProcesso, string $maioridade): void
    {
        if (strtolower(trim($maioridade)) !== 'menor') {
            return;
        }
        $numero = trim($numeroProcesso);
        if ($numero === '') {
            return;
        }
        $this->processoPrioridadesManager()->adicionarPrioridadeSeNaoExistir($numero, PrioridadesProcessuaisCatalog::prioridadeAutomaticaParteMenor());
    }

    /**
     * Remove a prioridade só se não restar nenhuma parte menor ativa em audiências do mesmo processo.
     */
    private function avaliarRemocaoPrioridadeCriancaAposMenor(string $processoId, string $numeroProcesso): void
    {
        $processoId = trim($processoId);
        $numero = trim($numeroProcesso);
        if ($processoId === '' || $numero === '') {
            return;
        }
        $q = $this->parteRepository->countPartesMenoresAtivasPorProcesso($processoId);
        if ($q === 0) {
            $this->processoPrioridadesManager()->removerPrioridade($numero, PrioridadesProcessuaisCatalog::prioridadeAutomaticaParteMenor());
        }
    }

    /**
     * Mantém em `processoPrioridades` a prioridade "Réu preso" conforme nomes de réus da audiência
     * e cadastros de réus presos (globais e por processo).
     *
     * @return bool detecção atual (há réu da audiência identificado como preso)
     */
    private function sincronizarPrioridadeReuPresoNoProcesso(string $idPauta): bool
    {
        $idPauta = trim($idPauta);
        if ($idPauta === '') {
            return false;
        }
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            return false;
        }
        $processoId = trim((string) ($pauta['processo'] ?? ''));
        $numero = trim((string) ($pauta['numero_processo'] ?? ''));
        $partes = $this->parteRepository->listByPauta($idPauta);
        $tem = SalaAudienciasReuPresoDetector::audienciaTemReuPreso($this->connection, $processoId, $numero, $partes);
        $label = PrioridadesProcessuaisCatalog::prioridadeAutomaticaReuPreso();
        if ($numero !== '') {
            if ($tem) {
                $this->processoPrioridadesManager()->adicionarPrioridadeSeNaoExistir($numero, $label);
            } else {
                $this->processoPrioridadesManager()->removerPrioridade($numero, $label);
            }
        }

        return $tem;
    }

    /**
     * Estado da seção de prioridades na edição da audiência (catálogo + lista no BD + bloqueio da automática).
     *
     * @return array{prioridades: list<string>, has_parte_menor: bool, has_reu_preso: bool, catalogo: list<string>, prioridade_bloqueada_se_menor: string, prioridade_bloqueada_se_reu_preso: string}
     */
    public function listPrioridadesEdicaoAudiencia(string $idPauta): array
    {
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        $processoId = trim((string) ($pauta['processo'] ?? ''));
        $numero = trim((string) ($pauta['numero_processo'] ?? ''));
        $hasMenor = $processoId !== '' && $this->parteRepository->countPartesMenoresAtivasPorProcesso($processoId) > 0;

        $dbList = [];
        if ($numero !== '') {
            $dbList = $this->processoPrioridadesManager()->listarPrioridades($numero);
        }

        $auto = PrioridadesProcessuaisCatalog::prioridadeAutomaticaParteMenor();
        if ($hasMenor && $numero !== '' && !in_array($auto, $dbList, true)) {
            $this->processoPrioridadesManager()->adicionarPrioridadeSeNaoExistir($numero, $auto);
            $dbList = $this->processoPrioridadesManager()->listarPrioridades($numero);
        }

        $hasReuPreso = $this->sincronizarPrioridadeReuPresoNoProcesso($idPauta);
        if ($numero !== '') {
            $dbList = $this->processoPrioridadesManager()->listarPrioridades($numero);
        }

        sort($dbList, SORT_LOCALE_STRING);

        return [
            'prioridades' => $dbList,
            'has_parte_menor' => $hasMenor,
            'has_reu_preso' => $hasReuPreso,
            'catalogo' => PrioridadesProcessuaisCatalog::sortedLabels(),
            'prioridade_bloqueada_se_menor' => $auto,
            'prioridade_bloqueada_se_reu_preso' => PrioridadesProcessuaisCatalog::prioridadeAutomaticaReuPreso(),
        ];
    }

    public function addPrioridadeCatalogoAudiencia(string $idPauta, string $prioridade, ?string $usuarioId): void
    {
        unset($usuarioId);
        $prioridade = trim($prioridade);
        if ($prioridade === '' || !PrioridadesProcessuaisCatalog::isValid($prioridade)) {
            throw new InvalidArgumentException('Selecione uma prioridade válida do catálogo.');
        }
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        $numero = trim((string) ($pauta['numero_processo'] ?? ''));
        if ($numero === '') {
            throw new InvalidArgumentException('Número do processo não disponível para esta audiência.');
        }
        $ok = $this->processoPrioridadesManager()->adicionarPrioridadeSeNaoExistir($numero, $prioridade);
        if (!$ok) {
            throw new RuntimeException('Não foi possível registrar a prioridade.');
        }
    }

    public function removePrioridadeCatalogoAudiencia(string $idPauta, string $prioridade, ?string $usuarioId): void
    {
        unset($usuarioId);
        $prioridade = trim($prioridade);
        if ($prioridade === '' || !PrioridadesProcessuaisCatalog::isValid($prioridade)) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }
        $pauta = $this->pautaRepository->findById($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }
        $processoId = trim((string) ($pauta['processo'] ?? ''));
        $numero = trim((string) ($pauta['numero_processo'] ?? ''));
        if ($numero === '') {
            throw new InvalidArgumentException('Número do processo não disponível para esta audiência.');
        }
        $auto = PrioridadesProcessuaisCatalog::prioridadeAutomaticaParteMenor();
        if ($prioridade === $auto && $processoId !== '' && $this->parteRepository->countPartesMenoresAtivasPorProcesso($processoId) > 0) {
            throw new InvalidArgumentException('Esta prioridade é mantida automaticamente enquanto houver parte menor.');
        }
        $autoReu = PrioridadesProcessuaisCatalog::prioridadeAutomaticaReuPreso();
        if ($prioridade === $autoReu) {
            $partes = $this->parteRepository->listByPauta($idPauta);
            $numeroCheck = trim((string) ($pauta['numero_processo'] ?? ''));
            if (SalaAudienciasReuPresoDetector::audienciaTemReuPreso($this->connection, $processoId, $numeroCheck, $partes)) {
                throw new InvalidArgumentException('Esta prioridade é mantida automaticamente enquanto houver réu preso entre as partes desta audiência.');
            }
        }
        $this->processoPrioridadesManager()->removerPrioridade($numero, $prioridade);
    }
}

