<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CejuscRepository.php';
require_once __DIR__ . '/../repositories/CejuscSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CejuscAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartorioProdutividade.php';
require_once __DIR__ . '/../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../helpers/CartorioTarefaStatusPrincipalHelper.php';

final class CejuscService
{
    private PDO $connection;
    private CejuscRepository $repository;
    private CejuscSubtarefasRepository $subtarefasRepository;
    private CejuscAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private PartesRepository $partesRepository;
    private ClassesRepository $classesRepository;

    private const REGRA_ALOCAR_CEJUSC = 'ALLOCATE-CEJUSC';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CejuscRepository($connection);
        $this->subtarefasRepository = new CejuscSubtarefasRepository($connection);
        $this->alocadosRepository = new CejuscAlocadosRepository($connection);
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
            $idTarefa = (string) ($row['IdCarta'] ?? '');
            if ($idTarefa !== '') {
                $this->subtarefasRepository->ensureFluxoCompleto($idTarefa);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByCejuscId($idTarefa);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByCejuscId($idTarefa);
                $alocadosRaw = $this->alocadosRepository->findByCejuscId($idTarefa);
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
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $parteId = trim((string) ($payload['parte_id'] ?? $payload['reu_id'] ?? ''));
        $nomeReu = trim((string) ($payload['nome_reu'] ?? ''));
        if ($nomeReu !== '') {
            // Se o label veio como "Nome - 000.000.000-00", mantém apenas o nome.
            $nomeReu = preg_replace('/\s*-\s*\d{3}\.?\d{3}\.?\d{3}-?\d{2}\s*$/u', '', $nomeReu) ?? $nomeReu;
        }
        if ($nomeReu !== '') {
            $nomeReu = TextFormatter::formatProperName($nomeReu, 120);
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        $processoOrigemId = null;
        if (strlen($digits) === 20) {
            $classeId = null;
            $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
            $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
            if ($classeCodigo !== '') {
                $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
                $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
            }
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);
        }

        if ($parteId !== '') {
            $parteSelecionada = $this->partesRepository->findById($parteId);
            if ($parteSelecionada !== null) {
                $nomeParteSelecionada = trim((string) ($parteSelecionada['nome_completo'] ?? ''));
                if ($nomeParteSelecionada !== '') {
                    $nomeReu = TextFormatter::formatProperName($nomeParteSelecionada, 120);
                }
            }
            if ($processoOrigemId !== null) {
                $existente = $this->processosPartesRepository->findByProcessoAndParte($processoOrigemId, $parteId);
                if ($existente === null) {
                    $this->processosPartesRepository->addParte(
                        $processoOrigemId,
                        $parteId,
                        ProcessosPartesRepository::TIPO_REQUERIDO,
                        ProcessosPartesRepository::POLO_PASSIVO
                    );
                }
            }
        } else {
            if ($nomeReu === '') {
                throw new InvalidArgumentException('O nome do réu é obrigatório.');
            }
            $parteId = $this->partesRepository->ensureParteByNomeOuCpf($nomeReu, null);
            $parteCriadaOuEncontrada = $this->partesRepository->findById($parteId);
            if ($parteCriadaOuEncontrada !== null) {
                $nomeParte = trim((string) ($parteCriadaOuEncontrada['nome_completo'] ?? ''));
                if ($nomeParte !== '') {
                    $nomeReu = TextFormatter::formatProperName($nomeParte, 120);
                }
            }
            if ($processoOrigemId !== null) {
                $existente = $this->processosPartesRepository->findByProcessoAndParte($processoOrigemId, $parteId);
                if ($existente === null) {
                    $this->processosPartesRepository->addParte(
                        $processoOrigemId,
                        $parteId,
                        ProcessosPartesRepository::TIPO_REQUERIDO,
                        ProcessosPartesRepository::POLO_PASSIVO
                    );
                }
            }
        }

        $idTarefa = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CJT',
            30
        );

