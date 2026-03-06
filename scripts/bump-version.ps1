<#
.SYNOPSIS
    Automatiza versionamento semântico (SemVer) para projetos Git.

.DESCRIPTION
    Este script atualiza o arquivo VERSION e CHANGELOG.md baseado em:
    - Mensagem de commit (conventional commits)
    - Quantidade de arquivos alterados
    - Tags explícitas ([major], [minor], [patch])
    - Breaking changes (!, BREAKING CHANGE:)

    Formato de versão suportado:
    - Major: V. 2.0
    - Minor: V. 1.02
    - Patch: V. 1.02.0003

.PARAMETER CommitMessage
    Mensagem completa do commit (título + corpo)

.PARAMETER Bump
    Tipo de bump: major, minor ou patch (opcional - será inferido se não fornecido)

.PARAMETER FileCount
    Quantidade de arquivos alterados no commit

.PARAMETER VersionFile
    Caminho do arquivo VERSION (padrão: VERSION)

.PARAMETER ChangelogPath
    Caminho do arquivo CHANGELOG.md (padrão: CHANGELOG.md)

.PARAMETER CommitHash
    Hash curto do commit (para incluir no CHANGELOG)

.PARAMETER Verbose
    Ativa logs detalhados para debug

.EXAMPLE
    .\bump-version.ps1 -CommitMessage "feat: nova funcionalidade" -FileCount 5

.EXAMPLE
    .\bump-version.ps1 -CommitMessage "feat!: mudança incompatível" -Bump "major" -Verbose

.NOTES
    Autor: Sistema de Versionamento Automático
    Versão: 2.0
    Integra com hooks Git: post-commit
#>

param(
    [Parameter(Mandatory=$false)]
    [string]$CommitMessage = "",

    [Parameter(Mandatory=$false)]
    [string]$CommitMessagePath = "",

    [Parameter(Mandatory=$false)]
    [ValidateSet("major", "minor", "patch", "")]
    [string]$Bump = "",

    [Parameter(Mandatory=$false)]
    [ValidateRange(0, [int]::MaxValue)]
    [int]$FileCount = 0,

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$VersionFile = "VERSION",

    [Parameter(Mandatory=$false)]
    [ValidateNotNullOrEmpty()]
    [string]$ChangelogPath = "CHANGELOG.md",

    [Parameter(Mandatory=$false)]
    [string]$CommitHash = ""
)

# =============================================================================
# CONFIGURAÇÕES
# =============================================================================

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# Versão padrão se arquivo não existir
$DEFAULT_VERSION = "V. 1.0"

# Limites para inferência de bump
$MINOR_THRESHOLD = 10
$MAJOR_THRESHOLD = 100

# =============================================================================
# FUNÇÕES DE LOG
# =============================================================================

<#
.SYNOPSIS
    Escreve mensagem de debug (apenas se -Verbose estiver ativo)
#>
function Write-Debug-Message {
    param([string]$Message)
    if ($PSBoundParameters.ContainsKey('Verbose') -and $PSBoundParameters['Verbose']) {
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
# FUNÇÕES DE BACKUP
# =============================================================================

<#
.SYNOPSIS
    Cria backup de um arquivo antes de modificá-lo
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
        Copy-Item -LiteralPath $Path -Destination $backupPath -Force
        Write-Debug-Message "Backup criado: $backupPath"
    }
    catch {
        Write-Warning-Message "Não foi possível criar backup de ${Path}: $_"
    }
}

# =============================================================================
# FUNÇÕES DE VERSÃO
# =============================================================================

<#
.SYNOPSIS
    Lê conteúdo do arquivo VERSION
.DESCRIPTION
    Retorna a versão atual ou versão padrão se arquivo não existir
#>
function Read-Version {
    param([string]$Path)

    Write-Debug-Message "Lendo versão de: $Path"

    # Se arquivo não existe, retorna versão padrão
    if (!(Test-Path -LiteralPath $Path)) {
        Write-Debug-Message "Arquivo não existe, usando versão padrão: $DEFAULT_VERSION"
        return $DEFAULT_VERSION
    }

    try {
        $content = Get-Content -Raw -LiteralPath $Path -ErrorAction Stop
        $version = $content.Trim()

        # Se vazio, retorna versão padrão
        if ([string]::IsNullOrWhiteSpace($version)) {
            Write-Debug-Message "Arquivo vazio, usando versão padrão: $DEFAULT_VERSION"
            return $DEFAULT_VERSION
        }

        Write-Debug-Message "Versão lida: $version"
        return $version
    }
    catch {
        Write-Warning-Message "Erro ao ler $Path, usando versão padrão: $_"
        return $DEFAULT_VERSION
    }
}

