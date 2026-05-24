# Sportsbook Cron Job Setup

## Windows Task Scheduler (WAMP)

### Schedule 1: Live Scores (every 1 minute)
- **Program**: `C:\wamp64\bin\php\php8.2.0\php.exe`
- **Arguments**: `C:\wamp64\www\public_html\sportsbook\sync_daemon.php --mode=live`
- **Trigger**: Every 1 minute
- **Purpose**: Updates live scores, match timers, inplay status

### Schedule 2: Upcoming Matches (every 5 minutes)
- **Program**: `C:\wamp64\bin\php\php8.2.0\php.exe`
- **Arguments**: `C:\wamp64\www\public_html\sportsbook\sync_daemon.php --mode=upcoming`
- **Trigger**: Every 5 minutes
- **Purpose**: Fetches new upcoming matches for all sports

### Schedule 3: Full Sync (every 1 hour)
- **Program**: `C:\wamp64\bin\php\php8.2.0\php.exe`
- **Arguments**: `C:\wamp64\www\public_html\sportsbook\sync_daemon.php --mode=full`
- **Trigger**: Every 1 hour
- **Purpose**: Full refresh of all data, cleanup old matches

## Quick Setup via PowerShell (Run as Administrator)

```powershell
$php = "C:\wamp64\bin\php\php8.2.0\php.exe"
$script = "C:\wamp64\www\public_html\sportsbook\sync_daemon.php"

# Live sync every 1 minute
$action1 = New-ScheduledTaskAction -Execute $php -Argument "$script --mode=live"
$trigger1 = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 1) -Once -At (Get-Date)
Register-ScheduledTask -TaskName "SB_Live_Sync" -Action $action1 -Trigger $trigger1 -RunLevel Highest -Force

# Upcoming sync every 5 minutes
$action2 = New-ScheduledTaskAction -Execute $php -Argument "$script --mode=upcoming"
$trigger2 = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -Once -At (Get-Date)
Register-ScheduledTask -TaskName "SB_Upcoming_Sync" -Action $action2 -Trigger $trigger2 -RunLevel Highest -Force

# Full sync every hour
$action3 = New-ScheduledTaskAction -Execute $php -Argument "$script --mode=full"
$trigger3 = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Hours 1) -Once -At (Get-Date)
Register-ScheduledTask -TaskName "SB_Full_Sync" -Action $action3 -Trigger $trigger3 -RunLevel Highest -Force

Write-Host "Cron jobs registered!"
```

## Trigger Sync via Browser (localhost only)
- Full sync: `http://localhost/public_html/sportsbook/sync_daemon.php?mode=full`
- Live only: `http://localhost/public_html/sportsbook/sync_daemon.php?mode=live`
- Upcoming:  `http://localhost/public_html/sportsbook/sync_daemon.php?mode=upcoming`

## API Status Check
- `http://localhost/public_html/sportsbook/api.php?action=status`
