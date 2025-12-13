# EEMS API Documentation
**Version:** 1.0  
**Last Updated:** December 13, 2025

## Overview
This document provides comprehensive API documentation for the External Exam Management System (EEMS). All endpoints use standard HTTP methods and return JSON responses.

---

## Authentication

All API endpoints require authentication via PHP sessions. Users must be logged in to access any endpoint.

**Session Variables:**
```php
$_SESSION['user_id']        // User ID
$_SESSION['role']           // User role
$_SESSION['college_id']     // User's college ID
$_SESSION['csrf_token']     // CSRF protection token
```

---

## Common Response Format

### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": { /* response data */ }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "error_code": "ERROR_CODE"
}
```

---

## Core Service Functions

Located in: `includes/functions.php`

### 1. Exam Management

#### `approveExam($pdo, $examId, $userId, $role, $comments)`
Approve an exam (Principal/Admin only)

**Parameters:**
- `$pdo` (PDO): Database connection
- `$examId` (int): Exam ID to approve
- `$userId` (int): User approving the exam
- `$role` (string): User's role
- `$comments` (string): Optional approval comments

**Returns:**
```php
[
    'success' => bool,
    'message' => string
]
```

**Example:**
```php
$result = approveExam($pdo, 123, 456, 'principal', 'Approved for scheduling');
```

---

#### `rejectExam($pdo, $examId, $userId, $role, $reason)`
Reject an exam with reason

**Parameters:**
- `$pdo` (PDO): Database connection
- `$examId` (int): Exam ID to reject
- `$userId` (int): User rejecting
- `$role` (string): User's role
- `$reason` (string): Rejection reason (required)

**Returns:** Same as approveExam()

---

### 2. Examiner Assignment

#### `createExamAssignment($pdo, $examId, $userId, $dutyType, $assignedBy)`
Assign examiner to exam

**Parameters:**
- `$examId` (int): Exam ID
- `$userId` (int): Examiner user ID
- `$dutyType` (string): 'chief_examiner', 'external_examiner', 'moderator', etc.
- `$assignedBy` (int): User ID of assigner

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'assignment_id' => int  // On success
]
```

---

#### `updateAssignmentStatus($pdo, $assignmentId, $newStatus, $userId)`
Update assignment status (accept/reject)

**Parameters:**
- `$assignmentId` (int): Assignment ID
- `$newStatus` (string): 'accepted' or 'rejected'
- `$userId` (int): User updating status

**Valid Statuses:**
- `pending` - Initial state
- `accepted` - Examiner accepted
- `rejected` - Examiner rejected
- `completed` - Duty completed

---

### 3. Question Papers

#### `lockQuestionPaper($pdo, $paperId, $userId, $role)`
Lock question paper (Principal only)

**Parameters:**
- `$paperId` (int): Question paper ID
- `$userId` (int): User locking
- `$role` (string): Must be 'principal' or 'admin'

**Returns:**
```php
[
    'success' => bool,
    'message' => string
]
```

**Workflow:**
1. Validate Principal/Admin role
2. Check if already locked
3. Update status to 'locked'
4. Set locked_by and locked_at
5. Log audit trail

---

#### `unlockQuestionPaper($pdo, $paperId, $userId, $role)`
Unlock question paper (Principal only)

**Workflow:**
1. Validate Principal/Admin role
2. Check if actually locked
3. Update status to 'unlocked'
4. Clear locked_by and locked_at
5. Log audit trail

---

### 4. Rating System

#### `rateExaminer($pdo, $examId, $examinerId, $ratingScore, $comments, $ratedBy)`
Submit rating for examiner

**Parameters:**
- `$examId` (int): Exam ID
- `$examinerId` (int): Examiner user ID
- `$ratingScore` (float): Rating (1.0 to 5.0)
- `$comments` (string): Optional feedback
- `$ratedBy` (int): Rater user ID

