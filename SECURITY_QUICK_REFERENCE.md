# 🔒 SECURITY QUICK REFERENCE
## EEMS - Developer Cheat Sheet

---

## 🚀 QUICK START

### Every New Page/Dashboard

```php
<?php
// 1. Include security middleware (FIRST)
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// 2. Enforce authentication
require_auth();

// 3. Enforce role-based access
require_role(['teacher', 'hod'], $strict = true);

// 4. Get current user safely
$userId = get_current_user_id();
$userInfo = get_current_user_info($pdo);
$userCollege = $userInfo['college_name'] ?? '';
```

---

## 🛡️ COMMON PATTERNS

### Pattern 1: Fetch User's Own Data

```php
// ✅ DO THIS - Filter by user_id
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE faculty_id = ?  -- PRIVACY
");
$stmt->execute([$userId]);
```

### Pattern 2: Fetch College Data

```php
// ✅ DO THIS - Filter by college_name
$stmt = $pdo->prepare("
    SELECT * FROM exams 
    WHERE department = ?  -- PRIVACY
");
$stmt->execute([$userCollege]);
```

### Pattern 3: Display User Input

```php
// ✅ DO THIS - Sanitize output
echo sanitize_output($userName);

// Or in HTML
<h1><?= sanitize_output($title) ?></h1>
<input value="<?= sanitize_attr($value) ?>">
```

### Pattern 4: Validate Integer Input

```php
// ✅ DO THIS - Sanitize and validate
$examId = sanitize_int($_POST['exam_id'], $default = 0);

if ($examId <= 0) {
    security_log('INVALID_INPUT', ['value' => $_POST['exam_id']]);
    die('Invalid input');
}
```

### Pattern 5: CSRF Protection

```php
// Generate token (in PHP)
$csrfToken = get_csrf_token();

// In HTML form
<input type="hidden" name="csrf_token" 
       value="<?= sanitize_attr($csrfToken) ?>">

// Validate on submission
if (!validate_csrf_token($_POST['csrf_token'])) {
    security_log('CSRF_VIOLATION');
    die('Invalid security token');
}
```

### Pattern 6: AJAX with CSRF

```javascript
// In JavaScript
const csrfToken = '<?= sanitize_attr($csrfToken) ?>';

fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify(data)
});
```

```php
// In PHP handler
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validate_csrf_token($token)) {
    echo json_encode(['error' => 'Invalid token']);
    exit;
}
```

---

## ⚠️ NEVER DO THESE

### ❌ SQL Injection Risks

```php
// WRONG - Concatenation
$sql = "SELECT * FROM users WHERE id = " . $_GET['id'];

// WRONG - Unsanitized input
$pdo->query("SELECT * FROM users WHERE name = '{$_POST['name']}'");
```

### ❌ XSS Vulnerabilities

```php
// WRONG - Raw output
echo $_POST['comment'];
echo $userInput;
```

### ❌ Privacy Violations

```php
// WRONG - No filtering
$stmt = $pdo->query("SELECT * FROM assignments");

// WRONG - Exposing other users' data
SELECT * FROM users;  -- Shows ALL users
```

### ❌ Authentication Bypass

```php
// WRONG - No auth check
if ($_POST['action'] == 'delete') {
    // Delete without checking login
}
```

---

## 📚 FUNCTION REFERENCE

### Security Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `require_auth()` | Force login | `require_auth();` |
| `require_role($roles)` | Enforce role | `require_role(['admin']);` |
| `get_current_user_id()` | Get user ID | `$id = get_current_user_id();` |
| `get_current_user_role()` | Get role | `$role = get_current_user_role();` |
| `get_current_user_info($pdo)` | Get full user data | `$user = get_current_user_info($pdo);` |

### Sanitization Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `sanitize_output($str)` | XSS prevention | `echo sanitize_output($name);` |
| `sanitize_attr($str)` | Attribute escaping | `value="<?= sanitize_attr($v) ?>"` |
| `sanitize_int($input, $default)` | Integer validation | `$id = sanitize_int($_POST['id'], 0);` |
| `sanitize_email($email)` | Email validation | `$email = sanitize_email($input);` |
| `sanitize_string($str)` | String cleanup | `$str = sanitize_string($input);` |

