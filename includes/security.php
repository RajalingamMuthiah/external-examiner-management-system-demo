<?php
/**
 * ============================================================================
 * SECURITY MIDDLEWARE & DATA PRIVACY ENFORCEMENT
 * ============================================================================
 * 
 * This file implements comprehensive security controls to ensure:
 * 1. Strong session management and role-based access control (RBAC)
 * 2. Complete data isolation between users and roles
 * 3. Prevention of cross-dashboard access and data leakage
 * 4. Input sanitization and XSS/SQL injection prevention
 * 5. Security audit logging for suspicious activities
 * 
 * PRIVACY REQUIREMENT:
 * One faculty member's data and dashboard MUST NOT leak, access, or cross over
 * to another faculty member's view. All data queries are filtered by user_id
 * and role to ensure complete privacy and data segmentation.
 * 
 * @author EEMS Security Team
 * @version 2.0
 */

// Prevent direct access to this file
if (!defined('SECURITY_INIT')) {
    define('SECURITY_INIT', true);
}

/**
 * ============================================================================
 * SECTION 1: SESSION SECURITY & INITIALIZATION
 * ============================================================================
 */

/**
 * Initialize secure session with enhanced security settings
 * Prevents session hijacking and fixation attacks
 * 
 * @return void
 */
function init_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Session already started
    }
    
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    
    // Extract hostname without port to avoid cookie issues
    $rawHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $hostOnly = preg_replace('/:\d+$/', '', $rawHost);
    $domain = in_array($hostOnly, ['localhost', '127.0.0.1']) ? '' : $hostOnly;
    
    // Configure secure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,              // Session cookie (expires when browser closes)
        'path' => '/',                // Available across entire domain
        'domain' => $domain,          // Current domain only
        'secure' => $secure,          // HTTPS only in production
        'httponly' => true,           // Not accessible via JavaScript (XSS protection)
        'samesite' => 'Strict',       // CSRF protection (changed from Lax to Strict)
    ]);
    
    session_start();
    
    // Regenerate session ID periodically to prevent fixation attacks
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    } elseif (time() - $_SESSION['created_at'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
    
    // Add session security token to detect hijacking
    if (!isset($_SESSION['security_token'])) {
        $_SESSION['security_token'] = bin2hex(random_bytes(32));
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    // Validate session hasn't been hijacked
    if (isset($_SESSION['user_id'])) {
        $current_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check for user agent mismatch (potential hijacking)
        if ($_SESSION['user_agent'] !== $current_agent) {
            security_log('SESSION_HIJACK_ATTEMPT', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'expected_agent' => $_SESSION['user_agent'] ?? '',
                'actual_agent' => $current_agent,
            ]);
            destroy_session_and_redirect('Suspicious activity detected. Please login again.');
        }
        
        // Optional: Strict IP checking (may cause issues with dynamic IPs)
        // Uncomment if needed for high-security environments
        /*
        if ($_SESSION['ip_address'] !== $current_ip) {
            security_log('IP_CHANGE_DETECTED', [
                'user_id' => $_SESSION['user_id'],
                'old_ip' => $_SESSION['ip_address'],
                'new_ip' => $current_ip,
            ]);
            destroy_session_and_redirect('Session expired due to network change.');
        }
        */
    }
}

/**
 * ============================================================================
 * SECTION 2: ROLE-BASED ACCESS CONTROL (RBAC)
 * ============================================================================
 */

/**
 * Enforce login requirement - blocks unauthenticated access
 * Redirects to login page if user is not authenticated
 * 
 * @param string $redirect_url Custom redirect URL (default: login.php)
 * @return void
 */
function require_auth($redirect_url = 'login.php') {
    init_secure_session();
    
    if (empty($_SESSION['user_id'])) {
        security_log('UNAUTHORIZED_ACCESS_ATTEMPT', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        
        $_SESSION['error_message'] = 'You must be logged in to access this page.';
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Enforce role-based access control
 * Only allows specified roles to access the page
 * 
 * @param string|array $allowed_roles Single role or array of allowed roles
 * @param bool $strict If true, shows 403 error; if false, redirects to appropriate dashboard
 * @return void
 */
function require_role($allowed_roles, $strict = true) {
    require_auth(); // First ensure user is logged in
    
    // Convert single role to array for uniform handling
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    // Get current user's role (with fallback checks for compatibility)
    $current_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
    
    // Normalize all roles for comparison
    $current_role_normalized = normalize_role($current_role);
    $allowed_roles_normalized = array_map('normalize_role', $allowed_roles);
    
    // Check if current role is allowed
    if (!in_array($current_role_normalized, $allowed_roles_normalized, true)) {
        // Log unauthorized access attempt
        security_log('ROLE_VIOLATION', [
            'user_id' => $_SESSION['user_id'],
            'user_role' => $current_role_normalized,
            'required_roles' => implode(', ', $allowed_roles_normalized),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        
        if ($strict) {
            // Show 403 Forbidden error
            http_response_code(403);
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>403 - Access Denied</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body class="bg-light">
                <div class="container mt-5">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h4 class="mb-0"><i class="bi bi-shield-exclamation"></i> 403 - Access Denied</h4>
                                </div>
                                <div class="card-body">
                                    <p class="lead">You do not have permission to access this page.</p>
                                    <p>This incident has been logged for security purposes.</p>
                                    <hr>
                                    <p><strong>Your Role:</strong> ' . htmlspecialchars($current_role) . '</p>
                                    <p><strong>Required Roles:</strong> ' . htmlspecialchars(implode(', ', $allowed_roles)) . '</p>
                                    <div class="mt-4">
                                        <a href="logout.php" class="btn btn-danger">Logout</a>
                                        <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>';
            exit;
        } else {
            // Redirect to appropriate dashboard based on their actual role
            $_SESSION['error_message'] = 'Access denied. You have been redirected to your dashboard.';
            redirect_to_dashboard($current_role_normalized);
        }
    }
}

/**
 * Redirect user to their role-appropriate dashboard
 * Prevents cross-dashboard access by enforcing role-based routing
 * 
 * @param string|null $role User's role (uses session if not provided)
 * @return void
 */
function redirect_to_dashboard($role = null) {
    init_secure_session();
    
    if ($role === null) {
        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
    }
    
    $role_normalized = normalize_role($role);
    
    switch ($role_normalized) {
        case 'principal':
            header('Location: dashboard.php');
            break;
        case 'vice-principal':
            header('Location: VP.php');
            break;
        case 'hod':
            header('Location: hod_dashboard.php');
            break;
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'faculty':
        case 'teacher':
            header('Location: teacher_dashboard.php');
            break;
        default:
            // Unknown role - logout for safety
            security_log('UNKNOWN_ROLE_REDIRECT', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'role' => $role,
            ]);
            header('Location: logout.php');
            break;
    }
    exit;
}

/**
 * ============================================================================
 * SECTION 3: DATA PRIVACY & FILTERING
 * ============================================================================
 */

/**
 * Get current user's ID with validation
 * Returns null if user is not authenticated
 * 
 * @return int|null User ID or null
 */
function get_current_user_id() {
    init_secure_session();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current user's role with normalization
 * 
 * @return string Normalized role name
 */
function get_current_user_role() {
    init_secure_session();
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
    return normalize_role($role);
}

/**
 * Get current user's complete information from database
 * PRIVACY: Only returns data for the authenticated user
 * 
 * @param PDO $pdo Database connection
 * @return array|null User data or null if not found
 */
function get_current_user_info($pdo) {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return null;
    }
    
    try {
        // PRIVACY CONTROL: Query filters by user_id to prevent data leakage
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, post, college_name, 
                   department, experience_years, profile_data, 
                   profile_completed, status
            FROM users 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Security: Never expose password hash
        if ($user && isset($user['password'])) {
            unset($user['password']);
        }
        
        return $user;
    } catch (PDOException $e) {
        security_log('DATABASE_ERROR', [
            'function' => 'get_current_user_info',
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}

/**
 * Build WHERE clause for data filtering based on user role
 * PRIVACY: Ensures users only see data they're authorized to access
 * 
 * @param string $table_alias Table alias in SQL query (e.g., 'u', 'e')
 * @param string $user_id_column Column name containing user ID
 * @param string $college_column Column name containing college name
 * @param int $current_user_id Current user's ID
 * @param string $current_role Current user's role
 * @param string $current_college Current user's college
 * @return string SQL WHERE clause fragment
 */
function build_privacy_filter($table_alias, $user_id_column, $college_column, $current_user_id, $current_role, $current_college) {
    $role = normalize_role($current_role);
    
    switch ($role) {
        case 'faculty':
        case 'teacher':
            // PRIVACY: Teachers can ONLY see their own data
            return "{$table_alias}.{$user_id_column} = " . (int)$current_user_id;
            
        case 'hod':
            // PRIVACY: HOD can see data from their college only
            return "{$table_alias}.{$college_column} = " . $GLOBALS['pdo']->quote($current_college);
            
        case 'vice-principal':
            // PRIVACY: VP can see data from their college only
            return "{$table_alias}.{$college_column} = " . $GLOBALS['pdo']->quote($current_college);
            
        case 'principal':
            // PRIVACY: Principal can see data from their college only
            return "{$table_alias}.{$college_column} = " . $GLOBALS['pdo']->quote($current_college);
            
        case 'admin':
            // Admin can see all data (for system management)
            return "1=1";
            
        default:
            // Unknown role - deny all access
            security_log('UNKNOWN_ROLE_FILTER', [
                'user_id' => $current_user_id,
                'role' => $current_role,
            ]);
            return "1=0"; // Returns no results
    }
}

/**
 * Validate that a user has permission to view specific data record
 * PRIVACY: Prevents unauthorized access to other users' data
 * 
 * @param PDO $pdo Database connection
 * @param int $record_user_id User ID associated with the record
 * @param string $record_college College associated with the record
 * @return bool True if access is allowed
 */
function can_access_record($pdo, $record_user_id, $record_college = null) {
    $current_user_id = get_current_user_id();
    $current_role = get_current_user_role();
    
    if (!$current_user_id) {
        return false;
    }
    
    $user_info = get_current_user_info($pdo);
    if (!$user_info) {
        return false;
    }
    
    $current_college = $user_info['college_name'] ?? '';
    
    switch ($current_role) {
        case 'faculty':
        case 'teacher':
            // PRIVACY: Can only access their own records
            return (int)$record_user_id === (int)$current_user_id;
            
        case 'hod':
        case 'vice-principal':
        case 'principal':
            // PRIVACY: Can access records from their college only
            return $record_college === $current_college;
            
        case 'admin':
            // Admin can access all records
            return true;
            
        default:
            return false;
    }
}

/**
 * ============================================================================
 * SECTION 4: INPUT SANITIZATION & XSS PREVENTION
 * ============================================================================
 */

/**
 * Sanitize user input for safe display
 * Prevents XSS attacks by encoding HTML entities
 * 
 * @param string $input Raw user input
 * @return string Sanitized output
 */
function sanitize_output($input) {
    if ($input === null || $input === '') {
        return '';
    }
    return htmlspecialchars((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize input for safe use in HTML attributes
 * 
 * @param string $input Raw input
 * @return string Sanitized attribute value
 */
function sanitize_attr($input) {
    if ($input === null || $input === '') {
        return '';
    }
    return htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize email address
 * 
 * @param string $email Raw email input
 * @return string|false Sanitized email or false if invalid
 */
function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

/**
 * Validate and sanitize integer input
 * 
 * @param mixed $input Raw input
 * @param int $default Default value if invalid
 * @return int Sanitized integer
 */
function sanitize_int($input, $default = 0) {
    $filtered = filter_var($input, FILTER_VALIDATE_INT);
    return $filtered !== false ? $filtered : $default;
}

/**
 * Validate and sanitize URL
 * 
 * @param string $url Raw URL input
 * @return string|false Sanitized URL or false if invalid
 */
function sanitize_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * Sanitize string for safe database insertion
 * Note: Should still use prepared statements for SQL queries
 * 
 * @param string $input Raw input
 * @return string Sanitized string
 */
function sanitize_string($input) {
    if ($input === null || $input === '') {
        return '';
    }
    // Remove null bytes and trim whitespace
    return trim(str_replace("\0", '', (string)$input));
}

/**
 * Validate CSRF token for form submissions
 * Prevents Cross-Site Request Forgery attacks
 * 
 * @param string|null $token Token from form submission
 * @param int $max_age Maximum token age in seconds (default: 1 hour)
 * @return bool True if token is valid
 */
function validate_csrf_token($token = null, $max_age = 3600) {
    init_secure_session();
    
    // Get token from POST, GET, or headers
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        security_log('CSRF_TOKEN_MISSING', [
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        return false;
    }
    
    // Timing-safe comparison to prevent timing attacks
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    
    if (!$valid) {
        security_log('CSRF_TOKEN_INVALID', [
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        return false;
    }
    
    // Check token age
    if (isset($_SESSION['csrf_token_time'])) {
        $age = time() - $_SESSION['csrf_token_time'];
        if ($age > $max_age) {
            security_log('CSRF_TOKEN_EXPIRED', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'token_age' => $age,
            ]);
            // Regenerate token
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
    }
    
    return true;
}

/**
 * Generate new CSRF token
 * 
 * @return string CSRF token
 */
function get_csrf_token() {
    init_secure_session();
    
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * ============================================================================
 * SECTION 5: SECURITY LOGGING & AUDIT TRAIL
 * ============================================================================
 */

/**
 * Log security events for audit trail
 * Tracks suspicious activities and security violations
 * 
 * @param string $event_type Type of security event
 * @param array $data Additional event data
 * @return void
 */
function security_log($event_type, $data = []) {
    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event_type,
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'data' => $data,
    ];
    
    $log_line = json_encode($log_entry) . PHP_EOL;
    
    // Append to log file
    @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    
    // Also log to PHP error log for critical events
    $critical_events = [
        'SESSION_HIJACK_ATTEMPT',
        'ROLE_VIOLATION',
        'SQL_INJECTION_ATTEMPT',
        'XSS_ATTEMPT',
        'CSRF_ATTACK',
    ];
    
    if (in_array($event_type, $critical_events)) {
        error_log("SECURITY ALERT [{$event_type}]: " . json_encode($log_entry));
    }
}

/**
 * Destroy session and redirect with error message
 * 
 * @param string $message Error message to display
 * @return void
 */
function destroy_session_and_redirect($message = 'Session expired. Please login again.') {
    init_secure_session();
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    session_start();
    $_SESSION['error_message'] = $message;
    
    header('Location: login.php');
    exit;
}

/**
 * ============================================================================
 * SECTION 6: SQL INJECTION PREVENTION HELPERS
 * ============================================================================
 */

/**
 * Validate table name to prevent SQL injection
 * Only allows alphanumeric characters and underscores
 * 
 * @param string $table_name Raw table name
 * @return string|false Validated table name or false if invalid
 */
function validate_table_name($table_name) {
    if (preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        return $table_name;
    }
    
    security_log('SQL_INJECTION_ATTEMPT', [
        'attempted_table' => $table_name,
    ]);
    
    return false;
}

/**
 * Validate column name to prevent SQL injection
 * Only allows alphanumeric characters, underscores, and dots (for table.column)
 * 
 * @param string $column_name Raw column name
 * @return string|false Validated column name or false if invalid
 */
function validate_column_name($column_name) {
    if (preg_match('/^[a-zA-Z0-9_.]+$/', $column_name)) {
        return $column_name;
    }
    
    security_log('SQL_INJECTION_ATTEMPT', [
        'attempted_column' => $column_name,
    ]);
    
    return false;
}

/**
 * ============================================================================
 * SECTION 7: RATE LIMITING (OPTIONAL - FOR API ENDPOINTS)
 * ============================================================================
 */

/**
 * Simple rate limiting for sensitive operations
 * Prevents brute force attacks
 * 
 * @param string $action Action identifier
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @return bool True if within rate limit
 */
function check_rate_limit($action, $max_attempts = 5, $time_window = 300) {
    init_secure_session();
    
    $key = 'rate_limit_' . $action;
    $current_time = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Remove old attempts outside time window
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= $max_attempts) {
        security_log('RATE_LIMIT_EXCEEDED', [
            'action' => $action,
            'attempts' => count($_SESSION[$key]),
        ]);
        return false;
    }
    
    // Record this attempt
    $_SESSION[$key][] = $current_time;
    
    return true;
}

/**
 * ============================================================================
 * BACKWARDS COMPATIBILITY ALIASES
 * ============================================================================
 */

// Alias for existing codebase compatibility
if (!function_exists('start_secure_session')) {
    function start_secure_session() {
        return init_secure_session();
    }
}

if (!function_exists('h')) {
    function h($s) {
        return sanitize_output($s);
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        return require_auth();
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token = null, $max_age = 3600) {
        return validate_csrf_token($token, $max_age);
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        return get_csrf_token();
    }
}

/**
 * ============================================================================
 * AUTO-INITIALIZATION
 * ============================================================================
 */

// Auto-initialize secure session when this file is included
init_secure_session();

?>
