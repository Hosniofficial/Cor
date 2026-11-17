$ErrorActionPreference = 'Stop'

$tlsDirectory = Join-Path $env:LOCALAPPDATA 'CAESER\local-tls'
$certificateFile = Join-Path $tlsDirectory 'localhost.crt'
$keyFile = Join-Path $tlsDirectory 'localhost.key'
$opensslCandidates = @(
    'C:\xampp\apache\bin\openssl.exe',
    'C:\Program Files\Git\usr\bin\openssl.exe'
)
$openssl = $opensslCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $openssl) {
    $opensslCommand = Get-Command openssl.exe -ErrorAction SilentlyContinue
    if ($opensslCommand) {
        $openssl = $opensslCommand.Source
    }
}
if (-not $openssl) {
    throw 'OpenSSL was not found. Install OpenSSL or XAMPP, then run this script again.'
}
$opensslConfigCandidates = @(
    'C:\xampp\apache\conf\openssl.cnf',
    (Join-Path (Split-Path -Parent $openssl) '..\conf\openssl.cnf')
)
$opensslConfig = $opensslConfigCandidates |
    Where-Object { Test-Path -LiteralPath $_ } |
    Select-Object -First 1
if (-not $opensslConfig -and $env:OPENSSL_CONF -and (Test-Path -LiteralPath $env:OPENSSL_CONF)) {
    $opensslConfig = $env:OPENSSL_CONF
}
if (-not $opensslConfig) {
    throw 'OpenSSL configuration file was not found.'
}

New-Item -ItemType Directory -Path $tlsDirectory -Force | Out-Null
$hasCertificate = Test-Path -LiteralPath $certificateFile
$hasKey = Test-Path -LiteralPath $keyFile
if ($hasCertificate -ne $hasKey) {
    throw "Incomplete local TLS setup in $tlsDirectory. Keep both localhost.crt and localhost.key, or remove both before rerunning."
}

if (-not $hasCertificate) {
    $temporaryId = [Guid]::NewGuid().ToString('N')
    $temporaryCertificate = Join-Path $tlsDirectory "localhost-$temporaryId.crt"
    $temporaryKey = Join-Path $tlsDirectory "localhost-$temporaryId.key"
    try {
        & $openssl req -config $opensslConfig -x509 -newkey rsa:2048 -nodes -sha256 -days 825 -batch `
            -keyout $temporaryKey `
            -out $temporaryCertificate `
            -subj '/CN=localhost' `
            -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' `
            -addext 'extendedKeyUsage=serverAuth' `
            -addext 'keyUsage=digitalSignature,keyEncipherment'
        if ($LASTEXITCODE -ne 0) {
            throw 'OpenSSL could not create the local TLS certificate.'
        }

        Move-Item -LiteralPath $temporaryCertificate -Destination $certificateFile
        Move-Item -LiteralPath $temporaryKey -Destination $keyFile
    } finally {
        if (Test-Path -LiteralPath $temporaryCertificate) {
            Remove-Item -LiteralPath $temporaryCertificate -Force
        }
        if (Test-Path -LiteralPath $temporaryKey) {
            Remove-Item -LiteralPath $temporaryKey -Force
        }
    }
}

& $openssl x509 -in $certificateFile -checkhost localhost -noout
if ($LASTEXITCODE -ne 0) {
    throw 'The local TLS certificate is not valid for localhost.'
}

$certificatePublicKey = (& $openssl x509 -in $certificateFile -pubkey -noout) -join "`n"
if ($LASTEXITCODE -ne 0) {
    throw 'Could not read the public key from the local TLS certificate.'
}
$privateKeyPublicKey = (& $openssl pkey -in $keyFile -pubout) -join "`n"
if ($LASTEXITCODE -ne 0) {
    throw 'Could not read the public key from the local TLS private key.'
}
if ($certificatePublicKey.Trim() -ne $privateKeyPublicKey.Trim()) {
    throw 'The local TLS certificate and private key do not match.'
}

$currentUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $keyFile /inheritance:r /grant:r "${currentUser}:(F)" 'NT AUTHORITY\SYSTEM:(F)' | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not restrict access to the local TLS private key.'
}

$certificate = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($certificateFile)
$trustedCertificate = Get-ChildItem Cert:\CurrentUser\Root | Where-Object { $_.Thumbprint -eq $certificate.Thumbprint }
if (-not $trustedCertificate) {
    Import-Certificate -FilePath $certificateFile -CertStoreLocation Cert:\CurrentUser\Root | Out-Null
}

Write-Output "Local TLS is ready for localhost. Certificate: $certificateFile"
