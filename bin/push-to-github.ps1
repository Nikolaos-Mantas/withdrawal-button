# Publish Withdrawal Button to GitHub (run once after gh auth login).
# Usage: powershell -ExecutionPolicy Bypass -File bin/push-to-github.ps1

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot\..

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
	Write-Host "GitHub CLI (gh) is not installed. Install: winget install GitHub.cli" -ForegroundColor Red
	exit 1
}

$auth = gh auth status 2>&1
if ($LASTEXITCODE -ne 0) {
	Write-Host "Not logged in to GitHub. Run this first in your terminal:" -ForegroundColor Yellow
	Write-Host "  gh auth login" -ForegroundColor Cyan
	Write-Host "Choose: GitHub.com -> HTTPS -> Login with browser" -ForegroundColor Gray
	exit 1
}

Write-Host "Creating public repo Nikolaos-Mantas/withdrawal-button (if missing) and pushing..." -ForegroundColor Green

# Create repo on GitHub and push (works with existing local git + commits).
gh repo create Nikolaos-Mantas/withdrawal-button --public --source=. --remote=origin --push

if ($LASTEXITCODE -ne 0) {
	Write-Host "If repo already exists, try: git push -u origin main" -ForegroundColor Yellow
	git push -u origin main
}

git push origin v3.0.0 2>$null
if ($LASTEXITCODE -eq 0) {
	Write-Host "Tag v3.0.0 pushed." -ForegroundColor Green
}

Write-Host "Done. Open: https://github.com/Nikolaos-Mantas/withdrawal-button" -ForegroundColor Green
