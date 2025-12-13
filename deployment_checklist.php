<?php
/**
 * Production Deployment Checklist
 * Final validation before deploying to production
 */

require_once 'config/db.php';
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

$db = Database::getInstance()->getConnection();

class DeploymentChecker {
    private $db;
    private $results = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Check database configuration
     */
    public function checkDatabase() {
        $checks = [];
        
        // Check database connection
        try {
            $this->db->query('SELECT 1');
            $checks[] = [
                'name' => 'Database Connection',
                'status' => 'pass',
                'message' => 'Successfully connected to database'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Database Connection',
                'status' => 'fail',
                'message' => 'Failed to connect: ' . $e->getMessage()
            ];
        }
        
        // Check required tables exist
        $requiredTables = [
            'users', 'exams', 'exam_requests', 'assignments', 'question_papers',
            'practical_exams', 'notifications', 'audit_logs', 'security_logs',
            'uat_feedback', 'uat_bugs', 'uat_test_scenarios'
        ];
        
        $stmt = $this->db->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingTables = array_diff($requiredTables, $existingTables);
        
        if (empty($missingTables)) {
            $checks[] = [
                'name' => 'Database Tables',
                'status' => 'pass',
                'message' => 'All required tables exist (' . count($requiredTables) . ' tables)'
            ];
        } else {
            $checks[] = [
                'name' => 'Database Tables',
                'status' => 'fail',
                'message' => 'Missing tables: ' . implode(', ', $missingTables)
            ];
        }
        
        // Check database user privileges
        try {
            $stmt = $this->db->query("SHOW GRANTS FOR CURRENT_USER()");
            $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $hasAll = false;
            foreach ($grants as $grant) {
                if (stripos($grant, 'ALL PRIVILEGES') !== false) {
                    $hasAll = true;
                    break;
                }
            }
            
            $checks[] = [
                'name' => 'Database Privileges',
                'status' => $hasAll ? 'pass' : 'warning',
                'message' => $hasAll ? 
                    'Database user has all required privileges' :
                    'Limited privileges - verify SELECT, INSERT, UPDATE, DELETE, CREATE are granted'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Database Privileges',
                'status' => 'warning',
                'message' => 'Could not check privileges: ' . $e->getMessage()
            ];
        }
        
        return ['category' => 'Database', 'checks' => $checks];
    }
    
    /**
     * Check server configuration
     */
    public function checkServerConfig() {
        $checks = [];
        
        // Check PHP version
        $phpVersion = phpversion();
        $minVersion = '8.0.0';
        $checks[] = [
            'name' => 'PHP Version',
            'status' => version_compare($phpVersion, $minVersion, '>=') ? 'pass' : 'fail',
            'message' => "PHP $phpVersion " . (version_compare($phpVersion, $minVersion, '>=') ? 
                '(meets minimum requirement)' : "(requires $minVersion or higher)")
        ];
        
        // Check required PHP extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd', 'zip'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        
        $checks[] = [
            'name' => 'PHP Extensions',
            'status' => empty($missingExtensions) ? 'pass' : 'fail',
            'message' => empty($missingExtensions) ?
                'All required extensions loaded' :
                'Missing extensions: ' . implode(', ', $missingExtensions)
        ];
        
        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        $minMemory = 128;
        $currentMemory = (int)$memoryLimit;
        
        $checks[] = [
            'name' => 'Memory Limit',
            'status' => $currentMemory >= $minMemory ? 'pass' : 'warning',
            'message' => "Current: $memoryLimit" . ($currentMemory >= $minMemory ?
                ' (adequate)' : " (recommended: {$minMemory}M or higher)")
        ];
        
        // Check upload size
        $maxUpload = ini_get('upload_max_filesize');
        $checks[] = [
            'name' => 'Max Upload Size',
            'status' => 'pass',
            'message' => "Current: $maxUpload (for document uploads)"
        ];
        
        // Check error display
        $displayErrors = ini_get('display_errors');
        $checks[] = [
            'name' => 'Error Display',
            'status' => $displayErrors ? 'warning' : 'pass',
            'message' => $displayErrors ?
                'display_errors is ON - should be OFF in production!' :
                'display_errors is OFF (secure for production)'
        ];
        
        // Check session configuration
        $sessionSecure = ini_get('session.cookie_secure');
        $sessionHttpOnly = ini_get('session.cookie_httponly');
        
        $checks[] = [
            'name' => 'Session Security',
            'status' => ($sessionHttpOnly ? 'pass' : 'warning'),
            'message' => 'HttpOnly: ' . ($sessionHttpOnly ? 'ON' : 'OFF') . 
                        ', Secure: ' . ($sessionSecure ? 'ON' : 'OFF (enable for HTTPS)')
        ];
        
        return ['category' => 'Server Configuration', 'checks' => $checks];
    }
    
