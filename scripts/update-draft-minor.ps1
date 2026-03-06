<#
.SYNOPSIS
    Gera Release Notes estilizados automaticamente para cada versão.

.DESCRIPTION
    Este script automatiza a criação de documentação de release baseado em:
    - PATCH: Acumula commits em buffer (_buffer_minor.txt)
    - MINOR: Compila buffer em documento Markdown categorizado e formatado
    - MAJOR: Gera documento com estatísticas estruturais detalhadas

    Integra com sistema de versionamento automático via hooks Git.

.PARAMETER CommitHash
    Hash curto do commit (ex: a1b2c3d)

.PARAMETER CommitMessage
    Mensagem completa do commit (título + corpo)

.PARAMETER Bump
    Tipo de bump: major, minor ou patch

.PARAMETER Impact
    Nível de impacto: nenhum, baixo, medio, alto

.PARAMETER RepoRoot
    Raiz do repositório (padrão: diretório atual)

.EXAMPLE
    .\update-draft-minor.ps1 -CommitHash "a1b2c3d" -CommitMessage "feat: nova funcionalidade" -Bump "minor" -Impact "baixo"
#>

param(
    [Parameter(Mandatory=$true)]
    [ValidateNotNullOrEmpty()]
    [string]$CommitHash,

    [Parameter(Mandatory=$false)]
    [string]$CommitMessage = "",

    [Parameter(Mandatory=$false)]
    [string]$CommitMessagePath = "",

    [Parameter(Mandatory=$true)]
    [ValidateSet("major", "minor", "patch")]
    [string]$Bump,

    [Parameter(Mandatory=$true)]
    [ValidateSet("nenhum", "baixo", "medio", "alto")]
    [string]$Impact,

    [Parameter(Mandatory=$false)]
    [string]$RepoRoot = ".",

    [Parameter(Mandatory=$false)]
    [string]$VersionFile = "VERSION",

    [Parameter(Mandatory=$false)]
    [string]$BufferMinorPath = "docs/90-releases/_buffer_minor.txt",

    [Parameter(Mandatory=$false)]
    [string]$NotesDir = "docs/90-releases/notes",

    [Parameter(Mandatory=$false)]
    [string]$DraftMinorPath = "docs/90-releases/_draft_minor.md"
)

# =============================================================================
# CONFIGURAÇÕES
# =============================================================================

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# Timeout para operações de arquivo (segundos)
$FILE_LOCK_TIMEOUT = 10

# =============================================================================
# FUNÇÕES DE LOG
# =============================================================================

function Write-Info {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host "[WARN] $Message" -ForegroundColor Yellow
}

function Write-Err {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

function Write-Debug-Custom {
    param([string]$Message)
    if ($VerbosePreference -eq 'Continue') {
        Write-Host "[DEBUG] $Message" -ForegroundColor Cyan
    }
}

# =============================================================================
# FUNÇÕES UTILITÁRIAS
# =============================================================================

function Ensure-Directory {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        Write-Warn "Caminho de diretório vazio"
        return
    }

    try {
        if (!(Test-Path -LiteralPath $Path -PathType Container)) {
            Write-Debug-Custom "Criando diretório: $Path"
            New-Item -ItemType Directory -Force -Path $Path | Out-Null
            Write-Info "Diretório criado: $Path"
        }
    }
    catch {
        Write-Err "Erro ao criar diretório ${Path}: $_"
        throw
    }
}

function Get-VersionText {
    param([string]$Path)

    Write-Debug-Custom "Lendo versão de: $Path"

    if (!(Test-Path -LiteralPath $Path)) {
        Write-Warn "Arquivo VERSION não encontrado em: $Path"
        Write-Warn "Usando versão padrão: V. 1.0.0"
        return "V. 1.0.0"
    }

    try {
        $content = Get-Content -Raw -LiteralPath $Path -Encoding UTF8 -ErrorAction Stop
        $version = $content.Trim()

        if ([string]::IsNullOrWhiteSpace($version)) {
            Write-Warn "Arquivo VERSION vazio, usando padrão: V. 1.0.0"
            return "V. 1.0.0"
        }

        Write-Debug-Custom "Versão lida: $version"
        return $version
    }
    catch {
        Write-Warn "Erro ao ler VERSION: $_"
        return "V. 1.0.0"
    }
}

