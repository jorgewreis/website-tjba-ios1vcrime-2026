<?php
/**
 * Configuração para a consulta pública do DataJud (CNJ).
 * As credenciais estão armazenadas via variável de ambiente para evitar hardcode.
 */

return [
    'endpoint' => 'https://api-publica.datajud.cnj.jus.br/api_publica_tjba/_search',
    'api_key' => getenv('DATAJUD_API_KEY') ?: 'cDZHYzlZa0JadVREZDJCendQbXY6SkJlTzNjLV9TRENyQk1RdnFKZGRQdw==',
    'timeout' => 30,
];
