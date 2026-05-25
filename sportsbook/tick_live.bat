@echo off
REM ─────────────────────────────────────────────────────────────
REM   Fast Live Tick Daemon launcher (Windows)
REM ─────────────────────────────────────────────────────────────
REM   Pre-warms the BetsAPI cache every 2 seconds so user
REM   requests return instantly from disk.
REM
REM   Run this in a dedicated terminal:
REM     C:\wamp64\www\public_html\sportsbook\tick_live.bat
REM
REM   OR schedule it as a Windows Service / Task Scheduler entry
REM   to start at boot and auto-restart on crash.
REM ─────────────────────────────────────────────────────────────
setlocal
set PHP_EXE=C:\wamp64\bin\php\php8.2.0\php.exe
set SCRIPT=C:\wamp64\www\public_html\sportsbook\tick_live.php

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP CLI not found at %PHP_EXE%
    echo Edit this .bat to point at your real php.exe path.
    pause
    exit /b 1
)

:LOOP
echo [%DATE% %TIME%] Starting tick_live...
"%PHP_EXE%" "%SCRIPT%"
echo [%DATE% %TIME%] tick_live exited; restarting in 3s...
timeout /t 3 /nobreak >nul
goto LOOP
