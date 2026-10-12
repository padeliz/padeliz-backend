# Enable Compose profile netopia-ipn-relay when the sibling relay repo exists
# and NETOPIA_IS_LIVE is not truthy. Must match Padeliz\NetopiaIpnRelay\ComposeProfileDecision.
$ErrorActionPreference = 'Stop'

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DevcontainerDir = Split-Path -Parent $ScriptDir
$BackendDir = Split-Path -Parent $DevcontainerDir
$MetaDir = Split-Path -Parent $BackendDir
$RelayDir = Join-Path $MetaDir 'padeliz-netopia-ipn-relay'
$EnvFile = Join-Path $BackendDir '.env'
$ComposeEnv = Join-Path $DevcontainerDir '.env'
$ProfileName = 'netopia-ipn-relay'

function Test-IsLiveTruthy {
    param([string] $Value)
    $normalized = ($Value -replace '\s', '').ToLowerInvariant()
    return @('true', '1', 'yes') -contains $normalized
}

function Read-NetopiaIsLive {
    if (-not (Test-Path -LiteralPath $EnvFile)) {
        return ''
    }
    foreach ($line in Get-Content -LiteralPath $EnvFile) {
        if ($line.StartsWith('NETOPIA_IS_LIVE=')) {
            return $line.Substring('NETOPIA_IS_LIVE='.Length)
        }
    }
    return ''
}

$repoExists = Test-Path -LiteralPath $RelayDir -PathType Container
$liveValue = Read-NetopiaIsLive
$enable = $repoExists -and -not (Test-IsLiveTruthy -Value $liveValue)

$lines = [System.Collections.Generic.List[string]]::new()
if (Test-Path -LiteralPath $ComposeEnv) {
    foreach ($line in Get-Content -LiteralPath $ComposeEnv) {
        if (-not $line.StartsWith('COMPOSE_PROFILES=')) {
            $lines.Add($line)
        }
    }
}

if ($enable) {
    $lines.Add("COMPOSE_PROFILES=$ProfileName")
    Write-Host "sync-netopia-ipn-relay-profile: enabled profile '$ProfileName' (repo present, NETOPIA_IS_LIVE not live)"
} else {
    $repoFlag = if ($repoExists) { 1 } else { 0 }
    Write-Host "sync-netopia-ipn-relay-profile: profile disabled (repo_exists=$repoFlag NETOPIA_IS_LIVE='$liveValue')"
}

$content = if ($lines.Count -eq 0) { '' } else { ($lines -join "`n") + "`n" }
[System.IO.File]::WriteAllText($ComposeEnv, $content)
