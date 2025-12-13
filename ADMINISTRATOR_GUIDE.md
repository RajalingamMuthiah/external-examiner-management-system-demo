# EEMS Administrator Guide
**System Administration & Maintenance**  
**Version:** 1.0  
**Last Updated:** December 13, 2025

---

## Table of Contents
1. [Administrator Responsibilities](#administrator-responsibilities)
2. [Daily Operations](#daily-operations)
3. [User Management](#user-management)
4. [System Maintenance](#system-maintenance)
5. [Backup & Recovery](#backup--recovery)
6. [Security Management](#security-management)
7. [Performance Monitoring](#performance-monitoring)
8. [Troubleshooting](#troubleshooting)
9. [Emergency Procedures](#emergency-procedures)

---

## Administrator Responsibilities

### Primary Duties

As a system administrator for EEMS, you are responsible for:

**System Operations:**
- Ensure 24/7 system availability
- Monitor system health and performance
- Manage user accounts and permissions
- Configure system settings
- Handle backup and disaster recovery
- Apply security patches and updates

**User Support:**
- Respond to technical support requests
- Troubleshoot user issues
- Train new administrators
- Provide user documentation
- Handle password resets

**Data Management:**
- Ensure data integrity
- Manage database performance
- Monitor storage capacity
- Archive old data
- Generate system reports

**Security:**
- Monitor security logs
- Investigate security incidents
- Implement security policies
- Manage SSL certificates
- Conduct security audits

---

## Daily Operations

### Morning Routine (15 minutes)

**1. System Health Check**

Navigate to Admin Dashboard:
```
https://eems.yourcollege.edu/admin_dashboard.php
```

Check key metrics:
- ✓ System status (green = healthy)
- ✓ Active users (should match expected usage)
- ✓ Pending notifications count
- ✓ Failed email count (should be 0)
- ✓ Storage usage (< 80% recommended)
- ✓ Database connections (< 150 of 200 max)

**2. Review Error Logs**

```bash
# Check application errors from last 24 hours
tail -n 100 /var/www/html/eems/logs/error.log | grep ERROR

# Check web server errors
tail -n 100 /var/log/nginx/eems-error.log  # Nginx
# OR
tail -n 100 /var/log/apache2/eems-error.log  # Apache

# Check PHP errors
tail -n 100 /var/log/php/php-error.log
```

Action if errors found:
- Document error patterns
- Check affected functionality
- Create support tickets if needed
- Implement fixes or workarounds

**3. Database Health**

```sql
-- Login to MySQL
mysql -u eems_user -p eems_production

-- Check table sizes
SELECT 
    table_name, 
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = "eems_production"
ORDER BY (data_length + index_length) DESC;

-- Check for locks or waiting queries
SHOW PROCESSLIST;

-- Check slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

**4. Backup Verification**

```bash
# Check last backup timestamp
ls -lht /backups/eems/ | head -5

# Verify backup completed successfully
tail -n 20 /var/log/cron.log | grep backup

# Check backup size (should be consistent)
du -sh /backups/eems/eems_db_*.sql.gz | tail -5
```

### Weekly Tasks (1 hour)

**Monday Morning:**

**1. Performance Report**

Access performance dashboard:
```
https://eems.yourcollege.edu/test_performance.php
```

Review:
- Query execution times (all < 50ms?)
- Slow queries (investigate any > 100ms)
- Index usage (all queries using indexes?)
- Memory usage trends

**2. User Activity Review**

```sql
-- Top active users last week
SELECT 
    u.name, 
    u.email, 
    u.role,
    COUNT(*) as action_count
FROM audit_logs a
JOIN users u ON a.user_id = u.user_id
WHERE a.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY u.user_id
ORDER BY action_count DESC
LIMIT 20;

-- Failed login attempts
SELECT 
    email,
    COUNT(*) as failed_attempts,
    MAX(created_at) as last_attempt
FROM audit_logs
WHERE action = 'login_failed'
AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY email
HAVING failed_attempts > 5
ORDER BY failed_attempts DESC;
```

Action items:
- Investigate users with unusual activity
- Contact users with repeated failed logins
- Review and approve new user registrations

**3. Storage Cleanup**

```bash
# Check disk usage
df -h /var/www/html/eems

# Find large files in uploads (> 5 MB)
find /var/www/html/eems/uploads -type f -size +5M -exec ls -lh {} \;

# Clean up old logs (> 90 days)
find /var/www/html/eems/logs -name "*.log" -mtime +90 -delete

# Clean up old temporary files
find /tmp -name "eems_*" -mtime +7 -delete
```

**4. Database Optimization**

```sql
-- Analyze all tables
ANALYZE TABLE exams, exam_assignments, users, question_papers, 
              practical_exam_sessions, practical_exam_attempts,
              ratings, notifications, audit_logs, approvals;

-- Optimize tables
OPTIMIZE TABLE exams, exam_assignments, users, question_papers;

-- Check index fragmentation
SHOW TABLE STATUS WHERE Name = 'exams';
```

### Monthly Tasks (2-3 hours)

**First Monday of Each Month:**

**1. Security Audit**

```bash
# Review fail2ban bans
sudo fail2ban-client status eems

# Check SSL certificate expiry
sudo certbot certificates

# Review firewall rules
sudo ufw status verbose

# Check for unauthorized access attempts
sudo grep "Failed password" /var/log/auth.log | tail -50
```

**2. Update Software**

```bash
# Update system packages
sudo apt update
sudo apt list --upgradable

# Apply security updates
sudo apt upgrade

# Update PHP dependencies (if any)
cd /var/www/html/eems
composer update --no-dev

# Restart services
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

**3. User Cleanup**

```sql
-- Find inactive users (no login > 90 days)
SELECT 
    u.user_id,
    u.name,
    u.email,
    u.role,
    MAX(a.created_at) as last_activity
FROM users u
LEFT JOIN audit_logs a ON u.user_id = a.user_id
GROUP BY u.user_id
HAVING last_activity < DATE_SUB(NOW(), INTERVAL 90 DAY)
   OR last_activity IS NULL
ORDER BY last_activity;

-- Decision: Suspend or delete inactive accounts
```

**4. Generate Monthly Report**

Create monthly report covering:
- Total exams conducted
- Total users (by role)
- System uptime percentage
- Average response time
- Storage usage trends
- Top active features
- Support tickets resolved
- Incidents and resolutions

Template location: `admin_dashboard.php` → **Reports** → **Monthly Summary**

---

## User Management

### Creating Users

**Method 1: Via Admin Panel (Recommended)**

1. Go to **Admin Panel** → **User Management** → **Add User**
2. Fill form:
   - Name, Email, Role
   - College, Department
   - Auto-generate or manual password
3. Click **Create User**
4. Copy temporary password (if auto-generated)
5. Send credentials to user securely (separate email for password)

**Method 2: Via Database (Bulk Import)**

For bulk user creation, create CSV file:
```csv
name,email,role,college_id,department_id
John Doe,john@college.edu,teacher,1,5
Jane Smith,jane@college.edu,hod,1,3
```

Import script:
```php
<?php
require_once 'config/db.php';

$csv = fopen('users_import.csv', 'r');
fgetcsv($csv); // Skip header

while ($row = fgetcsv($csv)) {
    list($name, $email, $role, $college_id, $dept_id) = $row;
    
    // Generate password
    $password = bin2hex(random_bytes(4)); // 8 characters
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, college_id, 
                          department_id, status, is_verified)
        VALUES (?, ?, ?, ?, ?, ?, 'active', 1)
    ");
    
    try {
        $stmt->execute([$name, $email, $hashed, $role, $college_id, $dept_id]);
        echo "Created: $name - Password: $password\n";
    } catch (PDOException $e) {
        echo "Error creating $name: " . $e->getMessage() . "\n";
    }
}

fclose($csv);
?>
```

### Modifying Users

**Change User Role:**

```sql
-- Promote teacher to HOD
UPDATE users 
SET role = 'hod', 
    department_id = 3  -- HOD's department
WHERE user_id = 123;

-- Log the change
INSERT INTO audit_logs (entity_type, entity_id, action, user_id, details)
VALUES ('user', 123, 'role_change', 1, JSON_OBJECT('old_role', 'teacher', 'new_role', 'hod'));
```

**Reset Password:**

Via Admin Panel:
1. Go to **User Management** → Find user → **Reset Password**
2. Choose auto-generate or manual password
3. Click **Reset**
4. Copy temporary password
5. Send to user securely

Via Database:
```sql
-- Generate password hash (use online tool or PHP)
-- Example: password_hash('newpassword123', PASSWORD_DEFAULT)

UPDATE users 
SET password = '$2y$10$...' -- Your generated hash
WHERE email = 'user@college.edu';
```

**Suspend User:**

Via Admin Panel:
1. Find user in **User Management**
2. Click **Suspend** button
3. Enter reason for suspension
4. Confirm

Via Database:
```sql
UPDATE users 
SET status = 'suspended',
    suspended_at = NOW(),
    suspended_reason = 'Reason here'
WHERE user_id = 123;
```

**Delete User (Rarely Used):**

⚠️ **Warning:** Deletion removes all audit trails. Prefer suspension.

```sql
-- Only if absolutely necessary
-- First, remove related records or handle foreign keys
DELETE FROM exam_assignments WHERE user_id = 123;
DELETE FROM audit_logs WHERE user_id = 123;
DELETE FROM notifications WHERE user_id = 123;
DELETE FROM users WHERE user_id = 123;
```

### Managing Permissions

**Current Role Hierarchy:**

1. **Admin** - Full system access
2. **Vice-Principal** - Cross-college coordination
3. **Principal** - College-wide management
4. **HOD** - Department management
5. **Teacher** - Examiner duties

**Permission Matrix:**

| Action | Teacher | HOD | Principal | VP | Admin |
|--------|---------|-----|-----------|----|----|
| View own college exams | ✓ | ✓ | ✓ | ✓ | ✓ |
| View other college exams | * | ✗ | ✗ | ✓ | ✓ |
| Create exam | ✗ | ✓ | ✓ | ✓ | ✓ |
| Approve exam | ✗ | ✗ | ✓ | ✓ | ✓ |
| Assign external examiner | ✗ | ✗ | ✗ | ✓ | ✓ |
| Manage users | ✗ | ✗ | * | ✗ | ✓ |
| System settings | ✗ | ✗ | ✗ | ✗ | ✓ |

\* Limited access or special conditions

To modify permissions, edit `includes/functions.php` and relevant dashboard files.

---

## System Maintenance

### Regular Maintenance Schedule

**Daily:**
- [ ] Check system status
- [ ] Review error logs
- [ ] Verify backup completion
- [ ] Monitor disk space

**Weekly:**
- [ ] Database optimization
- [ ] Performance review
- [ ] Log cleanup
- [ ] Security log review

**Monthly:**
- [ ] Software updates
- [ ] Security audit
- [ ] User cleanup
- [ ] Generate reports

**Quarterly:**
- [ ] Full system backup test
- [ ] Disaster recovery drill
- [ ] Capacity planning review
- [ ] Documentation update

### Database Maintenance

**Weekly Optimization:**

```sql
-- Optimize tables
OPTIMIZE TABLE exams;
OPTIMIZE TABLE exam_assignments;
OPTIMIZE TABLE users;
OPTIMIZE TABLE question_papers;
OPTIMIZE TABLE practical_exam_sessions;
OPTIMIZE TABLE practical_exam_attempts;
OPTIMIZE TABLE ratings;
OPTIMIZE TABLE notifications;
OPTIMIZE TABLE audit_logs;
OPTIMIZE TABLE approvals;

-- Analyze for better query plans
ANALYZE TABLE exams;
ANALYZE TABLE exam_assignments;
ANALYZE TABLE users;
-- (repeat for all tables)

-- Check for table errors
CHECK TABLE exams;
-- (repeat for all tables)

-- Repair if errors found
REPAIR TABLE table_name;
```

**Cleanup Old Data:**

```sql
-- Archive old audit logs (> 2 years)
CREATE TABLE audit_logs_archive AS
SELECT * FROM audit_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);

DELETE FROM audit_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);

-- Archive old notifications (> 6 months, read)
DELETE FROM notifications 
WHERE read_on IS NOT NULL 
AND read_on < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Archive completed exams (> 5 years)
-- Consider moving to archive database
```

### Log Management

**Rotate Logs:**

Already configured via logrotate (`/etc/logrotate.d/eems`), but manual rotation:

```bash
# Rotate application logs
cd /var/www/html/eems/logs
for log in *.log; do
    mv "$log" "$log.$(date +%Y%m%d)"
    gzip "$log.$(date +%Y%m%d)"
    touch "$log"
done

# Clean old logs (> 30 days)
find /var/www/html/eems/logs -name "*.log.*.gz" -mtime +30 -delete
```

**Analyze Logs:**

```bash
# Most common errors
grep ERROR /var/www/html/eems/logs/error.log | cut -d' ' -f4- | sort | uniq -c | sort -rn | head -20

# Most accessed pages
awk '{print $7}' /var/log/nginx/eems-access.log | sort | uniq -c | sort -rn | head -20

# Slowest pages
awk '$NF > 1 {print $7, $NF}' /var/log/nginx/eems-access.log | sort -k2 -rn | head -20
```

### File System Maintenance

**Check Disk Usage:**

```bash
# Overall disk usage
df -h

# EEMS directory usage
du -sh /var/www/html/eems/*

# Largest files
find /var/www/html/eems -type f -exec du -h {} \; | sort -rh | head -20

# Upload directory breakdown
du -sh /var/www/html/eems/uploads/*
```

**Clean Temporary Files:**

```bash
# Remove old temporary files
find /tmp -name "php*" -mtime +7 -delete
find /var/www/html/eems/uploads/temp -type f -mtime +1 -delete

# Remove orphaned uploads (not in database)
# Run orphaned_files_checker.php script
php /var/www/html/eems/scripts/orphaned_files_checker.php
```

---

## Backup & Recovery

### Backup Strategy

**3-2-1 Rule:**
- **3** copies of data
- **2** different media types
- **1** off-site copy

### Automated Backups

**Database Backup (Daily at 2 AM):**

Script: `/usr/local/bin/backup-eems-db.sh`
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/eems"
DB_NAME="eems_production"
DB_USER="eems_user"
DB_PASS="your_password"

mkdir -p $BACKUP_DIR

# Full backup
mysqldump -u $DB_USER -p$DB_PASS \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    $DB_NAME | gzip > $BACKUP_DIR/eems_db_$DATE.sql.gz

# Verify backup
if [ $? -eq 0 ]; then
    echo "Backup successful: eems_db_$DATE.sql.gz"
    
    # Create checksum
    md5sum $BACKUP_DIR/eems_db_$DATE.sql.gz > $BACKUP_DIR/eems_db_$DATE.sql.gz.md5
    
    # Send notification
    echo "Database backup completed" | mail -s "EEMS Backup Success" admin@yourcollege.edu
else
    echo "Backup failed!" | mail -s "EEMS Backup FAILED" admin@yourcollege.edu
fi

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "eems_db_*.sql.gz" -mtime +30 -delete
find $BACKUP_DIR -name "eems_db_*.sql.gz.md5" -mtime +30 -delete
```

**File Backup (Weekly on Sunday at 3 AM):**

Script: `/usr/local/bin/backup-eems-files.sh`
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/eems"
SOURCE="/var/www/html/eems"

mkdir -p $BACKUP_DIR

# Backup uploads and critical files
tar -czf $BACKUP_DIR/eems_files_$DATE.tar.gz \
    --exclude='*.log' \
    --exclude='*.sql' \
    --exclude='.git' \
    $SOURCE/uploads \
    $SOURCE/config

if [ $? -eq 0 ]; then
    echo "File backup successful: eems_files_$DATE.tar.gz"
    md5sum $BACKUP_DIR/eems_files_$DATE.tar.gz > $BACKUP_DIR/eems_files_$DATE.tar.gz.md5
else
    echo "File backup failed!" | mail -s "EEMS File Backup FAILED" admin@yourcollege.edu
fi

# Cleanup (keep 8 weeks)
find $BACKUP_DIR -name "eems_files_*.tar.gz" -mtime +56 -delete
```

### Manual Backup

**On-Demand Full Backup:**

```bash
# Database
sudo mysqldump -u eems_user -p eems_production | gzip > /backups/eems/manual_db_$(date +%Y%m%d).sql.gz

# Files
sudo tar -czf /backups/eems/manual_files_$(date +%Y%m%d).tar.gz /var/www/html/eems

# Verify
ls -lh /backups/eems/manual_*
```

### Recovery Procedures

**Database Recovery:**

```bash
# 1. Stop web server (prevent new connections)
sudo systemctl stop nginx

# 2. Restore database
gunzip < /backups/eems/eems_db_YYYYMMDD_HHMMSS.sql.gz | mysql -u eems_user -p eems_production

# 3. Verify data
mysql -u eems_user -p eems_production -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM exams;"

# 4. Start web server
sudo systemctl start nginx

# 5. Test application
curl -I https://eems.yourcollege.edu
```

**File Recovery:**

```bash
# 1. Extract backup to temporary location
mkdir /tmp/eems_restore
tar -xzf /backups/eems/eems_files_YYYYMMDD_HHMMSS.tar.gz -C /tmp/eems_restore

# 2. Stop web server
sudo systemctl stop nginx

# 3. Restore files
sudo rsync -av /tmp/eems_restore/var/www/html/eems/uploads/ /var/www/html/eems/uploads/
sudo rsync -av /tmp/eems_restore/var/www/html/eems/config/ /var/www/html/eems/config/

# 4. Fix permissions
sudo chown -R www-data:www-data /var/www/html/eems
sudo chmod -R 755 /var/www/html/eems

# 5. Start web server
sudo systemctl start nginx
```

**Complete Disaster Recovery:**

Scenario: Complete server failure, need to restore on new server.

```bash
# 1. Setup new server (follow DEPLOYMENT_GUIDE.md)
# 2. Install EEMS application
# 3. Create database
mysql -u root -p
CREATE DATABASE eems_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eems_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL ON eems_production.* TO 'eems_user'@'localhost';

# 4. Restore database
gunzip < eems_db_latest.sql.gz | mysql -u eems_user -p eems_production

# 5. Restore files
tar -xzf eems_files_latest.tar.gz -C /

# 6. Update configuration
nano /var/www/html/eems/config/db.php  # Update credentials
nano /var/www/html/eems/config/n8n_config.php  # Update email

# 7. Fix permissions
sudo chown -R www-data:www-data /var/www/html/eems
sudo chmod -R 755 /var/www/html/eems

# 8. Test
curl -I https://eems.yourcollege.edu
```

---

## Security Management

### Security Checklist

**Monthly Security Tasks:**

- [ ] Review user access logs
- [ ] Check SSL certificate expiry
- [ ] Update system packages
- [ ] Review firewall rules
- [ ] Scan for vulnerabilities
- [ ] Update passwords (if needed)
- [ ] Review fail2ban logs
- [ ] Check file permissions

### SSL Certificate Management

**Check Certificate Status:**

```bash
# Check expiry date
sudo certbot certificates

# Manual check
echo | openssl s_client -servername eems.yourcollege.edu -connect eems.yourcollege.edu:443 2>/dev/null | openssl x509 -noout -dates
```

**Renew Certificate:**

```bash
# Automatic renewal (Let's Encrypt)
sudo certbot renew

# Force renewal
sudo certbot renew --force-renewal

# Test renewal
sudo certbot renew --dry-run
```

### Firewall Management

**View Current Rules:**

```bash
sudo ufw status verbose
```

**Modify Rules:**

```bash
# Allow specific IP
sudo ufw allow from 192.168.1.100 to any port 22

# Block specific IP
sudo ufw deny from 192.168.1.200

# Remove rule
sudo ufw delete allow 8080/tcp
```

### Fail2Ban Management

**Check Banned IPs:**

```bash
# Overall status
sudo fail2ban-client status

# EEMS jail status
sudo fail2ban-client status eems

# Unban IP
sudo fail2ban-client set eems unbanip 192.168.1.100
```

### Intrusion Detection

**Review Auth Logs:**

```bash
# Failed SSH attempts
sudo grep "Failed password" /var/log/auth.log | tail -50

# Successful SSH logins
sudo grep "Accepted password" /var/log/auth.log | tail -20

# sudo usage
sudo grep "sudo:" /var/log/auth.log | tail -20
```

**Review Application Access:**

```sql
-- Suspicious login patterns
SELECT 
    email,
    COUNT(*) as attempts,
    COUNT(DISTINCT DATE(created_at)) as days_active
FROM audit_logs
WHERE action = 'login_failed'
AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY email
HAVING attempts > 10
ORDER BY attempts DESC;

-- After-hours access
SELECT 
    u.name,
    u.email,
    a.action,
    a.created_at
FROM audit_logs a
JOIN users u ON a.user_id = u.user_id
WHERE HOUR(a.created_at) NOT BETWEEN 6 AND 22  -- Outside 6 AM - 10 PM
AND a.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY a.created_at DESC;
```

### Security Incident Response

**If Breach Suspected:**

1. **Immediate Actions:**
   ```bash
   # Take offline if severe
   sudo systemctl stop nginx
   
   # Block suspicious IPs
   sudo ufw deny from <IP_ADDRESS>
   ```

2. **Investigation:**
   - Review all logs (application, web server, database, system)
   - Identify compromised accounts
   - Document timeline of events
   - Preserve evidence

3. **Containment:**
   - Reset passwords for affected accounts
   - Review and revoke suspicious sessions
   - Apply emergency patches if vulnerability found

4. **Recovery:**
   - Restore from last known good backup
   - Apply security fixes
   - Bring system back online
   - Monitor closely

5. **Post-Incident:**
   - Conduct root cause analysis
   - Update security procedures
   - Notify affected users
   - Document lessons learned

---

## Performance Monitoring

### Key Performance Indicators (KPIs)

**System KPIs:**
- Uptime: Target > 99.9%
- Page load time: Target < 2 seconds
- Database query time: Target < 50ms average
- Failed requests: Target < 0.1%

**User KPIs:**
- Active users per day
- Exams created per week
- Average assignments per exam
- Document generation rate

### Monitoring Tools

**1. Application Performance:**

Access: `https://eems.yourcollege.edu/test_performance.php`

Monitor:
- Query execution times
- Index usage
- Slow queries (> 100ms)
- Memory usage

**2. Server Performance:**

```bash
# CPU usage
top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1"%"}'

# Memory usage
free -h

# Disk I/O
iostat -x 1 5

# Network traffic
iftop
```

**3. Database Performance:**

```sql
-- Current queries
SHOW PROCESSLIST;

-- Slow queries
SELECT * FROM mysql.slow_log 
WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY query_time DESC
LIMIT 20;

-- Table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)",
    table_rows
FROM information_schema.TABLES
WHERE table_schema = 'eems_production'
ORDER BY (data_length + index_length) DESC;

-- Index usage
SELECT 
    object_name,
    index_name,
    rows_selected,
    rows_inserted,
    rows_updated,
    rows_deleted
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE object_schema = 'eems_production'
ORDER BY rows_selected DESC;
```

**4. Web Server Performance:**

```bash
# Apache
sudo apachectl status

# Nginx
curl http://localhost/nginx_status

# Request statistics
cat /var/log/nginx/eems-access.log | awk '{print $9}' | sort | uniq -c | sort -rn
```

### Performance Optimization

**If Performance Degrading:**

1. **Identify Bottleneck:**
   - Check CPU, memory, disk, network
   - Review slow query log
   - Check application error logs

2. **Apply Quick Fixes:**
   ```sql
   -- Optimize tables
   OPTIMIZE TABLE exams, exam_assignments, users;
   
   -- Clear query cache
   RESET QUERY CACHE;
   ```

3. **Restart Services:**
   ```bash
   sudo systemctl restart php8.1-fpm
   sudo systemctl restart nginx
   sudo systemctl restart mysql
   ```

4. **Long-term Solutions:**
   - Add database indexes (see `db/optimize_performance.sql`)
   - Increase server resources
   - Implement caching (Redis/Memcached)
   - Optimize slow queries
   - Archive old data

---

## Troubleshooting

### Common Issues

**Issue 1: Users Cannot Login**

**Symptoms:**
- Login page loads but authentication fails
- "Invalid credentials" error for valid users

**Diagnosis:**
```sql
-- Check user exists and is active
SELECT user_id, email, status, is_verified 
FROM users 
WHERE email = 'user@college.edu';

-- Check recent login attempts
SELECT * FROM audit_logs 
WHERE entity_type = 'user' 
AND action LIKE '%login%'
ORDER BY created_at DESC 
LIMIT 20;
```

**Solutions:**
```bash
# Check PHP session directory
ls -la /var/lib/php/sessions/

# Fix permissions if needed
sudo chown -R www-data:www-data /var/lib/php/sessions/
sudo chmod -R 700 /var/lib/php/sessions/

# Check session settings in php.ini
php -i | grep session.save_path
```

**Issue 2: File Uploads Failing**

**Symptoms:**
- Upload progress bar completes but file not saved
- "Upload failed" error

**Diagnosis:**
```bash
# Check upload directory permissions
ls -la /var/www/html/eems/uploads/

# Check disk space
df -h /var/www/html/eems/

# Check PHP upload limits
php -i | grep upload
```

**Solutions:**
```bash
# Fix permissions
sudo chmod 777 /var/www/html/eems/uploads/
sudo chown -R www-data:www-data /var/www/html/eems/uploads/

# Increase PHP limits in php.ini
upload_max_filesize = 10M
post_max_size = 10M

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

**Issue 3: Emails Not Sending**

**Symptoms:**
- Notifications not received
- Email queue building up

**Diagnosis:**
```bash
# Check email configuration
cat /var/www/html/eems/config/n8n_config.php

# Test SMTP connection
telnet smtp.gmail.com 587

# Check mail logs
tail -f /var/log/mail.log
```

**Solutions:**
```php
// Test email manually
<?php
require_once 'config/n8n_config.php';
require_once 'includes/email.php';

$result = sendEmail(
    'your@email.com',
    'Test Subject',
    'Test body'
);

var_dump($result);
?>
```

Check:
- SMTP credentials correct
- Firewall allows port 587/465
- Gmail "Less secure app access" enabled
- Correct encryption method (TLS/SSL)

**Issue 4: Slow Performance**

**Symptoms:**
- Pages take > 5 seconds to load
- Database queries timing out

**Diagnosis:**
```bash
# Check server load
uptime
top

# Check database
mysql -u root -p
SHOW PROCESSLIST;
```

Access `test_performance.php` to identify slow queries.

**Solutions:**
```bash
# Restart services
sudo systemctl restart mysql
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx

# Optimize database
mysql -u eems_user -p eems_production < db/optimize_performance.sql

# Clear cache if implemented
redis-cli FLUSHALL
```

### Log Analysis

**Finding Errors:**

```bash
# Application errors today
grep "$(date +%Y-%m-%d)" /var/www/html/eems/logs/error.log | grep ERROR

# PHP errors today
grep "$(date +%Y-%m-%d)" /var/log/php/php-error.log

# Web server errors (last 100 lines)
tail -n 100 /var/log/nginx/eems-error.log

# Database errors
sudo tail -f /var/log/mysql/error.log
```

**Common Error Patterns:**

```bash
# Find most frequent errors
grep ERROR /var/www/html/eems/logs/error.log | cut -d':' -f3- | sort | uniq -c | sort -rn | head -10

# Failed database connections
grep "Database connection failed" /var/www/html/eems/logs/error.log | wc -l

# Permission denied errors
grep "Permission denied" /var/log/nginx/eems-error.log
```

---

## Emergency Procedures

### System Down

**Immediate Response:**

1. **Assess Situation:**
   ```bash
   # Check if services running
   sudo systemctl status nginx
   sudo systemctl status php8.1-fpm
   sudo systemctl status mysql
   
   # Check system resources
   uptime
   free -h
   df -h
   ```

2. **Restart Services:**
   ```bash
   sudo systemctl restart nginx
   sudo systemctl restart php8.1-fpm
   sudo systemctl restart mysql
   ```

3. **Check Logs:**
   ```bash
   sudo journalctl -xe  # System logs
   tail -f /var/log/nginx/eems-error.log
   tail -f /var/log/mysql/error.log
   ```

4. **Notify Users:**
   - Post status update on college website
   - Send email to active users
   - Update system status page

5. **Document:**
   - Time of incident
   - Symptoms observed
   - Actions taken
   - Resolution time

### Data Loss

**If Database Corruption:**

1. **Stop Application:**
   ```bash
   sudo systemctl stop nginx
   ```

2. **Attempt Repair:**
   ```sql
   USE eems_production;
   CHECK TABLE exams;
   REPAIR TABLE exams;
   ```

3. **Restore from Backup:**
   ```bash
   # Find latest backup
   ls -lht /backups/eems/eems_db_*.sql.gz | head -1
   
   # Restore
   gunzip < /backups/eems/eems_db_LATEST.sql.gz | mysql -u eems_user -p eems_production
   ```

4. **Verify Data:**
   ```sql
   -- Check record counts
   SELECT 'exams' as table_name, COUNT(*) as count FROM exams
   UNION SELECT 'users', COUNT(*) FROM users
   UNION SELECT 'assignments', COUNT(*) FROM exam_assignments;
   ```

5. **Resume Service:**
   ```bash
   sudo systemctl start nginx
   ```

### Security Breach

**If Breach Confirmed:**

1. **Isolate System:**
   ```bash
   # Take offline
   sudo systemctl stop nginx
   
   # Block all external access
   sudo ufw default deny incoming
   ```

2. **Preserve Evidence:**
   ```bash
   # Copy logs to secure location
   sudo tar -czf /secure/evidence_$(date +%Y%m%d_%H%M%S).tar.gz \
       /var/log \
       /var/www/html/eems/logs \
       /var/lib/mysql/eems_production
   ```

3. **Notify:**
   - IT security team
   - College administration
   - Affected users (if personal data compromised)
   - Law enforcement (if required by law)

4. **Investigate:**
   - Identify attack vector
   - Assess damage
   - Determine data accessed
   - Timeline of events

5. **Remediate:**
   - Reset all passwords
   - Apply security patches
   - Restore from clean backup
   - Implement additional security measures

6. **Document:**
   - Full incident report
   - Lessons learned
   - Policy updates
   - User communications

---

## Escalation Procedures

### Contact List

**Primary Contacts:**

| Role | Name | Email | Phone | Availability |
|------|------|-------|-------|--------------|
| System Admin | [Your Name] | admin@college.edu | +1-XXX-XXX-XXXX | 24/7 |
| Database Admin | [Name] | dba@college.edu | +1-XXX-XXX-XXXX | Business hours |
| Network Admin | [Name] | netadmin@college.edu | +1-XXX-XXX-XXXX | 24/7 |
| IT Director | [Name] | itdirector@college.edu | +1-XXX-XXX-XXXX | Business hours |

**Vendor Contacts:**

| Service | Contact | Phone | Email |
|---------|---------|-------|-------|
| Hosting Provider | [Provider] | [Phone] | support@provider.com |
| SSL Certificate | [Provider] | [Phone] | ssl@provider.com |
| Email Service | [Provider] | [Phone] | support@provider.com |

### Escalation Matrix

**Level 1: Low Priority**
- Minor bugs affecting < 5 users
- Cosmetic issues
- Non-critical feature requests

**Response:** Within 48 hours  
**Handler:** System Administrator

**Level 2: Medium Priority**
- Bugs affecting specific feature
- Performance degradation
- Moderate user impact

**Response:** Within 4 hours  
**Handler:** System Administrator → IT Director

**Level 3: High Priority**
- Major feature broken
- Security vulnerability
- Large user group affected

**Response:** Within 1 hour  
**Handler:** System Administrator + IT Director

**Level 4: Critical**
- System down
- Data breach
- Data loss
- Complete service unavailability

**Response:** Immediate  
**Handler:** All IT staff + College Administration

---

## Maintenance Windows

### Scheduled Maintenance

**Monthly Maintenance:**
- **When:** First Sunday of each month, 2 AM - 6 AM
- **Duration:** Up to 4 hours
- **Activities:**
  - Software updates
  - Database optimization
  - Server restart
  - Backup verification

**Notification Timeline:**
- 7 days before: Email to all users
- 3 days before: Dashboard notification
- 1 day before: Email reminder
- During maintenance: Status page updated

### Maintenance Mode

**Enable Maintenance Mode:**

Create `maintenance.html`:
```html
<!DOCTYPE html>
<html>
<head>
    <title>System Maintenance - EEMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            background: white;
            color: #333;
            padding: 40px;
            border-radius: 10px;
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 System Maintenance</h1>
        <p>EEMS is currently undergoing scheduled maintenance.</p>
        <p>We expect to be back online by <strong>6:00 AM</strong>.</p>
        <p>Thank you for your patience!</p>
        <p><small>For urgent issues, contact: admin@college.edu</small></p>
    </div>
</body>
</html>
```

Redirect all traffic:
```nginx
# In Nginx config
location / {
    return 503;
}

error_page 503 /maintenance.html;
location = /maintenance.html {
    root /var/www/html/eems;
    internal;
}
```

---

*Administrator Guide v1.0 - For EEMS Version 1.0 - December 13, 2025*
