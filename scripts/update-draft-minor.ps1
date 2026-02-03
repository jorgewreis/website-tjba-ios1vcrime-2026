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
    Nível de impacto: nenhum, baixo, medio, médio, alto

.PARAMETER RepoRoot
    Raiz do repositório (padrão: diretório atual)

.PARAMETER VersionFile
    Caminho do arquivo VERSION (padrão: VERSION)

.PARAMETER BufferMinorPath
    Caminho do buffer para acumular patches (padrão: docs/90-releases/_buffer_minor.txt)

.PARAMETER DraftMajorPath
    Caminho do draft para major releases (padrão: docs/90-releases/_draft_major.md)

.PARAMETER NotesDir
    Diretório para salvar release notes (padrão: docs/90-releases/notes)

.PARAMETER Verbose
    Ativa logs detalhados para debug

.EXAMPLE
    .\update-draft-minor.ps1 -CommitHash "a1b2c3d" -CommitMessage "feat: nova funcionalidade" -Bump "minor" -Impact "baixo"

.NOTES
    Autor: Sistema de Versionamento Automático
    Versão: 2.0
    Integra com hooks Git: post-commit
#>

param(
    [Parameter(Mandatory=$true)]
    [ValidateNotNullOrEmpty()]
    [string]$CommitHash,

    [Parameter(Mandatory=$true)]
    [ValidateNotNullOrEmpty()]
    [string]$CommitMessage,

    [Parameter(Mandatory=$true)]
    [ValidateSet("major", "minor", "patch")]
    [string]$Bump,

    [Parameter(Mandatory=$true)]
    [ValidateSet("nenhum", "baixo", "medio", "alto")]  # Removido "médio" para evitar problemas de encoding
    [string]$Impact,

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$RepoRoot = ".",

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$VersionFile = "VERSION",

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$BufferMinorPath = "docs/90-releases/_buffer_minor.txt",

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$DraftMajorPath = "docs/90-releases/_draft_major.md",

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$NotesDir = "docs/90-releases/notes"
)

# =============================================================================
# CONFIGURAÇÕES
# =============================================================================

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# Timeout para operações de arquivo (segundos)
$FILE_LOCK_TIMEOUT = 10

# Tentativas de retry em caso de lock
$MAX_RETRIES = 5

# =============================================================================
# MAPEAMENTO DE CATEGORIAS
# =============================================================================

# Mapeia tipos de commit em português para categorias em inglês
$CategoryMap = @{
    "Feature"      = "New"
    "Correção"     = "Bugfixes"
    "Refatoração"  = "Improvements"
    "Performance"  = "Improvements"
    "Segurança"    = "Security"
    "Documentação" = "Documentation"
    "Manutenção"   = "Internal"
    "Testes"       = "Testing"
    "Build"        = "Build"
    "CI/CD"        = "CI/CD"
    "Geral"        = "Misc"
}

# Ordem de exibição das categorias no documento final
$CategoryOrder = @(
    "New",
    "Improvements",
    "Security",
    "Bugfixes",
    "Documentation",
    "Testing",
    "Build",
    "CI/CD",
    "Internal",
    "Misc"
)

# =============================================================================
# FUNÇÕES DE LOG
# =============================================================================

<#
.SYNOPSIS
    Escreve mensagem de debug (apenas se -Verbose estiver ativo)
#>
function Write-Debug-Message {
    param([string]$Message)
    if ($PSCmdlet.MyInvocation.BoundParameters["Verbose"].IsPresent) {
        Write-Host "[DEBUG] $Message" -ForegroundColor Cyan
    }
}

<#
.SYNOPSIS
    Escreve mensagem de informação
#>
function Write-Info-Message {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Green
}

<#
.SYNOPSIS
    Escreve mensagem de aviso
#>
function Write-Warning-Message {
    param([string]$Message)
    Write-Host "[WARN] $Message" -ForegroundColor Yellow
}

<#
.SYNOPSIS
    Escreve mensagem de erro
#>
function Write-Error-Message {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

# =============================================================================
# FUNÇÕES UTILITÁRIAS
# =============================================================================

<#
.SYNOPSIS
    Garante que um diretório existe, criando-o se necessário
#>
function Ensure-Directory {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        Write-Warning-Message "Caminho de diretório vazio"
        return
    }

    try {
        if (!(Test-Path -LiteralPath $Path -PathType Container)) {
            Write-Debug-Message "Criando diretório: $Path"
            New-Item -ItemType Directory -Force -Path $Path | Out-Null
        }
    }
    catch {
        Write-Error-Message "Erro ao criar diretório ${Path}: $_"
        throw
    }
}

