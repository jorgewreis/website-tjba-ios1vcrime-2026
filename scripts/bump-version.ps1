param(
  [string]$CommitMessage,
  [ValidateSet("major","minor","patch","patch2000","patch8000")]
  [string]$Bump = "",
  [int]$FileCount = 0,
  [string]$VersionFile = "VERSION",
  [string]$ChangelogPath = "CHANGELOG.md",
  [string]$DraftMinorPath = "docs/90-releases/_draft_minor.md", # mantido por compat; não usado aqui
  [string]$NotesDir = "docs/90-releases/notes"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Read-Version {
  param([string]$Path)
  if (!(Test-Path -LiteralPath $Path)) { return "V. 0.0" }
  $v = (Get-Content -Raw -LiteralPath $Path -Encoding UTF8).Trim()
  if ([string]::IsNullOrWhiteSpace($v)) { return "V. 0.0" }
  return $v
}

function Write-Version {
  param([string]$Path, [string]$Value)
  Set-Content -LiteralPath $Path -Value $Value -NoNewline -Encoding UTF8
}

function Parse-Version {
  param([string]$v)

  # Aceita:
  # - V. 1.0
  # - V. 1.02
  # - V. 1.02.0003
  $rx = '^(?:V\.\s*)?(?<maj>\d+)\.(?<min>\d{1,2})(?:\.(?<pat>\d{1,4}))?$'
  if ($v -notmatch $rx) {
    return @{ major = 0; minor = 0; patch = 0 }
  }

  $maj = [int]$Matches.maj
  $min = [int]$Matches.min
  $pat = 0
  if ($Matches.pat) { $pat = [int]$Matches.pat }

  return @{ major = $maj; minor = $min; patch = $pat }
}

function Detect-BumpFromMessage {
  param([string]$msg, [int]$fileCount)

  if ($null -eq $msg) { $msg = "" }
  $m = $msg.ToLowerInvariant()

  if ($m -match '\[(major|release)\]') { return "major" }
  if ($m -match '\[minor\]') { return "minor" }
  if ($m -match '\[(patch2000|refactor)\]') { return "patch2000" }
  if ($m -match '\[(patch8000|hotfix)\]') { return "patch8000" }
  if ($m -match '\[patch\]') { return "patch" }

  if ($m -match '^\s*release(\([^)]+\))?!?:') { return "major" }
  if ($fileCount -gt 100) { return "minor" }

  if ($m -match '^\s*(feat|ux|refactor|perf)(\([^)]+\))?!?:') { return "minor" }
  return "patch"
}

function Get-NextVersionParts {
  param(
    [hashtable]$Parsed,
    [string]$BumpKind
  )

  $major = [int]$Parsed.major
  $minor = [int]$Parsed.minor
  $patch = [int]$Parsed.patch

  switch ($BumpKind) {
    "major" {
      # REGRA: ao incrementar MAJOR, zerar MINOR e PATCH
      $major += 1
      $minor = 0
      $patch = 0
    }

    "minor" {
      # REGRA: ao incrementar MINOR, zerar PATCH
      $minor += 1
      if ($minor -gt 99) { $minor = 99 }  # trava simples; se quiser rollover, eu ajusto
      $patch = 0
    }

    "patch" {
      $patch += 1
      if ($patch -gt 9999) { $patch = 9999 }
    }

    "patch2000" {
      # Mantém patch dentro de 2000-2999 (útil p/ refactor)
      if ($patch -ge 2000 -and $patch -lt 2999) { $patch += 1 }
      else { $patch = 2000 }
    }

    "patch8000" {
      # Mantém patch dentro de 8000-8999 (útil p/ hotfix)
      if ($patch -ge 8000 -and $patch -lt 8999) { $patch += 1 }
      else { $patch = 8000 }
    }

    default {
      $patch += 1
      if ($patch -gt 9999) { $patch = 9999 }
    }
  }

  return @{ major = $major; minor = $minor; patch = $patch }
}

function Format-Version {
  param(
    [int]$Major,
    [int]$Minor,
    [int]$Patch,
    [string]$BumpKind
  )

  # Formatos:
  # major => V. X.0
  # minor => V. X.YY
  # patch e variantes => V. X.YY.PPPP
  switch ($BumpKind) {
    "major" { return ("V. {0}.0" -f $Major) }
    "minor" { return ("V. {0}.{1}" -f $Major, $Minor.ToString("D2")) }
    default { return ("V. {0}.{1}.{2}" -f $Major, $Minor.ToString("D2"), $Patch.ToString("D4")) }
  }
}

function Ensure-NotesDir {
  param([string]$dir)
  if (!(Test-Path -LiteralPath $dir)) {
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
  }
}

function Append-NoteFile {
  param(
    [string]$NotesDir,
    [string]$Version,
    [string]$CommitMessage
  )

  Ensure-NotesDir $NotesDir

  $yyyy = (Get-Date).ToString("yyyy")
  $mm = (Get-Date).ToString("MM")
  $dd = (Get-Date).ToString("dd")

  $hash = ""
  try { $hash = (git rev-parse --short HEAD 2>$null).Trim() } catch { $hash = "" }
  $safeHash = if ([string]::IsNullOrWhiteSpace($hash)) { "local" } else { $hash }

  $file = Join-Path $NotesDir ("{0}-{1}-{2}_{3}.md" -f $yyyy, $mm, $dd, $safeHash)

  if ($null -eq $CommitMessage) { $CommitMessage = "" }
  $line1 = $CommitMessage.Trim()
  if ([string]::IsNullOrWhiteSpace($line1)) { $line1 = "chore: atualização" }

  $content = @(
    "## $Version",
    "",
    "- $line1",
    ""
  )

  if (!(Test-Path -LiteralPath $file)) {
    Set-Content -LiteralPath $file -Value ($content -join "`n") -Encoding UTF8
  } else {
    Add-Content -LiteralPath $file -Value ($content -join "`n") -Encoding UTF8
  }
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

  $entry = @(
    "## $Version",
    "",
    ("- " + $msg),
    ""
  )

  if (!(Test-Path -LiteralPath $ChangelogPath)) {
    $header = @("# Changelog", "", "Registro de mudanças do projeto.", "")
    Set-Content -LiteralPath $ChangelogPath -Value (($header + $entry) -join "`n") -Encoding UTF8
    return
  }

  $existing = Get-Content -Raw -LiteralPath $ChangelogPath -Encoding UTF8

  # Evita duplicar a mesma versão
  if ($existing -match ("(?m)^\#\#\s+" + [regex]::Escape($Version) + "\s*$")) {
    return
  }

  $lines = $existing -split "`r?`n"
  $out = New-Object System.Collections.Generic.List[string]
  $out.AddRange($lines)

  $insertAt = 1
  for ($i = 0; $i -lt $out.Count; $i++) {
    if ($out[$i] -match '^#\s+') { $insertAt = $i + 1; continue }
    if ($i -gt 0 -and $out[$i] -match '^\s*$') { $insertAt = $i + 1; break }
  }

  $out.InsertRange($insertAt, $entry)
  Set-Content -LiteralPath $ChangelogPath -Value ($out -join "`n") -Encoding UTF8
}

# --- main ---

$current = Read-Version $VersionFile

if ([string]::IsNullOrWhiteSpace($Bump)) {
  $Bump = Detect-BumpFromMessage -msg $CommitMessage -fileCount $FileCount
}

$parsed = Parse-Version $current
$nextParts = Get-NextVersionParts -Parsed $parsed -BumpKind $Bump
$next = Format-Version -Major $nextParts.major -Minor $nextParts.minor -Patch $nextParts.patch -BumpKind $Bump

Write-Version -Path $VersionFile -Value $next
Update-Changelog -ChangelogPath $ChangelogPath -Version $next -CommitMessage $CommitMessage
Append-NoteFile  -NotesDir $NotesDir -Version $next -CommitMessage $CommitMessage

Write-Host ("VERSION: {0} -> {1} ({2})" -f $current, $next, $Bump)
