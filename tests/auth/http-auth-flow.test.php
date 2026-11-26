<?php
declare(strict_types=1);

require __DIR__ . "/sandbox-guard.php";
set_exception_handler(static function (Throwable $e): void { echo "HTTP_DIAG: " . ($GLOBALS['phase'] ?? 'unknown') . " " . get_class($e) . " code=" . (string)$e->getCode() . PHP_EOL; exit(1); });

function fail(string $m): never { fwrite(STDERR, 'HTTP_FAIL phase=' . ($GLOBALS['phase'] ?? 'unknown') . PHP_EOL); exit(1); }
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
    foreach ($received as $header) {
        if (preg_match('/^Transfer-Encoding:\s*(.+)$/i', $header, $encoding)) {
            $codings = array_map(static fn(string $v): string => strtolower(trim($v)), explode(',', $encoding[1]));
            if ($codings !== ['chunked']) fail('transfer encoding não suportado');
            $decoded = ''; $offset = 0; $length = strlen($content);
            while (true) {
                $end = strpos($content, "\r\n", $offset);
                if ($end === false) fail('chunk HTTP inválido');
                $line = explode(';', substr($content, $offset, $end - $offset), 2)[0];
                if ($line === '' || !ctype_xdigit($line)) fail('tamanho de chunk inválido');
                $size = hexdec($line); $offset = $end + 2;
                if ($size === 0) {
                    $trailers = substr($content, $offset);
                    if (!preg_match('/\A(?:[!#$%&\x27*+.^_`|~0-9A-Za-z-]+:[^\r\n]*\r\n)*\r\n\z/D', $trailers)) fail('final de chunk inválido');
                    break;
                }
                if ($size > $length - $offset - 2 || substr($content, $offset + $size, 2) !== "\r\n") fail('chunk HTTP incompleto');
                $decoded .= substr($content, $offset, $size); $offset += $size + 2;
            }
            $content = $decoded; break;
        }
    }
    preg_match('#\\s(\\d{3})\\s#', $received[0] ?? '', $status);
    return ['status' => (int)($status[1] ?? 0), 'headers' => $received, 'body' => $content];
}
function req(string $path, string $method = 'GET', array $fields = [], string $cookie = '', bool $json = false): array { return request('app', 8080, $path, $method, $fields, $cookie, $json); }
function sessionCookie(array $headers): string { foreach ($headers as $h) if (stripos($h, 'Set-Cookie: PHPSESSID=') === 0 && preg_match('/PHPSESSID=([^;]+)/', $h, $m)) return 'PHPSESSID=' . $m[1]; fail('cookie de sessão ausente'); }
function refreshedSessionCookie(array $headers, string $fallback): string { foreach ($headers as $h) if (stripos($h, 'Set-Cookie: PHPSESSID=') === 0 && preg_match('/PHPSESSID=([^;]+)/', $h, $m)) return 'PHPSESSID=' . $m[1]; return $fallback; }
function mails(): int { $GLOBALS['phase'] = 'mail_json'; return count(json_decode(request('mailpit', 8025, '/api/v1/messages')['body'], true, flags: JSON_THROW_ON_ERROR)['messages'] ?? []); }

$GLOBALS['phase'] = 'login';
$form = req('/views/public/login-form.php'); $loginCsrf = csrf($form['body']); $loginCookie = sessionCookie($form['headers']);
$invalid = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>'invalid'], $loginCookie);
ok(!responseSuccess($invalid['body']), 'login com CSRF inválido foi aceito');
$login = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>$loginCsrf], $loginCookie);
ok(responseSuccess($login['body']), 'login válido não autenticou: ' . substr($login['body'], 0, 500)); $session = sessionCookie($login['headers']);

// Consultas reais após login, com instituições e usuários exclusivamente sintéticos.
$GLOBALS['phase'] = 'institution_access';
$searchPath = '/api/instituicoes-publicas/search.php?nome=Sintetica';
$anonymousSearch = req($searchPath);
$anonymousBody = json_decode($anonymousSearch['body'], true);
ok($anonymousSearch['status'] === 401 && is_array($anonymousBody) && !array_key_exists('instituicoes', $anonymousBody)
    && !str_contains($anonymousSearch['body'], 'Sintetica') && !str_contains($anonymousSearch['body'], 'INS-SYNTHETIC-'), 'consulta anônima não foi recusada');
