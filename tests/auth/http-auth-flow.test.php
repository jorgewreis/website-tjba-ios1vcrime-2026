<?php
declare(strict_types=1);

function fail(string $m): never { fwrite(STDERR, "FAIL http-auth-flow: $m\n"); exit(1); }
function ok(bool $v, string $m): void { if (!$v) fail($m); }
function responseSuccess(string $body): bool { $decoded = json_decode($body, true); return is_array($decoded) && ($decoded['success'] ?? false) === true; }
function csrf(string $html): string {
    if (!preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $html, $m) && !preg_match('/name="csrf-token"\\s+content="([^"]+)"/', $html, $m)) fail('token CSRF ausente');
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}
/** @return array{status:int,headers:list<string>,body:string} */
function request(string $host, int $port, string $path, string $method = 'GET', array $fields = [], string $cookie = '', bool $json = false): array {
    if (str_contains($path, "\r") || str_contains($path, "\n")) fail('caminho HTTP inválido');
    $socket = @fsockopen($host, $port, $errno, $error, 10);
    if ($socket === false) fail("socket indisponível para $host:$port ($errno $error)");
    stream_set_timeout($socket, 60);
    $headers = ['Accept: application/json, text/html;q=0.9'];
    if ($cookie !== '') $headers[] = "Cookie: $cookie";
    $body = $fields === [] ? '' : ($json ? json_encode($fields, JSON_THROW_ON_ERROR) : http_build_query($fields));
    if ($fields !== []) $headers[] = $json ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded';
    $headers[] = 'Host: ' . $host . ':' . $port;
    $headers[] = 'Connection: close';
    if ($body !== '') $headers[] = 'Content-Length: ' . strlen($body);
    fwrite($socket, "$method $path HTTP/1.1\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body);
    $raw = stream_get_contents($socket);
    $metadata = stream_get_meta_data($socket);
    fclose($socket);
    if ($metadata['timed_out'] ?? false) fail("tempo esgotado na resposta HTTP: $path");
    if ($raw === false || !str_contains($raw, "\r\n\r\n")) fail("resposta HTTP inválida: $path");
    [$head, $content] = explode("\r\n\r\n", $raw, 2);
    $received = explode("\r\n", $head);
    preg_match('#\\s(\\d{3})\\s#', $received[0] ?? '', $status);
    return ['status' => (int)($status[1] ?? 0), 'headers' => $received, 'body' => $content];
}
function req(string $path, string $method = 'GET', array $fields = [], string $cookie = '', bool $json = false): array { return request('app', 8080, $path, $method, $fields, $cookie, $json); }
function sessionCookie(array $headers): string { foreach ($headers as $h) if (stripos($h, 'Set-Cookie: PHPSESSID=') === 0 && preg_match('/PHPSESSID=([^;]+)/', $h, $m)) return 'PHPSESSID=' . $m[1]; fail('cookie de sessão ausente'); }
function refreshedSessionCookie(array $headers, string $fallback): string { foreach ($headers as $h) if (stripos($h, 'Set-Cookie: PHPSESSID=') === 0 && preg_match('/PHPSESSID=([^;]+)/', $h, $m)) return 'PHPSESSID=' . $m[1]; return $fallback; }
function mails(): int { return count(json_decode(request('mailpit', 8025, '/api/v1/messages')['body'], true, flags: JSON_THROW_ON_ERROR)['messages'] ?? []); }

$form = req('/views/public/login-form.php'); $loginCsrf = csrf($form['body']); $loginCookie = sessionCookie($form['headers']);
$invalid = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>'invalid'], $loginCookie);
ok(!responseSuccess($invalid['body']), 'login com CSRF inválido foi aceito');
$login = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>$loginCsrf], $loginCookie);
ok(responseSuccess($login['body']), 'login válido não autenticou: ' . substr($login['body'], 0, 500)); $session = sessionCookie($login['headers']);
$home = req('/home.php', 'GET', [], $session); $logoutCsrf = csrf($home['body']);
$badLogout = req('/logout.php', 'POST', ['csrf_token'=>'invalid'], $session); ok($badLogout['status'] === 403, 'logout com CSRF inválido não retornou 403');

