<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasExpedidasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../repositories/ComarcasRepository.php';
require_once __DIR__ . '/../repositories/VarasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/DataJudService.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';

final class CartasExpedidasService
{
    private PDO $connection;
    private CartasExpedidasRepository $repository;
    private CartasExpedidasSubtarefasRepository $subtarefasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private TribunaisRepository $tribunaisRepository;
    private ComarcasRepository $comarcasRepository;
    private VarasRepository $varasRepository;
    private CartasExpedidasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ?DataJudService $dataJudService;

    private const REGRA_ALOCAR_CARTAS = 'ALLOCATE-CARTASEXPEDIDAS';

    public function __construct(PDO $connection, ?DataJudService $dataJudService = null)
    {
        $this->connection = $connection;
        $this->repository = new CartasExpedidasRepository($connection);
        $this->subtarefasRepository = new CartasExpedidasSubtarefasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->tribunaisRepository = new TribunaisRepository($connection);
        $this->comarcasRepository = new ComarcasRepository($connection);
        $this->varasRepository = new VarasRepository($connection);
        $this->alocadosRepository = new CartasExpedidasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->dataJudService = $dataJudService;
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idCarta = (string) ($row['IdCarta'] ?? '');
            if ($idCarta !== '') {
                $statusCarta = trim((string) ($row['Status'] ?? ''));
                if (!in_array($statusCarta, ['concluída', 'concluida', 'finalizada', 'cancelada'], true)) {
                    $this->subtarefasRepository->garantirSubtarefasFluxo($idCarta, $statusCarta);
                    $statusInferido = $this->subtarefasRepository->inferirStatusTarefaPrincipal($idCarta);
                    if ($statusInferido !== null && $statusInferido !== $statusCarta) {
                        $this->repository->update($idCarta, ['status' => $statusInferido]);
                        $rows[$i]['Status'] = $statusInferido;
                    }
                }
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
        $processo = trim((string) ($payload['processo'] ?? $payload['numero_processo'] ?? ''));
        if ($processo === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = $this->onlyDigits($processo);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('O número do processo está incompleto ou inválido (20 dígitos).');
        }

        $numeroFormatado = $this->formatarCNJ($digits);

        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeNome = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        $sigiloso = !empty($payload['nivel_sigilo']);

        if ($this->dataJudService !== null) {
            try {
                $dataJud = $this->dataJudService->searchProcesso($digits);
                $classeCodigo = trim((string) ($dataJud['classe']['codigo'] ?? $classeCodigo));
                $classeNome = trim((string) ($dataJud['classe']['descricao'] ?? $classeNome));
            } catch (Throwable $e) {
                // Mantém classe do formulário em caso de falha no DataJud
            }
        }

        $classeInformada = $classeCodigo !== '' || $classeNome !== '';
        $classeNaoInformada = !$classeInformada
            || $this->normalizeClasseLabel($classeNome) === 'classe nao informada'
            || $this->normalizeClasseLabel($classeCodigo) === 'classe nao informada';

        // Quando a API não retorna a classe (ex.: processo sigiloso), permite cadastro e marca o processo como sigiloso
        if ($classeNaoInformada) {
            $sigiloso = true;
        }

        $tribunalSigla = 'TJBA';
        $ufOrigem = 'BA';
        $comarcaOrigemNome = 'Ilhéus';
        $varaOrigemNome = 'Primeira Vara Crime da Comarca de Ilhéus';

        $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça da Bahia');
        $comarcaOrigemId = $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, $comarcaOrigemNome);
        $varaOrigemId = $this->varasRepository->ensureVara($comarcaOrigemId, $varaOrigemNome, null);

        $classeId = null;
        if (!$classeNaoInformada && $classeCodigo !== '') {
            $classeId = $this->classesRepository->ensureClass((string) $classeCodigo, $classeNome !== '' ? TextFormatter::formatProperName((string) $classeNome, 200) : (string) $classeCodigo);
        }
        $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, $sigiloso);

