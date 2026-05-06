<?php

declare(strict_types=1);

/**
 * Regra de produtividade para consulta de CEP (8 dígitos, ViaCEP com sucesso).
 * ID alinhado a docs/database/seed-consulta-cep-produtividade-regra.sql (hash estável do código lógico).
 */
return [
    'regra_id' => 'PRG-E4AED8332C544E3AE27FF8535E',
    'tipo_ato' => 'Consulta',
    'tipo_documento' => 'CEP',
    'sistema_modulo' => 'consultas-cep',
    'referencia_sistema' => 'consultas-cep',
    // Janela de deduplicacao da mesma consulta para o mesmo usuario.
    // Ex.: 10 = permite nova pontuacao apos 10 minutos.
    'dedupe_window_minutes' => 10,
];
