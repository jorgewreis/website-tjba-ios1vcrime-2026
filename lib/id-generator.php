<?php
declare(strict_types=1);

/**
 * Gera um segmento aleatório a partir de um alfabeto definido.
 */
function generateRandomSegment(int $length, string $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
{
    if ($length <= 0) {
        throw new InvalidArgumentException('O tamanho do segmento deve ser maior que zero.');
    }
    $result = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $index = random_int(0, $max);
        $result .= $alphabet[$index];
    }
    return $result;
}

/**
 * Retorna um identificador com prefixo e segmento aleatório.
 */
function generatePrefixedId(string $prefix, int $randomLength = 30, string $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
{
    $prefix = strtoupper($prefix);
    $segment = generateRandomSegment($randomLength, $alphabet);
    return $prefix . '-' . $segment;
}

/**
 * Garante que o identificador seja único na tabela especificada.
 */
function generateUniquePrefixedId(mysqli $connection, string $table, string $column, string $prefix, int $randomLength = 30, int $maxAttempts = 10, string $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
{
    if ($maxAttempts < 1) {
        throw new InvalidArgumentException('O número máximo de tentativas deve ser pelo menos 1.');
    }
    $table = validateIdentifier($table);
    $column = validateIdentifier($column);
    $query = sprintf('SELECT 1 FROM `%s` WHERE `%s` = ? LIMIT 1', $table, $column);
    $statement = $connection->prepare($query);
    if (!$statement) {
        throw new RuntimeException('Falha ao preparar a consulta de unicidade: ' . $connection->error);
    }
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $candidate = generatePrefixedId($prefix, $randomLength, $alphabet);
        $statement->bind_param('s', $candidate);
        $statement->execute();
        $statement->store_result();
        if ($statement->num_rows === 0) {
            $statement->close();
            return $candidate;
        }
    }
    $statement->close();
    throw new RuntimeException('Não foi possível gerar um identificador único após ' . $maxAttempts . ' tentativas.');
}

function validateIdentifier(string $value): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new InvalidArgumentException('Identificador inválido: ' . $value);
    }
    return $value;
}