// A second login replaces the persisted token while the first browser session
// remains open. Logging out from that stale session must not revoke the new one.
$secondLoginForm = req('/views/public/login-form.php');
$secondLogin = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>csrf($secondLoginForm['body'])], sessionCookie($secondLoginForm['headers']));
ok(responseSuccess($secondLogin['body']), 'segundo login válido não autenticou');
$secondSession = sessionCookie($secondLogin['headers']);
$staleLogout = req('/logout.php', 'POST', ['csrf_token'=>$logoutCsrf], $session);
ok($staleLogout['status'] === 302, 'logout de sessão antiga não encerrou o cookie local');
$oldSessionAccess = req('/home.php', 'GET', [], $session);
ok(in_array($oldSessionAccess['status'], [302,401,403], true), 'sessão antiga permaneceu autenticada depois do segundo login');
$secondHome = req('/home.php', 'GET', [], $secondSession);
ok($secondHome['status'] === 200, 'logout antigo revogou o login mais recente');
$secondLogout = req('/logout.php', 'POST', ['csrf_token'=>csrf($secondHome['body'])], $secondSession);
ok($secondLogout['status'] === 302, 'logout da sessão mais recente não redirecionou');
$secondClosed = req('/home.php', 'GET', [], $secondSession);
ok(in_array($secondClosed['status'], [302,401,403], true), 'logout recente não encerrou a sessão local');

// The runner seeds synthetic scopes; this successful login must clear them.
$rate = req('/views/public/login-form.php'); $rateCookie = sessionCookie($rate['headers']); $rateLogin = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>csrf($rate['body'])], $rateCookie);
ok(responseSuccess($rateLogin['body']), 'login não limpou rate-limit sintético');

