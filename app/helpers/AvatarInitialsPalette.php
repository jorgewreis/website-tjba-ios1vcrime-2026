<?php
declare(strict_types=1);

/**
 * Paleta aleatória para avatar por iniciais (regenerada a cada login bem-sucedido).
 * Mantém contraste entre fundo e texto para leitura.
 */
final class AvatarInitialsPalette
{
    private const SESSION_BG = 'avatar_initials_palette_bg';

    private const SESSION_FG = 'avatar_initials_palette_fg';

    /**
     * Gera novas cores e grava na sessão (chamar após login).
     */
    public static function regenerateOnLogin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        [$bg, $fg] = self::generateContrastingPair();
        $_SESSION[self::SESSION_BG] = $bg;
        $_SESSION[self::SESSION_FG] = $fg;
    }

    /**
     * Garante paleta na sessão (ex.: primeira carga após migração ou sessão antiga).
     *
     * @return array{bg: string, fg: string}
     */
    public static function getOrCreate(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return self::generateContrastingPair();
        }
        $bg = isset($_SESSION[self::SESSION_BG]) ? trim((string) $_SESSION[self::SESSION_BG]) : '';
        $fg = isset($_SESSION[self::SESSION_FG]) ? trim((string) $_SESSION[self::SESSION_FG]) : '';
        if ($bg === '' || $fg === '' || !self::isHexColor($bg) || !self::isHexColor($fg)) {
            [$bg, $fg] = self::generateContrastingPair();
            $_SESSION[self::SESSION_BG] = $bg;
            $_SESSION[self::SESSION_FG] = $fg;
        }
        return ['bg' => $bg, 'fg' => $fg];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function generateContrastingPair(): array
    {
        $bg = self::randomBackgroundHex();
        $fg = self::contrastingForegroundForBackground($bg);
        return [$bg, $fg];
    }

    private static function randomBackgroundHex(): string
    {
        // Cores saturadas mas não extremas (legíveis com texto claro ou escuro).
        $r = random_int(50, 200);
        $g = random_int(50, 200);
        $b = random_int(50, 200);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function contrastingForegroundForBackground(string $hexBg): string
    {
        $hex = ltrim($hexBg, '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#1a1a1a';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Luminância relativa (simplificada)
        $l = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        return $l > 0.55 ? '#1a1a1a' : '#ffffff';
    }

    private static function isHexColor(string $s): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $s);
    }
}
