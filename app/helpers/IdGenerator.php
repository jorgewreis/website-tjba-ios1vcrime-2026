<?php
declare(strict_types=1);

final class IdGenerator
{
    private const DEFAULT_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

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

    private static function buildCandidate(string $prefix, int $totalLength): string
    {
        $prefix = strtoupper(trim((string) $prefix));
        if ($prefix === '') {
            throw new InvalidArgumentException('O prefixo não pode ser vazio.');
        }

        $base = sprintf('%s-', $prefix);
        $payloadLimit = max(1, $totalLength - strlen($base));

        $timestamp = (string) (int) (microtime(true) * 1000);
        if (strlen($timestamp) > $payloadLimit) {
            $timestamp = substr($timestamp, -$payloadLimit);
        }

        $remaining = $payloadLimit - strlen($timestamp);
        $randomLength = $remaining > 0 ? min(max(1, $remaining), 8) : 1;
        $random = self::generateRandomSegment($randomLength);

        $payload = $timestamp . $random;
        if (strlen($payload) > $payloadLimit) {
            $payload = substr($payload, 0, $payloadLimit);
        }

        return $base . $payload;
    }

    private static function generateRandomSegment(int $length, string $alphabet = self::DEFAULT_ALPHABET): string
    {
        $result = '';
        $maxIndex = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $maxIndex)];
        }
        return $result;
    }

    public static function validateIdentifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new InvalidArgumentException('Identificador inválido: ' . $value);
        }

        return $value;
    }
}
