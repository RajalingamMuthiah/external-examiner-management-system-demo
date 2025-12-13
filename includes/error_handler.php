<?php
/**
 * EEMS Error Handler
 * Centralized error handling with user-friendly messages
 * Version: 1.0
 */

// Error codes and messages
class ErrorHandler {
    
    // Error code constants
    const AUTH_REQUIRED = 'AUTH_REQUIRED';
    const INVALID_ROLE = 'INVALID_ROLE';
    const INVALID_CSRF = 'INVALID_CSRF';
    const NOT_FOUND = 'NOT_FOUND';
    const PERMISSION_DENIED = 'PERMISSION_DENIED';
    const VALIDATION_ERROR = 'VALIDATION_ERROR';
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const DUPLICATE_ENTRY = 'DUPLICATE_ENTRY';
    const FILE_UPLOAD_ERROR = 'FILE_UPLOAD_ERROR';
    const FILE_SIZE_ERROR = 'FILE_SIZE_ERROR';
    const INVALID_FILE_TYPE = 'INVALID_FILE_TYPE';
    const EMAIL_ERROR = 'EMAIL_ERROR';
    const SESSION_EXPIRED = 'SESSION_EXPIRED';
    const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    
    // User-friendly error messages
    private static $messages = [
        'AUTH_REQUIRED' => [
            'title' => 'Authentication Required',
            'message' => 'You must be logged in to access this page.',
            'action' => 'Please log in to continue.',
            'icon' => 'lock'
        ],
        'INVALID_ROLE' => [
            'title' => 'Insufficient Permissions',
            'message' => 'Your account role does not have permission to perform this action.',
            'action' => 'Contact your administrator if you believe this is an error.',
            'icon' => 'shield-exclamation'
        ],
        'INVALID_CSRF' => [
            'title' => 'Security Check Failed',
            'message' => 'The security token for this request is invalid or expired.',
            'action' => 'Please refresh the page and try again.',
            'icon' => 'exclamation-triangle'
        ],
        'NOT_FOUND' => [
            'title' => 'Not Found',
            'message' => 'The requested resource could not be found.',
            'action' => 'Please check the URL or return to the dashboard.',
            'icon' => 'question-circle'
        ],
        'PERMISSION_DENIED' => [
            'title' => 'Access Denied',
            'message' => 'You do not have permission to access this resource.',
            'action' => 'Contact your administrator for assistance.',
            'icon' => 'ban'
        ],
        'VALIDATION_ERROR' => [
            'title' => 'Validation Error',
            'message' => 'The information you provided contains errors.',
            'action' => 'Please correct the errors and try again.',
            'icon' => 'exclamation-circle'
        ],
        'DATABASE_ERROR' => [
            'title' => 'System Error',
            'message' => 'A database error occurred while processing your request.',
            'action' => 'Please try again later. If the problem persists, contact support.',
            'icon' => 'database'
        ],
        'DUPLICATE_ENTRY' => [
            'title' => 'Duplicate Entry',
            'message' => 'This record already exists in the system.',
            'action' => 'Please use a different value or update the existing record.',
            'icon' => 'copy'
        ],
        'FILE_UPLOAD_ERROR' => [
            'title' => 'Upload Failed',
            'message' => 'The file could not be uploaded.',
            'action' => 'Please check your internet connection and try again.',
            'icon' => 'upload'
        ],
        'FILE_SIZE_ERROR' => [
            'title' => 'File Too Large',
            'message' => 'The file you are trying to upload exceeds the maximum allowed size.',
            'action' => 'Please compress the file or upload a smaller file (max 10 MB).',
            'icon' => 'file-archive'
        ],
        'INVALID_FILE_TYPE' => [
            'title' => 'Invalid File Type',
            'message' => 'The file type you uploaded is not allowed.',
            'action' => 'Please upload a PDF file only.',
            'icon' => 'file-pdf'
        ],
        'EMAIL_ERROR' => [
            'title' => 'Email Sending Failed',
            'message' => 'The email notification could not be sent.',
            'action' => 'The action was completed, but you may not receive an email notification.',
            'icon' => 'envelope'
        ],
        'SESSION_EXPIRED' => [
            'title' => 'Session Expired',
            'message' => 'Your session has expired due to inactivity.',
            'action' => 'Please log in again to continue.',
            'icon' => 'clock'
        ],
        'RATE_LIMIT_EXCEEDED' => [
            'title' => 'Too Many Requests',
            'message' => 'You have made too many requests in a short time.',
            'action' => 'Please wait a moment before trying again.',
            'icon' => 'hourglass-half'
        ]
    ];
    
