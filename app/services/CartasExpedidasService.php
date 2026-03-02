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
                $this->subtarefasRepository->ensureInformacoesDevolucaoPair($idCarta);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByCartaId($idCarta);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByCartaId($idCarta);
                $alocadosRaw = $this->alocadosRepository->findByCartaId($idCarta);
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
            'status' => 'pendente',
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $this->repository->insert($row);

        // 1) Alocação por regra no banco: usuário da regra ALLOCATE-CARTASEXPEDIDAS (tipo 'rule').
        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CARTAS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '') {
                $this->alocadosRepository->addAlocado($idCarta, $usuarioIdRegra, CartasExpedidasAlocadosRepository::TIPO_RULE);
            }
        }

        // 2) Alocação definida no cadastro como usuário principal (tipo 'all'), se informado.
        $usuarioPrincipalId = trim((string) ($payload['usuario_principal_id'] ?? $payload['usuario_alocado_principal_id'] ?? ''));
        if ($usuarioPrincipalId !== '') {
            $this->alocadosRepository->addAlocado($idCarta, $usuarioPrincipalId, CartasExpedidasAlocadosRepository::TIPO_ALL);
        }

        $this->subtarefasRepository->create(
            $idCarta,
            CartasExpedidasSubtarefasRepository::TIPO_ORDEM_1,
            1,
            'em_andamento',
            null
        );

        return $idCarta;
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $cartaId = trim((string) ($subtarefa['carta_expedida_id'] ?? ''));

        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        // Sempre que um usuário realizar alterações na tarefa ou subtarefas, alocá-lo na tarefa principal (evita duplicata em addAlocado).
        $usuarioIdMovimentacao = null;
        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            }
        }
        if ($usuarioIdMovimentacao !== '') {
            $this->alocadosRepository->addAlocado($cartaId, $usuarioIdMovimentacao);
        }

        // Quando a subtarefa for de "Aguardando informações" (ordem 5),
        // persiste os dados de processo/vara destino diretamente em cartas_expedidas.
        if ($ordem === 5 && $dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $numeroDestino = trim((string) ($dadosArray['numero_processo_destino'] ?? ''));
                $varaDestinoNome = trim((string) ($dadosArray['vara_destino'] ?? ''));
                if ($numeroDestino !== '' || $varaDestinoNome !== '') {
                    $cartaRow = $this->repository->find($cartaId);
                    if ($cartaRow !== null) {
                        $update = [];
                        if ($numeroDestino !== '') {
                            $update['numero_processo_destino'] = $numeroDestino;
                        }
                        if ($varaDestinoNome !== '') {
                            $comarcaDestinoId = (string) ($cartaRow['comarca_destino_id'] ?? '');
                            if ($comarcaDestinoId !== '') {
                                $varaId = $this->varasRepository->ensureVara($comarcaDestinoId, $varaDestinoNome, null);
                                $update['vara_destino_id'] = $varaId;
                                $update['vara_destino_nome'] = $varaDestinoNome;
                            }
                        }
                        if (!empty($update)) {
                            $this->repository->update($cartaId, $update);
                        }
                    }
                }
            }
        }

        // Corrige cartas antigas para manter as ordens 5 e 6 sincronizadas.
        if ($ordem >= 5) {
            $this->subtarefasRepository->ensureInformacoesDevolucaoPair($cartaId);
        }

        if ($ordem === 6) {
            $todas = $this->subtarefasRepository->findByCartaId($cartaId);
            foreach ($todas as $st) {
                if ((int) ($st['ordem'] ?? 0) === 5 && trim((string) ($st['status'] ?? '')) !== 'concluida') {
                    $this->subtarefasRepository->marcarConcluida((string) $st['id']);
                }
            }
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);

        if ($dadosJson !== null && $dadosJson !== '') {
            $stmt = $this->connection->prepare(
                'UPDATE `cartas_expedidas_subtarefas` SET `dados_especificos` = :dados, `updated_at` = NOW() WHERE `id` = :id LIMIT 1'
            );
            $stmt->bindValue(':dados', $dadosJson);
            $stmt->bindValue(':id', $subtarefaId);
            $stmt->execute();
        }

        $proximoTipo = $this->subtarefasRepository->obterProximoTipo($ordem);
        if ($proximoTipo !== null) {
            $proximaOrdem = $ordem + 1;
            if ($proximaOrdem === 5) {
                $ordem5 = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, 5);
                if ($ordem5 === null) {
                    $this->subtarefasRepository->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_ORDEM_5, 5, 'em_andamento', null);
                } else {
                    $status5 = trim((string) ($ordem5['status'] ?? ''));
                    if ($status5 !== 'concluida') {
                        $this->subtarefasRepository->atualizarStatus((string) ($ordem5['id'] ?? ''), 'em_andamento');
                    }
                }

                $ordem6 = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, 6);
                if ($ordem6 === null) {
                    $this->subtarefasRepository->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_ORDEM_6, 6, 'pendente', null);
                }
            } elseif ($proximaOrdem === 6) {
                $ordem6 = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, 6);
                if ($ordem6 === null) {
                    $this->subtarefasRepository->create($cartaId, CartasExpedidasSubtarefasRepository::TIPO_ORDEM_6, 6, 'em_andamento', null);
                } else {
                    $status6 = trim((string) ($ordem6['status'] ?? ''));
                    if ($status6 !== 'concluida') {
                        $this->subtarefasRepository->atualizarStatus((string) ($ordem6['id'] ?? ''), 'em_andamento');
                    }
                }
            } else {
                $this->subtarefasRepository->create($cartaId, $proximoTipo, $proximaOrdem, 'em_andamento', null);
            }
            $statusCarta = $proximaOrdem === 6 ? 'pendente' : 'em_andamento';
            $this->repository->update($cartaId, ['status' => $statusCarta]);
        } else {
            $this->repository->update($cartaId, ['status' => 'concluída']);
        }
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

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $this->subtarefasRepository->deleteByCartaId($id);
        $this->alocadosRepository->deleteByCartaId($id);
        return $this->repository->delete($id);
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * Cria a primeira subtarefa (ordem 1 - Assinar carta expedida) quando a carta ainda não possui nenhuma.
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
            CartasExpedidasSubtarefasRepository::TIPO_ORDEM_1,
            1,
            'em_andamento',
            null
        );
        $this->repository->update($cartaId, ['status' => 'pendente']);
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($cartaId, trim($usuarioId));
        }
    }
}
