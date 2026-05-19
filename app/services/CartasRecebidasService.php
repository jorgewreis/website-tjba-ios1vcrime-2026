<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasRecebidasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../repositories/ComarcasRepository.php';
require_once __DIR__ . '/../repositories/VarasRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartorioProdutividade.php';
require_once __DIR__ . '/../helpers/AlocacaoTarefasPerfisHelper.php';

final class CartasRecebidasService
{
    private PDO $connection;
    private CartasRecebidasRepository $repository;
    private CartasRecebidasSubtarefasRepository $subtarefasRepository;
    private CartasRecebidasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private TribunaisRepository $tribunaisRepository;
    private ComarcasRepository $comarcasRepository;
    private VarasRepository $varasRepository;

    private const REGRA_ALOCAR_CARTAS = 'ALLOCATE-CARTASRECEBIDAS';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CartasRecebidasRepository($connection);
        $this->subtarefasRepository = new CartasRecebidasSubtarefasRepository($connection);
        $this->alocadosRepository = new CartasRecebidasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->tribunaisRepository = new TribunaisRepository($connection);
        $this->comarcasRepository = new ComarcasRepository($connection);
        $this->varasRepository = new VarasRepository($connection);
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idCarta = (string) ($row['IdCarta'] ?? '');
            if ($idCarta !== '') {
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByCartaId($idCarta);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByCartaId($idCarta);
                $alocadosRaw = $this->alocadosRepository->findByCartaId($idCarta);
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

    public function getClasseCounts(array $filters): array
    {
        return $this->repository->countByClasse($filters);
    }

    public function getFinalidadeCounts(array $filters): array
    {
        return $this->repository->countByFinalidade($filters);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo de origem é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20
            ? $this->formatarCNJ($digits)
            : $numeroProcesso;

        $ufOrigem = strtoupper(trim((string) ($payload['uf_origem'] ?? $payload['estado'] ?? $payload['uf'] ?? '')));
        if (strlen($ufOrigem) !== 2) {
            throw new InvalidArgumentException('A UF de origem é obrigatória (2 caracteres).');
        }

        $cidadeOrigem = trim((string) ($payload['cidade_origem'] ?? $payload['cidade'] ?? ''));
        if ($cidadeOrigem === '') {
            throw new InvalidArgumentException('A cidade de origem é obrigatória.');
        }
        $cidadeOrigem = TextFormatter::formatProperName($cidadeOrigem, 120);

        $tribunalSigla = 'TJ' . $ufOrigem;
        $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça do Estado');
        $comarcaOrigem = $this->comarcasRepository->findByTribunalUfNome($tribunalId, $ufOrigem, $cidadeOrigem);
        $comarcaOrigemId = $comarcaOrigem !== null
            ? (string) $comarcaOrigem['id']
            : $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, $cidadeOrigem);

        $dataRecebimento = $this->parseDate($payload['data_recebimento'] ?? null);
        $finalidade = trim((string) ($payload['finalidade'] ?? ''));
        if ($finalidade === '') {
            throw new InvalidArgumentException('A finalidade é obrigatória.');
        }
        $prazoDias = (int) $this->sanitizePrazo((string) ($payload['prazo_dias'] ?? $payload['prazo'] ?? '30'));

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '' && $classeDescricao !== '') {
            $classeId = $this->classesRepository->ensureClass($classeCodigo, TextFormatter::formatProperName($classeDescricao, 200));
        } elseif ($classeCodigo !== '') {
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeCodigo);
        }

        $processoOrigemId = null;
        if (strlen($digits) === 20) {
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);
        }

        $idCarta = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CPR',
            30
        );

        $row = [
            'id' => $idCarta,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'comarca_origem_id' => $comarcaOrigemId,
            'cidade_origem' => $cidadeOrigem,
            'uf_origem' => $ufOrigem,
            'data_recebimento' => $dataRecebimento,
            'finalidade' => $finalidade,
            'prazo_dias' => $prazoDias,
            'status' => 'pendente',
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }

        $this->repository->insert($row);

        if ($usuarioCadastroId !== ''
            && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioCadastroId)) {
            $this->alocadosRepository->addAlocado($idCarta, $usuarioCadastroId, CartasRecebidasAlocadosRepository::TIPO_ALL);
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CARTAS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId
                && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioIdRegra)) {
                $this->alocadosRepository->addAlocado($idCarta, $usuarioIdRegra, CartasRecebidasAlocadosRepository::TIPO_RULE);
            }
        }

        AlocacaoTarefasPerfisHelper::registrarAlocacoesDirecionadasNoCadastro(
            $this->connection,
            $usuarioCadastroId,
            $payload,
            function (string $uid) use ($idCarta): void {
                try {
                    $this->alocadosRepository->addAlocado($idCarta, $uid, CartasRecebidasAlocadosRepository::TIPO_SPECIFIC);
                } catch (Throwable $e) {
                    // limite ou duplicata
                }
            }
        );

        $repo = $this->subtarefasRepository;
        $repo->create($idCarta, $repo::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_2, 2, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_3, 3, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_4, 4, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_5, 5, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_6, 6, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_7, 7, 'pendente', null);

        $this->repository->update($idCarta, ['status' => 'pendente']);

        if ($usuarioCadastroId !== '' && $usuarioCadastroId !== 'sistema') {
            $cartaComClasse = $this->repository->findWithClasseProcessualNome($idCarta)
                ?? $this->repository->find($idCarta)
                ?? [];
            $nomeClasse = trim((string) ($cartaComClasse['classe_nome_processual'] ?? ''));
            $tipoCarta = CartorioProdutividade::labelClasseCarta($nomeClasse);
            CartorioProdutividade::record($this->connection, $usuarioCadastroId, 'cartorio-cartas-recebidas.create', [
                'referencia_item_id' => $idCarta,
                'referencia_sistema' => 'cartorio-cartas-recebidas',
                'numero_processo' => $numeroFormatado,
                'classe_processo' => $tipoCarta,
                'tipo_carta' => $tipoCarta,
                'finalidade' => CartorioProdutividade::nonEmpty($finalidade, 'finalidade não informada'),
            ]);
        }

        return $idCarta;
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
        $cartaId = trim((string) ($subtarefa['carta_recebida_id'] ?? ''));

        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        $dadosArray = [];
        if ($dadosJson !== null && $dadosJson !== '') {
            $decoded = json_decode($dadosJson, true);
            if (is_array($decoded)) {
                $dadosArray = $decoded;
            }
        }

        $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
        if ($usuarioIdMovimentacao !== '') {
            try {
                $this->alocadosRepository->addAlocado($cartaId, $usuarioIdMovimentacao);
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }

        $tipo = trim((string) ($subtarefa['tipo'] ?? ''));

        $tiposJuntadaCertidao = [
            CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3,
            'certidao_oficial_justica',
            'juntada_certidao',
            'confirmar_juntada',
            'juntada_certidao_oficial_justica',
        ];
        $tiposEnvioDevolucao = [
            CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5,
            'envio_processo',
            'enviar_processo',
            'devolucao_origem',
            'devolução_origem',
        ];

        if (in_array($tipo, $tiposJuntadaCertidao, true)) {
            $resultadoCumprimento = trim((string) ($dadosArray['resultado_cumprimento'] ?? $dadosArray['resultado'] ?? ''));
            if ($resultadoCumprimento === '') {
                throw new InvalidArgumentException('Selecione o resultado do cumprimento para concluir a juntada da certidão do oficial de justiça.');
            }
        }

        if (in_array($tipo, $tiposEnvioDevolucao, true)) {
            $tipoEnvio = trim((string) ($dadosArray['tipo_envio'] ?? ''));
            if ($tipoEnvio === '') {
                throw new InvalidArgumentException('Selecione o tipo de envio para informar a devolução.');
            }
        }

        $this->subtarefasRepository->marcarConcluidaComDados($subtarefaId, $dadosJson);
        if ($usuarioIdMovimentacao !== '' && $usuarioIdMovimentacao !== 'sistema' && $tipo !== '') {
            $cartaAtual = $this->repository->findWithClasseProcessualNome($cartaId)
                ?? $this->repository->find($cartaId)
                ?? [];
            $nomeClasse = trim((string) ($cartaAtual['classe_nome_processual'] ?? ''));
            $tipoCarta = CartorioProdutividade::labelClasseCarta($nomeClasse);
            $finalidadeRow = (string) ($cartaAtual['finalidade'] ?? '');
            CartorioProdutividade::record($this->connection, $usuarioIdMovimentacao, 'cartorio-cartas-recebidas.subtarefa.' . $tipo, [
                'referencia_item_id' => $subtarefaId,
                'referencia_sistema' => 'cartorio-cartas-recebidas',
                'numero_processo' => (string) ($cartaAtual['numero_processo_origem'] ?? ''),
                'classe_processo' => $tipoCarta,
                'tipo_carta' => $tipoCarta,
                'finalidade' => CartorioProdutividade::nonEmpty($finalidadeRow, 'finalidade não informada'),
            ]);
        }

        if ($ordem < 7) {
            $proxima = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, $ordem + 1);
            if ($proxima !== null && trim((string) ($proxima['status'] ?? '')) !== CartasRecebidasSubtarefasRepository::STATUS_CANCELADA) {
                $statusProxima = strtolower(trim((string) ($proxima['status'] ?? '')));
                if ($statusProxima === 'concluída') {
                    $statusProxima = 'concluida';
                }
                if ($statusProxima !== 'concluida') {
                    $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
                }
            }
            $this->repository->update($cartaId, ['status' => 'em_andamento']);
            return ['tarefa_principal_concluida' => false];
        } else {
            $this->repository->update($cartaId, ['status' => 'concluída']);
            $carta = $this->repository->find($cartaId);
            $processo = trim((string) ($carta['numero_processo_origem'] ?? ''));
            $finalidade = trim((string) ($carta['finalidade'] ?? ''));
            $cidade = trim((string) ($carta['cidade_origem'] ?? ''));
            $uf = trim((string) ($carta['uf_origem'] ?? ''));
            $msg = 'Tarefa principal concluída automaticamente: carta recebida do processo '
                . ($processo !== '' ? $processo : 'não informado')
                . ' (' . ($finalidade !== '' ? $finalidade : 'finalidade não informada')
                . ' de ' . (($cidade !== '' ? $cidade : 'comarca não informada') . ($uf !== '' ? '/' . $uf : '')) . ').';
            return [
                'tarefa_principal_concluida' => true,
                'tarefa_principal_mensagem' => $msg,
            ];
        }
    }

    public function cancelarSubtarefa(string $subtarefaId): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        if ($ordem !== 1) {
            throw new InvalidArgumentException('Apenas a subtarefa "Ato ordinatório - Cumprimento" pode ser cancelada.');
        }

        $cartaId = trim((string) ($subtarefa['carta_recebida_id'] ?? ''));
        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, CartasRecebidasSubtarefasRepository::STATUS_CANCELADA);

        $proxima = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, 2);
        if ($proxima !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
        }
        $this->repository->update($cartaId, ['status' => 'em_andamento']);
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === CartasRecebidasAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === CartasRecebidasAlocadosRepository::TIPO_ALL || $t === 'all') {
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

    public function alocarUsuarioDirecionado(string $cartaId, string $targetUsuarioId, string $actorId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            throw new InvalidArgumentException('Identificador da carta inválido.');
        }
        $row = $this->repository->find($cartaId);
        if ($row === null) {
            throw new InvalidArgumentException('Carta recebida não encontrada.');
        }
        $st = trim((string) ($row['status'] ?? ''));
        if (in_array($st, ['cancelada', 'concluída'], true)) {
            throw new InvalidArgumentException('Não é possível alterar alocações neste status.');
        }
        AlocacaoTarefasPerfisHelper::registrarAlocacaoDirecionadaNaEdicao(
            $this->connection,
            $actorId,
            $targetUsuarioId,
            function (string $uid) use ($cartaId): void {
                $this->alocadosRepository->addAlocado($cartaId, $uid, CartasRecebidasAlocadosRepository::TIPO_SPECIFIC);
            }
        );
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1e12) {
                $ts = (int) floor($ts / 1000);
            }
            $date = date('Y-m-d', $ts);
            return $date !== false && $this->isValidDateYear($date) ? $date : null;
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return null;
        }
        $date = date('Y-m-d', $ts);
        return $date !== false && $this->isValidDateYear($date) ? $date : null;
    }

    private function isValidDateYear(string $ymd): bool
    {
        $y = (int) substr($ymd, 0, 4);
        return $y >= 1900 && $y <= 2100;
    }

    private function sanitizePrazo(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor);
        return $digits === '' ? '30' : substr($digits, 0, 3);
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
        return $this->repository->update($id, ['status' => 'cancelada']);
    }

    public function delete(string $id): array
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $subtarefas = $this->subtarefasRepository->findByCartaId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByCartaId($id);
        $this->alocadosRepository->deleteByCartaId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    public function criarPrimeiraSubtarefa(string $cartaId, ?string $usuarioId = null): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            throw new InvalidArgumentException('Identificador da carta é obrigatório.');
        }
        if ($this->repository->find($cartaId) === null) {
            throw new InvalidArgumentException('Carta recebida não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCartaId($cartaId);
        if (count($existentes) > 0) {
            throw new InvalidArgumentException('Esta carta já possui subtarefas.');
        }
        $repo = $this->subtarefasRepository;
        $repo->create($cartaId, $repo::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_2, 2, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_3, 3, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_4, 4, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_5, 5, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_6, 6, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_7, 7, 'pendente', null);
        $this->repository->update($cartaId, ['status' => 'pendente']);
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($cartaId, trim($usuarioId));
        }
    }
}
