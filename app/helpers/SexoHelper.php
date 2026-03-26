<?php
declare(strict_types=1);

final class SexoHelper
{
    public const FEMININO = 'Feminino';
    public const MASCULINO = 'Masculino';
    public const OUTRO = 'Outro';

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [self::FEMININO, self::MASCULINO, self::OUTRO];
    }

    public static function normalize(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);

        if (in_array($lower, ['f', 'feminino', 'mulher'], true)) {
            return self::FEMININO;
        }
        if (in_array($lower, ['m', 'masculino', 'homem'], true)) {
            return self::MASCULINO;
        }
        if (in_array($lower, ['outro', 'o', 'nao_binario', 'não_binário', 'nao binario', 'não binário'], true)) {
            return self::OUTRO;
        }

        return $v;
    }

    public static function isValid(string $value): bool
    {
        return in_array(self::normalize($value), self::options(), true);
    }
}
