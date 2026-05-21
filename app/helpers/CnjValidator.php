<?php
declare(strict_types=1);

final class CnjValidator
{
    public static function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function isFormatted(string $value): bool
    {
        return preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', trim($value)) === 1;
    }

    public static function isValid(string $value): bool
    {
        $digits = self::onlyDigits($value);
        if (strlen($digits) !== 20) {
            return false;
        }
        $dv = substr($digits, 7, 2);
        $base = substr($digits, 0, 7) . substr($digits, 9);
        $resto = self::modulo97($base . '00');
        $dvCalculado = str_pad((string) (98 - $resto), 2, '0', STR_PAD_LEFT);
        return $dv === $dvCalculado;
    }

    /**
     * Normaliza para 20 dígitos e valida DV; lança InvalidArgumentException se inválido.
     */
    public static function digits20OrThrow(string $value): string
    {
        $digits = self::onlyDigits($value);
        if (strlen($digits) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos (formato CNJ).');
        }
        if (!self::isValid($digits)) {
            throw new InvalidArgumentException('Número do processo inválido. Verifique o dígito verificador.');
        }

        return $digits;
    }

    private static function modulo97(string $value): int
    {
        $resto = 0;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            if ($char < '0' || $char > '9') {
                continue;
            }
            $resto = (($resto * 10) + (ord($char) - 48)) % 97;
        }
        return $resto;
    }
}
