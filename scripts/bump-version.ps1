param(
  [string]$CommitMessage,
  [ValidateSet("major","minor","patch","patch2000","patch8000")]
  [string]$Bump = "",
  [int]$FileCount = 0,
  [string]$VersionFile = "VERSION",
  [string]$ChangelogPath = "CHANGELOG.md",
  [string]$DraftMinorPath = "docs/90-releases/_draft_minor.md", # compat (não usado aqui)
  [string]$NotesDir = "docs/90-releases/notes"                 # compat (não usado aqui)
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Read-Version {
  param([string]$Path)
  if (!(Test-Path -LiteralPath $Path)) { return "V. 1.0" }
  $v = (Get-Content -Raw -LiteralPath $Path).Trim()
  if ([string]::IsNullOrWhiteSpace($v)) { return "V. 1.0" }
  return $v
}

function Write-Version {
  param([string]$Path, [string]$Value)
  Set-Content -LiteralPath $Path -Value $Value -NoNewline -Encoding UTF8
}

function Parse-Version {
  param([string]$v)

  if ($null -eq $v) { $v = "" }
  $t = $v.Trim()

  # Aceita:
  # V. 1.0
  # V. 1.02
  # V. 1.02.0003
  $re1 = '^(?:V\.\s*)?(?<maj>\d+)\.(?<min>\d{1,2})(?:\.(?<pat>\d{1,4}))?$'
  if ($t -notmatch $re1) {
    return @{ maj = 1; min = 0; pat = 0 }
  }

  $maj = [int]$Matches.maj
  $min = [int]$Matches.min
  $pat = 0
  if ($Matches.pat) { $pat = [int]$Matches.pat }

  return @{ maj = $maj; min = $min; pat = $pat }
}

function Format-Version {
  param([int]$maj, [int]$min, [int]$pat, [string]$kind)

  switch ($kind) {
    "major" { return ("V. {0}.{1}" -f $maj, $min) }              # V. 2.0
    "minor" { return ("V. {0}.{1:D2}" -f $maj, $min) }           # V. 1.02
    default { return ("V. {0}.{1:D2}.{2:D4}" -f $maj, $min, $pat) } # V. 1.02.0003
  }
}

function Detect-BumpFromMessage {
  param([string]$msg, [int]$fileCount)

  if ($null -eq $msg) { $msg = "" }
  $m = $msg.ToLowerInvariant()

  # Tags explícitas
  if ($m -match '\[(major|release)\]') { return "major" }
  if ($m -match '\[minor\]') { return "minor" }
  if ($m -match '\[(patch2000|refactor)\]') { return "patch2000" }
  if ($m -match '\[(patch8000|hotfix)\]') { return "patch8000" }
  if ($m -match '\[patch\]') { return "patch" }

  # Tipo do conventional commit
  if ($m -match '^\s*release(\([^)]+\))?!?:') { return "major" }
  if ($fileCount -gt 100) { return "minor" }

  if ($m -match '^\s*(feat|ux|refactor|perf)(\([^)]+\))?!?:') { return "minor" }
  return "patch"
}

function Get-NextVersionParts {
  param([hashtable]$p, [string]$bumpKind)

  $maj = [int]$p.maj
  $min = [int]$p.min
  $pat = [int]$p.pat

  switch ($bumpKind) {

    "major" {
      $maj += 1
      $min = 0
      $pat = 0
    }

    "minor" {
      $min += 1
      $pat = 0
    }

    "patch" {
      $pat += 1
    }

    "patch2000" {
      # entra no range 2000-2999 e incrementa dentro do range
      if ($pat -ge 2000 -and $pat -lt 2999) { $pat += 1 }
      else { $pat = 2000 }
    }

    "patch8000" {
      # entra no range 8000-8999 e incrementa dentro do range
      if ($pat -ge 8000 -and $pat -lt 8999) { $pat += 1 }
      else { $pat = 8000 }
    }

    default {
      $pat += 1
    }
  }

  return @{ maj = $maj; min = $min; pat = $pat }
}

function Update-Changelog {
  param(
    [string]$ChangelogPath,
    [string]$Version,
    [string]$CommitMessage
  )

  if ($null -eq $CommitMessage) { $CommitMessage = "" }
  $msg = $CommitMessage.Trim()
  if ([string]::IsNullOrWhiteSpace($msg)) { $msg = "chore: atualização" }

  # entry como string[]
  [string[]]$entry = @(
    "## $Version",
    "",
    "- $msg",
    ""
  )

  if (!(Test-Path -LiteralPath $ChangelogPath)) {
    [string[]]$header = @("# Changelog", "", "Registro de mudanças do projeto.", "")
    Set-Content -LiteralPath $ChangelogPath -Value (($header + $entry) -join "`n") -Encoding UTF8
    return
  }

  $existing = Get-Content -Raw -LiteralPath $ChangelogPath -Encoding UTF8

  # evita duplicar a mesma versão
  if ($existing -match ("(?m)^\#\#\s+" + [regex]::Escape($Version) + "\s*$")) {
    return
  }

  $lines = $existing -split "`r?`n"

  $out = New-Object 'System.Collections.Generic.List[string]'
  $out.AddRange([string[]]$lines)

  # inserir logo após o bloco do primeiro título
  $insertAt = 1
  for ($i=0; $i -lt $out.Count; $i++) {
    if ($out[$i] -match '^#\s+') { $insertAt = $i + 1; continue }
    if ($i -gt 0 -and $out[$i] -match '^\s*$') { $insertAt = $i + 1; break }
  }

  # CORREÇÃO: garantir IEnumerable[string]
  $out.InsertRange($insertAt, [string[]]$entry)

  Set-Content -LiteralPath $ChangelogPath -Value ($out -join "`n") -Encoding UTF8
}

# --- main ---
$current = Read-Version $VersionFile
$parsed  = Parse-Version $current

if ([string]::IsNullOrWhiteSpace($Bump)) {
  $Bump = Detect-BumpFromMessage -msg $CommitMessage -fileCount $FileCount
}

$nextParts = Get-NextVersionParts -p $parsed -bumpKind $Bump

# Formato final depende do tipo de bump:
# major -> V. X.0
# minor -> V. X.YY
# patch* -> V. X.YY.ZZZZ
$next = Format-Version -maj $nextParts.maj -min $nextParts.min -pat $nextParts.pat -kind $Bump

Write-Version -Path $VersionFile -Value $next
Update-Changelog -ChangelogPath $ChangelogPath -Version $next -CommitMessage $CommitMessage

Write-Host ("VERSION: {0} -> {1} ({2})" -f $current, $next, $Bump)
