# AMPOS Installer for Windows
# Usage: iwr -useb https://raw.githubusercontent.com/YOUR_USER/AMPOS/main/install.ps1 | iex

Write-Host "==================================" -ForegroundColor Cyan
Write-Host "      Installing AMPOS (Windows)..." -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan

# 1. Check for Node.js
if (!(Get-Command node -ErrorAction SilentlyContinue)) {
    Write-Host "Node.js not found. Please install Node.js from https://nodejs.org/ first." -ForegroundColor Red
    Exit
}

# 2. Setup Directory
$InstallDir = "$HOME\ampos"
$RepoUrl = "https://github.com/pranto48/AMPOS.git" # REPLACE THIS

if (Test-Path $InstallDir) {
    Write-Host "AMPOS found. Updating..." -ForegroundColor Yellow
    Set-Location $InstallDir
    git pull
} else {
    Write-Host "Cloning AMPOS..." -ForegroundColor Yellow
    git clone $RepoUrl $InstallDir
    Set-Location $InstallDir
}

# 3. Backend Setup
Write-Host "Installing Backend Dependencies..." -ForegroundColor Yellow
Set-Location "$InstallDir\backend"
npm install

# 4. Frontend Setup
Write-Host "Building Frontend (This takes time)..." -ForegroundColor Yellow
Set-Location "$InstallDir\frontend"
npm install
npm run build

# 5. Start
Write-Host "Starting AMPOS..." -ForegroundColor Green
Set-Location "$InstallDir\backend"
Start-Process "node" -ArgumentList "server.js" -NoNewWindow

Write-Host "==================================" -ForegroundColor Green
Write-Host "   AMPOS is running!"
Write-Host "   Open Browser: http://localhost:3001"
Write-Host "=================================="
