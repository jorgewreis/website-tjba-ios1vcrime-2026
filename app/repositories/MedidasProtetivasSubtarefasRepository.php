<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class MedidasProtetivasSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ORDEM_1 = 'cadastro_partes_pje';
    public const TIPO_ORDEM_2 = 'expedicao_mandados_intimacao';
    public const TIPO_ORDEM_3 = 'cadastro_bnmp';
    public const TIPO_ORDEM_4 = 'certidao_cumprimento';

    private const ORDEM_POR_TIPO = [
        self::TIPO_ORDEM_1 => 1,
        self::TIPO_ORDEM_2 => 2,
        self::TIPO_ORDEM_3 => 3,
        self::TIPO_ORDEM_4 => 4,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'medidas_protetivas_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $medidaId, string $tipo, int $ordem, string $status = self::STATUS_PENDENTE, ?string $dadosJson = null): string
    {
        $medidaId = trim($medidaId);
        $tipo = trim($tipo);
        if ($medidaId === '' || $tipo === '' || $ordem < 1 || $ordem > 4) {
            throw new InvalidArgumentException('medida_id, tipo e ordem (1-4) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'MPS', 30);
        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `medida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :medida_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':medida_id', $medidaId);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->execute();

        return $id;
    }

    public function findByMedidaId(string $medidaId): array
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `medida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `medida_id` = :medida_id ORDER BY `ordem` ASC', $this->table)
        );
        $statement->bindValue(':medida_id', $medidaId);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `medida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findPendenteOuEmAndamentoByMedidaId(string $medidaId): array
    {
        $all = $this->findByMedidaId($medidaId);
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
            sprintf('UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
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
            sprintf('UPDATE `%s` SET `dados_especificos` = :dados_especificos, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
        );
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    public function ensureDuasSubtarefas(string $medidaId): void
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            return;
        }

        $existentes = $this->findByMedidaId($medidaId);
        if (count($existentes) === 0) {
            $this->create($medidaId, self::TIPO_ORDEM_1, 1, self::STATUS_EM_ANDAMENTO, null);
            $this->create($medidaId, self::TIPO_ORDEM_2, 2, self::STATUS_PENDENTE, null);
            $this->create($medidaId, self::TIPO_ORDEM_3, 3, self::STATUS_PENDENTE, null);
            $this->create($medidaId, self::TIPO_ORDEM_4, 4, self::STATUS_PENDENTE, null);
            return;
        }

        $tiposExistentes = [];
        $statusOrdem1 = self::STATUS_PENDENTE;
        $statusOrdem2 = self::STATUS_PENDENTE;
        $statusOrdem3 = self::STATUS_PENDENTE;
        foreach ($existentes as $row) {
            $tipoExistente = trim((string) ($row['tipo'] ?? ''));
            if ($tipoExistente !== '') {
                $tiposExistentes[$tipoExistente] = true;
            }
            if ($tipoExistente === self::TIPO_ORDEM_1) {
                $statusOrdem1 = trim((string) ($row['status'] ?? self::STATUS_PENDENTE));
            }
            if ($tipoExistente === self::TIPO_ORDEM_2) {
                $statusOrdem2 = trim((string) ($row['status'] ?? self::STATUS_PENDENTE));
            }
            if ($tipoExistente === self::TIPO_ORDEM_3) {
                $statusOrdem3 = trim((string) ($row['status'] ?? self::STATUS_PENDENTE));
            }
        }

        if (!isset($tiposExistentes[self::TIPO_ORDEM_3])) {
            $statusNova = self::STATUS_PENDENTE;
            if ($statusOrdem1 === self::STATUS_CONCLUIDA && $statusOrdem2 === self::STATUS_CONCLUIDA) {
                $statusNova = self::STATUS_CONCLUIDA;
            }
            $this->create($medidaId, self::TIPO_ORDEM_3, 3, $statusNova, null);
            $statusOrdem3 = $statusNova;
            $tiposExistentes[self::TIPO_ORDEM_3] = true;
        }

        if (!isset($tiposExistentes[self::TIPO_ORDEM_4])) {
            $statusNova4 = self::STATUS_PENDENTE;
            if ($statusOrdem1 === self::STATUS_CONCLUIDA && $statusOrdem2 === self::STATUS_CONCLUIDA && $statusOrdem3 === self::STATUS_CONCLUIDA) {
                $statusNova4 = self::STATUS_CONCLUIDA;
            }
            $this->create($medidaId, self::TIPO_ORDEM_4, 4, $statusNova4, null);
        }
    }

    public function deleteByMedidaId(string $medidaId): void
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            return;
        }

        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `medida_id` = :medida_id', $this->table)
        );
        $statement->bindValue(':medida_id', $medidaId);
        $statement->execute();
    }
}