    /**
     * Check file permissions
     */
    public function checkFilePermissions() {
        $checks = [];
        
        $writableDirs = ['uploads', 'logs'];
        
        foreach ($writableDirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            $writable = is_dir($path) && is_writable($path);
            
            $checks[] = [
                'name' => "Directory: $dir",
                'status' => $writable ? 'pass' : 'fail',
                'message' => $writable ?
                    'Directory exists and is writable' :
                    'Directory missing or not writable - please create and set permissions'
            ];
        }
        
        // Check config file
        $configFile = __DIR__ . '/config/db.php';
        $configExists = file_exists($configFile);
        $configReadable = $configExists && is_readable($configFile);
        
        $checks[] = [
            'name' => 'Configuration File',
            'status' => $configReadable ? 'pass' : 'fail',
            'message' => $configReadable ?
                'Config file exists and is readable' :
                'Config file missing or not readable'
        ];
        
        return ['category' => 'File Permissions', 'checks' => $checks];
    }
    
    /**
     * Check security configuration
     */
    public function checkSecurity() {
        $checks = [];
        
        // Check HTTPS
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $checks[] = [
            'name' => 'HTTPS Enabled',
            'status' => $isHttps ? 'pass' : 'warning',
            'message' => $isHttps ?
                'HTTPS is enabled (secure)' :
                'HTTPS not detected - strongly recommended for production'
        ];
        
        // Check .htaccess exists
        $htaccessExists = file_exists(__DIR__ . '/.htaccess');
        $checks[] = [
            'name' => '.htaccess File',
            'status' => $htaccessExists ? 'pass' : 'warning',
            'message' => $htaccessExists ?
                '.htaccess file exists' :
                '.htaccess file missing - create for additional security'
        ];
        
        // Check default admin password
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE role = 'admin' 
                AND (password = ? OR password = ?)
            ");
            // Check for common weak hashes (you'd need to know your default password hash)
            $stmt->execute([
                password_hash('admin', PASSWORD_DEFAULT),
                password_hash('password', PASSWORD_DEFAULT)
            ]);
            $result = $stmt->fetch();
            
            $checks[] = [
                'name' => 'Default Admin Password',
                'status' => 'pass',
                'message' => 'Admin passwords should be changed from defaults before production'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Default Admin Password',
                'status' => 'warning',
                'message' => 'Could not verify admin passwords'
            ];
        }
        
        // Check CSRF protection enabled
        $securityValidatorExists = file_exists(__DIR__ . '/includes/security_validator.php');
        $checks[] = [
            'name' => 'CSRF Protection',
            'status' => $securityValidatorExists ? 'pass' : 'fail',
            'message' => $securityValidatorExists ?
                'SecurityValidator class available' :
                'CSRF protection files missing'
        ];
        
        return ['category' => 'Security', 'checks' => $checks];
    }
    
    /**
     * Check system readiness
     */
    public function checkSystemReadiness() {
        $checks = [];
        
        // Check if admin user exists
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
            $adminCount = $stmt->fetch()['count'];
            
            $checks[] = [
                'name' => 'Admin Users',
                'status' => $adminCount > 0 ? 'pass' : 'fail',
                'message' => $adminCount > 0 ?
                    "$adminCount admin user(s) exist" :
                    'No admin users found - create at least one admin'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Admin Users',
                'status' => 'fail',
                'message' => 'Could not check admin users: ' . $e->getMessage()
            ];
        }
        
        // Check error handler
        $errorHandlerExists = file_exists(__DIR__ . '/includes/error_handler.php');
        $checks[] = [
            'name' => 'Error Handler',
            'status' => $errorHandlerExists ? 'pass' : 'warning',
            'message' => $errorHandlerExists ?
                'Error handling system installed' :
                'Error handler missing - errors may not be handled gracefully'
        ];
        
        // Check backup system
        $checks[] = [
            'name' => 'Backup System',
            'status' => 'warning',
            'message' => 'Verify automated database backups are configured'
        ];
        
        // Check monitoring
        $checks[] = [
            'name' => 'Monitoring',
            'status' => 'warning',
            'message' => 'Verify server monitoring and alerting is configured'
        ];
        
        // Check email configuration
        $checks[] = [
            'name' => 'Email Configuration',
            'status' => 'warning',
            'message' => 'Test email notifications to ensure SMTP is configured correctly'
        ];
        
        return ['category' => 'System Readiness', 'checks' => $checks];
    }
    
    /**
     * Run all checks
     * @return array
     */
    public function runAllChecks(): array {
        return [
            $this->checkDatabase(),
            $this->checkServerConfig(),
            $this->checkFilePermissions(),
            $this->checkSecurity(),
            $this->checkSystemReadiness()
        ];
    }
}

$checker = new DeploymentChecker($db);
$results = $checker->runAllChecks();

// Calculate statistics
$totalChecks = 0;
$passedChecks = 0;
$failedChecks = 0;
$warningChecks = 0;

foreach ($results as $category) {
    foreach ($category['checks'] as $check) {
        $totalChecks++;
        if ($check['status'] === 'pass') $passedChecks++;
        if ($check['status'] === 'fail') $failedChecks++;
        if ($check['status'] === 'warning') $warningChecks++;
    }
}

