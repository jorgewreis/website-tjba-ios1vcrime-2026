<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/OficiosCartorioRepository.php';
require_once __DIR__ . '/../repositories/OficiosCartorioSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/OficiosCartorioAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class OficiosCartorioService
{
    private PDO $connection;
    private OficiosCartorioRepository $repository;
    private OficiosCartorioSubtarefasRepository $subtarefasRepository;
    private OficiosCartorioAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;

    private const MEIOS_VALIDOS = ['email', 'malote_digital', 'pessoalmente'];
    private const REGRA_ALOCAR_OFICIOS = 'ALLOCATE-CARTORIOOFICIOS';

    /** Ordem de exibição dos tipos de alocação. */
    private const TIPO_ORDER = ['rule' => 0, 'all' => 1, 'specific' => 2];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new OficiosCartorioRepository($connection);
        $this->subtarefasRepository = new OficiosCartorioSubtarefasRepository($connection);
        $this->alocadosRepository = new OficiosCartorioAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        return in_array($tipo, ['rule', 'all', 'specific'], true) ? $tipo : 'specific';
    }

    public function removeAlocado(string $alocadoId, string $tipo, string $requestUserId): bool
    {
        $alocadoId     = trim($alocadoId);
        $requestUserId = trim($requestUserId);
        if ($alocadoId === '' || $requestUserId === '') {
            return false;
        }
        return $this->alocadosRepository->deleteById($alocadoId);
    }

    public function listOficios(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $id = (string) ($row['IdOficio'] ?? '');
            if ($id !== '') {
                $allSt = $this->subtarefasRepository->findByOficioId($id);
                $rows[$i]['SubtarefasAll'] = $allSt;
                $rows[$i]['Subtarefas'] = $this->filterSubtarefasAbertas($allSt);
                $alocadosRaw = $this->alocadosRepository->findByOficioId($id);
                $alocados = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $tipo = $this->normalizeTipoAlocado((string) ($a['tipo'] ?? ''));
                    $alocados[] = [
                        'alocado_id'    => $a['id'] ?? '',
                        'iniciais'      => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id'    => $a['usuario_id'] ?? '',
                        'tipo'          => $tipo,
                    ];
                }
                usort($alocados, static function (array $a, array $b): int {
                    return (self::TIPO_ORDER[$a['tipo']] ?? 9) <=> (self::TIPO_ORDER[$b['tipo']] ?? 9);
                });
                $rows[$i]['Alocados'] = $alocados;
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['Alocados'] = [];
            }
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

    /**
     * @return list<int>
     */
    public function listAnosComOficios(): array
    {
        return $this->repository->fetchDistinctAnos();
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
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

        $tipoOficio = trim((string) ($payload['tipo_oficio'] ?? ''));
        if ($tipoOficio === '') {
            throw new InvalidArgumentException('O tipo de ofício é obrigatório.');
        }
        $tipoOficio = TextFormatter::formatProperName($tipoOficio, 255);

        $forma = trim((string) ($payload['forma_cumprimento'] ?? ''));
        if (!in_array($forma, ['ccm', 'cartorio'], true)) {
            throw new InvalidArgumentException('Informe a forma de cumprimento (CCM ou pelo Cartório).');
        }

        $meioEnvio = null;
        if ($forma === 'cartorio') {
            $meioEnvio = trim((string) ($payload['meio_envio'] ?? ''));
            if (!in_array($meioEnvio, self::MEIOS_VALIDOS, true)) {
                throw new InvalidArgumentException('Selecione o meio de envio (e-mail, malote digital ou pessoalmente).');
            }
        }

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }
        $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);

        $ano = (int) date('Y');
        if (isset($payload['ano']) && is_numeric($payload['ano'])) {
            $ano = (int) $payload['ano'];
        }
        if ($ano < 2000 || $ano > 2100) {
            throw new InvalidArgumentException('Ano inválido.');
        }

        $criadoPor = trim((string) ($payload['criado_por'] ?? ''));
        $criadoPor = $criadoPor !== '' ? $criadoPor : null;

        $this->connection->beginTransaction();
        try {
            $seq = $this->repository->nextSequencialForYear($ano);
            $id = IdGenerator::generateUniquePrefixedId(
                $this->connection,
                $this->repository->getTableName(),
                'id',
                'OFC',
                30
            );

            if ($forma === 'ccm') {
                $this->repository->insert([
                    'id' => $id,
                    'processo_id' => $processoOrigemId,
                    'numero_processo' => $numeroFormatado,
                    'ano' => $ano,
                    'numero_sequencial' => $seq,
                    'tipo_oficio' => $tipoOficio,
                    'forma_cumprimento' => 'ccm',
                    'meio_envio' => null,
                    'status' => 'finalizada',
                    'criado_por' => $criadoPor,
                    'atualizado_por' => $criadoPor,
                ]);
            } else {
                $this->repository->insert([
                    'id' => $id,
                    'processo_id' => $processoOrigemId,
                    'numero_processo' => $numeroFormatado,
                    'ano' => $ano,
                    'numero_sequencial' => $seq,
                    'tipo_oficio' => $tipoOficio,
                    'forma_cumprimento' => 'cartorio',
                    'meio_envio' => $meioEnvio,
                    'status' => 'pendente',
                    'criado_por' => $criadoPor,
                    'atualizado_por' => $criadoPor,
                ]);
                $this->subtarefasRepository->create($id, OficiosCartorioSubtarefasRepository::TIPO_ENVIAR, 1, 'em_andamento');
                $this->subtarefasRepository->create($id, OficiosCartorioSubtarefasRepository::TIPO_JUNTAR, 2, 'pendente');
            }

            // Alocação automática por regra só para cumprimento pelo Cartório; CCM só aloca o cadastrante (specific).
            if ($forma === 'cartorio') {
                $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_OFICIOS);
                if ($regra !== null) {
                    $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
                    if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $criadoPor) {
                        $this->alocadosRepository->addAlocado($id, $usuarioIdRegra, OficiosCartorioAlocadosRepository::TIPO_RULE);
                    }
                }
            }
            if ($criadoPor !== null && $criadoPor !== '') {
                $this->alocadosRepository->addAlocado($id, $criadoPor, OficiosCartorioAlocadosRepository::TIPO_SPECIFIC);
            }

            $this->connection->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $atualizadoPor = null): array
    {
        $st = $this->subtarefasRepository->findById($subtarefaId);
        if ($st === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }
        $oficioId = trim((string) ($st['oficio_cartorio_id'] ?? ''));
        if ($oficioId === '') {
            throw new InvalidArgumentException('Ofício não identificado.');
        }
        $oficio = $this->repository->findById($oficioId);
        if ($oficio === null) {
            throw new InvalidArgumentException('Ofício não encontrado.');
        }
        if (($oficio['status'] ?? '') === 'cancelada') {
            throw new InvalidArgumentException('Ofício cancelado.');
        }
        if (($oficio['status'] ?? '') === 'finalizada') {
            throw new InvalidArgumentException('Ofício já finalizado.');
        }
        $ordem = (int) ($st['ordem'] ?? 0);
        $statusSt = (string) ($st['status'] ?? '');
        if ($statusSt === 'concluida') {
            throw new InvalidArgumentException('Esta subtarefa já foi concluída.');
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);

        if ($ordem === 1) {
            $s2 = $this->subtarefasRepository->findByOficioIdEOrdem($oficioId, 2);
            if ($s2 !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $s2['id'], 'em_andamento');
            }
            return ['tarefa_principal_concluida' => false];
        }
        if ($ordem === 2) {
            $this->repository->updateStatus($oficioId, 'finalizada', $atualizadoPor);
            return ['tarefa_principal_concluida' => true];
        }
        throw new InvalidArgumentException('Ordem de subtarefa inválida.');
    }

    public function cancel(string $id, ?string $atualizadoPor = null): bool
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $row = $this->repository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Ofício não encontrado.');
        }
        if (($row['status'] ?? '') === 'finalizada') {
            throw new InvalidArgumentException('Não é possível cancelar um ofício já finalizado.');
        }
        if (($row['status'] ?? '') === 'cancelada') {
            return true;
        }
        $this->subtarefasRepository->deleteByOficioId($id);
        return $this->repository->updateStatus($id, 'cancelada', $atualizadoPor);
    }

    /**
     * Exclusão física apenas se for o último número sequencial do ano.
     */
    public function delete(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador obrigatório.');
        }
        $row = $this->repository->findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Ofício não encontrado.');
        }
        $ano = (int) ($row['ano'] ?? 0);
        $seq = (int) ($row['numero_sequencial'] ?? 0);
        $max = $this->repository->getMaxSequencialForYear($ano);
        if ($seq !== $max || $max === 0) {
            throw new InvalidArgumentException('Só é possível excluir o último ofício criado no ano (maior número sequencial).');
        }
        $this->subtarefasRepository->deleteByOficioId($id);
        $deleted = $this->repository->delete($id);
        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Ofício excluído com sucesso.' : 'Nenhum registro removido.',
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