<#
.SYNOPSIS
    Lê conteúdo do arquivo VERSION
#>
function Get-VersionText {
    param([string]$Path)

    Write-Debug-Message "Lendo versão de: $Path"

    if (!(Test-Path -LiteralPath $Path)) {
        Write-Warning-Message "Arquivo VERSION não encontrado, usando padrão: V. 1.0"
        return "V. 1.0"
    }

    try {
        $content = Get-Content -Raw -LiteralPath $Path -Encoding UTF8 -ErrorAction Stop
        $version = $content.Trim()

        if ([string]::IsNullOrWhiteSpace($version)) {
            Write-Warning-Message "Arquivo VERSION vazio, usando padrão: V. 1.0"
            return "V. 1.0"
        }

        Write-Debug-Message "Versão lida: $version"
        return $version
    }
    catch {
        Write-Warning-Message "Erro ao ler VERSION, usando padrão: $_"
        return "V. 1.0"
    }
}

<#
.SYNOPSIS
    Retorna data no formato ISO (YYYY-MM-DD)
#>
function Get-IsoDate {
    return (Get-Date).ToString("yyyy-MM-dd")
}

<#
.SYNOPSIS
    Retorna data no formato legível em português (03 de fevereiro de 2026)
#>
function Get-HumanDate {
    $date = Get-Date
    $culture = [System.Globalization.CultureInfo]::CreateSpecificCulture("pt-BR")
    return $date.ToString("dd 'de' MMMM 'de' yyyy", $culture)
}

<#
.SYNOPSIS
    Retorna timestamp completo (YYYY-MM-DD HH:MM:SS)