$token = getenv('E2E_RESET_TOKEN') ?: fail('token sintético ausente'); ok((bool)preg_match('/^[a-f0-9]{64}$/', $token), 'token sintético inválido');
$newPassword = 'NovaA' . bin2hex(random_bytes(4)) . '!9';
$resetStart = req('/redefinir_senha.php'); $resetCookie = sessionCookie($resetStart['headers']);
ok((bool) array_filter($resetStart['headers'], static fn (string $h): bool => stripos($h, 'Referrer-Policy: no-referrer') === 0), 'shell de reset precisa impedir envio de referrer');
ok(!str_contains($resetStart['body'], $token), 'token do fragmento não deve aparecer no HTML inicial');
if (!preg_match('/name="reset-bootstrap-csrf"\s+content="([^"]+)"/', $resetStart['body'], $bootstrapCsrfMatch)) fail('CSRF de bootstrap do reset ausente');
$bootstrapCsrf = html_entity_decode($bootstrapCsrfMatch[1], ENT_QUOTES, 'UTF-8');
$badStage = req('/redefinir_senha.php', 'POST', ['reset_action'=>'stage_token','csrf_token'=>'invalid','token'=>$token], $resetCookie);
ok($badStage['status'] === 419, 'bootstrap do reset deve rejeitar CSRF inválido');
$staged = req('/redefinir_senha.php', 'POST', ['reset_action'=>'stage_token','csrf_token'=>$bootstrapCsrf,'token'=>$token], $resetCookie);
ok($staged['status'] === 204, 'bootstrap do fragmento não guardou token válido na sessão');
$fragmentForm = req('/views/public/redefinir-senha-form.php', 'GET', [], $resetCookie);
ok(str_contains($fragmentForm['body'], 'id="formRedefinirSenha"'), 'token enviado no corpo não foi recuperado da sessão');
$resetCookie = refreshedSessionCookie($fragmentForm['headers'], $resetCookie);
$legacyQuerySentinel = str_repeat('a', 64);
$legacyShell = req('/redefinir_senha.php?token=' . rawurlencode($legacyQuerySentinel));
ok(!str_contains($legacyShell['body'], $legacyQuerySentinel), 'shell não deve ecoar credencial legada na query');
ok($legacyShell['status'] === 302, 'link legado válido deve migrar para fragmento sem renderizar a página com query');
ok((bool) array_filter($legacyShell['headers'], static fn (string $h): bool => stripos($h, 'Location: /redefinir_senha.php#token=' . $legacyQuerySentinel) === 0), 'link legado deve redirecionar para o fragmento');
ok((bool) array_filter($legacyShell['headers'], static fn (string $h): bool => stripos($h, 'Referrer-Policy: no-referrer') === 0), 'migração do link legado precisa impedir envio de referrer');
ok((bool) array_filter($legacyShell['headers'], static fn (string $h): bool => stripos($h, 'Cache-Control:') === 0 && stripos($h, 'no-store') !== false), 'redirect de link legado não pode ser armazenado em cache');
ok(!str_contains($legacyShell['body'], 'cdn-uicons.flaticon.com'), 'shell do reset não deve incluir recurso externo');
$legacyForm = req('/views/public/redefinir-senha-form.php');
ok(!str_contains($legacyForm['body'], 'id="formRedefinirSenha"'), 'query legada não deve conceder acesso ao formulário');
ok((bool) array_filter($legacyForm['headers'], static fn (string $h): bool => stripos($h, 'Referrer-Policy: no-referrer') === 0), 'formulário precisa impedir envio de referrer');
ok((bool) array_filter($legacyForm['headers'], static fn (string $h): bool => stripos($h, 'Cache-Control:') === 0 && stripos($h, 'no-store') !== false), 'link de reset não pode ser armazenado em cache');
$passwordCookie = $resetCookie;
$password = req('/views/public/redefinir-senha-form.php', 'GET', [], $passwordCookie);
ok(str_contains($password['body'], 'action="/views/public/redefinir-senha-form.php"'), 'formulário deve postar em URL sem token');
ok(!str_contains($password['body'], 'cdn-uicons.flaticon.com'), 'formulário de reset não deve carregar recurso externo');
$badPassword = req('/views/public/redefinir-senha-form.php', 'POST', ['csrf_token'=>'invalid','token'=>$token,'nova_senha'=>$newPassword,'confirmar_senha'=>$newPassword], $passwordCookie);
ok(str_contains($badPassword['body'], 'formRedefinirSenha'), 'CSRF inválido consumiu token de reset');
$password = req('/views/public/redefinir-senha-form.php', 'GET', [], $passwordCookie);
$passwordCookie = refreshedSessionCookie($password['headers'], $passwordCookie);
$goodPassword = req('/views/public/redefinir-senha-form.php', 'POST', ['csrf_token'=>csrf($password['body']),'token'=>$token,'nova_senha'=>$newPassword,'confirmar_senha'=>$newPassword], $passwordCookie);
$resetLoginForm = req('/views/public/login-form.php'); $resetLoginCookie = sessionCookie($resetLoginForm['headers']);
$resetLogin = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>$newPassword,'csrf_token'=>csrf($resetLoginForm['body'])], $resetLoginCookie);
ok(responseSuccess($resetLogin['body']), 'redefinição válida não permitiu novo login: ' . substr($resetLogin['body'], 0, 500));

$resetForm = req('/views/public/recupera-form.php'); $resetCsrf = csrf($resetForm['body']); $resetCookie = sessionCookie($resetForm['headers']); $before = mails();
$badReset = req('/envia_mail.php', 'POST', ['cpf'=>'52998224725','email'=>'ativo@auth-test.invalid','csrf_token'=>'invalid'], $resetCookie, true);
ok($badReset['status'] === 419 && mails() === $before, 'reset com CSRF inválido enviou e-mail ou teve status incorreto');
$known = req('/envia_mail.php', 'POST', ['cpf'=>'52998224725','email'=>'ativo@auth-test.invalid','csrf_token'=>$resetCsrf], $resetCookie, true);
$unknownForm = req('/views/public/recupera-form.php', 'GET', [], $resetCookie); $unknownCsrf = csrf($unknownForm['body']);
$unknown = req('/envia_mail.php', 'POST', ['cpf'=>'12345678909','email'=>'ausente@auth-test.invalid','csrf_token'=>$unknownCsrf], $resetCookie, true);
$knownPayload = json_decode($known['body'], true, flags: JSON_THROW_ON_ERROR); $unknownPayload = json_decode($unknown['body'], true, flags: JSON_THROW_ON_ERROR);
ok($knownPayload === $unknownPayload, 'reset conhecido e desconhecido são distinguíveis: conhecido=' . $known['body'] . ' desconhecido=' . $unknown['body']);
ok(mails() > $before, 'reset conhecido não chegou ao Mailpit');
echo "PASS http-auth-flow\n";
