<?php
declare(strict_types=1);

/** Nomenclatura do CNJ/DataJud. O indicador de sigilo existente continua controlando o acesso. */
final class ProcessoNivelSigilo
{
    public const ROTULOS = [
        1 => 'Segredo de Justiça',
        2 => 'Sigilo Mínimo',
        3 => 'Sigilo Médio',
        4 => 'Sigilo Intenso',
        5 => 'Sigilo Absoluto',
    ];

    public static function classificado(mixed $valor): ?int
    {
        if (!is_int($valor) && !(is_string($valor) && preg_match('/^[1-5]$/D', $valor) === 1)) {
            return null;
        }
        $nivel = (int) $valor;
        return isset(self::ROTULOS[$nivel]) ? $nivel : null;
    }

    public static function rotulo(bool $sigiloso, mixed $nivel = null): ?string
    {
        return $sigiloso ? self::ROTULOS[self::classificado($nivel) ?? 1] : null;
    }
}