#>
function Get-Timestamp {
    return (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
}

<#
.SYNOPSIS
    Cria backup de um arquivo
#>
function Backup-File {
    param(
        [string]$Path,
        [string]$BackupSuffix = ".bak"
    )

    if (!(Test-Path -LiteralPath $Path)) {
        Write-Debug-Message "Arquivo não existe, backup não necessário: $Path"
        return
    }

    try {
        $backupPath = "$Path$BackupSuffix"
        Copy-Item -LiteralPath $Path -Destination $backupPath -Force -ErrorAction Stop
        Write-Debug-Message "Backup criado: $backupPath"
    }
    catch {
        Write-Warning-Message "Não foi possível criar backup de ${Path}: $_"
    }
}

# =============================================================================
# FUNÇÕES DE GIT
# =============================================================================

<#
.SYNOPSIS
    Executa comando Git com tratamento de erro
#>
function Invoke-GitCommand {
    param(
        [Parameter(Mandatory=$true)]
        [string[]]$Arguments
    )

    Write-Debug-Message "Executando: git $($Arguments -join ' ')"

    # Verifica se Git está disponível
    if (-not (Get-Command git.exe -ErrorAction SilentlyContinue)) {
        Write-Warning-Message "Git não está disponível no PATH"
        return @()
    }

    try {
        # Executa comando e captura saída
        $output = & git.exe @Arguments 2>&1

        # Verifica código de saída
        if ($LASTEXITCODE -eq 0) {
            Write-Debug-Message "Git comando executado com sucesso"
            return $output | Where-Object { $_ -is [string] }
        }
        else {
            Write-Warning-Message "Git comando falhou com código $LASTEXITCODE"
            return @()
        }
    }
    catch {
        Write-Warning-Message "Erro ao executar Git: $_"
        return @()
    }
}

<#
.SYNOPSIS
    Obtém lista de arquivos alterados em um commit (name-status)
#>
function Get-CommitNameStatus {
    param([string]$Hash)

    Write-Debug-Message "Obtendo arquivos alterados para commit: $Hash"

    $output = Invoke-GitCommand @("show", "--name-status", "--format=", $Hash)

    if ($output.Count -eq 0) {
        Write-Warning-Message "Nenhum arquivo encontrado para commit $Hash"
        return @()
    }

    # Filtra linhas vazias
    $files = $output | Where-Object { ![string]::IsNullOrWhiteSpace($_) }

    Write-Debug-Message "Arquivos encontrados: $($files.Count)"
    return $files
}

<#
.SYNOPSIS
    Obtém autor de um commit
#>
function Get-CommitAuthor {
    param([string]$Hash)

    $output = Invoke-GitCommand @("show", "--format=%an", "--no-patch", $Hash)

    if ($output.Count -gt 0) {
        return $output[0].Trim()
    }

    return "Unknown"
}

# =============================================================================
# CLASSIFICAÇÃO DE COMMITS
# =============================================================================

<#
.SYNOPSIS
    Classifica tipo de mudança baseado na primeira linha do commit
.DESCRIPTION
    Analisa prefixo do commit (conventional commits) e retorna categoria em português
#>
function Get-ChangeType {
    param([string]$FirstLine)

    if ([string]::IsNullOrWhiteSpace($FirstLine)) {
        Write-Debug-Message "Linha vazia, usando tipo padrão: Geral"
        return "Geral"
    }

    $normalized = $FirstLine.Trim().ToLowerInvariant()
    Write-Debug-Message "Classificando tipo de commit: $normalized"

    # Conventional commits
    if ($normalized -match '^\s*(feat|feature)(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Feature"
        return "Feature"
    }

    if ($normalized -match '^\s*(fix|bugfix)(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Correção"
        return "Correção"
    }

    if ($normalized -match '^\s*refactor(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Refatoração"
        return "Refatoração"
    }

    if ($normalized -match '^\s*perf(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Performance"
        return "Performance"
    }

    if ($normalized -match '^\s*(security|sec)(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Segurança"
        return "Segurança"
    }

    if ($normalized -match '^\s*docs(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Documentação"
        return "Documentação"
    }

    if ($normalized -match '^\s*test(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Testes"
        return "Testes"
    }

    if ($normalized -match '^\s*build(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Build"
        return "Build"
    }

    if ($normalized -match '^\s*(ci|cd)(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: CI/CD"
        return "CI/CD"
    }

    if ($normalized -match '^\s*chore(\([^)]+\))?(!)?:') {
        Write-Debug-Message "Tipo detectado: Manutenção"
        return "Manutenção"
    }

    Write-Debug-Message "Tipo não reconhecido, usando padrão: Geral"
    return "Geral"
}

# =============================================================================
# ANÁLISE DE ARQUIVOS ALTERADOS
# =============================================================================

<#
.SYNOPSIS
    Sumariza arquivos alterados por categoria de módulo
.DESCRIPTION
    Analisa lista de arquivos e conta quantos pertencem a cada módulo/área
#>
function Get-FilesSummaryByModule {
    param([string[]]$NameStatusLines)

    Write-Debug-Message "Sumarizando arquivos por módulo..."

    # Inicializa contadores
    $summary = [ordered]@{
        "App/Core"   = 0
        "Public"     = 0
        "Database"   = 0
        "Config"     = 0
        "Docs"       = 0
        "Tests"      = 0
        "Scripts"    = 0
        "Outros"     = 0
    }

    foreach ($line in $NameStatusLines) {
        if ([string]::IsNullOrWhiteSpace($line)) {
            continue
        }

        # Parse: "M\tpath/to/file.js"
        $parts = $line -split "`t"
        if ($parts.Count -lt 2) {
            continue
        }

        $filePath = $parts[-1].Trim()
        Write-Debug-Message "  Analisando: $filePath"

        # Classifica por padrão de caminho
        switch -Regex ($filePath) {
            '^(app|bootstrap|src|lib)/' {
                $summary["App/Core"]++
                break
            }
            '^public/' {
                $summary["Public"]++
                break
            }
            '^(database|sql|bd|migrations)/' {
                $summary["Database"]++
                break
            }
            '^config/' {
                $summary["Config"]++
                break
            }
            '^docs/' {
                $summary["Docs"]++
                break
            }
            '^(tests|__tests__|spec)/' {
                $summary["Tests"]++
                break
            }
            '^scripts/' {
                $summary["Scripts"]++
                break
            }
            default {
                $summary["Outros"]++
                break
            }
        }
    }

    # Log resumo
    foreach ($module in $summary.Keys) {
        if ($summary[$module] -gt 0) {
            Write-Debug-Message "  $module: $($summary[$module]) arquivos"
        }
    }

    return $summary
}

# =============================================================================
# GERENCIAMENTO DE BUFFER
# =============================================================================

<#
.SYNOPSIS
    Adiciona entrada ao buffer de forma thread-safe
.DESCRIPTION
    Usa Mutex para garantir que apenas um processo escreve por vez
#>
function Add-BufferEntry {
    param(
        [string]$BufferPath,
        [string]$ChangeType,
        [string]$Message,
        [string]$CommitHash,
        [string]$Author = ""
    )

    Write-Debug-Message "Adicionando entrada ao buffer: $BufferPath"

    # Garante que diretório existe
    $directory = Split-Path -Path $BufferPath -Parent
    Ensure-Directory -Path $directory

    # Remove pipes da mensagem para evitar quebra do formato
    $safeMessage = $Message.Replace("|", "¦")  # Usa pipe quebrado em vez de hífen

    # Formato: TYPE|MESSAGE|HASH|AUTHOR|TIMESTAMP
    $timestamp = Get-Timestamp
    $line = "$ChangeType|$safeMessage|$CommitHash|$Author|$timestamp"

    # ✅ CORREÇÃO: Usa Mutex para evitar concorrência
    $mutexName = "Global\GitReleaseNotes_Buffer"
    $mutex = $null

    try {
        # Cria ou abre mutex
        $mutex = New-Object System.Threading.Mutex($false, $mutexName)

        # Aguarda lock com timeout
        if (!$mutex.WaitOne([TimeSpan]::FromSeconds($FILE_LOCK_TIMEOUT))) {
            Write-Warning-Message "Timeout ao aguardar lock do buffer - tentando sem lock"
        }

        Write-Debug-Message "Lock adquirido, escrevendo no buffer"

        # Escreve no arquivo
        $stream = [System.IO.StreamWriter]::new(
            $BufferPath,
            $true,  # append
            [System.Text.UTF8Encoding]::new($false)  # UTF8 sem BOM
        )

        $stream.WriteLine($line)
        $stream.Close()

        Write-Info-Message "Entrada adicionada ao buffer: $ChangeType"
    }
    catch {
        Write-Error-Message "Erro ao escrever no buffer: $_"
        throw
    }
    finally {
        # Libera lock
        if ($null -ne $mutex) {
            try {
                $mutex.ReleaseMutex()
                Write-Debug-Message "Lock liberado"
            }
            catch {
                Write-Debug-Message "Mutex já foi liberado"
            }
            $mutex.Dispose()
        }
    }
}

<#
.SYNOPSIS
    Lê entradas do buffer
#>
function Get-BufferEntries {
    param([string]$BufferPath)

    Write-Debug-Message "Lendo buffer: $BufferPath"

    if (!(Test-Path -LiteralPath $BufferPath)) {
        Write-Debug-Message "Buffer não existe, retornando array vazio"
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
            $parts = $line -split "\|"

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

        Write-Debug-Message "Buffer lido: $($entries.Count) entradas"
        return $entries
    }
    catch {
        Write-Error-Message "Erro ao ler buffer: $_"
        return @()
    }
}

# =============================================================================
# GERAÇÃO DE RELEASE NOTES
# =============================================================================

<#
.SYNOPSIS
    Gera documento de release notes estilizado no formato Markdown
.DESCRIPTION
    Compila entradas do buffer em documento formatado com:
    - Badge de versão em Markdown puro (sem HTML)
    - Data legível em português
    - Seções categorizadas
    - Metadados de commits
#>
function New-ReleaseNoteDocument {
    param(
        [string]$OutputPath,
        [string]$Version,
        [string]$Title,
        [string]$BadgeType,  # MINOR ou MAJOR
        [PSCustomObject[]]$Entries,
        [hashtable]$FilesSummary = @{},
        [string]$ImpactLevel = ""
    )

    Write-Debug-Message "Gerando release note: $OutputPath"

    # Cria backup se arquivo já existe
    Backup-File -Path $OutputPath

    # Garante que diretório existe
    $directory = Split-Path -Path $OutputPath -Parent
    Ensure-Directory -Path $directory

    # Inicia construção do documento
    $sb = [System.Text.StringBuilder]::new()

    # ===== CABEÇALHO =====

    # ✅ MELHORIA: Badge em Markdown puro (funciona em qualquer visualizador)
    $badgeColor = if ($BadgeType -eq "MAJOR") { "critical" } else { "blue" }
    $dateStr = Get-HumanDate

    $sb.AppendLine("![Badge](https://img.shields.io/badge/$BadgeType-$badgeColor?style=for-the-badge)")
    $sb.AppendLine("")
    $sb.AppendLine("**Data de Lançamento:** $dateStr")
    $sb.AppendLine("")

    # ===== TÍTULO PRINCIPAL =====

    $sb.AppendLine("# $Version")
    $sb.AppendLine("")
    if (![string]::IsNullOrWhiteSpace($Title)) {
        $sb.AppendLine("> $Title")
        $sb.AppendLine("")
    }

    # ===== SEÇÃO DE IMPACTO (se fornecido) =====

    if (![string]::IsNullOrWhiteSpace($ImpactLevel)) {
        $impactEmoji = switch ($ImpactLevel) {
            "alto"   { "🔴" }
            "medio"  { "🟡" }
            "baixo"  { "🟢" }
            default  { "⚪" }
        }

        $sb.AppendLine("## Nível de Impacto")
        $sb.AppendLine("")
        $sb.AppendLine("$impactEmoji **$($ImpactLevel.ToUpper())**")
        $sb.AppendLine("")
    }

    # ===== SEÇÕES CATEGORIZADAS =====

    if ($Entries.Count -gt 0) {
        Write-Debug-Message "Processando $($Entries.Count) entradas"

        # Agrupa entradas por categoria mapeada
        $grouped = $Entries | Group-Object {
            $type = $_.Type

            # ✅ CORREÇÃO: Fallback para categoria não mapeada
            if ($CategoryMap.ContainsKey($type)) {
                return $CategoryMap[$type]
            }
            else {
                Write-Debug-Message "Tipo não mapeado: $type - usando Misc"
                return "Misc"
            }
        }

        # Itera na ordem definida
        foreach ($categoryName in $CategoryOrder) {
            $group = $grouped | Where-Object { $_.Name -eq $categoryName }

            if ($null -eq $group -or $group.Count -eq 0) {
                continue
            }

            Write-Debug-Message "Adicionando categoria: $categoryName ($($group.Group.Count) itens)"

            # Traduz nome da categoria para português
            $categoryNamePt = switch ($categoryName) {
                "New"           { "Novidades" }
                "Improvements"  { "Melhorias" }
                "Security"      { "Segurança" }
                "Bugfixes"      { "Correções" }
                "Documentation" { "Documentação" }
                "Testing"       { "Testes" }
                "Build"         { "Build/Dependências" }
                "CI/CD"         { "Integração Contínua" }
                "Internal"      { "Interno" }
                "Misc"          { "Outros" }
                default         { $categoryName }
            }

            $sb.AppendLine("## $categoryNamePt")
            $sb.AppendLine("")

            foreach ($item in $group.Group) {
                # Formato: - **Mensagem** (hash) by @autor
                $line = "- **$($item.Message)**"

                # Adiciona hash se disponível
                if (![string]::IsNullOrWhiteSpace($item.Hash)) {
                    $line += " ``[$($item.Hash)]``"
                }

                # Adiciona autor se disponível
                if (![string]::IsNullOrWhiteSpace($item.Author)) {
                    $line += " por @$($item.Author)"
                }

                $sb.AppendLine($line)
            }

            $sb.AppendLine("")
        }
    }

    # ===== ESTATÍSTICAS DE ARQUIVOS (se fornecido) =====

    if ($FilesSummary.Count -gt 0) {
        $totalFiles = ($FilesSummary.Values | Measure-Object -Sum).Sum

        if ($totalFiles -gt 0) {
            $sb.AppendLine("## Estatísticas de Alterações")
            $sb.AppendLine("")
            $sb.AppendLine("**Total de arquivos alterados:** $totalFiles")
            $sb.AppendLine("")
            $sb.AppendLine("### Distribuição por Módulo")
            $sb.AppendLine("")

            foreach ($module in $FilesSummary.Keys) {
                $count = $FilesSummary[$module]
                if ($count -gt 0) {
                    $percentage = [math]::Round(($count / $totalFiles) * 100, 1)
                    $sb.AppendLine("- **$module**: $count arquivos ($percentage%)")
                }
            }

            $sb.AppendLine("")
        }
    }

    # ===== RODAPÉ =====

    $sb.AppendLine("---")
    $sb.AppendLine("")
    $sb.AppendLine("*Documento gerado automaticamente pelo sistema de versionamento.*")

    # ===== SALVA ARQUIVO =====

    try {
        [System.IO.File]::WriteAllText(
            $OutputPath,
            $sb.ToString(),
            [System.Text.UTF8Encoding]::new($false)
        )

        Write-Info-Message "Release note gerado: $OutputPath"
        return $true
    }
    catch {
        Write-Error-Message "Erro ao salvar release note: $_"
        return $false
    }
}

# =============================================================================
# SCRIPT PRINCIPAL
# =============================================================================

try {
    Write-Debug-Message "========================================="
    Write-Debug-Message "update-draft-minor.ps1 iniciado"
    Write-Debug-Message "========================================="
    Write-Debug-Message "Parâmetros:"
    Write-Debug-Message "  CommitHash: $CommitHash"
    Write-Debug-Message "  Bump: $Bump"
    Write-Debug-Message "  Impact: $Impact"
    Write-Debug-Message "  Version: (será lido)"
    Write-Debug-Message "========================================="

    # Muda para diretório raiz do repositório
    Push-Location $RepoRoot

    # -----------------------------------------------------------------------------
    # ETAPA 1: Coleta de Informações
    # -----------------------------------------------------------------------------

    Write-Info-Message "Coletando informações do commit..."

    # Lê versão atual
    $version = Get-VersionText -Path $VersionFile
    Write-Debug-Message "Versão atual: $version"

    # Normaliza Impact (converte "médio" com acento para "medio")
    $Impact = $Impact.ToLowerInvariant().Replace("é", "e")
    Write-Debug-Message "Impact normalizado: $Impact"

    # Extrai primeira linha da mensagem (título do commit)
    $firstLine = ($CommitMessage -split "`r?`n")[0].Trim()
    Write-Debug-Message "Título do commit: $firstLine"

    # Classifica tipo de mudança
    $changeType = Get-ChangeType -FirstLine $firstLine
    Write-Debug-Message "Tipo de mudança: $changeType"

    # Obtém autor do commit
    $author = Get-CommitAuthor -Hash $CommitHash
    Write-Debug-Message "Autor: $author"

    # -----------------------------------------------------------------------------
    # ETAPA 2: Processamento Baseado no Tipo de Bump
    # -----------------------------------------------------------------------------

    switch ($Bump) {
        # =========================================================================
        # PATCH: Apenas acumula no buffer
        # =========================================================================
        "patch" {
            Write-Info-Message "Processando PATCH - acumulando no buffer..."

            Add-BufferEntry `
                -BufferPath $BufferMinorPath `
                -ChangeType $changeType `
                -Message $firstLine `
                -CommitHash $CommitHash `
                -Author $author

            Write-Host ""
            Write-Host "=========================================" -ForegroundColor Green
            Write-Host "✅ PATCH REGISTRADO NO BUFFER" -ForegroundColor Green
            Write-Host "=========================================" -ForegroundColor Green
            Write-Host "Tipo:    $changeType" -ForegroundColor White
            Write-Host "Commit:  $CommitHash" -ForegroundColor Cyan
            Write-Host "Autor:   $author" -ForegroundColor White
            Write-Host "=========================================" -ForegroundColor Green
            Write-Host ""
            Write-Host "Aguardando release MINOR para compilar documento..." -ForegroundColor Yellow
            Write-Host ""

            Pop-Location
            exit 0
        }

        # =========================================================================
        # MINOR: Compila buffer em documento Markdown
        # =========================================================================
        "minor" {
            Write-Info-Message "Processando MINOR - compilando buffer..."

            # Adiciona commit atual ao buffer antes de compilar
            Add-BufferEntry `
                -BufferPath $BufferMinorPath `
                -ChangeType $changeType `
                -Message $firstLine `
                -CommitHash $CommitHash `
                -Author $author

            # Lê todas as entradas acumuladas
            $entries = Get-BufferEntries -BufferPath $BufferMinorPath

            if ($entries.Count -eq 0) {
                Write-Warning-Message "Buffer vazio - gerando documento apenas com commit atual"
                $entries = @(
                    [PSCustomObject]@{
                        Type      = $changeType
                        Message   = $firstLine
                        Hash      = $CommitHash
                        Author    = $author
                        Timestamp = Get-Timestamp
                    }
                )
            }

            Write-Info-Message "Total de entradas no buffer: $($entries.Count)"

            # Define nome do arquivo (versão sem espaços e barras)
            $safeVersion = $version -replace '\s+', '' -replace '/', '-'
            $outputFile = Join-Path $NotesDir "release-$safeVersion.md"

            # Gera documento
            $success = New-ReleaseNoteDocument `
                -OutputPath $outputFile `
                -Version $version `
                -Title $firstLine `
                -BadgeType "MINOR" `
                -Entries $entries `
                -ImpactLevel $Impact

            # ✅ CORREÇÃO: Só remove buffer se documento foi criado com sucesso
            if ($success) {
                if (Test-Path -LiteralPath $outputFile) {
                    Write-Info-Message "Limpando buffer após sucesso..."
                    Remove-Item -LiteralPath $BufferMinorPath -Force -ErrorAction SilentlyContinue

                    Write-Host ""
                    Write-Host "=========================================" -ForegroundColor Green
                    Write-Host "✅ VERSÃO MINOR PROCESSADA" -ForegroundColor Green
                    Write-Host "=========================================" -ForegroundColor Green
                    Write-Host "Versão:   $version" -ForegroundColor Cyan
                    Write-Host "Documento: $outputFile" -ForegroundColor White
                    Write-Host "Entradas: $($entries.Count) commits compilados" -ForegroundColor White
                    Write-Host "=========================================" -ForegroundColor Green
                    Write-Host ""
                }
                else {
                    Write-Warning-Message "Documento não foi criado - mantendo buffer"
                }
            }

            Pop-Location
            exit 0
        }

        # =========================================================================
        # MAJOR: Gera documento com foco em estatísticas
        # =========================================================================
        "major" {
            Write-Info-Message "Processando MAJOR - gerando documento com estatísticas..."

            # Obtém arquivos alterados do commit
            $nameStatus = Get-CommitNameStatus -Hash $CommitHash
            $filesSummary = Get-FilesSummaryByModule -NameStatusLines $nameStatus

            # Cria entrada para o commit MAJOR
            $entries = @(
                [PSCustomObject]@{
                    Type      = $changeType
                    Message   = $firstLine
                    Hash      = $CommitHash
                    Author    = $author
                    Timestamp = Get-Timestamp
                }
            )

            # Define nome do arquivo
            $safeVersion = $version -replace '\s+', '' -replace '/', '-'
            $outputFile = Join-Path $NotesDir "release-$safeVersion.md"

            # Gera documento com estatísticas
            $success = New-ReleaseNoteDocument `
                -OutputPath $outputFile `
                -Version $version `
                -Title $firstLine `
                -BadgeType "MAJOR" `
                -Entries $entries `
                -FilesSummary $filesSummary `
                -ImpactLevel $Impact

            # ✅ CORREÇÃO: Limpa buffers antigos apenas se sucesso
            if ($success -and (Test-Path -LiteralPath $outputFile)) {
                Write-Info-Message "Limpando buffers antigos..."
                Remove-Item -LiteralPath $BufferMinorPath -Force -ErrorAction SilentlyContinue

                Write-Host ""
                Write-Host "=========================================" -ForegroundColor Red
                Write-Host "✅ VERSÃO MAJOR PROCESSADA" -ForegroundColor Red
                Write-Host "=========================================" -ForegroundColor Red
                Write-Host "Versão:   $version" -ForegroundColor Cyan
                Write-Host "Documento: $outputFile" -ForegroundColor White
                Write-Host "Impacto:  $($Impact.ToUpper())" -ForegroundColor Yellow
                Write-Host "Arquivos: $($nameStatus.Count) alterados" -ForegroundColor White
                Write-Host "=========================================" -ForegroundColor Red
                Write-Host ""
            }

            Pop-Location
            exit 0
        }
    }

    # Não deveria chegar aqui
    Pop-Location
    exit 0
}
catch {
    Write-Host ""
    Write-Host "=========================================" -ForegroundColor Red
    Write-Host "❌ ERRO NO PROCESSAMENTO" -ForegroundColor Red
    Write-Host "=========================================" -ForegroundColor Red
    Write-Error-Message $_.Exception.Message
    Write-Host "=========================================" -ForegroundColor Red
    Write-Host ""

    Pop-Location
    exit 1
}
