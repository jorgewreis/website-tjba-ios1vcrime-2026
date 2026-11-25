<?php
declare(strict_types=1);

require __DIR__ . "/sandbox-guard.php";

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

// Consultas reais após login, com instituições e usuários exclusivamente sintéticos.
$searchPath = '/api/instituicoes-publicas/search.php?nome=Sintetica';
$anonymousSearch = req($searchPath);
$anonymousBody = json_decode($anonymousSearch['body'], true);
ok($anonymousSearch['status'] === 401 && is_array($anonymousBody) && !array_key_exists('instituicoes', $anonymousBody)
    && !str_contains($anonymousSearch['body'], 'Sintetica'), 'consulta anônima não foi recusada');
$adminSearch = req($searchPath, 'GET', [], $session);
$adminItems = json_decode($adminSearch['body'], true)['instituicoes'] ?? [];
$adminIds = array_column($adminItems, 'id'); sort($adminIds);
ok($adminSearch['status'] === 200 && $adminIds === ['INS-SYNTHETIC-ACTIVE', 'INS-SYNTHETIC-INACTIVE'], 'consulta interna não retornou instituições sintéticas esperadas');
foreach ([['258.369.147-37', 'CONSULTOR'], ['147.258.369-82', 'ESTUDANTE']] as [$cpf, $role]) {
    $profileForm = req('/views/public/login-form.php');
    $profileLogin = req('/login.php', 'POST', ['cpf'=>$cpf, 'senha'=>'Sintetica!12345', 'csrf_token'=>csrf($profileForm['body'])], sessionCookie($profileForm['headers']));
    ok(responseSuccess($profileLogin['body']), 'login sintético falhou para ' . $role);
    $profileSession = sessionCookie($profileLogin['headers']);
    $profileSearch = req($searchPath, 'GET', [], $profileSession);
    if ($role === 'CONSULTOR') {
        $profileIds = array_column(json_decode($profileSearch['body'], true)['instituicoes'] ?? [], 'id');
        ok($profileSearch['status'] === 200 && $profileIds === ['INS-SYNTHETIC-ACTIVE'], 'consulta externa não restringiu instituições ativas');
    } else {
        $deniedBody = json_decode($profileSearch['body'], true);
        ok($profileSearch['status'] === 403 && is_array($deniedBody) && !array_key_exists('instituicoes', $deniedBody)
            && !str_contains($profileSearch['body'], 'Sintetica'), 'perfil estudante acessou instituições');
    }
}
$home = req('/home.php', 'GET', [], $session); $logoutCsrf = csrf($home['body']);
// Exercise the real password-change endpoint and real session on the runner's
// disposable database, then restore the synthetic password for later cases.
$changeForm = req('/views/public/alterar-senha-form.php', 'GET', [], $session);
ok($changeForm['status'] === 200, 'formulário autenticado de troca de senha indisponível');
$changeCsrf = csrf($changeForm['body']);
$changeFields = ['csrf_token' => $changeCsrf, 'senha_atual' => 'Ativo!12345', 'nova_senha' => 'NovaSenha!45678', 'confirmar_senha' => 'NovaSenha!45678'];
$badCsrfChange = req('/api/alterar_senha.php', 'POST', array_merge($changeFields, ['csrf_token' => 'invalid']), $session, true);
ok($badCsrfChange['status'] === 403 && !responseSuccess($badCsrfChange['body']), 'troca com CSRF inválido não foi recusada');
$badCurrentChange = req('/api/alterar_senha.php', 'POST', array_merge($changeFields, ['senha_atual' => 'senha-incorreta']), $session, true);
ok($badCurrentChange['status'] === 422 && !responseSuccess($badCurrentChange['body']), 'troca com senha atual incorreta não foi recusada');
$change = req('/api/alterar_senha.php', 'POST', $changeFields, $session, true);
ok($change['status'] === 200 && responseSuccess($change['body']), 'troca de senha autenticada falhou');
$restore = req('/api/alterar_senha.php', 'POST', ['csrf_token' => $changeCsrf, 'senha_atual' => 'NovaSenha!45678', 'nova_senha' => 'Ativo!12345', 'confirmar_senha' => 'Ativo!12345'], $session, true);
ok($restore['status'] === 200 && responseSuccess($restore['body']), 'restauração da senha sintética falhou');
$badLogout = req('/logout.php', 'POST', ['csrf_token'=>'invalid'], $session); ok($badLogout['status'] === 403, 'logout com CSRF inválido não retornou 403');
$logout = req('/logout.php', 'POST', ['csrf_token'=>$logoutCsrf], $session); ok($logout['status'] === 302, 'logout válido não redirecionou');
$closed = req('/home.php', 'GET', [], $session); ok(in_array($closed['status'], [302,401,403], true), 'sessão encerrada acessou área autenticada');

// The runner seeds synthetic scopes; this successful login must clear them.
$rate = req('/views/public/login-form.php'); $rateCookie = sessionCookie($rate['headers']); $rateLogin = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>csrf($rate['body'])], $rateCookie);
ok(responseSuccess($rateLogin['body']), 'login não limpou rate-limit sintético');

$token = getenv('E2E_RESET_TOKEN') ?: fail('token sintético ausente'); ok((bool)preg_match('/^[a-f0-9]{64}$/', $token), 'token sintético inválido');
$newPassword = 'NovaA' . bin2hex(random_bytes(4)) . '!9';
$password = req('/views/public/redefinir-senha-form.php?token=' . rawurlencode($token)); $passwordCookie = sessionCookie($password['headers']);
$badPassword = req('/views/public/redefinir-senha-form.php?token=' . rawurlencode($token), 'POST', ['csrf_token'=>'invalid','token'=>$token,'nova_senha'=>$newPassword,'confirmar_senha'=>$newPassword], $passwordCookie);
ok(str_contains($badPassword['body'], 'formRedefinirSenha'), 'CSRF inválido consumiu token de reset');
$password = req('/views/public/redefinir-senha-form.php?token=' . rawurlencode($token), 'GET', [], $passwordCookie);
$passwordCookie = refreshedSessionCookie($password['headers'], $passwordCookie);
$goodPassword = req('/views/public/redefinir-senha-form.php?token=' . rawurlencode($token), 'POST', ['csrf_token'=>csrf($password['body']),'token'=>$token,'nova_senha'=>$newPassword,'confirmar_senha'=>$newPassword], $passwordCookie);
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
