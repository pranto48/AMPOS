# AMPOS Uninstaller for Windows
# Usage: iwr -useb https://raw.githubusercontent.com/pranto48/AMPOS/main/uninstall.ps1 | iex

Write-Host "==================================" -ForegroundColor Cyan
Write-Host "      Uninstalling AMPOS (Windows)..." -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan

$InstallDir = "$HOME\ampos"
$ServiceName = "ampos"

# 1. Stop PM2-managed service if present
if (Get-Command pm2 -ErrorAction SilentlyContinue) {
    Write-Host "Stopping PM2 service..." -ForegroundColor Yellow
    pm2 delete $ServiceName 2>$null | Out-Null
    pm2 save 2>$null | Out-Null
}

# 2. Stop any running AMPOS server processes
$serverProcesses = Get-Process node -ErrorAction SilentlyContinue | Where-Object { $_.Path -like '*server.js*' }
if ($serverProcesses) {
    Write-Host "Stopping running AMPOS server processes..." -ForegroundColor Yellow
    $serverProcesses | Stop-Process -Force -ErrorAction SilentlyContinue
}

# 3. Remove installation directory
if (Test-Path $InstallDir) {
    Write-Host "Removing installation directory at $InstallDir ..." -ForegroundColor Yellow
    Remove-Item -Recurse -Force $InstallDir
} else {
    Write-Host "No installation directory found at $InstallDir" -ForegroundColor DarkGray
}

Write-Host "==================================" -ForegroundColor Green
Write-Host "   AMPOS has been removed." -ForegroundColor Green
Write-Host "==================================" -ForegroundColor Green
