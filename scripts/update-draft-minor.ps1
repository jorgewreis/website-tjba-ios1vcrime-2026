param(
  [Parameter(Mandatory=$true)][string]$CommitHash,
  [Parameter(Mandatory=$true)][string]$CommitMessage,
  [Parameter(Mandatory=$true)][ValidateSet("major","minor","patch")][string]$Bump,
  [Parameter(Mandatory=$true)][ValidateSet("nenhum","baixo","medio","médio","alto")][string]$Impact,
  [string]$RepoRoot = ".",
  [string]$VersionFile = "VERSION",
  [string]$DraftMinorPath = "docs/90-releases/_draft_minor.md",
  [string]$DraftMajorPath = "docs/90-releases/_draft_major.md",
  [string]$NotesDir = "docs/90-releases/notes"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# --- Funções Utilitárias ---

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
  return (Get-Date).ToString("yyyy-MM-dd")
}

function Invoke-Git {
    param([Parameter(Mandatory=$true)][string[]]$Args)
    if (-not (Get-Command git.exe -ErrorAction SilentlyContinue)) {
        Write-Warning "Git não está disponível no PATH"
        return ""
    }
    try {
        $output = & git.exe @Args 2>&1
        if ($LASTEXITCODE -eq 0) {
            return $output | Out-String
        } else {
            return ""
        }
    }
    catch {
        return ""
    }
}

function Get-NameStatus {
  param([string]$Hash)
  $raw = Invoke-Git @("show","--name-status","--format=",$Hash)
  if ([string]::IsNullOrWhiteSpace($raw)) { return @() }
  return $raw -split "`r?`n" | Where-Object { $_.Trim() -ne "" }
}

function Classify-ChangeType {
  param([string]$FirstLine)
  if ($null -eq $FirstLine) { $FirstLine = "" }
  $m = $FirstLine.Trim().ToLowerInvariant()

  if ($m -match '^\s*(feat|feature)') { return "Feature" }
  if ($m -match '^\s*(fix|bugfix)')   { return "Correção" }
  if ($m -match '^\s*(refactor)')     { return "Refatoração" }
  if ($m -match '^\s*(perf)')         { return "Performance" }
  if ($m -match '^\s*(security)')     { return "Segurança" }
  if ($m -match '^\s*(docs)')         { return "Documentação" }
  if ($m -match '^\s*(chore|ci)')     { return "Manutenção" }
  return "Geral"
}

function Summarize-ByType {
  param([string[]]$NameStatusLines)
  $buckets = [ordered]@{
    "App/Core"  = 0
    "Public"    = 0
    "Database"  = 0
    "Config"    = 0
    "Docs"      = 0
    "Outros"    = 0
  }
  foreach ($l in $NameStatusLines) {
    $parts = $l -split "`t"
    if ($parts.Count -lt 2) { continue }
    $path = $parts[-1]

    if ($path -match '^(app|bootstrap|src)/') { $buckets["App/Core"]++; continue }
    if ($path -match '^public/')              { $buckets["Public"]++; continue }
    if ($path -match '^(database|sql|bd)/')   { $buckets["Database"]++; continue }
    if ($path -match '^config/')              { $buckets["Config"]++; continue }
    if ($path -match '^docs/')                { $buckets["Docs"]++; continue }
    $buckets["Outros"]++
  }
  return $buckets
}

# --- Funções de Escrita nos Drafts ---

function Append-DraftMinorEntry {
    param(
        [string]$DraftMinorPath,
        [string]$ChangeType,
        [string]$Message,
        [string]$CommitHash
    )
    # Garante cabeçalho se arquivo for novo
    Ensure-FileTemplate -Path $DraftMinorPath -TemplateLines @(
        "### Histórico de Patches e Alterações",
        "",
        "Lista acumulada de alterações para a próxima versão MINOR.",
        ""
    )

    # Formato: 1. **[Tipo]** Mensagem (hash curto)
    # O Markdown renderiza "1." repetido como uma lista sequencial correta (1, 2, 3...)
    $shortHash = $CommitHash.Substring(0, [System.Math]::Min(7, $CommitHash.Length))
    $line = "1. **[$ChangeType]** $Message (Ref: `$${shortHash}`)"

    try {
        $stream = [System.IO.StreamWriter]::new($DraftMinorPath, $true, [System.Text.UTF8Encoding]::new($false))
        $stream.WriteLine($line)
        $stream.Close()
    }
    catch {
        Write-Warning "Erro ao escrever no draft minor: $($Error[0].Exception.Message)"
    }
}

function Append-DraftMajorSummary {
  param(
    [string]$DraftMajorPath,
    [string]$Version,
    [string]$Impact,
    [string]$Date,
    [string]$ChangeType,
    [hashtable]$Buckets
  )

  Ensure-FileTemplate -Path $DraftMajorPath -TemplateLines @(
    "### Evolução do Ciclo (Estatísticas)",
    "",
    "Resumo estatístico das versões MINOR para a próxima versão MAJOR.",
    ""
  )

  $lines = New-Object System.Collections.Generic.List[string]
  $lines.Add('')
  $lines.Add("#### $Version ($Date)")
  $lines.Add("- **Foco Principal**: $ChangeType")
  $lines.Add("- **Impacto**: $Impact")
  $lines.Add("- **Arquivos Afetados (Estatística):**")

  # Filtra apenas buckets que tiveram alteração para não poluir
  foreach ($k in $Buckets.Keys) {
    if ($Buckets[$k] -gt 0) {
        $lines.Add("    - **$k**: $($Buckets[$k]) arquivos")
    }
  }

  try {
    $stream = [System.IO.StreamWriter]::new($DraftMajorPath, $true, [System.Text.UTF8Encoding]::new($false))
    $stream.Write(($lines -join "`n"))
    $stream.Close()
  }
  catch {
    Write-Warning "Erro ao escrever no draft major: $($Error[0].Exception.Message)"
  }
}