### CSRF Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `get_csrf_token()` | Generate token | `$token = get_csrf_token();` |
| `validate_csrf_token($token)` | Validate token | `if (!validate_csrf_token($t)) {...}` |

### Logging Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `security_log($event, $data)` | Log security event | `security_log('LOGIN_FAIL', [...]);` |

---

## 🎯 ROLE-BASED QUERIES

### For Teachers/Faculty

```php
// Get teacher's assignments
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE faculty_id = ?
");
$stmt->execute([$userId]);

// Get available exams (not from own college)
$stmt = $pdo->prepare("
    SELECT * FROM exams 
    WHERE department != ? 
    AND status = 'Approved'
");
$stmt->execute([$userCollege]);
```

### For HOD/VP/Principal

```php
// Get college's exams
$stmt = $pdo->prepare("
    SELECT * FROM exams 
    WHERE department = ?
");
$stmt->execute([$userCollege]);

// Get college's faculty
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE college_name = ? 
    AND post = 'teacher'
");
$stmt->execute([$userCollege]);
```

### For Admin

```php
// Admin can see all (no filtering)
if (get_current_user_role() === 'admin') {
    $stmt = $pdo->query("SELECT * FROM exams");
} else {
    // Regular users - filter by college
    $stmt = $pdo->prepare("
        SELECT * FROM exams 
        WHERE department = ?
    ");
    $stmt->execute([$userCollege]);
}
```

---

## 🔍 DEBUGGING SECURITY ISSUES

### Check Session

```php
// View current session
var_dump($_SESSION);

// Check user ID
echo "User ID: " . (get_current_user_id() ?? 'Not logged in');

// Check role
echo "Role: " . get_current_user_role();
```

### Check Security Log

```bash
# View recent security events
tail -n 50 logs/security.log

# Search for specific user
grep "user_id.*123" logs/security.log

# Find violations
grep "VIOLATION" logs/security.log
```

### Test CSRF Protection

```javascript
// Should FAIL without token
fetch('/api/endpoint', {
    method: 'POST',
    body: JSON.stringify({action: 'delete'})
});

// Should SUCCEED with token
fetch('/api/endpoint', {
    method: 'POST',
    headers: {'X-CSRF-TOKEN': csrfToken},
    body: JSON.stringify({action: 'delete'})
});
```

---

## ✅ PRE-DEPLOYMENT CHECKLIST

Before pushing to production:

```
[ ] All pages use require_auth()
[ ] All pages use require_role()
[ ] All queries filter by user_id or college_name
[ ] All user inputs sanitized
[ ] All outputs escaped (sanitize_output)
[ ] All forms have CSRF tokens
[ ] All AJAX requests validate CSRF
[ ] SQL uses prepared statements (no concatenation)
[ ] Error messages don't reveal sensitive info
[ ] Security logging enabled
[ ] Tested with different roles
[ ] Tested cross-role access (should be blocked)
[ ] Checked logs for violations
```

---

## 🆘 EMERGENCY FIXES

### Session Issues

```php
// Clear and restart session
session_destroy();
session_start();
session_regenerate_id(true);
```

### CSRF Token Issues

```php
// Force regenerate token
unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_time']);
$newToken = get_csrf_token();
```

### Access Denied Errors

```php
// Check role normalization
$role = $_SESSION['role'];
$normalized = normalize_role($role);
echo "Raw: $role, Normalized: $normalized";
```

---

## 📖 FULL DOCUMENTATION

For detailed information:

- **Implementation Guide**: `SECURITY_IMPLEMENTATION_GUIDE.md`
- **Summary**: `SECURITY_PRIVACY_SUMMARY.md`
- **Source Code**: `includes/security.php`

---

**Remember**: Security is not optional. Every line of code that touches user data or database queries MUST follow these patterns.

**Privacy First**: If you're not sure whether data should be visible to a user, default to hiding it. Better safe than sorry.

---

*Quick Reference v2.0*  
*Last Updated: November 14, 2025*