        $row = [
            'id' => $idTarefa,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'parte_id' => $parteId,
            'status' => 'pendente',
        ];

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }

        $this->repository->insert($row);

        if ($usuarioCadastroId !== ''
            && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioCadastroId)) {
            $this->alocadosRepository->addAlocado($idTarefa, $usuarioCadastroId, CejuscAlocadosRepository::TIPO_ALL);
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CEJUSC);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId
                && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioIdRegra)) {
                $this->alocadosRepository->addAlocado($idTarefa, $usuarioIdRegra, CejuscAlocadosRepository::TIPO_RULE);
            }
        }

        AlocacaoTarefasPerfisHelper::registrarAlocacoesDirecionadasNoCadastro(
            $this->connection,
            $usuarioCadastroId,
            $payload,
            function (string $uid) use ($idTarefa): void {
                try {
                    $this->alocadosRepository->addAlocado($idTarefa, $uid, CejuscAlocadosRepository::TIPO_SPECIFIC);
                } catch (Throwable $e) {
                    // limite ou duplicata
                }
            }
        );

        $this->subtarefasRepository->create($idTarefa, CejuscSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($idTarefa, CejuscSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->subtarefasRepository->create($idTarefa, CejuscSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);

        $this->repository->updateStatus($idTarefa, 'pendente');

        if ($usuarioCadastroId !== '' && $usuarioCadastroId !== 'sistema') {
            $tarefaRow = $this->repository->findById($idTarefa);
            $reuNome = $tarefaRow !== null
                ? trim((string) ($tarefaRow['reu_nome'] ?? ''))
                : '';
            $reuNomeObs = CartorioProdutividade::nonEmpty($reuNome, 'réu não informado');
            CartorioProdutividade::record($this->connection, $usuarioCadastroId, 'cartorio-cejusc.create', [
                'referencia_item_id' => $idTarefa,
                'referencia_sistema' => 'cartorio-cejusc',
                'numero_processo' => $numeroFormatado,
                'classe_processo' => $reuNomeObs,
                'reu_nome' => $reuNomeObs,
            ]);
        }

        return $idTarefa;
    }

    /**
     * Cria tarefa Cejusc vinculada à Medida Protetiva quando não houver duplicidade
     * (mesmo número de processo e mesmo réu/parte).
     *
     * @return array{id: string, created: bool}
     */
    public function createFromMedidaIfNotExists(array $payload): array
    {
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        $parteId = trim((string) ($payload['parte_id'] ?? $payload['reu_id'] ?? ''));
        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        if ($numeroFormatado !== '' && $parteId !== '') {
            $existente = $this->repository->findByProcessoEParte($numeroFormatado, $parteId);
            if ($existente !== null) {
                return [
                    'id' => (string) ($existente['id'] ?? ''),
                    'created' => false,
                ];
            }
        }

        $novoId = $this->create($payload);

        return [
            'id' => $novoId,
            'created' => true,
        ];
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
        $cejuscId = trim((string) ($subtarefa['cejusc_tarefa_id'] ?? ''));

        if ($cejuscId === '') {
            throw new InvalidArgumentException('Tarefa Cejusc da subtarefa não identificada.');
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
                $this->alocadosRepository->addAlocado($cejuscId, $usuarioIdMovimentacao);
            } catch (\RuntimeException $e) {
                // limite ou duplicata — não impede a conclusão da subtarefa
            }
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);
        $tipoSubtarefa = trim((string) ($subtarefa['tipo'] ?? ''));
        if ($usuarioIdMovimentacao !== null && $usuarioIdMovimentacao !== '' && $usuarioIdMovimentacao !== 'sistema' && $tipoSubtarefa !== '') {
            $tarefaAtual = $this->repository->findById($cejuscId) ?? [];
            $reuNome = trim((string) ($tarefaAtual['reu_nome'] ?? $tarefaAtual['nome_reu'] ?? ''));
            $reuNomeObs = CartorioProdutividade::nonEmpty($reuNome, 'réu não informado');
            CartorioProdutividade::record($this->connection, $usuarioIdMovimentacao, 'cartorio-cejusc.subtarefa.' . $tipoSubtarefa, [
                'referencia_item_id' => $subtarefaId,
                'referencia_sistema' => 'cartorio-cejusc',
                'numero_processo' => (string) ($tarefaAtual['numero_processo_origem'] ?? ''),
                'classe_processo' => $reuNomeObs,
                'reu_nome' => $reuNomeObs,
            ]);
        }

        if ($ordem === 1) {
            $ordem2 = $this->subtarefasRepository->findByCejuscIdEOrdem($cejuscId, 2);
            if ($ordem2 !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $ordem2['id'], 'em_andamento');
            } else {
                $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_2, 2, 'em_andamento', null);
            }
            $this->repository->updateStatus($cejuscId, 'em_andamento');
            return ['tarefa_principal_concluida' => false];
        } elseif ($ordem === 2) {
            $ordem3 = $this->subtarefasRepository->findByCejuscIdEOrdem($cejuscId, 3);
            if ($ordem3 !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $ordem3['id'], 'em_andamento');
            } else {
                $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_3, 3, 'em_andamento', null);
            }
            $this->repository->updateStatus($cejuscId, 'em_andamento');
            return ['tarefa_principal_concluida' => false];
        } else {
            $this->repository->updateStatus($cejuscId, 'concluída');
            $tarefa = $this->repository->findById($cejuscId);
            $processo = trim((string) ($tarefa['numero_processo_origem'] ?? ''));
            $reu = trim((string) ($tarefa['reu_nome'] ?? $tarefa['nome_reu'] ?? ''));
            return [
                'tarefa_principal_concluida' => true,
                'tarefa_principal_mensagem' => 'Tarefa principal concluída automaticamente: fluxo Cejusc do processo '
                    . ($processo !== '' ? $processo : 'não informado')
                    . ' (réu: ' . ($reu !== '' ? $reu : 'não informado') . ').',
            ];
        }
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === CejuscAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === CejuscAlocadosRepository::TIPO_ALL || $t === 'all') {
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

    public function alocarUsuarioDirecionado(string $cejuscId, string $targetUsuarioId, string $actorId): void
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '') {
            throw new InvalidArgumentException('Identificador do registro inválido.');
        }
        $row = $this->repository->findById($cejuscId);
        if ($row === null) {
            throw new InvalidArgumentException('Registro Cejusc não encontrado.');
        }
        $st = trim((string) ($row['status'] ?? ''));
        if (in_array($st, ['cancelada', 'concluída'], true)) {
            throw new InvalidArgumentException('Não é possível alterar alocações neste status.');
        }
        AlocacaoTarefasPerfisHelper::registrarAlocacaoDirecionadaNaEdicao(
            $this->connection,
            $actorId,
            $targetUsuarioId,
            function (string $uid) use ($cejuscId): void {
                $this->alocadosRepository->addAlocado($cejuscId, $uid, CejuscAlocadosRepository::TIPO_SPECIFIC);
            }
        );
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

        $usuarioId = trim((string) ($payload['usuario_id'] ?? ''));
        unset($payload['usuario_id']);
        if ($usuarioId !== '') {
            $this->alocadosRepository->addAlocado($id, $usuarioId);
        }

        return $this->repository->update($id, $payload);
    }

    public function cancel(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }
        return $this->repository->updateStatus($id, 'cancelada');
    }

    public function delete(string $id): array
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $subtarefas = $this->subtarefasRepository->findByCejuscId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByCejuscId($id);
        $this->alocadosRepository->deleteByCejuscId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }

    public function criarPrimeiraSubtarefa(string $cejuscId, ?string $usuarioId = null): void
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '') {
            throw new InvalidArgumentException('Identificador da tarefa Cejusc é obrigatório.');
        }
        if ($this->repository->findById($cejuscId) === null) {
            throw new InvalidArgumentException('Tarefa Cejusc não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCejuscId($cejuscId);
        if (count($existentes) > 0) {
            $this->subtarefasRepository->ensureFluxoCompleto($cejuscId);
            $ordem1 = $this->subtarefasRepository->findByCejuscIdEOrdem($cejuscId, 1);
            if ($ordem1 !== null && trim((string) ($ordem1['status'] ?? '')) !== 'concluida') {
                $this->subtarefasRepository->atualizarStatus((string) $ordem1['id'], 'em_andamento');
            }
            $todas = $this->subtarefasRepository->findByCejuscId($cejuscId);
            $status = CartorioTarefaStatusPrincipalHelper::principalStatusPadrao(
                $todas,
                ['concluida'],
                'concluída'
            );
            $this->repository->updateStatus($cejuscId, $status);
            return;
        }
        $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);
        $this->repository->updateStatus($cejuscId, 'pendente');
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($cejuscId, trim($usuarioId));
        }
    }
}
