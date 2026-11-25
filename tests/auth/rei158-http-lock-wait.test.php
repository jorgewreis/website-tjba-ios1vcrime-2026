<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';

$pdo = Conection::getInstance()->getConnection();
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$cpf = '52998224725';
$lock = 'fokus_login_' . substr(hash('sha256', $database . "\0cpf\0" . $cpf), 0, 52);
$owned = false;
$socket = null;
try {
    $form = file_get_contents('http://app:8080/views/public/login-form.php');
    $headers = $http_response_header ?? [];
    if (!is_string($form) || !preg_match('/name="csrf_token"\s+value="([^"]+)"/', $form, $csrf)) throw new RuntimeException('Formulário sintético inválido.');
    $cookie = '';
    foreach ($headers as $header) if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;\r\n]+)/i', $header, $m)) $cookie = $m[1];
    if ($cookie === '') throw new RuntimeException('Sessão sintética indisponível.');

    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Trava sintética indisponível.');
    $owned = true;
    $socket = fsockopen('app', 8080, $code, $error, 10);
    if ($socket === false) throw new RuntimeException('Conexão HTTP indisponível.');
    stream_set_timeout($socket, 15);
    $body = http_build_query(['cpf' => $cpf, 'senha' => 'senha-incorreta-sintetica', 'csrf_token' => html_entity_decode($csrf[1], ENT_QUOTES, 'UTF-8')]);
    fwrite($socket, "POST /login.php HTTP/1.1\r\nHost: app:8080\r\nCookie: PHPSESSID=$cookie\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n$body");

    $wait = $pdo->prepare("SELECT COUNT(*) FROM performance_schema.metadata_locks WHERE OBJECT_TYPE='USER LEVEL LOCK' AND OBJECT_NAME=? AND LOCK_STATUS='PENDING'");
    $observed = false;
    $deadline = microtime(true) + 4;
    do {
        $wait->execute([$lock]);
        if ((int)$wait->fetchColumn() > 0) { $observed = true; break; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    if (!$observed) throw new RuntimeException('Espera do endpoint pela trava não foi observada.');
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Liberação da trava não confirmada.');
    $owned = false;
    $raw = stream_get_contents($socket);
    $meta = stream_get_meta_data($socket);
    if ($raw === false || ($meta['timed_out'] ?? false) || !str_contains($raw, "\r\n\r\n")) throw new RuntimeException('Resposta HTTP incompleta.');
    [, $response] = explode("\r\n\r\n", $raw, 2);
    $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
    if (($data['success'] ?? null) !== false) throw new RuntimeException('Credencial sintética incorreta foi aceita.');
    echo "PASS REI-158 endpoint aguardou lock MySQL\n";
} finally {
    if (is_resource($socket)) fclose($socket);
    if ($owned) { $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
}
