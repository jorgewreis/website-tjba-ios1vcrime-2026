<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class RulesAllocator
{
    private PDO $connection;
    private string $rulesTable;

    public function __construct(PDO $connection, string $rulesTable = 'rules')
    {
        $this->connection = $connection;
        $this->rulesTable = $rulesTable;
    }

    public function getRule(string $ruleName): ?array
    {
        error_log("DEBUG: Calling getRule for ruleName: " . $ruleName);
        if (!$this->tableExists()) {
            error_log("DEBUG: Rules table does not exist in getRule.");
            return null;
        }

        $table = IdGenerator::validateIdentifier($this->rulesTable);
        $sql = sprintf('SELECT `id_rule`, `nome`, `id_user` FROM `%s` WHERE `nome` = :nome LIMIT 1', $table);
        error_log("DEBUG SQL (getRule): " . $sql . " (binding :nome to " . $ruleName . ")");
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':nome', $ruleName);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function getUsers(): array
    {
        error_log("DEBUG: Calling getUsers.");
        // A tabela 'Usuarios' é um nome fixo aqui, não $this->rulesTable
        if (!$this->tableExists('Usuarios')) {
            error_log("DEBUG: 'Usuarios' table does not exist in getUsers.");
            return [];
        }

        $sql = 'SELECT `UserId`, `UserNome` FROM `Usuarios` ORDER BY `UserNome`';
        error_log("DEBUG SQL (getUsers): " . $sql); // Esta é a query que precisamos verificar

        $statement = $this->connection->prepare($sql);
        $statement->execute();

        $users = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($row['UserId'] === null || trim((string) $row['UserNome']) === '') {
                continue;
            }

            $users[] = [
                'id' => (string) $row['UserId'],
                'name' => trim((string) $row['UserNome']),
            ];
        }

        return $users;
    }

    public function assignServer(string $ruleName, string $userId): array
    {
        error_log("DEBUG: Calling assignServer for ruleName: " . $ruleName . ", userId: " . $userId);
        $userId = trim($userId);
        if ($userId === '') {
            throw new InvalidArgumentException('O servidor informado é invlido.');
        }

        if (!$this->tableExists()) {
            throw new RuntimeException('A tabela de regras não está disponível.');
        }

        if (!$this->isUserRegistered($userId)) {
            throw new InvalidArgumentException('O servidor informado não está cadastrado.');
        }

        $ruleId = $this->buildRuleId();
        $table = IdGenerator::validateIdentifier($this->rulesTable);
        $existing = $this->getRule($ruleName);

        if ($existing !== null) {
            $sql = sprintf('UPDATE `%s` SET `id_rule` = :id_rule, `id_user` = :id_user WHERE `nome` = :nome LIMIT 1', $table);
            error_log("DEBUG SQL (assignServer - UPDATE): " . $sql . " (binding :id_rule to " . $ruleId . ", :id_user to " . $userId . ", :nome to " . $ruleName . ")");
        } else {
            $sql = sprintf('INSERT INTO `%s` (`id_rule`, `nome`, `id_user`) VALUES (:id_rule, :nome, :id_user)', $table);
            error_log("DEBUG SQL (assignServer - INSERT): " . $sql . " (binding :id_rule to " . $ruleId . ", :id_user to " . $userId . ", :nome to " . $ruleName . ")");
        }

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id_rule', $ruleId);
        $statement->bindValue(':id_user', $userId);
        $statement->bindValue(':nome', $ruleName);
        $statement->execute();

        return $this->getRule($ruleName);
    }

    public function getAssignedUserName(string $ruleName): ?string
    {
        error_log("DEBUG: Calling getAssignedUserName for ruleName: " . $ruleName);
        $rule = $this->getRule($ruleName);
        if ($rule === null || empty($rule['id_user'])) {
            return null;
        }

        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['id'] === (string) $rule['id_user']) {
                return $user['name'];
            }
        }

        return null;
    }

    public function tableExists(string $tableName = null): bool
    {
        $target    = $tableName ?? $this->rulesTable;
        $validated = IdGenerator::validateIdentifier($target);
    
        // Escapa o nome da tabela como literal de string
        $quoted = $this->connection->quote($validated);   // adiciona aspas simples e trata caracteres especiais
    
        // Não usamos placeholder aqui
        $sql = "SHOW TABLES LIKE $quoted";
    
        // Opcional: log para depuração
        error_log("DEBUG SQL (tableExists): $sql");
    
        $statement = $this->connection->query($sql);
        if ($statement === false) {
            return false;
        }
    
        return $statement->fetchColumn() !== false;
    }

    private function buildRuleId(): string
    {
        error_log("DEBUG: Calling buildRuleId.");
        $segmentLength = $this->computeSegmentLength($this->rulesTable, 'id_rule', 'RUL', 30);
        return IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->rulesTable,
            'id_rule',
            'RUL',
            $segmentLength,
            10
        );
    }

    private function computeSegmentLength(string $table, string $column, string $prefix, int $defaultLength): int
    {
        error_log("DEBUG: Calling computeSegmentLength for table: " . $table . ", column: " . $column);
        $columnLength = $this->getColumnLength($table, $column);
        $prefixLen = strlen($prefix) + 1;

        if ($columnLength === null) {
            return max(1, $defaultLength);
        }

        $maxSegment = $columnLength - $prefixLen;
        if ($maxSegment < 1) {
            return 1;
        }

        return min($defaultLength, $maxSegment);
    }

    private function getColumnLength(string $table, string $column): ?int
    {
        error_log("DEBUG: Calling getColumnLength for table: " . $table . ", column: " . $column);
        $schema = $this->getDatabaseName();
        if ($schema === null) {
            error_log("DEBUG: Database name is null in getColumnLength.");
            return null;
        }

        $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS ' .
            'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1';
        error_log("DEBUG SQL (getColumnLength): " . $sql . " (binding :schema to " . $schema . ", :table to " . IdGenerator::validateIdentifier($table) . ", :column to " . IdGenerator::validateIdentifier($column) . ")");
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':schema', $schema);
        $statement->bindValue(':table', IdGenerator::validateIdentifier($table));
        $statement->bindValue(':column', IdGenerator::validateIdentifier($column));
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        if ($result === false || $result['CHARACTER_MAXIMUM_LENGTH'] === null) {
            return null;
        }

        return (int) $result['CHARACTER_MAXIMUM_LENGTH'];
    }

    private function getDatabaseName(): ?string
    {
        error_log("DEBUG: Calling getDatabaseName.");
        $sql = 'SELECT DATABASE() AS db';
        error_log("DEBUG SQL (getDatabaseName): " . $sql);
        $statement = $this->connection->query($sql);
        if ($statement === false) {
            error_log("DEBUG: getDatabaseName query failed.");
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row['db'] ?? null;
    }

    private function isUserRegistered(string $userId): bool
    {
        error_log("DEBUG: Calling isUserRegistered for userId: " . $userId);
        if (!$this->tableExists('Usuarios')) {
            error_log("DEBUG: 'Usuarios' table does not exist in isUserRegistered.");
            return false;
        }

        $sql = 'SELECT 1 FROM `Usuarios` WHERE `UserId` = :userId LIMIT 1';
        error_log("DEBUG SQL (isUserRegistered): " . $sql . " (binding :userId to " . $userId . ")");
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':userId', $userId);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }
}
