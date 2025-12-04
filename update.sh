# AMPOS Updater for Windows
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "      Updating AMPOS..."
Write-Host "==================================" -ForegroundColor Cyan

# Go up one level from backend (if called from there) or ensure we are at root
$RootPath = Resolve-Path ".."
Set-Location $RootPath

# 1. Pull latest code
Write-Host "Pulling changes from GitHub..." -ForegroundColor Yellow
git pull origin main

# 2. Rebuild Frontend
Write-Host "Rebuilding Frontend..." -ForegroundColor Yellow
Set-Location "$RootPath\frontend"
npm install
npm run build

# 3. Update Backend Dependencies
Write-Host "Updating Backend..." -ForegroundColor Yellow
Set-Location "$RootPath\backend"
npm install

Write-Host "==================================" -ForegroundColor Green
Write-Host "   Update Complete!"
Write-Host "   Please restart the server manually if it doesn't reload."
Write-Host "=================================="
