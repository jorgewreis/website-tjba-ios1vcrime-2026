param(
  [string]$Path = "VERSION",
  [string]$CommitMessage = "",
  [string]$Bump = "",
  [int]$FileCount = 0
)

if (-not (Test-Path -Path $Path)) {
  throw "VERSION file not found at: $Path"
}

$raw = (Get-Content -Raw -Path $Path).Trim()
$match = [regex]::Match($raw, '^V\s+(\d+)\.(\d{2})\.(\d{4})$')
if (-not $match.Success) {
  throw "Invalid VERSION format. Expected: 'V 1.00.0000'. Found: '$raw'"
}

$major = [int]$match.Groups[1].Value
$minor = [int]$match.Groups[2].Value
$patch = [int]$match.Groups[3].Value

$commit = $CommitMessage.ToLowerInvariant()
$bumpMajor = $commit -match '\[major\]'
$bumpMinor = $commit -match '\[minor\]'
$bumpPatch2000 = $commit -match '\[patch2000\]' -or $commit -match '\[refactor\]'
$bumpPatch8000 = $commit -match '\[patch8000\]' -or $commit -match '\[hotfix\]'
$bumpPatch = $commit -match '\[patch\]'

$firstLine = ($CommitMessage -split "`r?`n", 2)[0]
$typeMatch = [regex]::Match($firstLine, '^([a-z]+)(\([^)]+\))?:')
$commitType = ""
if ($typeMatch.Success) {
  $commitType = $typeMatch.Groups[1].Value.ToLowerInvariant()
}

$forceMinor = ($FileCount -gt 100)

$resolvedBump = $Bump
if (-not $resolvedBump) {
  if ($bumpMajor -or $commitType -eq "release") {
    $resolvedBump = "major"
  } elseif ($bumpMinor -or $forceMinor -or ($commitType -in @("feat", "ux", "refactor", "perf"))) {
    $resolvedBump = "minor"
  } elseif ($bumpPatch8000) {
    $resolvedBump = "patch8000"
  } elseif ($bumpPatch2000) {
    $resolvedBump = "patch2000"
  } else {
    $resolvedBump = "patch"
  }
}

switch ($resolvedBump) {
  "major" {
    $major += 1
    $minor = 0
    $patch = 0
  }
  "minor" {
    $minor += 1
    $patch = 0
  }
  "patch2000" {
    if ($patch -lt 2000) { $patch = 2000 } else { $patch += 1 }
  }
  "patch8000" {
    if ($patch -lt 8000) { $patch = 8000 } else { $patch += 1 }
  }
  default {
    if ($patch -lt 1) { $patch = 1 } else { $patch += 1 }
  }
}

if ($minor -gt 99) {
  throw "MINOR overflow: $minor"
}
if ($patch -gt 9999) {
  throw "PATCH/BUILD overflow: $patch"
}

$newVersion = "V {0}.{1:00}.{2:0000}" -f $major, $minor, $patch
Set-Content -Encoding utf8 -Path $Path -Value ($newVersion + "`n")
Write-Host $newVersion

if ($resolvedBump -eq "minor") {
  $releaseDir = "docs/releases"
  if (-not (Test-Path -Path $releaseDir)) {
    New-Item -ItemType Directory -Force -Path $releaseDir | Out-Null
  }

  $draftPath = Join-Path $releaseDir "_draft_minor.md"
  $releaseFile = Join-Path $releaseDir ("v{0}.{1:00}.md" -f $major, $minor)
  $today = Get-Date -Format "yyyy-MM-dd"

  $draftContent = ""
  if (Test-Path -Path $draftPath) {
    $draftContent = Get-Content -Raw -Path $draftPath
  }

  $sectionsStart = $draftContent.IndexOf("## Added")
  if ($sectionsStart -ge 0) {
    $sections = $draftContent.Substring($sectionsStart).Trim()
  } else {
    $sections = @"
## Added

## Changed

## Fixed

## Security

## Deprecated

## Removed

## Bugs conhecidos (para versao futura)

## Proximas implementacoes (sugestoes)
"@.Trim()
  }

  if ($sections -notmatch "(?m)^## Notas de compatibilidade") {
    $sections = $sections + "`n`n## Notas de compatibilidade`n"
  }
  if ($sections -notmatch "(?m)^## Notas de migracao") {
    $sections = $sections + "`n`n## Notas de migracao`n"
  }

  $releaseContent = @"
# Versao {0}.{1:00}.0000 - {2}

## Resumo

- (preencher)

{3}
"@ -f $major, $minor, $today, $sections

  Set-Content -Encoding utf8 -Path $releaseFile -Value $releaseContent

  $draftTemplate = @"
# Draft da proxima minor

> Este arquivo e cumulativo e recebe entradas de patch enquanto a proxima MINOR esta em desenvolvimento.

## Added

## Changed

## Fixed

## Security

## Deprecated

## Removed

## Bugs conhecidos (para versao futura)

## Proximas implementacoes (sugestoes)
"@
  Set-Content -Encoding utf8 -Path $draftPath -Value $draftTemplate

  $changelogPath = "CHANGELOG.md"
  if (Test-Path -Path $changelogPath) {
    $changelog = Get-Content -Raw -Path $changelogPath
    $entry = "- [Versao {0}.{1:00}.0000](docs/releases/v{0}.{1:00}.md)" -f $major, $minor
    if ($changelog -notmatch [regex]::Escape($entry)) {
      $changelog = $changelog.TrimEnd() + "`n" + $entry + "`n"
      Set-Content -Encoding utf8 -Path $changelogPath -Value $changelog
    }
  }
}
