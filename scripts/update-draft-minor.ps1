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
    try {
      $stream = [System.IO.StreamWriter]::new($Path, $false, [System.Text.UTF8Encoding]::new($false))
      $stream.Write(($TemplateLines -join "`n"))
      $stream.Close()
    }
    catch {
      Write-Warning "Erro ao escrever template no arquivo ${Path}: $($Error[0].Exception.Message)"
    }
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
    # Verifica se git.exe está disponível
    if (-not (Get-Command git.exe -ErrorAction SilentlyContinue)) {
        Write-Warning "Git não está disponível no PATH"
        return ""
    }
    try {
        # Redireciona stderr para stdout para capturar todos os outputs
        $output = & git.exe @Args 2>&1
        # Verifica se o comando foi bem-sucedido
        if ($LASTEXITCODE -eq 0) {
            return $output | Out-String
        } else {
            Write-Warning "Comando Git falhou com código $LASTEXITCODE"
            return ""
        }
    }
    catch {
        Write-Warning "Erro ao executar Git: $($Error[0].Exception.Message)"
        return ""
    }
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
  if ($m -match '^\s*(feat|feature)(|$[^)]+$|)?:') { return "feature" }
  if ($m -match '^\s*(fix)(|$[^)]+$|)?:') { return "bugfix" }
  if ($m -match '^\s*(refactor)(|$[^)]+$|)?:') { return "refactor" }
  if ($m -match '^\s*(perf)(|$[^)]+$|)?:') { return "performance" }
  if ($m -match '^\s*(security)(|$[^)]+$|)?:') { return "security" }
  if ($m -match '^\s*(docs)(|$[^)]+$|)?:') { return "docs" }
  if ($m -match '^\s*(chore|ci|build)(|$[^)]+$|)?:') { return "maintenance" }
  if ($m -match '^\s*(test)(|$[^)]+$|)?:') { return "tests" }
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
        "# Draft Minor (acumulado para a proxima versao MINOR)",
        "",
        "> Gerado automaticamente.",
        "> Contem entradas detalhadas por commit (hash, data, tipo, impacto, arquivos).",
        "",
        "---",
        ""
    )
    $block = New-Object System.Collections.Generic.List[string]
    $block.Add('')
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
    $block.Add('')
    foreach ($l in $NameStatusLines) { $block.Add('- ' + $l) }
    $block.Add('')
    $block.Add('### Estatisticas')
    $block.Add('')
    if ([string]::IsNullOrWhiteSpace($StatsText)) {
        $block.Add('- (sem estatisticas)')
    } else {
        foreach ($s in ($StatsText -split "`r?`n")) {
            if ($s.Trim() -ne '') { $block.Add('- ' + $s) }
        }
    }
    $block.Add('')
    $block.Add('---')
    # Usar StreamWriter em vez de Add-Content
    try {
        $stream = [System.IO.StreamWriter]::new($DraftMinorPath, $true, [System.Text.UTF8Encoding]::new($false))
        $stream.Write(($block -join "`n"))
        $stream.Close()
    }
    catch {
        Write-Warning "Erro ao escrever no arquivo ${DraftMinorPath}: $($Error[0].Exception.Message)"
    }
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
    "# Draft Major (acumulado para a proxima versao MAJOR)",
    "",
    "> Gerado automaticamente.",
    "> Contem resumos compactos (agrupados por tipo) e lista de hashes.",
    "",
    "---",
    ""
  )
  $lines = New-Object System.Collections.Generic.List[string]
  $lines.Add('')
  $lines.Add('## ' + $Version)
  $lines.Add('')
  $lines.Add('- **impacto**: ' + $Impact)
  $lines.Add('- **tipo**: ' + $ChangeType)
  $lines.Add('- **hash**: `' + $CommitHash + '`')
  $lines.Add('- **data**: ' + $Date)
  $lines.Add('')
  $lines.Add('### Resumo por area')
  $lines.Add('')
  foreach ($k in $Buckets.Keys) {
    $lines.Add('- **' + $k + '**: ' + $Buckets[$k])
  }
  $lines.Add('')
  $lines.Add('---')

  # Usar StreamWriter em vez de Add-Content
  try {
    $stream = [System.IO.StreamWriter]::new($DraftMajorPath, $true, [System.Text.UTF8Encoding]::new($false))
    $stream.Write(($lines -join "`n"))
    $stream.Close()
  }
  catch {
    Write-Warning "Erro ao escrever no arquivo ${DraftMajorPath}: $($Error[0].Exception.Message)"
  }
}

function Reset-DraftMinor {
  param([string]$Path)
  try {
    $content = @(
      "# Draft Minor (acumulado para a proxima versao MINOR)",
      "",
      "> Gerado automaticamente.",
      "> Contem entradas detalhadas por commit (hash, data, tipo, impacto, arquivos).",
      "",
      "---",
      ""
    )
    $stream = [System.IO.StreamWriter]::new($Path, $false, [System.Text.UTF8Encoding]::new($false))
    $stream.Write(($content -join "`n"))
    $stream.Close()
  }
  catch {
    Write-Warning "Erro ao resetar arquivo ${Path}: $($Error[0].Exception.Message)"
  }
}

function Reset-DraftMajor {
  param([string]$Path)
  try {
    $content = @(
      "# Draft Major (acumulado para a proxima versao MAJOR)",
      "",
      "> Gerado automaticamente.",
      "> Contem resumos compactos (agrupados por tipo) e lista de hashes.",
      "",
      "---",
      ""
    )
    $stream = [System.IO.StreamWriter]::new($Path, $false, [System.Text.UTF8Encoding]::new($false))
    $stream.Write(($content -join "`n"))
    $stream.Close()
  }
  catch {
    Write-Warning "Erro ao resetar arquivo ${Path}: $($Error[0].Exception.Message)"
  }
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
    "> Gerado automaticamente.",
    ""
  ) -join "`n"

  try {
    $stream = [System.IO.StreamWriter]::new($outFile, $false, [System.Text.UTF8Encoding]::new($false))
    $stream.Write($header + $draft)
    $stream.Close()
  }
  catch {
    Write-Warning "Erro ao escrever notas de release em ${outFile}: $($Error[0].Exception.Message)"
  }
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
