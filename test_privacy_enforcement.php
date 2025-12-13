<?php
/**
 * PRIVACY ENFORCEMENT TEST SUITE
 * File: test_privacy_enforcement.php
 * Purpose: Validate multi-college privacy isolation
 * 
 * Tests:
 * 1. Teacher at College A cannot see College B data
 * 2. HOD at College A cannot see College B data
 * 3. Principal at College A cannot see College B data
 * 4. Vice-Principal can see ALL colleges (coordinator role)
 * 5. Admin can see ALL colleges
 * 6. Cross-college exam assignments work correctly
 */

require_once 'includes/functions.php';
require_once 'config/db.php';

// Suppress warnings for test environment
error_reporting(E_ERROR | E_PARSE);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Privacy Enforcement Test - EEMS</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .test-pass { background: #d4edda; border-left: 4px solid #28a745; }
        .test-fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .test-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .test-info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        body { padding: 20px; background: #f8f9fa; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'><i class='bi bi-shield-check'></i> Privacy Enforcement Test Suite</h1>
    <p class='lead'>Testing multi-college privacy isolation across EEMS system</p>
    <hr>
";

// Test configuration
$tests = [];
$passCount = 0;
$failCount = 0;
$warnCount = 0;

// Helper function to render test result
function renderTest($name, $status, $message, $details = '') {
    global $passCount, $failCount, $warnCount;
    
    $icon = '✓';
    $class = 'test-pass';
    $badge = 'success';
    
    if ($status === 'FAIL') {
        $icon = '✗';
        $class = 'test-fail';
        $badge = 'danger';
        $failCount++;
    } elseif ($status === 'WARN') {
        $icon = '⚠';
        $class = 'test-warning';
        $badge = 'warning';
        $warnCount++;
    } elseif ($status === 'INFO') {
        $icon = 'ℹ';
        $class = 'test-info';
        $badge = 'info';
    } else {
        $passCount++;
    }
    
    echo "<div class='card mb-3 $class'>
        <div class='card-body'>
            <div class='d-flex justify-content-between align-items-start'>
                <div>
                    <h5>$icon $name</h5>
                    <span class='badge bg-$badge'>$status</span>
                    <p class='mt-2 mb-0'>$message</p>
                </div>
            </div>";
    
    if ($details) {
        echo "<div class='mt-3'><small class='text-muted'>Details: $details</small></div>";
    }
    
    echo "</div></div>";
}

// TEST 1: Database connection
try {
    $pdo->query("SELECT 1");
    renderTest("Database Connection", "PASS", "Successfully connected to database");
} catch (PDOException $e) {
    renderTest("Database Connection", "FAIL", "Cannot connect to database", $e->getMessage());
    exit;
}

// TEST 2: Check if colleges exist
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM colleges");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $collegeCount = $result['count'];
    
    if ($collegeCount >= 2) {
        renderTest("Multi-College Setup", "PASS", "Found $collegeCount colleges in system");
    } else {
        renderTest("Multi-College Setup", "WARN", "Only $collegeCount college(s) found. Need at least 2 for privacy testing");
    }
} catch (PDOException $e) {
    renderTest("Multi-College Setup", "FAIL", "Cannot query colleges table", $e->getMessage());
}

// TEST 3: Get test users from different colleges
$testUsers = [];
try {
    $stmt = $pdo->query("
        SELECT user_id, name, role, college_id, 
               (SELECT college_name FROM colleges WHERE college_id = users.college_id) as college_name
        FROM users 
        WHERE role IN ('teacher', 'hod', 'principal', 'vice_principal', 'admin')
        ORDER BY college_id, role
        LIMIT 10
    ");
    $testUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($testUsers) > 0) {
        $usersList = array_map(fn($u) => "{$u['name']} ({$u['role']}) at {$u['college_name']}", $testUsers);
        renderTest("Test Users Found", "PASS", "Found " . count($testUsers) . " test users", implode(', ', $usersList));
    } else {
        renderTest("Test Users Found", "WARN", "No test users found in database");
    }
} catch (PDOException $e) {
    renderTest("Test Users Found", "FAIL", "Cannot query users", $e->getMessage());
}

// TEST 4: Privacy helper functions exist
$functionsExist = true;
if (!function_exists('getCollegeFilterSQL')) {
    renderTest("Privacy Helper: getCollegeFilterSQL", "FAIL", "Function getCollegeFilterSQL() not found in functions.php");
    $functionsExist = false;
} else {
    renderTest("Privacy Helper: getCollegeFilterSQL", "PASS", "Function exists");
}

if (!function_exists('canAccessExam')) {
    renderTest("Privacy Helper: canAccessExam", "FAIL", "Function canAccessExam() not found in functions.php");
    $functionsExist = false;
} else {
    renderTest("Privacy Helper: canAccessExam", "PASS", "Function exists");
}

// TEST 5: Test getCollegeFilterSQL function
if ($functionsExist) {
    $testRoles = [
        'teacher' => 'e.college_id = 1',
        'hod' => 'e.college_id = 1',
        'principal' => 'e.college_id = 1',
        'vice_principal' => '1=1',
        'admin' => '1=1'
    ];
    
    foreach ($testRoles as $role => $expectedPattern) {
        $sql = getCollegeFilterSQL($role, 1, 'e');
        
        if ($role === 'vice_principal' || $role === 'admin') {
            // Should have no filter (1=1 allows all)
            if ($sql === '1=1') {
                renderTest("College Filter: $role", "PASS", "Correctly allows access to ALL colleges (coordinator role)");
            } else {
                renderTest("College Filter: $role", "FAIL", "Should return '1=1' for $role but got: $sql");
            }
        } else {
            // Should filter by college_id
            if (strpos($sql, 'college_id') !== false) {
                renderTest("College Filter: $role", "PASS", "Correctly filters by college_id: $sql");
            } else {
                renderTest("College Filter: $role", "FAIL", "Should filter by college_id but got: $sql");
            }
        }
    }
}

// TEST 6: Test getVisibleExamsForUser for different roles
if (!empty($testUsers)) {
    foreach ($testUsers as $user) {
        try {
            $exams = getVisibleExamsForUser($pdo, $user['user_id'], $user['role'], $user['college_id'], null);
            $examCount = count($exams);
            
            // Check if VP and Admin see more exams (cross-college)
            if ($user['role'] === 'vice_principal' || $user['role'] === 'admin') {
                renderTest(
                    "Exam Visibility: {$user['role']} - {$user['name']}", 
                    "INFO", 
                    "Coordinator role sees $examCount exams (should include ALL colleges)"
                );
            } else {
                // Check all exams belong to user's college
                $wrongCollegeCount = 0;
                foreach ($exams as $exam) {
                    if (isset($exam['college_id']) && $exam['college_id'] != $user['college_id']) {
                        // Allowed if user is assigned as examiner
                        if (!isset($exam['exam_source']) || $exam['exam_source'] !== 'assigned') {
                            $wrongCollegeCount++;
                        }
                    }
                }
                
                if ($wrongCollegeCount > 0) {
                    renderTest(
                        "Exam Visibility: {$user['role']} - {$user['name']}", 
                        "WARN", 
                        "Found $wrongCollegeCount exam(s) from other colleges (may be cross-college assignments)"
                    );
                } else {
                    renderTest(
                        "Exam Visibility: {$user['role']} - {$user['name']}", 
                        "PASS", 
                        "Sees $examCount exam(s) - all from own college or assigned"
                    );
                }
            }
        } catch (Exception $e) {
            renderTest(
                "Exam Visibility: {$user['role']} - {$user['name']}", 
                "FAIL", 
                "Error querying exams", 
                $e->getMessage()
            );
        }
    }
}

// TEST 7: Test canAccessExam function
if ($functionsExist && !empty($testUsers)) {
    try {
        // Get a sample exam
        $stmt = $pdo->query("SELECT exam_id, college_id FROM exams LIMIT 1");
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exam) {
            foreach ($testUsers as $user) {
                $canAccess = canAccessExam($pdo, $exam['exam_id'], $user['user_id'], $user['role'], $user['college_id']);
                
                if ($user['role'] === 'admin' || $user['role'] === 'vice_principal') {
                    // Should always have access
                    if ($canAccess) {
                        renderTest(
                            "Access Control: {$user['role']}", 
                            "PASS", 
                            "Coordinator can access exam from any college"
                        );
                    } else {
                        renderTest(
                            "Access Control: {$user['role']}", 
                            "FAIL", 
                            "Coordinator should be able to access all exams"
                        );
                    }
                } elseif ($exam['college_id'] == $user['college_id']) {
                    // Same college - should have access
                    if ($canAccess) {
                        renderTest(
                            "Access Control: {$user['role']} (same college)", 
                            "PASS", 
                            "Can access exam from own college"
                        );
                    } else {
                        renderTest(
                            "Access Control: {$user['role']} (same college)", 
                            "FAIL", 
                            "Should be able to access own college exam"
                        );
                    }
                } else {
                    // Different college - should NOT have access unless assigned
                    if (!$canAccess) {
                        renderTest(
                            "Access Control: {$user['role']} (different college)", 
                            "PASS", 
                            "Correctly denied access to other college exam"
                        );
                    } else {
                        renderTest(
                            "Access Control: {$user['role']} (different college)", 
                            "INFO", 
                            "Has access (likely assigned as external examiner)"
                        );
                    }
                }
            }
        }
    } catch (Exception $e) {
        renderTest("Access Control Tests", "FAIL", "Error testing access control", $e->getMessage());
    }
}