**Validations:**
- Rating must be between 1.0 and 5.0
- Rater must be assigned to the exam
- Examiner must be assigned to the exam
- No duplicate ratings allowed

**Returns:**
```php
[
    'success' => bool,
    'message' => string,
    'rating_id' => int  // On success
]
```

---

#### `getExaminerRatings($pdo, $examinerId)`
Get examiner's rating profile

**Returns:**
```php
[
    'average_rating' => float,
    'total_ratings' => int,
    'ratings_by_score' => [
        '5' => int,
        '4' => int,
        '3' => int,
        '2' => int,
        '1' => int
    ],
    'recent_ratings' => [
        // Array of rating objects
    ]
]
```

---

### 5. Privacy & Access Control

#### `getCollegeFilterSQL($role, $collegeId, $tableAlias)`
Generate SQL WHERE clause for privacy filtering

**Parameters:**
- `$role` (string): User role (normalized)
- `$collegeId` (int): User's college ID
- `$tableAlias` (string): Table alias in query (default: 'e')

**Returns:** SQL fragment string

**Examples:**
```php
// Teacher/HOD/Principal
getCollegeFilterSQL('teacher', 5, 'e');
// Returns: "e.college_id = 5"

// Vice-Principal/Admin (Coordinator)
getCollegeFilterSQL('vice_principal', 5, 'e');
// Returns: "1=1" (no restriction)
```

**Usage:**
```php
$filter = getCollegeFilterSQL($userRole, $userCollegeId, 'e');
$sql = "SELECT * FROM exams e WHERE $filter ORDER BY exam_date DESC";
```

---

#### `canAccessExam($pdo, $examId, $userId, $role, $collegeId)`
Check if user can access specific exam

**Returns:** `true` if access allowed, `false` otherwise

**Access Rules:**
- VP/Admin: Always allowed (coordinator role)
- Same college: Allowed
- Different college + assigned as examiner: Allowed
- Different college + not assigned: Denied

---

### 6. Utility Functions

#### `logAudit($pdo, $entityType, $entityId, $action, $userId, $details)`
Create audit log entry

**Parameters:**
- `$entityType` (string): 'exam', 'user', 'assignment', etc.
- `$entityId` (int): Entity ID
- `$action` (string): 'create', 'update', 'delete', 'approve', etc.
- `$userId` (int): User performing action
- `$details` (array): Additional details (JSON encoded)

---

#### `normalize_role($role)`
Normalize role string for consistency

**Examples:**
```php
normalize_role('Vice Principal')    // Returns: 'vice_principal'
normalize_role('vice-principal')    // Returns: 'vice_principal'
normalize_role('HOD')               // Returns: 'hod'
```

---

## AJAX Endpoints

### 1. Notifications API
**File:** `api/notifications.php`

#### GET /api/notifications.php?action=count
Get unread notification count

**Response:**
```json
{
    "success": true,
    "count": 5
}
```

---

#### GET /api/notifications.php?action=list&page=1&limit=20
Get paginated notifications

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `limit` (int): Items per page (default: 20)
- `type` (string): Filter by notification type (optional)
- `status` (string): 'read' or 'unread' (optional)

**Response:**
```json
{
    "success": true,
    "notifications": [
        {
            "id": 123,
            "notification_type": "exam_approved",
            "title": "Exam Approved",
            "message": "Your exam has been approved",
            "link": "view_exam.php?id=456",
            "created_at": "2025-12-13 10:30:00",
            "read_on": null
        }
    ],
    "total": 45,
    "page": 1,
    "total_pages": 3
}
```

---

#### POST /api/notifications.php
Mark notification as read

**POST Data:**
```json
{
    "action": "mark_read",
    "notification_id": 123,
    "csrf_token": "token_here"
}
```

---

### 2. Document Generation API
**File:** `api/generate_document.php`

#### GET /api/generate_document.php?type=schedule&exam_id=123
Generate exam document

