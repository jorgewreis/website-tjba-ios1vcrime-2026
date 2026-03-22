<?php
declare(strict_types=1);

final class IdGenerator
{
    public static function generateUniquePrefixedId(PDO $connection, string $table, string $column, string $prefix, int $totalLength = 30, int $maxAttempts = 10): string
    {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('O número máximo de tentativas deve ser pelo menos 1.');
        }

        $table = self::validateIdentifier($table);
        $column = self::validateIdentifier($column);
        $query = sprintf('SELECT 1 FROM `%s` WHERE `%s` = ? LIMIT 1', $table, $column);
        $statement = $connection->prepare($query);
        if (!$statement) {
            $info = $connection->errorInfo();
            throw new RuntimeException('Falha ao preparar a consulta de unicidade: ' . ($info[2] ?? 'desconhecido.'));
        }

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = self::buildCandidate($prefix, $totalLength);
            $statement->execute([$candidate]);
            $result = $statement->fetchColumn();
            $statement->closeCursor();
            if ($result === false) {
                return $candidate;
            }
        }

        throw new RuntimeException('Não foi possível gerar um identificador único após ' . $maxAttempts . ' tentativas.');
    }

    /**
     * Gera um ID no padrão do sistema: prefixo (3 letras) + hífen + 26 caracteres hex = 30 caracteres.
     * Ver docs/00-foundation/id-padrao-banco-dados.md
     */
    private static function buildCandidate(string $prefix, int $totalLength): string
    {
        $prefix = strtoupper(trim((string) $prefix));
        if ($prefix === '') {
            throw new InvalidArgumentException('O prefixo não pode ser vazio.');
        }

        $payloadLength = max(1, $totalLength - strlen($prefix) - 1);
        if ($payloadLength === 26) {
            return $prefix . '-' . strtoupper(bin2hex(random_bytes(13)));
        }

        $payload = strtoupper(bin2hex(random_bytes((int) ceil($payloadLength / 2))));
        return $prefix . '-' . substr($payload, 0, $payloadLength);
    }

    public static function validateIdentifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new InvalidArgumentException('Identificador inválido: ' . $value);
        }

        return $value;
    }
}
