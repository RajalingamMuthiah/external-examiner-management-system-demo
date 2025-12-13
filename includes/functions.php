<?php
// includes/functions.php

/**
 * =================================================================================
 * HELPER & DATABASE FUNCTIONS
 * =================================================================================
 */

// A centralized function for sending notifications
function send_notification($pdo, $user_id, $message, $type = 'in-app') {
    // In-app notification
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$user_id, $message]);

    // Email notification (requires a mailer library for production)
    if ($type === 'email' || $type === 'all') {
        $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $email = $userStmt->fetchColumn();
        if ($email) {
            $subject = "EEMS Notification";
            $headers = "From: no-reply@eems.com";
            // mail($email, $subject, $message, $headers); // Uncomment when mail server is configured
        }
    }
    // SMS placeholder
    // if ($type === 'sms' || $type === 'all') { /* ... SMS gateway logic ... */ }
}


/**
 * =================================================================================
 * EXAM & FACULTY ASSIGNMENT LOGIC
 * =================================================================================
 */

// 1. Smart Conflict Detection: Checks if a faculty is already booked for a given time slot.
function has_time_conflict($pdo, $faculty_id, $exam_date, $exam_time) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_assignments WHERE faculty_id = ? AND exam_date = ? AND exam_time = ?");
    $stmt->execute([$faculty_id, $exam_date, $exam_time]);
    return $stmt->fetchColumn() > 0;
}

// New function to check for any exam on the same day.
function hasExamConflict($faculty_id, $exam_date, $pdo) {
    // This query checks if the faculty member has any exam assignments on the specified date.
    $sql = "SELECT COUNT(*) FROM exam_assignments WHERE faculty_id = :faculty_id AND exam_date = :exam_date";
    
    // Prepare and execute the statement securely to prevent SQL injection.
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':faculty_id' => $faculty_id,
        ':exam_date' => $exam_date
    ]);
    
    // fetchColumn() returns the count. If it's greater than 0, a conflict exists.
    return $stmt->fetchColumn() > 0;
}

// 2. Workload Balancing: Gets the assignment count for all faculty.
function get_faculty_workload($pdo) {
    $sql = "SELECT u.name, COUNT(ea.id) as assignment_count
            FROM users u
            LEFT JOIN exam_assignments ea ON u.id = ea.faculty_id
            WHERE u.post IN ('teacher', 'hod')
            GROUP BY u.id
            ORDER BY assignment_count DESC";
    return $pdo->query($sql)->fetchAll();
}

// 3. Automated Availability: Checks if faculty has marked themselves as unavailable.
function is_faculty_available($pdo, $faculty_id, $exam_date) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_unavailability WHERE faculty_id = ? AND unavailable_date = ?");
    $stmt->execute([$faculty_id, $exam_date]);
    return $stmt->fetchColumn() == 0;
}

// 4. Experience-Based Matching: Gets examiners with minimum required experience.
function get_examiners_by_experience($pdo, $min_years) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE post IN ('teacher', 'hod') AND experience_years >= ?");
    $stmt->execute([$min_years]);
    return $stmt->fetchAll();
}

// 7. Conflict of Interest Prevention: Checks if examiner belongs to the exam's college.
function has_conflict_of_interest($pdo, $faculty_id, $exam_college_id) {
    $stmt = $pdo->prepare("SELECT college_id FROM users WHERE id = ?");
    $stmt->execute([$faculty_id]);
    $faculty_college_id = $stmt->fetchColumn();

    // Returns true if colleges are the same (conflict exists)
    return $faculty_college_id == $exam_college_id;
}


/**
 * =================================================================================
 * VERIFICATION & ESCALATION
 * =================================================================================
 */

// 8. Automated Escalation: Checks for pending tasks older than 48 hours and notifies admin/principal.
function check_and_escalate_pending_tasks($pdo, $admin_or_principal_id) {
    $sql = "SELECT id, user_id FROM verifications WHERE status = 'pending' AND created_at < NOW() - INTERVAL 48 HOUR";
    $stmt = $pdo->query($sql);
    $pending_tasks = $stmt->fetchAll();

    foreach ($pending_tasks as $task) {
        $message = "Escalation: Verification for user ID {$task['user_id']} has been pending for over 48 hours.";
        send_notification($pdo, $admin_or_principal_id, $message, 'all');
        // Flag task as escalated to avoid re-sending notifications
        $pdo->prepare("UPDATE verifications SET status = 'escalated' WHERE id = ?")->execute([$task['id']]);
    }
    return count($pending_tasks);
}

// 9. Hierarchical Verification: Get pending verifications for the current user's role.
function get_pending_verifications_for_user($pdo, $user_id, $user_role) {
    $sql = "";
    // Example: Principal can see verifications approved by VP or escalated tasks.
    if ($user_role === 'principal') {
        $sql = "SELECT v.id, u.name as user_to_verify, v.status
                FROM verifications v
                JOIN users u ON v.user_id = u.id
                WHERE v.status = 'pending_principal_approval' OR v.status = 'escalated'";
    }
    // Add more conditions for 'vice_principal', 'hod', etc.

    if ($sql) {
        return $pdo->query($sql)->fetchAll();
    }
    return [];
}


/**
 * =================================================================================
 * REGISTRATION & VALIDATION (For use in registration file, e.g., register.php)
 * =================================================================================
 */

// 10. Server-Side Registration Validation
function validate_registration_data($data) {
    $errors = [];
    if (empty($data['name']) || strlen($data['name']) < 2) {
        $errors['name'] = "Full name is required.";
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "A valid email is required.";
    }
    if (empty($data['password']) || strlen($data['password']) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }
    // Add checks for phone, experience, college_id etc.
    // Example: if (!preg_match('/^[0-9]{10}$/', $data['phone'])) { $errors['phone'] = "Invalid phone number."; }
    return $errors;
}

/* ---------------------------------------------------------------------------
 * AUTH, SESSION & CSRF HELPERS
 * ---------------------------------------------------------------------------
 * These helpers centralize session handling, CSRF tokens, flash messages and
 * simple role utilities. They are safe to call from any script in the app.
 * 
 * NOTE: Core security functions (start_secure_session, require_auth, etc.)
 * are now in includes/security.php. This file maintains backwards compatibility.
 */

// Load security middleware if not already loaded
if (!function_exists('init_secure_session')) {
    require_once __DIR__ . '/security.php';
}

// Alias for backwards compatibility
if (!function_exists('start_secure_session')) {
    function start_secure_session() {
        return init_secure_session();
    }
}