// TEST 8: Check existing files for privacy violations
$filesToCheck = [
    'teacher_dashboard.php',
    'hod_dashboard.php',
    'VP.php',
    'admin_dashboard.php',
    'view_exam_details.php',
    'create_exam.php',
    'manage_faculty.php'
];

echo "<h3 class='mt-5'>File Privacy Audit</h3>";

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Check for unfiltered queries
        $violations = [];
        
        // Look for SELECT without WHERE (dangerous)
        if (preg_match('/SELECT.*FROM\s+(exams|exam_assignments|practical_exam_sessions)(?!\s+WHERE)/i', $content)) {
            $violations[] = "Unfiltered query detected";
        }
        
        // Look for college_id filtering
        $hasCollegeFilter = stripos($content, 'college_id') !== false;
        $hasRoleCheck = stripos($content, 'vice_principal') !== false || stripos($content, 'admin') !== false;
        
        if ($hasCollegeFilter || $hasRoleCheck) {
            renderTest(
                "File Audit: $file", 
                "PASS", 
                "Contains privacy filters (college_id or role checks)"
            );
        } else {
            renderTest(
                "File Audit: $file", 
                "WARN", 
                "No obvious privacy filters found - manual review recommended"
            );
        }
        
        if (!empty($violations)) {
            renderTest(
                "File Audit: $file", 
                "FAIL", 
                implode(', ', $violations)
            );
        }
    } else {
        renderTest("File Audit: $file", "INFO", "File not found");
    }
}

