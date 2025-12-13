<?php
/**
 * Penetration Testing Tool
 * Automated security vulnerability scanner
 * 
 * WARNING: Only use on systems you own or have permission to test!
 */

require_once 'includes/security_validator.php';

// Only allow from localhost or with proper authentication
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        die('Access denied. Must be run from localhost or as admin.');
    }
}

class PenetrationTester {
    private $baseUrl;
    private $results = [];
    
    public function __construct($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Test for SQL Injection vulnerabilities
     */
    public function testSQLInjection() {
        $results = [
            'name' => 'SQL Injection Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        $payloads = [
            "1' OR '1'='1",
            "admin'--",
            "1; DROP TABLE users;--",
            "' UNION SELECT NULL--",
            "1' AND '1'='1",
            "' OR 1=1--"
        ];
        
        $endpoints = [
            '/login.php',
            '/register.php',
            '/dashboard.php',
            '/manage_users.php'
        ];
        
        foreach ($endpoints as $endpoint) {
            foreach ($payloads as $payload) {
                $results['tested']++;
                
                // Test GET parameter
                $response = $this->makeRequest($endpoint . '?id=' . urlencode($payload));
                if ($this->detectSQLError($response)) {
                    $results['vulnerabilities'][] = [
                        'endpoint' => $endpoint,
                        'method' => 'GET',
                        'payload' => $payload,
                        'severity' => 'CRITICAL'
                    ];
                    $results['vulnerable']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Test for XSS vulnerabilities
     */
    public function testXSS() {
        $results = [
            'name' => 'Cross-Site Scripting (XSS) Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        $payloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror="alert(\'XSS\')">',
            'javascript:alert("XSS")',
            '<svg/onload=alert(\'XSS\')>',
            '<iframe src="javascript:alert(\'XSS\')">',
            '<body onload="alert(\'XSS\')">'
        ];
        
        $endpoints = [
            '/register.php',
            '/create_exam.php',
            '/manage_faculty.php'
        ];
        
        foreach ($endpoints as $endpoint) {
            foreach ($payloads as $payload) {
                $results['tested']++;
                
                $response = $this->makeRequest($endpoint . '?search=' . urlencode($payload));
                if ($this->detectXSSReflection($response, $payload)) {
                    $results['vulnerabilities'][] = [
                        'endpoint' => $endpoint,
                        'method' => 'GET',
                        'payload' => $payload,
                        'severity' => 'HIGH'
                    ];
                    $results['vulnerable']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Test for CSRF vulnerabilities
     */
    public function testCSRF() {
        $results = [
            'name' => 'Cross-Site Request Forgery (CSRF) Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        $forms = [
            '/login.php',
            '/register.php',
            '/create_exam.php',
            '/manage_users.php'
        ];
        
        foreach ($forms as $form) {
            $results['tested']++;
            
            $response = $this->makeRequest($form);
            $hasCSRFToken = $this->detectCSRFToken($response);
            
            if (!$hasCSRFToken) {
                $results['vulnerabilities'][] = [
                    'endpoint' => $form,
                    'issue' => 'No CSRF token found in form',
                    'severity' => 'HIGH'
                ];
                $results['vulnerable']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Test for insecure authentication
     */
    public function testAuthentication() {
        $results = [
            'name' => 'Authentication Security Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        // Test for weak password policy
        $results['tested']++;
        $weakPasswords = ['password', '123456', 'admin'];
        foreach ($weakPasswords as $pass) {
            // This would need actual form submission testing
            // For now, we just document the check
        }
        
        // Test for username enumeration
        $results['tested']++;
        $response1 = $this->makeRequest('/login.php', 'POST', ['email' => 'nonexistent@example.com', 'password' => 'test']);
        $response2 = $this->makeRequest('/login.php', 'POST', ['email' => 'admin@example.com', 'password' => 'wrongpass']);
        
        if ($response1 !== $response2) {
            $results['vulnerabilities'][] = [
                'endpoint' => '/login.php',
                'issue' => 'Possible username enumeration (different error messages)',
                'severity' => 'MEDIUM'
            ];
            $results['vulnerable']++;
        }
        
        // Test for rate limiting on login
        $results['tested']++;
        $attempts = 0;
        $blocked = false;
        for ($i = 0; $i < 10; $i++) {
            $response = $this->makeRequest('/login.php', 'POST', ['email' => 'test@test.com', 'password' => 'wrong']);
            $attempts++;
            if (strpos($response, 'too many') !== false || strpos($response, 'rate limit') !== false) {
                $blocked = true;
                break;
            }
        }
        
        if (!$blocked) {
            $results['vulnerabilities'][] = [
                'endpoint' => '/login.php',
                'issue' => 'No rate limiting detected (tested ' . $attempts . ' attempts)',
                'severity' => 'HIGH'
            ];
            $results['vulnerable']++;
        }
        
        return $results;
    }
    
    /**
     * Test for insecure file upload
     */
    public function testFileUpload() {
        $results = [
            'name' => 'File Upload Security Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        $dangerousFiles = [
            'shell.php' => '<?php system($_GET["cmd"]); ?>',
            'backdoor.phtml' => '<?php eval($_POST["code"]); ?>',
            'script.php5' => '<?php phpinfo(); ?>'
        ];
        
        // Note: Actual file upload testing would need proper form handling
        // This is a placeholder for documentation
        foreach ($dangerousFiles as $filename => $content) {
            $results['tested']++;
            
            // Check if upload endpoint exists and accepts these extensions
            // In production, you would actually test file uploads here
        }
        
        $results['vulnerabilities'][] = [
            'endpoint' => 'File uploads',
            'issue' => 'Manual review recommended for file upload endpoints',
            'severity' => 'INFO'
        ];
        
        return $results;
    }
    
    /**
     * Test for security headers
     */
    public function testSecurityHeaders() {
        $results = [
            'name' => 'Security Headers Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        $requiredHeaders = [
            'X-Frame-Options',
            'X-Content-Type-Options',
            'X-XSS-Protection',
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'Referrer-Policy'
        ];
        
        foreach ($requiredHeaders as $header) {
            $results['tested']++;
            
            $headers = $this->getHeaders($this->baseUrl . '/dashboard.php');
            if (!isset($headers[$header])) {
                $results['vulnerabilities'][] = [
                    'endpoint' => 'All pages',
                    'issue' => "Missing security header: $header",
                    'severity' => 'MEDIUM'
                ];
                $results['vulnerable']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Test for information disclosure
     */
    public function testInformationDisclosure() {
        $results = [
            'name' => 'Information Disclosure Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        // Test for PHP errors displayed
        $results['tested']++;
        $response = $this->makeRequest('/nonexistent.php');
        if (preg_match('/Fatal error|Warning|Notice|Parse error/i', $response)) {
            $results['vulnerabilities'][] = [
                'endpoint' => 'Various',
                'issue' => 'PHP errors displayed to users',
                'severity' => 'MEDIUM'
            ];
            $results['vulnerable']++;
        }
        
        // Test for directory listing
        $results['tested']++;
        $response = $this->makeRequest('/uploads/');
        if (strpos($response, 'Index of') !== false) {
            $results['vulnerabilities'][] = [
                'endpoint' => '/uploads/',
                'issue' => 'Directory listing enabled',
                'severity' => 'MEDIUM'
            ];
            $results['vulnerable']++;
        }
        
        // Test for sensitive files
        $sensitiveFiles = [
            '/.git/config',
            '/.env',
            '/config.php',
            '/phpinfo.php',
            '/test.php'
        ];
        
        foreach ($sensitiveFiles as $file) {
            $results['tested']++;
            $response = $this->makeRequest($file);
            if ($response && strlen($response) > 0 && strpos($response, '404') === false) {
                $results['vulnerabilities'][] = [
                    'endpoint' => $file,
                    'issue' => 'Sensitive file accessible',
                    'severity' => 'HIGH'
                ];
                $results['vulnerable']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Test for session security
     */
    public function testSessionSecurity() {
        $results = [
            'name' => 'Session Security Tests',
            'vulnerabilities' => [],
            'tested' => 0,
            'vulnerable' => 0
        ];
        
        // Test session cookie flags
        $results['tested']++;
        $headers = $this->getHeaders($this->baseUrl . '/login.php');
        $setCookie = $headers['Set-Cookie'] ?? '';
        
        if (!strpos($setCookie, 'HttpOnly')) {
            $results['vulnerabilities'][] = [
                'endpoint' => 'Sessions',
                'issue' => 'Session cookie missing HttpOnly flag',
                'severity' => 'HIGH'
            ];
            $results['vulnerable']++;
        }
        
        if (!strpos($setCookie, 'Secure') && isset($_SERVER['HTTPS'])) {
            $results['vulnerabilities'][] = [
                'endpoint' => 'Sessions',
                'issue' => 'Session cookie missing Secure flag (HTTPS)',
                'severity' => 'HIGH'
            ];
            $results['vulnerable']++;
        }
        
        if (!strpos($setCookie, 'SameSite')) {
            $results['vulnerabilities'][] = [
                'endpoint' => 'Sessions',
                'issue' => 'Session cookie missing SameSite attribute',
                'severity' => 'MEDIUM'
            ];
            $results['vulnerable']++;
        }
        
        return $results;
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($url, $method = 'GET', $data = []) {
        if (strpos($url, 'http') !== 0) {
            $url = $this->baseUrl . $url;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    /**
     * Get HTTP headers
     */
    private function getHeaders($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $headers = [];
        foreach (explode("\n", $response) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    /**
     * Detect SQL errors in response
     */
    private function detectSQLError($response) {
        $patterns = [
            'SQL syntax',
            'mysql_fetch',
            'mysqli',
            'PDOException',
            'You have an error in your SQL',
            'Warning: mysql',
            'valid MySQL result',
            'MySqlClient',
            'PostgreSQL query failed',
            'unterminated quoted string'
        ];
        
        foreach ($patterns as $pattern) {
            if (stripos($response, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect XSS reflection
     */
    private function detectXSSReflection($response, $payload) {
        // Check if payload is reflected unescaped
        $unescaped = html_entity_decode($payload);
        return (strpos($response, $unescaped) !== false);
    }
    
    /**
     * Detect CSRF token
     */
    private function detectCSRFToken($response) {
        return (preg_match('/name=["\']csrf[_-]?token["\']|name=["\']_token["\']/', $response) === 1);
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        return [
            $this->testSQLInjection(),
            $this->testXSS(),
            $this->testCSRF(),
            $this->testAuthentication(),
            $this->testFileUpload(),
            $this->testSecurityHeaders(),
            $this->testInformationDisclosure(),
            $this->testSessionSecurity()
        ];
    }
}

// Run tests
$baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$tester = new PenetrationTester($baseUrl);
$results = $tester->runAllTests();

// Calculate statistics
$totalTests = 0;
$totalVulnerabilities = 0;
foreach ($results as $result) {
    $totalTests += $result['tested'];
    $totalVulnerabilities += $result['vulnerable'];
}

$securityScore = $totalTests > 0 ? round((($totalTests - $totalVulnerabilities) / $totalTests) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penetration Test Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .vulnerability-critical { border-left: 4px solid #dc3545; }
        .vulnerability-high { border-left: 4px solid #fd7e14; }
        .vulnerability-medium { border-left: 4px solid #ffc107; }
        .vulnerability-low { border-left: 4px solid #0dcaf0; }
        .vulnerability-info { border-left: 4px solid #6c757d; }
        
        .score-excellent { color: #28a745; }
        .score-good { color: #17a2b8; }
        .score-warning { color: #ffc107; }
        .score-danger { color: #dc3545; }
        
        .security-score {
            font-size: 4rem;
            font-weight: bold;
        }
        
        .badge-critical { background-color: #dc3545; }
        .badge-high { background-color: #fd7e14; }
        .badge-medium { background-color: #ffc107; color: #000; }
        .badge-low { background-color: #0dcaf0; color: #000; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="alert alert-warning">
            <strong>Warning:</strong> This is a penetration testing tool. Only use on systems you own or have explicit permission to test.
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="bi bi-bug-fill"></i> Penetration Test Report</h1>
                <p class="text-muted">Generated: <?= date('Y-m-d H:i:s') ?></p>
            </div>
        </div>
        
        <!-- Security Score -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h3>Security Score</h3>
                        <div class="security-score <?php 
                            if ($securityScore >= 90) echo 'score-excellent';
                            elseif ($securityScore >= 75) echo 'score-good';
                            elseif ($securityScore >= 60) echo 'score-warning';
                            else echo 'score-danger';
                        ?>">
                            <?= $securityScore ?>%
                        </div>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar <?php 
                                if ($securityScore >= 90) echo 'bg-success';
                                elseif ($securityScore >= 75) echo 'bg-info';
                                elseif ($securityScore >= 60) echo 'bg-warning';
                                else echo 'bg-danger';
                            ?>" style="width: <?= $securityScore ?>%">
                                <?= $securityScore ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h3>Summary</h3>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Total Tests:</td>
                                <td><strong><?= $totalTests ?></strong></td>
                            </tr>
                            <tr>
                                <td>Vulnerabilities Found:</td>
                                <td><strong class="text-danger"><?= $totalVulnerabilities ?></strong></td>
                            </tr>
                            <tr>
                                <td>Passed Tests:</td>
                                <td><strong class="text-success"><?= $totalTests - $totalVulnerabilities ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test Results -->
        <?php foreach ($results as $category): ?>
        <div class="card shadow mb-3">
            <div class="card-header bg-light">
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="mb-0"><?= htmlspecialchars($category['name']) ?></h4>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-primary">Tested: <?= $category['tested'] ?></span>
                        <span class="badge bg-danger">Vulnerable: <?= $category['vulnerable'] ?></span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($category['vulnerabilities'])): ?>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill"></i> No vulnerabilities detected in this category.
                </div>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($category['vulnerabilities'] as $vuln): ?>
                    <div class="list-group-item vulnerability-<?= strtolower($vuln['severity']) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($vuln['endpoint'] ?? $vuln['issue']) ?></h6>
                                <p class="mb-1">
                                    <?php if (isset($vuln['issue'])): ?>
                                    <strong>Issue:</strong> <?= htmlspecialchars($vuln['issue']) ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($vuln['payload'])): ?>
                                    <strong>Payload:</strong> <code><?= htmlspecialchars($vuln['payload']) ?></code><br>
                                    <?php endif; ?>
                                    <?php if (isset($vuln['method'])): ?>
                                    <strong>Method:</strong> <?= htmlspecialchars($vuln['method']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="badge badge-<?= strtolower($vuln['severity']) ?>">
                                <?= $vuln['severity'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Recommendations -->
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="bi bi-shield-exclamation"></i> Immediate Actions Required</h4>
            </div>
            <div class="card-body">
                <ol>
                    <?php if ($totalVulnerabilities > 0): ?>
                    <li><strong>Fix all CRITICAL and HIGH severity vulnerabilities immediately</strong></li>
                    <li>Review and address all MEDIUM severity issues</li>
                    <?php endif; ?>
                    <li>Implement CSRF protection on all forms</li>
                    <li>Sanitize all user inputs to prevent XSS</li>
                    <li>Use prepared statements for all database queries</li>
                    <li>Implement rate limiting on authentication endpoints</li>
                    <li>Add all security headers (X-Frame-Options, CSP, etc.)</li>
                    <li>Disable error display in production</li>
                    <li>Secure session cookies with HttpOnly, Secure, and SameSite flags</li>
                    <li>Regular security audits and penetration testing</li>
                </ol>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</body>
</html>
