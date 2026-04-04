<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/EditaisRepository.php';
require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/EditaisAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/FeriadosRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartorioProdutividade.php';

final class EditaisService
{
    private PDO $connection;
    private EditaisRepository $repository;
    private EditaisSubtarefasRepository $subtarefasRepository;
    private EditaisAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private FeriadosRepository $feriadosRepository;

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
        $this->feriadosRepository = new FeriadosRepository($connection);
    }

    public function listEditais(array $filters, int $limit, int $offset): array
    {
        // Remove eventos de data única/móvel já vencidos para manter base de feriados enxuta.
        $this->feriadosRepository->purgeExpiradosUnicosOuMoveis(new \DateTimeImmutable('today'));
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idEdital = (string) ($row['IdEdital'] ?? '');
            if ($idEdital !== '') {
                $this->subtarefasRepository->ensureQuatroSubtarefas($idEdital);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByEditalId($idEdital);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByEditalId($idEdital);
                $alocadosRaw = $this->alocadosRepository->findByEditalId($idEdital);
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
                    static $order = ['rule' => 0, 'all' => 1, 'specific' => 2];
                    return ($order[$a['tipo']] ?? 9) <=> ($order[$b['tipo']] ?? 9);
                });
                $rows[$i]['Alocados'] = $alocados;
                $rows[$i]['DecursoInfo'] = $this->getDecursoInfo($idEdital);
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['Alocados'] = [];
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

        if ($usuarioCadastroId !== '' && $usuarioCadastroId !== 'sistema') {
            CartorioProdutividade::record($this->connection, $usuarioCadastroId, 'cartorio-editais.create', [
                'referencia_item_id' => $idEdital,
                'referencia_sistema' => 'cartorio-editais',
                'numero_processo' => $numeroFormatado,
                'classe_processo' => $tipoEdital,
                'tipo_edital' => $tipoEdital,
                'prazo_dias' => (string) $prazoDias,
            ]);
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
            try {
                $this->alocadosRepository->addAlocado($editalId, $usuarioIdMovimentacao, EditaisAlocadosRepository::TIPO_SPECIFIC);
            } catch (\RuntimeException $e) {
                // limite ou duplicata — não impede a conclusão da subtarefa
            }
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

        if ($ordem === 4) {
            $this->validarDecursoParaCertificacao($editalId);
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, 'concluida');
        $tipoSubtarefa = trim((string) ($subtarefa['tipo'] ?? ''));
        if ($usuarioIdMovimentacao !== null && $usuarioIdMovimentacao !== '' && $usuarioIdMovimentacao !== 'sistema' && $tipoSubtarefa !== '') {
            $editalAtual = $this->repository->findById($editalId) ?? [];
            $tipoEditalRow = trim((string) ($editalAtual['tipo_edital'] ?? ''));
            $prazoRow = (int) ($editalAtual['prazo_dias'] ?? 0);
            $dataPub = trim((string) ($editalAtual['data_publicacao'] ?? ''));
            $dataDecurso = trim((string) ($editalAtual['data_decurso_prazo'] ?? ''));
            CartorioProdutividade::record($this->connection, $usuarioIdMovimentacao, 'cartorio-editais.subtarefa.' . $tipoSubtarefa, [
                'referencia_item_id' => $subtarefaId,
                'referencia_sistema' => 'cartorio-editais',
                'numero_processo' => (string) ($editalAtual['numero_processo'] ?? ''),
                'classe_processo' => CartorioProdutividade::nonEmpty($tipoEditalRow, 'tipo não informado'),
                'tipo_edital' => CartorioProdutividade::nonEmpty($tipoEditalRow, 'tipo não informado'),
                'prazo_dias' => (string) max(0, $prazoRow),
                'data_publicacao' => CartorioProdutividade::nonEmpty($dataPub, 'data não informada'),
                'data_decurso_prazo' => CartorioProdutividade::nonEmpty($dataDecurso, 'data não informada'),
            ]);
        }

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

        $dataPublicacao = trim((string) ($edital['data_publicacao'] ?? ''));
        if ($dataPublicacao === '') {
            $dadosJson = $subtarefa3['dados_especificos'] ?? null;
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
        $dtPub = \DateTime::createFromFormat('Y-m-d', $dataDecurso);
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
        $dtDecurso = \DateTime::createFromFormat('Y-m-d', $dataDecurso);
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
        $hoje = new \DateTimeImmutable('today');
        $dtDecursoImutavel = \DateTimeImmutable::createFromMutable($dtDecurso);
        $prazoCumprido = $hoje >= $dtDecursoImutavel;
        $liberaConclusao = $hoje > $dtDecursoImutavel;
        $diasRestantes = 0;
        if ($hoje < $dtDecursoImutavel) {
            $diasRestantes = (int) $hoje->diff($dtDecursoImutavel)->days;
        }
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
        $unicos = [];
        foreach ($intervalos as $par) {
            if (!is_array($par) || count($par) < 2) {
                continue;
            }
            $ini = trim((string) ($par[0] ?? ''));
            $fim = trim((string) ($par[1] ?? ''));
            if ($ini === '' || $fim === '' || $fim < $ini) {
                continue;
            }
            foreach ($this->repository->findIdsComDecursoPorSobreposicaoFeriado($ini, $fim) as $id) {
                $id = trim((string) $id);
                if ($id !== '') {
                    $unicos[$id] = true;
                }
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
     * Data de publicação na tabela `editais` ou, se vazia, em dados_especificos da subtarefa 3 concluída.
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
            if ((int) ($st['ordem'] ?? 0) === 3 && trim((string) ($st['status'] ?? '')) === 'concluida') {
                $dadosJson = $st['dados_especificos'] ?? null;
                if ($dadosJson !== null && $dadosJson !== '') {
                    $dados = json_decode((string) $dadosJson, true);
                    if (is_array($dados)) {
                        return trim((string) ($dados['data_publicacao'] ?? ''));
                    }
                }
                break;
            }
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

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === EditaisAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === 'all') {
            return 'all';
        }
        return 'specific';
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
