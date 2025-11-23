# EEMS - Quick Reference Guide

## 🚀 Quick Start

### Using PDF Export
```html
<!-- In your dashboard PHP file -->
<div data-pdf-export="exams" data-pdf-label="Export Schedule"></div>
```

### Using Global Search
```
Press Ctrl+K anywhere in the application
Type your search query
Use arrow keys to navigate
Press Enter to open result
```

### Security Check
```php
// Automatically enabled - no code needed!
// All logins are now protected with:
// - 5 attempt limit per 15 minutes
// - Rate limiting (60 req/min)
// - IP blacklisting
// - Security logging
```

---

## 📁 New Files Reference

| File | Purpose | Lines |
|------|---------|-------|
| `includes/pdf_export.php` | PDF generation class | 400+ |
| `api/export_pdf.php` | PDF export API endpoint | 150+ |
| `public/js/pdf_export.js` | PDF download handler | 200+ |
| `public/js/global_search.js` | Search modal & keyboard shortcuts | 400+ |
| `api/global_search.php` | Search API with filtering | 150+ |
| `includes/security_manager.php` | Security & rate limiting | 600+ |

---

## ⌨️ Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Open global search |
| `↑` / `↓` | Navigate search results |
| `Enter` | Open selected result |
| `Esc` | Close search modal |

---

## 🔒 Security Features

### Login Protection
- **Max Attempts:** 5 per 15 minutes
- **Lockout:** 15 minutes after 5 failed attempts
- **IP Blocking:** Automatic for brute force attacks

### Rate Limiting
- **Limit:** 60 requests per minute per IP
- **Endpoints:** All API endpoints protected
- **Bypass:** None (applies to all users)

### Security Headers (Auto-Applied)
```
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
X-Content-Type-Options: nosniff
Content-Security-Policy: default-src 'self'...
```

---

## 📊 PDF Export Types

| Type | Description | Access Level |
|------|-------------|--------------|
| `users` | User list with filters | Admin, VP |
| `exams` | Exam schedule | All roles |
| `workload` | Faculty workload | Admin, VP, HOD |
| `analytics` | System analytics | Admin, VP |

### Export Examples

```javascript
// Users
PDFExport.exportUsers(); // Uses current filters

// Exams
PDFExport.exportExams(); // Uses current filters

// Workload
PDFExport.exportWorkload(); // Uses current college filter

// Analytics
PDFExport.exportAnalytics(); // Full system report
```

---

## 🔍 Search Capabilities

### What Can Be Searched?

**Exams:**
- Exam name/title
- Subject
- Description
- Department

**Users:** (Admin/VP only)
- Name
- Email
- College name

**Assignments:**
- Exam name
- Faculty name

### Search Results Show:
- Color-coded status badges
- Relevant metadata (date, college, role)
- Highlighted search terms
- Direct links to details

---

## 🛠️ Installation Steps

### 1. Install FPDF
```bash
composer require setasign/fpdf
```

OR download from http://www.fpdf.org/ to `vendor/fpdf/fpdf.php`

### 2. Add Security to login.php
```php
require_once 'includes/security_manager.php';
$security = new SecurityManager();
$ip = SecurityManager::getClientIP();

// Check rate limit
if (!$security->checkRateLimit($ip, 'login')) {
    // Show error
}

// Check account lockout
if ($security->isAccountLocked($email)) {
    // Show lockout message
}

// Record attempt
$security->recordLoginAttempt($ip, $email, $success);
```

### 3. Add Export Buttons
```html
<!-- In admin_dashboard.php -->
<div data-pdf-export="users"></div>

<!-- In hod_dashboard.php -->
<div data-pdf-export="exams"></div>
<div data-pdf-export="workload"></div>

<!-- In VP.php -->
<div data-pdf-export="analytics"></div>
```

### 4. Test
1. Press `Ctrl+K` - Search should open
2. Click export button - PDF should download
3. Try 6 failed logins - Account should lock

---

## 🎯 Common Tasks

### Add Custom PDF Export
```php
// In includes/pdf_export.php
public function exportCustomReport($data) {
    $this->initPDF('P', 'Custom Report');
    $this->addHeader('My Report', 'Subtitle');
    
    // Add your content
    $this->pdf->SetFont('Arial', '', 10);
    $this->pdf->Cell(0, 10, 'My content', 0, 1);
    
    $this->addFooter();
    return $this->pdf->Output('S');
}

// In api/export_pdf.php
case 'custom':
    exportToPDF('custom', $data);
    break;
```

### Check Security Logs
```sql
-- Critical events in last 24 hours
SELECT * FROM security_logs 
WHERE severity = 'critical' 
AND logged_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY logged_at DESC;

-- Blacklisted IPs
SELECT * FROM ip_blacklist 
WHERE blocked_until IS NULL OR blocked_until > NOW();

-- Failed login attempts
SELECT email, COUNT(*) as attempts
FROM login_attempts
WHERE success = FALSE
AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY email
ORDER BY attempts DESC;
```

### Manually Blacklist IP
```php
$security = new SecurityManager();
$security->blacklistIP('192.168.1.100', 'Manual block', 3600); // 1 hour
```

### Unblock IP
```sql
DELETE FROM ip_blacklist WHERE ip_address = '192.168.1.100';
```

---

## 📈 Performance Tips

### Enable OpCache (php.ini)
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

### Enable Gzip (httpd.conf or .htaccess)
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript
</IfModule>
```

### Browser Caching (.htaccess)
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
</IfModule>
```

---

## 🐛 Troubleshooting

### PDF Export Not Working
1. Check FPDF is installed: `vendor/fpdf/fpdf.php` exists
2. Check permissions: PHP can create files
3. Check browser console for errors
4. Verify API endpoint: `api/export_pdf.php` accessible

### Global Search Not Opening
1. Check JavaScript console for errors
2. Verify `global_search.js` is loaded
3. Check Bootstrap is loaded (required for modal)
4. Try `GlobalSearch.show()` in console

### Rate Limiting Too Strict
Adjust in `includes/security_manager.php`:
```php
private $maxRequestsPerMinute = 120; // Increase from 60
```

### Account Locked - How to Unlock?
```sql
-- Delete failed attempts for email
DELETE FROM login_attempts 
WHERE email = 'user@example.com' 
AND success = FALSE;
```

---

## 📞 Quick Links

- **Main Documentation:** `FINAL_IMPLEMENTATION.md`
- **Previous Features:** `IMPLEMENTATION_COMPLETE.md`
- **Security Details:** `includes/security_manager.php`
- **PDF Export:** `includes/pdf_export.php`
- **Search API:** `api/global_search.php`

---

## ✅ Feature Checklist

**All 10 Features Complete:**
- [x] Centralized CSS (main.css)
- [x] JavaScript utilities (utils.js)
- [x] Toast notifications
- [x] Mobile responsive design
- [x] Database optimization (25+ indexes)
- [x] Real-time validation
- [x] Email notifications
- [x] **PDF export system**
- [x] **Global search (Ctrl+K)**
- [x] **Security & rate limiting**

**Ready for Production!** 🎉
