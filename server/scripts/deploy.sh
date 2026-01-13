#!/bin/bash
# SafeShift EHR Deployment Script
# Usage: ./scripts/deploy.sh [environment]

set -e

ENVIRONMENT=${1:-production}
APP_DIR="/var/www/safeshift-ehr"
BACKUP_DIR="/backups/safeshift"

echo "=== SafeShift EHR Deployment ==="
echo "Environment: $ENVIRONMENT"
echo "Date: $(date)"

# Create backup
echo "Creating backup..."
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf "$BACKUP_FILE" -C "$APP_DIR" . 2>/dev/null || true

# Pull latest code
echo "Pulling latest code..."
cd "$APP_DIR"
git fetch origin
git checkout main
git pull origin main

# Install PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Install and build frontend
echo "Building frontend..."
npm ci
npm run build

# Run database migrations
echo "Running database migrations..."
php database/run_migration.php

# Clear caches
echo "Clearing caches..."
rm -rf /tmp/safeshift_cache/* 2>/dev/null || true

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 777 "$APP_DIR/logs"
chmod 600 "$APP_DIR/.env"

# Reload Apache
echo "Reloading Apache..."
systemctl reload apache2

echo "=== Deployment Complete ==="
echo "Backup saved to: $BACKUP_FILE"