$adminSearch = req($searchPath, 'GET', [], $session);
$adminItems = json_decode($adminSearch['body'], true)['instituicoes'] ?? [];
$adminIds = array_column($adminItems, 'id'); sort($adminIds);
ok($adminSearch['status'] === 200 && $adminIds === ['INS-SYNTHETIC-ACTIVE', 'INS-SYNTHETIC-INACTIVE'], 'consulta interna não retornou instituições sintéticas esperadas');
$inactivePath = '/api/instituicoes-publicas/list.php?nome=Sintetica&ativo=0';
$adminInactive = req($inactivePath, 'GET', [], $session);
$adminInactiveData = json_decode($adminInactive['body'], true);
ok($adminInactive['status'] === 200 && is_array($adminInactiveData) && array_column($adminInactiveData['data'] ?? [], 'id') === ['INS-SYNTHETIC-INACTIVE'], 'filtro de inativas interno divergiu');
$GLOBALS['phase'] = 'profile_access';
foreach ([['258.369.147-37', 'CONSULTOR'], ['314.159.265-90', 'MISTO'], ['147.258.369-82', 'ESTUDANTE']] as [$cpf, $role]) {
    $profileForm = req('/views/public/login-form.php');
    $profileLogin = req('/login.php', 'POST', ['cpf'=>$cpf, 'senha'=>'Sintetica!12345', 'csrf_token'=>csrf($profileForm['body'])], sessionCookie($profileForm['headers']));
    ok(responseSuccess($profileLogin['body']), 'login sintético falhou para ' . $role);
    $profileSession = sessionCookie($profileLogin['headers']);
    $profileSearch = req($searchPath, 'GET', [], $profileSession);
    if ($role === 'CONSULTOR') {
        $profileIds = array_column(json_decode($profileSearch['body'], true)['instituicoes'] ?? [], 'id');
        ok($profileSearch['status'] === 200 && $profileIds === ['INS-SYNTHETIC-ACTIVE'], 'consulta externa não restringiu instituições ativas');
        $inactiveList = req($inactivePath, 'GET', [], $profileSession);
        $inactiveData = json_decode($inactiveList['body'], true);
        ok($inactiveList['status'] === 200 && is_array($inactiveData) && array_column($inactiveData['data'] ?? [], 'id') === ['INS-SYNTHETIC-ACTIVE']
            && !str_contains($inactiveList['body'], 'INS-SYNTHETIC-INACTIVE'), 'filtro consultor incluiu instituição inativa');
    } elseif ($role === 'MISTO') {
        $profileIds = array_column(json_decode($profileSearch['body'], true)['instituicoes'] ?? [], 'id');
        sort($profileIds);
        ok($profileSearch['status'] === 200 && $profileIds === ['INS-SYNTHETIC-ACTIVE', 'INS-SYNTHETIC-INACTIVE'], 'perfil misto não preservou a consulta interna');
        $inactiveList = req($inactivePath, 'GET', [], $profileSession);
        $inactiveData = json_decode($inactiveList['body'], true);
        ok($inactiveList['status'] === 200 && is_array($inactiveData) && array_column($inactiveData['data'] ?? [], 'id') === ['INS-SYNTHETIC-INACTIVE'], 'filtro de inativas do perfil misto divergiu');
    } else {
        $deniedBody = json_decode($profileSearch['body'], true);
        ok($profileSearch['status'] === 403 && is_array($deniedBody) && !array_key_exists('instituicoes', $deniedBody)
            && !str_contains($profileSearch['body'], 'Sintetica') && !str_contains($profileSearch['body'], 'INS-SYNTHETIC-'), 'perfil estudante acessou instituições');
    }
}
$GLOBALS['phase'] = 'password_change';
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

// A second login replaces the persisted token while the first browser session
// remains open. Logging out from that stale session must not revoke the new one.
$GLOBALS['phase'] = 'logout_race';
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
$GLOBALS['phase'] = 'http_rate_limit';
$rate = req('/views/public/login-form.php'); $rateCookie = sessionCookie($rate['headers']); $rateLogin = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>'Ativo!12345','csrf_token'=>csrf($rate['body'])], $rateCookie);
ok(responseSuccess($rateLogin['body']), 'login não limpou rate-limit sintético');

