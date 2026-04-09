<?php
declare(strict_types=1);

final class CartorioAlocacaoTipo
{
    public const ALL = 'all';
    public const RULE = 'rule';
    public const SPECIFIC = 'specific';

    /**
     * @return list<string>
     */
    public static function todos(): array
    {
        return [self::ALL, self::RULE, self::SPECIFIC];
    }

    /**
     * @param list<string>|null $allow
     */
    public static function normalizar(string $tipo, ?array $allow = null, string $fallback = self::SPECIFIC): string
    {
        $tipo = strtolower(trim($tipo));
        $permitidos = $allow ?? self::todos();
        if (in_array($tipo, $permitidos, true)) {
            return $tipo;
        }
        return $fallback;
    }

    /**
     * @param list<string>|null $allow
     */
    public static function valido(string $tipo, ?array $allow = null): bool
    {
        $tipo = strtolower(trim($tipo));
        $permitidos = $allow ?? self::todos();
        return in_array($tipo, $permitidos, true);
    }

    public static function prioridade(string $tipo): int
    {
        $tipo = strtolower(trim($tipo));
        if ($tipo === self::ALL) {
            return 0;
        }
        if ($tipo === self::RULE) {
            return 1;
        }
        if ($tipo === self::SPECIFIC) {
            return 2;
        }
        return 99;
    }
}

