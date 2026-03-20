<?php
/**
 * Mapa de ícones e variante visual (error-page-code--*) por código HTTP.
 * path: URL a partir da raiz do site (/assets/...).
 */
return [
    '401' => [
        'path' => '/assets/icons/Road-Sign-Warning-Alternate--Streamline-Ultimate.png',
        'alt' => 'Ícone de autenticação',
        'variant' => 'warning',
    ],
    '403' => [
        'path' => '/assets/icons/Alert-Triangle--Streamline-Ultimate.png',
        'alt' => 'Ícone de acesso negado',
        'variant' => 'danger',
    ],
    '404' => [
        'path' => '/assets/icons/Link-Broken--Streamline-Ultimate.png',
        'alt' => 'Ícone de página não encontrada',
        'variant' => 'info',
    ],
    '500' => [
        'path' => '/assets/icons/Server-Warning-1--Streamline-Ultimate.png',
        'alt' => 'Ícone de erro no servidor',
        'variant' => 'danger',
    ],
    '503' => [
        'path' => '/assets/icons/Maintenance-Wrench--Streamline-Ultimate.png',
        'alt' => 'Ícone de manutenção',
        'variant' => 'maintenance',
    ],
];
