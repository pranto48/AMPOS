#!/bin/bash

# AMPOS Installer for Linux
# Usage: curl -sL https://raw.githubusercontent.com/YOUR_USER/AMPOS/main/install.sh | bash

echo "=================================="
echo "      Installing AMPOS..."
echo "=================================="

# 1. Install Dependencies (Node.js & Git)
if ! command -v node &> /dev/null; then
    echo "Node.js not found. Installing..."
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt-get install -y nodejs git
fi

# 2. Clone/Setup Directory
INSTALL_DIR="$HOME/ampos"

if [ -d "$INSTALL_DIR" ]; then
    echo "AMPOS already installed. Updating..."
    cd "$INSTALL_DIR"
    git pull
else
    echo "Cloning AMPOS from GitHub..."
    # REPLACE WITH YOUR GITHUB URL BELOW
    git clone https://github.com/YOUR_GITHUB_USERNAME/AMPOS.git "$INSTALL_DIR"
    cd "$INSTALL_DIR"
fi

# 3. Install Backend
echo "Setting up Backend..."
cd "$INSTALL_DIR/backend"
npm install

# 4. Install & Build Frontend
echo "Building Frontend (this may take a moment)..."
cd "$INSTALL_DIR/frontend"
npm install
npm run build

# 5. Setup Process Manager (PM2) to keep it running
if ! command -v pm2 &> /dev/null; then
    echo "Installing PM2 process manager..."
    sudo npm install -g pm2
fi

echo "Starting AMPOS Service..."
cd "$INSTALL_DIR/backend"
pm2 delete ampos 2>/dev/null || true
pm2 start server.js --name ampos

# 6. Save PM2 list so it starts on boot
pm2 save
pm2 startup | tail -n 1 | bash 2>/dev/null || true

echo "=================================="
echo "   AMPOS Installed Successfully!"
echo "   Access it at: http://$(hostname -I | awk '{print $1}'):3001"
echo "=================================="