<#
.SYNOPSIS
    Escreve nova versão no arquivo VERSION
#>
function Write-Version {
    param(
        [string]$Path,
        [string]$Value
    )

    Write-Debug-Message "Escrevendo versão '$Value' em: $Path"

    try {
        # Cria diretório se não existir
        $directory = Split-Path -Path $Path -Parent
        if ($directory -and !(Test-Path -LiteralPath $directory)) {
            New-Item -ItemType Directory -Path $directory -Force | Out-Null
            Write-Debug-Message "Diretório criado: $directory"
        }

        # Escreve versão sem newline final
        Set-Content -LiteralPath $Path -Value $Value -NoNewline -Encoding UTF8 -ErrorAction Stop
        Write-Info-Message "Versão atualizada: $Value"
    }
    catch {
        Write-Error-Message "Erro ao escrever versão em ${Path}: $_"
        throw
    }
}

<#
.SYNOPSIS
    Faz parse de string de versão em componentes numéricos
.DESCRIPTION
    Suporta múltiplos formatos:
    - V. 1.0
    - V. 1.02
    - V. 1.02.0003
    - v1.0.0 (sem espaço, minúsculo)
    - 1.0.0 (sem prefixo)
#>
function Parse-Version {
    param([string]$VersionString)

    if ([string]::IsNullOrWhiteSpace($VersionString)) {
        Write-Debug-Message "String de versão vazia, usando padrão"
        return @{ maj = 1; min = 0; pat = 0 }
    }

    $normalized = $VersionString.Trim()
    Write-Debug-Message "Fazendo parse da versão: $normalized"

    # Regex flexível que aceita múltiplos formatos:
    # - V. 1.0 | V. 1.02 | V. 1.02.0003 (formato preferido)
    # - v1.0.0 | V1.0.0 (sem espaço)
    # - 1.0.0 (sem prefixo)
    $pattern = '^(?:[Vv]\.?\s*)?(?<maj>\d+)\.(?<min>\d{1,2})(?:\.(?<pat>\d{1,4}))?$'

    if ($normalized -notmatch $pattern) {
        Write-Warning-Message "Formato de versão não reconhecido: '$normalized' - usando padrão"
        return @{ maj = 1; min = 0; pat = 0 }
    }

    # Extrai componentes
    $major = [int]$Matches['maj']
    $minor = [int]$Matches['min']
    $patch = 0

    if ($Matches.ContainsKey('pat') -and ![string]::IsNullOrEmpty($Matches['pat'])) {
        $patch = [int]$Matches['pat']
    }

    Write-Debug-Message "Parse resultado: major=$major, minor=$minor, patch=$patch"
    return @{ maj = $major; min = $minor; pat = $patch }
}

<#
.SYNOPSIS
    Formata componentes de versão em string
.DESCRIPTION
    Diferentes formatos baseados no tipo de bump:
    - major → V. 2.0
    - minor → V. 1.02
    - patch → V. 1.02.0003
#>
function Format-Version {
    param(
        [int]$Major,
        [int]$Minor,
        [int]$Patch,
        [string]$BumpKind
    )

    Write-Debug-Message "Formatando versão: maj=$Major, min=$Minor, pat=$Patch, kind=$BumpKind"

    switch ($BumpKind) {
        "major" {
            # Formato: V. X.0
            $formatted = "V. {0}.{1}" -f $Major, $Minor
        }
        "minor" {
            # Formato: V. X.YY (minor com zero padding de 2 dígitos)
            $formatted = "V. {0}.{1:D2}" -f $Major, $Minor
        }
        default {
            # Formato: V. X.YY.ZZZZ (patch com zero padding de 4 dígitos)
            $formatted = "V. {0}.{1:D2}.{2:D4}" -f $Major, $Minor, $Patch
        }
    }

    Write-Debug-Message "Versão formatada: $formatted"
    return $formatted
}

# =============================================================================
# DETECÇÃO DE TIPO DE BUMP
# =============================================================================

<#
.SYNOPSIS
    Detecta breaking changes na mensagem de commit
.DESCRIPTION
    Verifica:
    1. "!" antes de ":" (ex: feat!:, fix(scope)!:)
    2. "BREAKING CHANGE:" no footer/corpo
