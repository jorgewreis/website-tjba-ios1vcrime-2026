param(
  [Parameter(Mandatory=$true)][string]$CommitHash,
  [Parameter(Mandatory=$true)][string]$CommitMessage,
  [Parameter(Mandatory=$true)][ValidateSet("major","minor","patch","patch2000","patch8000")][string]$Bump,
  [Parameter(Mandatory=$true)][ValidateSet("nenhum","baixo","medio","médio","alto")][string]$Impact,
  [string]$RepoRoot = ".",
  [string]$VersionFile = "VERSION",
  [string]$DraftMinorPath = "docs/90-releases/_draft_minor.md",
  [string]$DraftMajorPath = "docs/90-releases/_draft_major.md",
  [string]$NotesDir = "docs/90-releases/notes"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Ensure-FileTemplate {
  param([string]$Path, [string[]]$TemplateLines)
  $dir = Split-Path -Parent $Path
  if ($dir -and !(Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
  if (!(Test-Path -LiteralPath $Path)) {
    Set-Content -LiteralPath $Path -Value ($TemplateLines -join "`n") -Encoding UTF8
  }
}

function Ensure-Dir {
  param([string]$Path)
  if (!(Test-Path -LiteralPath $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Get-VersionText {
  param([string]$Path)
  if (!(Test-Path -LiteralPath $Path)) { return "" }
  return (Get-Content -Raw -LiteralPath $Path -Encoding UTF8).Trim()
}

function Now-Iso {
  return (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
}

function Invoke-Git {
  param([Parameter(Mandatory=$true)][string[]]$Args)

  # chama o executável, não uma função
  $out = & git.exe @Args 2>$null
  if ($LASTEXITCODE -ne 0) { return "" }
  if ($null -eq $out) { return "" }
  return ($out | Out-String).TrimEnd()
}


function Get-NameStatus {
  param([string]$Hash)
  $raw = Invoke-Git @("show","--name-status","--format=",$Hash)
  if ([string]::IsNullOrWhiteSpace($raw)) { return @() }
  return $raw -split "`r?`n" | Where-Object { $_.Trim() -ne "" }
}

function Get-Stats {
  param([string]$Hash)
  return Invoke-Git @("show","--stat","--format=",$Hash)
}

function Classify-ChangeType {
  param([string]$FirstLine)

  if ($null -eq $FirstLine) { $FirstLine = "" }
  $m = $FirstLine.Trim().ToLowerInvariant()

  if ($m -match '^\s*(feat|feature)(\([^)]+\))?:') { return "feature" }
  if ($m -match '^\s*(fix)(\([^)]+\))?:') { return "bugfix" }
  if ($m -match '^\s*(refactor)(\([^)]+\))?:') { return "refactor" }
  if ($m -match '^\s*(perf)(\([^)]+\))?:') { return "performance" }
  if ($m -match '^\s*(security)(\([^)]+\))?:') { return "security" }
  if ($m -match '^\s*(docs)(\([^)]+\))?:') { return "docs" }
  if ($m -match '^\s*(chore|ci|build)(\([^)]+\))?:') { return "maintenance" }
  if ($m -match '^\s*(test)(\([^)]+\))?:') { return "tests" }

  return "change"
}

function Summarize-ByType {
  param([string[]]$NameStatusLines)

  $buckets = [ordered]@{
    "app"       = 0
    "public"    = 0
    "database"  = 0
    "config"    = 0
    "scripts"   = 0
    "docs"      = 0
    "other"     = 0
  }

  foreach ($l in $NameStatusLines) {
    $parts = $l -split "`t"
    if ($parts.Count -lt 2) { continue }
    $path = $parts[-1]

    if ($path -match '^(app|bootstrap)/') { $buckets["app"]++; continue }
    if ($path -match '^public/')          { $buckets["public"]++; continue }
    if ($path -match '^(database|sql)/')  { $buckets["database"]++; continue }
    if ($path -match '^config/')          { $buckets["config"]++; continue }
    if ($path -match '^scripts/')         { $buckets["scripts"]++; continue }
    if ($path -match '^docs/')            { $buckets["docs"]++; continue }

    $buckets["other"]++
  }

  return $buckets
}

function Append-DraftMinorEntry {
  param(
    [string]$DraftMinorPath,
    [string]$Version,
    [string]$Bump,
    [string]$Impact,
    [string]$CommitHash,
    [string]$Date,
    [string]$ChangeType,
    [string]$Message,
    [string[]]$NameStatusLines,
    [string]$StatsText
  )

  Ensure-FileTemplate -Path $DraftMinorPath -TemplateLines @(
    "# Draft Minor (acumulado para a próxima versão MINOR)",
    "",
    "> Gerado automaticamente.",
    "> Contém entradas detalhadas por commit (hash, data, tipo, impacto, arquivos).",
    "",
    "---",
    ""
  )

  $block = New-Object System.Collections.Generic.List[string]
  $block.Add('## ' + $Version)
  $block.Add('')
  $block.Add('- **bump**: ' + $Bump)
  $block.Add('- **impacto**: ' + $Impact)
  $block.Add('- **tipo**: ' + $ChangeType)
  $block.Add('- **hash**: `' + $CommitHash + '`')
  $block.Add('- **data**: ' + $Date)
  $block.Add('- **mensagem**: ' + $Message)
  $block.Add('')
  $block.Add('### Arquivos (name-status)')

  foreach ($l in $NameStatusLines) { $block.Add('- ' + $l) }

  $block.Add('')
  $block.Add('### Estatísticas')

  if ([string]::IsNullOrWhiteSpace($StatsText)) {
    $block.Add('- (sem estatísticas)')
  } else {
    foreach ($s in ($StatsText -split "`r?`n")) {
      if ($s.Trim() -ne '') { $block.Add('- ' + $s) }
    }
  }

  $block.Add('')
  $block.Add('---')
  $block.Add('')

  Add-Content -LiteralPath $DraftMinorPath -Value ($block -join "`n") -Encoding UTF8
}

function Append-DraftMajorSummary {
  param(
    [string]$DraftMajorPath,
    [string]$Version,
    [string]$Impact,
    [string]$CommitHash,
    [string]$Date,
    [string]$ChangeType,
    [hashtable]$Buckets
  )

  Ensure-FileTemplate -Path $DraftMajorPath -TemplateLines @(
    "# Draft Major (acumulado para a próxima versão MAJOR)",
    "",
    "> Gerado automaticamente.",
    "> Contém resumos compactos (agrupados por tipo) e lista de hashes.",
    "",
    "---",
    ""
  )

  $lines = New-Object System.Collections.Generic.List[string]
  $lines.Add('## ' + $Version)
  $lines.Add('')
  $lines.Add('- **impacto**: ' + $Impact)
  $lines.Add('- **tipo**: ' + $ChangeType)
  $lines.Add('- **hash**: `' + $CommitHash + '`')
  $lines.Add('- **data**: ' + $Date)
  $lines.Add('')
  $lines.Add('### Resumo por área')

  foreach ($k in $Buckets.Keys) {
    $lines.Add('- **' + $k + '**: ' + $Buckets[$k])
  }

  $lines.Add('')
  $lines.Add('---')
  $lines.Add('')

  Add-Content -LiteralPath $DraftMajorPath -Value ($lines -join "`n") -Encoding UTF8
}

function Reset-DraftMinor {
  param([string]$Path)
  Set-Content -LiteralPath $Path -Value @(
    "# Draft Minor (acumulado para a próxima versão MINOR)",
    "",
    "> Gerado automaticamente.",
    "> Contém entradas detalhadas por commit (hash, data, tipo, impacto, arquivos).",
    "",
    "---",
    ""
  ) -Encoding UTF8
}

function Reset-DraftMajor {
  param([string]$Path)
  Set-Content -LiteralPath $Path -Value @(
    "# Draft Major (acumulado para a próxima versão MAJOR)",
    "",
    "> Gerado automaticamente.",
    "> Contém resumos compactos (agrupados por tipo) e lista de hashes.",
    "",
    "---",
    ""
  ) -Encoding UTF8
}

function Write-ReleaseNotesFromDraft {
  param([string]$NotesDir, [string]$Version, [string]$DraftPath)

  Ensure-Dir $NotesDir
  $safe = $Version.Replace(" ", "").Replace("/", "-").Replace(":", "-")
  $outFile = Join-Path $NotesDir ($safe + ".md")

  $draft = ""
  if (Test-Path -LiteralPath $DraftPath) {
    $draft = Get-Content -Raw -LiteralPath $DraftPath -Encoding UTF8
  }

  $header = @(
    "# Release Notes - " + $Version,
    "",
    "> Gerado automaticamente a partir do draft.",
    ""
  ) -join "`n"

  Set-Content -LiteralPath $outFile -Value ($header + $draft) -Encoding UTF8
}

# --- main ---
Push-Location $RepoRoot

$version = Get-VersionText -Path $VersionFile
if ([string]::IsNullOrWhiteSpace($version)) { $version = "(sem versão)" }

$date = Now-Iso

if ($null -eq $CommitMessage) { $CommitMessage = "" }
$firstLine = ($CommitMessage -split "`r?`n")[0]
$changeType = Classify-ChangeType -FirstLine $firstLine

$nameStatus = Get-NameStatus -Hash $CommitHash
$stats = Get-Stats -Hash $CommitHash
$buckets = Summarize-ByType -NameStatusLines $nameStatus

# PATCH: registra detalhado no _draft_minor
if ($Bump -in @("patch","patch2000","patch8000")) {
  Append-DraftMinorEntry `
    -DraftMinorPath $DraftMinorPath `
    -Version $version `
    -Bump $Bump `
    -Impact $Impact `
    -CommitHash $CommitHash `
    -Date $date `
    -ChangeType $changeType `
    -Message $firstLine `
    -NameStatusLines $nameStatus `
    -StatsText $stats

  Pop-Location
  exit 0
}

# MINOR: inclui o commit atual no draft minor, gera notes, reseta draft minor,
# e também alimenta draft major de forma compacta
if ($Bump -eq "minor") {

  Append-DraftMinorEntry `
    -DraftMinorPath $DraftMinorPath `
    -Version $version `
    -Bump $Bump `
    -Impact $Impact `
    -CommitHash $CommitHash `
    -Date $date `
    -ChangeType $changeType `
    -Message $firstLine `
    -NameStatusLines $nameStatus `
    -StatsText $stats

  Write-ReleaseNotesFromDraft -NotesDir $NotesDir -Version $version -DraftPath $DraftMinorPath
  Reset-DraftMinor -Path $DraftMinorPath

  Append-DraftMajorSummary `
    -DraftMajorPath $DraftMajorPath `
    -Version $version `
    -Impact $Impact `
    -CommitHash $CommitHash `
    -Date $date `
    -ChangeType $changeType `
    -Buckets $buckets

  Pop-Location
  exit 0
}

# MAJOR: atualiza draft major com o commit atual e gera notes a partir dele.
# Depois reseta ambos.
if ($Bump -eq "major") {

  Append-DraftMajorSummary `
    -DraftMajorPath $DraftMajorPath `
    -Version $version `
    -Impact $Impact `
    -CommitHash $CommitHash `
    -Date $date `
    -ChangeType $changeType `
    -Buckets $buckets

  Write-ReleaseNotesFromDraft -NotesDir $NotesDir -Version $version -DraftPath $DraftMajorPath

  Reset-DraftMinor -Path $DraftMinorPath
  Reset-DraftMajor -Path $DraftMajorPath

  Pop-Location
  exit 0
}

Pop-Location
