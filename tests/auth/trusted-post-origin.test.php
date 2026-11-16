<?php
declare(strict_types=1);

// Carrega somente configuração sintética, sem criar conexão de banco ou rede.
$path = __DIR__ . '/.trusted-origin-' . bin2hex(random_bytes(12)) . '.runtime';
$env = "APP_ENV=testing\nAUTH_TEST_ISOLATION=isolated-auth-test\nDB_HOST=127.0.0.1\nDB_PORT=1\nDB_NAME=auth_test_origin\nDB_USER=fixture\nDB_PASS=fixture\nMAIL_DRIVER=smtp\nMAIL_HOST=127.0.0.1\nMAIL_USER=fixture\nMAIL_ENCRYPTION=none\nMAIL_FROM_ADDRESS=fixture@auth-test.invalid\nAPP_URL=https://auth-test.invalid\n";
if (file_put_contents($path, $env, LOCK_EX) === false) {
    throw new RuntimeException('Não foi possível criar configuração sintética.');
}
try {
    putenv('APP_ENV=testing');
    putenv('AUTH_TEST_ENV_FILE=' . $path);
    require dirname(__DIR__, 2) . '/app/helpers/BootstrapHelper.php';

    $cases = [
        ['mesma origem HTTP', 'POST', 'example.test', 'http://example.test', '', false, true],
        ['porta explícita igual', 'POST', 'example.test:8080', 'http://example.test:8080', '', false, true],
        ['porta diferente', 'POST', 'example.test:8080', 'http://example.test:8081', '', false, false],
        ['referer com porta diferente', 'POST', 'example.test:8080', '', 'http://example.test:8081/page', false, false],
        ['referer mesma origem', 'POST', 'example.test:8080', '', 'http://example.test:8080/page?q=1', false, true],
        ['referer com esquema diferente', 'POST', 'example.test', '', 'https://example.test/page', false, false],
        ['HTTPS padrão explícito', 'POST', 'example.test', 'https://example.test:443', '', true, true],
        ['IPv6 e porta iguais', 'POST', '[::1]:8443', 'https://[::1]:8443', '', true, true],
        ['IPv6 e porta diferentes', 'POST', '[::1]:8443', 'https://[::1]:9443', '', true, false],
        ['IPv6 com referer válido', 'POST', '[::1]', '', 'http://[::1]:80/page', false, true],
        ['HTTP padrão explícito', 'POST', 'example.test:80', 'http://example.test', '', false, true],
        ['host diferente', 'POST', 'example.test', 'http://evil.test', '', false, false],
        ['origem ausente', 'POST', 'example.test', '', '', false, false],
        ['origem inválida tem precedência', 'POST', 'example.test', 'null', 'http://example.test/page', false, false],
        ['origem com caminho', 'POST', 'example.test', 'http://example.test/path', '', false, false],
        ['método inválido', 'GET', 'example.test', 'http://example.test', '', false, false],
    ];
    foreach ($cases as [$label, $method, $host, $origin, $referer, $https, $expected]) {
        $_SERVER = ['REQUEST_METHOD' => $method, 'HTTP_HOST' => $host, 'HTTP_ORIGIN' => $origin,
            'HTTP_REFERER' => $referer, 'HTTPS' => $https ? 'on' : 'off'];
        if (bootstrapRequireTrustedPostRequest() !== $expected) {
            throw new RuntimeException('Caso falhou: ' . $label);
        }
    }
    putenv('TRUSTED_PROXIES=192.0.2.1');
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'example.test',
        'HTTP_ORIGIN' => 'https://example.test', 'HTTP_X_FORWARDED_PROTO' => 'https',
        'REMOTE_ADDR' => '192.0.2.2', 'HTTPS' => 'off'];
    if (bootstrapRequireTrustedPostRequest()) {
        throw new RuntimeException('Proxy não confiável influenciou o esquema.');
    }
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
    if (!bootstrapRequireTrustedPostRequest()) {
        throw new RuntimeException('Proxy confiável não foi reconhecido.');
    }
    echo 'PASS trusted-post-origin (' . (count($cases) + 2) . " casos)\n";
} finally {
    unlink($path);
}
