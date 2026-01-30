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

function Normalize-Desc([string]$Text) {
  $t = $Text
  if ($null -eq $t) { $t = "" }
  $t = $t.Trim()
  $t = [regex]::Replace($t, "\s+", " ")
  $t = $t.TrimEnd(".", "!", "?")
  return $t.ToLowerInvariant()
}

function Impact-Rank([string]$Impact) {
  $i = $Impact
  if ($null -eq $i) { $i = "" }
  switch ($i.ToLowerInvariant()) {
    "nenhum" { return 0 }
    "baixo" { return 1 }
    "médio" { return 2 }
    "medio" { return 2 }
    "alto" { return 3 }
    default { return 1 }
  }
}

function Get-Impact([string]$LowerMsg, [string]$CommitType) {
  $m = [regex]::Match($LowerMsg, "\[impact:(nenhum|baixo|medio|m[eé]dio|alto)\]")
  if ($m.Success) {
    $val = $m.Groups[1].Value
    if ($val -eq "medio") { return "Médio" }
    if ($val -eq "médio") { return "Médio" }
    return ($val.Substring(0, 1).ToUpperInvariant() + $val.Substring(1))
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

$descKey = Normalize-Desc $desc

$entry = "[{0}][{1}] {2} (commit {3}) Impacto: {4}" -f $section, $scope, $desc, $CommitHash, $impact

$content = [System.IO.File]::ReadAllText($DraftPath)
if ($content -match [regex]::Escape("(commit $CommitHash)")) {
  exit 0
}

$lines = $content -split "`n", -1
$header = "## $section"
$out = New-Object System.Collections.Generic.List[string]
$inserted = $false
$skipNextBlank = $false
$didGroup = $false

for ($i = 0; $i -lt $lines.Length; $i++) {
  $line = $lines[$i].TrimEnd("`r")

  if ($skipNextBlank -and $line -eq "") {
    $skipNextBlank = $false
    continue
  }

  # Group identical entries (same section + scope + description) to reduce repetition.
  if (-not $didGroup -and $line -match '^- \[([^\]]+)\]\[([^\]]+)\]\s*') {
    $secInLine = $Matches[1]
    $scopeInLine = $Matches[2]

    if ($secInLine -eq $section -and $scopeInLine -eq $scope) {
      $prefixLen = $Matches[0].Length
      $impactIdx = $line.LastIndexOf(" Impacto:")
      if ($impactIdx -gt 0) {
        $commitIdx = $line.LastIndexOf(" (commit ")
        if ($commitIdx -lt 0) {
          $commitIdx = $line.LastIndexOf(" (commits ")
        }

        if ($commitIdx -gt $prefixLen) {
          $descInLine = $line.Substring($prefixLen, $commitIdx - $prefixLen).Trim()
          $descInLineKey = Normalize-Desc $descInLine
          if ($descInLineKey -eq $descKey -or $descInLineKey.StartsWith($descKey) -or $descKey.StartsWith($descInLineKey)) {
            $endParen = $line.IndexOf(")", $commitIdx)
            if ($endParen -gt $commitIdx) {
              $commitsRaw = $line.Substring($commitIdx, $endParen - $commitIdx)
              $commitsRaw = $commitsRaw -replace " \((commit|commits)\s+", ""

              $existing = @()
              foreach ($c in ($commitsRaw -split ",")) {
                $v = $c.Trim()
                if ($v) { $existing += $v }
              }

              if (-not ($existing -contains $CommitHash)) {
                $existing += $CommitHash
              }

              $existingImpact = $line.Substring($impactIdx + " Impacto:".Length).Trim()
              $finalImpact = $impact
              if ((Impact-Rank $existingImpact) -ge (Impact-Rank $finalImpact)) {
                $finalImpact = $existingImpact
              }

              $commitPart = " (commits " + ($existing -join ", ") + ")"
              $newLine = "- [{0}][{1}] {2}{3} Impacto: {4}" -f $section, $scope, $descInLine, $commitPart, $finalImpact

              $out.Add($newLine)
              $didGroup = $true
              continue
            }
          }
        }
      }
    }
  }

  $out.Add($line)

  if (-not $inserted -and $line -eq $header) {
    # Insert the entry at the top of the section, ensuring a single blank line after the header.
    $out.Add("")
    $out.Add("- $entry")
    if ($i + 1 -lt $lines.Length) {
      $next = $lines[$i + 1].TrimEnd("`r")
      if ($next -eq "") {
        $skipNextBlank = $true
      }
    }
    $inserted = $true
  }
}

if (-not $inserted) {
  throw "Section header not found in draft: $header"
}

Write-Utf8NoBomFile -Path $DraftPath -Content ($out -join "`n")
