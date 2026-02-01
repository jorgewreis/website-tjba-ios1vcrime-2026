param(
  [string]$Path = "VERSION",
  [string]$CommitMessage = "",

  # Compatibility with post-commit:
  # major | release | minor | patch | patch2000 | patch8000
  [string]$Bump = "",
  [int]$FileCount = 0,

  # Paths (can be overridden if repo layout changes)
  [string]$ChangelogPath = "CHANGELOG.md",
  [string]$ReleaseRoot = "docs/90-releases/notes"
)

# -----------------------------
# Version parsing / formatting
# -----------------------------
# Accepted inputs:
#   V. 1.0
#   V. 1.04
#   V. 1.04.0123
# Also accepts legacy:
#   V 1.00.0000
#   V 1.04.0000
# And "V" without dot: "V 1.04"
function Parse-Version([string]$raw) {
  $raw = ($raw ?? "").Trim()

  # Normalize any multiple spaces
  $raw = ($raw -replace '\s+', ' ')

  # Regex:
  # V or V. then whitespace
  # major: digits
  # dot
  # minor: 1-2 digits (we'll store as int; formatting will enforce 2 digits when minor>0)
  # optional: dot + patch (1-4 digits)
  if ($raw -notmatch '^V\.?\s+(\d+)\.(\d{1,2})(?:\.(\d{1,4}))?$') {
    throw "VERSION inválida: '$raw'. Esperado: 'V. X.YY.ZZZZ' (ou variações: 'V. X.0', 'V. X.YY', 'V X.YY.ZZZZ')."
  }

  $major = [int]$Matches[1]
  $minor = [int]$Matches[2]
  $patch = 0
  if ($Matches[3]) { $patch = [int]$Matches[3] }

  return [pscustomobject]@{
    Major = $major
    Minor = $minor
    Patch = $patch
  }
}

# Output rules:
# - release new: V. {major}.0  (minor=0, patch=0)
# - patch zero:  V. {major}.{minor:00} (patch=0, minor>0)
# - patch > 0:   V. {major}.{minor:00}.{patch:0000}
function Format-Version([int]$major, [int]$minor, [int]$patch) {
  if ($minor -eq 0 -and $patch -eq 0) {
    return ("V. {0}.0" -f $major)
  }
  if ($patch -eq 0) {
    return ("V. {0}.{1:00}" -f $major, $minor)
  }
  return ("V. {0}.{1:00}.{2:0000}" -f $major, $minor, $patch)
}

function Read-Version([string]$path) {
  if (-not (Test-Path -LiteralPath $path)) {
    # Default new repo version
    return "V. 1.0"
  }
  return (Get-Content -LiteralPath $path -Raw).Trim()
}

function Write-Version([string]$path, [string]$value) {
  Set-Content -Encoding ascii -LiteralPath $path -Value $value
}

function Ensure-Exists([string]$path) {
  $dir = Split-Path -Parent $path
  if ($dir -and -not (Test-Path -LiteralPath $dir)) {
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
  }
}

# -----------------------------
# Commit parsing / bump resolve
# -----------------------------
function Get-CommitType([string]$msg) {
  if ([string]::IsNullOrWhiteSpace($msg)) { return "patch" }
  $first = ($msg -split "`n")[0].Trim()
  if ($first -match '^([a-z]+)(\([^)]+\))?:') { return $Matches[1].ToLowerInvariant() }
  return "patch"
}

function Has-Tag([string]$msg, [string]$tag) {
  if ([string]::IsNullOrWhiteSpace($msg)) { return $false }
  return ($msg.ToLowerInvariant().Contains("[$tag]"))
}

function Normalize-Bump([string]$b) {
  if ([string]::IsNullOrWhiteSpace($b)) { return "" }
  return $b.Trim().ToLowerInvariant()
}

function Next-PatchInRange([int]$currentPatch, [string]$kind) {
  # Ranges:
  # patch      => 0001-1999
  # patch2000  => 2000-7999
  # patch8000  => 8000-9999
  switch ($kind) {
    "patch2000" {
      if ($currentPatch -lt 2000) { return 2000 }
      if ($currentPatch -ge 7999) { return 2000 }
      return ($currentPatch + 1)
    }
    "patch8000" {
      if ($currentPatch -lt 8000) { return 8000 }
      if ($currentPatch -ge 9999) { return 8000 }
      return ($currentPatch + 1)
    }
    default {
      if ($currentPatch -lt 1) { return 1 }
      if ($currentPatch -ge 1999) { return 1 }
      return ($currentPatch + 1)
    }
  }
}

