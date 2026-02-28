<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasExpedidasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class CartasExpedidasService
{
    private PDO $connection;
    private CartasExpedidasRepository $repository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CartasExpedidasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
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

        $digitos = $this->onlyDigits($processo);
        if (strlen($digitos) !== 20) {
            throw new InvalidArgumentException('O número do processo está incompleto ou inválido.');
        }

        $formattedProcesso = $this->formatarCNJ($digitos);
        $payload['numero_processo'] = $formattedProcesso;
        if (isset($payload['prazo'])) {
            $payload['prazo'] = $this->sanitizePrazo($payload['prazo']);
        }

        $classe = trim((string) ($payload['classe'] ?? ''));
        if ($classe === '') {
            throw new InvalidArgumentException('A classe processual é obrigatória.');
        }

        $classe = TextFormatter::formatProperName($classe, 100);
        if ($classe === '') {
            throw new InvalidArgumentException('A classe processual está inválida após a normalização.');
        }
        $payload['classe'] = $classe;

        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        if ($classeCodigo !== '') {
            $classeCodigo = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $classeCodigo));
        }
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $classe));
        $classeDescricao = TextFormatter::formatProperName($classeDescricao, 150);
        if ($classeDescricao === '') {
            $classeDescricao = $classe;
        }
        if ($classeCodigo !== '') {
            $this->processosRepository->ensureProcess($formattedProcesso, $classeCodigo);
            $this->classesRepository->ensureClass($classeCodigo, $classeDescricao);
        }

        $usuario = trim((string) ($payload['usuario'] ?? ''));
        if ($usuario === '') {
            throw new InvalidArgumentException('Não foi possível identificar o usuário responsável pelo cadastro.');
        }
        $payload['usuario'] = $usuario;

        $status = trim((string) ($payload['status'] ?? ''));
        if ($status === '') {
            $status = 'Pendente';
        }
        $payload['status'] = $status;

        $cidade = trim((string) ($payload['cidade'] ?? ''));
        if ($cidade !== '') {
            $cidade = TextFormatter::formatProperName($cidade, 50);
            $payload['cidade'] = $cidade;
        } else {
            $payload['cidade'] = '';
        }

        $idCarta = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'IdCarta',
            'CPE',
            30
        );
        $payload['IdCarta'] = $idCarta;

        return $this->repository->insert($payload);
    }

    private function sanitizePrazo(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor);
        if ($digits === '') {
            return '0';
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
            throw new InvalidArgumentException('O identificador informado é invlido.');
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