function Reset-File {
  param([string]$Path, [string[]]$Header)
  try {
    $stream = [System.IO.StreamWriter]::new($Path, $false, [System.Text.UTF8Encoding]::new($false))
    $stream.Write(($Header -join "`n"))
    $stream.Close()
  }
  catch {
    Write-Warning "Erro ao resetar arquivo ${Path}: $($Error[0].Exception.Message)"
  }
}

function Write-ReleaseNotes {
  param([string]$NotesDir, [string]$Version, [string]$DraftPath, [string]$Title)
  Ensure-Dir $NotesDir
  $safe = $Version.Replace(" ", "").Replace("/", "-").Replace(":", "-")
  $outFile = Join-Path $NotesDir ($safe + ".md")

  $draftContent = ""
  if (Test-Path -LiteralPath $DraftPath) {
    $draftContent = Get-Content -Raw -LiteralPath $DraftPath -Encoding UTF8
  }

  $header = @(
    "# Release Notes - $Version",
    "",
    "> **$Title**",
    "> Gerado em: $(Now-Iso)",
    "",
    "---",
    ""
  ) -join "`n"

  try {
    $stream = [System.IO.StreamWriter]::new($outFile, $false, [System.Text.UTF8Encoding]::new($false))
    $stream.Write($header + $draftContent)
    $stream.Close()
    Write-Host "Release Notes gerado em: $outFile"
  }
  catch {
    Write-Warning "Erro ao salvar Release Notes: $($Error[0].Exception.Message)"
  }
}

# --- Fluxo Principal ---

Push-Location $RepoRoot

$version = Get-VersionText -Path $VersionFile
if ([string]::IsNullOrWhiteSpace($version)) { $version = "v0.0.0" }
$date = Now-Iso

if ($null -eq $CommitMessage) { $CommitMessage = "Sem mensagem" }
$firstLine = ($CommitMessage -split "`r?`n")[0]
$changeType = Classify-ChangeType -FirstLine $firstLine
$nameStatus = Get-NameStatus -Hash $CommitHash
$buckets = Summarize-ByType -NameStatusLines $nameStatus

# 1. PATCH: Apenas adiciona uma linha resumida no Draft Minor
if ($Bump -eq "patch") {
  Append-DraftMinorEntry `
    -DraftMinorPath $DraftMinorPath `
    -ChangeType $changeType `
    -Message $firstLine `
    -CommitHash $CommitHash

  Write-Host "Patch registrado no draft minor."
  Pop-Location
  exit 0
}

# 2. MINOR: Gera Notes com a lista de patches, e salva estatísticas no Draft Major
if ($Bump -eq "minor") {
  # Primeiro, adiciona o próprio commit de bump na lista (opcional, mas bom para registro)
  Append-DraftMinorEntry `
    -DraftMinorPath $DraftMinorPath `
    -ChangeType $changeType `
    -Message $firstLine `
    -CommitHash $CommitHash

  # Gera o Release Note final da versão MINOR (contendo a lista detalhada)
  Write-ReleaseNotes `
    -NotesDir $NotesDir `
    -Version $version `
    -DraftPath $DraftMinorPath `
    -Title "Atualização Minor (Funcionalidades e Correções)"

  # Reseta o Draft Minor para o próximo ciclo
  Reset-File -Path $DraftMinorPath -Header @(
    "### Histórico de Patches e Alterações",
    "",
    "Lista acumulada de alterações para a próxima versão MINOR.",
    ""
  )

  # Alimenta o Draft Major com as estatísticas desta versão
  Append-DraftMajorSummary `
    -DraftMajorPath $DraftMajorPath `
    -Version $version `
    -Impact $Impact `
    -Date $date `
    -ChangeType $changeType `
    -Buckets $buckets

  Write-Host "Versão Minor processada."
  Pop-Location
  exit 0
}

# 3. MAJOR: Gera Notes com estatísticas acumuladas, e reseta tudo
if ($Bump -eq "major") {
  # Adiciona a estatística deste commit final de Major
  Append-DraftMajorSummary `
    -DraftMajorPath $DraftMajorPath `
    -Version $version `
    -Impact $Impact `
    -Date $date `
    -ChangeType $changeType `
    -Buckets $buckets

  # Gera o Release Note final da versão MAJOR (contendo apenas estatísticas)
  Write-ReleaseNotes `
    -NotesDir $NotesDir `
    -Version $version `
    -DraftPath $DraftMajorPath `
    -Title "Versão Major (Mudanças Estruturais e Estatísticas)"

  # Reseta ambos os drafts
  Reset-File -Path $DraftMinorPath -Header @(
    "### Histórico de Patches e Alterações",
    "",
    "Lista acumulada de alterações para a próxima versão MINOR.",
    ""
  )
  Reset-File -Path $DraftMajorPath -Header @(
    "### Evolução do Ciclo (Estatísticas)",
    "",
    "Resumo estatístico das versões MINOR para a próxima versão MAJOR.",
    ""
  )

  Write-Host "Versão Major processada."
  Pop-Location
  exit 0
}

Pop-Location
