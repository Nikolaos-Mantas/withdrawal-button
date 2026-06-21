# Publish Withdrawal Button to GitHub (run once after gh auth login).
# Usage: powershell -ExecutionPolicy Bypass -File bin/push-to-github.ps1

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot\..

$gh = "C:\Program Files\GitHub CLI\gh.exe"
if (-not (Test-Path $gh)) {
	$ghCmd = Get-Command gh -ErrorAction SilentlyContinue
	if ($ghCmd) {
		$gh = $ghCmd.Source
	} else {
		Write-Host "GitHub CLI not found. Install: winget install GitHub.cli" -ForegroundColor Red
		exit 1
	}
}

& $gh auth status 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
	Write-Host "Not logged in to GitHub. Run first:" -ForegroundColor Yellow
	Write-Host "  & `"C:\Program Files\GitHub CLI\gh.exe`" auth login" -ForegroundColor Cyan
	exit 1
}

Write-Host "Creating public repo and pushing..." -ForegroundColor Green

& $gh repo create Nikolaos-Mantas/withdrawal-button --public --source=. --remote=origin --push

if ($LASTEXITCODE -ne 0) {
	Write-Host "Trying git push..." -ForegroundColor Yellow
	git push -u origin main
}

git push origin v3.0.0 2>$null
Write-Host "Done: https://github.com/Nikolaos-Mantas/withdrawal-button" -ForegroundColor Green
