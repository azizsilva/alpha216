#!/bin/bash
# ─────────────────────────────────────────────────────────────
#   tick_live.sh  —  Fast Live Tick Daemon launcher (Linux/VPS)
# ─────────────────────────────────────────────────────────────
#
#   Pre-warms the BetsAPI cache every 2 seconds so user
#   requests return instantly from disk.
#
#   INSTALL ON VPS:
#   1. Upload this file to /var/www/html/public_html/sportsbook/
#   2. chmod +x tick_live.sh
#   3. Run once to test:   bash tick_live.sh
#   4. Add to crontab for auto-start on reboot:
#      crontab -e
#      @reboot /bin/bash /var/www/html/public_html/sportsbook/tick_live.sh >> /var/log/tick_live.log 2>&1 &
#
#   OR run as systemd service (preferred):
#      sudo nano /etc/systemd/system/tick_live.service
#      (copy the systemd block below, then: systemctl enable tick_live && systemctl start tick_live)
#
#   SYSTEMD SERVICE FILE (paste into /etc/systemd/system/tick_live.service):
#   ─────────────────────────────────────────────────────────
#   [Unit]
#   Description=SportsBook Live Tick Daemon
#   After=network.target
#
#   [Service]
#   Type=simple
#   User=www-data
#   ExecStart=/usr/bin/php /var/www/html/public_html/sportsbook/tick_live.php
#   Restart=always
#   RestartSec=3
#   StandardOutput=append:/var/log/tick_live.log
#   StandardError=append:/var/log/tick_live.log
#
#   [Install]
#   WantedBy=multi-user.target
#   ─────────────────────────────────────────────────────────

# Detect PHP binary
PHP_BIN=$(which php8.2 2>/dev/null || which php8.1 2>/dev/null || which php 2>/dev/null)
if [ -z "$PHP_BIN" ]; then
    echo "[ERROR] PHP CLI not found. Install with: apt install php-cli"
    exit 1
fi

# Detect script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT="$SCRIPT_DIR/tick_live.php"

if [ ! -f "$SCRIPT" ]; then
    echo "[ERROR] tick_live.php not found at $SCRIPT"
    exit 1
fi

echo "[tick_live.sh] Using PHP: $PHP_BIN"
echo "[tick_live.sh] Script: $SCRIPT"

# Restart loop — auto-restarts if PHP crashes
while true; do
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting tick_live..."
    "$PHP_BIN" "$SCRIPT"
    EXIT_CODE=$?
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] tick_live exited (code $EXIT_CODE); restarting in 3s..."
    sleep 3
done