#>
function Test-BreakingChange {
    param([string]$Message)

    if ([string]::IsNullOrWhiteSpace($Message)) {
        return $false
    }

    $normalized = $Message.ToLowerInvariant()

    # Verifica "!" no título: feat!:, fix(scope)!:
    if ($Message -match '^\s*[a-z]+(\([^)]+\))?!:') {
        Write-Debug-Message "Breaking change detectado via '!'"
        return $true
    }

    # Verifica "BREAKING CHANGE:" ou "BREAKING-CHANGE:" no corpo/footer
    if ($normalized -match '(^|\n)\s*breaking[- ]change\s*:') {
        Write-Debug-Message "Breaking change detectado via footer 'BREAKING CHANGE:'"
        return $true
    }

    return $false
}

<#
.SYNOPSIS
    Detecta tipo de bump baseado na mensagem de commit e contexto
.DESCRIPTION
    Prioridade de detecção:
    1. Breaking changes → major
    2. Tags explícitas ([major], [minor], [patch])
    3. Tipo de commit (feat, fix, etc.)
    4. Quantidade de arquivos alterados
#>
function Detect-BumpFromMessage {
    param(
        [string]$Message,
        [int]$FileCount
    )

    Write-Debug-Message "Detectando tipo de bump..."
    Write-Debug-Message "  Mensagem: $($Message.Substring(0, [Math]::Min(50, $Message.Length)))..."
    Write-Debug-Message "  Arquivos: $FileCount"

    if ([string]::IsNullOrWhiteSpace($Message)) {
        Write-Debug-Message "Mensagem vazia, usando bump padrão: patch"
        return "patch"
    }

    $normalized = $Message.ToLowerInvariant()

    # ✅ PRIORIDADE 1: Breaking changes sempre resultam em MAJOR
    if (Test-BreakingChange -Message $Message) {
        Write-Debug-Message "Bump detectado: MAJOR (breaking change)"
        return "major"
    }

    # ✅ PRIORIDADE 2: Tags explícitas na mensagem
    if ($normalized -match '\[(major|release)\]') {
        Write-Debug-Message "Bump detectado: MAJOR (tag explícita)"
        return "major"
    }

    if ($normalized -match '\[minor\]') {
        Write-Debug-Message "Bump detectado: MINOR (tag explícita)"
        return "minor"
    }

    if ($normalized -match '\[(refactor|hotfix|patch)\]') {
        Write-Debug-Message "Bump detectado: PATCH (tag explícita)"
        return "patch"
    }

    # ✅ PRIORIDADE 3: Tipo de commit "release" → MAJOR
    if ($normalized -match '^\s*release(\([^)]+\))?:') {
        Write-Debug-Message "Bump detectado: MAJOR (tipo 'release')"
        return "major"
    }

    # ✅ PRIORIDADE 4: Quantidade muito grande de arquivos → MAJOR
    if ($FileCount -ge $MAJOR_THRESHOLD) {
        Write-Debug-Message "Bump detectado: MAJOR (≥$MAJOR_THRESHOLD arquivos)"
        return "major"
    }

    # ✅ PRIORIDADE 5: Quantidade moderada de arquivos → MINOR
    if ($FileCount -ge $MINOR_THRESHOLD) {
        Write-Debug-Message "Bump detectado: MINOR (≥$MINOR_THRESHOLD arquivos)"
        return "minor"
    }

    # ✅ PRIORIDADE 6: Tipo de commit que geralmente é MINOR
    if ($normalized -match '^\s*(feat|ux|refactor|perf)(\([^)]+\))?:') {
        Write-Debug-Message "Bump detectado: MINOR (tipo de commit)"
        return "minor"
    }

    # ✅ PADRÃO: PATCH para demais casos
    Write-Debug-Message "Bump detectado: PATCH (padrão)"
    return "patch"
}

<#
.SYNOPSIS
    Calcula próxima versão baseada no tipo de bump
.DESCRIPTION
    Segue versionamento semântico:
    - major: incrementa major, zera minor e patch
    - minor: incrementa minor, zera patch
    - patch: incrementa patch
#>
function Get-NextVersionParts {
    param(
        [hashtable]$CurrentParts,
        [string]$BumpKind
    )

    # Garante que valores são inteiros
    $major = [int]$CurrentParts.maj
    $minor = [int]$CurrentParts.min
    $patch = [int]$CurrentParts.pat

    Write-Debug-Message "Calculando próxima versão: atual=$major.$minor.$patch, bump=$BumpKind"

    switch ($BumpKind) {
        "major" {
            # Major: incrementa major, zera minor e patch
            $major += 1
            $minor = 0
            $patch = 0
            Write-Debug-Message "  → MAJOR bump: $major.0.0"
        }
        "minor" {
            # Minor: incrementa minor, zera patch
            $minor += 1
            $patch = 0
            Write-Debug-Message "  → MINOR bump: $major.$minor.0"
        }
        default {
            # Patch: incrementa patch
            $patch += 1
            Write-Debug-Message "  → PATCH bump: $major.$minor.$patch"
        }
    }

    return @{
        maj = $major
        min = $minor
        pat = $patch
    }
}

