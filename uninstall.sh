#!/bin/bash

# AMPOS Uninstaller for Linux
# Usage: curl -sL https://raw.githubusercontent.com/pranto48/AMPOS/main/uninstall.sh | bash

set -euo pipefail

INSTALL_DIR="$HOME/ampos"
SERVICE_NAME="ampos"

echo "=================================="
echo "      Uninstalling AMPOS..."
echo "=================================="

# 1. Stop PM2-managed service if present
if command -v pm2 >/dev/null 2>&1; then
    echo "Stopping PM2 service..."
    pm2 delete "$SERVICE_NAME" 2>/dev/null || true
    pm2 save >/dev/null 2>&1 || true

    # Attempt to remove startup entry for the current user
    pm2 unstartup systemd -u "$USER" --hp "$HOME" >/dev/null 2>&1 || true
fi

# 2. Terminate any remaining Node processes tied to AMPOS
if pgrep -f "backend/server.js" >/dev/null 2>&1; then
    echo "Stopping running AMPOS server process..."
    pkill -f "backend/server.js" || true
fi

# 3. Remove installation directory
if [ -d "$INSTALL_DIR" ]; then
    echo "Removing installation directory at $INSTALL_DIR ..."
    rm -rf "$INSTALL_DIR"
else
    echo "No installation directory found at $INSTALL_DIR"
fi

echo "=================================="
echo "   AMPOS has been removed."
echo "=================================="
