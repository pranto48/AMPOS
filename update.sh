#!/bin/bash
echo "Updating AMPOS..."
cd "$HOME/ampos"
git pull origin main
cd frontend && npm install && npm run build
cd ../backend && npm install
pm2 restart ampos
echo "Update Complete."