    /**
     * Get error details by code
     */
    public static function getError($code, $details = null) {
        if (!isset(self::$messages[$code])) {
            $code = 'VALIDATION_ERROR'; // Default fallback
        }
        
        $error = self::$messages[$code];
        $error['code'] = $code;
        
        // Add specific details if provided
        if ($details) {
            if (is_string($details)) {
                $error['details'] = $details;
            } elseif (is_array($details)) {
                $error = array_merge($error, $details);
            }
        }
        
        return $error;
    }
    
    /**
     * Render error page
     */
    public static function renderErrorPage($code, $details = null) {
        $error = self::getError($code, $details);
        
        // Log error
        self::logError($code, $details);
        
        // Set appropriate HTTP status
        http_response_code(self::getHttpStatus($code));
        
        // Include error page template
        include __DIR__ . '/error_page_template.php';
        exit;
    }
    
    /**
     * Render error message (for AJAX responses)
     */
    public static function jsonError($code, $details = null) {
        $error = self::getError($code, $details);
        
        // Log error
        self::logError($code, $details);
        
        header('Content-Type: application/json');
        http_response_code(self::getHttpStatus($code));
        
        echo json_encode([
            'success' => false,
            'error' => $error
        ]);
        exit;
    }
    
    /**
     * Get HTTP status code for error type
     */
    private static function getHttpStatus($code) {
        $statusMap = [
            'AUTH_REQUIRED' => 401,
            'INVALID_ROLE' => 403,
            'INVALID_CSRF' => 403,
            'NOT_FOUND' => 404,
            'PERMISSION_DENIED' => 403,
            'VALIDATION_ERROR' => 400,
            'DATABASE_ERROR' => 500,
            'DUPLICATE_ENTRY' => 409,
            'FILE_UPLOAD_ERROR' => 400,
            'FILE_SIZE_ERROR' => 413,
            'INVALID_FILE_TYPE' => 415,
            'EMAIL_ERROR' => 500,
            'SESSION_EXPIRED' => 401,
            'RATE_LIMIT_EXCEEDED' => 429
        ];
        
        return $statusMap[$code] ?? 500;
    }
    
    /**
     * Log error to file
     */
    private static function logError($code, $details) {
        $logFile = __DIR__ . '/../logs/error.log';
        
        $logEntry = sprintf(
            "[%s] ERROR: %s | Details: %s | User: %s | IP: %s | URL: %s\n",
            date('Y-m-d H:i:s'),
            $code,
            is_array($details) ? json_encode($details) : $details,
            $_SESSION['user_id'] ?? 'Not logged in',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['REQUEST_URI'] ?? 'Unknown'
        );
        
        // Ensure logs directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

/**
 * Form validation helper
 */
class FormValidator {
    
    private $errors = [];
    
    /**
     * Validate required field
     */
    public function required($value, $fieldName) {
        if (empty($value) && $value !== '0') {
            $this->errors[$fieldName] = ucfirst($fieldName) . ' is required.';
            return false;
        }
        return true;
    }
    
    /**
     * Validate email
     */
    public function email($value, $fieldName) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName] = 'Please enter a valid email address.';
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($value, $min, $fieldName) {
        if (strlen($value) < $min) {
            $this->errors[$fieldName] = ucfirst($fieldName) . " must be at least $min characters.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($value, $max, $fieldName) {
        if (strlen($value) > $max) {
            $this->errors[$fieldName] = ucfirst($fieldName) . " must not exceed $max characters.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate numeric
     */
    public function numeric($value, $fieldName) {
        if (!is_numeric($value)) {
            $this->errors[$fieldName] = ucfirst($fieldName) . ' must be a number.';
            return false;
        }
        return true;
    }
    
    /**
     * Validate integer
     */
    public function integer($value, $fieldName) {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$fieldName] = ucfirst($fieldName) . ' must be a whole number.';
            return false;
        }
        return true;
    }
    
    /**
     * Validate range
     */
    public function range($value, $min, $max, $fieldName) {
        if ($value < $min || $value > $max) {
            $this->errors[$fieldName] = ucfirst($fieldName) . " must be between $min and $max.";
            return false;
        }
        return true;
    }
    
    /**
     * Validate date format
     */
    public function date($value, $format, $fieldName) {
        $d = DateTime::createFromFormat($format, $value);
        if (!$d || $d->format($format) !== $value) {
            $this->errors[$fieldName] = ucfirst($fieldName) . ' must be a valid date.';
            return false;
        }
        return true;
    }
    
    /**
     * Validate future date
     */
    public function futureDate($value, $fieldName) {
        $date = strtotime($value);
        if ($date < strtotime('today')) {
            $this->errors[$fieldName] = ucfirst($fieldName) . ' must be in the future.';
            return false;
        }
        return true;
    }
    
    /**
     * Validate file upload
     */
    public function file($file, $fieldName, $maxSize = 10485760, $allowedTypes = ['pdf']) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$fieldName] = $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return false;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $sizeMB = $maxSize / 1048576;
            $this->errors[$fieldName] = "File size must not exceed {$sizeMB} MB.";
            return false;
        }
        