# =============================================================================
# GERENCIAMENTO DE CHANGELOG
# =============================================================================

<#
.SYNOPSIS
    Atualiza CHANGELOG.md com nova entrada de versão
.DESCRIPTION
    Cria entrada rica com:
    - Versão e data
    - Hash do commit (se fornecido)
    - Tipo de bump
    - Mensagem de commit
#>
function Update-Changelog {
    param(
        [string]$ChangelogPath,
        [string]$Version,
        [string]$CommitMessage,
        [string]$BumpType,
        [string]$CommitHash
    )

    Write-Debug-Message "Atualizando CHANGELOG: $ChangelogPath"

    # Normaliza mensagem
    if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
        $CommitMessage = "chore: atualização automática"
    }

    $message = $CommitMessage.Trim()

    # Pega primeira linha (título do commit)
    $firstLine = ($message -split "`r?`n")[0].Trim()

    # Data e hora atual
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

    # Monta cabeçalho da entrada
    $header = "## $Version"

    # Adiciona metadados
    $metadata = "**Data:** $timestamp"
    if (![string]::IsNullOrWhiteSpace($CommitHash)) {
        $metadata += " | **Commit:** ``$CommitHash``"
    }
    $metadata += " | **Tipo:** ``$BumpType``"

    # Monta entrada completa
    [string[]]$entry = @(
        $header,
        "",
        $metadata,
        "",
        "- $firstLine",
        ""
    )

    Write-Debug-Message "Entrada do CHANGELOG preparada (versão: $Version)"

    try {
        # Se CHANGELOG não existe, cria novo com cabeçalho
        if (!(Test-Path -LiteralPath $ChangelogPath)) {
            Write-Info-Message "Criando novo CHANGELOG.md"

            [string[]]$header = @(
                "# Changelog",
                "",
                "Registro automático de mudanças do projeto.",
                "Formato baseado em [Keep a Changelog](https://keepachangelog.com/).",
                ""
            )

            $content = ($header + $entry) -join "`n"
            Set-Content -LiteralPath $ChangelogPath -Value $content -Encoding UTF8 -ErrorAction Stop
            Write-Info-Message "CHANGELOG.md criado com sucesso"
            return
        }

        # Lê CHANGELOG existente
        $existing = Get-Content -Raw -LiteralPath $ChangelogPath -Encoding UTF8 -ErrorAction Stop

        # Verifica se versão já existe (evita duplicatas)
        $versionPattern = "(?m)^\#\#\s+" + [regex]::Escape($Version) + "\s*$"
        if ($existing -match $versionPattern) {
            Write-Warning-Message "Versão $Version já existe no CHANGELOG - pulando atualização"
            return
        }

        # Divide em linhas
        $lines = $existing -split "`r?`n"
        $linesList = New-Object 'System.Collections.Generic.List[string]'
        $linesList.AddRange([string[]]$lines)

        # Encontra posição de inserção (após cabeçalho principal)
        $insertPosition = 1
        $foundHeader = $false

        for ($i = 0; $i -lt $linesList.Count; $i++) {
            $line = $linesList[$i]

            # Detecta título principal (# Changelog)
            if ($line -match '^\#\s+') {
                $foundHeader = $true
                $insertPosition = $i + 1
                continue
            }

            # Após encontrar header, procura primeira linha vazia
            if ($foundHeader -and $line -match '^\s*$') {
                $insertPosition = $i + 1
                break
            }

            # Ou até encontrar primeira entrada de versão (## X.X.X)
            if ($foundHeader -and $line -match '^\#\#\s+') {
                $insertPosition = $i
                break
            }
        }

        Write-Debug-Message "Inserindo entrada na posição $insertPosition"

        # Insere nova entrada
        $linesList.InsertRange($insertPosition, [string[]]$entry)

        # Escreve CHANGELOG atualizado
        $updatedContent = $linesList -join "`n"
        Set-Content -LiteralPath $ChangelogPath -Value $updatedContent -Encoding UTF8 -ErrorAction Stop

        Write-Info-Message "CHANGELOG.md atualizado com versão $Version"
    }
    catch {
        Write-Error-Message "Erro ao atualizar CHANGELOG: $_"
        throw
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
            Write-Warning-Message "Não foi possível ler CommitMessagePath: $_"
        }
    }

    Write-Debug-Message "========================================="
    Write-Debug-Message "bump-version.ps1 iniciado"
    Write-Debug-Message "========================================="
    Write-Debug-Message "Parâmetros recebidos:"
    Write-Debug-Message "  CommitMessage: $($CommitMessage.Substring(0, [Math]::Min(50, $CommitMessage.Length)))..."
    Write-Debug-Message "  Bump: $Bump"
    Write-Debug-Message "  FileCount: $FileCount"
    Write-Debug-Message "  VersionFile: $VersionFile"
    Write-Debug-Message "  ChangelogPath: $ChangelogPath"
    Write-Debug-Message "  CommitHash: $CommitHash"
    Write-Debug-Message "========================================="

    # -----------------------------------------------------------------------------
    # ETAPA 1: Criar backups
    # -----------------------------------------------------------------------------

    Write-Debug-Message "Criando backups..."
    Backup-File -Path $VersionFile
    Backup-File -Path $ChangelogPath

    # -----------------------------------------------------------------------------
    # ETAPA 2: Ler versão atual
    # -----------------------------------------------------------------------------

    Write-Info-Message "Lendo versão atual..."
    $currentVersion = Read-Version -Path $VersionFile
    Write-Info-Message "Versão atual: $currentVersion"

    # -----------------------------------------------------------------------------
    # ETAPA 3: Fazer parse da versão
    # -----------------------------------------------------------------------------

    $parsedVersion = Parse-Version -VersionString $currentVersion
    Write-Debug-Message "Versão parseada: $($parsedVersion.maj).$($parsedVersion.min).$($parsedVersion.pat)"

    # -----------------------------------------------------------------------------
    # ETAPA 4: Determinar tipo de bump
    # -----------------------------------------------------------------------------

    if ([string]::IsNullOrWhiteSpace($Bump)) {
        Write-Info-Message "Detectando tipo de bump automaticamente..."
        $Bump = Detect-BumpFromMessage -Message $CommitMessage -FileCount $FileCount
    }
    else {
        Write-Debug-Message "Tipo de bump fornecido explicitamente: $Bump"
    }

    Write-Info-Message "Tipo de bump: $Bump"

    # -----------------------------------------------------------------------------
    # ETAPA 5: Calcular próxima versão
    # -----------------------------------------------------------------------------

    Write-Info-Message "Calculando próxima versão..."
    $nextParts = Get-NextVersionParts -CurrentParts $parsedVersion -BumpKind $Bump

    # -----------------------------------------------------------------------------
    # ETAPA 6: Formatar nova versão
    # -----------------------------------------------------------------------------

    $nextVersion = Format-Version -Major $nextParts.maj -Minor $nextParts.min -Patch $nextParts.pat -BumpKind $Bump
    Write-Info-Message "Nova versão: $nextVersion"

    # -----------------------------------------------------------------------------
    # ETAPA 7: Escrever nova versão no arquivo
    # -----------------------------------------------------------------------------

    Write-Version -Path $VersionFile -Value $nextVersion

    # -----------------------------------------------------------------------------
    # ETAPA 8: Atualizar CHANGELOG
    # -----------------------------------------------------------------------------

    Write-Info-Message "Atualizando CHANGELOG..."
    Update-Changelog `
        -ChangelogPath $ChangelogPath `
        -Version $nextVersion `
        -CommitMessage $CommitMessage `
        -BumpType $Bump `
        -CommitHash $CommitHash

    # -----------------------------------------------------------------------------
    # ETAPA 9: Mensagem de sucesso
    # -----------------------------------------------------------------------------

    Write-Host ""
    Write-Host "=========================================" -ForegroundColor Green
    Write-Host "✅ VERSIONAMENTO CONCLUÍDO COM SUCESSO" -ForegroundColor Green
    Write-Host "=========================================" -ForegroundColor Green
    Write-Host "Versão anterior: $currentVersion" -ForegroundColor White
    Write-Host "Nova versão:     $nextVersion" -ForegroundColor Cyan
    Write-Host "Tipo de bump:    $Bump" -ForegroundColor Yellow
    Write-Host "=========================================" -ForegroundColor Green
    Write-Host ""

    # Retorna código de sucesso
    exit 0
}
catch {
    Write-Host ""
    Write-Host "=========================================" -ForegroundColor Red
    Write-Host "❌ ERRO NO VERSIONAMENTO" -ForegroundColor Red
    Write-Host "=========================================" -ForegroundColor Red
    Write-Error-Message $_.Exception.Message
    Write-Host "=========================================" -ForegroundColor Red
    Write-Host ""

    # Retorna código de erro
    exit 1
}