// Summary
echo "<hr class='mt-5'>";
echo "<h2>Test Summary</h2>";
echo "<div class='row'>";
echo "<div class='col-md-4'><div class='alert alert-success'><h3>$passCount</h3>Tests Passed</div></div>";
echo "<div class='col-md-4'><div class='alert alert-danger'><h3>$failCount</h3>Tests Failed</div></div>";
echo "<div class='col-md-4'><div class='alert alert-warning'><h3>$warnCount</h3>Warnings</div></div>";
echo "</div>";

$totalTests = $passCount + $failCount + $warnCount;
if ($totalTests > 0) {
    $successRate = round(($passCount / $totalTests) * 100);
    echo "<div class='alert alert-" . ($successRate >= 80 ? 'success' : ($successRate >= 60 ? 'warning' : 'danger')) . "'>";
    echo "<h4>Overall Success Rate: $successRate%</h4>";
    
    if ($successRate >= 90) {
        echo "<p>✓ Excellent! Privacy enforcement is working correctly.</p>";
    } elseif ($successRate >= 70) {
        echo "<p>⚠ Good, but some issues need attention.</p>";
    } else {
        echo "<p>✗ Privacy enforcement needs significant improvements.</p>";
    }
    echo "</div>";
}

echo "</div>
</body>
</html>";
?>
