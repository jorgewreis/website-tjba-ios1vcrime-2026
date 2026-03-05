<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CejuscSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ORDEM_1 = 'enviar_decisao';
    public const TIPO_ORDEM_2 = 'juntar_termo_envio';

    private const ORDEM_POR_TIPO = [
        self::TIPO_ORDEM_1 => 1,
        self::TIPO_ORDEM_2 => 2,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'cejusc_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $cejuscId, string $tipo, int $ordem, string $status = 'pendente', ?string $dadosJson = null): string
    {
        $cejuscId = trim($cejuscId);
        $tipo = trim($tipo);
        if ($cejuscId === '' || $tipo === '' || $ordem < 1 || $ordem > 2) {
            throw new InvalidArgumentException('cejusc_tarefa_id, tipo e ordem (1-2) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'CJS',
            30
        );

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `cejusc_tarefa_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :cejusc_tarefa_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':cejusc_tarefa_id', $cejuscId);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->execute();

        return $id;
    }

    public function findByCejuscId(string $cejuscId): array
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `cejusc_tarefa_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `cejusc_tarefa_id` = :cejusc_id ORDER BY `ordem` ASC',
                $this->table
            )
        );
        $statement->bindValue(':cejusc_id', $cejuscId);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function deleteByCejuscId(string $cejuscId): void
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '') {
            return;
        }
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `cejusc_tarefa_id` = :cejusc_id', $this->table)
        );
        $statement->bindValue(':cejusc_id', $cejuscId);
        $statement->execute();
    }

    public function findByCejuscIdEOrdem(string $cejuscId, int $ordem): ?array
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '' || $ordem < 1 || $ordem > 2) {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `cejusc_tarefa_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`
                 FROM `%s`
                 WHERE `cejusc_tarefa_id` = :cejusc_id AND `ordem` = :ordem
                 ORDER BY `updated_at` DESC
                 LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':cejusc_id', $cejuscId);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Retorna as subtarefas a exibir na lista: apenas 1 por vez, a que estiver em andamento ou pendente (primeira na ordem).
     */
    public function findPendenteOuEmAndamentoByCejuscId(string $cejuscId): array
    {
        $all = $this->findByCejuscId($cejuscId);
        foreach ($all as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === self::STATUS_EM_ANDAMENTO || $status === self::STATUS_PENDENTE) {
                return [$row];
            }
        }
        return [];
    }

    public function marcarConcluida(string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':status', self::STATUS_CONCLUIDA);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    public function atualizarStatus(string $id, string $status): void
    {
        $id = trim($id);
        $status = trim($status);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }
        if (!in_array($status, [self::STATUS_PENDENTE, self::STATUS_EM_ANDAMENTO, self::STATUS_CONCLUIDA], true)) {
            throw new InvalidArgumentException('Status de subtarefa inválido.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    public function atualizarDadosEspecificos(string $id, ?string $dadosJson): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `dados_especificos` = :dados_especificos, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    /**
     * Garante que as subtarefas das ordens 1 (enviar decisão) e 2 (juntar termo de envio) existam em conjunto.
     */
    public function ensureEnviarJuntarPair(string $cejuscId): void
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '') {
            return;
        }

        $ordem1 = $this->findByCejuscIdEOrdem($cejuscId, 1);
        $ordem2 = $this->findByCejuscIdEOrdem($cejuscId, 2);

        if ($ordem1 === null && $ordem2 === null) {
            return;
        }

        if ($ordem1 !== null && $ordem2 === null) {
            $status1 = trim((string) ($ordem1['status'] ?? ''));
            $status2 = $status1 === self::STATUS_CONCLUIDA ? self::STATUS_EM_ANDAMENTO : self::STATUS_PENDENTE;
            $this->create($cejuscId, self::TIPO_ORDEM_2, 2, $status2, null);
            return;
        }

        if ($ordem1 === null && $ordem2 !== null) {
            $status2 = trim((string) ($ordem2['status'] ?? ''));
            $status1 = $status2 === self::STATUS_CONCLUIDA ? self::STATUS_CONCLUIDA : self::STATUS_PENDENTE;
            $this->create($cejuscId, self::TIPO_ORDEM_1, 1, $status1, null);
        }
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `cejusc_tarefa_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function obterProximoTipo(int $ordemAtual): ?string
    {
        $next = $ordemAtual + 1;
        $map = [
            1 => self::TIPO_ORDEM_1,
            2 => self::TIPO_ORDEM_2,
        ];
        return $map[$next] ?? null;
    }
}
