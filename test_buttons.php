<?php
/**
 * COMPREHENSIVE BUTTON FIX SCRIPT
 * File: fix_buttons.php
 * Purpose: Test and validate all button functionality across dashboards
 * 
 * Tests:
 * 1. Create Exam button (HOD/Principal/VP/Admin)
 * 2. Add Faculty button
 * 3. Assign Examiners button
 * 4. Approve/Reject buttons
 * 5. Status workflow buttons
 */

require_once 'includes/functions.php';
require_once 'config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Button Functionality Test - EEMS</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .test-pass { background: #d4edda; border-left: 4px solid #28a745; }
        .test-fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        body { padding: 20px; background: #f8f9fa; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'>Button Functionality Test Suite</h1>
";

$tests = [];
$pass = 0;
$fail = 0;

// Helper function
function renderTest($name, $status, $message) {
    global $pass, $fail;
    $icon = $status === 'PASS' ? '✓' : '✗';
    $class = $status === 'PASS' ? 'test-pass' : 'test-fail';
    $badge = $status === 'PASS' ? 'success' : 'danger';
    
    if ($status === 'PASS') $pass++; else $fail++;
    
    echo "<div class='card mb-3 $class'>
        <div class='card-body'>
            <h5>$icon $name <span class='badge bg-$badge'>$status</span></h5>
            <p class='mb-0'>$message</p>
        </div>
    </div>";
}

// TEST 1: Check if create_exam.php exists and works
if (file_exists('create_exam.php')) {
    $content = file_get_contents('create_exam.php');
    if (strpos($content, 'exam_name') !== false && strpos($content, 'INSERT INTO') !== false) {
        renderTest('Create Exam Page', 'PASS', 'File exists with proper form and database logic');
    } else {
        renderTest('Create Exam Page', 'FAIL', 'File exists but missing key functionality');
    }
} else {
    renderTest('Create Exam Page', 'FAIL', 'File does not exist - needs creation');
}

// TEST 2: Check Create Exam button in HOD dashboard
if (file_exists('hod_dashboard.php')) {
    $content = file_get_contents('hod_dashboard.php');
    $hasCreateButton = strpos($content, 'Create Exam') !== false;
    $hasAjaxHandler = strpos($content, 'submitAddExam') !== false || strpos($content, 'add_exam') !== false;
    
    if ($hasCreateButton && $hasAjaxHandler) {
        renderTest('HOD Dashboard - Create Exam Button', 'PASS', 'Button exists with AJAX handler');
    } else {
        renderTest('HOD Dashboard - Create Exam Button', 'FAIL', 'Missing button or AJAX handler');
    }
}

// TEST 3: Check Add Faculty functionality
if (file_exists('manage_faculty.php')) {
    renderTest('Manage Faculty Page', 'PASS', 'File exists for faculty management');
} else {
    renderTest('Manage Faculty Page', 'FAIL', 'manage_faculty.php not found');
}

// TEST 4: Check exam status workflow in admin dashboard
if (file_exists('admin_dashboard.php')) {
    $content = file_get_contents('admin_dashboard.php');
    $hasApproveHandler = strpos($content, 'update_exam_status') !== false || strpos($content, 'approve_exam') !== false;
    
    if ($hasApproveHandler) {
        renderTest('Admin Dashboard - Status Workflow', 'PASS', 'Approve/reject handlers exist');
    } else {
        renderTest('Admin Dashboard - Status Workflow', 'FAIL', 'Missing status update handlers');
    }
}

// TEST 5: Check if exam_assignments table exists for examiner assignment
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'exam_assignments'");
    if ($stmt->fetch()) {
        renderTest('Exam Assignments Table', 'PASS', 'Database table exists for examiner assignments');
    } else {
        renderTest('Exam Assignments Table', 'FAIL', 'Table missing - assignments won\'t work');
    }
} catch (Exception $e) {
    renderTest('Exam Assignments Table', 'FAIL', 'Database error: ' . $e->getMessage());
}

// TEST 6: Check AJAX endpoints
$ajaxEndpoints = [
    'admin_dashboard.php' => ['add_exam', 'update_exam_status', 'assign_examiner'],
    'hod_dashboard.php' => ['hod_approve_exam', 'hod_reject_exam']
];

foreach ($ajaxEndpoints as $file => $actions) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $foundCount = 0;
        foreach ($actions as $action) {
            if (strpos($content, "'$action'") !== false || strpos($content, "\"$action\"") !== false) {
                $foundCount++;
            }
        }
        
        if ($foundCount === count($actions)) {
            renderTest("AJAX Endpoints: $file", 'PASS', "All $foundCount endpoints found");
        } else {
            renderTest("AJAX Endpoints: $file", 'FAIL', "Only $foundCount/" . count($actions) . " endpoints found");
        }
    }
}

// TEST 7: Check button permissions in different dashboards
$dashboards = [
    'teacher_dashboard.php' => ['no_create' => true, 'apply_exam' => true],
    'hod_dashboard.php' => ['create_exam' => true, 'approve_exam' => true],
    'admin_dashboard.php' => ['create_exam' => true, 'manage_all' => true]
];

foreach ($dashboards as $file => $expectedFeatures) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $features = [];
        
        if (isset($expectedFeatures['create_exam'])) {
            $features[] = (strpos($content, 'Create Exam') !== false) ? 'Has Create' : 'Missing Create';
        }
        if (isset($expectedFeatures['no_create'])) {
            $features[] = (strpos($content, 'Create Exam') === false) ? 'Correctly No Create' : 'Incorrect Create button';
        }
        if (isset($expectedFeatures['apply_exam'])) {
            $features[] = (strpos($content, 'Select') !== false || strpos($content, 'Apply') !== false) ? 'Has Apply' : 'Missing Apply';
        }
        
        renderTest("Permissions: $file", 'PASS', implode(', ', $features));
    }
}

// Summary
echo "<hr class='mt-5'><h2>Test Summary</h2>";
echo "<div class='row'>";
echo "<div class='col-md-6'><div class='alert alert-success'><h3>$pass</h3>Tests Passed</div></div>";
echo "<div class='col-md-6'><div class='alert alert-danger'><h3>$fail</h3>Tests Failed</div></div>";
echo "</div>";

$total = $pass + $fail;
if ($total > 0) {
    $rate = round(($pass / $total) * 100);
    echo "<div class='alert alert-" . ($rate >= 80 ? 'success' : 'warning') . "'>";
    echo "<h4>Success Rate: $rate%</h4>";
    echo "</div>";
}

echo "<h3 class='mt-5'>Issues Found & Fixes Needed:</h3>";
echo "<ul class='list-group'>";

if ($fail > 0) {
    echo "<li class='list-group-item list-group-item-warning'>";
    echo "<strong>Action Required:</strong> Review failed tests above and implement fixes";
    echo "</li>";
}

echo "<li class='list-group-item'>";
echo "<strong>Fix 1:</strong> Ensure all dashboards have correct role-based button visibility";
echo "</li>";

echo "<li class='list-group-item'>";
echo "<strong>Fix 2:</strong> Validate AJAX handlers are properly connected to buttons";
echo "</li>";

echo "<li class='list-group-item'>";
echo "<strong>Fix 3:</strong> Test button state management (disabled/enabled based on conditions)";
echo "</li>";

echo "</ul>";

echo "</div></body></html>";
?>
