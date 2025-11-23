<?php
/**
 * EEMS - Security & Rate Limiting
 * =====================================================
 * Advanced security features including:
 * - Login attempt tracking
 * - IP-based rate limiting
 * - Brute force protection
 * - Session security
 * - Two-factor authentication support
 */

class SecurityManager {
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes in seconds
    private $rateLimitWindow = 60; // 1 minute
    private $maxRequestsPerMinute = 60;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->initSecurityTables();
    }
    
    /**
     * Initialize security tables if they don't exist
     */
    private function initSecurityTables() {
        try {
            // Login attempts table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    email VARCHAR(255),
                    success BOOLEAN DEFAULT FALSE,
                    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip_time (ip_address, attempted_at),
                    INDEX idx_email_time (email, attempted_at)
                )
            ");
            
            // Rate limiting table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    limit_id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    endpoint VARCHAR(255) NOT NULL,
                    request_count INT DEFAULT 1,
                    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip_endpoint (ip_address, endpoint),
                    INDEX idx_window (window_start)
                )
            ");
            
            // Security logs table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS security_logs (
                    log_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    ip_address VARCHAR(45),
                    event_type VARCHAR(50) NOT NULL,
                    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
                    details TEXT,
                    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_event_type (event_type),
                    INDEX idx_logged_at (logged_at)
                )
            ");
            
            // IP blacklist table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_blacklist (
                    blacklist_id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL UNIQUE,
                    reason VARCHAR(255),
                    blocked_until TIMESTAMP NULL,
                    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip (ip_address)
                )
            ");
            
        } catch (PDOException $e) {
            error_log('Security table initialization error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if IP is blacklisted
     */
    public function isBlacklisted($ipAddress) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM ip_blacklist 
            WHERE ip_address = ? 
            AND (blocked_until IS NULL OR blocked_until > NOW())
        ");
        $stmt->execute([$ipAddress]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Add IP to blacklist
     */
    public function blacklistIP($ipAddress, $reason, $duration = null) {
        $blockedUntil = $duration ? date('Y-m-d H:i:s', time() + $duration) : null;
        
        $stmt = $this->pdo->prepare("
            INSERT INTO ip_blacklist (ip_address, reason, blocked_until)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                blocked_until = VALUES(blocked_until),
                blocked_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$ipAddress, $reason, $blockedUntil]);
        
        $this->logSecurityEvent(null, $ipAddress, 'ip_blacklisted', 'critical', [
            'reason' => $reason,
            'duration' => $duration
        ]);
    }
    
    /**
     * Check rate limit for IP and endpoint
     */
    public function checkRateLimit($ipAddress, $endpoint = 'general') {
        // Clean old rate limit records
        $this->pdo->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        // Check if IP is blacklisted
        if ($this->isBlacklisted($ipAddress)) {
            $this->logSecurityEvent(null, $ipAddress, 'blocked_request', 'warning', [
                'endpoint' => $endpoint,
                'reason' => 'IP blacklisted'
            ]);
            return false;
        }
        
        // Get current window
        $stmt = $this->pdo->prepare("
            SELECT request_count, window_start 
            FROM rate_limits 
            WHERE ip_address = ? AND endpoint = ?
            AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$ipAddress, $endpoint, $this->rateLimitWindow]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            if ($record['request_count'] >= $this->maxRequestsPerMinute) {
                $this->logSecurityEvent(null, $ipAddress, 'rate_limit_exceeded', 'warning', [
                    'endpoint' => $endpoint,
                    'request_count' => $record['request_count']
                ]);
                return false;
            }
            
            // Increment counter
            $stmt = $this->pdo->prepare("
                UPDATE rate_limits 
                SET request_count = request_count + 1 
                WHERE ip_address = ? AND endpoint = ?
            ");
            $stmt->execute([$ipAddress, $endpoint]);
        } else {
            // Create new window
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$ipAddress, $endpoint]);
        }
        
        return true;
    }
    
    /**
     * Record login attempt
     */
    public function recordLoginAttempt($ipAddress, $email, $success) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (ip_address, email, success, attempted_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$ipAddress, $email, $success ? 1 : 0]);
        
        if (!$success) {
            $this->checkBruteForce($ipAddress, $email);
        }
    }
    
    /**
     * Check for brute force attacks
     */
    private function checkBruteForce($ipAddress, $email) {
        // Check failed attempts in last 15 minutes
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempt_count
            FROM login_attempts
            WHERE ip_address = ?
            AND success = FALSE
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$ipAddress, $this->lockoutDuration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['attempt_count'] >= $this->maxLoginAttempts) {
            // Blacklist IP temporarily
            $this->blacklistIP($ipAddress, 'Brute force attack detected', $this->lockoutDuration);
            
            $this->logSecurityEvent(null, $ipAddress, 'brute_force_detected', 'critical', [
                'email' => $email,
                'attempt_count' => $result['attempt_count']
            ]);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if account is locked
     */
    public function isAccountLocked($email) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempt_count
            FROM login_attempts
            WHERE email = ?
            AND success = FALSE
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$email, $this->lockoutDuration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempt_count'] >= $this->maxLoginAttempts;
    }
    
    /**
     * Get remaining lockout time
     */
    public function getLockoutTime($email) {
        $stmt = $this->pdo->prepare("
            SELECT attempted_at 
            FROM login_attempts
            WHERE email = ?
            AND success = FALSE
            ORDER BY attempted_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $lastAttempt = strtotime($result['attempted_at']);
            $unlockTime = $lastAttempt + $this->lockoutDuration;
            $remaining = $unlockTime - time();
            
            return max(0, $remaining);
        }
        
        return 0;
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $ipAddress, $eventType, $severity, $details = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (user_id, ip_address, event_type, severity, details, logged_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $detailsJson = is_array($details) ? json_encode($details) : $details;
        $stmt->execute([$userId, $ipAddress, $eventType, $severity, $detailsJson]);
    }
    
    /**
     * Secure session configuration
     */
    public static function configureSession() {
        // Session security settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', 1800); // 30 minutes
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Validate session security
     */
    public static function validateSession() {
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > 1800) { // 30 minutes
                session_unset();
                session_destroy();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        
        // Check IP consistency (optional - can cause issues with mobile networks)
        /*
        if (isset($_SESSION['ip_address'])) {
            if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
                session_unset();
                session_destroy();
                return false;
            }
        } else {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        */
        
        // Check user agent consistency
        if (isset($_SESSION['user_agent'])) {
            if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
                session_unset();
                session_destroy();
                return false;
            }
        } else {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        
        return true;
    }
    
    /**
     * Get security headers
     */
    public static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self';");
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Clean old security logs
     */
    public function cleanOldLogs($days = 30) {
        try {
            $this->pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
            $this->pdo->exec("DELETE FROM security_logs WHERE logged_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
            $this->pdo->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $this->pdo->exec("DELETE FROM ip_blacklist WHERE blocked_until IS NOT NULL AND blocked_until < NOW()");
        } catch (PDOException $e) {
            error_log('Log cleanup error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check for proxy headers
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $_SERVER['REMOTE_ADDR'];
    }
}

// Global security initialization
function initSecurity() {
    SecurityManager::setSecurityHeaders();
    SecurityManager::configureSession();
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        SecurityManager::validateSession();
    }
}

// Auto-initialize on include
initSecurity();
