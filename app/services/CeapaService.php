<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CeapaRepository.php';
require_once __DIR__ . '/../repositories/CeapaAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class CeapaService
{
    private PDO $connection;
    private CeapaRepository $repository;
    private CeapaAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private PartesRepository $partesRepository;
    private ClassesRepository $classesRepository;

    private const REGRA_ALOCAR_CEAPA = 'ALLOCATE-CEAPA';

    /** Ordem de exibição dos tipos de alocação. */
    private const TIPO_ORDER = ['rule' => 0, 'all' => 1, 'specific' => 2];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CeapaRepository($connection);
        $this->alocadosRepository = new CeapaAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    public function listTarefas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idTarefa = (string) ($row['id'] ?? '');
            if ($idTarefa !== '') {
                $alocadosRaw = $this->alocadosRepository->findByCeapaId($idTarefa);
                $alocados = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $tipo = $this->normalizeTipoAlocado((string) ($a['tipo'] ?? ''));
                    $alocados[] = [
                        'alocado_id'   => $a['id'] ?? '',
                        'iniciais'     => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id'   => $a['usuario_id'] ?? '',
                        'tipo'         => $tipo,
                    ];
                }
                usort($alocados, static function (array $a, array $b): int {
                    return (self::TIPO_ORDER[$a['tipo']] ?? 9) <=> (self::TIPO_ORDER[$b['tipo']] ?? 9);
                });
                $rows[$i]['Alocados'] = $alocados;
            } else {
                $rows[$i]['Alocados'] = [];
            }
        }
        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
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

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    public function getTipoSuspensaoCounts(array $filters): array
    {
        return $this->repository->countByTipoSuspensao($filters);
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        $tipoSuspensao = trim((string) ($payload['tipo_suspensao'] ?? ''));
        $decisaoCumprir = trim((string) ($payload['decisao_cumprir'] ?? ''));
        $dataDecisao = trim((string) ($payload['data_decisao'] ?? ''));
        $observacoes = trim((string) ($payload['observacoes_cumprimento'] ?? ''));
        $statusCumprimento = trim((string) ($payload['status_cumprimento'] ?? 'pendente'));
        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));

        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }
        if ($tipoSuspensao === '') {
            throw new InvalidArgumentException('O tipo de suspensão é obrigatório.');
        }
        if ($decisaoCumprir === '') {
            throw new InvalidArgumentException('A decisão a cumprir é obrigatória.');
        }
        if (!$this->isValidDate($dataDecisao)) {
            throw new InvalidArgumentException('A data da decisão é obrigatória e deve estar no formato AAAA-MM-DD.');
        }

        $statusCumprimento = $this->normalizeStatus($statusCumprimento, true);
        $numeroFormatado = $this->normalizeNumeroProcesso($numeroProcesso);

        [$processoOrigemId, ] = $this->resolveProcessoAndClasse($numeroFormatado, $payload);
        [$parteId, ] = $this->resolveParte($payload, $processoOrigemId);

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CPA',
            30
        );

        $this->repository->insert([
            'id' => $id,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'parte_id' => $parteId,
            'tipo_suspensao' => $tipoSuspensao,
            'decisao_cumprir' => $decisaoCumprir,
            'data_decisao' => $dataDecisao,
            'observacoes_cumprimento' => $observacoes !== '' ? $observacoes : null,
            'status_cumprimento' => $statusCumprimento,
            'usuario_cadastro_id' => $usuarioCadastroId !== '' ? $usuarioCadastroId : null,
        ]);

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CEAPA);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId) {
                $this->alocadosRepository->addAlocado($id, $usuarioIdRegra, CeapaAlocadosRepository::TIPO_RULE);
            }
        }
        if ($usuarioCadastroId !== '') {
            $this->alocadosRepository->addAlocado($id, $usuarioCadastroId, CeapaAlocadosRepository::TIPO_SPECIFIC);
        }

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador inválido para atualização.');
        }

        $existing = $this->repository->findById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Registro CEAPA não encontrado.');
        }

        $updates = [];
        $classeNomeExistente = trim((string) ($existing['classe_nome'] ?? ''));
        $classeCodigoExistente = trim((string) ($existing['classe_codigo'] ?? ''));

        $numeroOriginal = trim((string) ($existing['numero_processo_origem'] ?? ''));
        $numeroPayload = trim((string) ($payload['numero_processo_origem'] ?? ''));
        if ($numeroPayload !== '') {
            $numeroNovo = $this->normalizeNumeroProcesso($numeroPayload);
            if ($numeroOriginal !== '' && $numeroNovo !== $numeroOriginal) {
                throw new InvalidArgumentException('Não é permitido alterar o número do processo quando já houver valor cadastrado.');
            }
            $classeCodigoPayload = trim((string) ($payload['classe_codigo'] ?? ''));
            if ($classeNomeExistente !== '' && $classeNomeExistente !== 'Classe não informada') {
                if ($classeCodigoPayload !== '' && $classeCodigoPayload !== $classeCodigoExistente) {
                    throw new InvalidArgumentException('Não é permitido alterar a classe processual quando já houver valor cadastrado.');
                }
            }
            [$processoIdNovo, $numeroFormatado] = $this->resolveProcessoAndClasse($numeroNovo, $payload);
            $updates['processo_origem_id'] = $processoIdNovo;
            $updates['numero_processo_origem'] = $numeroFormatado;
        }

        if (array_key_exists('tipo_suspensao', $payload)) {
            $tipoSuspensao = trim((string) ($payload['tipo_suspensao'] ?? ''));
            if ($tipoSuspensao === '') {
                throw new InvalidArgumentException('O tipo de suspensão é obrigatório.');
            }
            $updates['tipo_suspensao'] = $tipoSuspensao;
        }

        if (array_key_exists('decisao_cumprir', $payload)) {
            $decisaoCumprir = trim((string) ($payload['decisao_cumprir'] ?? ''));
            if ($decisaoCumprir === '') {
                throw new InvalidArgumentException('A decisão a cumprir é obrigatória.');
            }
            $updates['decisao_cumprir'] = $decisaoCumprir;
        }

        if (array_key_exists('data_decisao', $payload)) {
            $dataDecisao = trim((string) ($payload['data_decisao'] ?? ''));
            if (!$this->isValidDate($dataDecisao)) {
                throw new InvalidArgumentException('A data da decisão deve estar no formato AAAA-MM-DD.');
            }
            $updates['data_decisao'] = $dataDecisao;
        }

        if (array_key_exists('observacoes_cumprimento', $payload)) {
            $observacoes = trim((string) ($payload['observacoes_cumprimento'] ?? ''));
            $updates['observacoes_cumprimento'] = $observacoes !== '' ? $observacoes : null;
        }

        if (array_key_exists('status_cumprimento', $payload)) {
            $updates['status_cumprimento'] = $this->normalizeStatus((string) $payload['status_cumprimento'], false);
        }

        $processoOrigemIdAtual = trim((string) ($updates['processo_origem_id'] ?? $existing['processo_origem_id'] ?? ''));
        if (
            array_key_exists('parte_id', $payload)
            || array_key_exists('nome_reu', $payload)
            || array_key_exists('reu_id', $payload)
        ) {
            [$parteId, ] = $this->resolveParte($payload, $processoOrigemIdAtual);
            $updates['parte_id'] = $parteId;
        }

        if ($updates === []) {
            return false;
        }

        return $this->repository->update($id, $updates);
    }

    public function delete(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('Identificador inválido para exclusão.');
        }

        return $this->repository->delete($id);
    }

    private function resolveProcessoAndClasse(string $numeroFormatado, array $payload): array
    {
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        $classeId = null;
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }

        $digits = preg_replace('/\D+/', '', $numeroFormatado);
        $processoOrigemId = null;
        if (strlen((string) $digits) === 20) {
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);
        }

        return [$processoOrigemId, $numeroFormatado];
    }

    private function resolveParte(array $payload, ?string $processoOrigemId): array
    {
        $parteId = trim((string) ($payload['parte_id'] ?? $payload['reu_id'] ?? ''));
        $nomeReu = trim((string) ($payload['nome_reu'] ?? ''));
        if ($nomeReu !== '') {
            $nomeReu = preg_replace('/\s*-\s*\d{3}\.?\d{3}\.?\d{3}-?\d{2}\s*$/u', '', $nomeReu) ?? $nomeReu;
            $nomeReu = TextFormatter::formatProperName($nomeReu, 120);
        }

        if ($parteId !== '') {
            $parteSelecionada = $this->partesRepository->findById($parteId);
            if ($parteSelecionada === null) {
                throw new InvalidArgumentException('Réu selecionado não encontrado.');
            }
            if ($processoOrigemId !== null && $processoOrigemId !== '') {
                $this->ensureVinculoParteProcesso($processoOrigemId, $parteId);
            }

            return [$parteId, (string) ($parteSelecionada['nome_completo'] ?? '')];
        }

        if ($nomeReu === '') {
            throw new InvalidArgumentException('O nome do réu é obrigatório.');
        }
        $parteId = $this->partesRepository->ensureParteByNomeOuCpf($nomeReu, null);
        if ($processoOrigemId !== null && $processoOrigemId !== '') {
            $this->ensureVinculoParteProcesso($processoOrigemId, $parteId);
        }

        return [$parteId, $nomeReu];
    }

    private function ensureVinculoParteProcesso(string $processoId, string $parteId): void
    {
        $existente = $this->processosPartesRepository->findByProcessoAndParte($processoId, $parteId);
        if ($existente !== null) {
            return;
        }
        $this->processosPartesRepository->addParte(
            $processoId,
            $parteId,
            ProcessosPartesRepository::TIPO_REU,
            ProcessosPartesRepository::POLO_PASSIVO
        );
    }

    private function normalizeNumeroProcesso(string $numeroProcesso): string
    {
        $numeroProcesso = trim($numeroProcesso);
        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if (strlen((string) $digits) === 20) {
            return $this->formatarCNJ((string) $digits);
        }

        return $numeroProcesso;
    }

    private function normalizeStatus(string $status, bool $allowDefault): string
    {
        $status = trim($status);
        if ($status === '' && $allowDefault) {
            return 'pendente';
        }
        $valid = ['pendente', 'em_andamento', 'finalizada'];
        if (!in_array($status, $valid, true)) {
            throw new InvalidArgumentException('Status de cumprimento inválido.');
        }

        return $status;
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

    private function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        return $dateObj !== false && $dateObj->format('Y-m-d') === $date;
    }
}