        $ufDestino = strtoupper(trim((string) ($payload['estado'] ?? $payload['uf'] ?? '')));
        if (strlen($ufDestino) !== 2) {
            throw new InvalidArgumentException('O estado (UF) de destino é obrigatório (2 caracteres).');
        }

        $cidadeDestino = trim((string) ($payload['cidade'] ?? ''));
        if ($cidadeDestino === '') {
            throw new InvalidArgumentException('A cidade de destino é obrigatória.');
        }
        $cidadeDestino = TextFormatter::formatProperName($cidadeDestino, 120);

        $cidadeDestinoNormalizada = $this->normalizeNomeCidade($cidadeDestino);
        if ($cidadeDestinoNormalizada === 'ilheus') {
            throw new InvalidArgumentException('O cadastro de carta expedida não é permitido para a cidade de Ilhéus (comarca de origem).');
        }

        $tribunalDestinoSigla = 'TJ' . $ufDestino;
        $tribunalDestinoId = $this->tribunaisRepository->ensureTribunal($tribunalDestinoSigla, 'Tribunal de Justiça do Estado');
        $comarcaDestino = $this->comarcasRepository->findByTribunalUfNome($tribunalDestinoId, $ufDestino, $cidadeDestino);
        if ($comarcaDestino !== null) {
            $comarcaDestinoId = (string) $comarcaDestino['id'];
        } else {
            $comarcaDestinoId = $this->comarcasRepository->ensureComarca($tribunalDestinoId, $ufDestino, $cidadeDestino);
        }

