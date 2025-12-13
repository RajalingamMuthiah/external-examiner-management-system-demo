<?php
/**
 * Security Validator
 * Comprehensive security validation and hardening utilities
 * 
 * Features:
 * - Input sanitization and validation
 * - XSS prevention
 * - SQL injection prevention
 * - CSRF token management
 * - Security headers
 * - Password strength validation
 * - File upload security
 * - Rate limiting
 */

class SecurityValidator {
    private static $instance = null;
    private $csrfTokens = [];
    private $rateLimiter = [];
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        $this->csrfTokens = &$_SESSION['csrf_tokens'];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate CSRF Token
     */
    public function generateCSRFToken($form = 'default') {
        $token = bin2hex(random_bytes(32));
        $this->csrfTokens[$form] = [
            'token' => $token,
            'time' => time()
        ];
        
        // Clean old tokens (older than 1 hour)
        foreach ($this->csrfTokens as $key => $data) {
            if (time() - $data['time'] > 3600) {
                unset($this->csrfTokens[$key]);
            }
        }
        
        return $token;
    }
    
    /**
     * Validate CSRF Token
     */
    public function validateCSRFToken($token, $form = 'default') {
        if (!isset($this->csrfTokens[$form])) {
            return false;
        }
        
        $stored = $this->csrfTokens[$form];
        
        // Check if token matches and not expired
        if ($stored['token'] !== $token) {
            return false;
        }
        
        if (time() - $stored['time'] > 3600) {
            unset($this->csrfTokens[$form]);
            return false;
        }
        
        // Token is valid, remove it (one-time use)
        unset($this->csrfTokens[$form]);
        return true;
    }
    
    /**
     * Sanitize Input - Prevent XSS
     */
    public function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
                
