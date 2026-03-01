#Requires -Version 7.0
param([string]$MsgFile)

$ErrorActionPreference = 'Stop'

# Configurações
$ALLOWED_TYPES = "feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert|release"
$MIN_DESC_LENGTH = 10
$DEBUG = [bool]$env:DEBUG

# Cores
$RED = "`e[0;31m"
$GREEN = "`e[0;32m"
$YELLOW = "`e[1;33m"
$NC = "`e[0m"

function Write-ErrorExit {
    param([string]$msg)
    Write-Host "${RED}ERRO:${NC} $msg"
    exit 1
}

function Write-Warn {
    param([string]$msg)
    Write-Host "${YELLOW}AVISO:${NC} $msg"
}

function Write-DebugInfo {
    param([string]$msg)
    if ($DEBUG) { Write-Host "${GREEN}DEBUG:${NC} $msg" }
}

# Validação inicial
if (-not $MsgFile -or -not (Test-Path $MsgFile)) { exit 0 }

Write-DebugInfo "Arquivo: $MsgFile"

$msg = Get-Content $MsgFile -Raw -ErrorAction SilentlyContinue
if (-not $msg) { Write-ErrorExit "Mensagem vazia." }

$firstLine = ($msg -split "`n")[0].Trim()
$lower = $msg.ToLower()

Write-DebugInfo "Primeira linha: $firstLine"

# Exceções
if ($firstLine -match '^Merge') {
    Write-DebugInfo "Commit de merge - pulando validação"
    exit 0
}

if ($lower -match '\[skip[-\s]?version\]') {
    Write-Warn "Validação ignorada por [skip version]"
    exit 0
}

# Extração: tipo(escopo): descrição
if ($firstLine -match '^([a-z]+)(\([^)]+\))?(!)?:\s*(.*)$') {
    $type = $matches[1].ToLower()
    $desc = $matches[4].Trim()
} else {
    Write-ErrorExit @"
Padrão inválido. Use: tipo(escopo): descrição

Exemplos:
  feat(auth): adicionar login OAuth
  fix: corrigir validação de CPF
  docs(readme): atualizar instruções

[skip version] para ignorar versionamento.
https://www.conventionalcommits.org/
"@
}

Write-DebugInfo "Tipo: '$type' | Descrição: '$desc'"

# Validar tipo
if ($type -notmatch "^($ALLOWED_TYPES)$") {
    Write-ErrorExit "Tipo '$type' não permitido.`nTipos válidos: $ALLOWED_TYPES"
}

# Validação especial: release
if ($type -eq 'release' -and $lower -notmatch '\[(release|major)\]') {
    Write-ErrorExit "Tipo 'release' exige [release] ou [major].`nExemplo: release: preparar v2.0.0 [major]"
}

# Mensagens genéricas
$genericRe = '^(update|upd|wip|teste?|fix|ajustes?|mudancas?|alteracoes?|commit|changes?|update code|fix bug)$'
if ($desc.ToLower().Trim() -match $genericRe) {
    Write-ErrorExit "Mensagem genérica: '$desc'`nUse algo descritivo ou [skip version]."
}

# Tamanho mínimo
if ($desc.Length -lt $MIN_DESC_LENGTH) {
    Write-ErrorExit "Descrição curta ($($desc.Length) chars). Mínimo: $MIN_DESC_LENGTH.`nAtual: '$desc'"
}

# Validar tags [xxx]
$allowedTagRe = '^\[(major|release|minor|patch\d*|hotfix|refactor|skip[-\s]?version|skip[-\s]?draft|skip release-notes|security|deprecated|removed|impact:(nenhum|baixo|medio|alto))\]$'
$tags = [regex]::Matches($msg, '\[[^\]]+\]') | ForEach-Object { $_.Value }

foreach ($tag in $tags) {
    $tagLower = $tag.ToLower()
    Write-DebugInfo "Validando tag: $tagLower"

    if ($tagLower -notmatch $allowedTagRe) {
        Write-ErrorExit @"
Tag inválida: $tag

Permitidas:
  Versionamento: [major] [release] [minor] [patch] [hotfix]
  Controle: [skip version] [skip draft] [skip release-notes]
  Especiais: [security] [deprecated] [removed]
  Impacto: [impact:nenhum] [impact:baixo] [impact:medio] [impact:alto]
"@
    }
}

Write-DebugInfo "✓ Mensagem validada!"
exit 0
