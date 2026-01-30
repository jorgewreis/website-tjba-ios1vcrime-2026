param(
  [string]$DraftPath = "docs/releases/_draft_minor.md",
  [string]$CommitHash = "",
  [string]$CommitMessage = "",
  [string]$Bump = ""
)

function Write-Utf8NoBomFile([string]$Path, [string]$Content) {
  $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
  # Trim trailing whitespace/blank lines and ensure exactly one trailing newline.
  $normalized = [regex]::Replace($Content, "\s+\z", "") + "`n"
  [System.IO.File]::WriteAllText($Path, $normalized, $utf8NoBom)
}

function Get-Impact([string]$LowerMsg, [string]$CommitType) {
  $m = [regex]::Match($LowerMsg, "\[impact:(nenhum|baixo|medio|m[eé]dio|alto)\]")
  if ($m.Success) {
    $val = $m.Groups[1].Value
    if ($val -eq "médio") { return "Médio" }
    if ($val -eq "medio") { return "Médio" }
    return ($val.Substring(0,1).ToUpperInvariant() + $val.Substring(1))
  }

  switch ($CommitType) {
    "docs" { return "Nenhum" }
    "style" { return "Nenhum" }
    "test" { return "Nenhum" }
    "ci" { return "Nenhum" }
    "build" { return "Nenhum" }
    "chore" { return "Nenhum" }
    "config" { return "Nenhum" }
    default { return "Baixo" }
  }
}

function Get-Section([string]$CommitType, [string]$LowerMsg, [string]$Bump) {
  if ($LowerMsg -match "\[security\]") { return "Security" }
  if ($LowerMsg -match "\[deprecated\]") { return "Deprecated" }
  if ($LowerMsg -match "\[removed\]") { return "Removed" }

  if ($Bump -eq "patch8000") { return "Fixed" }
  if ($Bump -eq "patch2000") { return "Changed" }

  switch ($CommitType) {
    "feat" { return "Added" }
    "fix" { return "Fixed" }
    "hotfix" { return "Fixed" }
    "refactor" { return "Changed" }
    "perf" { return "Changed" }
    default { return "Changed" }
  }
}

function Get-Scope([string]$CommitType, [string]$ParsedScope) {
  if ($ParsedScope) { return $ParsedScope }
  switch ($CommitType) {
    "docs" { return "Docs" }
    "ci" { return "CI" }
    "build" { return "Build" }
    "config" { return "Config" }
    "style" { return "UI" }
    default { return "Geral" }
  }
}

if (-not (Test-Path -Path $DraftPath)) {
  throw "Draft file not found: $DraftPath"
}

if (-not $CommitHash) {
  throw "CommitHash is required"
}

$msgSafe = $CommitMessage
if ($null -eq $msgSafe) { $msgSafe = "" }
$lowerMsg = $msgSafe.ToLowerInvariant()
if ($lowerMsg -match "\[skip draft\]" -or $lowerMsg -match "\[skip-draft\]" -or $lowerMsg -match "\[skip release-notes\]") {
  exit 0
}

$firstLine = ($CommitMessage -split "`r?`n", 2)[0]
$m = [regex]::Match($firstLine, "^([a-z]+)(?:\(([^)]+)\))?:\s*(.+)$")
$commitType = "patch"
$scopeFromMsg = ""
$desc = $firstLine
if ($m.Success) {
  $commitType = $m.Groups[1].Value.ToLowerInvariant()
  $scopeFromMsg = $m.Groups[2].Value
  $desc = $m.Groups[3].Value
}

# Remove bracket-tags from the description to keep it clean in release notes.
$desc = [regex]::Replace($desc, "\[[^\]]+\]", "").Trim()
if (-not $desc) { $desc = $firstLine.Trim() }
if ($desc -notmatch "[\.\!\?]$") { $desc = $desc + "." }

$section = Get-Section -CommitType $commitType -LowerMsg $lowerMsg -Bump $Bump
$scope = Get-Scope -CommitType $commitType -ParsedScope $scopeFromMsg
$impact = Get-Impact -LowerMsg $lowerMsg -CommitType $commitType

$entry = "[{0}][{1}] {2} (commit {3}) Impacto: {4}" -f $section, $scope, $desc, $CommitHash, $impact

$content = [System.IO.File]::ReadAllText($DraftPath)
if ($content -match [regex]::Escape("(commit $CommitHash)")) {
  exit 0
}

$lines = $content -split "`n", -1
$header = "## $section"
$out = New-Object System.Collections.Generic.List[string]
$inserted = $false

for ($i = 0; $i -lt $lines.Length; $i++) {
  $line = $lines[$i].TrimEnd("`r")
  $out.Add($line)

  if (-not $inserted -and $line -eq $header) {
    # Ensure there is a blank line after the header, then insert entry at top of section.
    if ($i + 1 -lt $lines.Length) {
      $next = $lines[$i + 1].TrimEnd("`r")
      if ($next -ne "") {
        $out.Add("")
      }
    } else {
      $out.Add("")
    }
    $out.Add("- $entry")
    $inserted = $true
  }
}

if (-not $inserted) {
  throw "Section header not found in draft: $header"
}

Write-Utf8NoBomFile -Path $DraftPath -Content ($out -join "`n")
