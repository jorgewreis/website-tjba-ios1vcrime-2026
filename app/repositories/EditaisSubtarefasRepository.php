<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class EditaisSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ORDEM_1 = 'expedicao_edital';
    public const TIPO_ORDEM_2 = 'envio_djen';
    public const TIPO_ORDEM_3 = 'certificacao_publicacao';
    public const TIPO_ORDEM_4 = 'certificacao_decurso_prazo';

    private const ORDEM_POR_TIPO = [
        self::TIPO_ORDEM_1 => 1,
        self::TIPO_ORDEM_2 => 2,
        self::TIPO_ORDEM_3 => 3,
        self::TIPO_ORDEM_4 => 4,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'editais_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $editalId, string $tipo, int $ordem, string $status = 'pendente', ?string $dadosJson = null): string
    {
        $editalId = trim($editalId);
        $tipo = trim($tipo);
        if ($editalId === '' || $tipo === '' || $ordem < 1 || $ordem > 4) {
            throw new InvalidArgumentException('edital_id, tipo e ordem (1-4) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'EDS',
            30
        );

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `edital_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :edital_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':edital_id', $editalId);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->execute();

        return $id;
    }

    public function findByEditalId(string $editalId): array
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `edital_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `edital_id` = :edital_id ORDER BY `ordem` ASC',
                $this->table
            )
        );
        $statement->bindValue(':edital_id', $editalId);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function deleteByEditalId(string $editalId): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return;
        }
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `edital_id` = :edital_id', $this->table)
        );
        $statement->bindValue(':edital_id', $editalId);
        $statement->execute();
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `edital_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findPendenteOuEmAndamentoByEditalId(string $editalId): array
    {
        $all = $this->findByEditalId($editalId);
        foreach ($all as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === self::STATUS_EM_ANDAMENTO || $status === self::STATUS_PENDENTE) {
                return [$row];
            }
        }
        return [];
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
     * Garante as 4 subtarefas para um edital que ainda não tem nenhuma.
     */
    public function ensureQuatroSubtarefas(string $editalId): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return;
        }
        $existentes = $this->findByEditalId($editalId);
        if (count($existentes) > 0) {
            return;
        }
        $this->create($editalId, self::TIPO_ORDEM_1, 1, self::STATUS_EM_ANDAMENTO, null);
        $this->create($editalId, self::TIPO_ORDEM_2, 2, self::STATUS_PENDENTE, null);
        $this->create($editalId, self::TIPO_ORDEM_3, 3, self::STATUS_PENDENTE, null);
        $this->create($editalId, self::TIPO_ORDEM_4, 4, self::STATUS_PENDENTE, null);
    }
}
