<?php
declare(strict_types=1);

final class AccessAreas
{
    public const CARTORIO = 'CARTORIO';
    public const GABINETE = 'GABINETE';
    public const AUDIENCIAS = 'AUDIENCIAS';

    /** Valores permitidos em setores.codigo_area */
    public const ALL = [
        self::CARTORIO,
        self::GABINETE,
        self::AUDIENCIAS,
    ];
}
