# Run as Administrator in PowerShell to install sportsbook cron jobs
# Usage: powershell -ExecutionPolicy Bypass -File install_cron.ps1

$php  = "C:\wamp64\bin\php\php8.2.0\php.exe"
$script = "C:\wamp64\www\public_html\sportsbook\sync_daemon.php"

# Check if php exists
if (-not (Test-Path $php)) {
    $php = (Get-ChildItem "C:\wamp64\bin\php" -Filter "php.exe" -Recurse | Sort-Object FullName -Descending | Select-Object -First 1).FullName
    Write-Host "Using PHP: $php"
}

function Register-SBTask($name, $modeArg, $interval) {
    $action  = New-ScheduledTaskAction -Execute $php -Argument "$script --mode=$modeArg"
    $trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes $interval) -Once -At (Get-Date)
    $settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -MultipleInstances IgnoreNew
    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest
    Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force
    Write-Host "Registered: $name (every $interval min, mode=$modeArg)"
}

# Live scores + odds: every 1 minute
Register-SBTask "SB_Live_Sync"     "live"     1

# Upcoming matches: every 5 minutes
Register-SBTask "SB_Upcoming_Sync" "upcoming" 5

# Full refresh: every 60 minutes
Register-SBTask "SB_Full_Sync"     "full"     60

Write-Host ""
Write-Host "Done! Verify with: Get-ScheduledTask | Where-Object {`$_.TaskName -like 'SB_*'}"
Get-ScheduledTask | Where-Object { $_.TaskName -like "SB_*" } | Format-Table TaskName, State
