<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasExpedidasRepository.php';
require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../repositories/ComarcasRepository.php';
require_once __DIR__ . '/../repositories/VarasRepository.php';
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
    private ?DataJudService $dataJudService;

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
        $this->dataJudService = $dataJudService;
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idCarta = (string) ($row['IdCarta'] ?? '');
            $rows[$i]['Subtarefas'] = $idCarta !== '' ? $this->subtarefasRepository->findPendenteOuEmAndamentoByCartaId($idCarta) : [];
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
            $this->subtarefasRepository->create($cartaId, $proximoTipo, $proximaOrdem, 'em_andamento', null);
            $statusCarta = $proximaOrdem === 6 ? 'pendente' : 'em_andamento';
            $this->repository->update($cartaId, ['status' => $statusCarta]);
        } else {
            $this->repository->update($cartaId, ['status' => 'concluída']);
        }
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

        return $this->repository->update($id, $payload);
    }

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        return $this->repository->delete($id);
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * Cria a primeira subtarefa (ordem 1 - Assinar carta expedida) quando a carta ainda não possui nenhuma.
     *
     * @throws InvalidArgumentException Se a carta não existir ou já possuir subtarefas.
     */
    public function criarPrimeiraSubtarefa(string $cartaId): void
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
    }
}
