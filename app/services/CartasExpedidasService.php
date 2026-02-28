<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasExpedidasRepository.php';
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
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->tribunaisRepository = new TribunaisRepository($connection);
        $this->comarcasRepository = new ComarcasRepository($connection);
        $this->varasRepository = new VarasRepository($connection);
        $this->dataJudService = $dataJudService;
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        return [
            'rows' => $this->repository->fetchList($filters, $limit, $offset),
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
        $dataFato = isset($payload['data_ajuizamento']) ? $this->parseDate($payload['data_ajuizamento']) : null;
        $sigiloso = !empty($payload['nivel_sigilo']);

        if ($this->dataJudService !== null) {
            try {
                $dataJud = $this->dataJudService->searchProcesso($digits);
                $classeCodigo = trim((string) ($dataJud['classe']['codigo'] ?? $classeCodigo));
                $classeNome = trim((string) ($dataJud['classe']['descricao'] ?? $classeNome));
                $dataFato = $this->parseDate($dataJud['data_ajuizamento'] ?? null) ?? $dataFato;

                $tribunalSigla = $this->normalizeTribunalSigla($dataJud['tribunal'] ?? 'TJBA');
                $ufOrigem = $this->tribunalSiglaToUf($tribunalSigla);

                $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça - ' . $tribunalSigla);
                $comarcaOrigemNome = 'Comarca origem';
                $comarcaOrigemId = $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, $comarcaOrigemNome, 0, 0);

                $orgao = $dataJud['orgao_julgador'] ?? [];
                $varaNome = $orgao['nome'] ?? '1ª Vara';
                $varaCodigo = $orgao['codigo'] ?? null;
                $varaOrigemId = $this->varasRepository->ensureVara($comarcaOrigemId, $varaNome, $varaCodigo);
            } catch (Throwable $e) {
                if ($classeCodigo === '' || $classeNome === '') {
                    throw new InvalidArgumentException('Não foi possível obter dados do DataJud e a classe processual é obrigatória.');
                }
                $tribunalSigla = 'TJBA';
                $ufOrigem = 'BA';
                $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça da Bahia');
                $comarcaOrigemId = $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, 'Comarca origem', 0, 0);
                $varaOrigemId = $this->varasRepository->ensureVara($comarcaOrigemId, '1ª Vara', null);
            }
        } else {
            if ($classeCodigo === '' || $classeNome === '') {
                throw new InvalidArgumentException('A classe processual é obrigatória.');
            }
            $tribunalSigla = 'TJBA';
            $ufOrigem = 'BA';
            $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça da Bahia');
            $comarcaOrigemId = $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, 'Comarca origem', 0, 0);
            $varaOrigemId = $this->varasRepository->ensureVara($comarcaOrigemId, '1ª Vara', null);
        }

        $classeId = $this->classesRepository->ensureClass((string) $classeCodigo, $classeNome !== '' ? TextFormatter::formatProperName((string) $classeNome, 200) : (string) $classeCodigo);
        $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, $dataFato, $sigiloso);

        $ufDestino = strtoupper(trim((string) ($payload['estado'] ?? $payload['uf'] ?? '')));
        if (strlen($ufDestino) !== 2) {
            throw new InvalidArgumentException('O estado (UF) de destino é obrigatório (2 caracteres).');
        }

        $cidadeDestino = trim((string) ($payload['cidade'] ?? ''));
        if ($cidadeDestino === '') {
            throw new InvalidArgumentException('A cidade de destino é obrigatória.');
        }
        $cidadeDestino = TextFormatter::formatProperName($cidadeDestino, 120);

        $tribunalDestinoSigla = 'TJ' . $ufDestino;
        $tribunalDestinoId = $this->tribunaisRepository->ensureTribunal($tribunalDestinoSigla, 'Tribunal de Justiça do Estado');
        $comarcaDestino = $this->comarcasRepository->findByTribunalUfNome($tribunalDestinoId, $ufDestino, $cidadeDestino);
        if ($comarcaDestino !== null) {
            $comarcaDestinoId = (string) $comarcaDestino['id'];
        } else {
            $comarcaDestinoId = $this->comarcasRepository->ensureComarca($tribunalDestinoId, $ufDestino, $cidadeDestino, 0, 0);
        }

        $varaDestino = $this->varasRepository->findFirstByComarca($comarcaDestinoId);
        if ($varaDestino !== null) {
            $varaDestinoId = (string) $varaDestino['id'];
            $varaDestinoNome = (string) $varaDestino['nome'];
        } else {
            $varaDestinoId = $this->varasRepository->ensureVara($comarcaDestinoId, $cidadeDestino . ' - 1ª Vara', null);
            $varaDestino = $this->varasRepository->findFirstByComarca($comarcaDestinoId);
            $varaDestinoNome = $varaDestino !== null ? (string) $varaDestino['nome'] : $cidadeDestino . ' - 1ª Vara';
        }

        $finalidade = trim((string) ($payload['finalidade'] ?? ''));
        if ($finalidade === '') {
            throw new InvalidArgumentException('A finalidade é obrigatória.');
        }
        $prazoDias = (int) $this->sanitizePrazo((string) ($payload['prazo'] ?? $payload['prazo_dias'] ?? '30'));
        $status = trim((string) ($payload['status'] ?? 'aguardando assinatura'));
        if ($status === '') {
            $status = 'aguardando assinatura';
        }

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
            'vara_destino_id' => $varaDestinoId,
            'vara_destino_nome' => $varaDestinoNome,
            'status' => $status,
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        return $this->repository->insert($row);
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
}
