<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';

function http158(string $request): string {
    $s = fsockopen('app', 8080, $code, $error, 10);
    if ($s === false) throw new RuntimeException('Conexão sintética indisponível.');
    stream_set_timeout($s, 30);
    fwrite($s, $request);
    $result = stream_get_contents($s);
    $meta = stream_get_meta_data($s);
    fclose($s);
    if ($result === false || ($meta['timed_out'] ?? false)) throw new RuntimeException('Resposta sintética incompleta.');
    return $result;
}

// Each request receives a separate session so PHP session locking cannot
// accidentally serialize the login attempts being tested.
$sessions = [];
for ($i = 0; $i < 9; $i++) {
    $raw = http158("GET /views/public/login-form.php HTTP/1.1\r\nHost: app:8080\r\nConnection: close\r\n\r\n");
    if (!preg_match('/Set-Cookie:\s*PHPSESSID=([^;\r\n]+)/i', $raw, $cookie) ||
        !preg_match('/name="csrf_token"\s+value="([^"]+)"/', $raw, $csrf)) {
        throw new RuntimeException('Não foi possível preparar sessões sintéticas independentes.');
    }
    $sessions[] = [$cookie[1], html_entity_decode($csrf[1], ENT_QUOTES, 'UTF-8')];
}
if (count(array_unique(array_column($sessions, 0))) !== count($sessions)) {
    throw new RuntimeException('Sessões sintéticas não são independentes.');
}
$sockets = [];
foreach ($sessions as [$cookie, $csrf]) {
    $s = fsockopen('app', 8080, $code, $error, 10);
    if ($s === false) throw new RuntimeException('Conexão concorrente indisponível.');
    stream_set_timeout($s, 30);
    $body = http_build_query(['cpf' => '529.982.247-25', 'senha' => 'senha-incorreta-sintetica', 'csrf_token' => $csrf]);
    $sockets[] = [$s, "POST /login.php HTTP/1.1\r\nHost: app:8080\r\nCookie: PHPSESSID=$cookie\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n$body"];
}
foreach ($sockets as [$s, $request]) fwrite($s, $request);
$invalid = $blocked = 0;
foreach ($sockets as [$s]) {
    $raw = stream_get_contents($s);
    $meta = stream_get_meta_data($s);
    fclose($s);
    if ($raw === false || ($meta['timed_out'] ?? false) || !str_contains($raw, "\r\n\r\n")) throw new RuntimeException('Resposta concorrente incompleta.');
    [, $body] = explode("\r\n\r\n", $raw, 2);
    $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    if (($data['success'] ?? null) !== false) throw new RuntimeException('Tentativa sintética não foi recusada.');
    $message = (string)($data['message'] ?? '');
    if (str_contains($message, 'CPF ou senha inválidos')) $invalid++;
    elseif (str_contains($message, 'Muitas tentativas')) $blocked++;
    else throw new RuntimeException('Resposta inesperada no teste concorrente.');
}
// Default fixture limit is five: the fifth invalid attempt reports the lock.
if ($invalid !== 4 || $blocked !== 5) throw new RuntimeException('Admissões HTTP excederam o limite sintético.');
echo "PASS REI-158 HTTP concorrente com sessões independentes\n";
