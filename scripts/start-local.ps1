param([ValidateRange(1024, 65535)][int]$Port = 8015)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $repo
try {
    & php scripts/build-demo-kit.php
    if ($LASTEXITCODE -ne 0) { throw 'The demo kit could not be built.' }
    Write-Host "StoreLift Sandbox is at http://127.0.0.1:$Port/ (Ctrl+C stops this server)."
    & php -d max_execution_time=60 -S "127.0.0.1:$Port" -t public public/index.php
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
