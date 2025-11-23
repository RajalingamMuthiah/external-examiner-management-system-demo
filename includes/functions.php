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
        
        // Update user status and password
        $sql = "UPDATE users 
                SET status = 'verified', 
                    password = ?, 
                    verified_by = ?, 
                    verified_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hashed_password, $verified_by, $user_id]);
        
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

?>

