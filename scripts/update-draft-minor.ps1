param(
  [Parameter(Mandatory=$true)][string]$CommitHash,
  [Parameter(Mandatory=$true)][string]$CommitMessage,
  [Parameter(Mandatory=$true)][ValidateSet("major","minor","patch")][string]$Bump,
  [Parameter(Mandatory=$true)][ValidateSet("nenhum","baixo","medio","médio","alto")][string]$Impact,
  [string]$RepoRoot = ".",
  [string]$VersionFile = "VERSION",
  # Arquivos de buffer para armazenar dados crus antes da compilação
  [string]$BufferMinorPath = "docs/90-releases/_buffer_minor.txt", 
  [string]$DraftMajorPath = "docs/90-releases/_draft_major.md",
  [string]$NotesDir = "docs/90-releases"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# --- Mapeamento de Categorias (Para o visual da imagem) ---
$CategoryMap = @{
    "Feature"      = "New"
    "Correção"     = "Bugfixes"
    "Refatoração"  = "Improvements"
    "Performance"  = "Improvements"
    "Segurança"    = "Security"
    "Documentação" = "Documentation"
    "Manutenção"   = "Internal"
    "Geral"        = "Misc"
}

# --- Funções Utilitárias ---

function Ensure-Dir {
  param([string]$Path)
  if (!(Test-Path -LiteralPath $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Get-VersionText {
  param([string]$Path)
  if (!(Test-Path -LiteralPath $Path)) { return "0.0.0" }
  return (Get-Content -Raw -LiteralPath $Path -Encoding UTF8).Trim()
}

function Now-Iso {
  return (Get-Date).ToString("yyyy-MM-dd")
}

function Now-Human {
  # Formato da imagem: "February 24, 2021"
  return (Get-Date).ToString("MMMM dd, yyyy", [System.Globalization.CultureInfo]::InvariantCulture)
}

function Invoke-Git {
    param([Parameter(Mandatory=$true)][string[]]$Args)
    if (-not (Get-Command git.exe -ErrorAction SilentlyContinue)) {
        Write-Warning "Git não está disponível no PATH"
        return ""
    }
    try {
        $output = & git.exe @Args 2>&1
        if ($LASTEXITCODE -eq 0) { return $output | Out-String } else { return "" }
    }
    catch { return "" }
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
  $buckets = [ordered]@{ "App/Core"=0; "Public"=0; "Database"=0; "Config"=0; "Docs"=0; "Outros"=0 }
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

# --- Funções de Escrita ---

function Append-BufferEntry {
    param(
        [string]$BufferPath,
        [string]$ChangeType,
        [string]$Message,
        [string]$CommitHash
    )
    # Garante diretório
    $dir = Split-Path -Parent $BufferPath
    Ensure-Dir $dir

    # Salva em formato CSV-like: TYPE|MESSAGE|HASH
    # Remove pipes da mensagem para evitar quebra
    $safeMessage = $Message.Replace("|", "-")
    $line = "$ChangeType|$safeMessage|$CommitHash"

    try {
        $stream = [System.IO.StreamWriter]::new($BufferPath, $true, [System.Text.UTF8Encoding]::new($false))
        $stream.WriteLine($line)
        $stream.Close()
    }
    catch {
        Write-Warning "Erro ao escrever no buffer: $($Error[0].Exception.Message)"
    }
}

function Generate-StyledReleaseNote {
    param(
        [string]$NotesDir,
        [string]$Version,
        [string]$BufferPath,
        [string]$Title,
        [string]$BadgeType, # MINOR ou MAJOR
        [hashtable]$CategoryMap
    )
    Ensure-Dir $NotesDir

    # Nome do arquivo final
    $safeVersion = $Version.Replace(" ", "").Replace("/", "-")
    $outFile = Join-Path $NotesDir ("$safeVersion.md")

    # Lê o buffer
    $entries = @()
    if (Test-Path -LiteralPath $BufferPath) {
        $lines = Get-Content -LiteralPath $BufferPath -Encoding UTF8
        foreach ($l in $lines) {
            $parts = $l -split "\|"
            if ($parts.Count -ge 3) {
                $entries += [PSCustomObject]@{
                    Type = $parts[0]
                    Message = $parts[1]
                    Hash = $parts[2]
                }
            }
        }
    }

    # Início da construção do Markdown (Estilo da Imagem)
    $sb = [System.Text.StringBuilder]::new()

    # 1. Header com Badge e Data
    # Usando HTML span para facilitar estilização no frontend
    $dateStr = Now-Human
    $sb.AppendLine("<span class=""badge badge-$($BadgeType.ToLower())"">$BadgeType</span> <span class=""date"">$dateStr</span>")
    $sb.AppendLine("")

    # 2. Título Grande (Versão + Mensagem Principal)
    $sb.AppendLine("# $Version $Title")
    $sb.AppendLine("")

    # 3. Seções Categorizadas (New, Bugfixes, etc.)
    # Agrupa entradas pelo tipo mapeado
    $grouped = $entries | Group-Object { $CategoryMap[$_.Type] }

    # Ordem de exibição preferida
    $order = @("New", "Improvements", "Security", "Bugfixes", "Documentation", "Internal", "Misc")

    foreach ($catName in $order) {
        $group = $grouped | Where-Object { $_.Name -eq $catName }
        if ($group) {
            $sb.AppendLine("### $catName")
            foreach ($item in $group.Group) {
                # Formato preferido: - **Texto**
                $sb.AppendLine("- **$($item.Message)**")
            }
            $sb.AppendLine("")
        }
    }

    # Salva o arquivo
    try {
        $stream = [System.IO.StreamWriter]::new($outFile, $false, [System.Text.UTF8Encoding]::new($false))
        $stream.Write($sb.ToString())
        $stream.Close()
        Write-Host "Release Note gerado: $outFile"

        # Limpa o buffer após gerar
        Remove-Item -LiteralPath $BufferPath -Force -ErrorAction SilentlyContinue
    }
    catch {
        Write-Warning "Erro ao salvar Release Note: $($Error[0].Exception.Message)"
    }
}

# --- Fluxo Principal ---

Push-Location $RepoRoot
$version = Get-VersionText -Path $VersionFile
$date = Now-Iso

if ($null -eq $CommitMessage) { $CommitMessage = "Update" }
$firstLine = ($CommitMessage -split "`r?`n")[0]
$changeType = Classify-ChangeType -FirstLine $firstLine

# 1. PATCH: Apenas acumula no buffer (não gera arquivo final ainda)
if ($Bump -eq "patch") {
  Append-BufferEntry `
    -BufferPath $BufferMinorPath `
    -ChangeType $changeType `
    -Message $firstLine `
    -CommitHash $CommitHash

  Write-Host "Patch registrado no buffer."
  Pop-Location
  exit 0
}

# 2. MINOR: Compila o buffer em um arquivo Markdown bonito
if ($Bump -eq "minor") {
  # Adiciona o commit atual ao buffer antes de gerar
  Append-BufferEntry `
    -BufferPath $BufferMinorPath `
    -ChangeType $changeType `
    -Message $firstLine `
    -CommitHash $CommitHash

  # Gera o documento final
  Generate-StyledReleaseNote `
    -NotesDir $NotesDir `
    -Version $version `
    -BufferPath $BufferMinorPath `
    -Title $firstLine `
    -BadgeType "MINOR" `
    -CategoryMap $CategoryMap

  Write-Host "Versão Minor processada e documentada."
  Pop-Location
  exit 0
}

# 3. MAJOR: Gera documento, mas focado em estatísticas (mantendo lógica original + estilo novo)
if ($Bump -eq "major") {
  # Para Major, vamos criar um arquivo direto, pois a lógica original era baseada em estatísticas
  # Mas vamos manter o cabeçalho visual

  $nameStatus = Get-NameStatus -Hash $CommitHash
  $buckets = Summarize-ByType -NameStatusLines $nameStatus

  $safeVersion = $version.Replace(" ", "").Replace("/", "-")
  $outFile = Join-Path $NotesDir ("$safeVersion.md")

  $sb = [System.Text.StringBuilder]::new()
  $dateStr = Now-Human

  # Header Visual
  $sb.AppendLine("<span class=""badge badge-major"">MAJOR</span> <span class=""date"">$dateStr</span>")
  $sb.AppendLine("")
  $sb.AppendLine("# $version $firstLine")
  $sb.AppendLine("")

  # Seção de Estatísticas (Major Focus)
  $sb.AppendLine("### Structural Changes & Stats")
  $sb.AppendLine("- **Impact Level**: $Impact")
  $sb.AppendLine("- **Primary Focus**: $changeType")
  $sb.AppendLine("")
  $sb.AppendLine("#### Files Changed by Module")
  foreach ($k in $buckets.Keys) {
    if ($buckets[$k] -gt 0) {
        $sb.AppendLine("- **$k**: $($buckets[$k]) files")
    }
  }

  # Salva
  [System.IO.File]::WriteAllText($outFile, $sb.ToString(), [System.Text.UTF8Encoding]::new($false))

  # Limpa buffers antigos se existirem
  Remove-Item -LiteralPath $BufferMinorPath -Force -ErrorAction SilentlyContinue

  Write-Host "Versão Major processada."
  Pop-Location
  exit 0
}

Pop-Location
