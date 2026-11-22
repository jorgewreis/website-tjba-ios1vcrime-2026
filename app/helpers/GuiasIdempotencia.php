<?php
declare(strict_types=1);

/** Reserva uma chave por ator na mesma transação de criação da guia. */
final class GuiasIdempotencia
{
    public static function payloadHash(array $payload): string
    {
        unset($payload['idempotency_key']);
        $canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value, SORT_STRING);
            foreach ($value as $key => $item) $value[$key] = $canonicalize($item);
            return $value;
        };
        return hash('sha256', json_encode(['v' => 1, 'payload' => $canonicalize($payload)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array{id:string,replayed:bool} */
    public static function reserve(PDO $pdo, string $actor, string $key, string $hash, string $candidate): array
    {
        if (!$pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('Envio seguro requer transação MySQL.');
        $actor = trim($actor);
        if ($actor === '' || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) throw new InvalidArgumentException('Chave de envio inválida.');
        $actorHash = hash('sha256', $actor);
        // Upsert acquires an exclusive lock for an existing key immediately;
        // handling duplicate INSERT and then upgrading a shared lock can
        // deadlock when several identical requests arrive together.
        $insert = $pdo->prepare('INSERT INTO guia_criacao_idempotencia (ator_hash,chave,payload_hash,hash_versao,guia_id,criado_utc) VALUES (?,?,?,1,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE chave=chave');
        $insert->execute([$actorHash, $key, $hash, $candidate]);
        $read = $pdo->prepare('SELECT payload_hash,hash_versao,guia_id,(criado_utc <= UTC_TIMESTAMP(6) - INTERVAL 30 DAY) AS expirou FROM guia_criacao_idempotencia WHERE ator_hash=? AND chave=? FOR UPDATE');
        $read->execute([$actorHash, $key]);
        $row = $read->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Reserva idempotente não encontrada.');
        if ((int)$row['hash_versao'] === 1 && hash_equals((string)$row['guia_id'], $candidate) && hash_equals((string)$row['payload_hash'], $hash)) return ['id' => $candidate, 'replayed' => false];
        if ((int)$row['expirou'] === 1) {
            $replace = $pdo->prepare('UPDATE guia_criacao_idempotencia SET payload_hash=?,hash_versao=1,guia_id=?,criado_utc=UTC_TIMESTAMP(6) WHERE ator_hash=? AND chave=?');
            $replace->execute([$hash, $candidate, $actorHash, $key]);
            return ['id' => $candidate, 'replayed' => false];
        }
        if ((int)$row['hash_versao'] !== 1) throw new RuntimeException('Versão de envio seguro não reconhecida.');
        if (!hash_equals((string)$row['payload_hash'], $hash)) throw new DomainException('Esta chave de envio já foi usada com outros dados.');
        return ['id' => (string)$row['guia_id'], 'replayed' => true];
    }
}
