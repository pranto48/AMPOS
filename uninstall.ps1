# AMPOS Uninstaller for Windows
# Usage: iwr -useb https://raw.githubusercontent.com/pranto48/AMPOS/main/uninstall.ps1 | iex

Write-Host "==================================" -ForegroundColor Cyan
Write-Host "      Uninstalling AMPOS..."
Write-Host "==================================" -ForegroundColor Cyan

# 1. Stop the Process
# Note: This attempts to stop Node.js processes. 
# If you have other Node apps running, you might want to do this manually.
$process = Get-Process node -ErrorAction SilentlyContinue
if ($process) {
    Write-Host "Stopping Node.js processes..." -ForegroundColor Yellow
    Stop-Process -Name node -Force -ErrorAction SilentlyContinue
}

# 2. Remove Files
$InstallDir = "$HOME\ampos"
if (Test-Path $InstallDir) {
    Write-Host "Deleting AMPOS folder..." -ForegroundColor Yellow
    Remove-Item -Path $InstallDir -Recurse -Force
} else {
    Write-Host "AMPOS folder not found." -ForegroundColor Red
}

Write-Host "==================================" -ForegroundColor Green
Write-Host "   AMPOS Uninstalled Successfully."
Write-Host "=================================="
