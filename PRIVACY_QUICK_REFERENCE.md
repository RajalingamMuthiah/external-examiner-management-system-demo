# Multi-College Privacy Enforcement - Quick Reference

## Privacy Model Summary

### Access Levels by Role

```
┌─────────────────┬──────────────────┬───────────────────────────┐
│ Role            │ College Access   │ Rationale                 │
├─────────────────┼──────────────────┼───────────────────────────┤
│ Teacher         │ Own college only │ Personal assignments      │
│ HOD             │ Own college only │ Department management     │
│ Principal       │ Own college only │ College administration    │
│ Vice-Principal  │ ALL colleges     │ Exam coordinator role     │
│ Admin           │ ALL colleges     │ System administration     │
└─────────────────┴──────────────────┴───────────────────────────┘
```

## Using Privacy Helper Functions

### 1. getCollegeFilterSQL()

**Purpose:** Generate SQL WHERE clause for privacy filtering

**Usage:**
```php
$collegeFilter = getCollegeFilterSQL($userRole, $userCollegeId, 'e');
$sql = "SELECT * FROM exams e WHERE $collegeFilter";
```

**Returns:**
- Teacher/HOD/Principal: `e.college_id = 5` (restricts to their college)
- VP/Admin: `1=1` (no restriction, sees all colleges)

**Example:**
```php
// For a teacher at College ID 3
$filter = getCollegeFilterSQL('teacher', 3, 'e');
// Returns: "e.college_id = 3"

// For a VP
$filter = getCollegeFilterSQL('vice_principal', 3, 'e');
// Returns: "1=1" (sees all)
```

### 2. canAccessExam()

**Purpose:** Check if user can access a specific exam

**Usage:**
```php
if (!canAccessExam($pdo, $examId, $userId, $role, $collegeId)) {
    die('Access denied');
}
```

**Returns:** `true` if user can access, `false` otherwise

**Logic:**
- VP/Admin: Always `true` (global access)
- Same college: `true` (own college exam)
- Different college: Check if assigned as examiner
- Not assigned: `false` (privacy violation)

**Example:**
```php
// Teacher trying to access exam from their college
if (canAccessExam($pdo, 123, 456, 'teacher', 5)) {
    // Show exam details
}

// VP accessing ANY exam
if (canAccessExam($pdo, 123, 789, 'vice_principal', 5)) {
    // Always true - coordinator access
}
```

## Query Patterns

### Pattern 1: Basic Exam Query with Privacy
```php
$role = $_SESSION['role'];
$collegeId = $_SESSION['college_id'];
$userId = $_SESSION['user_id'];

$collegeFilter = getCollegeFilterSQL($role, $collegeId, 'e');

$sql = "
    SELECT e.* 
    FROM exams e
    WHERE $collegeFilter
    ORDER BY e.exam_date DESC
";
```

### Pattern 2: Exam Access Validation
```php
$examId = $_GET['exam_id'];

if (!canAccessExam($pdo, $examId, $userId, $role, $collegeId)) {
    $_SESSION['error'] = 'You cannot access this exam';
    header('Location: dashboard.php');
    exit;
}

// Proceed with exam details
```

### Pattern 3: Cross-College Assignment
```php
// Teachers can be assigned to exams from OTHER colleges
// This is intentional for external examiner system

// Check if user is assigned
$stmt = $pdo->prepare("
    SELECT ea.assignment_id 
    FROM exam_assignments ea
    WHERE ea.exam_id = ? AND ea.user_id = ?
");
$stmt->execute([$examId, $userId]);

if ($stmt->fetch()) {
    // User is assigned - allow access even if different college
    $canAccess = true;
}
```

## Dashboard Implementation Guide

### Teacher Dashboard
```php
// Teachers see:
// 1. Their own assignments (any college)
// 2. Available exams from OTHER colleges

$sql = "
    SELECT e.* FROM exams e
    JOIN exam_assignments ea ON e.exam_id = ea.exam_id
    WHERE ea.user_id = ?
    
    UNION
    
    SELECT e.* FROM exams e
    WHERE e.college_id != ? 
    AND e.status = 'approved'
    AND e.exam_date >= CURDATE()
";
$stmt->execute([$userId, $userCollegeId]);
```

### HOD Dashboard
```php
// HODs see only their college exams
$collegeFilter = getCollegeFilterSQL('hod', $collegeId, 'e');

$sql = "
    SELECT e.* FROM exams e
    WHERE $collegeFilter
    ORDER BY e.created_at DESC
";
```

