<?php
/**
 * College and Department API
 * Provides endpoints for fetching colleges and departments
 * Used during user registration and profile updates
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_colleges':
            getColleges($pdo);
            break;
            
        case 'get_departments':
            $collegeId = $_GET['college_id'] ?? $_POST['college_id'] ?? null;
            getDepartments($pdo, $collegeId);
            break;
            
        case 'add_college':
            addCollege($pdo);
            break;
            
        case 'add_department':
            addDepartment($pdo);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Get list of all colleges
 */
function getColleges($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, college_name as name, 
                   (SELECT COUNT(*) FROM departments WHERE college_id = colleges.id) as department_count,
                   (SELECT COUNT(*) FROM users WHERE college_id = colleges.id) as user_count
            FROM colleges 
            ORDER BY college_name ASC
        ");
        
        $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'colleges' => $colleges
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching colleges: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get departments for a specific college
 */
function getDepartments($pdo, $collegeId = null) {
    try {
        if ($collegeId) {
            $stmt = $pdo->prepare("
                SELECT d.id, d.dept_name as name, d.college_id, c.college_name,
                       (SELECT COUNT(*) FROM users WHERE department_id = d.id) as user_count
                FROM departments d
                LEFT JOIN colleges c ON d.college_id = c.id
                WHERE d.college_id = ?
                ORDER BY d.dept_name ASC
            ");
            $stmt->execute([$collegeId]);
        } else {
            $stmt = $pdo->query("
                SELECT d.id, d.dept_name as name, d.college_id, c.college_name,
                       (SELECT COUNT(*) FROM users WHERE department_id = d.id) as user_count
                FROM departments d
                LEFT JOIN colleges c ON d.college_id = c.id
                ORDER BY c.college_name ASC, d.dept_name ASC
            ");
        }
        
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'departments' => $departments
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching departments: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add a new college (admin only)
 */
function addCollege($pdo) {
    // Check authentication
    start_secure_session();
    if (empty($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        return;
    }
    
    $role = normalize_role($_SESSION['role'] ?? '');
    if ($role !== 'admin') {
        echo json_encode([
            'success' => false,
            'message' => 'Only admins can add colleges'
        ]);
        return;
    }
    
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        echo json_encode([
            'success' => false,
            'message' => 'College name is required'
        ]);
        return;
    }
    
    try {
        // Check if college already exists
        $checkStmt = $pdo->prepare("SELECT id FROM colleges WHERE LOWER(college_name) = LOWER(?)");
        $checkStmt->execute([$name]);
        
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'College already exists'
            ]);
            return;
        }
        
        // Insert new college
        $stmt = $pdo->prepare("INSERT INTO colleges (college_name, college_code, is_active) VALUES (?, CONCAT('COL', LPAD(FLOOR(RAND() * 1000), 3, '0')), 1)");
        $stmt->execute([$name]);
        
        $collegeId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'College added successfully',
            'college_id' => $collegeId,
            'college_name' => $name
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding college: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add a new department (admin and principal only)
 */
function addDepartment($pdo) {
    // Check authentication
    start_secure_session();
    if (empty($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        return;
    }
    
    $role = normalize_role($_SESSION['role'] ?? '');
    $userCollegeId = $_SESSION['college_id'] ?? null;
    
    if (!in_array($role, ['admin', 'principal'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Only admins and principals can add departments'
        ]);
        return;
    }
    
    $name = trim($_POST['name'] ?? '');
    $collegeId = $_POST['college_id'] ?? null;
    
    if (empty($name)) {
        echo json_encode([
            'success' => false,
            'message' => 'Department name is required'
        ]);
        return;
    }
    
    if (empty($collegeId)) {
        echo json_encode([
            'success' => false,
            'message' => 'College ID is required'
        ]);
        return;
    }
    
    // Principal can only add departments to their own college
    if ($role === 'principal' && $collegeId != $userCollegeId) {
        echo json_encode([
            'success' => false,
            'message' => 'You can only add departments to your own college'
        ]);
        return;
    }
    
    try {
        // Check if department already exists for this college
        $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE college_id = ? AND LOWER(dept_name) = LOWER(?)");
        $checkStmt->execute([$collegeId, $name]);
        
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Department already exists in this college'
            ]);
            return;
        }
        
        // Insert new department
        $stmt = $pdo->prepare("INSERT INTO departments (college_id, dept_name, dept_code, is_active) VALUES (?, ?, CONCAT('DEPT', LPAD(FLOOR(RAND() * 100), 2, '0')), 1)");
        $stmt->execute([$collegeId, $name]);
        
        $departmentId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully',
            'department_id' => $departmentId,
            'department_name' => $name
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding department: ' . $e->getMessage()
        ]);
    }
}
?>
