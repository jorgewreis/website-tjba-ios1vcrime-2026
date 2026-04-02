<?php
declare(strict_types=1);

class RandomPhrase
{
    private const RECENT_LIMIT = 30;

    /**
        * Retorna uma frase aleatória evitando repetir as últimas RECENT_LIMIT.
        */
    public static function getRandom(): string
    {
        self::ensureSession();
        $phrases = self::loadPhrases();
        if (count($phrases) === 0) {
            return '';
        }

        $recent = isset($_SESSION['phrases_recent']) && is_array($_SESSION['phrases_recent'])
            ? array_values($_SESSION['phrases_recent'])
            : [];

        $availableIndexes = array_diff(array_keys($phrases), $recent);
        if (count($availableIndexes) === 0) {
            $availableIndexes = array_keys($phrases);
            $recent = [];
        }

        $idx = (int) array_values($availableIndexes)[random_int(0, count($availableIndexes) - 1)];
        $phrase = $phrases[$idx] ?? '';

        // Atualiza FIFO de recentes
        $recent[] = $idx;
        if (count($recent) > self::RECENT_LIMIT) {
            $recent = array_slice($recent, -self::RECENT_LIMIT);
        }
        $_SESSION['phrases_recent'] = $recent;

        return $phrase;
    }

    private static function loadPhrases(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $path = __DIR__ . '/../data/frases.php';
        if (!file_exists($path)) {
            return $cache = [];
        }
        $data = require $path;
        if (!is_array($data)) {
            return $cache = [];
        }
        // Sanitiza: remove vazias e corta para 200 chars
        $clean = [];
        foreach ($data as $p) {
            $s = trim((string) $p);
            if ($s === '') {
                continue;
            }
            if (mb_strlen($s, 'UTF-8') > 200) {
                $s = mb_substr($s, 0, 200, 'UTF-8');
            }
            $clean[] = $s;
        }
        return $cache = $clean;
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
