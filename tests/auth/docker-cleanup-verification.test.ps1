$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/docker-cleanup-verification.ps1"

function Assert-Equal($actual, $expected, [string]$name) {
    if($actual -ne $expected) { throw "$name esperado '$expected', recebido '$actual'." }
}
function New-Probe([object[]]$responses) {
    $state = [pscustomobject]@{ Responses = $responses; Index = 0 }
    return {
        param([string[]]$arguments)
        if($state.Index -ge $state.Responses.Count) { throw 'Probe chamado além das respostas configuradas.' }
        $response = $state.Responses[$state.Index]
        $state.Index++
        return $response
    }.GetNewClosure()
}
function Response([int]$exitCode, [string[]]$output = @()) {
    return [pscustomobject]@{ ExitCode = $exitCode; Output = $output }
}

$id = 'a' * 64
$probe = New-Probe @((Response 1), (Response 0 @()))
Assert-Equal (Test-DockerResourceAbsent -Kind container -Identifier $id -Probe $probe) $true 'Container ausente confirmado após inspect falhar'

$probe = New-Probe @((Response 1), (Response 0 @($id)))
Assert-Equal (Test-DockerResourceAbsent -Kind container -Identifier $id -Probe $probe) $false 'Container ainda listado após inspect falhar'

$probe = New-Probe @((Response 1), (Response 1 @('daemon indisponível')))
$threw = $false
try { Test-DockerResourceAbsent -Kind container -Identifier $id -Probe $probe | Out-Null } catch { $threw = $true }
Assert-Equal $threw $true 'Falha ambígua na verificação secundária'

$probe = New-Probe @((Response 1), (Response 0 @('daemon indisponível')))
$threw = $false
try { Test-DockerResourceAbsent -Kind container -Identifier $id -Probe $probe | Out-Null } catch { $threw = $true }
Assert-Equal $threw $true 'Saída inesperada com exit zero'

$network = 'fokus-auth-e2e-test'
$probe = New-Probe @((Response 1), (Response 0 @()))
Assert-Equal (Test-DockerResourceAbsent -Kind network -Identifier $network -Probe $probe) $true 'Rede ausente confirmada após inspect falhar'

$probe = New-Probe @((Response 1), (Response 0 @($network)))
Assert-Equal (Test-DockerResourceAbsent -Kind network -Identifier $network -Probe $probe) $false 'Rede ainda listada após inspect falhar'

$probe = New-Probe @((Response 0 @('{}')))
Assert-Equal (Test-DockerResourceAbsent -Kind container -Identifier $id -Probe $probe) $false 'Inspect positivo impede afirmar ausência'

$probe = New-Probe @((Response 1), (Response 0 @('invalid network name')))
$threw = $false
try { Test-DockerResourceAbsent -Kind network -Identifier $network -Probe $probe | Out-Null } catch { $threw = $true }
Assert-Equal $threw $true 'Nome de rede malformado'

Write-Output 'PASS docker-cleanup-verification (8 cenários)'
