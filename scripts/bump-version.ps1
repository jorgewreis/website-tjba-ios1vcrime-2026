param(
  [string]$Path = "VERSION",
  [string]$CommitMessage = ""
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
$bumpPatch2000 = $commit -match '\[patch2000\]'
$bumpPatch8000 = $commit -match '\[patch8000\]'
$bumpPatch = $commit -match '\[patch\]'

if ($bumpMajor) {
  $major += 1
  $minor = 0
  $patch = 0
} elseif ($bumpMinor) {
  $minor += 1
  $patch = 0
} else {
  $rangeStart = 1
  if ($bumpPatch8000) {
    $rangeStart = 8000
  } elseif ($bumpPatch2000) {
    $rangeStart = 2000
  } elseif ($bumpPatch) {
    $rangeStart = 1
  }

  if ($patch -lt $rangeStart) {
    $patch = $rangeStart
  } else {
    $patch += 1
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