$GLOBALS['phase'] = 'password_reset';
$tokenForm = req('/views/public/recupera-form.php');
$tokenCsrf = csrf($tokenForm['body']);
$tokenMailBefore = mails();
$tokenRequest = req('/envia_mail.php', 'POST', ['cpf'=>'52998224725','email'=>'ativo@auth-test.invalid','csrf_token'=>$tokenCsrf], sessionCookie($tokenForm['headers']), true);
$GLOBALS['phase'] = 'reset_token_mail';
if ($tokenRequest['status'] !== 200 || mails() !== $tokenMailBefore + 1) fail('token sintético não foi enviado ao Mailpit');
$latestMail = request('mailpit', 8025, '/view/latest.txt')['body'];
if (!preg_match('~/redefinir_senha\.php#token=([a-f0-9]{64})~i', $latestMail, $tokenMatch)) fail('token sintético ausente no e-mail');
$token = $tokenMatch[1];
$newPassword = 'NovaA' . bin2hex(random_bytes(4)) . '!9';
$GLOBALS['phase'] = 'reset_shell';
$resetStart = req('/redefinir_senha.php'); $resetCookie = sessionCookie($resetStart['headers']);
ok((bool) array_filter($resetStart['headers'], static fn (string $h): bool => stripos($h, 'Referrer-Policy: no-referrer') === 0), 'shell de reset precisa impedir envio de referrer');
ok(!str_contains($resetStart['body'], $token), 'token do fragmento não deve aparecer no HTML inicial');
if (!preg_match('/name="reset-bootstrap-csrf"\s+content="([^"]+)"/', $resetStart['body'], $bootstrapCsrfMatch)) fail('CSRF de bootstrap do reset ausente');
$bootstrapCsrf = html_entity_decode($bootstrapCsrfMatch[1], ENT_QUOTES, 'UTF-8');
$GLOBALS['phase'] = 'reset_invalid_csrf';
$badStage = req('/redefinir_senha.php', 'POST', ['reset_action'=>'stage_token','csrf_token'=>'invalid','token'=>$token], $resetCookie);
ok($badStage['status'] === 419, 'bootstrap do reset deve rejeitar CSRF inválido');
$GLOBALS['phase'] = 'reset_valid_stage';
$staged = req('/redefinir_senha.php', 'POST', ['reset_action'=>'stage_token','csrf_token'=>$bootstrapCsrf,'token'=>$token], $resetCookie);
if ($staged['status'] !== 204) {
    fwrite(STDERR, 'HTTP_FAIL phase=reset_valid_stage status=' . (int) $staged['status'] . PHP_EOL);
    exit(1);
}
$GLOBALS['phase'] = 'reset_staged_form';
$fragmentForm = req('/views/public/redefinir-senha-form.php', 'GET', [], $resetCookie);
ok(str_contains($fragmentForm['body'], 'id="formRedefinirSenha"'), 'token enviado no corpo não foi recuperado da sessão');
$resetCookie = refreshedSessionCookie($fragmentForm['headers'], $resetCookie);
$GLOBALS['phase'] = 'legacy_reset_link';
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
$GLOBALS['phase'] = 'password_update';
$password = req('/views/public/redefinir-senha-form.php', 'GET', [], $passwordCookie);
ok(str_contains($password['body'], 'action="/views/public/redefinir-senha-form.php"'), 'formulário deve postar em URL sem token');
ok(!str_contains($password['body'], 'cdn-uicons.flaticon.com'), 'formulário de reset não deve carregar recurso externo');
$badPassword = req('/views/public/redefinir-senha-form.php', 'POST', ['csrf_token'=>'invalid','token'=>$token,'nova_senha'=>$newPassword,'confirmar_senha'=>$newPassword], $passwordCookie);
ok(str_contains($badPassword['body'], 'formRedefinirSenha'), 'CSRF inválido consumiu token de reset');
$password = req('/views/public/redefinir-senha-form.php', 'GET', [], $passwordCookie);
$passwordCookie = refreshedSessionCookie($password['headers'], $passwordCookie);
$goodPassword = req('/views/public/redefinir-senha-form.php', 'POST', ['csrf_token'=>csrf($password['body']),'token'=>$token,'nova_senha'=>$newPassword,'confirmar_senha'=>$newPassword], $passwordCookie);
$GLOBALS['phase'] = 'password_login';
$resetLoginForm = req('/views/public/login-form.php'); $resetLoginCookie = sessionCookie($resetLoginForm['headers']);
$resetLogin = req('/login.php', 'POST', ['cpf'=>'529.982.247-25','senha'=>$newPassword,'csrf_token'=>csrf($resetLoginForm['body'])], $resetLoginCookie);
ok(responseSuccess($resetLogin['body']), 'redefinição válida não permitiu novo login: ' . substr($resetLogin['body'], 0, 500));

$GLOBALS['phase'] = 'reset_mail';
$resetForm = req('/views/public/recupera-form.php'); $resetCsrf = csrf($resetForm['body']); $resetCookie = sessionCookie($resetForm['headers']); $before = mails();
$badReset = req('/envia_mail.php', 'POST', ['cpf'=>'52998224725','email'=>'ativo@auth-test.invalid','csrf_token'=>'invalid'], $resetCookie, true);
ok($badReset['status'] === 419 && mails() === $before, 'reset com CSRF inválido enviou e-mail ou teve status incorreto');
$known = req('/envia_mail.php', 'POST', ['cpf'=>'52998224725','email'=>'ativo@auth-test.invalid','csrf_token'=>$resetCsrf], $resetCookie, true);
$unknownForm = req('/views/public/recupera-form.php', 'GET', [], $resetCookie); $unknownCsrf = csrf($unknownForm['body']);
$unknown = req('/envia_mail.php', 'POST', ['cpf'=>'12345678909','email'=>'ausente@auth-test.invalid','csrf_token'=>$unknownCsrf], $resetCookie, true);
$GLOBALS['phase'] = 'response_json';
$knownPayload = json_decode($known['body'], true, flags: JSON_THROW_ON_ERROR); $unknownPayload = json_decode($unknown['body'], true, flags: JSON_THROW_ON_ERROR);
ok($knownPayload === $unknownPayload, 'reset conhecido e desconhecido são distinguíveis: conhecido=' . $known['body'] . ' desconhecido=' . $unknown['body']);
ok(mails() > $before, 'reset conhecido não chegou ao Mailpit');
echo "PASS http-auth-flow\n";