**Parameters:**
- `type` (string): 'schedule', 'invitation', 'roster', 'report'
- `exam_id` (int): Exam ID
- `invite_id` (int): Assignment ID (for invitations only)

**Returns:** HTML document (optimized for printing)

**Document Types:**

1. **Exam Schedule** - Complete timetable with rooms and timings
2. **Invitation Letter** - Formal examiner invitation
3. **Duty Roster** - Assignment schedule for all examiners
4. **Exam Report** - Summary with statistics and ratings

---

### 3. Practical Exams API
**File:** `practical_exams.php`

#### POST /practical_exams.php
Create practical session

**POST Data:**
```json
{
    "action": "create_session",
    "exam_id": 123,
    "session_date": "2025-12-20",
    "start_time": "10:00:00",
    "end_time": "12:00:00",
    "max_students": 30,
    "lab_room": "Lab 101",
    "instructions": "Bring lab coats",
    "csrf_token": "token"
}
```

---

#### POST /practical_exams.php
Record student attempt

**POST Data:**
```json
{
    "action": "record_attempt",
    "session_id": 456,
    "student_name": "John Doe",
    "student_roll_no": "CS2021001",
    "marks_obtained": 45,
    "total_marks": 50,
    "performance_notes": "Excellent work",
    "csrf_token": "token"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Attempt recorded",
    "attempt_id": 789,
    "percentage": 90.0,
    "result": "pass"
}
```

---

## Database Schema Reference

### Key Tables

#### `exams`
```sql
exam_id INT PRIMARY KEY AUTO_INCREMENT
exam_name VARCHAR(255)
subject VARCHAR(100)
exam_date DATE
exam_type ENUM('theory', 'practical', 'viva', 'project')
duration INT (minutes)
venue VARCHAR(255)
college_id INT
status ENUM('pending', 'approved', 'completed', 'cancelled')
created_by INT
created_at TIMESTAMP
```

#### `exam_assignments`
```sql
assignment_id INT PRIMARY KEY AUTO_INCREMENT
exam_id INT
user_id INT
duty_type ENUM('chief_examiner', 'external_examiner', ...)
assignment_status ENUM('pending', 'accepted', 'rejected', 'completed')
assigned_by INT
assigned_at TIMESTAMP
```

#### `question_papers`
```sql
paper_id INT PRIMARY KEY AUTO_INCREMENT
exam_id INT
version INT
filename VARCHAR(255)
file_path VARCHAR(500)
file_size INT
status ENUM('unlocked', 'locked')
locked_by INT
locked_at TIMESTAMP
uploaded_by INT
uploaded_at TIMESTAMP
```

---

## Error Codes

| Code | Description |
|------|-------------|
| AUTH_REQUIRED | User must be logged in |
| INVALID_ROLE | User doesn't have required role |
| INVALID_CSRF | CSRF token validation failed |
| NOT_FOUND | Resource not found |
| PERMISSION_DENIED | User lacks permission |
| VALIDATION_ERROR | Input validation failed |
| DATABASE_ERROR | Database operation failed |
| DUPLICATE_ENTRY | Resource already exists |

---

## Rate Limiting

No rate limiting currently implemented. Recommended limits:
- API calls: 100 requests/minute per user
- File uploads: 10 MB max size
- Document generation: 20 requests/hour per user

---

## Best Practices

1. **Always validate CSRF tokens** for state-changing operations
2. **Check role permissions** before executing sensitive operations
3. **Use prepared statements** for all database queries
4. **Log audit trails** for important actions
5. **Handle errors gracefully** with user-friendly messages
6. **Sanitize all inputs** to prevent XSS and SQL injection
7. **Use privacy helpers** (getCollegeFilterSQL, canAccessExam) consistently

---

## Support & Contact

For API issues or questions:
- Email: support@eems.edu
- Documentation: /docs/api/
- GitHub: https://github.com/yourusername/eems

---

*API Documentation v1.0 - Generated December 13, 2025*
