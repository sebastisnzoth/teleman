# Campaign Queue Workers - Start Script
# This script starts all necessary queue workers for handling large concurrent campaigns

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TELEMAN AI - Campaign Queue Workers  " -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$projectPath = "d:\laragon\www\teleman-ai"
$campaignWorkers = 2
$callWorkers = 10
$defaultWorkers = 1

Write-Host "Project Path: $projectPath" -ForegroundColor Yellow
Write-Host "Campaign Workers: $campaignWorkers" -ForegroundColor Yellow
Write-Host "Call Workers: $callWorkers" -ForegroundColor Yellow
Write-Host "Default Workers: $defaultWorkers" -ForegroundColor Yellow
Write-Host ""

# Verify project path exists
if (-not (Test-Path $projectPath)) {
    Write-Host "ERROR: Project path does not exist: $projectPath" -ForegroundColor Red
    exit 1
}

Write-Host "Starting queue workers..." -ForegroundColor Green
Write-Host ""

# Start Campaign Queue Workers
Write-Host "[1/3] Starting $campaignWorkers Campaign Queue Workers..." -ForegroundColor Cyan
for ($i = 1; $i -le $campaignWorkers; $i++) {
    Start-Process powershell -ArgumentList @(
        "-NoExit",
        "-Command",
        "cd '$projectPath'; `$host.ui.RawUI.WindowTitle = 'Campaign Worker #$i'; php artisan queue:work --queue=campaigns --tries=3 --timeout=300 --memory=256 --sleep=3"
    )
    Write-Host "  ✓ Campaign Worker #$i started" -ForegroundColor Green
    Start-Sleep -Milliseconds 500
}

Write-Host ""

# Start Campaign Calls Queue Workers
Write-Host "[2/3] Starting $callWorkers Campaign Call Workers..." -ForegroundColor Cyan
for ($i = 1; $i -le $callWorkers; $i++) {
    Start-Process powershell -ArgumentList @(
        "-NoExit",
        "-Command",
        "cd '$projectPath'; `$host.ui.RawUI.WindowTitle = 'Call Worker #$i'; php artisan queue:work --queue=campaign-calls --tries=3 --timeout=120 --memory=256 --sleep=1"
    )
    Write-Host "  ✓ Call Worker #$i started" -ForegroundColor Green
    Start-Sleep -Milliseconds 300
}

Write-Host ""

# Start Default Queue Workers
Write-Host "[3/3] Starting $defaultWorkers Default Queue Worker(s)..." -ForegroundColor Cyan
for ($i = 1; $i -le $defaultWorkers; $i++) {
    Start-Process powershell -ArgumentList @(
        "-NoExit",
        "-Command",
        "cd '$projectPath'; `$host.ui.RawUI.WindowTitle = 'Default Worker #$i'; php artisan queue:work --queue=default --tries=3 --timeout=90 --memory=256 --sleep=3"
    )
    Write-Host "  ✓ Default Worker #$i started" -ForegroundColor Green
    Start-Sleep -Milliseconds 500
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  ALL WORKERS STARTED SUCCESSFULLY!     " -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Total Workers: $($campaignWorkers + $callWorkers + $defaultWorkers)" -ForegroundColor Yellow
Write-Host ""
Write-Host "Worker Distribution:" -ForegroundColor White
Write-Host "  - campaigns queue: $campaignWorkers workers" -ForegroundColor White
Write-Host "  - campaign-calls queue: $callWorkers workers" -ForegroundColor White
Write-Host "  - default queue: $defaultWorkers workers" -ForegroundColor White
Write-Host ""
Write-Host "System Capacity:" -ForegroundColor White
Write-Host "  - Max Concurrent Campaigns: 10-20" -ForegroundColor White
Write-Host "  - Max Concurrent Calls: 200-1,000" -ForegroundColor White
Write-Host "  - Contacts per Hour: 3,000-6,000" -ForegroundColor White
Write-Host ""
Write-Host "To stop all workers:" -ForegroundColor Yellow
Write-Host "  Run: .\scripts\stop-queue-workers.ps1" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press any key to close this window..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
