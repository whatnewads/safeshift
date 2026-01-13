# SafeShift EHR Deployment Guide

## Prerequisites
- PHP 8.4+ with extensions: pdo_mysql, openssl, mbstring, json
- MySQL 8.0+
- Apache 2.4+ with mod_rewrite
- Node.js 20+ (for building frontend)
- Composer 2.x

## Quick Start

### 1. Clone Repository
```bash
git clone https://github.com/your-org/safeshift-ehr.git
cd safeshift-ehr
```

### 2. Install Dependencies
```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# Frontend dependencies
npm ci
```

### 3. Configure Environment
```bash
cp .env.example .env
# Edit .env with your settings
```

### 4. Build Frontend
```bash
npm run build
```

### 5. Database Setup
```bash
mysql -u root -p < database/migrations/safeshift_complete_schema.sql
php database/run_migration.php
```

### 6. Set Permissions
```bash
chmod -R 755 .
chmod -R 777 logs/
chmod 600 .env
```

## Production Configuration

### Environment Variables
```env
# REQUIRED - Database
DB_HOST=localhost
DB_NAME=safeshift_production
DB_USER=safeshift_app
DB_PASS=<strong-password>

# REQUIRED - Security
APP_ENV=production
APP_DEBUG=false
ENCRYPTION_KEY=<32-character-random-key>
SESSION_SECRET=<64-character-random-key>

# REQUIRED - Application
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com

# OPTIONAL - Email
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=notifications@yourdomain.com
SMTP_PASS=<smtp-password>
```

### Apache Virtual Host
```apache
<VirtualHost *:443>
    ServerName safeshift.yourdomain.com
    DocumentRoot /var/www/safeshift-ehr
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/safeshift.crt
    SSLCertificateKeyFile /etc/ssl/private/safeshift.key
    
    <Directory /var/www/safeshift-ehr>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    
    ErrorLog ${APACHE_LOG_DIR}/safeshift-error.log
    CustomLog ${APACHE_LOG_DIR}/safeshift-access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName safeshift.yourdomain.com
    Redirect permanent / https://safeshift.yourdomain.com/
</VirtualHost>
```

### MySQL Configuration
```ini
[mysqld]
# Security
skip-symbolic-links
local-infile=0

# Performance
innodb_buffer_pool_size=1G
innodb_log_file_size=256M

# Connections
max_connections=200

# Character set
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
```

### PHP Configuration (php.ini)
```ini
; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Session
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1

; Upload limits
upload_max_filesize = 10M
post_max_size = 10M

; Memory
memory_limit = 256M
max_execution_time = 60
```

## Database Setup

### Create Database User
```sql
CREATE DATABASE safeshift_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'safeshift_app'@'localhost' IDENTIFIED BY '<strong-password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON safeshift_production.* TO 'safeshift_app'@'localhost';
FLUSH PRIVILEGES;
```

### Run Migrations
```bash
php database/run_complete_migration.php
```

### Create Initial Admin User
```bash
php create_test_user.php admin AdminPass123! Admin admin@yourdomain.com
```

## SSL/TLS Configuration

### Let's Encrypt (Recommended)
```bash
sudo certbot --apache -d safeshift.yourdomain.com
```

### Manual Certificate
1. Obtain certificate from CA
2. Place cert and key files
3. Update Apache config
4. Test with `ssl-labs.com`

## Deployment Checklist

### Pre-Deployment
- [ ] Database backup completed
- [ ] .env configured for production
- [ ] ENCRYPTION_KEY generated and secure
- [ ] SSL certificate installed
- [ ] DNS configured

### Deployment
- [ ] Dependencies installed (composer, npm)
- [ ] Frontend built (npm run build)
- [ ] Database migrations run
- [ ] File permissions set
- [ ] Apache restarted

### Post-Deployment
- [ ] Login tested
- [ ] API endpoints verified
- [ ] SSL certificate verified
- [ ] Error logging confirmed
- [ ] Backup schedule configured

## Backup & Recovery

### Database Backup (Daily)
```bash
#!/bin/bash
# /etc/cron.daily/safeshift-backup.sh
DATE=$(date +%Y%m%d)
mysqldump -u backup_user -p safeshift_production | gzip > /backups/db_$DATE.sql.gz
# Retain 30 days
find /backups -name "db_*.sql.gz" -mtime +30 -delete
```

### File Backup
```bash
rsync -avz /var/www/safeshift-ehr/ /backups/files/
```

## Monitoring

### Health Check Endpoint
```
GET /api/v1/health
```

### Log Locations
- Apache: `/var/log/apache2/safeshift-*.log`
- PHP: `/var/log/php/error.log`
- Application: `/var/www/safeshift-ehr/logs/`

## Troubleshooting

### Common Issues

**500 Internal Server Error**
- Check Apache error log
- Verify .htaccess is being read
- Check file permissions

**Database Connection Failed**
- Verify credentials in .env
- Check MySQL is running
- Verify network/firewall

**Session Issues**
- Check session directory permissions
- Verify cookie settings
- Clear browser cookies

## Updates

### Applying Updates
```bash
cd /var/www/safeshift-ehr
git pull origin main
composer install --no-dev
npm ci && npm run build
php database/run_migration.php
sudo systemctl reload apache2
```
