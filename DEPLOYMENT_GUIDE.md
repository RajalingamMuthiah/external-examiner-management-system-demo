# EEMS Deployment Guide
**External Exam Management System**  
**Version:** 1.0  
**Last Updated:** December 13, 2025

---

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Installation Steps](#installation-steps)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [Post-Deployment](#post-deployment)
7. [Production Optimization](#production-optimization)
8. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Server Requirements

**Minimum Specifications:**
- **OS:** Linux (Ubuntu 20.04+, CentOS 8+) or Windows Server 2019+
- **Web Server:** Apache 2.4+ or Nginx 1.18+
- **PHP:** 7.4+ (8.0+ recommended)
- **Database:** MySQL 8.0+ or MariaDB 10.5+
- **RAM:** 4 GB minimum, 8 GB recommended
- **Storage:** 50 GB minimum, 100 GB+ for production
- **SSL Certificate:** Required for HTTPS

**Recommended Production Specifications:**
- **OS:** Ubuntu Server 22.04 LTS
- **Web Server:** Nginx 1.22+ with PHP-FPM
- **PHP:** 8.1+
- **Database:** MySQL 8.0+ (dedicated server for large installations)
- **RAM:** 16 GB
- **Storage:** 500 GB SSD
- **CPU:** 4+ cores
- **Backup:** Separate backup server/service

### PHP Extensions Required

```bash
# Check installed extensions
php -m

# Required extensions:
- pdo
- pdo_mysql
- mysqli
- mbstring
- curl
- openssl
- json
- fileinfo
- gd (for image processing)
- zip (for document generation)
- xml
- dom
- session
```

### Database Configuration

**MySQL/MariaDB Settings:**
```sql
-- Minimum settings for my.cnf or my.ini
[mysqld]
max_allowed_packet=64M
innodb_buffer_pool_size=2G
innodb_log_file_size=512M
max_connections=200
query_cache_size=64M
query_cache_type=1
```

---

## Pre-Deployment Checklist

### 1. Environment Preparation

- [ ] Server provisioned with required specifications
- [ ] Domain name registered and DNS configured
- [ ] SSL certificate obtained (Let's Encrypt or commercial)
- [ ] Firewall configured (ports 80, 443 open)
- [ ] SSH access configured for deployment
- [ ] Backup strategy planned and tested

### 2. Software Installation

- [ ] Web server installed and running
- [ ] PHP installed with all required extensions
- [ ] MySQL/MariaDB installed and secured
- [ ] Composer installed (for dependency management)
- [ ] Git installed (for version control)

### 3. Security Preparation

- [ ] Strong database passwords generated
- [ ] Admin user credentials prepared
- [ ] SMTP credentials obtained for email
- [ ] File upload directory with proper permissions
- [ ] Security headers configured

### 4. Data Preparation

- [ ] College information collected
- [ ] Department list prepared
- [ ] Initial user list with emails
- [ ] Email templates reviewed
- [ ] System branding assets ready (logo, etc.)

---

## Installation Steps

### Step 1: Download EEMS

**Option A: Git Clone (Recommended)**
```bash
# Navigate to web root
cd /var/www/html  # Linux
cd C:\xampp\htdocs  # Windows

# Clone repository
git clone https://github.com/yourusername/eems.git
cd eems

# Set correct permissions (Linux)
sudo chown -R www-data:www-data /var/www/html/eems
sudo chmod -R 755 /var/www/html/eems
sudo chmod -R 777 /var/www/html/eems/uploads
sudo chmod -R 777 /var/www/html/eems/logs
```

**Option B: Manual Upload**
1. Download EEMS zip from repository
2. Extract to your web server directory
3. Ensure proper file permissions (as above)

### Step 2: Web Server Configuration

**Apache Configuration**

Create virtual host file: `/etc/apache2/sites-available/eems.conf`

```apache
<VirtualHost *:80>
    ServerName eems.yourcollege.edu
    ServerAdmin admin@yourcollege.edu
    DocumentRoot /var/www/html/eems

    <Directory /var/www/html/eems>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/eems-error.log
    CustomLog ${APACHE_LOG_DIR}/eems-access.log combined

    # Redirect to HTTPS
    Redirect permanent / https://eems.yourcollege.edu/
</VirtualHost>

<VirtualHost *:443>
    ServerName eems.yourcollege.edu
    ServerAdmin admin@yourcollege.edu
    DocumentRoot /var/www/html/eems

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/eems.yourcollege.edu/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/eems.yourcollege.edu/privkey.pem

    <Directory /var/www/html/eems>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; img-src 'self' data:;"
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/eems-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/eems-ssl-access.log combined
</VirtualHost>
```

Enable site and modules:
```bash
sudo a2ensite eems.conf
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

**Nginx Configuration**

Create configuration: `/etc/nginx/sites-available/eems`

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name eems.yourcollege.edu;
    return 301 https://$server_name$request_uri;
}

# HTTPS configuration
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name eems.yourcollege.edu;

    # Root directory
    root /var/www/html/eems;
    index index.php index.html;

    # SSL certificates
    ssl_certificate /etc/letsencrypt/live/eems.yourcollege.edu/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/eems.yourcollege.edu/privkey.pem;

    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # File upload limits
    client_max_body_size 10M;

    # Logging
    access_log /var/log/nginx/eems-access.log;
    error_log /var/log/nginx/eems-error.log;

    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Default location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/eems /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 3: SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Obtain certificate
sudo certbot --apache -d eems.yourcollege.edu    # For Apache
# OR
sudo certbot --nginx -d eems.yourcollege.edu     # For Nginx

# Test auto-renewal
sudo certbot renew --dry-run
```

---

## Database Setup

### Step 1: Create Database

```bash
# Login to MySQL
mysql -u root -p

# Or for remote server
mysql -h hostname -u root -p
```

```sql
-- Create database
CREATE DATABASE eems_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create database user
CREATE USER 'eems_user'@'localhost' IDENTIFIED BY 'strong_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON eems_production.* TO 'eems_user'@'localhost';
FLUSH PRIVILEGES;

-- Verify
SHOW GRANTS FOR 'eems_user'@'localhost';

-- Exit
EXIT;
```

### Step 2: Import Schema

```bash
# Navigate to EEMS directory
cd /var/www/html/eems

# Import main schema
mysql -u eems_user -p eems_production < db/schema.sql

# Import seed data (optional - for testing)
mysql -u eems_user -p eems_production < db/seed.sql

# Import performance optimizations
mysql -u eems_user -p eems_production < db/optimize_performance.sql

# Verify tables created
mysql -u eems_user -p eems_production -e "SHOW TABLES;"
```

Expected tables:
```
+---------------------------+
| Tables_in_eems_production |
+---------------------------+
| approvals                 |
| assignments               |
| audit_logs                |
| colleges                  |
| departments               |
| exam_assignments          |
| exams                     |
| notifications             |
| practical_exam_attempts   |
| practical_exam_sessions   |
| question_papers           |
| ratings                   |
| users                     |
+---------------------------+
```

### Step 3: Create Admin User

```sql
-- Login to MySQL
mysql -u eems_user -p eems_production

-- Insert admin user
INSERT INTO users (
    name, 
    email, 
    password, 
    role, 
    college_id, 
    status, 
    is_verified,
    created_at
) VALUES (
    'System Administrator',
    'admin@yourcollege.edu',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "admin123"
    'admin',
    1, -- Update with actual college_id
    'active',
    1,
    NOW()
);

-- Get the admin user ID
SELECT user_id, name, email, role FROM users WHERE role = 'admin';
```

**Important:** Change the default password immediately after first login!

---

## Configuration

### Step 1: Database Configuration

Edit `config/db.php`:

```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'eems_production');
define('DB_USER', 'eems_user');
define('DB_PASS', 'your_strong_password');
define('DB_CHARSET', 'utf8mb4');

// Error reporting (disable in production)
define('DB_DEBUG', false);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please contact administrator.");
}
?>
```

### Step 2: Email Configuration

Edit `config/n8n_config.php` or create `config/email.php`:

```php
<?php
// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');        // Your SMTP server
define('SMTP_PORT', 587);                     // TLS: 587, SSL: 465
define('SMTP_ENCRYPTION', 'tls');             // 'tls' or 'ssl'
define('SMTP_USERNAME', 'noreply@yourcollege.edu');
define('SMTP_PASSWORD', 'your_smtp_password');
define('SMTP_FROM_NAME', 'EEMS - Your College');
define('SMTP_FROM_EMAIL', 'noreply@yourcollege.edu');

// Email settings
define('MAIL_DEBUG', false);                  // Set to true for debugging
define('MAIL_CHARSET', 'UTF-8');
?>
```

**For Gmail:**
- Enable "Less secure app access" OR
- Use App-specific password (recommended)
- Enable IMAP in Gmail settings

**For Office 365:**
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

### Step 3: Application Configuration

Create `config/app.php`:

```php
<?php
// Application configuration

// Environment
define('APP_ENV', 'production');              // 'development' or 'production'
define('APP_DEBUG', false);                   // false for production

// URLs
define('BASE_URL', 'https://eems.yourcollege.edu');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Session configuration
define('SESSION_LIFETIME', 1800);             // 30 minutes
define('SESSION_NAME', 'EEMS_SESSION');
define('SESSION_SECURE', true);               // true for HTTPS
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Strict');

// File upload settings
define('MAX_FILE_SIZE', 10485760);            // 10 MB in bytes
define('ALLOWED_EXTENSIONS', ['pdf']);

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

// Pagination
define('ITEMS_PER_PAGE', 20);

// College name
define('COLLEGE_NAME', 'Your College Name');
?>
```

### Step 4: PHP Configuration

Edit `php.ini` (location varies by OS):

```ini
; Error handling (production)
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php/php-error.log

; File uploads
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

; Memory and execution
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; Session configuration
session.cookie_lifetime = 0
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
session.gc_maxlifetime = 1800

; Security
expose_php = Off
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.1-fpm  # Adjust version
```

---

## Post-Deployment

### Step 1: Verify Installation

Access: `https://eems.yourcollege.edu/test.php`

Create `test.php` temporarily:
```php
<?php
// Test database connection
require_once 'config/db.php';
echo "✓ Database connection successful<br>";

// Test PHP version
echo "✓ PHP version: " . phpversion() . "<br>";

// Test extensions
$required = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'openssl'];
foreach ($required as $ext) {
    echo extension_loaded($ext) ? "✓ $ext loaded<br>" : "✗ $ext missing<br>";
}

// Test file permissions
echo is_writable('uploads') ? "✓ Uploads writable<br>" : "✗ Uploads not writable<br>";
echo is_writable('logs') ? "✓ Logs writable<br>" : "✗ Logs not writable<br>";

echo "<br><strong>All tests passed! Delete this file.</strong>";
?>
```

**Delete `test.php` after verification!**

### Step 2: Initial Login

1. Navigate to `https://eems.yourcollege.edu`
2. Click **Login**
3. Enter admin credentials:
   - Email: `admin@yourcollege.edu`
   - Password: `admin123` (or what you set)
4. **Immediately change password** in profile settings

### Step 3: System Configuration

**Add Colleges:**
1. Go to **Admin Panel** → **Colleges**
2. Add your college(s)
3. Note the college IDs for user creation

**Add Departments:**
1. Go to **Admin Panel** → **Departments**
2. Add all departments for each college

**Add Initial Users:**
1. Go to **Admin Panel** → **User Management**
2. Add Principals, HODs, Teachers
3. Send credentials securely

### Step 4: Test Email

1. Go to **Admin Panel** → **Email Settings**
2. Click **Send Test Email**
3. Verify receipt
4. Check spam folder if not received

### Step 5: Configure Notifications

1. Go to **Settings** → **Notifications**
2. Enable desired notification types
3. Test notification creation

---

## Production Optimization

### Database Optimization

**1. Run Performance Indexes:**
```bash
mysql -u eems_user -p eems_production < db/optimize_performance.sql
```

**2. Enable Query Cache:**
```sql
-- Edit /etc/mysql/my.cnf
[mysqld]
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M
```

**3. Regular Maintenance:**
```sql
-- Weekly maintenance script
OPTIMIZE TABLE exams, exam_assignments, users, question_papers;
ANALYZE TABLE exams, exam_assignments, users, question_papers;
```

### Caching

**1. OPcache (PHP Bytecode Cache):**

Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

**2. Redis (Session & Data Cache):**

Install Redis:
```bash
sudo apt install redis-server php-redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

Configure PHP sessions to use Redis in `php.ini`:
```ini
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"
```

**3. Browser Caching:**

Already configured in web server configs (expires headers, Cache-Control).

### Security Hardening

**1. File Permissions:**
```bash
# Set strict permissions
sudo chown -R www-data:www-data /var/www/html/eems
sudo find /var/www/html/eems -type d -exec chmod 755 {} \;
sudo find /var/www/html/eems -type f -exec chmod 644 {} \;
sudo chmod -R 777 /var/www/html/eems/uploads
sudo chmod -R 777 /var/www/html/eems/logs
```

**2. Firewall Configuration:**
```bash
# UFW (Ubuntu)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

**3. Fail2Ban (Brute Force Protection):**
```bash
sudo apt install fail2ban

# Create EEMS filter: /etc/fail2ban/filter.d/eems.conf
[Definition]
failregex = ^<HOST> .* "POST /login.php HTTP.*" 401
ignoreregex =

# Create jail: /etc/fail2ban/jail.d/eems.conf
[eems]
enabled = true
port = http,https
filter = eems
logpath = /var/log/nginx/eems-access.log
maxretry = 5
bantime = 3600
findtime = 600

sudo systemctl restart fail2ban
```

**4. Regular Updates:**
```bash
# Create update script: /usr/local/bin/update-eems.sh
#!/bin/bash
cd /var/www/html/eems
git pull origin main
# Run migrations if any
# Restart services if needed
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

### Backup Strategy

**1. Automated Database Backup:**

Create backup script: `/usr/local/bin/backup-eems-db.sh`
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/eems"
DB_NAME="eems_production"
DB_USER="eems_user"
DB_PASS="your_password"

# Create backup directory
mkdir -p $BACKUP_DIR

# Dump database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/eems_db_$DATE.sql.gz

# Keep only last 30 days of backups
find $BACKUP_DIR -name "eems_db_*.sql.gz" -mtime +30 -delete

echo "Backup completed: eems_db_$DATE.sql.gz"
```

Make executable:
```bash
sudo chmod +x /usr/local/bin/backup-eems-db.sh
```

Schedule with cron:
```bash
sudo crontab -e

# Add daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-eems-db.sh
```

**2. Automated File Backup:**

Create file backup script: `/usr/local/bin/backup-eems-files.sh`
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/eems"
SOURCE="/var/www/html/eems/uploads"

mkdir -p $BACKUP_DIR

# Backup uploads directory
tar -czf $BACKUP_DIR/eems_files_$DATE.tar.gz $SOURCE

# Keep only last 30 days
find $BACKUP_DIR -name "eems_files_*.tar.gz" -mtime +30 -delete

echo "File backup completed: eems_files_$DATE.tar.gz"
```

Schedule weekly:
```bash
# Add to crontab - every Sunday at 3 AM
0 3 * * 0 /usr/local/bin/backup-eems-files.sh
```

**3. Off-site Backup:**

Use rsync to copy backups to remote server:
```bash
#!/bin/bash
REMOTE_USER="backup"
REMOTE_HOST="backup.yourcollege.edu"
REMOTE_DIR="/backups/eems"

rsync -avz --delete /backups/eems/ $REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/
```

### Monitoring

**1. Install monitoring tools:**
```bash
# Install monitoring stack
sudo apt install prometheus grafana
```

**2. Application Logging:**

Create log rotation: `/etc/logrotate.d/eems`
```
/var/www/html/eems/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload nginx
    endscript
}
```

**3. Health Check Endpoint:**

Create `health.php`:
```php
<?php
require_once 'config/db.php';

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Database check
try {
    $pdo->query('SELECT 1');
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['checks']['database'] = 'error';
    $health['status'] = 'unhealthy';
}

// Uploads directory check
$health['checks']['uploads'] = is_writable('uploads') ? 'ok' : 'error';

// Logs directory check
$health['checks']['logs'] = is_writable('logs') ? 'ok' : 'error';

header('Content-Type: application/json');
echo json_encode($health);
?>
```

Monitor with cron:
```bash
# Check health every 5 minutes
*/5 * * * * curl -f https://eems.yourcollege.edu/health.php || echo "EEMS health check failed" | mail -s "EEMS Alert" admin@yourcollege.edu
```

---

## Troubleshooting

### Common Issues

**1. Database Connection Error**

**Symptoms:** "Database connection error" message

**Solutions:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection manually
mysql -u eems_user -p eems_production

# Check credentials in config/db.php
# Verify user has correct permissions:
mysql -u root -p
SHOW GRANTS FOR 'eems_user'@'localhost';
```

**2. File Upload Fails**

**Symptoms:** Upload errors, "Permission denied"

**Solutions:**
```bash
# Check directory permissions
ls -la /var/www/html/eems/uploads

# Fix permissions
sudo chmod -R 777 /var/www/html/eems/uploads
sudo chown -R www-data:www-data /var/www/html/eems/uploads

# Check PHP upload settings
php -i | grep upload

# Increase limits in php.ini if needed
upload_max_filesize = 10M
post_max_size = 10M
```

**3. Session Timeout Too Quickly**

**Symptoms:** Users logged out frequently

**Solutions:**
```bash
# Check session settings in php.ini
php -i | grep session

# Increase session lifetime
session.gc_maxlifetime = 3600  # 1 hour

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

**4. Slow Performance**

**Symptoms:** Pages load slowly

**Solutions:**
```sql
-- Run database optimizations
mysql -u eems_user -p eems_production < db/optimize_performance.sql

-- Check slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Analyze slow queries
mysqldumpslow /var/log/mysql/slow.log
```

```bash
# Enable OPcache if not enabled
# Edit php.ini
opcache.enable=1

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

**5. Email Not Sending**

**Symptoms:** Users not receiving notifications

**Solutions:**
```php
// Add to test_email.php
<?php
require_once 'config/n8n_config.php';
require_once 'includes/email.php';

$result = sendEmail(
    'test@example.com',
    'Test Email',
    'If you receive this, email is working!'
);

echo $result ? 'Email sent!' : 'Email failed!';
?>
```

Check SMTP credentials, firewall, and spam filters.

**6. SSL Certificate Issues**

**Symptoms:** "Not secure" warning, SSL errors

**Solutions:**
```bash
# Renew Let's Encrypt certificate
sudo certbot renew

# Check certificate expiry
sudo certbot certificates

# Force renewal if needed
sudo certbot renew --force-renewal
```

### Log Locations

**Application Logs:**
- `/var/www/html/eems/logs/` - Application logs
- Check `error.log`, `audit.log`

**Web Server Logs:**
- Apache: `/var/log/apache2/eems-error.log`
- Nginx: `/var/log/nginx/eems-error.log`

**PHP Logs:**
- `/var/log/php/php-error.log`
- Or check `php -i | grep error_log`

**MySQL Logs:**
- `/var/log/mysql/error.log`
- Slow query log: `/var/log/mysql/slow.log`

### Getting Help

**Documentation:**
- User Manual: `USER_MANUAL.md`
- API Documentation: `API_DOCUMENTATION.md`

**Support:**
- Email: support@eems.edu
- GitHub Issues: https://github.com/yourusername/eems/issues

**Emergency Contacts:**
- System Administrator: admin@yourcollege.edu
- Database Administrator: dba@yourcollege.edu
- IT Support: itsupport@yourcollege.edu

---

## Rollback Procedure

If deployment fails:

**1. Database Rollback:**
```bash
# Restore from backup
gunzip < /backups/eems/eems_db_TIMESTAMP.sql.gz | mysql -u eems_user -p eems_production
```

**2. Code Rollback:**
```bash
cd /var/www/html/eems
git log  # Find previous working commit
git checkout <commit-hash>
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

**3. Verify Rollback:**
```bash
# Test health endpoint
curl https://eems.yourcollege.edu/health.php

# Check logs
tail -f /var/log/nginx/eems-error.log
```

---

## Production Checklist

Before going live:

- [ ] All tests pass (database, email, uploads)
- [ ] SSL certificate installed and valid
- [ ] Admin password changed from default
- [ ] Email notifications working
- [ ] Backups configured and tested
- [ ] Monitoring setup complete
- [ ] Log rotation configured
- [ ] Firewall rules applied
- [ ] Security headers enabled
- [ ] Performance optimizations applied
- [ ] Documentation reviewed
- [ ] User training completed
- [ ] Support contacts distributed

---

*Deployment Guide v1.0 - For EEMS Version 1.0 - December 13, 2025*
