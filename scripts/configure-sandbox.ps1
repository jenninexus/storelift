param(
    [string]$ClientId,
    [SecureString]$ClientSecret
)

$ErrorActionPreference = 'Stop'
$secretDirectory = Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) 'StoreLift/secrets'
$secretFile = Join-Path $secretDirectory 'sandbox.env'

if (Test-Path -LiteralPath $secretFile) {
    Write-Host "StoreLift already has a sandbox configuration at $secretFile."
    return
}

function Read-AppValue([string]$Prompt, [SecureString]$Protected) {
    if ($null -eq $Protected) {
        $Protected = Read-Host -Prompt $Prompt -AsSecureString
    }
    $buffer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($protected)
    try {
        $value = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($buffer).Trim()
        if ($value -notmatch '^[A-Za-z0-9._~+/=-]{12,256}$') {
            throw 'The value did not look like a sandbox app credential. Copy only the credential itself.'
        }
        return $value
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($buffer)
        $protected.Dispose()
    }
}

Write-Host 'Use the StoreLift app under the PayPal Developer Dashboard Sandbox tab. Input is hidden.'
if ([string]::IsNullOrWhiteSpace($ClientId)) {
    $ClientId = Read-AppValue 'Sandbox Client ID'
}
$ClientId = $ClientId.Trim()
if ($ClientId -notmatch '^[A-Za-z0-9._~+/=-]{12,256}$') {
    throw 'Enter the Client ID from the Sandbox app.'
}
$secretValue = Read-AppValue 'Sandbox Secret' $ClientSecret
$downloadKey = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()

$probeValues = @{
    PAYPAL_ENV = 'sandbox'
    PAYPAL_SANDBOX_CLIENT_ID = $clientId
    PAYPAL_SANDBOX_CLIENT_SECRET = $secretValue
}
$previousValues = @{}
try {
    foreach ($name in $probeValues.Keys) {
        $previousValues[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $probeValues[$name], 'Process')
    }
    & php (Join-Path $PSScriptRoot 'check-paypal.php')
    if ($LASTEXITCODE -ne 0) {
        throw 'Sandbox verification failed. Check the Sandbox tab and use the credentials shown there.'
    }
} finally {
    foreach ($name in $previousValues.Keys) {
        if ($null -eq $previousValues[$name]) {
            Remove-Item -LiteralPath ('Env:' + $name) -ErrorAction SilentlyContinue
        } else {
            [Environment]::SetEnvironmentVariable($name, $previousValues[$name], 'Process')
        }
    }
}

[IO.Directory]::CreateDirectory($secretDirectory) | Out-Null
$stream = [IO.File]::Open($secretFile, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
try {
    $writer = [IO.StreamWriter]::new($stream, [Text.UTF8Encoding]::new($false), 1024, $true)
    try {
        $writer.WriteLine('PAYPAL_ENV=sandbox')
        $writer.WriteLine('PAYPAL_SANDBOX_CLIENT_ID="' + $clientId + '"')
        $writer.WriteLine('PAYPAL_SANDBOX_CLIENT_SECRET="' + $secretValue + '"')
        $writer.WriteLine('STORELIFT_DOWNLOAD_KEY=' + $downloadKey)
    } finally {
        $writer.Dispose()
    }
} finally {
    $stream.Dispose()
}

Write-Host 'Sandbox app credentials saved to the StoreLift profile. Tell Codex setup is done.'
