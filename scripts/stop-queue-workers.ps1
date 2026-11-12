# Campaign Queue Workers - Stop Script
# This script stops all queue worker PowerShell windows

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  TELEMAN AI - Stop Queue Workers       " -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Find all PowerShell processes running queue workers
$queueWorkers = Get-Process powershell -ErrorAction SilentlyContinue | Where-Object {
    $_.MainWindowTitle -like "*Worker*"
}

if ($queueWorkers) {
    Write-Host "Found $($queueWorkers.Count) queue worker(s) running" -ForegroundColor Yellow
    Write-Host ""
    
    foreach ($worker in $queueWorkers) {
        Write-Host "Stopping: $($worker.MainWindowTitle)" -ForegroundColor Cyan
        Stop-Process -Id $worker.Id -Force
    }
    
    Write-Host ""
    Write-Host "✓ All queue workers stopped successfully!" -ForegroundColor Green
} else {
    Write-Host "No queue workers found running." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Press any key to close this window..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
