<?php
declare(strict_types=1);

/**
 * Monta o mapa label => valor para a seção "Detalhes técnicos" das páginas de erro.
 *
 * @param string $code    Código HTTP como string ('401', '404', …).
 * @param array<string, string> $override Sobrescreve ou acrescenta chaves.
 * @return array<string, string>
 */
function error_page_merge_details(string $code, array $override = []): array
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
    $request_fullpath = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
    $rawIp = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
    if (strpos($rawIp, ',') !== false) {
        $rawIp = trim(explode(',', $rawIp)[0]);
    }
    $user_id = (string) ($_SESSION['id_usuario'] ?? ($_SESSION['user_id'] ?? ''));
    $datetime_iso = date('Y-m-d H:i:s');
    $datetime_br = date('d/m/Y, H:i:s');

    $defaults = [
        '401' => [
            'Código' => 'HTTP 401 Unauthorized',
            'Status' => 'Não autorizado',
            'Endpoint' => $host . $request_fullpath,
            'Método' => $method,
            'IP do cliente' => $rawIp,
            'Data/Hora' => $datetime_iso,
        ],
        '403' => [
            'Código' => 'HTTP 403 Forbidden',
            'Status' => 'Proibido',
            'Endpoint' => $host . $request_fullpath,
            'Método' => $method,
            'Usuário' => $user_id !== '' ? $user_id : '—',
            'Data/Hora' => $datetime_iso,
        ],
        '404' => [
            'Código de erro' => 'HTTP 404',
            'Status' => 'Não encontrado',
            'URL solicitada' => $request_fullpath,
            'Data/Hora' => $datetime_br,
        ],
        '500' => [
            'Código de erro' => 'HTTP 500',
            'Status' => 'Erro interno do servidor',
            'URL solicitada' => $request_fullpath,
            'Data/Hora' => $datetime_br,
        ],
        '503' => [
            'Código' => 'HTTP 503 Service Unavailable',
            'Status' => 'Serviço indisponível',
            'Previsão' => 'Em breve',
            'Endpoint' => ($host !== '' ? $host : 'localhost') . $request_fullpath,
            'Data/Hora' => $datetime_iso,
        ],
    ];

    $base = $defaults[$code] ?? [
        'Código' => 'HTTP ' . $code,
        'Endpoint' => $host . $request_fullpath,
        'Data/Hora' => $datetime_iso,
    ];

    return array_merge($base, $override);
}
