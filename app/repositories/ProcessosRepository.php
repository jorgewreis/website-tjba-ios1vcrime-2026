<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/ProcessoDadoProtegidoException.php';
require_once __DIR__ . '/../helpers/ProcessoClasseOrigemIndisponivelException.php';
require_once __DIR__ . '/../services/ProcessoDataJudSyncQueue.php';

final class ProcessosRepository
{
    private PDO $connection;
    private string $table;

    /** @var bool|null cache por instância: servidor suporta REGEXP_REPLACE em SQL */
    private ?bool $supportsRegexpReplaceCache = null;
    private ?bool $classeOrigemDisponivel = null;

    public function __construct(PDO $connection, string $table = 'processos')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function classeOrigemDisponivel(): bool
    {
        if ($this->classeOrigemDisponivel !== null) {
            return $this->classeOrigemDisponivel;
        }
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->connection->prepare("SELECT 1 FROM pragma_table_info(:tabela) WHERE name = 'classe_origem'");
        } else {
            $stmt = $this->connection->prepare(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = 'classe_origem' LIMIT 1"
            );
        }
        $stmt->execute([':tabela' => $this->table]);
        $this->classeOrigemDisponivel = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();
        return $this->classeOrigemDisponivel;
    }

    /** Retorna o id do processo (existente ou recém-criado). numero_processo formatado (ex. CNJ); classe_id opcional (null = classe não informada); data_fato e sigiloso opcionais. */
    public function ensureProcess(string $numeroProcesso, ?string $classeId = null, ?string $dataFato = null, ?bool $sigiloso = null, bool $preservarSigiloExistente = false): string
    {
        $digits = CnjValidator::digits20OrThrow($numeroProcesso);

        $numeroCanonico = $this->formatarCNJ20($digits);

        $existing = $this->findByDigits($digits);
        if ($existing !== null) {
            $this->updateIfNeeded($existing['id'], $classeId, $dataFato, $preservarSigiloExistente ? null : $sigiloso);
            return (string) $existing['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'PRC',
            30
        );

        $registrarOrigem = $classeId !== null && $classeId !== '' && $this->classeOrigemDisponivel();
        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at`%s) VALUES (:id, :numero_processo, :classe_id, :data_fato, :sigiloso, NOW(), NOW()%s)',
                $this->table,
                $registrarOrigem ? ', `classe_origem`' : '',
                $registrarOrigem ? ", 'manual'" : ''
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':numero_processo', $numeroCanonico);
        $statement->bindValue(':classe_id', $classeId !== null && $classeId !== '' ? $classeId : null);
        $statement->bindValue(':data_fato', $dataFato);
        $statement->bindValue(':sigiloso', $sigiloso ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        if (!$sigiloso) {
            ProcessoDataJudSyncQueue::enqueueFirstRegistration($this->connection, $id);
        }

        return $id;
    }

    public function findByNumero(string $numero): ?array
    {
        $digits = $this->normalizeNumber($numero);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 20) {
            return $this->findByDigits($digits);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at` FROM `%s` WHERE REPLACE(REPLACE(`numero_processo`, \'-\', \'\'), \'.\', \'\') = :digits LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':digits', $digits);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Localiza processo pelos 20 dígitos do CNJ, independente de máscara, espaços ou outros separadores no banco.
     */
    private function findByDigits(string $digits): ?array
    {
        if (strlen($digits) !== 20) {
            return null;
        }

        if ($this->supportsRegexpReplace()) {
            try {
                $statement = $this->connection->prepare(
                    sprintf(
                        'SELECT `id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at` FROM `%s` WHERE REGEXP_REPLACE(`numero_processo`, \'[^0-9]\', \'\') = :digits LIMIT 1',
                        $this->table
                    )
                );
                $statement->bindValue(':digits', $digits);
                $statement->execute();
                $result = $statement->fetch(PDO::FETCH_ASSOC);
                if ($result !== false) {
                    return $result;
                }
            } catch (Throwable $e) {
                $this->supportsRegexpReplaceCache = false;
            }
        }

        return $this->findByDigitsLegacy($digits);
    }

    /** Fallback quando REGEXP_REPLACE não existe ou falha na execução. */
    private function findByDigitsLegacy(string $digits): ?array
    {
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `numero_processo`, `classe_id`, `data_fato`, `sigiloso`, `created_at`, `updated_at` FROM `%s` WHERE REPLACE(REPLACE(`numero_processo`, \'-\', \'\'), \'.\', \'\') = :digits LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':digits', $digits);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    private function supportsRegexpReplace(): bool
    {
        if ($this->supportsRegexpReplaceCache !== null) {
            return $this->supportsRegexpReplaceCache;
        }

        try {
            $st = $this->connection->query('SELECT REGEXP_REPLACE(\'0\',\'[^0-9]\',\'\')');
            $st?->fetchColumn();
            $this->supportsRegexpReplaceCache = true;
        } catch (Throwable $e) {
            $this->supportsRegexpReplaceCache = false;
        }

        return $this->supportsRegexpReplaceCache;
    }

    private function formatarCNJ20(string $digits): string
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

    public function findById(string $id, bool $forUpdate = false): ?array
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `numero_processo`, `classe_id`, %s, `data_fato`, `sigiloso`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1%s',
                $this->classeOrigemDisponivel() ? '`classe_origem`' : 'NULL AS `classe_origem`',
                $this->table,
                $forUpdate && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params, $joinClause] = $this->buildListFilter($filters);

        $groupBy = $joinClause !== '' ? ' GROUP BY proc.`id` ' : '';
        $sql = sprintf(
            'SELECT proc.`id`, proc.`numero_processo`, proc.`classe_id`, proc.`data_fato`, proc.`sigiloso`, proc.`created_at`, proc.`updated_at` FROM `%s` proc %s %s %s ORDER BY proc.`numero_processo` ASC LIMIT :limit OFFSET :offset',
            $this->table,
            $joinClause,
            $whereClause ? 'WHERE ' . $whereClause : '',
            $groupBy
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    public function count(array $filters): int
    {
        [$whereClause, $params, $joinClause] = $this->buildListFilter($filters);
        $sql = sprintf('SELECT COUNT(DISTINCT proc.`id`) AS total FROM `%s` proc %s %s', $this->table, $joinClause, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: string} */
    private function buildListFilter(array $filters): array
    {
        $conditions = [];
        $params = [];
        $joinClause = '';

        $numero = trim((string) ($filters['numero'] ?? $filters['numero_processo'] ?? ''));
        if ($numero !== '') {
            $digits = $this->normalizeNumber($numero);
            if ($digits !== '') {
                $conditions[] = 'REPLACE(REPLACE(proc.`numero_processo`, \'-\', \'\'), \'.\', \'\') LIKE :numero';
                $params[':numero'] = '%' . $digits . '%';
            }
        }

        $parteNome = trim((string) ($filters['parte_nome'] ?? $filters['nome'] ?? ''));
        if ($parteNome !== '') {
            $joinClause = ' INNER JOIN `processos_partes` pp ON pp.`processo_id` = proc.`id` INNER JOIN `partes` pt ON pt.`id` = pp.`parte_id` ';
            $conditions[] = 'pt.`nome_completo` LIKE :parte_nome';
            $params[':parte_nome'] = '%' . $parteNome . '%';
        }

        $where = $conditions === [] ? '' : implode(' AND ', $conditions);
        return [$where, $params, $joinClause];
    }

    private function updateIfNeeded(string $id, ?string $classeId, ?string $dataFato, ?bool $sigiloso): void
    {
        // Operações que só garantem o processo (cartas, ofícios, pautas)
        // continuam normalmente, mas nunca sobrescrevem uma classe protegida.
        $setClasse = '';
        if ($classeId !== null && $classeId !== '') {
            if ($this->classeOrigemDisponivel()) {
                $permitido = self::condicaoClasseManual();
                // MySQL avalia SET da esquerda para a direita.
                $setClasse = "`classe_origem` = CASE WHEN $permitido THEN 'manual' ELSE `classe_origem` END, "
                    . "`classe_id` = CASE WHEN $permitido THEN :classe_id ELSE `classe_id` END, ";
            } else {
                $setClasse = "`classe_id` = CASE WHEN `classe_id` IS NULL OR `classe_id` = '' THEN :classe_id ELSE `classe_id` END, ";
            }
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET %s`data_fato` = COALESCE(:data_fato, `data_fato`), `sigiloso` = COALESCE(:sigiloso, `sigiloso`), `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table,
                $setClasse
            )
        );
        $statement->bindValue(':id', $id);
        if ($setClasse !== '') $statement->bindValue(':classe_id', $classeId);
        $statement->bindValue(':data_fato', $dataFato);
        $statement->bindValue(':sigiloso', $sigiloso === null ? null : ($sigiloso ? 1 : 0), $sigiloso === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
    }

    private static function condicaoClasseManual(): string
    {
        return "(`classe_origem` = 'manual' OR ((`classe_id` IS NULL OR `classe_id` = '') AND `classe_origem` IS NULL))";
    }

    public function assertClasseEditavel(array $processo): void
    {
        $origem = $processo['classe_origem'] ?? null;
        if ($origem === 'datajud') {
            throw new ProcessoDadoProtegidoException('A classe sincronizada só pode ser alterada por outra sincronização com o DataJud.');
        }
        if ($origem !== 'manual' && trim((string) ($processo['classe_id'] ?? '')) !== '') {
            throw new ProcessoDadoProtegidoException('Não é possível editar a classe atual porque não há registro de sua origem. Sincronize com o DataJud para confirmá-la.');
        }
        if (!$this->classeOrigemDisponivel()) throw new ProcessoClasseOrigemIndisponivelException();
    }

    public function updateClasseForProcesso(string $processoId, string $classeId): void
    {
        $this->gravarClasse($processoId, $classeId, false);
    }

    /** Chamar somente após a validação da origem DataJud no serviço de sincronização. */
    public function syncClasseForProcesso(string $processoId, string $classeId): bool
    {
        return $this->gravarClasse($processoId, $classeId, true);
    }

    private function gravarClasse(string $processoId, string $classeId, bool $datajud): bool
    {
        $processoId = trim($processoId);
        $classeId = trim($classeId);
        if ($processoId === '' || $classeId === '') {
            throw new InvalidArgumentException('processo_id e classe_id são obrigatórios.');
        }
        if (!$this->classeOrigemDisponivel()) throw new ProcessoClasseOrigemIndisponivelException();

        $origem = $datajud ? 'datajud' : 'manual';
        $permitido = $datajud ? '1 = 1' : self::condicaoClasseManual();
        $statement = $this->connection->prepare(
            sprintf(
                "UPDATE `%s` SET `classe_id` = :classe_id, `classe_origem` = :origem, `updated_at` = NOW()
                 WHERE `id` = :id AND $permitido AND (`classe_id` IS NULL OR `classe_id` <> :classe_atual
                   OR `classe_origem` IS NULL OR `classe_origem` <> :origem_atual) LIMIT 1",
                $this->table
            )
        );
        $statement->execute([
            ':classe_id' => $classeId, ':origem' => $origem, ':id' => $processoId,
            ':classe_atual' => $classeId, ':origem_atual' => $origem,
        ]);
        if ($statement->rowCount() > 0) return true;
        if (!$datajud) {
            $atual = $this->findById($processoId, $this->connection->inTransaction());
            if ($atual === null) throw new InvalidArgumentException('Processo não encontrado.');
            $this->assertClasseEditavel($atual);
        }
        return false;
    }

    private function normalizeNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }
}
