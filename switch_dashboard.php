<?php
/**
 * Dashboard Switcher
 * Handles switching between different role dashboards
 * Supports admin impersonation and role-based access
 */

session_start();
require_once __DIR__ . '/config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get target dashboard
$targetDashboard = $_POST['dashboard'] ?? '';
$currentRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'];

// Valid dashboards
$validDashboards = ['admin', 'principal', 'vice-principal', 'hod', 'teacher'];

if (!in_array($targetDashboard, $validDashboards)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid dashboard']);
    exit;
}

// Admin can view any dashboard (impersonation)
if ($currentRole === 'admin') {
    $_SESSION['view_as'] = $targetDashboard;
    
    // Log the switch in audit trail
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_trail (user_id, action, table_name, new_values, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            'dashboard_switch',
            'sessions',
            json_encode(['view_as' => $targetDashboard]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
    
    // Update user's view_as_role in database for persistence
    try {
        $stmt = $db->prepare("UPDATE users SET view_as_role = ? WHERE id = ?");
        $stmt->execute([$targetDashboard, $userId]);
    } catch (Exception $e) {
        error_log("Failed to update view_as_role: " . $e->getMessage());
    }
    
    // Determine redirect URL
    $dashboardUrls = [
        'admin' => 'admin_dashboard.php',
        'principal' => 'principal_dashboard.php',
        'vice-principal' => 'vp_dashboard.php',
        'hod' => 'hod_dashboard.php',
        'teacher' => 'teacher_dashboard.php'
    ];
    
    $redirectUrl = $dashboardUrls[$targetDashboard] ?? 'admin_dashboard.php';
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Switched to ' . ucfirst($targetDashboard) . ' dashboard',
        'redirect' => $redirectUrl
    ]);
    exit;
}

// Non-admin users can only switch to dashboards they have access to
// Check user permissions
try {
    $stmt = $db->prepare("
        SELECT 
            principal_access, 
            vice_access, 
            hod_access, 
            teacher_access,
            module_permissions
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $permissions = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permissions) {
        throw new Exception('User not found');
    }
    
    // Map dashboard to permission field
    $permissionMap = [
        'principal' => 'principal_access',
        'vice-principal' => 'vice_access',
        'hod' => 'hod_access',
        'teacher' => 'teacher_access'
    ];
    
    $permissionField = $permissionMap[$targetDashboard] ?? null;
    
    // Check if user has access
    if ($permissionField && $permissions[$permissionField] == 1) {
        // Allowed - set session and redirect
        $_SESSION['view_as'] = $targetDashboard;
        
        // Log the switch
        try {
            $stmt = $db->prepare("
                INSERT INTO audit_trail (user_id, action, table_name, new_values, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                'dashboard_switch',
                'sessions',
                json_encode(['view_as' => $targetDashboard, 'current_role' => $currentRole]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
        
        // Determine redirect URL
        $dashboardUrls = [
            'principal' => 'principal_dashboard.php',
            'vice-principal' => 'vp_dashboard.php',
            'hod' => 'hod_dashboard.php',
            'teacher' => 'teacher_dashboard.php'
        ];
        
        $redirectUrl = $dashboardUrls[$targetDashboard] ?? 'dashboard.php';
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Switched to ' . ucfirst($targetDashboard) . ' dashboard',
            'redirect' => $redirectUrl
        ]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Access denied. You do not have permission to view this dashboard.'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Dashboard switch error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit;
}
