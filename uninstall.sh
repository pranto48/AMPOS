#!/bin/bash

# AMPOS Uninstaller for Linux
# Usage: curl -sL https://raw.githubusercontent.com/pranto48AMPOS/main/uninstall.sh | bash

echo "=================================="
echo "      Uninstalling AMPOS..."
echo "=================================="

# 1. Stop and Delete Service
if command -v pm2 &> /dev/null; then
    echo "Stopping background service..."
    pm2 stop ampos
    pm2 delete ampos
    pm2 save
else
    echo "PM2 not found, skipping service removal."
fi

# 2. Remove Files
INSTALL_DIR="$HOME/ampos"
if [ -d "$INSTALL_DIR" ]; then
    echo "Removing files from $INSTALL_DIR..."
    rm -rf "$INSTALL_DIR"
else
    echo "AMPOS directory not found."
fi

echo "=================================="
echo "   AMPOS Uninstalled Successfully."
echo "=================================="
