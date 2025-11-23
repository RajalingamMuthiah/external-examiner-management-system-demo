# SECURITY & PRIVACY IMPLEMENTATION GUIDE
## External Exam Management System (EEMS)

---

## 📋 TABLE OF CONTENTS
1. [Overview](#overview)
2. [Security Architecture](#security-architecture)
3. [Implementation Details](#implementation-details)
4. [Privacy Enforcement](#privacy-enforcement)
5. [Testing & Validation](#testing-validation)
6. [Troubleshooting](#troubleshooting)

---

## 🔒 OVERVIEW

This document outlines the comprehensive security and data privacy implementation for EEMS. The system enforces strict role-based access control (RBAC) and complete data isolation between users.

### Critical Privacy Requirement

**⚠️ ONE FACULTY MEMBER'S DATA MUST NEVER LEAK TO ANOTHER FACULTY MEMBER**

All data queries are filtered by:
- `user_id` - Ensures users only see their own data
- `college_name` - Ensures college-level users only see their institution's data  
- `role` - Ensures users only access features appropriate to their role

---

## 🏗️ SECURITY ARCHITECTURE

### Multi-Layer Security Model

```
┌─────────────────────────────────────────────────────────────┐
│                    LAYER 1: SESSION SECURITY                 │
│  • Secure cookie parameters (HTTPOnly, Secure, SameSite)    │
│  • Session hijacking prevention                             │
│  • Automatic session timeout and regeneration               │
└─────────────────────────────────────────────────────────────┘
                               ↓
┌─────────────────────────────────────────────────────────────┐
│              LAYER 2: ROLE-BASED ACCESS CONTROL              │
│  • require_auth() - Enforces authentication                 │
│  • require_role() - Enforces role requirements              │
│  • redirect_to_dashboard() - Prevents cross-dashboard access│
└─────────────────────────────────────────────────────────────┘
                               ↓
┌─────────────────────────────────────────────────────────────┐
│               LAYER 3: DATA PRIVACY FILTERING                │
│  • WHERE user_id = ? (Teacher/Faculty queries)              │
│  • WHERE college_name = ? (HOD/VP/Principal queries)        │
│  • can_access_record() - Runtime permission checks          │
└─────────────────────────────────────────────────────────────┘
                               ↓
┌─────────────────────────────────────────────────────────────┐
│            LAYER 4: INPUT/OUTPUT SANITIZATION                │
│  • sanitize_output() - XSS prevention                       │
│  • sanitize_int() - Type validation                         │
│  • Prepared statements - SQL injection prevention           │
└─────────────────────────────────────────────────────────────┘
                               ↓
┌─────────────────────────────────────────────────────────────┐
│             LAYER 5: CSRF & ATTACK PREVENTION                │
│  • CSRF tokens on all forms                                 │
│  • Rate limiting on sensitive operations                    │
│  • Security audit logging                                   │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛠️ IMPLEMENTATION DETAILS

### File Structure

```
/eems/
├── includes/
│   ├── security.php          ← NEW: Security middleware (796 lines)
│   ├── functions.php         ← EXISTING: Helper functions
│   └── assignment_widget.php
├── config/
│   └── db.php
├── logs/
│   └── security.log          ← AUTO-CREATED: Security audit trail
├── teacher_dashboard.php     ← UPDATED: Enhanced with security
├── hod_dashboard.php         ← TO UPDATE
├── dashboard.php             ← TO UPDATE (Principal)
├── VP.php                    ← TO UPDATE
└── admin_dashboard.php       ← TO UPDATE
```

### Core Security Functions

#### 1. Session Management

```php
// Initialize secure session
init_secure_session();

// Features:
// - HTTPS-only cookies in production
// - HTTPOnly flag (prevents JavaScript access)
// - SameSite=Strict (CSRF protection)
// - Session ID regeneration every 30 minutes
// - User agent validation (hijacking detection)
```

#### 2. Authentication & Authorization

```php
// Enforce login
require_auth();

// Enforce role-based access
require_role(['teacher', 'faculty'], $strict = true);

// Roles supported:
// - 'teacher' / 'faculty'
// - 'hod'
// - 'vice-principal' / 'vp'
// - 'principal'
// - 'admin'
```

#### 3. Data Privacy Helpers

```php
// Get current user's ID (or null if not logged in)
$user_id = get_current_user_id();

// Get current user's role (normalized)
$role = get_current_user_role();

// Get complete user info (ONLY for logged-in user)
$user_info = get_current_user_info($pdo);

// Check if user can access a specific record
if (can_access_record($pdo, $record_user_id, $record_college)) {
    // Allow access
}
```

#### 4. Input Sanitization

```php
// Sanitize for HTML display (XSS prevention)
echo sanitize_output($user_input);

// Sanitize for HTML attributes
<input value="<?= sanitize_attr($value) ?>">

// Sanitize and validate integers
$id = sanitize_int($_POST['id'], $default = 0);

// Sanitize email
$email = sanitize_email($_POST['email']);

// Sanitize strings (remove null bytes, trim)
$name = sanitize_string($_POST['name']);
```

#### 5. CSRF Protection

```php
// Generate CSRF token
$csrf_token = get_csrf_token();

// In HTML form:
<input type="hidden" name="csrf_token" value="<?= sanitize_attr($csrf_token) ?>">

// Validate token on submission
if (!validate_csrf_token($_POST['csrf_token'])) {
    die('Invalid security token');
}

// In AJAX (JavaScript):
const csrfToken = '<?= sanitize_attr($csrf_token) ?>';
fetch('/api/endpoint', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrfToken },
    body: JSON.stringify(data)
});
```

#### 6. Security Logging

```php
// Log security events
security_log('ROLE_VIOLATION', [
    'user_id' => $user_id,
    'attempted_role' => $role,
    'url' => $_SERVER['REQUEST_URI'],
]);

// Critical events auto-logged:
// - SESSION_HIJACK_ATTEMPT
// - ROLE_VIOLATION  
// - SQL_INJECTION_ATTEMPT
// - CSRF_TOKEN_INVALID
// - UNAUTHORIZED_ACCESS_ATTEMPT
```

---

## 🔐 PRIVACY ENFORCEMENT

### Teacher/Faculty Dashboard

**Privacy Rule:** Teacher A CANNOT see Teacher B's data

```php
// ✅ CORRECT: Query filtered by user_id
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE faculty_id = ?  -- ONLY this teacher's assignments
");
$stmt->execute([$currentUserId]);

// ❌ WRONG: Would show all teachers' assignments
$stmt = $pdo->query("SELECT * FROM assignments");
```

**Implementation in `teacher_dashboard.php`:**

```php
// Line 135: User info query
SELECT name, college_name, email 
FROM users 
WHERE id = ?  -- PRIVACY: Only current user
LIMIT 1

// Line 159: Assignment check
SELECT COUNT(*) FROM assignments 
WHERE faculty_id = ?  -- PRIVACY: Only this teacher

// Line 169: Available exams
WHERE e.department != ?  -- PRIVACY: Not from own college
AND NOT EXISTS(
    SELECT 1 FROM assignments a3
    WHERE a3.exam_id = e.id 
    AND u3.college_name = ?  -- PRIVACY: No colleague already assigned
)

// Line 215: Assigned exams
SELECT e.*, a.* FROM assignments a
JOIN exams e ON a.exam_id = e.id
WHERE a.faculty_id = ?  -- PRIVACY: Only this teacher's assignments
```

### HOD Dashboard

**Privacy Rule:** HOD at College A CANNOT see College B's data

```php
// ✅ CORRECT: Query filtered by college
$stmt = $pdo->prepare("
    SELECT e.* FROM exams e
    WHERE e.department = ?  -- ONLY this college's exams
");
$stmt->execute([$currentUserCollege]);

// ❌ WRONG: Would show all colleges' exams
$stmt = $pdo->query("SELECT * FROM exams");
```

### Principal/VP Dashboard

**Privacy Rule:** Same as HOD - college-level isolation

```php
// Exam creation
WHERE created_by = ?  -- ONLY exams created by this user

// Faculty management  
WHERE college_name = ?  -- ONLY faculty from this college

// Assignment tracking
WHERE e.department = ?  -- ONLY exams from this college
```

### Admin Dashboard

**Special Case:** Admin can see all data (for system management)

```php
if ($role === 'admin') {
    // No college filter applied
    $stmt = $pdo->query("SELECT * FROM exams");
} else {
    // Regular privacy filtering
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE department = ?");
}
```

---

## 🧪 TESTING & VALIDATION

### Test Scenarios

#### Scenario 1: Cross-Role Access Prevention

```
1. Login as Teacher
2. Try to access: /hod_dashboard.php
   Expected: 403 Forbidden OR redirect to teacher_dashboard.php
   Security Log: ROLE_VIOLATION

3. Try to access: /admin_dashboard.php
   Expected: 403 Forbidden
   Security Log: ROLE_VIOLATION
```

#### Scenario 2: Data Isolation (Same Role)

```
1. Login as Teacher A (MIT, user_id=10)
2. Note assigned exam IDs
3. Logout

4. Login as Teacher B (MIT, user_id=11)
5. Check assignments shown
   Expected: Only Teacher B's assignments visible
   Expected: Teacher A's assignments NOT visible

6. Inspect database queries (enable query logging)
   Expected: WHERE faculty_id = 11 (not 10)
```

#### Scenario 3: College Isolation

```
1. Login as HOD at MIT
2. Create exam "Advanced Thermodynamics"
3. Note exam ID
4. Logout

5. Login as HOD at Harvard
6. Check exam list
   Expected: MIT exam NOT visible
   Expected: Only Harvard exams visible
```

#### Scenario 4: CSRF Attack Prevention

```
1. Login as Teacher
2. Open browser console
3. Try manual AJAX without CSRF token:
   fetch('/teacher_dashboard.php', {
       method: 'POST',
       body: 'action=teacher_select_exam&exam_id=5'
   })
   Expected: {"success": false, "message": "Invalid security token"}
   Security Log: CSRF_TOKEN_MISSING
```

#### Scenario 5: Session Hijacking Detection

```
1. Login as Teacher A
2. Copy session cookie value
3. Change User-Agent in browser dev tools
4. Refresh page
   Expected: Logged out with error "Suspicious activity detected"
   Security Log: SESSION_HIJACK_ATTEMPT
```

### SQL Injection Testing

```
Test Input: ' OR '1'='1
Field: exam_id in AJAX request

Expected Result:
- Input sanitized by sanitize_int()
- Returns 0 (default value)
- No SQL execution
- Security Log: Potentially logged as SQL_INJECTION_ATTEMPT
```

### XSS Testing

```
Test Input: <script>alert('XSS')</script>
Field: Teacher name display

Expected Result:
- Output: &lt;script&gt;alert('XSS')&lt;/script&gt;
- Script does NOT execute
- sanitize_output() or htmlspecialchars() applied
```

---

## 🐛 TROUBLESHOOTING

### Issue: "Session expired" errors frequently

**Cause:** Session timeout too aggressive or IP address changing

**Solution:**
```php
// In security.php, line 67-68
// Increase timeout from 1800 (30 min) to 3600 (1 hour)
} elseif (time() - $_SESSION['created_at'] > 3600) {

// Or disable IP checking (lines 91-99) if using dynamic IPs
```

### Issue: CSRF token errors on valid forms

**Cause:** Token expired or session cleared

**Solution:**
```php
// In security.php validate_csrf_token()
// Increase max_age from 3600 to 7200
function validate_csrf_token($token = null, $max_age = 7200)
```

### Issue: 403 Forbidden on correct role

**Cause:** Role normalization mismatch

**Solution:**
```php
// Check database value vs normalized value
SELECT post FROM users WHERE id = ?;  -- e.g., "Vice_Principal"

// In functions.php normalize_role()
// Ensure mapping includes variant:
'vice_principal' => 'vice-principal',
```

### Issue: Teacher sees other teacher's data

**Cause:** Missing WHERE clause filter

**Solution:**
```php
// Add LIMIT 1 and user_id filter to ALL user-specific queries
SELECT * FROM assignments 
WHERE faculty_id = ?  -- ← THIS IS CRITICAL
LIMIT 1
```

### Issue: Security log not created

**Cause:** Directory permissions

**Solution:**
```bash
mkdir logs
chmod 755 logs
```

---

## 📊 SECURITY CHECKLIST

Before deploying to production, verify:

- [ ] All dashboards use `require_auth()` and `require_role()`
- [ ] All user-specific queries filter by `user_id`
- [ ] All college-specific queries filter by `college_name`
- [ ] All form submissions validate CSRF tokens
- [ ] All user inputs use `sanitize_*()` functions
- [ ] All HTML outputs use `sanitize_output()` or `htmlspecialchars()`
- [ ] All SQL queries use prepared statements (no string concatenation)
- [ ] Session cookies use Secure flag (HTTPS only in production)
- [ ] Security logging is enabled and writing to `/logs/security.log`
- [ ] Error messages don't reveal sensitive information
- [ ] Database credentials are not in version control
- [ ] All file uploads validate type and size
- [ ] Rate limiting enabled on login and sensitive operations

---

## 🔗 FILE REFERENCES

### Updated Files

1. **includes/security.php** (NEW)
   - Lines 1-40: Documentation
   - Lines 42-100: Session security
   - Lines 102-215: Role-based access control
   - Lines 217-305: Data privacy helpers
   - Lines 307-395: Input sanitization
   - Lines 397-455: CSRF protection
   - Lines 457-520: Security logging
   - Lines 522-580: SQL injection prevention
   - Lines 582-625: Rate limiting

2. **teacher_dashboard.php** (UPDATED)
   - Lines 1-30: Security initialization
   - Lines 32-130: AJAX handler with security
   - Lines 132-235: Privacy-filtered queries
   - Lines 540: CSRF token in JavaScript

### Files to Update

- `hod_dashboard.php`
- `dashboard.php` (Principal)
- `VP.php` (Vice Principal)
- `admin_dashboard.php`
- `view_exam_details.php`
- All API endpoints in `/api/` directory

---

## 📝 SUMMARY

The security implementation provides:

✅ **Session Security**: Hijacking prevention, secure cookies, timeout  
✅ **Access Control**: Role-based restrictions, 403 error pages  
✅ **Data Privacy**: User-level and college-level isolation  
✅ **Attack Prevention**: XSS, SQL injection, CSRF protection  
✅ **Audit Trail**: Comprehensive security logging  
✅ **Input Validation**: Type checking and sanitization  

**Key Principle**: Defense in depth - multiple layers ensure that even if one fails, others catch the issue.

---

*Last Updated: 2025-11-14*  
*Version: 2.0*  
*Maintainer: EEMS Security Team*
