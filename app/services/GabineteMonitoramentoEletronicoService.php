<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GabineteMonitoramentoEletronicoRepository.php';
require_once __DIR__ . '/../repositories/GabineteMonitoramentoEletronicoSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class GabineteMonitoramentoEletronicoService
{
    private PDO $connection;
    private GabineteMonitoramentoEletronicoRepository $repository;
    private GabineteMonitoramentoEletronicoSubtarefasRepository $subtarefasRepository;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private ClassesRepository $classesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new GabineteMonitoramentoEletronicoRepository($connection);
        $this->subtarefasRepository = new GabineteMonitoramentoEletronicoSubtarefasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    public function listMonitoramentos(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $id = (string) ($row['IdMon'] ?? '');
            if ($id !== '') {
                $allSt = $this->subtarefasRepository->findByMonitoramentoId($id);
                $rows[$i]['SubtarefasAll'] = $allSt;
                $rows[$i]['Subtarefas'] = $this->filterSubtarefasAbertas($allSt);
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
            }
            $rows[$i]['DuracaoLabel'] = $this->labelDuracao(
                (string) ($row['Status'] ?? ''),
                $row['DataInicio'] ?? null,
                $row['DataEncerramento'] ?? null
            );
        }

        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * @return list<array{id: string, nome: string, tipo_parte: string}>
     */
    public function listarReusDoProcesso(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }
        $raw = $this->processosPartesRepository->findAcusadosByProcessoId($processoId);
        $out = [];
        foreach ($raw as $r) {
            $ppId = trim((string) ($r['id'] ?? ''));
            if ($ppId === '') {
                continue;
            }
            $nome = trim((string) ($r['nome_completo'] ?? ''));
            $out[] = [
                'id' => $ppId,
                'nome' => $nome !== '' ? $nome : '(sem nome)',
                'tipo_parte' => (string) ($r['tipo_parte'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Consulta réus já vinculados. Nome ou polo extraídos do DataJud não comprovam
     * identidade nem condição de réu; quando ausentes, o usuário informa manualmente.
     *
     * @return array{processo_id: string, reus: list<array{id: string, nome: string, tipo_parte: string}>}
     */
    public function ensureProcessoEListarReusParaCadastro(string $numeroRaw, string $classeCodigo = '', string $classeDescricao = ''): array
    {
        $numeroProcesso = trim($numeroRaw);
        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos (formato CNJ).');
        }
        $numeroFormatado = $this->formatarCNJ($digits);

        $existing = $this->processosRepository->findByNumero($numeroFormatado);
        if ($existing === null) {
            return ['processo_id' => '', 'reus' => [], 'mensagem' => 'Nenhum réu vinculado encontrado. Informe o nome manualmente.'];
        }
        $processoId = trim((string) ($existing['id'] ?? ''));
        if ($processoId === '') throw new RuntimeException('Processo encontrado sem identificador.');
        $reus = $this->listarReusDoProcesso($processoId);
        $result = ['processo_id' => $processoId, 'reus' => $reus];
        if ($reus === []) $result['mensagem'] = 'Nenhum réu vinculado encontrado. Informe o nome manualmente.';
        return $result;
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }
        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos (formato CNJ).');
        }
        $numeroFormatado = $this->formatarCNJ($digits);

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }
        $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, null);

        $processoParteId = trim((string) ($payload['processo_parte_id'] ?? $payload['reu_processo_parte_id'] ?? ''));
        $reuId = trim((string) ($payload['reu_id'] ?? ''));
        if ($processoParteId === '' && $reuId !== '') {
            $partesRepo = new PartesRepository($this->connection);
            $parte = $partesRepo->findById($reuId);
            if ($parte === null) {
                throw new InvalidArgumentException('Réu informado não foi encontrado no cadastro de partes.');
            }
            $vinculoExistente = $this->processosPartesRepository->findByProcessoAndParte($processoOrigemId, $reuId);
            if ($vinculoExistente !== null) {
                $processoParteId = (string) ($vinculoExistente['id'] ?? '');
            } else {
                $processoParteId = $this->processosPartesRepository->addParte(
                    $processoOrigemId,
                    $reuId,
                    ProcessosPartesRepository::TIPO_REU,
                    ProcessosPartesRepository::POLO_PASSIVO
                );
            }
        }
        $modoOutro = ($processoParteId === '' || $processoParteId === 'outro');
        $complemento = trim((string) ($payload['reu_complemento'] ?? ''));

        $reuNomeExibicao = '';
        if ($modoOutro) {
            if ($complemento === '') {
                throw new InvalidArgumentException('Informe o nome do réu ou complemento quando não houver seleção na lista.');
            }
            $reuNomeExibicao = TextFormatter::formatProperName($complemento, 500);
            $processoParteId = '';
        } else {
            $pp = $this->processosPartesRepository->findById($processoParteId);
            if ($pp === null || (string) ($pp['processo_id'] ?? '') !== $processoOrigemId) {
                throw new InvalidArgumentException('Réu selecionado não pertence ao processo informado.');
            }
            $baseNome = trim((string) ($pp['nome_completo'] ?? ''));
            $reuNomeExibicao = $baseNome !== '' ? TextFormatter::formatProperName($baseNome, 500) : 'Réu';
            if ($complemento !== '') {
                $reuNomeExibicao .= ' — ' . TextFormatter::formatProperName($complemento, 500);
            }
        }

        $complementoStore = $complemento !== '' ? TextFormatter::formatProperName($complemento, 500) : null;
        $processoParteIdNull = $modoOutro ? null : $processoParteId;

        $ano = (int) date('Y');

        $this->connection->beginTransaction();
        try {
            $seq = $this->repository->nextSequencialForYear($ano);
            $id = IdGenerator::generateUniquePrefixedId(
                $this->connection,
                $this->repository->getTableName(),
                'id',
                'GME',
                30
            );
            $insertRow = [
                'id' => $id,
                'processo_id' => $processoOrigemId,
                'numero_processo' => $numeroFormatado,
                'ano' => $ano,
                'numero_sequencial' => $seq,
                'processo_parte_id' => $processoParteIdNull,
                'reu_nome_exibicao' => $reuNomeExibicao,
                'reu_complemento' => $complementoStore,
                'status' => 'pendente',
                'data_inicio' => null,
                'data_encerramento' => null,
            ];
            $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
            if ($usuarioCadastroId !== '') {
                $insertRow['criado_por'] = $usuarioCadastroId;
            }
            $this->repository->insert($insertRow);
            $this->subtarefasRepository->create($id, GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR, 1, 'em_andamento');
            $this->subtarefasRepository->create($id, GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR, 2, 'pendente');
            $this->connection->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dataInicio, string $usuarioMovimentacaoId = ''): array
    {
        $st = $this->subtarefasRepository->findById($subtarefaId);
        if ($st === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }
        $monId = trim((string) ($st['monitoramento_id'] ?? ''));
        if ($monId === '') {
            throw new InvalidArgumentException('Monitoramento não identificado.');
        }
        $mon = $this->repository->findById($monId);
        if ($mon === null) {
            throw new InvalidArgumentException('Registro não encontrado.');
        }
        if (($mon['status'] ?? '') === 'cancelada') {
            throw new InvalidArgumentException('Registro cancelado.');
        }
        if (($mon['status'] ?? '') === 'finalizada') {
            throw new InvalidArgumentException('Monitoramento já finalizado.');
        }
        $ordem = (int) ($st['ordem'] ?? 0);
        $statusSt = (string) ($st['status'] ?? '');
        if ($statusSt === 'concluida') {
            throw new InvalidArgumentException('Esta subtarefa já foi concluída.');
        }

        $tipo = (string) ($st['tipo'] ?? '');

        if ($tipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR) {
            $dataInicio = trim((string) ($dataInicio ?? ''));
            if ($dataInicio === '') {
                throw new InvalidArgumentException('Informe a data de início do monitoramento.');
            }
            $d = DateTime::createFromFormat('Y-m-d', $dataInicio);
            if ($d === false || $d->format('Y-m-d') !== $dataInicio) {
                throw new InvalidArgumentException('Data de início inválida. Use o formato AAAA-MM-DD.');
            }
            $this->subtarefasRepository->marcarConcluida($subtarefaId);
            $s2 = $this->subtarefasRepository->findByMonitoramentoIdEOrdem($monId, 2);
            if ($s2 !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $s2['id'], 'em_andamento');
            }
            $this->repository->updateDatas($monId, $dataInicio, null, $usuarioMovimentacaoId);
            $this->repository->updateStatus($monId, 'em_andamento', $usuarioMovimentacaoId);

            return ['tarefa_principal_concluida' => false];
        }

        if ($tipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR) {
            $hoje = (new DateTime('today'))->format('Y-m-d');
            $di = trim((string) ($mon['data_inicio'] ?? ''));
            if ($di === '') {
                throw new InvalidArgumentException('O monitoramento ainda não foi iniciado.');
            }
            $this->subtarefasRepository->marcarConcluida($subtarefaId);
            $this->repository->updateDatas($monId, $di, $hoje, $usuarioMovimentacaoId);
            $this->repository->updateStatus($monId, 'finalizada', $usuarioMovimentacaoId);

            return ['tarefa_principal_concluida' => true];
        }

        throw new InvalidArgumentException('Tipo de subtarefa inválido.');
    }

    public function cancel(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $row = $this->repository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Registro não encontrado.');
        }
        if (($row['status'] ?? '') === 'finalizada') {
            throw new InvalidArgumentException('Não é possível cancelar um monitoramento já finalizado.');
        }
        if (($row['status'] ?? '') === 'cancelada') {
            return true;
        }
        $this->subtarefasRepository->deleteByMonitoramentoId($id);

        return $this->repository->updateStatus($id, 'cancelada');
    }

    public function delete(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $row = $this->repository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Registro não encontrado.');
        }
        $ano = (int) ($row['ano'] ?? 0);
        $seq = (int) ($row['numero_sequencial'] ?? 0);
        $max = $this->repository->getMaxSequencialForYear($ano);
        if ($seq !== $max || $max === 0) {
            throw new InvalidArgumentException('Só é possível excluir o último registro criado no ano (maior número sequencial).');
        }
        $this->subtarefasRepository->deleteByMonitoramentoId($id);
        $deleted = $this->repository->delete($id);

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Registro excluído com sucesso.' : 'Nenhum registro removido.',
        ];
    }

    private function formatarCNJ(string $digits): string
    {
        if (strlen($digits) !== 20) {
            return $digits;
        }

        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digits, 0, 7),
            substr($digits, 7, 2),
            substr($digits, 9, 4),
            substr($digits, 13, 1),
            substr($digits, 14, 2),
            substr($digits, 16, 4)
        );
    }

    public function formatDuracaoLabel(string $status, $dataInicio, $dataFim): string
    {
        return $this->labelDuracao($status, $dataInicio, $dataFim);
    }

    private function labelDuracao(string $status, $dataInicio, $dataFim): string
    {
        $di = $dataInicio !== null && $dataInicio !== '' ? (string) $dataInicio : '';
        if ($di === '') {
            return '—';
        }
        $d1 = DateTime::createFromFormat('Y-m-d', $di);
        if ($d1 === false) {
            return '—';
        }
        $d1->setTime(0, 0, 0);

        if ($status === 'finalizada') {
            $df = $dataFim !== null && $dataFim !== '' ? (string) $dataFim : '';
            if ($df === '') {
                return '—';
            }
            $d2 = DateTime::createFromFormat('Y-m-d', $df);
            if ($d2 === false) {
                return '—';
            }
            $d2->setTime(0, 0, 0);
            $n = $this->diasDiferencaInclusive($d1, $d2);

            return $n >= 0 ? (string) $n . ' dias' : '—';
        }

        if ($status === 'em_andamento') {
            $hoje = new DateTime('today');
            $n = $this->diasDiferencaInclusive($d1, $hoje);

            return $n >= 0 ? (string) $n . ' dias' : '—';
        }

        return '—';
    }

    private function diasDiferencaInclusive(DateTime $inicio, DateTime $fim): int
    {
        if ($fim < $inicio) {
            return -1;
        }

        return (int) $inicio->diff($fim)->days + 1;
    }

    /** @param list<array<string,mixed>> $all */
    private function filterSubtarefasAbertas(array $all): array
    {
        $out = [];
        foreach ($all as $s) {
            $st = (string) ($s['status'] ?? '');
            if ($st === 'pendente' || $st === 'em_andamento') {
                $out[] = $s;
            }
        }

        return $out;
    }
}
