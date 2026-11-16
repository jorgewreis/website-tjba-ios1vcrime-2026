<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/EditaisRepository.php';
require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/EditaisAlocadosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/FeriadosRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartorioTarefaStatusPrincipalHelper.php';

final class EditaisService
{
    private PDO $connection;
    private EditaisRepository $repository;
    private EditaisSubtarefasRepository $subtarefasRepository;
    private EditaisAlocadosRepository $alocadosRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private FeriadosRepository $feriadosRepository;


    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new EditaisRepository($connection);
        $this->subtarefasRepository = new EditaisSubtarefasRepository($connection);
        $this->alocadosRepository = new EditaisAlocadosRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->feriadosRepository = new FeriadosRepository($connection);
    }

    public function listEditais(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idEdital = (string) ($row['IdEdital'] ?? '');
            if ($idEdital !== '') {
                $this->subtarefasRepository->ensureSubtarefasAtivas($idEdital);
                $this->tentarFinalizacaoAutomaticaPorDecurso($idEdital);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByEditalId($idEdital);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByEditalId($idEdital);
                $editalAtualizado = $this->repository->findById($idEdital);
                if ($editalAtualizado !== null) {
                    $rows[$i]['Status'] = (string) ($editalAtualizado['status'] ?? $rows[$i]['Status'] ?? '');
                }
                $rows[$i]['DecursoInfo'] = $this->getDecursoInfo($idEdital);
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['DecursoInfo'] = [
                    'data_publicacao' => '',
                    'data_inicio_contagem' => '',
                    'data_decurso_prazo' => '',
                    'data_limite_formatada' => '',
                    'prazo_cumprido' => false,
                    'libera_conclusao' => false,
                    'dias_restantes' => 0,
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
            $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, null);
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

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }

        $this->repository->insert($row);

        return $idEdital;
    }

    public function criarPrimeiraSubtarefa(string $editalId): void
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
            require_once __DIR__ . '/../helpers/EditaisUiCopy.php';
            $status = EditaisUiCopy::statusPrincipalFromSubtarefas($existentes);
            if ($status === 'concluída' || $status === 'concluida') {
                $status = 'concluída';
            }
            $this->repository->update($editalId, ['status' => $status]);
            $this->tentarFinalizacaoAutomaticaPorDecurso($editalId);
            return;
        }

        $repo = $this->subtarefasRepository;
        $repo->create($editalId, $repo::TIPO_PUBLICACAO, $repo::ORDEM_PUBLICACAO, 'em_andamento', null);
        $repo->create($editalId, $repo::TIPO_DECURSO, $repo::ORDEM_DECURSO, 'pendente', null);

        require_once __DIR__ . '/../helpers/EditaisUiCopy.php';
        $this->repository->update($editalId, ['status' => EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO]);

    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);

        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $tipoSub = trim((string) ($subtarefa['tipo'] ?? ''));
        if ($tipoSub === EditaisSubtarefasRepository::TIPO_PUBLICACAO) {
            $ordem = EditaisSubtarefasRepository::ORDEM_PUBLICACAO;
        } elseif ($tipoSub === EditaisSubtarefasRepository::TIPO_DECURSO) {
            $ordem = EditaisSubtarefasRepository::ORDEM_DECURSO;
        } elseif ($ordem === 3) {
            $ordem = EditaisSubtarefasRepository::ORDEM_PUBLICACAO;
        } elseif ($ordem === 4) {
            $ordem = EditaisSubtarefasRepository::ORDEM_DECURSO;
        }
        $editalId = trim((string) ($subtarefa['edital_id'] ?? ''));

        if ($editalId === '') {
            throw new InvalidArgumentException('Edital da subtarefa não identificado.');
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        if ($ordem === EditaisSubtarefasRepository::ORDEM_PUBLICACAO) {
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
            $edital = $this->repository->findById($editalId);
            if ($edital === null) {
                throw new InvalidArgumentException('Edital não encontrado para calcular o decurso.');
            }
            $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
            if ($prazoDias < 1) {
                throw new InvalidArgumentException('Prazo do edital inválido para calcular o decurso.');
            }
            [$inicioContagem, $dataDecurso] = $this->calcularInicioEDataDecurso($dataPublicacao, $prazoDias);
            $this->repository->update($editalId, [
                'data_publicacao' => $dataPublicacao,
                'data_inicio_contagem' => $inicioContagem,
                'data_decurso_prazo' => $dataDecurso,
            ]);
        }

        if ($ordem === EditaisSubtarefasRepository::ORDEM_DECURSO) {
            $this->validarDecursoParaCertificacao($editalId);
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, 'concluida');

        require_once __DIR__ . '/../helpers/EditaisUiCopy.php';
        $todas = $this->subtarefasRepository->findByEditalId($editalId);
        $proximaEncontrada = null;
        if ($ordem === EditaisSubtarefasRepository::ORDEM_PUBLICACAO) {
            foreach ($todas as $st) {
                if ((int) ($st['ordem'] ?? 0) === EditaisSubtarefasRepository::ORDEM_DECURSO
                    || (string) ($st['tipo'] ?? '') === EditaisSubtarefasRepository::TIPO_DECURSO) {
                    $proximaEncontrada = $st;
                    break;
                }
            }
        }

        if ($proximaEncontrada !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proximaEncontrada['id'], 'em_andamento');
            $this->repository->update($editalId, ['status' => EditaisUiCopy::STATUS_AGUARDANDO_DECURSO]);
            $this->tentarFinalizacaoAutomaticaPorDecurso($editalId);

            return ['tarefa_principal_concluida' => false];
        }

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

    /**
     * Conclui automaticamente decurso e tarefa principal quando o prazo já encerrou (dia após data de decurso).
     */
    public function tentarFinalizacaoAutomaticaPorDecurso(string $editalId): bool
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return false;
        }

        $edital = $this->repository->findById($editalId);
        if ($edital === null) {
            return false;
        }

        $status = strtolower(trim((string) ($edital['status'] ?? '')));
        if ($status === 'concluída') {
            $status = 'concluida';
        }
        if (in_array($status, ['concluida', 'cancelada'], true)) {
            return false;
        }

        $decurso = $this->getDecursoInfo($editalId);
        if (empty($decurso['libera_conclusao'])) {
            return false;
        }

        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId);
        $subPublicacao = null;
        $subDecurso = null;
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordemSt = (int) ($st['ordem'] ?? 0);
            if ($tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO || $ordemSt === EditaisSubtarefasRepository::ORDEM_PUBLICACAO || $ordemSt === 3) {
                $subPublicacao = $st;
            }
            if ($tipo === EditaisSubtarefasRepository::TIPO_DECURSO || $ordemSt === EditaisSubtarefasRepository::ORDEM_DECURSO || $ordemSt === 4) {
                $subDecurso = $st;
            }
        }

        if ($subPublicacao === null) {
            return false;
        }

        $statusPub = strtolower(trim((string) ($subPublicacao['status'] ?? '')));
        if ($statusPub === 'concluída') {
            $statusPub = 'concluida';
        }
        if ($statusPub !== 'concluida') {
            return false;
        }

        if ($subDecurso !== null) {
            $statusDec = strtolower(trim((string) ($subDecurso['status'] ?? '')));
            if ($statusDec === 'concluída') {
                $statusDec = 'concluida';
            }
            if ($statusDec !== 'concluida') {
                $this->subtarefasRepository->atualizarStatus((string) $subDecurso['id'], 'concluida');
            }
        }

        $this->repository->update($editalId, ['status' => 'concluída']);

        return true;
    }

    /**
     * Valida se o prazo do edital já decorreu (data_publicacao + prazo_dias <= hoje).
     * Usado ao concluir a subtarefa de decurso do prazo.
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
        $subPublicacao = null;
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordemSt = (int) ($st['ordem'] ?? 0);
            if ($tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO || $ordemSt === EditaisSubtarefasRepository::ORDEM_PUBLICACAO || $ordemSt === 3) {
                $subPublicacao = $st;
                break;
            }
        }
        $statusPub = strtolower(trim((string) ($subPublicacao['status'] ?? '')));
        if ($statusPub === 'concluída') {
            $statusPub = 'concluida';
        }
        if ($subPublicacao === null || $statusPub !== 'concluida') {
            throw new InvalidArgumentException('A certificação da publicação deve estar concluída antes de certificar o decurso do prazo.');
        }

        $dataPublicacao = trim((string) ($edital['data_publicacao'] ?? ''));
        if ($dataPublicacao === '') {
            $dadosJson = $subPublicacao['dados_especificos'] ?? null;
            if ($dadosJson !== null && $dadosJson !== '') {
                $dados = json_decode($dadosJson, true);
                if (is_array($dados)) {
                    $dataPublicacao = trim((string) ($dados['data_publicacao'] ?? ''));
                }
            }
        }
        if ($dataPublicacao === '') {
            throw new InvalidArgumentException('Data da publicação não informada na subtarefa anterior. Não é possível calcular o decurso do prazo.');
        }

        $dataDecurso = trim((string) ($edital['data_decurso_prazo'] ?? ''));
        if ($dataDecurso === '') {
            throw new InvalidArgumentException('Data de decurso não calculada. Refaça a certificação da publicação.');
        }
        $dtPub = \DateTime::createFromFormat('!Y-m-d', $dataDecurso);
        if ($dtPub === false || $dtPub->format('Y-m-d') !== $dataDecurso) {
            throw new InvalidArgumentException('Data do decurso inválida.');
        }
        $hoje = new \DateTime('today');
        if ($hoje <= $dtPub) {
            $decursoFormatado = $dtPub->format('d/m/Y');
            throw new InvalidArgumentException('O prazo do edital ainda não decorreu. Só é possível certificar o decurso a partir do dia seguinte a ' . $decursoFormatado . '.');
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
            return [
                'data_publicacao' => '',
                'data_inicio_contagem' => '',
                'data_limite_decurso' => '',
                'data_limite_formatada' => '',
                'prazo_cumprido' => false,
                'libera_conclusao' => false,
                'dias_restantes' => 0,
            ];
        }
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        $dataPublicacao = $this->resolverDataPublicacaoParaDecurso($edital);
        $dataInicioContagem = trim((string) ($edital['data_inicio_contagem'] ?? ''));
        $dataDecurso = trim((string) ($edital['data_decurso_prazo'] ?? ''));
        if ($dataPublicacao === '' || $prazoDias < 1) {
            return [
                'data_publicacao' => $dataPublicacao,
                'data_inicio_contagem' => '',
                'data_limite_decurso' => '',
                'data_limite_formatada' => '',
                'prazo_cumprido' => false,
                'libera_conclusao' => false,
                'dias_restantes' => 0,
            ];
        }

        if ($dataInicioContagem === '' || $dataDecurso === '') {
            [$dataInicioContagem, $dataDecurso] = $this->calcularInicioEDataDecurso($dataPublicacao, $prazoDias);
            $this->repository->update($editalId, [
                'data_inicio_contagem' => $dataInicioContagem,
                'data_decurso_prazo' => $dataDecurso,
            ]);
        }
        $dtDecurso = \DateTime::createFromFormat('!Y-m-d', $dataDecurso);
        if ($dtDecurso === false || $dtDecurso->format('Y-m-d') !== $dataDecurso) {
            return [
                'data_publicacao' => $dataPublicacao,
                'data_inicio_contagem' => $dataInicioContagem,
                'data_limite_decurso' => '',
                'data_limite_formatada' => '',
                'prazo_cumprido' => false,
                'libera_conclusao' => false,
                'dias_restantes' => 0,
            ];
        }
        $dtDecursoImutavel = \DateTimeImmutable::createFromMutable($dtDecurso);
        [$prazoCumprido, $liberaConclusao, $diasRestantes] = self::estadoDecurso($dtDecursoImutavel, new \DateTimeImmutable('now'));
        return [
            'data_publicacao' => $dataPublicacao,
            'data_inicio_contagem' => $dataInicioContagem,
            'data_limite_decurso' => $dataDecurso,
            'data_limite_formatada' => $dtDecursoImutavel->format('d/m/Y'),
            'prazo_cumprido' => $prazoCumprido,
            'libera_conclusao' => $liberaConclusao,
            'dias_restantes' => $diasRestantes,
        ];
    }

    /** Compara dias civis no fuso configurado, sem antecipar a certificação. */
    private static function estadoDecurso(\DateTimeImmutable $dataLimite, \DateTimeImmutable $agora): array
    {
        $hoje = $agora->setTime(0, 0);
        $limite = $dataLimite->setTime(0, 0);
        return [$hoje >= $limite, $hoje > $limite, $hoje < $limite ? (int) $hoje->diff($limite)->days : 0];
    }

    /**
     * Recalcula e persiste `data_inicio_contagem` e `data_decurso_prazo` nos editais em fluxo
     * cujo intervalo de contagem intercepta o período do feriado (inclusive extremos).
     */
    public function recalcularDecursosPorSobreposicaoFeriado(string $ferIni, string $ferFim): int
    {
        $ids = $this->repository->findIdsComDecursoPorSobreposicaoFeriado($ferIni, $ferFim);

        return $this->recalcularDecursoParaEditalIds($ids);
    }

    /**
     * @param list<array{0:string,1:string}> $intervalos Lista de [data_inicio, data_fim] do feriado (Y-m-d)
     */
    public function recalcularDecursosPorIntervalosFeriado(array $intervalos): int
    {
        $intervalosValidos = [];
        foreach ($intervalos as $par) {
            if (!is_array($par) || count($par) < 2) {
                continue;
            }
            $ini = trim((string) ($par[0] ?? ''));
            $fim = trim((string) ($par[1] ?? ''));
            if ($ini === '' || $fim === '' || $fim < $ini) {
                continue;
            }
            $intervalosValidos[$ini . '/' . $fim] = [$ini, $fim];
        }
        if ($intervalosValidos === []) {
            return 0;
        }

        $ids = $this->repository->findIdsComDecursoPorIntervalosFeriado(array_values($intervalosValidos));
        $unicos = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $unicos[$id] = true;
            }
        }

        return $this->recalcularDecursoParaEditalIds(array_keys($unicos));
    }

    /**
     * @param list<string> $ids
     */
    public function recalcularDecursoParaEditalIds(array $ids): int
    {
        $n = 0;
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            if ($this->recalcularESalvarDecursoEdital($id)) {
                $n++;
            }
        }

        return $n;
    }

    private function recalcularESalvarDecursoEdital(string $editalId): bool
    {
        $edital = $this->repository->findById($editalId);
        if ($edital === null) {
            return false;
        }
        $dataPublicacao = $this->resolverDataPublicacaoParaDecurso($edital);
        $prazoDias = (int) ($edital['prazo_dias'] ?? 0);
        if ($dataPublicacao === '' || $prazoDias < 1) {
            return false;
        }
        try {
            [$inicioContagem, $dataDecurso] = $this->calcularInicioEDataDecurso($dataPublicacao, $prazoDias);
        } catch (InvalidArgumentException $e) {
            return false;
        }
        $this->repository->update($editalId, [
            'data_inicio_contagem' => $inicioContagem,
            'data_decurso_prazo' => $dataDecurso,
        ]);

        return true;
    }

    /**
     * Data de publicação na tabela `editais` ou, se vazia, em dados_especificos da subtarefa de publicação concluída.
     */
    private function resolverDataPublicacaoParaDecurso(array $edital): string
    {
        $dataPublicacao = trim((string) ($edital['data_publicacao'] ?? ''));
        if ($dataPublicacao !== '') {
            return $dataPublicacao;
        }
        $editalId = trim((string) ($edital['id'] ?? ''));
        if ($editalId === '') {
            return '';
        }
        $subtarefas = $this->subtarefasRepository->findByEditalId($editalId);
        foreach ($subtarefas as $st) {
            $tipo = trim((string) ($st['tipo'] ?? ''));
            $ordemSt = (int) ($st['ordem'] ?? 0);
            $statusSt = strtolower(trim((string) ($st['status'] ?? '')));
            if ($statusSt === 'concluída') {
                $statusSt = 'concluida';
            }
            $ehPublicacao = $tipo === EditaisSubtarefasRepository::TIPO_PUBLICACAO
                || $ordemSt === EditaisSubtarefasRepository::ORDEM_PUBLICACAO
                || $ordemSt === 3;
            if (!$ehPublicacao || $statusSt !== 'concluida') {
                continue;
            }
            $dadosJson = $st['dados_especificos'] ?? null;
            if ($dadosJson !== null && $dadosJson !== '') {
                $dados = json_decode((string) $dadosJson, true);
                if (is_array($dados)) {
                    return trim((string) ($dados['data_publicacao'] ?? ''));
                }
            }
            break;
        }

        return '';
    }

    /**
     * Regras definidas:
     * - início = dia seguinte da publicação, ajustado para próximo dia útil;
     * - contagem processual = dia inicial não conta => soma exatamente prazo_dias ao início;
     * - vencimento ajustado para próximo dia útil se cair em sábado/domingo/feriado.
     *
     * @return array{0:string,1:string} [data_inicio_contagem, data_decurso_prazo]
     */
    private function calcularInicioEDataDecurso(string $dataPublicacao, int $prazoDias): array
    {
        $publicacao = \DateTimeImmutable::createFromFormat('Y-m-d', $dataPublicacao);
        if ($publicacao === false || $publicacao->format('Y-m-d') !== $dataPublicacao) {
            throw new InvalidArgumentException('Data da publicação inválida para cálculo do decurso.');
        }

        $inicio = $publicacao->modify('+1 day');
        $inicio = $this->proximoDiaUtil($inicio);

        $decurso = $inicio->modify('+' . $prazoDias . ' days');
        $decurso = $this->proximoDiaUtil($decurso);

        return [$inicio->format('Y-m-d'), $decurso->format('Y-m-d')];
    }

    private function proximoDiaUtil(\DateTimeImmutable $data): \DateTimeImmutable
    {
        $cursor = $data;
        while ($this->isDiaNaoUtil($cursor)) {
            $cursor = $cursor->modify('+1 day');
        }
        return $cursor;
    }

    private function isDiaNaoUtil(\DateTimeImmutable $data): bool
    {
        $diaSemana = (int) $data->format('N');
        if ($diaSemana >= 6) {
            return true;
        }

        return $this->feriadosRepository->isDiaNaoUtil($data);
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