# CHANGELOG should index notes (only for MINOR and MAJOR)
function Update-ChangelogLink([string]$changelogPath, [string]$releaseRoot, [string]$versionText) {
  if (-not (Test-Path -LiteralPath $changelogPath)) { return }

  # Convert "V. 1.04" -> "v1.04"
  # Convert "V. 1.0"  -> "v1.0"
  $verSlug = $versionText.Trim()
  $verSlug = ($verSlug -replace '^V\.?\s+', 'v')

  $line = "- [Versão $verSlug]($releaseRoot/$verSlug.md)"

  $changelog = Get-Content -LiteralPath $changelogPath -Raw
  if ($changelog -notmatch [regex]::Escape($line)) {
    $changelog = $changelog.TrimEnd() + "`n" + $line + "`n"
    Set-Content -Encoding utf8 -LiteralPath $changelogPath -Value $changelog
  }
}

# -----------------------------
# Main
# -----------------------------
$path = $Path
Ensure-Exists $path

$currentRaw = Read-Version $path
$current = Parse-Version $currentRaw

$commitType = Get-CommitType $CommitMessage
$lowerMsg = ($CommitMessage ?? "").ToLowerInvariant()

# Resolve bump:
# - If -Bump provided => take precedence (post-commit already resolved it).
# - Else resolve from tags and heuristics.
$bumpResolved = Normalize-Bump $Bump

if ([string]::IsNullOrWhiteSpace($bumpResolved)) {
  # Tags first
  if (Has-Tag $lowerMsg "major" -or Has-Tag $lowerMsg "release") { $bumpResolved = "major" }
  elseif (Has-Tag $lowerMsg "minor") { $bumpResolved = "minor" }
  elseif (Has-Tag $lowerMsg "patch2000" -or Has-Tag $lowerMsg "refactor") { $bumpResolved = "patch2000" }
  elseif (Has-Tag $lowerMsg "patch8000" -or Has-Tag $lowerMsg "hotfix") { $bumpResolved = "patch8000" }
  elseif (Has-Tag $lowerMsg "patch") { $bumpResolved = "patch" }
  else {
    # Heuristics
    if ($commitType -in @("feat","ux","refactor","perf")) { $bumpResolved = "minor" }
    elseif ($commitType -eq "release") { $bumpResolved = "major" }
    else { $bumpResolved = "patch" }
  }
}

# Normalize bump: treat "release" as major as per your rule
if ($bumpResolved -eq "release") { $bumpResolved = "major" }

# Force MINOR if huge change and bump isn't explicitly major/minor
if ($bumpResolved -in @("patch","patch2000","patch8000")) {
  $fc = 0
  if ($FileCount -gt 0) { $fc = $FileCount }
  else {
    # fallback: staged files count (best effort)
    try {
      $staged = (git diff --cached --name-only 2>$null)
      if ($staged) {
        $fc = ($staged | Where-Object { $_ -and $_.Trim() -ne "" } | Measure-Object).Count
      }
    } catch { $fc = 0 }
  }

  if ($fc -gt 100) {
    $bumpResolved = "minor"
  }
}

# Apply bump
$major = $current.Major
$minor = $current.Minor
$patch = $current.Patch

$didMajor = $false
$didMinor = $false

if ($bumpResolved -eq "major") {
  # MAJOR only when explicitly major/release resolved upstream
  $major += 1
  $minor = 0
  $patch = 0
  $didMajor = $true
}
elseif ($bumpResolved -eq "minor") {
  $minor += 1
  $patch = 0
  $didMinor = $true
}
else {
  $patch = Next-PatchInRange $patch $bumpResolved
}

$newVersion = Format-Version $major $minor $patch
Write-Version $path $newVersion

# Update CHANGELOG link only for MINOR/MAJOR (notes live in ReleaseRoot)
if (($didMajor -or $didMinor) -and (Test-Path -LiteralPath $ChangelogPath)) {
  Update-ChangelogLink $ChangelogPath $ReleaseRoot $newVersion
}
