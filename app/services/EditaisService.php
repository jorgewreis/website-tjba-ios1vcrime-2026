<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/EditaisRepository.php';
require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/EditaisAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';

final class EditaisService
{
    private PDO $connection;
    private EditaisRepository $repository;
    private EditaisSubtarefasRepository $subtarefasRepository;
    private EditaisAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;

    private const REGRA_ALOCAR_EDITAIS = 'ALLOCATE-EDITAIS';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new EditaisRepository($connection);
        $this->subtarefasRepository = new EditaisSubtarefasRepository($connection);
        $this->alocadosRepository = new EditaisAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
    }

    public function listEditais(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idEdital = (string) ($row['IdEdital'] ?? '');
            if ($idEdital !== '') {
                $this->subtarefasRepository->ensureQuatroSubtarefas($idEdital);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByEditalId($idEdital);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByEditalId($idEdital);
                $alocadosRaw = $this->alocadosRepository->findByEditalId($idEdital);
                $rows[$i]['Alocados'] = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $rows[$i]['Alocados'][] = [
                        'iniciais' => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id' => $a['usuario_id'] ?? '',
                        'tipo' => $this->normalizeTipoAlocado($a['tipo'] ?? ''),
                    ];
                }
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

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['processo'] ?? $payload['numero_processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $tipoEdital = trim((string) ($payload['tipo_edital'] ?? ''));
        if ($tipoEdital === '') {
            throw new InvalidArgumentException('O tipo de edital é obrigatório.');
        }

        $prazoDias = isset($payload['prazo_dias']) ? (int) $payload['prazo_dias'] : 0;
        if ($prazoDias < 1) {
            throw new InvalidArgumentException('O prazo do edital é obrigatório e deve ser de pelo menos 1 dia.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        if (strlen($digits) === 20) {
            $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
            $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
            $classeId = null;
            if ($classeCodigo !== '') {
                $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
                $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
            }
            $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);
        } else {
            $processoId = null;
            $existing = $this->processosRepository->findByNumero($numeroFormatado);
            if ($existing !== null) {
                $processoId = (string) $existing['id'];
            }
        }

        $idEdital = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'EDI',
            30
        );

        $row = [
            'id' => $idEdital,
            'processo_id' => $processoId,
            'status' => 'pendente',
            'tipo_edital' => $tipoEdital,
            'prazo_dias' => $prazoDias,
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $this->repository->insert($row);

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));

        if ($usuarioCadastroId !== '') {
            $this->alocadosRepository->addAlocado($idEdital, $usuarioCadastroId, EditaisAlocadosRepository::TIPO_SPECIFIC);
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_EDITAIS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '') {
                $this->alocadosRepository->addAlocado($idEdital, $usuarioIdRegra, EditaisAlocadosRepository::TIPO_RULE);
            }
        }

        return $idEdital;
    }

    public function criarPrimeiraSubtarefa(string $editalId, ?string $usuarioId = null): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            throw new InvalidArgumentException('Identificador do edital é obrigatório.');
        }
        if ($this->repository->findById($editalId) === null) {
            throw new InvalidArgumentException('Edital não encontrado.');
        }
        $existentes = $this->subtarefasRepository->findByEditalId($editalId);
        if (count($existentes) > 0) {
            $this->repository->update($editalId, ['status' => 'em_andamento']);
            return;
        }

        $this->subtarefasRepository->create($editalId, EditaisSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($editalId, EditaisSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->subtarefasRepository->create($editalId, EditaisSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);
        $this->subtarefasRepository->create($editalId, EditaisSubtarefasRepository::TIPO_ORDEM_4, 4, 'pendente', null);

        $this->repository->update($editalId, ['status' => 'em_andamento']);

        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($editalId, trim($usuarioId), EditaisAlocadosRepository::TIPO_SPECIFIC);
        }
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $editalId = trim((string) ($subtarefa['edital_id'] ?? ''));

        if ($editalId === '') {
            throw new InvalidArgumentException('Edital da subtarefa não identificado.');
        }

        $usuarioIdMovimentacao = null;
        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            }
        }
        if ($usuarioIdMovimentacao !== null && $usuarioIdMovimentacao !== '') {
            $this->alocadosRepository->addAlocado($editalId, $usuarioIdMovimentacao, EditaisAlocadosRepository::TIPO_SPECIFIC);
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        if ($ordem === 3) {
            $dadosArray = [];
            if ($dadosJson !== null && $dadosJson !== '') {
                $decoded = json_decode($dadosJson, true);
                if (is_array($decoded)) {
                    $dadosArray = $decoded;
                }
            }
            $dataPublicacao = trim((string) ($dadosArray['data_publicacao'] ?? ''));
            if ($dataPublicacao === '') {
                throw new InvalidArgumentException('Informe a data da publicação para concluir a certificação da publicação.');
            }
            $dtPublicacao = \DateTime::createFromFormat('Y-m-d', $dataPublicacao);
            if ($dtPublicacao === false || $dtPublicacao->format('Y-m-d') !== $dataPublicacao) {
                throw new InvalidArgumentException('Data da publicação inválida.');
            }
        }

        if ($ordem === 4) {
            $this->validarDecursoParaCertificacao($editalId);
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, 'concluida');

        $todas = $this->subtarefasRepository->findByEditalId($editalId);
        $proximaOrdem = $ordem + 1;
        $proximaEncontrada = null;
        foreach ($todas as $st) {
            if ((int) ($st['ordem'] ?? 0) === $proximaOrdem) {
                $proximaEncontrada = $st;
                break;
            }
        }

        if ($proximaEncontrada !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proximaEncontrada['id'], 'em_andamento');
            $this->repository->update($editalId, ['status' => 'em_andamento']);
            return ['tarefa_principal_concluida' => false];
        } else {
            $this->repository->update($editalId, ['status' => 'concluída']);
            $edital = $this->repository->findById($editalId);
            $processo = trim((string) ($edital['numero_processo'] ?? ''));
            $tipoEdital = trim((string) ($edital['tipo_edital'] ?? ''));
            $prazoDias = isset($edital['prazo_dias']) ? (int) $edital['prazo_dias'] : 0;
            $msg = 'Tarefa principal concluída automaticamente: edital do processo '
                . ($processo !== '' ? $processo : 'não informado')
                . ' (' . ($tipoEdital !== '' ? $tipoEdital : 'tipo não informado')
                . ', prazo de ' . ($prazoDias > 0 ? $prazoDias : 0) . ' dias).';
            return [
                'tarefa_principal_concluida' => true,
                'tarefa_principal_mensagem' => $msg,
            ];
        }
    }

    /**
     * Valida se o prazo do edital já decorreu (data_publicacao + prazo_dias <= hoje).
     * Usado ao concluir a subtarefa 4 (certificação de decurso do prazo).
     */
    private function validarDecursoParaCertificacao(string $editalId): void
    {
        $edital = $this->repository->findById($editalId);
        if ($edital === null) {
            throw new InvalidArgumentException('Edital não encontrado.');
        }
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        if ($prazoDias < 1) {
            throw new InvalidArgumentException('Edital sem prazo definido. Não é possível certificar o decurso.');
        }

        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId);
        $subtarefa3 = null;
        foreach ($subtarefas as $st) {
            if ((int) ($st['ordem'] ?? 0) === 3) {
                $subtarefa3 = $st;
                break;
            }
        }
        if ($subtarefa3 === null || trim((string) ($subtarefa3['status'] ?? '')) !== 'concluida') {
            throw new InvalidArgumentException('A certificação da publicação (subtarefa 3) deve estar concluída antes de certificar o decurso do prazo.');
        }

        $dadosJson = $subtarefa3['dados_especificos'] ?? null;
        $dataPublicacao = null;
        if ($dadosJson !== null && $dadosJson !== '') {
            $dados = json_decode($dadosJson, true);
            if (is_array($dados)) {
                $dataPublicacao = trim((string) ($dados['data_publicacao'] ?? ''));
            }
        }
        if ($dataPublicacao === '') {
            throw new InvalidArgumentException('Data da publicação não informada na subtarefa anterior. Não é possível calcular o decurso do prazo.');
        }

        $dtPub = \DateTime::createFromFormat('Y-m-d', $dataPublicacao);
        if ($dtPub === false) {
            throw new InvalidArgumentException('Data da publicação inválida.');
        }
        $dtPub->modify('+' . $prazoDias . ' days');
        $hoje = new \DateTime('today');
        if ($hoje < $dtPub) {
            $decursoFormatado = $dtPub->format('d/m/Y');
            throw new InvalidArgumentException('O prazo do edital ainda não decorreu. Só é possível certificar o decurso após ' . $decursoFormatado . '.');
        }
    }

    /**
     * Retorna informações para exibição do decurso do prazo (data limite e se já pode certificar).
     * data_publicacao (Y-m-d), data_limite_decurso (Y-m-d), data_limite_formatada (d/m/Y), prazo_cumprido (bool).
     */
    public function getDecursoInfo(string $editalId): array
    {
        $edital = $this->repository->findById($editalId);
        if ($edital === null) {
            return ['data_publicacao' => '', 'data_limite_decurso' => '', 'data_limite_formatada' => '', 'prazo_cumprido' => false];
        }
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId);
        $dataPublicacao = '';
        foreach ($subtarefas as $st) {
            if ((int) ($st['ordem'] ?? 0) === 3 && trim((string) ($st['status'] ?? '')) === 'concluida') {
                $dadosJson = $st['dados_especificos'] ?? null;
                if ($dadosJson !== null && $dadosJson !== '') {
                    $dados = json_decode($dadosJson, true);
                    if (is_array($dados)) {
                        $dataPublicacao = trim((string) ($dados['data_publicacao'] ?? ''));
                    }
                }
                break;
            }
        }
        if ($dataPublicacao === '' || $prazoDias < 1) {
            return ['data_publicacao' => $dataPublicacao, 'data_limite_decurso' => '', 'data_limite_formatada' => '', 'prazo_cumprido' => false];
        }
        $dtPub = \DateTime::createFromFormat('Y-m-d', $dataPublicacao);
        if ($dtPub === false) {
            return ['data_publicacao' => $dataPublicacao, 'data_limite_decurso' => '', 'data_limite_formatada' => '', 'prazo_cumprido' => false];
        }
        $dtPub->modify('+' . $prazoDias . ' days');
        $dataLimite = $dtPub->format('Y-m-d');
        $dataLimiteFormatada = $dtPub->format('d/m/Y');
        $hoje = new \DateTime('today');
        $prazoCumprido = $hoje >= $dtPub;
        return [
            'data_publicacao' => $dataPublicacao,
            'data_limite_decurso' => $dataLimite,
            'data_limite_formatada' => $dataLimiteFormatada,
            'prazo_cumprido' => $prazoCumprido,
        ];
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === EditaisAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
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

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $allowed = ['observacoes', 'status'];
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

    public function delete(string $id): array
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $subtarefas = $this->subtarefasRepository->findByEditalId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByEditalId($id);
        $this->alocadosRepository->deleteByEditalId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }
}