### Principal Dashboard
```php
// Principals see only their college exams
$collegeFilter = getCollegeFilterSQL('principal', $collegeId, 'e');

$sql = "
    SELECT e.* FROM exams e
    WHERE $collegeFilter
    ORDER BY e.exam_date DESC
";
```

### VP Dashboard (Coordinator)
```php
// VPs see ALL colleges
$collegeFilter = getCollegeFilterSQL('vice_principal', $collegeId, 'e');

$sql = "
    SELECT e.*, c.college_name
    FROM exams e
    JOIN colleges c ON e.college_id = c.college_id
    WHERE $collegeFilter
    ORDER BY e.exam_date DESC
";
// $collegeFilter will be "1=1" so no restriction
```

### Admin Dashboard
```php
// Admins see ALL colleges
$sql = "
    SELECT e.*, c.college_name
    FROM exams e
    JOIN colleges c ON e.college_id = c.college_id
    ORDER BY e.exam_date DESC
";
// No filter needed for admin
```

## Testing Privacy Enforcement

### Run Privacy Test Suite
```bash
# Open in browser:
http://localhost/eems/test_privacy_enforcement.php
```

**Tests Include:**
- Database connection
- Multi-college setup
- Privacy helper functions
- Role-based exam visibility
- Access control validation
- File audits for violations

### Manual Testing Checklist

1. **Teacher Privacy:**
   - [ ] Cannot see exams from other colleges (unless assigned)
   - [ ] Can see their assignments from any college
   - [ ] Cannot access other teachers' data

2. **HOD Privacy:**
   - [ ] Cannot see exams from other colleges
   - [ ] Cannot approve exams from other colleges
   - [ ] Cannot see other HODs' faculty

3. **Principal Privacy:**
   - [ ] Cannot lock question papers from other colleges
   - [ ] Cannot see exams from other colleges
   - [ ] Cannot approve exams from other colleges

4. **VP Coordinator Access:**
   - [ ] Can see exams from ALL colleges
   - [ ] Can coordinate across colleges
   - [ ] Can view all exam assignments

5. **Admin Access:**
   - [ ] Can access all data
   - [ ] Can modify all exams
   - [ ] Can manage all users

## Common Pitfalls to Avoid

### ❌ WRONG: Unfiltered Query
```php
// BAD - No college filter!
$sql = "SELECT * FROM exams";
```

### ✅ RIGHT: Privacy-Filtered Query
```php
// GOOD - Uses privacy helper
$collegeFilter = getCollegeFilterSQL($role, $collegeId, 'e');
$sql = "SELECT * FROM exams e WHERE $collegeFilter";
```

---

### ❌ WRONG: Hardcoded Role Check
```php
// BAD - Fragile and incomplete
if ($role === 'admin') {
    // Show all
} else {
    // Show only own college
}
```

### ✅ RIGHT: Using Privacy Helper
```php
// GOOD - Handles all roles correctly
$collegeFilter = getCollegeFilterSQL($role, $collegeId, 'e');
$sql = "SELECT * FROM exams e WHERE $collegeFilter";
```

---

### ❌ WRONG: No Access Validation
```php
// BAD - Anyone can access any exam
$examId = $_GET['id'];
$sql = "SELECT * FROM exams WHERE exam_id = ?";
```

### ✅ RIGHT: Validate Access First
```php
// GOOD - Check permission before showing
$examId = $_GET['id'];
if (!canAccessExam($pdo, $examId, $userId, $role, $collegeId)) {
    die('Access denied');
}
$sql = "SELECT * FROM exams WHERE exam_id = ?";
```

## Key Principles

1. **Default Deny:** Always restrict to own college unless role is VP/Admin
2. **Coordinator Role:** VP has global access for coordination purposes
3. **Cross-College Assignments:** External examiners can access assigned exams
4. **Helper Functions:** Always use privacy helpers instead of manual filters
5. **Validate Access:** Check permissions before displaying sensitive data

## Support

- **Documentation:** [PRIVACY_AUDIT.md](PRIVACY_AUDIT.md)
- **Test Suite:** [test_privacy_enforcement.php](test_privacy_enforcement.php)
- **Functions:** [includes/functions.php](includes/functions.php) (lines 1620-1704)

---

*Last Updated: <?= date('Y-m-d') ?>*  
*Version: 1.0*