function Get-IsoDate {
    return (Get-Date).ToString("yyyy-MM-dd")
}

function Get-HumanDate {
    $date = Get-Date
    $culture = [System.Globalization.CultureInfo]::CreateSpecificCulture("pt-BR")
    return $date.ToString("dd 'de' MMMM 'de' yyyy", $culture)
}

function Get-Timestamp {
    return (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
}

# =============================================================================
# CLASSIFICAÇÃO DE COMMITS
# =============================================================================

function Get-ChangeType {
    param([string]$FirstLine)

    if ([string]::IsNullOrWhiteSpace($FirstLine)) {
        return "Geral"
    }

    $normalized = $FirstLine.Trim().ToLowerInvariant()

    # Conventional commits (escopo opcional: tipo(escopo):)
    if ($normalized -match '^\s*(feat|feature)(\([^)]+\))?(!)?:') { return "Feature" }
    if ($normalized -match '^\s*(fix|bugfix)(\([^)]+\))?(!)?:') { return "Correção" }
    if ($normalized -match '^\s*refactor(\([^)]+\))?(!)?:') { return "Refatoração" }
    if ($normalized -match '^\s*perf(\([^)]+\))?(!)?:') { return "Performance" }
    if ($normalized -match '^\s*(security|sec)(\([^)]+\))?(!)?:') { return "Segurança" }
    if ($normalized -match '^\s*docs(\([^)]+\))?(!)?:') { return "Documentação" }
    if ($normalized -match '^\s*test(\([^)]+\))?(!)?:') { return "Testes" }
    if ($normalized -match '^\s*build(\([^)]+\))?(!)?:') { return "Build" }
    if ($normalized -match '^\s*(ci|cd)(\([^)]+\))?(!)?:') { return "CI/CD" }
    if ($normalized -match '^\s*chore(\([^)]+\))?(!)?:') { return "Manutenção" }

    return "Geral"
}

# =============================================================================
# GERENCIAMENTO DE BUFFER
# =============================================================================

function Add-BufferEntry {
    param(
        [string]$BufferPath,
        [string]$ChangeType,
        [string]$Message,
        [string]$CommitHash,
        [string]$Author = ""
    )

    Write-Debug-Custom "Adicionando entrada ao buffer: $BufferPath"

    # Garante que diretório existe
    $directory = Split-Path -Path $BufferPath -Parent
    Ensure-Directory -Path $directory

    # Remove pipes da mensagem
    $safeMessage = $Message.Replace("|", "¦")

    # Formato: TYPE|MESSAGE|HASH|AUTHOR|TIMESTAMP
    $timestamp = Get-Timestamp
    $line = "$ChangeType|$safeMessage|$CommitHash|$Author|$timestamp"

    try {
        # Escreve no arquivo com encoding UTF8 sem BOM
        Add-Content -LiteralPath $BufferPath -Value $line -Encoding UTF8 -ErrorAction Stop
        Write-Info "Entrada adicionada ao buffer: $ChangeType"
    }
    catch {
        Write-Err "Erro ao escrever no buffer: $_"
        throw
    }
}

function Get-BufferEntries {
    param([string]$BufferPath)

    Write-Debug-Custom "Lendo buffer: $BufferPath"

    if (!(Test-Path -LiteralPath $BufferPath)) {
        Write-Debug-Custom "Buffer não existe, retornando array vazio"
        return @()
    }

    try {
        $lines = Get-Content -LiteralPath $BufferPath -Encoding UTF8 -ErrorAction Stop
        $entries = @()

        foreach ($line in $lines) {
            if ([string]::IsNullOrWhiteSpace($line)) {
                continue
            }

            # Parse: TYPE|MESSAGE|HASH|AUTHOR|TIMESTAMP
            $parts = @($line -split "\|")
            if ($parts.Count -ge 3) {
                $entry = [PSCustomObject]@{
                    Type      = $parts[0].Trim()
                    Message   = $parts[1].Trim()
                    Hash      = $parts[2].Trim()
                    Author    = if ($parts.Count -ge 4) { $parts[3].Trim() } else { "" }
                    Timestamp = if ($parts.Count -ge 5) { $parts[4].Trim() } else { "" }
                }
                $entries += $entry
            }
        }

        Write-Debug-Custom "Buffer lido: $($entries.Count) entradas"
        return $entries
    }
    catch {
        Write-Err "Erro ao ler buffer: $_"
        return @()
    }
}

# =============================================================================
# ATUALIZAÇÃO DO ARQUIVO _draft_minor.md (Entradas Pendentes)
# =============================================================================

function Update-DraftMinorMarkdown {
    param(
        [string]$BufferPath,
        [string]$DraftPath
    )

    if ([string]::IsNullOrWhiteSpace($DraftPath)) { return }

    $directory = Split-Path -Path $DraftPath -Parent
    Ensure-Directory -Path $directory

    $entries = @(Get-BufferEntries -BufferPath $BufferPath)

    $content = @()
    $content += "# Draft - Release Notes (Minor)"
    $content += ""
    $content += "Este arquivo acumula mudanças incrementais para a próxima release MINOR."
    $content += ""
    $content += "## Entradas Pendentes"
    $content += ""

    if ($entries.Count -eq 0) {
        $content += "_Nenhuma entrada pendente._"
    }
    else {
        foreach ($item in $entries) {
            $line = "- **$($item.Message)**"
            if (![string]::IsNullOrWhiteSpace($item.Hash)) {
                $line += " ``[$($item.Hash)]``"
            }
            $content += $line
        }
    }

    $content += ""

    try {
        $content | Out-File -LiteralPath $DraftPath -Encoding UTF8 -Force -ErrorAction Stop
        Write-Debug-Custom "Draft atualizado: $DraftPath ($($entries.Count) entradas)"
    }
    catch {
        Write-Warn "Não foi possível atualizar o draft: $_"
    }
}

# =============================================================================
# GERAÇÃO DE RELEASE NOTES
# =============================================================================

# =============================================================================
# GERAÇÃO DE RELEASE NOTES
# =============================================================================

function New-SimpleReleaseNote {
    param(
        [string]$OutputPath,
        [string]$Version,
        [string]$Title,
        [string]$BadgeType,
        [PSCustomObject[]]$Entries,
        [string]$ImpactLevel = ""
    )

    Write-Debug-Custom "Gerando release note: $OutputPath"

    # Garante que diretório existe
    $directory = Split-Path -Path $OutputPath -Parent
    Ensure-Directory -Path $directory

    # Inicia construção do documento
    $content = @()

    # Cabeçalho
    $badgeColor = if ($BadgeType -eq "MAJOR") { "critical" } else { "blue" }
    $dateStr = Get-HumanDate

    # ✅ CORREÇÃO: Usa string literal para evitar expansão de variáveis
    $badgeUrl = "https://img.shields.io/badge/$BadgeType-$badgeColor" + "?style=for-the-badge"
    $content += "![Badge]($badgeUrl)"
    $content += ""
    $content += "**Data de Lançamento:** $dateStr"
    $content += ""

    # Título
    $content += "# $Version"
    $content += ""

    if (![string]::IsNullOrWhiteSpace($Title)) {
        $content += "> $Title"
        $content += ""
    }

    # Impacto
    if (![string]::IsNullOrWhiteSpace($ImpactLevel)) {
        $impactEmoji = switch ($ImpactLevel) {
            "alto"   { "🔴" }
            "medio"  { "🟡" }
            "baixo"  { "🟢" }
            default  { "⚪" }
        }
        $content += "## Nível de Impacto"
        $content += ""
        $content += "$impactEmoji **$($ImpactLevel.ToUpper())**"
        $content += ""
    }

    # Entradas (garante array para evitar falha de .Count em objeto único ou $null)
    $entriesList = @($Entries)
    if ($entriesList.Count -gt 0) {
        $content += "## Alterações"
        $content += ""

        foreach ($item in $entriesList) {
            $line = "- **$($item.Message)**"
            if (![string]::IsNullOrWhiteSpace($item.Hash)) {
                $line += " ``[$($item.Hash)]``"
            }
            $content += $line
        }
        $content += ""
    }

    # Rodapé
    $content += "---"
    $content += ""
    $content += "*Documento gerado automaticamente pelo sistema de versionamento.*"

    # Salva arquivo
    try {
        $content | Out-File -LiteralPath $OutputPath -Encoding UTF8 -Force -ErrorAction Stop
        Write-Info "Release note gerado: $OutputPath"
        return $true
    }
    catch {
        Write-Err "Erro ao salvar release note: $_"
        return $false
    }
}


# =============================================================================
# SCRIPT PRINCIPAL
# =============================================================================

try {
    if ([string]::IsNullOrWhiteSpace($CommitMessage) -and [string]::IsNullOrWhiteSpace($CommitMessagePath) -eq $false -and (Test-Path -LiteralPath $CommitMessagePath -PathType Leaf)) {
        try {
            $CommitMessage = Get-Content -Raw -LiteralPath $CommitMessagePath -Encoding UTF8 -ErrorAction Stop
            $CommitMessage = $CommitMessage.TrimEnd()
        } catch {
            Write-Warn "Não foi possível ler CommitMessagePath: $_"
        }
    }
    if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
        $CommitMessage = "chore: atualização automática"
    }

    Write-Info "========================================="
    Write-Info "update-draft-minor.ps1 iniciado"
    Write-Info "========================================="
    Write-Info "Parâmetros:"
    Write-Info "  CommitHash: $CommitHash"
    Write-Info "  Bump: $Bump"
    Write-Info "  Impact: $Impact"
    Write-Info "  RepoRoot: $RepoRoot"
    Write-Info "========================================="

    # Muda para diretório raiz
    Push-Location $RepoRoot

    # Lê versão
    $versionPath = Join-Path $RepoRoot $VersionFile
    $version = Get-VersionText -Path $versionPath
    Write-Info "Versão atual: $version"

    # Normaliza Impact
    $Impact = $Impact.ToLowerInvariant().Replace("é", "e")
    Write-Debug-Custom "Impact normalizado: $Impact"

    # Extrai primeira linha
    $firstLine = ($CommitMessage -split "`r?`n")[0].Trim()
    Write-Info "Título do commit: $firstLine"

    # Classifica tipo
    $changeType = Get-ChangeType -FirstLine $firstLine
    Write-Info "Tipo de mudança: $changeType"

    # Define caminhos absolutos
    $bufferPath = Join-Path $RepoRoot $BufferMinorPath
    $notesPath = Join-Path $RepoRoot $NotesDir
    $draftPath = Join-Path $RepoRoot $DraftMinorPath

    Write-Debug-Custom "Buffer path: $bufferPath"
    Write-Debug-Custom "Notes path: $notesPath"
    Write-Debug-Custom "Draft path: $draftPath"

    # Processa baseado no bump
    switch ($Bump) {
        "patch" {
            Write-Info "Processando PATCH - acumulando no buffer..."

            Add-BufferEntry `
                -BufferPath $bufferPath `
                -ChangeType $changeType `
                -Message $firstLine `
                -CommitHash $CommitHash `
                -Author "Unknown"

            Update-DraftMinorMarkdown -BufferPath $bufferPath -DraftPath $draftPath

            Write-Host ""
            Write-Host "✅ PATCH REGISTRADO NO BUFFER" -ForegroundColor Green
            Write-Host "Tipo:    $changeType" -ForegroundColor White
            Write-Host "Commit:  $CommitHash" -ForegroundColor Cyan
            Write-Host ""

            Pop-Location
            exit 0
        }

        "minor" {
            Write-Info "Processando MINOR - compilando buffer..."

            # Adiciona commit atual
            Add-BufferEntry `
                -BufferPath $bufferPath `
                -ChangeType $changeType `
                -Message $firstLine `
                -CommitHash $CommitHash `
                -Author "Unknown"

            # Lê buffer (garante array para .Count)
            $entries = @(Get-BufferEntries -BufferPath $bufferPath)

            if ($entries.Count -eq 0) {
                Write-Warn "Buffer vazio - gerando documento apenas com commit atual"
                $entries = @(
                    [PSCustomObject]@{
                        Type      = $changeType
                        Message   = $firstLine
                        Hash      = $CommitHash
                        Author    = "Unknown"
                        Timestamp = Get-Timestamp
                    }
                )
            }

            Write-Info "Total de entradas: $($entries.Count)"

            # Define arquivo de saída
            $safeVersion = $version -replace '\s+', '' -replace '/', '-'
            $outputFile = Join-Path $notesPath "release-$safeVersion.md"

            Write-Info "Arquivo de saída: $outputFile"

            # Gera documento
            $success = New-SimpleReleaseNote `
                -OutputPath $outputFile `
                -Version $version `
                -Title $firstLine `
                -BadgeType "MINOR" `
                -Entries $entries `
                -ImpactLevel $Impact

            # Limpa buffer se sucesso e atualiza draft para "nenhuma entrada"
            if ($success -and (Test-Path -LiteralPath $outputFile)) {
                Write-Info "Limpando buffer..."
                Remove-Item -LiteralPath $bufferPath -Force -ErrorAction SilentlyContinue
                Update-DraftMinorMarkdown -BufferPath $bufferPath -DraftPath $draftPath

                Write-Host ""
                Write-Host "✅ VERSÃO MINOR PROCESSADA" -ForegroundColor Green
                Write-Host "Versão:   $version" -ForegroundColor Cyan
                Write-Host "Documento: $outputFile" -ForegroundColor White
                Write-Host "Entradas: $($entries.Count) commits" -ForegroundColor White
                Write-Host ""
            }
            else {
                Write-Warn "Documento não foi criado - mantendo buffer"
            }

            Pop-Location
            exit 0
        }

        "major" {
            Write-Info "Processando MAJOR - gerando documento..."

            # Cria entrada
            $entries = @(
                [PSCustomObject]@{
                    Type      = $changeType
                    Message   = $firstLine
                    Hash      = $CommitHash
                    Author    = "Unknown"
                    Timestamp = Get-Timestamp
                }
            )

            # Define arquivo de saída
            $safeVersion = $version -replace '\s+', '' -replace '/', '-'
            $outputFile = Join-Path $notesPath "release-$safeVersion.md"

            Write-Info "Arquivo de saída: $outputFile"

            # Gera documento
            $success = New-SimpleReleaseNote `
                -OutputPath $outputFile `
                -Version $version `
                -Title $firstLine `
                -BadgeType "MAJOR" `
                -Entries $entries `
                -ImpactLevel $Impact

            # Limpa buffers
            if ($success -and (Test-Path -LiteralPath $outputFile)) {
                Write-Info "Limpando buffers..."
                Remove-Item -LiteralPath $bufferPath -Force -ErrorAction SilentlyContinue

                Write-Host ""
                Write-Host "✅ VERSÃO MAJOR PROCESSADA" -ForegroundColor Red
                Write-Host "Versão:   $version" -ForegroundColor Cyan
                Write-Host "Documento: $outputFile" -ForegroundColor White
                Write-Host "Impacto:  $($Impact.ToUpper())" -ForegroundColor Yellow
                Write-Host ""
            }

            Pop-Location
            exit 0
        }
    }

    Pop-Location
    exit 0
}
catch {
    Write-Host ""
    Write-Host "❌ ERRO NO PROCESSAMENTO" -ForegroundColor Red
    Write-Err $_.Exception.Message
    Write-Err "Stack Trace: $($_.ScriptStackTrace)"
    Write-Host ""
    Pop-Location
    exit 1
}
