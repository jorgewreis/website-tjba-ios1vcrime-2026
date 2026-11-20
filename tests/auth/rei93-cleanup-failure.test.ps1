$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'cleanup-owned.ps1')
$script:case = ''
$script:calls = [Collections.Generic.List[string]]::new()
function docker {
    $argsText = $args -join ' '
    $script:calls.Add($argsText)
    $global:LASTEXITCODE = 0
    if ($argsText -match 'ps -aq --no-trunc --filter label=') {
        switch ($script:case) {
            'lost-one' { return ('b' * 64) }
            'lost-plus-known' { return @(('a' * 64), ('b' * 64)) }
            'lost-ambiguous' { return @(('b' * 64), ('c' * 64)) }
            'lost-malformed' { return 'bad-id' }
            'lost-list-fail' { $global:LASTEXITCODE = 1; return }
            'lost-label-mismatch' { return ('b' * 64) }
            default { return }
        }
    }
    if ($argsText -match 'network inspect') {
        if ($script:case -eq 'network-inspect-fail') { $global:LASTEXITCODE = 1; return }
        return '[{"Id":"net123","Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"},"Containers":{}}]'
    }
    if ($argsText -match '^--context local inspect') {
        if ($script:case -eq 'inspect-fail') { $global:LASTEXITCODE = 1; return }
        if ($script:case -eq 'lost-label-mismatch') { return '[{"Config":{"Labels":{"fokus.auth-e2e":"ffffffffffffffffffffffffffffffff"}}}]' }
        return '[{"Config":{"Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}}}]'
    }
    if ($argsText -match ' rm ') { if ($script:case -eq 'rm-fail') { $global:LASTEXITCODE = 1 }; return }
    if ($argsText -match ' ps ' -and $script:case -eq 'residual') { return ('a' * 64) }
    if ($argsText -match ' ps ' -and $script:case -eq 'verify-fail') { $global:LASTEXITCODE = 1; return }
}
$id = 'a' * 64; $run = '0123456789abcdef0123456789abcdef'
foreach ($case in @('inspect-fail', 'rm-fail', 'residual', 'verify-fail')) {
    $script:case = $case; $script:calls.Clear()
    if (Remove-OwnedAuthContainer 'local' $id 'fokus.auth-e2e' $run) { throw "Falha não detectada: $case" }
    if ($case -eq 'inspect-fail' -and @($script:calls | Where-Object { $_ -match ' rm ' }).Count) { throw 'Removeu após inspect falho.' }
}
$script:case = 'network-inspect-fail'; $script:calls.Clear()
if (Remove-OwnedAuthNetwork 'local' 'name' 'fokus.auth-e2e' $run) { throw 'Falha de inspect da rede ignorada.' }
if (@($script:calls | Where-Object { $_ -match ' rm ' }).Count) { throw 'Removeu rede após inspect falho.' }
$script:case = 'ok'; if (-not (Remove-OwnedAuthContainer 'local' $id 'fokus.auth-e2e' $run)) { throw 'Cleanup válido recusado.' }
if (-not (Remove-OwnedAuthNetwork 'local' 'name' 'fokus.auth-e2e' $run)) { throw 'Cleanup de rede válido recusado.' }
foreach ($case in @('lost-one', 'lost-plus-known')) {
    $script:case = $case; $script:calls.Clear()
    if (-not (Recover-LostAuthCreate 'local' 'fokus.auth-e2e' $run @($id))) { throw "Candidato único não recuperado: $case" }
    if (@($script:calls | Where-Object { $_ -match ' rm ' }).Count -ne 1) { throw "Remoção inesperada: $case" }
}
foreach ($case in @('lost-ambiguous', 'lost-malformed', 'lost-list-fail', 'lost-label-mismatch', 'lost-none')) {
    $script:case = $case; $script:calls.Clear()
    if (Recover-LostAuthCreate 'local' 'fokus.auth-e2e' $run @($id)) { throw "Ambiguidade não recusada: $case" }
    if (@($script:calls | Where-Object { $_ -match ' rm ' }).Count) { throw "Não deveria remover: $case" }
}
Write-Output 'PASS falhas de limpeza recusadas sem Docker real'