// HTML escape helper (backwards compatibility alias for sanitize_output)
if (!function_exists('h')) {
    function h($s) {
        if (function_exists('sanitize_output')) {
            return sanitize_output($s);
        }
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Simple flash helpers (one-time messages)
if (!function_exists('set_flash')) {
    function set_flash($key, $message) {
        start_secure_session();
        if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];
        $_SESSION['flash'][$key] = $message;
    }
}

if (!function_exists('get_flash')) {
    function get_flash($key) {
        start_secure_session();
        if (!empty($_SESSION['flash'][$key])) {
            $m = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $m;
        }
        return null;
    }
}

// CSRF token generation & verification
// NOTE: Enhanced versions in security.php, these maintain backwards compatibility
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (function_exists('get_csrf_token')) {
            return get_csrf_token();
        }
        start_secure_session();
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // Fallback to less strong token if random_bytes fails
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token, $max_age = 3600) {
        if (function_exists('validate_csrf_token')) {
            return validate_csrf_token($token, $max_age);
        }
        start_secure_session();
        if (empty($token) || empty($_SESSION['csrf_token'])) return false;
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        if (!$valid) return false;
        if (!empty($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time'] > $max_age)) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        return true;
    }
}

// Login helper: securely set session variables after authentication
// NOTE: Enhanced version available in security.php, this maintains compatibility
if (!function_exists('login_user')) {
    function login_user($userId, $username, $role) {
        start_secure_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['user_name'] = (string)$username;
        // keep compatibility with older keys
        $_SESSION['name'] = $_SESSION['user_name'];
        // Normalize role before storing to ensure consistent checks across app
        $norm = normalize_role($role);
        $_SESSION['role'] = $norm;
        $_SESSION['user_role'] = $norm;
        $_SESSION['logged_in_at'] = time();
    }
}

// Backwards compatibility aliases for security.php functions
if (!function_exists('require_login')) {
    function require_login() {
        if (function_exists('require_auth')) {
            return require_auth();
        }
        start_secure_session();
        if (empty($_SESSION['user_id'])) {
            set_flash('error', 'You must be logged in to access that page.');
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('require_role')) {
    function require_role($roles, $strict = false) {
        start_secure_session();
        if (is_string($roles)) $roles = [$roles];
        $current = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
        $current = normalize_role($current);
        // normalize allowed roles for tolerant comparisons
        $allowed = array_map('normalize_role', $roles);
        if (empty($current) || !in_array($current, $allowed, true)) {
            http_response_code(403);
            echo "<h1>403 - Unauthorized</h1><p>You do not have permission to access this page.</p><p><a href=\"login.php\">Return to login</a></p>";
            exit;
        }
    }
}

function redirect_by_role($role) {
    $r = normalize_role($role);
    switch ($r) {
        case 'principal':
            header('Location: dashboard.php'); // Principal dashboard
            break;
        case 'vice-principal':
            header('Location: VP.php'); // Vice Principal dashboard
            break;
        case 'hod':
            header('Location: hod_dashboard.php'); // HOD dashboard
            break;
        case 'admin':
        case 'system-admin':
            header('Location: admin_dashboard.php'); // Admin dashboard
            break;
        case 'teacher':
        case 'faculty':
            header('Location: teacher_dashboard.php'); // Teacher/Faculty dashboard
            break;
        default:
            header('Location: dashboard.php'); // Default dashboard
            break;
    }
    exit;
}

// Normalize role strings to canonical values used across the app.
// Accepts variants like 'vice_principal', 'Vice Principal', 'VP', 'hod', 'teacher', etc.
function normalize_role($role) {
    if (empty($role)) return '';
    $r = trim(strtolower((string)$role));
    // Normalize common separators
    $r = str_replace(['_', ' '], '-', $r);

    $map = [
        // Vice principal variants
        'vice' => 'vice-principal',
        'vp' => 'vice-principal',
        'vice-principal' => 'vice-principal',
        'viceprincipal' => 'vice-principal',
        // HOD role (preserve separately)
        'hod' => 'hod',
        'head-of-department' => 'hod',
        // Faculty/Teacher variants
        'teacher' => 'faculty',
        'faculty' => 'faculty',
        // Principal & admin
        'principal' => 'principal',
        'admin' => 'admin',
        'administrator' => 'admin',
        'system-admin' => 'admin',
        // default common user roles
        'user' => 'user',
    ];

    return $map[$r] ?? $r;
}

/**
 * =================================================================================
 * HIERARCHICAL VERIFICATION SYSTEM
 * =================================================================================
 */

/**
 * Check if the current user has authority to verify a target user based on role hierarchy
 * Hierarchy: Principal → VP → HOD → Teacher
 * Admin can verify anyone
 * 
 * @param string $verifier_role Current user's role
 * @param string $target_role Target user's role to verify
 * @return bool True if verifier can verify target
 */
function can_verify_user($verifier_role, $target_role) {
    $verifier = normalize_role($verifier_role);
    $target = normalize_role($target_role);
    
    // Admin can verify anyone
    if ($verifier === 'admin') {
        return true;
    }
    
    // Define verification hierarchy
    $hierarchy = [
        'principal' => ['vice-principal', 'vp'],
        'vice-principal' => ['hod', 'head', 'hod_incharge'],
        'hod' => ['teacher', 'faculty']
    ];
    
    // Check if verifier role exists in hierarchy
    if (!isset($hierarchy[$verifier])) {
        return false;
    }
    
    // Check if target is in verifier's allowed list
    $allowed_targets = $hierarchy[$verifier];
    foreach ($allowed_targets as $allowed) {
        if ($target === normalize_role($allowed)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get list of users that current user can verify based on role hierarchy
 * 
 * @param PDO $pdo Database connection
 * @param int $verifier_id Current user's ID
 * @param string $verifier_role Current user's role
 * @return array List of pending users that can be verified
 */
function get_verifiable_users($pdo, $verifier_id, $verifier_role) {
    $verifier_role = normalize_role($verifier_role);
    
    // Admin can see all pending users
    if ($verifier_role === 'admin') {
        $sql = "SELECT id, name, email, post, college_name, phone, created_at 
                FROM users 
                WHERE status = 'pending' 
                ORDER BY created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Define which roles each level can verify
    $verifiable_roles = [];
    switch ($verifier_role) {
        case 'principal':
            $verifiable_roles = ['vice_principal', 'vice-principal', 'vp', 'vice principal'];
            break;
        case 'vice-principal':
            $verifiable_roles = ['hod', 'head', 'hod_incharge'];
            break;
        case 'hod':
            $verifiable_roles = ['teacher', 'faculty'];
            break;
        default:
            return []; // No verification authority
    }
    
    // Build SQL with multiple post options
    if (empty($verifiable_roles)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($verifiable_roles), '?'));
    $sql = "SELECT id, name, email, post, college_name, phone, created_at 
            FROM users 
            WHERE status = 'pending' 
            AND LOWER(REPLACE(REPLACE(post, '_', '-'), ' ', '-')) IN ($placeholders)
            ORDER BY created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    
    // Normalize roles for comparison
    $normalized_roles = array_map(function($role) {
        return normalize_role($role);
    }, $verifiable_roles);
    
    $stmt->execute($normalized_roles);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verify a user and generate password, send via email/SMS
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User to verify
 * @param int $verified_by ID of user performing verification
 * @return array ['success' => bool, 'message' => string, 'password' => string]
 */
function verify_user_and_send_password($pdo, $user_id, $verified_by) {
    try {
        // Generate random password
        $password = generate_random_password();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Get user details
        $stmt = $pdo->prepare("SELECT name, email, phone, post FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Update user status and password (store both hashed and plain text)
        $sql = "UPDATE users 
                SET status = 'verified', 
                    password = ?, 
                    raw_password = ?,
                    verified_by = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hashed_password, $password, $verified_by, $user_id]);
        
        // Send password notification
        $notification_result = send_password_notification($user, $password);
        
        return [
            'success' => true,
            'message' => 'User verified successfully. Password sent to ' . $user['email'],
            'password' => $password,
            'notification_sent' => $notification_result
        ];
        
    } catch (Exception $e) {
        error_log('Verification error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()];
    }
}

/**
 * Generate a random secure password
 * 
 * @return string Random password
 */
function generate_random_password($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Send password notification via email/SMS
 * 
 * @param array $user User details (name, email, phone, post)
 * @param string $password Generated password
 * @return bool Success status
 */
function send_password_notification($user, $password) {
    // Email notification
    $to = $user['email'];
    $subject = 'EEMS - Your Account has been Verified';
    $message = "Dear {$user['name']},\n\n";
    $message .= "Your EEMS account has been verified!\n\n";
    $message .= "Role: {$user['post']}\n";
    $message .= "Email: {$user['email']}\n";
    $message .= "Password: {$password}\n\n";
    $message .= "Please login at: http://localhost/external/eems/login.php\n\n";
    $message .= "For security, please change your password after first login.\n\n";
    $message .= "Best regards,\nEEMS Team";
    
    $headers = "From: noreply@eems.local\r\n";
    $headers .= "Reply-To: support@eems.local\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Attempt to send email (will work if mail server is configured)
    $email_sent = @mail($to, $subject, $message, $headers);
    
    // TODO: Implement SMS sending via Twilio/MSG91 API
    // For now, we'll log the password
    error_log("Password for {$user['email']}: {$password}");
    
    return $email_sent;
}

/**
 * Get verification statistics for dashboard
 * 
 * @param PDO $pdo Database connection
 * @param string $role User's role
 * @return array Statistics about verifiable users
 */
function get_verification_stats($pdo, $role) {
    $role = normalize_role($role);
    
    $verifiable_roles = [];
    switch ($role) {
        case 'admin':
            // Admin sees all pending
            $sql = "SELECT COUNT(*) as count FROM users WHERE status = 'pending'";
            $stmt = $pdo->query($sql);
            return ['pending_count' => $stmt->fetchColumn()];
            
        case 'principal':
            $verifiable_roles = ['vice_principal', 'vice-principal', 'vp'];
            break;
        case 'vice-principal':
            $verifiable_roles = ['hod', 'head'];
            break;
        case 'hod':
            $verifiable_roles = ['teacher', 'faculty'];
            break;
        default:
            return ['pending_count' => 0];
    }
    
    if (empty($verifiable_roles)) {
        return ['pending_count' => 0];
    }
    
    $placeholders = implode(',', array_fill(0, count($verifiable_roles), '?'));
    $sql = "SELECT COUNT(*) as count 
            FROM users 
            WHERE status = 'pending' 
            AND LOWER(REPLACE(REPLACE(post, '_', '-'), ' ', '-')) IN ($placeholders)";
    
    $stmt = $pdo->prepare($sql);
    $normalized_roles = array_map('normalize_role', $verifiable_roles);
    $stmt->execute($normalized_roles);
    
    return ['pending_count' => $stmt->fetchColumn()];
}

/**
 * =================================================================================
 * SHARED BUSINESS LOGIC FUNCTIONS FOR EEMS
 * =================================================================================
 */

/**
 * Get visible exams for a user based on their role and college/department
 * 
 * @param PDO $pdo Database connection
 * @param int $userId Current user's ID
 * @param string $role User's role (admin, principal, vice_principal, hod, teacher)
 * @param int|null $collegeId User's college ID
 * @param int|null $departmentId User's department ID
 * @return array List of exams visible to this user
 */
function getVisibleExamsForUser($pdo, $userId, $role, $collegeId = null, $departmentId = null) {
    $role = normalize_role($role);
    
    try {
        if ($role === 'admin') {
            // Admin sees all exams across all colleges
            $sql = "SELECT e.*, 
                           u.name as created_by_name,
                           u.college_name as creator_college
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.id
                    ORDER BY e.exam_date DESC, e.created_at DESC";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if ($role === 'principal') {
            // Principal sees exams for their college only
            if (!$collegeId) {
                // Fallback: get college from user record if not provided
                $userStmt = $pdo->prepare("SELECT college_id FROM users WHERE user_id = ?");
                $userStmt->execute([$userId]);
                $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                $collegeId = $userData['college_id'] ?? null;
            }
            
            $sql = "SELECT e.*, 
                           u.name as created_by_name,
                           u.college_name as creator_college
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.user_id
                    WHERE e.college_id = ?
                    ORDER BY e.exam_date DESC, e.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$collegeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if ($role === 'vice-principal' || $role === 'vice_principal') {
            // Vice-Principal is a COORDINATOR - sees ALL colleges for coordination purposes
            $sql = "SELECT e.*, 
                           u.name as created_by_name,
                           u.college_name as creator_college,
                           c.college_name as exam_college_name
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.user_id
                    LEFT JOIN colleges c ON e.college_id = c.college_id
                    ORDER BY e.exam_date DESC, e.created_at DESC";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if ($role === 'hod') {
            // HOD sees exams for their department
            if (!$departmentId) {
                // Fallback: get department from user record
                $userStmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                $departmentId = $userData['department_id'] ?? null;
            }
            
            $sql = "SELECT e.*, 
                           u.name as created_by_name,
                           u.college_name as creator_college
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.department_id = ?
                    ORDER BY e.exam_date DESC, e.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if ($role === 'teacher' || $role === 'faculty' || $role === 'external_examiner') {
            // Teacher sees:
            // 1. Exams they are assigned to (via assignments table)
            // 2. Approved exams from OTHER colleges (for self-selection)
            
            // Get teacher's college to filter out own college exams
            $userStmt = $pdo->prepare("SELECT college_name, college_id FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
            $userCollege = $userData['college_name'] ?? '';
            $userCollegeId = $userData['college_id'] ?? null;
            
            // Get assigned exams
            $sql = "SELECT e.*, 
                           u.name as created_by_name,
                           u.college_name as creator_college,
                           a.role as assignment_role,
                           a.status as assignment_status,
                           a.assigned_at,
                           'assigned' as exam_source
                    FROM exams e
                    INNER JOIN assignments a ON e.id = a.exam_id
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE a.faculty_id = ?
                    
                    UNION
                    
                    SELECT e.*,
                           u.name as created_by_name,
                           u.college_name as creator_college,
                           NULL as assignment_role,
                           NULL as assignment_status,
                           NULL as assigned_at,
                           'available' as exam_source
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.status = 'approved'
                      AND e.exam_date >= CURDATE()
                      AND e.department != ?
                      AND NOT EXISTS(
                          SELECT 1 FROM assignments a2
                          INNER JOIN users u2 ON a2.faculty_id = u2.id
                          WHERE a2.exam_id = e.id AND u2.college_name = ?
                      )
                    
                    ORDER BY exam_date DESC, created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $userCollege, $userCollege]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [];
    } catch (PDOException $e) {
        error_log('getVisibleExamsForUser error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Create a new exam with proper role and privacy checks
 * 
 * @param PDO $pdo Database connection
 * @param array $data Exam data (title, course_code, exam_date, start_time, end_time, etc.)
 * @param int $createdByUserId User creating the exam
 * @param string $role User's role
 * @param int|null $collegeId User's college ID
 * @param int|null $departmentId User's department ID
 * @return array ['success' => bool, 'message' => string, 'exam_id' => int|null]
 */
function createExam($pdo, $data, $createdByUserId, $role, $collegeId = null, $departmentId = null) {
    $role = normalize_role($role);
    
    try {
        // Validate required fields
        if (empty($data['title']) || empty($data['exam_date'])) {
            return ['success' => false, 'message' => 'Title and exam date are required', 'exam_id' => null];
        }
        
        // Determine college_id and department_id based on role
        if ($role === 'admin' || $role === 'principal' || $role === 'vice-principal') {
            // These roles can specify college/department or use their own
            $examCollegeId = $data['college_id'] ?? $collegeId;
            $examDepartmentId = $data['department_id'] ?? $departmentId;
        } elseif ($role === 'hod') {
            // HOD can only create exams for their own college and department
            $examCollegeId = $collegeId;
            $examDepartmentId = $departmentId;
        } else {
            return ['success' => false, 'message' => 'You do not have permission to create exams', 'exam_id' => null];
        }
        
        // Insert exam
        $sql = "INSERT INTO exams (
                    title, course_code, exam_date, start_time, end_time, 
                    college_id, department_id, created_by_user_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['course_code'] ?? null,
            $data['exam_date'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
            $examCollegeId,
            $examDepartmentId,
            $createdByUserId,
            $data['status'] ?? 'draft'
        ]);
        
        $examId = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Exam created successfully',
            'exam_id' => $examId
        ];
    } catch (PDOException $e) {
        error_log('createExam error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'exam_id' => null];
    }
}

/**
 * Assign faculty to an exam with conflict and availability checks
 * 
 * @param PDO $pdo Database connection
 * @param int $examId Exam ID
 * @param int $facultyUserId Faculty user ID
 * @param string $roleAssigned Role (invigilator, paper_setter, valuator)
 * @param string $dutyType Duty type description
 * @return array ['success' => bool, 'message' => string]
 */
function assignFacultyToExam($pdo, $examId, $facultyUserId, $roleAssigned, $dutyType = '') {
    try {
        // Get exam details
        $examStmt = $pdo->prepare("SELECT exam_date, start_time, end_time FROM exams WHERE id = ?");
        $examStmt->execute([$examId]);
        $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            return ['success' => false, 'message' => 'Exam not found'];
        }
        
        // Check faculty availability
        $availStmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_availability 
                                    WHERE faculty_user_id = ? AND unavailable_date = ?");
        $availStmt->execute([$facultyUserId, $exam['exam_date']]);
        
        if ($availStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Faculty is unavailable on this date'];
        }
        
        // Check for conflicting assignments (same date and overlapping time)
        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*) FROM exam_assignments ea
            INNER JOIN exams e ON ea.exam_id = e.id
            WHERE ea.faculty_user_id = ? 
            AND e.exam_date = ?
            AND ea.status = 'assigned'
        ");
        $conflictStmt->execute([$facultyUserId, $exam['exam_date']]);
        
        if ($conflictStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Faculty already has an assignment on this date'];
        }
        
        // Check if already assigned to this exam
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM exam_assignments 
                                    WHERE exam_id = ? AND faculty_user_id = ?");
        $checkStmt->execute([$examId, $facultyUserId]);
        
        if ($checkStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Faculty is already assigned to this exam'];
        }
        
        // Insert assignment
        $insertStmt = $pdo->prepare("
            INSERT INTO exam_assignments (exam_id, faculty_user_id, role_assigned, duty_type, status, created_at)
            VALUES (?, ?, ?, ?, 'assigned', NOW())
        ");
        $insertStmt->execute([$examId, $facultyUserId, $roleAssigned, $dutyType]);
        
        return ['success' => true, 'message' => 'Faculty assigned successfully'];
    } catch (PDOException $e) {
        error_log('assignFacultyToExam error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get faculty list for a college and department with privacy rules
 * 
 * @param PDO $pdo Database connection
 * @param int|null $collegeId College ID filter
 * @param int|null $departmentId Department ID filter (optional)
 * @param string $role Role filter (default: 'teacher')
 * @return array List of faculty members
 */
function getFacultyForCollegeAndDepartment($pdo, $collegeId, $departmentId = null, $role = 'teacher') {
    try {
        $sql = "SELECT u.id, u.name, u.email, u.post, u.phone, 
                       c.name as college_name, d.name as department_name
                FROM users u
                LEFT JOIN colleges c ON u.college_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.status = 'verified' AND u.college_id = ?";
        
        $params = [$collegeId];
        
        if ($departmentId !== null) {
            $sql .= " AND u.department_id = ?";
            $params[] = $departmentId;
        }
        
        if ($role !== 'all') {
            $sql .= " AND LOWER(REPLACE(u.post, '_', '-')) = ?";
            $params[] = normalize_role($role);
        }
        
        $sql .= " ORDER BY u.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getFacultyForCollegeAndDepartment error: ' . $e->getMessage());
        return [];
    }
}

// ============================================================================
// EXAM SUBMISSION AND APPROVAL WORKFLOW FUNCTIONS
// Implements Teacher → HOD → Approved flow
// ============================================================================

/**
 * Submit exam for approval (Teacher submits to HOD)
 * 
 * @param PDO $pdo Database connection
 * @param int $examId Exam ID to submit
 * @param int $userId User ID submitting
 * @param string $role User's role
 * @return array ['success' => bool, 'message' => string]
 * 
 * Roles allowed: teacher, admin
 * Tables touched: exams, notifications, audit_logs
 * Workflow: Changes status from 'draft' to 'submitted', notifies HOD
 */
function submitExamForApproval($pdo, $examId, $userId, $role) {
    try {
        // Get exam details
        $stmt = $pdo->prepare("SELECT id, title, created_by, department_id, college_id, status FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            return ['success' => false, 'message' => 'Exam not found'];
        }
        
        // Permission check: only creator or admin can submit
        if ($exam['created_by'] != $userId && normalize_role($role) !== 'admin') {
            return ['success' => false, 'message' => 'You do not have permission to submit this exam'];
        }
        
        // Check if already submitted
        if ($exam['status'] !== 'draft') {
            return ['success' => false, 'message' => 'Exam has already been submitted'];
        }
        
        // Update status to submitted
        $stmt = $pdo->prepare("UPDATE exams SET status = 'submitted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$examId]);
        
        // Find HOD for this department
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE department_id = ? AND post = 'hod' AND status = 'verified' LIMIT 1");
        $stmt->execute([$exam['department_id']]);
        $hod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hod) {
            // Notify HOD
            sendNotification($pdo, $hod['id'], 'exam_submitted', [
                'exam_id' => $examId,
                'exam_title' => $exam['title'],
                'submitted_by' => $userId,
                'message' => 'New exam submitted for approval'
            ]);
        }
        
        // Audit log
        logAudit($pdo, 'exam', $examId, 'submit_for_approval', $userId, [
            'exam_title' => $exam['title'],
            'department_id' => $exam['department_id']
        ]);
        
        return ['success' => true, 'message' => 'Exam submitted for approval successfully'];
        
    } catch (PDOException $e) {
        error_log('submitExamForApproval error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Approve exam (HOD/Principal approves submitted exam)
 * 
 * @param PDO $pdo Database connection
 * @param int $examId Exam ID to approve
 * @param int $approverId User ID approving
 * @param string $approverRole Approver's role
 * @param string $comments Optional approval comments
 * @return array ['success' => bool, 'message' => string]
 * 
 * Roles allowed: hod, principal, vice_principal, admin
 * Tables touched: exams, approvals, notifications, audit_logs
 * Workflow: Changes status to 'approved', creates approval record, notifies creator
 */
function approveExam($pdo, $examId, $approverId, $approverRole, $comments = '') {
    try {
        // Get exam details
        $stmt = $pdo->prepare("SELECT id, title, created_by, department_id, college_id, status FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            return ['success' => false, 'message' => 'Exam not found'];
        }
        
        // Check if exam is in submittable status
        if (!in_array($exam['status'], ['submitted', 'draft'])) {
            return ['success' => false, 'message' => 'Exam cannot be approved in current status'];
        }
        
        // Update exam status
        $stmt = $pdo->prepare("UPDATE exams SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$examId]);
        
        // Create approval record
        $stmt = $pdo->prepare("INSERT INTO approvals (exam_id, approver_id, approver_role, decision, comments, created_at) VALUES (?, ?, ?, 'approved', ?, NOW())");
        $stmt->execute([$examId, $approverId, $approverRole, $comments]);
        
        // Notify exam creator
        sendNotification($pdo, $exam['created_by'], 'exam_approved', [
            'exam_id' => $examId,
            'exam_title' => $exam['title'],
            'approved_by' => $approverId,
            'comments' => $comments,
            'message' => 'Your exam has been approved'
        ]);
        
        // Audit log
        logAudit($pdo, 'exam', $examId, 'approve', $approverId, [
            'exam_title' => $exam['title'],
            'approver_role' => $approverRole,
            'comments' => $comments
        ]);
        
        return ['success' => true, 'message' => 'Exam approved successfully'];
        
    } catch (PDOException $e) {
        error_log('approveExam error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Reject exam (HOD/Principal rejects submitted exam)
 * 
 * @param PDO $pdo Database connection
 * @param int $examId Exam ID to reject
 * @param int $approverId User ID rejecting
 * @param string $approverRole Approver's role
 * @param string $comments Reason for rejection (required)
 * @return array ['success' => bool, 'message' => string]
 * 
 * Roles allowed: hod, principal, vice_principal, admin
 * Tables touched: exams, approvals, notifications, audit_logs
 * Workflow: Changes status to 'rejected' or 'draft' if changes requested, notifies creator
 */
function rejectExam($pdo, $examId, $approverId, $approverRole, $comments, $requestChanges = false) {
    try {
        if (empty($comments)) {
            return ['success' => false, 'message' => 'Please provide a reason for rejection'];
        }
        
        // Get exam details
        $stmt = $pdo->prepare("SELECT id, title, created_by, department_id, college_id, status FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            return ['success' => false, 'message' => 'Exam not found'];
        }
        
        // Determine new status
        $newStatus = $requestChanges ? 'draft' : 'rejected';
        $decision = $requestChanges ? 'changes_requested' : 'rejected';
        
        // Update exam status
        $stmt = $pdo->prepare("UPDATE exams SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $examId]);
        
        // Create approval record
        $stmt = $pdo->prepare("INSERT INTO approvals (exam_id, approver_id, approver_role, decision, comments, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$examId, $approverId, $approverRole, $decision, $comments]);
        
        // Notify exam creator
        $message = $requestChanges ? 'Changes requested for your exam' : 'Your exam has been rejected';
        sendNotification($pdo, $exam['created_by'], 'exam_rejected', [
            'exam_id' => $examId,
            'exam_title' => $exam['title'],
            'rejected_by' => $approverId,
            'comments' => $comments,
            'request_changes' => $requestChanges,
            'message' => $message
        ]);
        
        // Audit log
        logAudit($pdo, 'exam', $examId, $decision, $approverId, [
            'exam_title' => $exam['title'],
            'approver_role' => $approverRole,
            'comments' => $comments
        ]);
        
        return ['success' => true, 'message' => $requestChanges ? 'Changes requested successfully' : 'Exam rejected successfully'];
        
    } catch (PDOException $e) {
        error_log('rejectExam error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================================================
// INVITE MANAGEMENT FUNCTIONS
// Handles external examiner invitations with token-based responses
// ============================================================================

/**
 * Invite examiner to exam (internal or external)
 * 
 * @param PDO $pdo Database connection
 * @param int $examId Exam ID
 * @param mixed $inviteeIdentifier User ID (int) or email (string) for external
 * @param string $role Role: moderator, evaluator, invigilator, paper_setter, external_examiner
 * @param int $createdBy User ID creating invite
 * @param string $inviteeName Optional name for external invitee
 * @param int $isExternal Whether examiner is external (1) or internal (0)
 * @param string $dutyType Type of duty: theory, practical, viva, etc.
 * @return array ['success' => bool, 'message' => string, 'token' => string, 'invite_id' => int]
 * 
 * Roles allowed: teacher, hod, principal, vice_principal, admin
 * Tables touched: exam_invites, notifications, audit_logs
 * Workflow: Creates invite with unique token, sends notification/email
 */
function inviteExaminer($pdo, $examId, $inviteeIdentifier, $role, $createdBy, $inviteeName = null, $isExternal = 1, $dutyType = 'theory') {
    try {
        // Get exam details
        $stmt = $pdo->prepare("SELECT id, title, exam_date, start_time, end_time, college_id, department_id FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            return ['success' => false, 'message' => 'Exam not found'];
        }
        
        // Determine if internal user or external email
        $inviteeUserId = null;
        $inviteeEmail = '';
        $inviteeName = $inviteeName ?? '';
        
        if (is_numeric($inviteeIdentifier) && $inviteeIdentifier > 0) {
            // Internal user
            $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND status = 'verified'");
            $stmt->execute([$inviteeIdentifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            $inviteeUserId = $user['id'];
            $inviteeEmail = $user['email'];
            $inviteeName = $user['name'];
            $isExternal = 0; // Override to internal if user ID provided
        } else {
            // External email
            if (!filter_var($inviteeIdentifier, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            $inviteeEmail = $inviteeIdentifier;
        }
        
        // Check if already invited
        $checkStmt = $pdo->prepare("SELECT id FROM exam_invites WHERE exam_id = ? AND invitee_email = ? AND status != 'cancelled'");
        $checkStmt->execute([$examId, $inviteeEmail]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'This examiner has already been invited to this exam'];
        }
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        
        // Create invite with additional fields
        $stmt = $pdo->prepare("
            INSERT INTO exam_invites 
            (exam_id, invitee_user_id, email, name, role, token, status, invited_by, created_at, is_external, duty_type) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), ?, ?)
        ");
        $stmt->execute([$examId, $inviteeUserId, $inviteeEmail, $inviteeName, $role, $token, $createdBy, $isExternal, $dutyType]);
        $inviteId = $pdo->lastInsertId();
        
        // Get college name for email
        $collegeStmt = $pdo->prepare("
            SELECT u.college_name 
            FROM users u 
            WHERE u.id = ?
        ");
        $collegeStmt->execute([$createdBy]);
        $collegeData = $collegeStmt->fetch(PDO::FETCH_ASSOC);
        $collegeName = $collegeData['college_name'] ?? 'Unknown College';
        
        // Send in-app notification for internal users
        if ($inviteeUserId) {
            sendNotification($pdo, $inviteeUserId, 'exam_invite', [
                'exam_id' => $examId,
                'exam_title' => $exam['title'],
                'role' => $role,
                'token' => $token,
                'invite_id' => $inviteId,
                'message' => "You've been invited as " . ucfirst($role) . " for exam: " . $exam['title']
            ]);
        }
        
        // Send email with invite link
        require_once __DIR__ . '/email.php';
        $emailSent = sendEmail('examiner_invite', $inviteeEmail, [
            'name' => $inviteeName,
            'exam_title' => $exam['title'],
            'exam_date' => $exam['exam_date'],
            'exam_time' => $exam['start_time'] ?? null,
            'role' => $role,
            'duty_type' => $dutyType,
            'college' => $collegeName,
            'token' => $token
        ]);
        
        // Audit log
        logAudit($pdo, 'exam_invite', $inviteId, 'create', $createdBy, [
            'exam_id' => $examId,
            'exam_title' => $exam['title'],
            'invitee_email' => $inviteeEmail,
            'role' => $role,
            'is_external' => $inviteeUserId === null,
            'email_sent' => $emailSent
        ]);
        
        return [
            'success' => true,
            'message' => 'Invitation sent successfully' . ($emailSent ? ' (email delivered)' : ' (email pending)'),
            'token' => $token,
            'invite_id' => $inviteId,
            'email_sent' => $emailSent
        ];
        
    } catch (PDOException $e) {
        error_log('inviteExaminer error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Respond to exam invite (accept/decline)
 * 
 * @param PDO $pdo Database connection
 * @param string $token Unique invite token
 * @param string $response 'accepted' or 'declined'
 * @param string $comment Optional response comment
 * @param array $availability Optional availability dates (JSON)
 * @return array ['success' => bool, 'message' => string, 'exam' => array]
 * 
 * Roles allowed: any (public endpoint via token)
 * Tables touched: exam_invites, exam_assignments, notifications, audit_logs
 * Workflow: Updates invite status, creates assignment if accepted, notifies creator
 */
function respondToInvite($pdo, $token, $response, $comment = '', $availability = null) {
    try {
        // Validate response
        if (!in_array($response, ['accepted', 'declined'])) {
            return ['success' => false, 'message' => 'Invalid response'];
        }
        
        // Get invite details
        $stmt = $pdo->prepare("SELECT ei.*, e.title as exam_title, e.exam_date, e.created_by FROM exam_invites ei JOIN exams e ON ei.exam_id = e.id WHERE ei.token = ?");
        $stmt->execute([$token]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invite) {
            return ['success' => false, 'message' => 'Invalid or expired invitation'];
        }
        
        // Check if already responded
        if ($invite['status'] !== 'pending') {
            return ['success' => false, 'message' => 'You have already responded to this invitation'];
        }
        
        // Update invite
        $stmt = $pdo->prepare("UPDATE exam_invites SET status = ?, response_comment = ?, availability_dates = ?, responded_on = NOW() WHERE invite_id = ?");
        $availabilityJson = $availability ? json_encode($availability) : null;
        $stmt->execute([$response, $comment, $availabilityJson, $invite['invite_id']]);
        
        // If accepted, create exam assignment
        if ($response === 'accepted' && $invite['invitee_user_id']) {
            $stmt = $pdo->prepare("INSERT INTO exam_assignments (exam_id, faculty_user_id, role_assigned, duty_type, status, assigned_by, assigned_at) VALUES (?, ?, ?, ?, 'assigned', ?, NOW())");
            $stmt->execute([$invite['exam_id'], $invite['invitee_user_id'], $invite['role'], $invite['role'], $invite['created_by']]);
        }
        
        // Notify exam creator
        $message = $response === 'accepted' ? 'Invitation accepted' : 'Invitation declined';
        sendNotification($pdo, $invite['created_by'], 'invite_response', [
            'exam_id' => $invite['exam_id'],
            'exam_title' => $invite['exam_title'],
            'invitee_name' => $invite['invitee_name'] ?? $invite['invitee_email'],
            'role' => $invite['role'],
            'response' => $response,
            'comment' => $comment,
            'message' => $message . ' for ' . $invite['exam_title']
        ]);
        
        // Audit log
        logAudit($pdo, 'exam_invite', $invite['invite_id'], 'respond_' . $response, $invite['invitee_user_id'], [
            'exam_id' => $invite['exam_id'],
            'exam_title' => $invite['exam_title'],
            'response' => $response,
            'comment' => $comment
        ]);
        
        return [
            'success' => true,
            'message' => $response === 'accepted' ? 'Invitation accepted successfully' : 'Invitation declined',
            'exam' => [
                'title' => $invite['exam_title'],
                'date' => $invite['exam_date'],
                'role' => $invite['role']
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('respondToInvite error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================================================
// RATING AND FEEDBACK FUNCTIONS
// Principal-only visibility for examiner ratings
// ============================================================================

/**
 * Rate external examiner
 * 
 * @param PDO $pdo Database connection
 * @param int $examinerId External examiner ID (user_id or external_examiners.id)
 * @param int $examId Optional exam ID this rating relates to
 * @param int $ratedByUserId User ID giving rating
 * @param string $ratedByRole Rater's role
 * @param int $collegeId College ID
 * @param float $score Rating score (1.0 to 5.0)
 * @param string $comments Rating comments
 * @return array ['success' => bool, 'message' => string, 'rating_id' => int]
 * 
 * Roles allowed: teacher, hod, principal (rating), principal/admin (viewing)
 * Tables touched: ratings, external_examiners, audit_logs
 * Workflow: Creates rating, updates profile_score, logs action
 */
function rateExaminer($pdo, $examinerId, $examId, $ratedByUserId, $ratedByRole, $collegeId, $score, $comments) {
    try {
        // Validate score
        if ($score < 1.0 || $score > 5.0) {
            return ['success' => false, 'message' => 'Score must be between 1.0 and 5.0'];
        }
        
        // Create rating
        $stmt = $pdo->prepare("INSERT INTO ratings (examiner_id, exam_id, rated_by_user_id, rated_by_role, college_id, score, comments, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$examinerId, $examId, $ratedByUserId, $ratedByRole, $collegeId, $score, $comments]);
        $ratingId = $pdo->lastInsertId();
        
        // Update examiner's profile score (average of all ratings)
        $stmt = $pdo->prepare("SELECT AVG(score) as avg_score FROM ratings WHERE examiner_id = ?");
        $stmt->execute([$examinerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgScore = $result['avg_score'] ?? 0.00;
        
        // Try to update external_examiners table
        $stmt = $pdo->prepare("UPDATE external_examiners SET profile_score = ?, last_active = NOW() WHERE id = ?");
        $stmt->execute([$avgScore, $examinerId]);
        
        // If no rows affected, might be a user record
        if ($stmt->rowCount() === 0) {
            // Check if it's a user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$examinerId]);
            if ($stmt->fetch()) {
                // It's a user, ratings are stored but profile_score update skipped
                // Could add a profile_score column to users table if needed
            }
        }
        
        // Audit log
        logAudit($pdo, 'rating', $ratingId, 'create', $ratedByUserId, [
            'examiner_id' => $examinerId,
            'exam_id' => $examId,
            'score' => $score,
            'avg_score' => $avgScore
        ]);
        
        return [
            'success' => true,
            'message' => 'Rating submitted successfully',
            'rating_id' => $ratingId,
            'new_avg_score' => $avgScore
        ];
        
    } catch (PDOException $e) {
        error_log('rateExaminer error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================================================
// QUESTION PAPER MANAGEMENT FUNCTIONS
// Principal-only locking mechanism
// ============================================================================

/**
 * Lock question paper (Principal only)
 * 
 * @param PDO $pdo Database connection
 * @param int $paperId Question paper ID
 * @param int $userId User ID locking the paper
 * @param string $role User's role
 * @return array ['success' => bool, 'message' => string]
 * 
 * Roles allowed: principal, admin (for locking)
 * Tables touched: question_papers, audit_logs
 * Workflow: Sets status to 'locked', prevents further edits
 */
function lockQuestionPaper($pdo, $paperId, $userId, $role) {
    try {
        // Permission check
        $normalizedRole = normalize_role($role);
        if (!in_array($normalizedRole, ['principal', 'admin'])) {
            return ['success' => false, 'message' => 'Only Principal or Admin can lock question papers'];
        }
        
        // Get paper details
        $stmt = $pdo->prepare("SELECT paper_id, exam_id, status, locked_by FROM question_papers WHERE paper_id = ?");
        $stmt->execute([$paperId]);
        $paper = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paper) {
            return ['success' => false, 'message' => 'Question paper not found'];
        }
        
        // Check if already locked
        if ($paper['status'] === 'locked') {
            return ['success' => false, 'message' => 'Question paper is already locked'];
        }
        
        // Lock the paper
        $stmt = $pdo->prepare("UPDATE question_papers SET status = 'locked', locked_by = ?, locked_at = NOW() WHERE paper_id = ?");
        $stmt->execute([$userId, $paperId]);
        
        // Audit log
        logAudit($pdo, 'question_paper', $paperId, 'lock', $userId, [
            'exam_id' => $paper['exam_id'],
            'previous_status' => $paper['status']
        ]);
        
        return ['success' => true, 'message' => 'Question paper locked successfully'];
        
    } catch (PDOException $e) {
        error_log('lockQuestionPaper error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Unlock question paper (Principal/Admin only)
 * 
 * @param PDO $pdo Database connection
 * @param int $paperId Question paper ID
 * @param int $userId User ID unlocking the paper
 * @param string $role User's role
 * @return array ['success' => bool, 'message' => string]
 */
function unlockQuestionPaper($pdo, $paperId, $userId, $role) {
    try {
        // Permission check
        $normalizedRole = normalize_role($role);
        if (!in_array($normalizedRole, ['principal', 'admin'])) {
            return ['success' => false, 'message' => 'Only Principal or Admin can unlock question papers'];
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE question_papers SET status = 'approved', locked_by = NULL, locked_at = NULL WHERE paper_id = ?");
        $stmt->execute([$paperId]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Question paper not found'];
        }
        
        // Audit log
        logAudit($pdo, 'question_paper', $paperId, 'unlock', $userId, []);
        
        return ['success' => true, 'message' => 'Question paper unlocked successfully'];
        
    } catch (PDOException $e) {
        error_log('unlockQuestionPaper error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================================================
// AUDIT LOGGING FUNCTION
// Comprehensive action tracking across all operations
// ============================================================================

/**
 * Log audit entry
 * 
 * @param PDO $pdo Database connection
 * @param string $resourceType Type: exam, approval, invite, rating, question_paper, practical_session, etc.
 * @param int $resourceId ID of the resource
 * @param string $action Action performed: create, update, delete, approve, reject, lock, etc.
 * @param int $userId User ID performing action
 * @param array $metadata Additional context data (stored as JSON)
 * @return bool Success status
 * 
 * Tables touched: audit_logs
 * Used by: All critical operations
 */
function logAudit($pdo, $resourceType, $resourceId, $action, $userId, $metadata = []) {
    try {
        $metadataJson = !empty($metadata) ? json_encode($metadata) : null;
        
        $stmt = $pdo->prepare("INSERT INTO audit_logs (resource_type, resource_id, action, user_id, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$resourceType, $resourceId, $action, $userId, $metadataJson]);
        
        return true;
    } catch (PDOException $e) {
        error_log('logAudit error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// NOTIFICATION SYSTEM FUNCTIONS
// In-app and email notifications
// ============================================================================

/**
 * Send notification to user
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID to notify
 * @param string $type Notification type: exam_submitted, exam_approved, exam_rejected, exam_invite, invite_response, etc.
 * @param array $payload Notification data (stored as JSON)
 * @return bool Success status
 * 
 * Tables touched: notifications
 * Workflow: Creates notification record, optionally sends email based on user preferences
 */
function sendNotification($pdo, $userId, $type, $payload = []) {
    try {
        $payloadJson = !empty($payload) ? json_encode($payload) : null;
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, payload, sent_on) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $type, $payloadJson]);
        
        // TODO: Check user notification preferences and send email if enabled
        // Could integrate with email service here
        
        return true;
    } catch (PDOException $e) {
        error_log('sendNotification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications for user
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notification records
 */
function getUnreadNotifications($pdo, $userId, $limit = 20) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND read_on IS NULL ORDER BY sent_on DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getUnreadNotifications error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 * 
 * @param PDO $pdo Database connection
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security check)
 * @return bool Success status
 */
function markNotificationRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET read_on = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log('markNotificationRead error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get college filter SQL for privacy enforcement
 * Generates WHERE clause to filter queries by college based on user role
 * 
 * @param string $role User's role (normalized)
 * @param int $collegeId User's college ID
 * @param string $tableAlias Table alias in query (e.g., 'e' for exams table)
 * @return string SQL WHERE clause fragment (without WHERE keyword)
 * 
 * Usage Examples:
 * - Teacher: "e.college_id = 5"
 * - HOD: "e.college_id = 5"  
 * - Principal: "e.college_id = 5"
 * - Vice-Principal: "1=1" (can see all colleges)
 * - Admin: "1=1" (can see all colleges)
 */
function getCollegeFilterSQL($role, $collegeId, $tableAlias = 'e') {
    $normalizedRole = normalize_role($role);
    
    // VP and Admin can see ALL colleges
    if (in_array($normalizedRole, ['vice_principal', 'admin'])) {
        return '1=1';
    }
    
    // Teacher, HOD, Principal: only their own college
    if (in_array($normalizedRole, ['teacher', 'hod', 'principal'])) {
        return "{$tableAlias}.college_id = " . intval($collegeId);
    }
    
    // Default: restrict to user's college
    return "{$tableAlias}.college_id = " . intval($collegeId);
}

/**
 * Check if user can access exam (privacy check)
 * Validates whether a user has permission to view/modify an exam
 * 
 * @param PDO $pdo Database connection
 * @param int $examId Exam ID to check
 * @param int $userId User ID requesting access
 * @param string $role User's role
 * @param int $collegeId User's college ID
 * @return bool True if user can access, false otherwise
 */
function canAccessExam($pdo, $examId, $userId, $role, $collegeId) {
    $normalizedRole = normalize_role($role);
    
    // Admin and VP can access all exams
    if (in_array($normalizedRole, ['admin', 'vice_principal'])) {
        return true;
    }
    
    try {
        // Check if exam belongs to user's college
        $stmt = $pdo->prepare("SELECT college_id FROM exams WHERE exam_id = ?");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            return false;
        }
        
        // Teacher, HOD, Principal: can only access own college exams
        if ($exam['college_id'] == $collegeId) {
            return true;
        }
        
        // Check if user is assigned as examiner (cross-college assignments allowed)
        $stmt = $pdo->prepare("
            SELECT assignment_id FROM exam_assignments 
            WHERE exam_id = ? AND user_id = ?
        ");
        $stmt->execute([$examId, $userId]);
        
        return (bool)$stmt->fetch();
        
    } catch (PDOException $e) {
        error_log('canAccessExam error: ' . $e->getMessage());
        return false;
    }
}

?>


