<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/MedidasProtetivasRepository.php';
require_once __DIR__ . '/../repositories/MedidasProtetivasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/MedidasProtetivasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../services/CejuscService.php';
require_once __DIR__ . '/../services/ReuManager.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class MedidasProtetivasService
{
    private PDO $connection;
    private MedidasProtetivasRepository $repository;
    private MedidasProtetivasSubtarefasRepository $subtarefasRepository;
    private MedidasProtetivasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private PartesRepository $partesRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private ProcessoPrioridadesRepository $processoPrioridadesRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private CejuscService $cejuscService;
    private ReuManager $reuManager;

    private const REGRA_ALOCAR_MP = 'ALLOCATE-MEDIDASPROTETIVAS';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new MedidasProtetivasRepository($connection);
        $this->subtarefasRepository = new MedidasProtetivasSubtarefasRepository($connection);
        $this->alocadosRepository = new MedidasProtetivasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->processoPrioridadesRepository = new ProcessoPrioridadesRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->cejuscService = new CejuscService($connection);
        $this->reuManager = new ReuManager($connection);
    }

    public function listMedidasProtetivas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $index => $row) {
            $idMedida = (string) ($row['IdMedida'] ?? '');
            if ($idMedida === '') {
                $rows[$index]['Subtarefas'] = [];
                $rows[$index]['SubtarefasAll'] = [];
                $rows[$index]['Alocados'] = [];
                continue;
            }

            $this->subtarefasRepository->ensureDuasSubtarefas($idMedida);
            $rows[$index]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByMedidaId($idMedida);
            $rows[$index]['SubtarefasAll'] = $this->subtarefasRepository->findByMedidaId($idMedida);
            $alocadosRaw = $this->alocadosRepository->findByMedidaId($idMedida);
            $rows[$index]['Alocados'] = [];
            foreach (array_slice($alocadosRaw, 0, 5) as $alocado) {
                $rows[$index]['Alocados'][] = [
                    'iniciais' => $alocado['iniciais'] ?? '—',
                    'nome_completo' => $alocado['nome_completo'] ?? '',
                    'usuario_id' => $alocado['usuario_id'] ?? '',
                    'tipo' => $this->normalizeTipoAlocado((string) ($alocado['tipo'] ?? '')),
                ];
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

    public function find(string $id): ?array
    {
        $medida = $this->repository->findById($id);
        if ($medida === null) {
            return null;
        }

        $processoId = trim((string) ($medida['processo_id'] ?? ''));
        $medida['reu'] = [
            'parte_id' => $medida['parte_reu_id'] ?? '',
            'cpf' => $medida['reu_cpf'] ?? '',
            'nome_completo' => $medida['reu_nome_completo'] ?? '',
            'sexo' => $medida['reu_sexo'] ?? '',
            'data_nascimento' => $medida['reu_data_nascimento'] ?? '',
        ];
        $medida['vitimas'] = $processoId !== ''
            ? $this->processosPartesRepository->findByProcessoId($processoId, ProcessosPartesRepository::TIPO_VITIMA)
            : [];
        $medida['prioridades_processo'] = $processoId !== ''
            ? $this->processoPrioridadesRepository->findByProcessoId($processoId)
            : [];

        return $medida;
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['processo'] ?? $payload['numero_processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if ($digits === '') {
            throw new InvalidArgumentException('O número do processo é inválido.');
        }

        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;
        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }
        $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);

        $reuPayload = $payload['reu'] ?? null;
        if (!is_array($reuPayload)) {
            throw new InvalidArgumentException('Os dados do réu são obrigatórios.');
        }
        $reuData = $this->validatePartePayload($reuPayload, 'réu');

        $vitimas = $payload['vitimas'] ?? [];
        if (!is_array($vitimas)) {
            $vitimas = [];
        }

        $this->connection->beginTransaction();
        try {
            $parteReuId = $this->partesRepository->ensureParte(
                $reuData['cpf'],
                $reuData['nome_completo'],
                $reuData['sexo'],
                $reuData['data_nascimento']
            );
            $this->processosPartesRepository->addParte(
                $processoId,
                $parteReuId,
                ProcessosPartesRepository::TIPO_REU,
                ProcessosPartesRepository::POLO_PASSIVO
            );

            foreach ($vitimas as $index => $vitimaPayload) {
                if (!is_array($vitimaPayload)) {
                    throw new InvalidArgumentException('Formato inválido para vítima na posição ' . ($index + 1) . '.');
                }
                if ($this->isPartePayloadEmpty($vitimaPayload)) {
                    continue;
                }

                $vitimaData = $this->validatePartePayload($vitimaPayload, 'vítima #' . ($index + 1));
                $parteVitimaId = $this->partesRepository->ensureParte(
                    $vitimaData['cpf'],
                    $vitimaData['nome_completo'],
                    $vitimaData['sexo'],
                    $vitimaData['data_nascimento']
                );

                $flags = $this->extractPrioridadeFlags($vitimaPayload);
                $this->processosPartesRepository->addParte(
                    $processoId,
                    $parteVitimaId,
                    ProcessosPartesRepository::TIPO_VITIMA,
                    ProcessosPartesRepository::POLO_ATIVO,
                    $flags
                );

                foreach ($this->mapPrioridadesMarcadas($flags) as $prioridade) {
                    $this->processoPrioridadesRepository->insert($processoId, $prioridade);
                }
            }

            $reuIdLegacy = $this->reuManager->cadastrarReu($reuData['nome_completo'], $reuData['cpf']);
            if ($reuIdLegacy === null || trim($reuIdLegacy) === '') {
                throw new RuntimeException('Não foi possível criar ou localizar o réu para integração com o Cejusc.');
            }
            $this->reuManager->vincularReuProcesso($reuIdLegacy, $numeroFormatado);

            $medidaId = IdGenerator::generateUniquePrefixedId(
                $this->connection,
                $this->repository->getTableName(),
                'id',
                'MPR',
                30
            );

            $this->repository->insert([
                'id' => $medidaId,
                'processo_id' => $processoId,
                'parte_reu_id' => $parteReuId,
                'status' => 'pendente',
                'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
            ]);

            $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
            if ($usuarioCadastroId !== '') {
                $this->alocadosRepository->addAlocado($medidaId, $usuarioCadastroId, MedidasProtetivasAlocadosRepository::TIPO_ALL);
            }

            $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_MP);
            if ($regra !== null) {
                $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
                if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId) {
                    $this->alocadosRepository->addAlocado($medidaId, $usuarioIdRegra, MedidasProtetivasAlocadosRepository::TIPO_RULE);
                }
            }

            $cejuscId = $this->cejuscService->create([
                'numero_processo_origem' => $numeroFormatado,
                'reu_id' => $reuIdLegacy,
                'nome_reu' => $reuData['nome_completo'],
                'classe_codigo' => $classeCodigo,
                'classe_descricao' => $classeDescricao,
                'usuario_cadastro_id' => $usuarioCadastroId,
            ]);
            $this->repository->update($medidaId, ['cejusc_tarefa_id' => $cejuscId]);

            $this->connection->commit();
            return $medidaId;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function criarPrimeiraSubtarefa(string $medidaId, ?string $usuarioId = null): void
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            throw new InvalidArgumentException('Identificador da medida protetiva é obrigatório.');
        }
        if ($this->repository->findById($medidaId) === null) {
            throw new InvalidArgumentException('Medida protetiva não encontrada.');
        }

        $existentes = $this->subtarefasRepository->findByMedidaId($medidaId);
        if (count($existentes) > 0) {
            $this->repository->update($medidaId, ['status' => 'em_andamento']);
            return;
        }

        $this->subtarefasRepository->create($medidaId, MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($medidaId, MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->repository->update($medidaId, ['status' => 'em_andamento']);

        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($medidaId, trim($usuarioId), MedidasProtetivasAlocadosRepository::TIPO_SPECIFIC);
        }
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $medidaId = trim((string) ($subtarefa['medida_id'] ?? ''));
        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        if ($medidaId === '' || $ordem < 1) {
            throw new InvalidArgumentException('Subtarefa inválida.');
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (!is_array($dadosArray)) {
                throw new InvalidArgumentException('Dados específicos inválidos.');
            }
            $qtd = isset($dadosArray['qtd']) ? (int) $dadosArray['qtd'] : 0;
            if ($qtd < 1) {
                throw new InvalidArgumentException('Informe uma quantidade maior que zero para concluir a subtarefa.');
            }

            $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            if ($usuarioIdMovimentacao !== '') {
                $this->alocadosRepository->addAlocado($medidaId, $usuarioIdMovimentacao, MedidasProtetivasAlocadosRepository::TIPO_SPECIFIC);
            }
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        } else {
            throw new InvalidArgumentException('Os dados da subtarefa são obrigatórios.');
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, 'concluida');
        $subtarefas = $this->subtarefasRepository->findByMedidaId($medidaId);
        $proximaOrdem = $ordem + 1;
        $proxima = null;
        foreach ($subtarefas as $item) {
            if ((int) ($item['ordem'] ?? 0) === $proximaOrdem) {
                $proxima = $item;
                break;
            }
        }

        if ($proxima !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
            $this->repository->update($medidaId, ['status' => 'em_andamento']);
            return;
        }

        $this->repository->update($medidaId, ['status' => 'concluída']);
    }

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $allowed = ['observacoes', 'status', 'cejusc_tarefa_id'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }
        if (empty($data)) {
            return false;
        }

        return $this->repository->update($id, $data);
    }

    public function cancel(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }
        return $this->repository->update($id, ['status' => 'cancelada']);
    }

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $this->subtarefasRepository->deleteByMedidaId($id);
        $this->alocadosRepository->deleteByMedidaId($id);
        return $this->repository->delete($id);
    }

    private function validatePartePayload(array $payload, string $label): array
    {
        $cpf = preg_replace('/\D+/', '', (string) ($payload['cpf'] ?? ''));
        $nomeCompleto = TextFormatter::formatProperName((string) ($payload['nome_completo'] ?? ''), 255);
        $sexo = trim((string) ($payload['sexo'] ?? ''));
        $dataNascimento = trim((string) ($payload['data_nascimento'] ?? ''));

        if ($cpf === '') {
            throw new InvalidArgumentException('Informe o CPF para ' . $label . '.');
        }
        if ($nomeCompleto === '') {
            throw new InvalidArgumentException('Informe o nome completo para ' . $label . '.');
        }
        if ($sexo === '') {
            throw new InvalidArgumentException('Informe o sexo para ' . $label . '.');
        }
        if ($dataNascimento === '') {
            throw new InvalidArgumentException('Informe a data de nascimento para ' . $label . '.');
        }

        return [
            'cpf' => $cpf,
            'nome_completo' => $nomeCompleto,
            'sexo' => $sexo,
            'data_nascimento' => $dataNascimento,
        ];
    }

    private function extractPrioridadeFlags(array $payload): array
    {
        return [
            'prioridade_mulher' => !empty($payload['mulher']),
            'prioridade_crianca_adolescente' => !empty($payload['crianca_adolescente']),
            'prioridade_idoso' => !empty($payload['idoso']),
            'prioridade_pessoa_deficiencia' => !empty($payload['pessoa_deficiencia']),
        ];
    }

    private function mapPrioridadesMarcadas(array $flags): array
    {
        $prioridades = [];
        if (!empty($flags['prioridade_mulher'])) {
            $prioridades[] = 'mulher';
        }
        if (!empty($flags['prioridade_crianca_adolescente'])) {
            $prioridades[] = 'crianca_adolescente';
        }
        if (!empty($flags['prioridade_idoso'])) {
            $prioridades[] = 'idoso';
        }
        if (!empty($flags['prioridade_pessoa_deficiencia'])) {
            $prioridades[] = 'pessoa_deficiencia';
        }

        return $prioridades;
    }

    private function isPartePayloadEmpty(array $payload): bool
    {
        $keys = ['cpf', 'nome_completo', 'sexo', 'data_nascimento'];
        foreach ($keys as $key) {
            if (trim((string) ($payload[$key] ?? '')) !== '') {
                return false;
            }
        }

        return empty($payload['mulher']) && empty($payload['crianca_adolescente']) && empty($payload['idoso']) && empty($payload['pessoa_deficiencia']);
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        if ($tipo === MedidasProtetivasAlocadosRepository::TIPO_RULE || $tipo === 'rule') {
            return 'rule';
        }
        if ($tipo === MedidasProtetivasAlocadosRepository::TIPO_ALL || $tipo === 'all') {
            return 'all';
        }

        return 'specific';
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
}
