<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/FeriadosRepository.php';

final class FeriadosService
{
    public const TIPOS_FERIADO = [
        'estadual',
        'municipal',
        'federal',
        'religioso',
        'compensacao',
        'recesso',
    ];

    public const REGRAS_DATA = ['fixa', 'unica', 'movel'];

    private PDO $connection;
    private FeriadosRepository $repository;

    public function __construct(PDO $connection, ?FeriadosRepository $repository = null)
    {
        $this->connection = $connection;
        $this->repository = $repository ?? new FeriadosRepository($connection);
    }

    /**
     * @param array{tipo_feriado?: string, ativo?: int|null, q?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(array $filters, int $limit, int $offset): array
    {
        return $this->repository->fetchListPaginated($filters, $limit, $offset);
    }

    public function find(string $id): ?array
    {
        if (!$this->isValidId($id)) {
            return null;
        }

        return $this->repository->findById($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{id: string, editais_decurso_recalculados: int}
     */
    public function create(array $payload): array
    {
        $normalized = $this->normalizePayload($payload);
        $id = $this->generateId();
        $this->repository->insert($id, $normalized);

        $recalc = 0;
        if ($normalized['ativo'] === 1) {
            require_once __DIR__ . '/EditaisService.php';
            $recalc = (new EditaisService($this->connection))->recalcularDecursosPorSobreposicaoFeriado(
                $normalized['data_inicio'],
                $normalized['data_fim']
            );
        }

        return [
            'id' => $id,
            'editais_decurso_recalculados' => $recalc,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{editais_decurso_recalculados: int}
     */
    public function update(string $id, array $payload): array
    {
        if (!$this->isValidId($id)) {
            throw new InvalidArgumentException('Identificador inválido.');
        }
        $anterior = $this->repository->findById($id);
        if ($anterior === null) {
            throw new InvalidArgumentException('Feriado não encontrado.');
        }
        $normalized = $this->normalizePayload($payload);
        $this->repository->update($id, $normalized);

        $intervalosParaRecalcular = [];
        if (!empty($anterior['ativo'])) {
            $oi = trim((string) ($anterior['data_inicio'] ?? ''));
            $of = trim((string) ($anterior['data_fim'] ?? ''));
            if ($oi !== '' && $of !== '' && $of >= $oi) {
                $intervalosParaRecalcular[] = [$oi, $of];
            }
        }
        if ($normalized['ativo'] === 1) {
            $intervalosParaRecalcular[] = [$normalized['data_inicio'], $normalized['data_fim']];
        }

        return [
            'editais_decurso_recalculados' => $this->recalcularEditaisDecursoPorVariosIntervalosFeriado($intervalosParaRecalcular),
        ];
    }

    /**
     * @return array{editais_decurso_recalculados: int}
     */
    public function delete(string $id): array
    {
        if (!$this->isValidId($id)) {
            throw new InvalidArgumentException('Identificador inválido.');
        }
        $anterior = $this->repository->findById($id);
        if ($this->repository->deleteById($id) < 1) {
            throw new InvalidArgumentException('Feriado não encontrado ou já excluído.');
        }
        $recalc = 0;
        if ($anterior !== null && !empty($anterior['ativo'])) {
            $oi = trim((string) ($anterior['data_inicio'] ?? ''));
            $of = trim((string) ($anterior['data_fim'] ?? ''));
            if ($oi !== '' && $of !== '' && $of >= $oi) {
                require_once __DIR__ . '/EditaisService.php';
                $recalc = (new EditaisService($this->connection))->recalcularDecursosPorSobreposicaoFeriado($oi, $of);
            }
        }

        return ['editais_decurso_recalculados' => $recalc];
    }

    private function isValidId(string $id): bool
    {
        return (bool) preg_match('/^FER-[a-fA-F0-9]{26}$/', $id);
    }

    private function generateId(): string
    {
        return 'FER-' . bin2hex(random_bytes(13));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{tipo_feriado: string, data_inicio: string, data_fim: string, regra_data: string, descricao: ?string, ativo: int}
     */
    private function normalizePayload(array $payload): array
    {
        $tipo = strtolower(trim((string) ($payload['tipo_feriado'] ?? '')));
        if ($tipo === '') {
            throw new InvalidArgumentException('Informe o tipo de feriado.');
        }
        if (!in_array($tipo, self::TIPOS_FERIADO, true)) {
            throw new InvalidArgumentException('Tipo de feriado inválido.');
        }

        $regra = strtolower(trim((string) ($payload['regra_data'] ?? '')));
        if ($regra === '') {
            throw new InvalidArgumentException('Informe a regra de data.');
        }
        if (!in_array($regra, self::REGRAS_DATA, true)) {
            throw new InvalidArgumentException('Regra de data inválida.');
        }

        $ini = trim((string) ($payload['data_inicio'] ?? ''));
        $fim = trim((string) ($payload['data_fim'] ?? ''));
        if ($ini === '' || $fim === '') {
            throw new InvalidArgumentException('Informe data de início e data de fim.');
        }
        if (!$this->isValidDate($ini)) {
            throw new InvalidArgumentException('Data de início inválida.');
        }
        if (!$this->isValidDate($fim)) {
            throw new InvalidArgumentException('Data de fim inválida.');
        }
        if ($fim < $ini) {
            throw new InvalidArgumentException('A data de fim deve ser igual ou posterior à data de início.');
        }

        $desc = $payload['descricao'] ?? null;
        if ($desc !== null) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                $desc = null;
            } elseif (strlen($desc) > 255) {
                throw new InvalidArgumentException('A descrição deve ter no máximo 255 caracteres.');
            }
        }

        $ativoRaw = $payload['ativo'] ?? 1;
        $ativo = ((int) (bool) $ativoRaw) ? 1 : 0;

        return [
            'tipo_feriado' => $tipo,
            'data_inicio' => $ini,
            'data_fim' => $fim,
            'regra_data' => $regra,
            'descricao' => $desc,
            'ativo' => $ativo,
        ];
    }

    private function isValidDate(string $date): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $date;
    }

    /**
     * @param list<array{0:string,1:string}> $intervalos Pares [início, fim] Y-m-d
     */
    private function recalcularEditaisDecursoPorVariosIntervalosFeriado(array $intervalos): int
    {
        if ($intervalos === []) {
            return 0;
        }
        require_once __DIR__ . '/EditaisService.php';

        return (new EditaisService($this->connection))->recalcularDecursosPorIntervalosFeriado($intervalos);
    }
}