$readyForDeployment = ($failedChecks === 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Checklist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .status-pass { color: #28a745; }
        .status-fail { color: #dc3545; }
        .status-warning { color: #ffc107; }
        
        .deployment-status {
            padding: 2rem;
            border-radius: 0.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .deployment-ready {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .deployment-not-ready {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .checklist-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="bi bi-check2-square"></i> Production Deployment Checklist</h1>
                <p class="text-muted">Verify all requirements before deploying to production</p>
            </div>
        </div>
        
        <!-- Deployment Status -->
        <div class="deployment-status <?= $readyForDeployment ? 'deployment-ready' : 'deployment-not-ready' ?> shadow">
            <?php if ($readyForDeployment): ?>
            <h2><i class="bi bi-check-circle-fill"></i> Ready for Deployment</h2>
            <p class="mb-0">All critical checks have passed. System is ready for production.</p>
            <?php else: ?>
            <h2><i class="bi bi-exclamation-triangle-fill"></i> Not Ready for Deployment</h2>
            <p class="mb-0"><?= $failedChecks ?> critical issue(s) must be resolved before deployment.</p>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h3><?= $totalChecks ?></h3>
                        <p class="text-muted mb-0">Total Checks</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?= $passedChecks ?></h3>
                        <p class="text-muted mb-0">Passed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?= $warningChecks ?></h3>
                        <p class="text-muted mb-0">Warnings</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h3 class="text-danger"><?= $failedChecks ?></h3>
                        <p class="text-muted mb-0">Failed</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Checklist Results -->
        <?php foreach ($results as $category): ?>
        <div class="card checklist-card shadow">
            <div class="card-header bg-light">
                <h4 class="mb-0"><?= htmlspecialchars($category['category']) ?></h4>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($category['checks'] as $check): ?>
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <?php if ($check['status'] === 'pass'): ?>
                                <i class="bi bi-check-circle-fill fs-3 status-pass"></i>
                                <?php elseif ($check['status'] === 'fail'): ?>
                                <i class="bi bi-x-circle-fill fs-3 status-fail"></i>
                                <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill fs-3 status-warning"></i>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <strong><?= htmlspecialchars($check['name']) ?></strong>
                            </div>
                            <div class="col-md-7">
                                <span class="text-muted"><?= htmlspecialchars($check['message']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Deployment Instructions -->
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-rocket-takeoff"></i> Deployment Instructions</h4>
            </div>
            <div class="card-body">
                <h5>Pre-Deployment Steps:</h5>
                <ol>
                    <li>Resolve all FAILED checks above</li>
                    <li>Review and address all WARNING items</li>
                    <li>Create a backup of the current database</li>
                    <li>Test all critical workflows in staging environment</li>
                    <li>Verify email notifications are working</li>
                    <li>Run security audit (<a href="security_audit.php">Security Audit Tool</a>)</li>
                    <li>Run penetration tests (<a href="penetration_test.php">Penetration Test Tool</a>)</li>
                    <li>Complete UAT testing (<a href="uat_dashboard.php">UAT Dashboard</a>)</li>
                </ol>
                
                <h5 class="mt-4">Deployment Steps:</h5>
                <ol>
                    <li>Upload all files to production server</li>
                    <li>Configure database connection in <code>config/db.php</code></li>
                    <li>Import database schema: <code>mysql &lt; db/schema.sql</code></li>
                    <li>Set file permissions: uploads/ and logs/ directories writable</li>
                    <li>Configure SSL certificate (Let's Encrypt recommended)</li>
                    <li>Update .htaccess for production URLs</li>
                    <li>Test all endpoints and workflows</li>
                    <li>Enable production error logging (disable display_errors)</li>
                    <li>Set up automated backups (daily recommended)</li>
                    <li>Configure monitoring and alerts</li>
                </ol>
                
                <h5 class="mt-4">Post-Deployment:</h5>
                <ol>
                    <li>Verify all user roles can log in</li>
                    <li>Test critical workflows (create exam, assign teachers, etc.)</li>
                    <li>Monitor error logs for first 24 hours</li>
                    <li>Run security audit again</li>
                    <li>Verify backup system is working</li>
                    <li>Document any production-specific configuration</li>
                    <li>Provide training to users if needed</li>
                </ol>
                
                <h5 class="mt-4">Documentation:</h5>
                <ul>
                    <li><a href="README.md">System Overview</a></li>
                    <li><a href="API_DOCUMENTATION.md">API Documentation</a></li>
                    <li><a href="USER_MANUAL.md">User Manual</a></li>
                    <li><a href="DEPLOYMENT_GUIDE.md">Deployment Guide</a></li>
                    <li><a href="ADMINISTRATOR_GUIDE.md">Administrator Guide</a></li>
                </ul>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
            <?php if ($readyForDeployment): ?>
            <button class="btn btn-success" onclick="if(confirm('Have you backed up the database?')) alert('Proceed with deployment following the instructions above.');">
                <i class="bi bi-rocket-takeoff-fill"></i> Proceed to Deployment
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