            case 'html':
                // Allow safe HTML tags
                $allowed = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
                return strip_tags($input, $allowed);
                
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
                
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'filename':
                // Remove path traversal attempts
                $input = basename($input);
                return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $input);
                
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate Input
     */
    public function validateInput($input, $type, $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
                
            case 'int':
                $valid = filter_var($input, FILTER_VALIDATE_INT) !== false;
                if ($valid && isset($options['min'])) {
                    $valid = $input >= $options['min'];
                }
                if ($valid && isset($options['max'])) {
                    $valid = $input <= $options['max'];
                }
                return $valid;
                
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
                
            case 'ip':
                return filter_var($input, FILTER_VALIDATE_IP) !== false;
                
            case 'regex':
                if (!isset($options['pattern'])) {
                    return false;
                }
                return preg_match($options['pattern'], $input) === 1;
                
            case 'length':
                $len = strlen($input);
                $valid = true;
                if (isset($options['min'])) {
                    $valid = $len >= $options['min'];
                }
                if ($valid && isset($options['max'])) {
                    $valid = $len <= $options['max'];
                }
                return $valid;
                
            default:
                return !empty($input);
        }
    }
    
    /**
     * Validate Password Strength
     */
    public function validatePasswordStrength($password) {
        $strength = [
            'score' => 0,
            'feedback' => [],
            'passed' => false
        ];
        
        // Minimum length
        if (strlen($password) < 8) {
            $strength['feedback'][] = 'Password must be at least 8 characters';
            return $strength;
        }
        $strength['score'] += 20;
        
        // Contains lowercase
        if (preg_match('/[a-z]/', $password)) {
            $strength['score'] += 20;
        } else {
            $strength['feedback'][] = 'Add lowercase letters';
        }
        
        // Contains uppercase
        if (preg_match('/[A-Z]/', $password)) {
            $strength['score'] += 20;
        } else {
            $strength['feedback'][] = 'Add uppercase letters';
        }
        
        // Contains numbers
        if (preg_match('/[0-9]/', $password)) {
            $strength['score'] += 20;
        } else {
            $strength['feedback'][] = 'Add numbers';
        }
        
        // Contains special characters
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $strength['score'] += 20;
        } else {
            $strength['feedback'][] = 'Add special characters (!@#$%^&*)';
        }
        
        // Bonus for length
        if (strlen($password) >= 12) {
            $strength['score'] += 10;
        }
        
        // Check common passwords
        $commonPasswords = ['password', '12345678', 'qwerty', 'abc123', 'password123'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $strength['score'] = 0;
            $strength['feedback'] = ['Password is too common'];
        }
        
        $strength['passed'] = $strength['score'] >= 80;
        
        return $strength;
    }
    
    /**
     * Validate File Upload
     */
    public function validateFileUpload($file, $options = []) {
        $result = [
            'valid' => false,
            'errors' => []
        ];
        
        // Check if file was uploaded
        if (!isset($file['error']) || is_array($file['error'])) {
            $result['errors'][] = 'Invalid file upload';
            return $result;
        }
        
        // Check upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $result['errors'][] = 'No file was uploaded';
                return $result;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $result['errors'][] = 'File size exceeds limit';
                return $result;
            default:
                $result['errors'][] = 'Unknown upload error';
                return $result;
        }
        
        // Validate file size
        if (isset($options['maxSize'])) {
            if ($file['size'] > $options['maxSize']) {
                $result['errors'][] = 'File size exceeds maximum allowed (' . 
                    $this->formatBytes($options['maxSize']) . ')';
                return $result;
            }
        }
        
        // Validate MIME type
        if (isset($options['allowedTypes'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $options['allowedTypes'])) {
                $result['errors'][] = 'File type not allowed';
                return $result;
            }
        }
        
        // Validate file extension
        if (isset($options['allowedExtensions'])) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $options['allowedExtensions'])) {
                $result['errors'][] = 'File extension not allowed';
                return $result;
            }
        }
        
        // Check for double extensions (security)
        if (substr_count($file['name'], '.') > 1) {
            $result['errors'][] = 'Multiple file extensions not allowed';
            return $result;
        }
        
        // Validate image dimensions (if image)
        if (isset($options['imageMaxWidth']) || isset($options['imageMaxHeight'])) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo !== false) {
                if (isset($options['imageMaxWidth']) && $imageInfo[0] > $options['imageMaxWidth']) {
                    $result['errors'][] = 'Image width exceeds maximum';
                    return $result;
                }
                if (isset($options['imageMaxHeight']) && $imageInfo[1] > $options['imageMaxHeight']) {
                    $result['errors'][] = 'Image height exceeds maximum';
                    return $result;
                }
            }
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    /**
     * Rate Limiting
     */
    public function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 60) {
        $now = time();
        
        // Clean old entries
        foreach ($this->rateLimiter as $key => $data) {
            if ($now - $data['start'] > $timeWindow) {
                unset($this->rateLimiter[$key]);
            }
        }
        
        if (!isset($this->rateLimiter[$identifier])) {
            $this->rateLimiter[$identifier] = [
                'count' => 1,
                'start' => $now
            ];
            return true;
        }
        
        $entry = $this->rateLimiter[$identifier];
        
        // Check if within time window
        if ($now - $entry['start'] > $timeWindow) {
            // Reset counter
            $this->rateLimiter[$identifier] = [
                'count' => 1,
                'start' => $now
            ];
            return true;
        }
        
        // Increment counter
        $this->rateLimiter[$identifier]['count']++;
        
        // Check if exceeded
        if ($this->rateLimiter[$identifier]['count'] > $maxAttempts) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Set Security Headers
     */
    public function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Type Sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self'; " .
               "frame-ancestors 'self';";
        header("Content-Security-Policy: $csp");
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Prevent SQL Injection (for raw queries)
     */
    public function escapeSQLInput($input, $pdo) {
        if (is_array($input)) {
            return array_map(function($item) use ($pdo) {
                return $this->escapeSQLInput($item, $pdo);
            }, $input);
        }
        
        return $pdo->quote($input);
    }
    
    /**
     * Detect SQL Injection Attempts
     */
    public function detectSQLInjection($input) {
        $patterns = [
            '/(\bunion\b.*\bselect\b)|(\bselect\b.*\bunion\b)/i',
            '/\b(insert|update|delete|drop|create|alter|exec|execute|script|javascript|eval)\b/i',
            '/(;|\-\-|\/\*|\*\/|xp_|sp_)/',
            '/\'.*(\bor\b|\band\b).*\'/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect XSS Attempts
     */
    public function detectXSS($input) {
        $patterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=\s*["\']?[^"\']*["\']?/i',
            '/<iframe\b[^>]*>(.*?)<\/iframe>/is',
            '/<object\b[^>]*>(.*?)<\/object>/is',
            '/<embed\b[^>]*>/i',
            '/<applet\b[^>]*>(.*?)<\/applet>/is'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log Security Event
     */
    public function logSecurityEvent($type, $description, $severity = 'medium') {
        require_once __DIR__ . '/../config/db.php';
        $db = Database::getInstance()->getConnection();
        
        try {
            $stmt = $db->prepare("
                INSERT INTO security_logs (
                    event_type, description, severity, ip_address,
                    user_agent, user_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $type,
                $description,
                $severity,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $_SESSION['user_id'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Security log failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check for Suspicious Activity
     */
    public function checkSuspiciousActivity() {
        $suspicious = [];
        
        // Check for SQL injection in GET/POST
        foreach ($_GET as $key => $value) {
            if ($this->detectSQLInjection($value)) {
                $suspicious[] = "SQL injection attempt in GET[$key]";
            }
        }
        
        foreach ($_POST as $key => $value) {
            if ($this->detectSQLInjection($value)) {
                $suspicious[] = "SQL injection attempt in POST[$key]";
            }
        }
        
        // Check for XSS in GET/POST
        foreach ($_GET as $key => $value) {
            if ($this->detectXSS($value)) {
                $suspicious[] = "XSS attempt in GET[$key]";
            }
        }
        
        foreach ($_POST as $key => $value) {
            if ($this->detectXSS($value)) {
                $suspicious[] = "XSS attempt in POST[$key]";
            }
        }
        
        // Check for path traversal
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '..') !== false || strpos($requestUri, '/../') !== false) {
            $suspicious[] = "Path traversal attempt in URI";
        }
        
        // Log and return
        if (!empty($suspicious)) {
            foreach ($suspicious as $event) {
                $this->logSecurityEvent('attack_attempt', $event, 'high');
            }
        }
        
        return $suspicious;
    }
    
    /**
     * Generate Secure Random String
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash Password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify Password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if Password Needs Rehash
     */
    public function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Format Bytes
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

/**
 * CSRF Token Helper Functions
 */
function csrf_token($form = 'default') {
    $validator = SecurityValidator::getInstance();
    return $validator->generateCSRFToken($form);
}

function csrf_field($form = 'default') {
    $token = csrf_token($form);
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function verify_csrf($token, $form = 'default') {
    $validator = SecurityValidator::getInstance();
    return $validator->validateCSRFToken($token, $form);
}

/**
 * Sanitization Helper Functions
 */
function clean_input($input, $type = 'string') {
    $validator = SecurityValidator::getInstance();
    return $validator->sanitizeInput($input, $type);
}

function validate_input($input, $type, $options = []) {
    $validator = SecurityValidator::getInstance();
    return $validator->validateInput($input, $type, $options);
}
