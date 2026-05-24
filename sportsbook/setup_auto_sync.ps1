# ══════════════════════════════════════════════════════════════════════════════
#  SPORTSBOOK AUTO-SYNC SETUP — Windows Task Scheduler
#  Run this once as Administrator to enable automatic live sync every minute.
#  ─────────────────────────────────────────────────────────────────────────────
#  Run: Right-click → "Run with PowerShell" OR
#       powershell -ExecutionPolicy Bypass -File setup_auto_sync.ps1
# ══════════════════════════════════════════════════════════════════════════════

$PHP = "C:\wamp64\bin\php\php8.2.0\php.exe"
$DAEMON = "C:\wamp64\www\public_html\sportsbook\sync_daemon.php"

# Auto-detect PHP if default path doesn't exist
if (-not (Test-Path $PHP)) {
    $found = Get-ChildItem "C:\wamp64\bin\php" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue |
             Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($found) { $PHP = $found.FullName }
}

Write-Host "Using PHP: $PHP" -ForegroundColor Cyan
Write-Host "Using Daemon: $DAEMON" -ForegroundColor Cyan

# ── Task 1: Live Sync — every 1 minute ─────────────────────────────────────
$actionLive = New-ScheduledTaskAction -Execute $PHP -Argument "`"$DAEMON`" --mode=live"
$triggerLive = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 1) -Once -At (Get-Date)
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 2) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName "SportsBook_LiveSync" `
    -Action $actionLive -Trigger $triggerLive -Settings $settings `
    -RunLevel Highest -Force
Write-Host "[OK] LiveSync task created (every 1 min)" -ForegroundColor Green

# ── Task 2: Upcoming Sync — every 5 minutes ────────────────────────────────
$actionUp = New-ScheduledTaskAction -Execute $PHP -Argument "`"$DAEMON`" --mode=upcoming"
$triggerUp = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -Once -At (Get-Date)
Register-ScheduledTask -TaskName "SportsBook_UpcomingSync" `
    -Action $actionUp -Trigger $triggerUp -Settings $settings `
    -RunLevel Highest -Force
Write-Host "[OK] UpcomingSync task created (every 5 min)" -ForegroundColor Green

Write-Host ""
Write-Host "Auto-sync configured! The sportsbook will now update automatically." -ForegroundColor Yellow
Write-Host "Scores and odds refresh every ~1 minute without any manual action." -ForegroundColor Yellow
