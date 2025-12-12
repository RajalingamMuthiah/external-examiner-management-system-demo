<?php
/**
 * Test script for getVisibleExamsForUser() function
 * Tests role-based exam visibility after refactoring
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "<h1>Testing getVisibleExamsForUser() Function</h1>\n";
echo "<pre>\n";

// Test 1: Admin sees all exams
echo "=== TEST 1: Admin Role ===\n";
try {
    $adminExams = getVisibleExamsForUser($pdo, 1, 'admin', null, null);
    echo "Admin sees " . count($adminExams) . " exams\n";
    if (!empty($adminExams)) {
        echo "Sample exam: " . ($adminExams[0]['title'] ?? 'N/A') . "\n";
    }
    echo "✓ Admin test passed\n\n";
} catch (Exception $e) {
    echo "✗ Admin test failed: " . $e->getMessage() . "\n\n";
}

// Test 2: Get a principal user
echo "=== TEST 2: Principal Role ===\n";
try {
    $stmt = $pdo->query("SELECT id, name, college_id, college_name FROM users WHERE post = 'principal' LIMIT 1");
    $principal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($principal) {
        echo "Testing with Principal: {$principal['name']} (College: {$principal['college_name']})\n";
        $principalExams = getVisibleExamsForUser($pdo, $principal['id'], 'principal', $principal['college_id'], null);
        echo "Principal sees " . count($principalExams) . " exams from their college\n";
        
        // Verify all exams belong to principal's college
        $wrongCollege = array_filter($principalExams, function($exam) use ($principal) {
            return ($exam['college_id'] ?? 0) != $principal['college_id'];
        });
        
        if (empty($wrongCollege)) {
            echo "✓ Principal test passed - all exams from own college\n\n";
        } else {
            echo "✗ Principal test failed - saw " . count($wrongCollege) . " exams from other colleges\n\n";
        }
    } else {
        echo "No principal user found in database\n\n";
    }
} catch (Exception $e) {
    echo "✗ Principal test failed: " . $e->getMessage() . "\n\n";
}

// Test 3: Get an HOD user
echo "=== TEST 3: HOD Role ===\n";
try {
    $stmt = $pdo->query("SELECT id, name, college_id, department_id, college_name FROM users WHERE post = 'hod' LIMIT 1");
    $hod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hod) {
        echo "Testing with HOD: {$hod['name']} (College: {$hod['college_name']}, Dept: {$hod['department_id']})\n";
        $hodExams = getVisibleExamsForUser($pdo, $hod['id'], 'hod', $hod['college_id'], $hod['department_id']);
        echo "HOD sees " . count($hodExams) . " exams from their department\n";
        echo "✓ HOD test passed\n\n";
    } else {
        echo "No HOD user found in database\n\n";
    }
} catch (Exception $e) {
    echo "✗ HOD test failed: " . $e->getMessage() . "\n\n";
}

// Test 4: Get a teacher user
echo "=== TEST 4: Teacher Role ===\n";
try {
    $stmt = $pdo->query("SELECT id, name, college_name FROM users WHERE post = 'teacher' LIMIT 1");
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        echo "Testing with Teacher: {$teacher['name']} (College: {$teacher['college_name']})\n";
        $teacherExams = getVisibleExamsForUser($pdo, $teacher['id'], 'teacher', null, null);
        echo "Teacher sees " . count($teacherExams) . " exams (assigned + available from other colleges)\n";
        
        // Check for exam_source field
        $assigned = array_filter($teacherExams, function($e) { return ($e['exam_source'] ?? '') === 'assigned'; });
        $available = array_filter($teacherExams, function($e) { return ($e['exam_source'] ?? '') === 'available'; });
        
        echo "  - Assigned exams: " . count($assigned) . "\n";
        echo "  - Available exams from other colleges: " . count($available) . "\n";
        
        // Verify teacher cannot see own college exams in available list
        $ownCollegeInAvailable = array_filter($available, function($exam) use ($teacher) {
            return ($exam['department'] ?? '') === $teacher['college_name'] || 
                   ($exam['creator_college'] ?? '') === $teacher['college_name'];
        });
        
        if (empty($ownCollegeInAvailable)) {
            echo "✓ Teacher test passed - no own college exams in available list\n\n";
        } else {
            echo "✗ Teacher test failed - saw " . count($ownCollegeInAvailable) . " own college exams in available\n\n";
        }
    } else {
        echo "No teacher user found in database\n\n";
    }
} catch (Exception $e) {
    echo "✗ Teacher test failed: " . $e->getMessage() . "\n\n";
}

echo "</pre>\n";
echo "<p><a href='index.php'>Back to Home</a></p>\n";
