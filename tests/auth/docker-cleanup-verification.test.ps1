$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/docker-cleanup-verification.ps1"

$runnerPath = Join-Path $PSScriptRoot 'run-isolated-auth-e2e.ps1'
$runnerTokens = $null
$runnerParseErrors = $null
$runnerAst = [System.Management.Automation.Language.Parser]::ParseFile($runnerPath, [ref]$runnerTokens, [ref]$runnerParseErrors)
if ($runnerParseErrors.Count -ne 0) { throw 'Runner PowerShell inválido.' }
$networkInspectFunction = $runnerAst.Find({
    param($node)
    $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq 'Get-DockerNetworkInspectObject'
}, $true)
if ($null -eq $networkInspectFunction) { throw 'Validador de resposta do inspect de rede ausente no runner.' }
Invoke-Expression $networkInspectFunction.Extent.Text

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
$networkLabelKey = 'fokus.auth-e2e'
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

$validNetworkInspect = '[{"Name":"fokus-auth-e2e-test","Containers":{},"Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}}]'
$parsedNetwork = Get-DockerNetworkInspectObject -Inspection (Response 0 @($validNetworkInspect)) -ExpectedName $network -ExpectedLabelKey $networkLabelKey
Assert-Equal $parsedNetwork.Name $network 'Inspeção válida da rede produz objeto'

$invalidNetworkInspectOutputs = @(
    [pscustomobject]@{ Name = 'stdout vazio'; Output = @() },
    [pscustomobject]@{ Name = 'JSON null'; Output = @('null') },
    [pscustomobject]@{ Name = 'array vazio'; Output = @('[]') },
    [pscustomobject]@{ Name = 'dois objetos'; Output = @('[{"Name":"fokus-auth-e2e-test","Containers":{},"Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}},{"Name":"outra-rede","Containers":{},"Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}}]') },
    [pscustomobject]@{ Name = 'nome diferente do solicitado'; Output = @('[{"Name":"outra-rede","Containers":{},"Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}}]') },
    [pscustomobject]@{ Name = 'objeto incompleto sem Name'; Output = @('[{"Containers":{},"Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}}]') },
    [pscustomobject]@{ Name = 'objeto incompleto sem label esperado'; Output = @('[{"Name":"fokus-auth-e2e-test","Containers":{},"Labels":{"outra":"valor"}}]') },
    [pscustomobject]@{ Name = 'objeto incompleto sem mapa Containers'; Output = @('[{"Name":"fokus-auth-e2e-test","Labels":{"fokus.auth-e2e":"0123456789abcdef0123456789abcdef"}}]') },
    [pscustomobject]@{ Name = 'JSON inválido'; Output = @('{') }
)
foreach($case in $invalidNetworkInspectOutputs) {
    $threw = $false
    $inspection = Response 0 $case.Output
    Assert-Equal $inspection.ExitCode 0 "Inspect sintético $($case.Name) retorna exit code zero"
    try { Get-DockerNetworkInspectObject -Inspection $inspection -ExpectedName $network -ExpectedLabelKey $networkLabelKey | Out-Null } catch { $threw = $true }
    Assert-Equal $threw $true "Inspect exit 0 com $($case.Name) não pode ser aceito como rede verificada"
}

Write-Output 'PASS docker-cleanup-verification (18 cenários)'
