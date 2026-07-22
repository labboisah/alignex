param(
    [string] $OfflineServerPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..\..')) 'offline-server'),
    [string] $OutputRoot = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) 'public\downloads\offline-server'),
    [string] $IconPath = ''
)

$ErrorActionPreference = 'Stop'

$offlineServer = Resolve-Path $OfflineServerPath
$repoRoot = Resolve-Path (Join-Path $offlineServer '..')
$electronDist = Join-Path $offlineServer 'node_modules\electron\dist'
$distFolder = Join-Path $offlineServer 'dist'
$appFolder = Join-Path $OutputRoot 'AlignEx-Center-Server-win-unpacked'
$zipPath = Join-Path $OutputRoot 'AlignEx-Center-Server-win-unpacked.zip'
$resourcesApp = Join-Path $appFolder 'resources\app'
$resolvedIconPath = if ($IconPath) { Resolve-Path $IconPath } else { Join-Path $offlineServer 'public\images\logo.ico' }
$rcedit = @(
    (Join-Path $offlineServer 'node_modules\rcedit\bin\rcedit.exe'),
    (Join-Path $offlineServer 'node_modules\electron-winstaller\vendor\rcedit.exe'),
    (Join-Path $repoRoot 'offline-candidate-browser\node_modules\rcedit\bin\rcedit.exe'),
    (Join-Path $repoRoot 'offline-candidate-browser\node_modules\electron-winstaller\vendor\rcedit.exe')
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not (Test-Path (Join-Path $electronDist 'electron.exe'))) {
    throw "Electron runtime was not found at $electronDist. Run npm install inside offline-server first."
}

if (-not (Test-Path (Join-Path $distFolder 'electron\main.js'))) {
    throw "Built server files were not found at $distFolder. Run npm run build inside offline-server first."
}

if (-not (Test-Path (Join-Path $distFolder 'renderer\index.html'))) {
    throw "Built renderer files were not found at $distFolder. Run npm run build inside offline-server first."
}

if (-not (Test-Path $resolvedIconPath)) {
    throw "Center server icon was not found at $resolvedIconPath."
}

if (-not $rcedit) {
    throw 'Windows resource editor was not found. Run npm install inside offline-server so rcedit is available.'
}

New-Item -ItemType Directory -Force $OutputRoot | Out-Null

if (Test-Path $appFolder) {
    Remove-Item -LiteralPath $appFolder -Recurse -Force
}

if (Test-Path $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

Copy-Item -LiteralPath $electronDist -Destination $appFolder -Recurse
Rename-Item -LiteralPath (Join-Path $appFolder 'electron.exe') -NewName 'AlignEx Center Server.exe'
$serverExe = Join-Path $appFolder 'AlignEx Center Server.exe'

& $rcedit $serverExe --set-icon $resolvedIconPath

if ($LASTEXITCODE -ne 0) {
    throw 'Failed to apply the AlignEx icon to the center server executable.'
}

New-Item -ItemType Directory -Force $resourcesApp | Out-Null
Copy-Item -LiteralPath $distFolder -Destination (Join-Path $resourcesApp 'dist') -Recurse
Copy-Item -LiteralPath (Join-Path $offlineServer 'package.json') -Destination (Join-Path $resourcesApp 'package.json')
Copy-Item -LiteralPath (Join-Path $offlineServer '.env.example') -Destination (Join-Path $resourcesApp '.env.example')
Copy-Item -LiteralPath (Join-Path $offlineServer 'node_modules') -Destination (Join-Path $resourcesApp 'node_modules') -Recurse

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $appFolder,
    $zipPath,
    [System.IO.Compression.CompressionLevel]::Fastest,
    $true
)

if (-not (Test-Path $zipPath) -or (Get-Item $zipPath).Length -le 0) {
    throw 'Offline server archive was not created.'
}

Write-Output "Created: $zipPath"
Write-Output "Size: $((Get-Item $zipPath).Length) bytes"
