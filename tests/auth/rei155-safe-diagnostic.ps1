function Format-AuthDockerFailure([string]$action, [int]$code, [object[]]$output) {
    $safeActions = @('exec', 'create', 'start', 'inspect', 'network', 'image', 'cp', 'rm', 'stop')
    if ($action -notin $safeActions) { $action = 'operação' }
    $safeDiagnostics = @('HTTP_DIAG: mail_json JsonException code=4', 'HTTP_DIAG: response_json JsonException code=4')
    $diag = @($output | Where-Object { "$_" -in $safeDiagnostics })
    if (@($output | Where-Object { "$_" -eq 'FAIL http-auth-flow: reset conhecido não chegou ao Mailpit' }).Count) { $diag += 'HTTP_MAIL_NOT_RECEIVED' }
    return "Docker falhou em $action com código $code. $diag"
}