        // Check file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes)) {
            $allowed = implode(', ', array_map('strtoupper', $allowedTypes));
            $this->errors[$fieldName] = "Only $allowed files are allowed.";
            return false;
        }
        
        return true;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum allowed size.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the form maximum size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];
        
        return $messages[$error] ?? 'Unknown upload error.';
    }
    
    /**
     * Check if validation passed
     */
    public function passed() {
        return empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error
     */
    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
    
    /**
     * Get errors as HTML list
     */
    public function getErrorsHtml() {
        if (empty($this->errors)) {
            return '';
        }
        
        $html = '<ul class="list-unstyled mb-0">';
        foreach ($this->errors as $field => $message) {
            $html .= '<li><i class="bi bi-exclamation-circle text-danger"></i> ' . htmlspecialchars($message) . '</li>';
        }
        $html .= '</ul>';
        
        return $html;
    }
}

/**
 * Flash message helper
 */
class FlashMessage {
    
    /**
     * Set flash message
     */
    public static function set($type, $message) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Set success message
     */
    public static function success($message) {
        self::set('success', $message);
    }
    
    /**
     * Set error message
     */
    public static function error($message) {
        self::set('danger', $message);
    }
    
    /**
     * Set warning message
     */
    public static function warning($message) {
        self::set('warning', $message);
    }
    
    /**
     * Set info message
     */
    public static function info($message) {
        self::set('info', $message);
    }
    
    /**
     * Get and clear flash message
     */
    public static function get() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        
        return null;
    }
    
    /**
     * Check if flash message exists
     */
    public static function has() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        return isset($_SESSION['flash_message']);
    }
    
    /**
     * Render flash message HTML
     */
    public static function render() {
        $message = self::get();
        
        if (!$message) {
            return '';
        }
        
        $type = $message['type'];
        $text = htmlspecialchars($message['message']);
        
        $icons = [
            'success' => 'check-circle',
            'danger' => 'exclamation-circle',
            'warning' => 'exclamation-triangle',
            'info' => 'info-circle'
        ];
        
        $icon = $icons[$type] ?? 'info-circle';
        
        return <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
            <i class="bi bi-{$icon} me-2"></i>
            <strong>{$text}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
HTML;
    }
}

/**
 * Database error handler
 */
function handleDatabaseError(PDOException $e, $userMessage = 'A database error occurred.') {
    // Log detailed error
    error_log("Database Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Check for specific error types
    if ($e->getCode() == 23000) {
        // Duplicate entry
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            if (strpos($e->getMessage(), 'email') !== false) {
                return 'This email address is already registered.';
            }
            return 'This record already exists in the system.';
        }
        
        // Foreign key constraint
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            return 'Cannot delete this record because it is referenced by other records.';
        }
    }
    
    // Return generic message (don't expose SQL details to users)
    return $userMessage;
}

/**
 * Safe redirect with flash message
 */
function redirectWithMessage($url, $type, $message) {
    FlashMessage::set($type, $message);
    header("Location: $url");
    exit;
}

/**
 * AJAX response helper
 */
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}
?>