        $finalidade = trim((string) ($payload['finalidade'] ?? ''));
        if ($finalidade === '') {
            throw new InvalidArgumentException('A finalidade é obrigatória.');
        }
        $prazoDias = (int) $this->sanitizePrazo((string) ($payload['prazo'] ?? $payload['prazo_dias'] ?? '30'));
        $idCarta = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CPE',
            30
        );

        $row = [
            'id' => $idCarta,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'finalidade' => $finalidade,
            'prazo_dias' => $prazoDias,
            'comarca_destino_id' => $comarcaDestinoId,
            'cidade_destino' => $cidadeDestino,
            'uf_destino' => $ufDestino,
            'numero_processo_destino' => null,
            'vara_destino_id' => null,
            'vara_destino_nome' => null,
            'status' => 'em_andamento',
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
        if ($usuarioCadastroId !== '') {
            $row['criado_por'] = $usuarioCadastroId;
        }

        $this->repository->insert($row);

        // 1) Quem cadastrou a carta é alocado com tipo 'all' (usuário principal da tarefa). CHEFIA não é autoalocado.
        if ($usuarioCadastroId !== ''
            && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioCadastroId)) {
            $this->alocadosRepository->addAlocado($idCarta, $usuarioCadastroId, CartasExpedidasAlocadosRepository::TIPO_ALL);
        }

        // 2) Alocação por regra (tipo 'rule'): omitir se for o mesmo usuário que cadastrou ou se o da regra for CHEFIA.
        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CARTAS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId
                && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioIdRegra)) {
                $this->alocadosRepository->addAlocado($idCarta, $usuarioIdRegra, CartasExpedidasAlocadosRepository::TIPO_RULE);
            }
        }

        // 3) Usuário principal explícito do formulário (tipo 'all'), se informado e diferente do cadastro.
        $usuarioPrincipalId = trim((string) ($payload['usuario_principal_id'] ?? $payload['usuario_alocado_principal_id'] ?? ''));
        if ($usuarioPrincipalId !== '' && $usuarioPrincipalId !== $usuarioCadastroId) {
            $this->alocadosRepository->addAlocado($idCarta, $usuarioPrincipalId, CartasExpedidasAlocadosRepository::TIPO_ALL);
        }

        AlocacaoTarefasPerfisHelper::registrarAlocacoesDirecionadasNoCadastro(
            $this->connection,
            $usuarioCadastroId,
            $payload,
            function (string $uid) use ($idCarta): void {
                try {
                    $this->alocadosRepository->addAlocado($idCarta, $uid, CartasExpedidasAlocadosRepository::TIPO_SPECIFIC);
                } catch (Throwable $e) {
                    // limite ou duplicata
                }
            }
        );

        $this->subtarefasRepository->create(
            $idCarta,
            CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
            CartasExpedidasSubtarefasRepository::ORDEM_ENVIO,
            'em_andamento',
            null
        );
        $this->subtarefasRepository->create(
            $idCarta,
            CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO,
            CartasExpedidasSubtarefasRepository::ORDEM_DEVOLUCAO,
            'pendente',
            null
        );

        return $idCarta;
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);

        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $cartaId = trim((string) ($subtarefa['carta_expedida_id'] ?? ''));
        $tipoSubtarefaAtual = trim((string) ($subtarefa['tipo'] ?? ''));

        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        if (!CartasExpedidasSubtarefasRepository::isOrdemAtiva($ordem)
            || !CartasExpedidasSubtarefasRepository::isTipoAtivo($tipoSubtarefaAtual)) {
            throw new InvalidArgumentException('Subtarefa inválida para o fluxo atual. Atualize a página.');
        }

        $cartaAtual = $this->repository->find($cartaId);
        if ($cartaAtual === null) {
            throw new InvalidArgumentException('Carta não encontrada.');
        }
        $statusCarta = str_replace('concluída', 'concluida', mb_strtolower(trim((string) ($cartaAtual['status'] ?? '')), 'UTF-8'));
        if (in_array($statusCarta, ['cancelada', 'concluida', 'finalizada'], true)) {
            throw new InvalidArgumentException('Não é possível concluir subtarefa de carta cancelada ou concluída.');
        }

        $statusSubtarefa = str_replace('concluída', 'concluida', mb_strtolower(trim((string) ($subtarefa['status'] ?? '')), 'UTF-8'));
        if (in_array($statusSubtarefa, ['cancelada', 'concluida'], true)) {
            throw new InvalidArgumentException('Subtarefa já finalizada.');
        }

        foreach ($this->subtarefasRepository->findByCartaId($cartaId) as $stFluxo) {
            $ordemFluxo = (int) ($stFluxo['ordem'] ?? 0);
            if (!CartasExpedidasSubtarefasRepository::isOrdemAtiva($ordemFluxo) || $ordemFluxo >= $ordem) {
                continue;
            }
            $statusFluxo = str_replace('concluída', 'concluida', mb_strtolower(trim((string) ($stFluxo['status'] ?? '')), 'UTF-8'));
            if ($statusFluxo !== 'concluida') {
                throw new InvalidArgumentException('Conclua as subtarefas anteriores antes de avançar.');
            }
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
            } catch (\RuntimeException $e) {
                // limite ou duplicata — não impede a conclusão da subtarefa
            }
        }

        $this->subtarefasRepository->marcarConcluidaComDados($subtarefaId, $dadosJson);

        $proximaOrdem = $this->subtarefasRepository->obterProximaOrdem($ordem);
        if ($proximaOrdem !== null) {
            $proximoTipo = $this->subtarefasRepository->obterProximoTipo($ordem);
            $existente = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, $proximaOrdem);
            if ($existente === null) {
                $this->subtarefasRepository->create($cartaId, $proximoTipo, $proximaOrdem, 'pendente', null);
            } else {
                $statusProx = trim((string) ($existente['status'] ?? ''));
                if ($statusProx !== 'concluida') {
                    $this->subtarefasRepository->atualizarStatus((string) ($existente['id'] ?? ''), 'pendente');
                }
            }
            $this->repository->update($cartaId, ['status' => 'aguardando cumprimento']);
            return ['tarefa_principal_concluida' => false];
        }

        $this->repository->update($cartaId, ['status' => 'concluída']);
        $carta = $this->repository->find($cartaId);
        $processo = trim((string) ($carta['numero_processo_origem'] ?? ''));
        $finalidade = trim((string) ($carta['finalidade'] ?? ''));
        $cidade = trim((string) ($carta['cidade_destino'] ?? ''));
        $uf = trim((string) ($carta['uf_destino'] ?? ''));
        $msg = 'Tarefa principal concluída automaticamente: carta expedida do processo '
            . ($processo !== '' ? $processo : 'não informado')
            . ' (' . ($finalidade !== '' ? $finalidade : 'finalidade não informada')
            . ' para ' . (($cidade !== '' ? $cidade : 'comarca não informada') . ($uf !== '' ? '/' . $uf : '')) . ').';

        return [
            'tarefa_principal_concluida' => true,
            'tarefa_principal_mensagem' => $msg,
        ];
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === CartasExpedidasAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === CartasExpedidasAlocadosRepository::TIPO_ALL || $t === 'all') {
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
            throw new InvalidArgumentException('Carta expedida não encontrada.');
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
                $this->alocadosRepository->addAlocado($cartaId, $uid, CartasExpedidasAlocadosRepository::TIPO_SPECIFIC);
            }
        );
    }

    private function normalizeTribunalSigla(string $sigla): string
    {
        $s = strtoupper(preg_replace('/\s+/', '', $sigla));
        return $s !== '' ? $s : 'TJBA';
    }

    private function tribunalSiglaToUf(string $sigla): string
    {
        if (preg_match('/^(TJ|TRF)([A-Z]{2})$/', $sigla, $m)) {
            return $m[2];
        }
        if (strlen($sigla) >= 2) {
            return substr($sigla, -2);
        }
        return 'BA';
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
            if ($ts > 2147483647 || $ts < -2147483648) {
                return null;
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

    private function normalizeNomeCidade(string $nome): string
    {
        $n = trim($nome);
        $n = mb_strtolower($n, 'UTF-8');
        $n = preg_replace('/[àáâãäå]/u', 'a', $n);
        $n = preg_replace('/[èéêë]/u', 'e', $n);
        $n = preg_replace('/[ìíîï]/u', 'i', $n);
        $n = preg_replace('/[òóôõö]/u', 'o', $n);
        $n = preg_replace('/[ùúûü]/u', 'u', $n);
        $n = preg_replace('/[ç]/u', 'c', $n);
        return $n;
    }

    private function normalizeClasseLabel(string $valor): string
    {
        return $this->normalizeNomeCidade($valor);
    }

    private function sanitizePrazo(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor);
        if ($digits === '') {
            return '30';
        }
        return substr($digits, 0, 3);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
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

    /**
     * Altera apenas o status da tarefa principal para "cancelada". Não exclui tarefa nem subtarefas.
     */
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

    /**
     * Cria as subtarefas iniciais (envio + devolução pendente) quando a carta ainda não possui nenhuma.
     *
     * @param string|null $usuarioId Usuário que está criando; se informado, é alocado na tarefa principal.
     * @throws InvalidArgumentException Se a carta não existir ou já possuir subtarefas.
     */
    public function criarPrimeiraSubtarefa(string $cartaId, ?string $usuarioId = null): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            throw new InvalidArgumentException('Identificador da carta é obrigatório.');
        }
        if ($this->repository->find($cartaId) === null) {
            throw new InvalidArgumentException('Carta expedida não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCartaId($cartaId);
        if (count($existentes) > 0) {
            throw new InvalidArgumentException('Esta carta já possui subtarefas.');
        }
        $this->subtarefasRepository->create(
            $cartaId,
            CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
            CartasExpedidasSubtarefasRepository::ORDEM_ENVIO,
            'em_andamento',
            null
        );
        $this->subtarefasRepository->create(
            $cartaId,
            CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO,
            CartasExpedidasSubtarefasRepository::ORDEM_DEVOLUCAO,
            'pendente',
            null
        );
        $this->repository->update($cartaId, ['status' => 'em_andamento']);
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($cartaId, trim($usuarioId));
        }
    }
}
