<?php
/**
 * admin_dashboard.php
 *
 * Single-file Admin Dashboard template for an Education/Exam Management System.
 * - Session auth + admin role restriction
 * - PDO prepared statements for DB access
 * - Bootstrap 5 frontend with AJAX-backed tab loading
 * - Permission management (assign/revoke subordinate dashboard access)
 *
 * NOTE: Update DB credentials below to match your environment.
 * Place this file in your webroot (e.g., c:\xampp\htdocs\examiner\)
 * and ensure your login system sets $_SESSION['user_id'] and $_SESSION['role'].
 *
 * Security notes:
 *  - This file demonstrates secure patterns: prepared statements, CSRF token, output escaping.
 *  - Integrate with your real login flow to set session values and a login page at login.php.
 *
 * Author: Generated template
 * Date: 2025-11-12
 */

// Use centralized auth/session helpers
require_once __DIR__ . '/includes/functions.php';
start_secure_session();
require_login();
// Allow admins, principals, vice principals, and teachers to access this dashboard
// Teachers can only access the "Available Exams" module for self-assignment
require_role(['admin', 'principal', 'vice-principal', 'faculty', 'teacher', 'hod']);

// Normalize display name and current admin id
$_SESSION['name'] = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin';
$adminId = (int) ($_SESSION['user_id'] ?? 0);

// DB Connection (PDO)
require_once __DIR__ . '/config/db.php';

// Ensure a CSRF token exists for POST actions initiated via JS
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Precompute counts for sidebar badges and default view
function countPendingUsers(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE status='pending'");
        $r = $stmt->fetch();
        return (int)($r['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function countPendingApprovals(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM approvals WHERE status = ?");
        $stmt->execute(['pending']);
        $r = $stmt->fetch();
        return (int)($r['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$PENDING_USERS_COUNT = countPendingUsers($pdo);
$PENDING_APPROVALS_COUNT = countPendingApprovals($pdo);
$PENDING_TOTAL = $PENDING_USERS_COUNT + $PENDING_APPROVALS_COUNT;

// ---------------------------
// Dashboard Statistics Functions
// ---------------------------
/**
 * Get comprehensive dashboard statistics
 * Returns: total users, colleges, exams, pending verifications, recent activity
 */
function getDashboardStats(PDO $pdo) {
    try {
        $stats = [];
        
        // Total users count
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
        $stats['total_users'] = (int)$stmt->fetchColumn();
        
        // Total colleges count (distinct)
        $stmt = $pdo->query("SELECT COUNT(DISTINCT college_name) AS total FROM users WHERE college_name IS NOT NULL AND college_name != ''");
        $stats['total_colleges'] = (int)$stmt->fetchColumn();
        
        // Total exams count
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM exams");
        $stats['total_exams'] = (int)$stmt->fetchColumn();
        
        // Pending verifications
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE status='pending'");
        $stats['pending_verifications'] = (int)$stmt->fetchColumn();
        
        // Users by role breakdown
        $stmt = $pdo->query("SELECT post, COUNT(*) AS count FROM users GROUP BY post");
        $stats['users_by_role'] = $stmt->fetchAll();
        
        // Recent registrations (last 7 days)
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_registrations'] = (int)$stmt->fetchColumn();
        
        // Verified users percentage
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE status='verified'");
        $verified = (int)$stmt->fetchColumn();
        $stats['verified_users'] = $verified;
        $stats['verification_rate'] = $stats['total_users'] > 0 ? round(($verified / $stats['total_users']) * 100, 1) : 0;
        
        // Pending exams count (for notification badge)
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM exams WHERE status='Pending' OR status IS NULL");
        $stats['pending_exams'] = (int)$stmt->fetchColumn();
        
        return $stats;
    } catch (Throwable $e) {
        return [
            'total_users' => 0,
            'total_colleges' => 0,
            'total_exams' => 0,
            'pending_verifications' => 0,
            'pending_exams' => 0,
            'users_by_role' => [],
            'recent_registrations' => 0,
            'verified_users' => 0,
            'verification_rate' => 0
        ];
    }
}

/**
 * Get chart data for admin analytics
 * Returns data formatted for Chart.js
 */
function getChartData(PDO $pdo) {
    try {
        $charts = [];
        
        // Users by role for pie chart
        $stmt = $pdo->query("SELECT post, COUNT(*) AS count FROM users GROUP BY post");
        $roleData = $stmt->fetchAll();
        $charts['roles'] = [
            'labels' => array_column($roleData, 'post'),
            'data' => array_column($roleData, 'count')
        ];
        
        // User registrations by month (last 6 months)
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count 
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month 
            ORDER BY month ASC
        ");
        $monthData = $stmt->fetchAll();
        $charts['registrations'] = [
            'labels' => array_column($monthData, 'month'),
            'data' => array_column($monthData, 'count')
        ];
        
        // Verification status for doughnut chart
        $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM users GROUP BY status");
        $statusData = $stmt->fetchAll();
        $charts['verification_status'] = [
            'labels' => array_column($statusData, 'status'),
            'data' => array_column($statusData, 'count')
        ];
        
        return $charts;
    } catch (Throwable $e) {
        return [
            'roles' => ['labels' => [], 'data' => []],
            'registrations' => ['labels' => [], 'data' => []],
            'verification_status' => ['labels' => [], 'data' => []]
        ];
    }
}

/**
 * Log admin activity for audit trail
 * Stores admin actions with timestamp and details
 */
function logAdminActivity(PDO $pdo, $adminId, $action, $details = '') {
    try {
        // Create audit_logs table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(admin_id),
                INDEX(created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$adminId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'Unknown']);
        return true;
    } catch (Throwable $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent audit logs for admin transparency
 */
function getAuditLogs(PDO $pdo, $limit = 50) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.name AS admin_name, u.email AS admin_email
            FROM audit_logs a
            LEFT JOIN users u ON a.admin_id = u.id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get list of all unique colleges for filtering
 */
function getAllColleges(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT college_name FROM users WHERE college_name IS NOT NULL AND college_name != '' ORDER BY college_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Bulk update user status
 * Used for batch verify/reject/enable/disable operations
 */
function bulkUpdateUserStatus(PDO $pdo, array $userIds, $status, $adminId) {
    if (empty($userIds)) return 0;
    
    $allowed_statuses = ['verified', 'rejected', 'pending'];
    if (!in_array($status, $allowed_statuses, true)) {
        return 0;
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id IN ($placeholders)");
        $params = array_merge([$status], $userIds);
        $stmt->execute($params);
        
        // Log bulk action
        logAdminActivity($pdo, $adminId, "Bulk Status Update", "Updated " . count($userIds) . " users to status: $status");
        
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Search and filter users with advanced criteria
 */
function searchUsers(PDO $pdo, $filters = []) {
    try {
        $sql = "SELECT id, name, email, post, college_name, phone, status, created_at,
                CASE WHEN password IS NULL OR password = '' THEN 0 ELSE 1 END as has_password 
                FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR college_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['role'])) {
            $sql .= " AND post = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['college'])) {
            $sql .= " AND college_name = ?";
            $params[] = $filters['college'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// ---------------------------
// Helper functions
// ---------------------------
function esc($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Fetch users for the User Management tab
function getRegisteredUsers(PDO $pdo) {
    // Fetches all users, including pending and rejected ones, for the admin view.
    // Include password field to check if user has password set
    $stmt = $pdo->prepare('SELECT id, name, email, post, college_name, phone, status, created_at, 
                          CASE WHEN password IS NULL OR password = "" THEN 0 ELSE 1 END as has_password 
                          FROM users ORDER BY created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

// Update a user's status (e.g., from 'pending' to 'verified')
function updateUserStatus(PDO $pdo, $userId, $status) {
    // Ensure status is one of the allowed values to prevent injection
    $allowed_statuses = ['verified', 'rejected', 'pending'];
    if (!in_array($status, $allowed_statuses, true)) {
        return false; // Invalid status
    }

    try {
        // If verifying user, check if they have a password - if not, generate one
        if ($status === 'verified') {
            $checkStmt = $pdo->prepare('SELECT password, email FROM users WHERE id = :id LIMIT 1');
            $checkStmt->execute([':id' => $userId]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && (empty($user['password']) || is_null($user['password']))) {
                // User has no password - generate a default one
                $defaultPassword = 'Welcome@123'; // Or generate random: bin2hex(random_bytes(4))
                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                $updateStmt = $pdo->prepare('UPDATE users SET status = :status, password = :password WHERE id = :id');
                $result = $updateStmt->execute([
                    ':status' => $status,
                    ':password' => $hashedPassword,
                    ':id' => $userId
                ]);
                
                // Log that a password was generated
                error_log("Generated default password for user ID $userId (email: {$user['email']})");
                
                return $result;
            }
        }
        
        // Normal status update (user already has password or status is not 'verified')
        $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
        return $stmt->execute([':status' => $status, ':id' => $userId]);
    } catch (Throwable $e) {
        error_log("Error updating user status: " . $e->getMessage());
        return false;
    }
}

function fetchPendingUsers(PDO $pdo) {
    try {
        $stmt = $pdo->prepare('SELECT id, name, email, post, college_name, phone, status, created_at FROM users WHERE status = ? ORDER BY created_at ASC');
        $stmt->execute(['pending']);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}


// Fetch some example data using prepared statements
function fetchUpcomingExams(PDO $pdo, $limit = 10) {
    try {
        // Try upcoming exams first
        $stmt = $pdo->prepare('SELECT id, title, exam_date, department FROM exams WHERE exam_date >= CURDATE() ORDER BY exam_date ASC LIMIT :limit');
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!empty($rows)) return $rows;

        // Fallback: show most recent past exams if no upcoming found
        $stmt = $pdo->prepare('SELECT id, title, exam_date, department FROM exams ORDER BY exam_date DESC LIMIT :limit');
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fetchFacultyWorkload(PDO $pdo, $limit = 10) {
    try {
        // Aggregate assignments per user (treating users as faculty)
        $sql = 'SELECT u.id, u.name, COUNT(a.id) AS assignments
                FROM users u
                LEFT JOIN assignments a ON a.faculty_id = u.id
                GROUP BY u.id, u.name
                ORDER BY assignments DESC
                LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fetchPendingApprovals(PDO $pdo) {
    try {
        $stmt = $pdo->prepare('SELECT id, requester_id, type, created_at, status FROM approvals WHERE status = ? ORDER BY created_at ASC');
        $stmt->execute(['pending']);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getUsersForPermissionManagement(PDO $pdo) {
    try {
        // Return users and current permissions with granular module access
        $sql = 'SELECT u.id, u.name, u.email, u.post,
                p.principal_access, p.vice_access, p.hod_access, p.teacher_access,
                p.module_overview, p.module_user_management, p.module_exam_management,
                p.module_approvals, p.module_available_exams, p.module_permissions,
                p.module_analytics, p.module_audit_logs, p.module_settings,
                p.module_principal_dash, p.module_vice_dash, p.module_hod_dash, p.module_teacher_dash
                FROM users u
                LEFT JOIN permissions p ON p.user_id = u.id
                WHERE u.status = "verified"
                ORDER BY u.name ASC';
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function updateUserPermissions(PDO $pdo, $userId, $perms) {
    // Enhanced: Update both dashboard access and granular module permissions
    // perms: array with keys for dashboard access and module access (0/1)
    
    // First ensure permissions record exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE user_id = ?");
    $checkStmt->execute([$userId]);
    if ($checkStmt->fetchColumn() == 0) {
        // Create initial permissions record
        $insertStmt = $pdo->prepare("INSERT INTO permissions (user_id) VALUES (?)");
        $insertStmt->execute([$userId]);
    }
    
    // Build dynamic UPDATE query for all permission fields
    $fields = [];
    $values = [':user_id' => $userId];
    
    // Dashboard access permissions
    if (isset($perms['principal_access'])) {
        $fields[] = 'principal_access = :principal';
        $values[':principal'] = $perms['principal_access'];
    }
    if (isset($perms['vice_access'])) {
        $fields[] = 'vice_access = :vice';
        $values[':vice'] = $perms['vice_access'];
    }
    if (isset($perms['hod_access'])) {
        $fields[] = 'hod_access = :hod';
        $values[':hod'] = $perms['hod_access'];
    }
    if (isset($perms['teacher_access'])) {
        $fields[] = 'teacher_access = :teacher';
        $values[':teacher'] = $perms['teacher_access'];
    }
    
    // Module-level permissions
    $modules = [
        'module_overview', 'module_user_management', 'module_exam_management',
        'module_approvals', 'module_available_exams', 'module_permissions',
        'module_analytics', 'module_audit_logs', 'module_settings',
        'module_principal_dash', 'module_vice_dash', 'module_hod_dash', 'module_teacher_dash'
    ];
    
    foreach ($modules as $module) {
        if (isset($perms[$module])) {
            $placeholder = ':' . $module;
            $fields[] = "$module = $placeholder";
            $values[$placeholder] = $perms[$module];
        }
    }
    
    if (empty($fields)) {
        return true; // Nothing to update
    }
    
    $sql = "UPDATE permissions SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    return true;
}

// ---------------------------
// AJAX/Endpoint handling
// ---------------------------
$action = $_REQUEST['action'] ?? null;
if ($action) {
    try {
        if ($action === 'load_module') {
            // Return partial HTML for the requested module
            $module = $_GET['module'] ?? 'overview';

            // In real app, check current admin's permissions to manage which modules to show
            switch ($module) {
                case 'overview':
                    // NEW: Admin Overview Dashboard with Stats & Charts
                    $stats = getDashboardStats($pdo);
                    $colleges = getAllColleges($pdo);
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1">Admin Dashboard Overview</h3>
                                <p class="text-muted small mb-0">Welcome back, <?= esc($_SESSION['name']) ?>! Here's what's happening today.</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-primary" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Quick Stats Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-white-50 small fw-semibold mb-1">Total Users</div>
                                                <div class="h2 mb-0 fw-bold"><?= number_format($stats['total_users']) ?></div>
                                                <div class="small mt-2">
                                                    <span class="badge bg-white bg-opacity-25">+<?= $stats['recent_registrations'] ?> this week</span>
                                                </div>
                                            </div>
                                            <div class="fs-1 opacity-50">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-white-50 small fw-semibold mb-1">Total Colleges</div>
                                                <div class="h2 mb-0 fw-bold"><?= number_format($stats['total_colleges']) ?></div>
                                                <div class="small mt-2">
                                                    <span class="badge bg-white bg-opacity-25">Institutions</span>
                                                </div>
                                            </div>
                                            <div class="fs-1 opacity-50">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5Z"/>
                                                    <path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466 4.176 9.032Z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-white-50 small fw-semibold mb-1">Total Exams</div>
                                                <div class="h2 mb-0 fw-bold"><?= number_format($stats['total_exams']) ?></div>
                                                <div class="small mt-2">
                                                    <span class="badge bg-white bg-opacity-25">Scheduled</span>
                                                </div>
                                            </div>
                                            <div class="fs-1 opacity-50">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M14 3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h12zM2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H2z"/>
                                                    <path d="M5 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm4 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-white-50 small fw-semibold mb-1">Pending Verifications</div>
                                                <div class="h2 mb-0 fw-bold"><?= number_format($stats['pending_verifications']) ?></div>
                                                <div class="small mt-2">
                                                    <a href="#" class="text-white text-decoration-none" onclick="loadModule('approvals_verifications'); return false;">
                                                        <span class="badge bg-white bg-opacity-25">Review Now →</span>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="fs-1 opacity-50">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-white">
                                        <h6 class="mb-0 fw-semibold">Users by Role</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="rolesChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-white">
                                        <h6 class="mb-0 fw-semibold">Verification Status</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="verificationChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Breakdown Table -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-semibold">Role Distribution</h6>
                                        <small class="text-muted">Verification Rate: <strong><?= $stats['verification_rate'] ?>%</strong></small>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Role</th>
                                                        <th>Total Count</th>
                                                        <th>Percentage</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats['users_by_role'] as $role): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-primary"><?= esc(ucwords(str_replace('_', ' ', $role['post']))) ?></span>
                                                        </td>
                                                        <td><strong><?= (int)$role['count'] ?></strong></td>
                                                        <td>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar" role="progressbar" 
                                                                     style="width: <?= $stats['total_users'] > 0 ? round(($role['count'] / $stats['total_users']) * 100) : 0 ?>%"></div>
                                                            </div>
                                                            <small class="text-muted"><?= $stats['total_users'] > 0 ? round(($role['count'] / $stats['total_users']) * 100, 1) : 0 ?>%</small>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="filterUsersByRole('<?= esc($role['post']) ?>')">
                                                                View Users
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
                    <script>
                    // Fetch chart data and render
                    $.getJSON('?action=get_chart_data', function(data) {
                        if (data.success && data.charts) {
                            // Roles Pie Chart
                            new Chart(document.getElementById('rolesChart'), {
                                type: 'doughnut',
                                data: {
                                    labels: data.charts.roles.labels.map(l => l.charAt(0).toUpperCase() + l.slice(1).replace('_', ' ')),
                                    datasets: [{
                                        data: data.charts.roles.data,
                                        backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#fa709a', '#43e97b']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            });

                            // Verification Status Pie Chart
                            new Chart(document.getElementById('verificationChart'), {
                                type: 'pie',
                                data: {
                                    labels: data.charts.verification_status.labels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
                                    datasets: [{
                                        data: data.charts.verification_status.data,
                                        backgroundColor: ['#43e97b', '#fa709a', '#f5576c']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            });
                        }
                    });

                    function filterUsersByRole(role) {
                        loadModule('user_management');
                        // Add a small delay then filter
                        setTimeout(function() {
                            if ($('#roleFilter').length) {
                                $('#roleFilter').val(role).trigger('change');
                            }
                        }, 500);
                    }
                    </script>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                
                // ==========================================================================
                // EMBEDDED ROLE DASHBOARDS - Admin can view all role-specific interfaces
                // These modules load content from existing role dashboard files inline
                // without full page reload, maintaining session and admin privileges
                // ==========================================================================
                
                case 'principal':
                    // INTEGRATED: Load actual Principal Dashboard from dashboard.php
                    // Using iframe for seamless integration without conflicts
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-building"></i> Principal Dashboard
                                    <span class="badge bg-primary ms-2">Integrated View</span>
                                </h3>
                                <p class="text-muted small mb-0">Loading from dashboard.php - Full principal functionality</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Admin
                                </button>
                            </div>
                        </div>
                        <div class="position-relative" style="height: calc(100vh - 200px);">
                            <iframe src="dashboard.php" 
                                    style="width: 100%; height: 100%; border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" 
                                    title="Principal Dashboard">
                            </iframe>
                        </div>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                
                // OLD EMBEDDED VERSION - Now replaced with iframe integration
                case 'principal_old':
                    // Embed Principal Dashboard content inline
                    // Admin has full access to all principal functions
                    $exams = fetchUpcomingExams($pdo, 5);
                    $workload = fetchFacultyWorkload($pdo, 5);
                    $pending = fetchPendingApprovals($pdo);
                    ob_start();
                    ?>
                    <div class="p-3">
                        <!-- Tab indicator showing which dashboard is active -->
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-building"></i> Principal Dashboard
                                    <span class="badge bg-primary ms-2">Role View</span>
                                </h3>
                                <p class="text-muted small mb-0">Administrative oversight and exam management</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Overview
                                </button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-calendar-event"></i> Upcoming Exams
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($exams)): ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                                <p class="text-muted mb-2 mt-2">No upcoming exams.</p>
                                                <p class="text-muted small">Tip: If you're setting up locally, import <code>db/schema.sql</code> and <code>db/seed.sql</code> into your <strong>eems</strong> database to load sample data.</p>
                                                <button class="btn btn-sm btn-primary mt-2" onclick="alert('Exam creation feature coming soon!')">
                                                    <i class="bi bi-plus-lg"></i> Create Exam
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($exams as $e): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                        <div>
                                                            <strong><?= esc($e['title']) ?></strong>
                                                            <div class="text-muted small">
                                                                <i class="bi bi-building"></i> <?= esc($e['department']) ?> • 
                                                                <i class="bi bi-calendar3"></i> <?= esc($e['exam_date']) ?>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <a href="#" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <i class="bi bi-people"></i> Faculty Workload Summary
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($workload)): ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-person-x text-muted" style="font-size: 3rem;"></i>
                                                <p class="text-muted mb-2 mt-2">No workload data available.</p>
                                                <p class="text-muted small">Tip: Ensure the <code>assignments</code> table exists and has rows. The seed file populates examples.</p>
                                            </div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($workload as $w): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                        <div>
                                                            <i class="bi bi-person-circle"></i> <?= esc($w['name']) ?>
                                                        </div>
                                                        <span class="badge bg-secondary rounded-pill"><?= (int)$w['assignments'] ?> assignments</span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="bi bi-clock-history"></i> Pending Approvals
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($pending)): ?>
                                            <div class="text-center py-3">
                                                <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                                                <p class="text-muted mt-2 mb-0">No pending approvals. All clear!</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Type</th>
                                                            <th>Requester</th>
                                                            <th>Date</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($pending as $p): ?>
                                                            <tr>
                                                                <td><strong><?= esc($p['type']) ?></strong></td>
                                                                <td>User #<?= esc($p['requester_id']) ?></td>
                                                                <td><small><?= esc($p['created_at']) ?></small></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-success approve-btn" data-id="<?= (int)$p['id'] ?>">
                                                                        <i class="bi bi-check-lg"></i> Approve
                                                                    </button>
                                                                    <button class="btn btn-sm btn-danger reject-btn" data-id="<?= (int)$p['id'] ?>">
                                                                        <i class="bi bi-x-lg"></i> Reject
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                
                case 'audit_logs':
                    // ENHANCED: Audit trail for all admin activities
                    $logs = getAuditLogs($pdo, 100); // Get last 100 logs
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1"><i class="bi bi-clock-history"></i> Audit Logs</h3>
                                <p class="text-muted small mb-0">Complete activity history for all admin actions</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-primary" onclick="location.href='?action=export_csv&type=audit_logs'">
                                    <i class="bi bi-download"></i> Export Logs
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="loadModule('audit_logs')">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Filter Controls -->
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Search Action</label>
                                        <input type="text" id="searchAction" class="form-control form-control-sm" placeholder="Search actions...">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Admin User</label>
                                        <select id="adminFilter" class="form-select form-select-sm">
                                            <option value="">All Admins</option>
                                            <?php
                                            $uniqueAdmins = array_unique(array_column($logs, 'admin_name'));
                                            foreach ($uniqueAdmins as $admin): 
                                                if ($admin): ?>
                                                <option value="<?= esc($admin) ?>"><?= esc($admin) ?></option>
                                                <?php endif;
                                            endforeach; 
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold">Date From</label>
                                        <input type="date" id="dateFrom" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold">Date To</label>
                                        <input type="date" id="dateTo" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button class="btn btn-sm btn-primary w-100" onclick="filterAuditLogs()">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Total Actions</small>
                                        <h5 class="mb-0"><?= count($logs) ?></h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Today</small>
                                        <h5 class="mb-0"><?= count(array_filter($logs, function($log) { return date('Y-m-d', strtotime($log['created_at'] ?? 'now')) === date('Y-m-d'); })) ?></h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Unique Admins</small>
                                        <h5 class="mb-0"><?= count(array_unique(array_column($logs, 'admin_id'))) ?></h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <small class="text-muted">Last Activity</small>
                                        <h5 class="mb-0 small"><?= !empty($logs) ? date('H:i', strtotime($logs[0]['created_at'] ?? 'now')) : 'N/A' ?></h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Audit Logs Table -->
                        <div class="card shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="auditLogsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Admin</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($logs)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">No audit logs found</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($logs as $log): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <strong><?= date('M d, Y', strtotime($log['created_at'] ?? 'now')) ?></strong>
                                                                <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'] ?? 'now')) ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-sm me-2 bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 11px; font-weight: bold;">
                                                                    <?= $log['admin_name'] ? strtoupper(substr($log['admin_name'], 0, 2)) : 'NA' ?>
                                                                </div>
                                                                <div>
                                                                    <strong><?= esc($log['admin_name'] ?? 'Unknown') ?></strong>
                                                                    <br><small class="text-muted">#<?= (int)$log['admin_id'] ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge 
                                                                <?php 
                                                                if (stripos($log['action'], 'verify') !== false || stripos($log['action'], 'approve') !== false) echo 'bg-success';
                                                                elseif (stripos($log['action'], 'reject') !== false || stripos($log['action'], 'delete') !== false) echo 'bg-danger';
                                                                elseif (stripos($log['action'], 'update') !== false || stripos($log['action'], 'change') !== false) echo 'bg-warning text-dark';
                                                                else echo 'bg-info';
                                                                ?>">
                                                                <?= esc($log['action']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?= esc($log['details']) ?></small>
                                                        </td>
                                                        <td>
                                                            <code class="small"><?= esc($log['ip_address']) ?></code>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <script>
                        function filterAuditLogs() {
                            const search = $('#searchAction').val().toLowerCase();
                            const admin = $('#adminFilter').val();
                            const dateFrom = $('#dateFrom').val();
                            const dateTo = $('#dateTo').val();

                            $('#auditLogsTable tbody tr').each(function() {
                                const row = $(this);
                                const action = row.find('td:eq(2)').text().toLowerCase();
                                const adminName = row.find('td:eq(1) strong').text();
                                const timestamp = row.find('td:eq(0) strong').text();

                                let show = true;

                                if (search && action.indexOf(search) === -1) show = false;
                                if (admin && adminName !== admin) show = false;
                                
                                // Simple date filtering (could be enhanced)
                                if (dateFrom || dateTo) {
                                    const logDate = new Date(timestamp).toISOString().split('T')[0];
                                    if (dateFrom && logDate < dateFrom) show = false;
                                    if (dateTo && logDate > dateTo) show = false;
                                }

                                row.toggle(show);
                            });

                            // Update visible count
                            const visible = $('#auditLogsTable tbody tr:visible').length;
                            if (visible === 0) {
                                $('#auditLogsTable tbody').append('<tr class="no-results"><td colspan="5" class="text-center text-muted py-4">No logs match your filters</td></tr>');
                            } else {
                                $('#auditLogsTable tbody tr.no-results').remove();
                            }
                        }

                        // Real-time search
                        $('#searchAction').on('keyup', function() {
                            filterAuditLogs();
                        });
                        </script>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                
                case 'vice':
                    // INTEGRATED: Load actual Vice Principal Dashboard from VP.php
                    // Using iframe for seamless integration
                    ob_start(); ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-briefcase"></i> Vice Principal Dashboard
                                    <span class="badge bg-info ms-2">Integrated View</span>
                                </h3>
                                <p class="text-muted small mb-0">Loading from VP.php - Full VP functionality</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Admin
                                </button>
                            </div>
                        </div>
                        <div class="position-relative" style="height: calc(100vh - 200px);">
                            <iframe src="VP.php" 
                                    style="width: 100%; height: 100%; border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" 
                                    title="Vice Principal Dashboard">
                            </iframe>
                        </div>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                
                // OLD EMBEDDED VERSION - Now replaced with iframe integration
                case 'vice_old':
                    // EMBEDDED: Vice Principal Dashboard - Admin can access all VP functions
                    // This integrates content from VP.php without full page reload
                    ob_start(); ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-briefcase"></i> Vice Principal Dashboard
                                    <span class="badge bg-info ms-2">Role View</span>
                                </h3>
                                <p class="text-muted small mb-0">Department-wise exam oversight and HOD request management</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Overview
                                </button>
                            </div>
                        </div>

                        <!-- VP Dashboard Tabs Navigation -->
                        <ul class="nav nav-tabs mb-3" id="vpTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="vp-overview-tab" data-bs-toggle="tab" data-bs-target="#vp-overview" type="button">
                                    <i class="bi bi-speedometer2"></i> Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="vp-requests-tab" data-bs-toggle="tab" data-bs-target="#vp-requests" type="button">
                                    <i class="bi bi-inbox"></i> Examiner Requests
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="vp-scheduling-tab" data-bs-toggle="tab" data-bs-target="#vp-scheduling" type="button">
                                    <i class="bi bi-calendar3"></i> Scheduling
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="vp-reports-tab" data-bs-toggle="tab" data-bs-target="#vp-reports" type="button">
                                    <i class="bi bi-file-earmark-bar-graph"></i> Reports
                                </button>
                            </li>
                        </ul>

                        <!-- VP Tabs Content -->
                        <div class="tab-content" id="vpTabsContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="vp-overview" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card shadow-sm mb-3">
                                            <div class="card-body">
                                                <h6 class="text-muted small">Pending HOD Requests</h6>
                                                <h3 class="mb-0">0</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card shadow-sm mb-3">
                                            <div class="card-body">
                                                <h6 class="text-muted small">Active Examiners</h6>
                                                <h3 class="mb-0">0</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card shadow-sm mb-3">
                                            <div class="card-body">
                                                <h6 class="text-muted small">Upcoming Exams</h6>
                                                <h3 class="mb-0">0</h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <h5>Department Summaries</h5>
                                        <p class="text-muted">Department-wise exam assignment statistics</p>
                                        <?php
                                        // Include VP-specific content from includes/vp_requests_panel.php if it exists
                                        if (file_exists(__DIR__ . '/includes/vp_requests_panel.php')) {
                                            include __DIR__ . '/includes/vp_requests_panel.php';
                                        } else {
                                            echo '<div class="alert alert-info">VP panel integration ready. Add includes/vp_requests_panel.php for full functionality.</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Requests Tab -->
                            <div class="tab-pane fade" id="vp-requests" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="bi bi-inbox"></i> HOD Examiner Requests
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Review and approve examiner assignment requests from department heads</p>
                                        <div id="vp-requests-container">
                                            <?php
                                            // Embed VP examiner requests functionality
                                            if (file_exists(__DIR__ . '/includes/vp_examiners_panel.php')) {
                                                include __DIR__ . '/includes/vp_examiners_panel.php';
                                            } else {
                                                echo '<div class="alert alert-secondary">No pending requests. System ready for VP examiner management.</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduling Tab -->
                            <div class="tab-pane fade" id="vp-scheduling" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-calendar3"></i> Exam Scheduling
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Coordinate exam schedules across departments</p>
                                        <div class="btn-group mb-3">
                                            <button class="btn btn-sm btn-outline-primary" onclick="alert('Create schedule feature')">
                                                <i class="bi bi-plus-lg"></i> Create Schedule
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="alert('View calendar')">
                                                <i class="bi bi-calendar2-week"></i> View Calendar
                                            </button>
                                        </div>
                                        <div class="alert alert-info">Scheduling integration ready</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reports Tab -->
                            <div class="tab-pane fade" id="vp-reports" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <i class="bi bi-file-earmark-bar-graph"></i> Reports & Analytics
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Generate reports on examiner utilization and department performance</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <button class="btn btn-outline-success w-100 mb-2" onclick="alert('Export report')">
                                                    <i class="bi bi-file-earmark-excel"></i> Examiner Utilization Report
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <button class="btn btn-outline-info w-100 mb-2" onclick="alert('Export report')">
                                                    <i class="bi bi-file-earmark-pdf"></i> Department Summary
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $html = ob_get_clean(); echo $html; exit;
                case 'hod':
                    // INTEGRATED: Load actual HOD Dashboard from hod_dashboard.php
                    // Using iframe for seamless integration
                    ob_start(); ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-diagram-3"></i> HOD Dashboard
                                    <span class="badge bg-success ms-2">Integrated View</span>
                                </h3>
                                <p class="text-muted small mb-0">Loading from hod_dashboard.php - Full HOD functionality</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Admin
                                </button>
                            </div>
                        </div>
                        <div class="position-relative" style="height: calc(100vh - 200px);">
                            <iframe src="hod_dashboard.php" 
                                    style="width: 100%; height: 100%; border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" 
                                    title="HOD Dashboard">
                            </iframe>
                        </div>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                
                case 'hod_old':
                    // EMBEDDED: HOD Dashboard - Admin oversight of department head functions
                    // Integrates HOD-specific features from hod_dashboard.php inline
                    ob_start(); ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-diagram-3"></i> HOD Dashboard
                                    <span class="badge bg-success ms-2">Role View</span>
                                </h3>
                                <p class="text-muted small mb-0">Department faculty management and exam duty assignments</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Overview
                                </button>
                            </div>
                        </div>

                        <!-- HOD Dashboard Tabs -->
                        <ul class="nav nav-tabs mb-3" id="hodTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="hod-overview-tab" data-bs-toggle="tab" data-bs-target="#hod-overview" type="button">
                                    <i class="bi bi-speedometer2"></i> Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="hod-faculty-tab" data-bs-toggle="tab" data-bs-target="#hod-faculty" type="button">
                                    <i class="bi bi-people"></i> Faculty Assignments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="hod-availability-tab" data-bs-toggle="tab" data-bs-target="#hod-availability" type="button">
                                    <i class="bi bi-calendar-check"></i> Availability
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="hod-nominations-tab" data-bs-toggle="tab" data-bs-target="#hod-nominations" type="button">
                                    <i class="bi bi-person-plus"></i> Nominations
                                </button>
                            </li>
                        </ul>

                        <!-- HOD Tabs Content -->
                        <div class="tab-content" id="hodTabsContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="hod-overview" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card shadow-sm text-center">
                                            <div class="card-body">
                                                <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                                                <h4 class="mt-2">0</h4>
                                                <p class="text-muted small mb-0">Department Faculty</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card shadow-sm text-center">
                                            <div class="card-body">
                                                <i class="bi bi-calendar-event text-success" style="font-size: 2rem;"></i>
                                                <h4 class="mt-2">0</h4>
                                                <p class="text-muted small mb-0">Upcoming Exams</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card shadow-sm text-center">
                                            <div class="card-body">
                                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                                                <h4 class="mt-2">0</h4>
                                                <p class="text-muted small mb-0">Conflicts</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card shadow-sm text-center">
                                            <div class="card-body">
                                                <i class="bi bi-check-circle text-info" style="font-size: 2rem;"></i>
                                                <h4 class="mt-2">0</h4>
                                                <p class="text-muted small mb-0">Approved Requests</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Faculty Assignments Tab -->
                            <div class="tab-pane fade" id="hod-faculty" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-people"></i> Faculty Exam Duty Assignments
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <p class="text-muted mb-0">Manage and assign faculty to exam duties</p>
                                            <button class="btn btn-sm btn-primary" onclick="alert('Assign faculty feature')">
                                                <i class="bi bi-plus-lg"></i> New Assignment
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Faculty</th>
                                                        <th>Assigned Exams</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">No assignments yet</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Availability Tab -->
                            <div class="tab-pane fade" id="hod-availability" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <i class="bi bi-calendar-check"></i> Faculty Availability Check
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Check faculty availability and identify potential conflicts</p>
                                        <?php
                                        // Embed HOD availability panel if exists
                                        if (file_exists(__DIR__ . '/includes/hod_availability_panel.php')) {
                                            include __DIR__ . '/includes/hod_availability_panel.php';
                                        } else {
                                            echo '<div class="alert alert-info">Availability tracking integration ready</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Nominations Tab -->
                            <div class="tab-pane fade" id="hod-nominations" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="bi bi-person-plus"></i> Examiner Nominations
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Nominate faculty as external examiners</p>
                                        <?php
                                        // Embed HOD nominations panel if exists
                                        if (file_exists(__DIR__ . '/includes/hod_nominations_panel.php')) {
                                            include __DIR__ . '/includes/hod_nominations_panel.php';
                                        } else {
                                            echo '<div class="alert alert-info">Nominations system integration ready</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $html = ob_get_clean(); echo $html; exit;
                case 'teacher':
                    // EMBEDDED: Teacher Dashboard - Admin view of faculty member interface
                    // Shows teacher-level functionality for testing and oversight
                    ob_start(); ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-person"></i> Teacher Dashboard
                                    <span class="badge bg-secondary ms-2">Role View</span>
                                </h3>
                                <p class="text-muted small mb-0">Personal exam assignments and availability management</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Overview
                                </button>
                            </div>
                        </div>

                        <!-- Teacher Dashboard Tabs -->
                        <ul class="nav nav-tabs mb-3" id="teacherTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="teacher-assignments-tab" data-bs-toggle="tab" data-bs-target="#teacher-assignments" type="button">
                                    <i class="bi bi-list-check"></i> My Assignments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="teacher-calendar-tab" data-bs-toggle="tab" data-bs-target="#teacher-calendar" type="button">
                                    <i class="bi bi-calendar3"></i> Calendar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="teacher-availability-tab" data-bs-toggle="tab" data-bs-target="#teacher-availability" type="button">
                                    <i class="bi bi-calendar-check"></i> Mark Availability
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="teacher-notifications-tab" data-bs-toggle="tab" data-bs-target="#teacher-notifications" type="button">
                                    <i class="bi bi-bell"></i> Notifications
                                </button>
                            </li>
                        </ul>

                        <!-- Teacher Tabs Content -->
                        <div class="tab-content" id="teacherTabsContent">
                            <!-- Assignments Tab -->
                            <div class="tab-pane fade show active" id="teacher-assignments" role="tabpanel">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="card shadow-sm">
                                            <div class="card-body text-center">
                                                <i class="bi bi-briefcase text-primary" style="font-size: 2.5rem;"></i>
                                                <h4 class="mt-2">0</h4>
                                                <p class="text-muted mb-0">Active Assignments</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card shadow-sm">
                                            <div class="card-body text-center">
                                                <i class="bi bi-calendar-event text-success" style="font-size: 2.5rem;"></i>
                                                <h4 class="mt-2">0</h4>
                                                <p class="text-muted mb-0">Upcoming Exams</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-list-check"></i> Current Exam Duties
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> No current exam duty assignments
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Calendar Tab -->
                            <div class="tab-pane fade" id="teacher-calendar" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <i class="bi bi-calendar3"></i> Exam Schedule Calendar
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">View your exam duty schedule in calendar format</p>
                                        <div class="alert alert-secondary text-center py-5">
                                            <i class="bi bi-calendar2-week" style="font-size: 3rem;"></i>
                                            <p class="mt-3 mb-0">Calendar integration ready</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Availability Tab -->
                            <div class="tab-pane fade" id="teacher-availability" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="bi bi-calendar-check"></i> Mark Your Availability
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Indicate when you're available for exam duties</p>
                                        <form>
                                            <div class="mb-3">
                                                <label class="form-label">Select Dates</label>
                                                <input type="date" class="form-control" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Availability Status</label>
                                                <select class="form-select">
                                                    <option>Available</option>
                                                    <option>Not Available</option>
                                                    <option>Tentative</option>
                                                </select>
                                            </div>
                                            <button type="button" class="btn btn-primary" onclick="alert('Availability marking feature')">
                                                <i class="bi bi-save"></i> Save Availability
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Notifications Tab -->
                            <div class="tab-pane fade" id="teacher-notifications" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-info text-white">
                                        <i class="bi bi-bell"></i> Notifications & Alerts
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group">
                                            <div class="list-group-item text-center text-muted py-5">
                                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                                <p class="mt-3 mb-0">No new notifications</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $html = ob_get_clean(); echo $html; exit;
                case 'n8n':
                    // EMBEDDED: Automation Dashboard - n8n workflow management
                    // Admin oversight of automated processes and workflows
                    ob_start(); ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-lightning"></i> Automation Dashboard
                                    <span class="badge bg-dark ms-2">n8n Integration</span>
                                </h3>
                                <p class="text-muted small mb-0">Automated workflows, notifications, and scheduling</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadModule('overview')">
                                    <i class="bi bi-arrow-left"></i> Back to Overview
                                </button>
                            </div>
                        </div>

                        <!-- Automation Tabs -->
                        <ul class="nav nav-tabs mb-3" id="automationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="auto-workflows-tab" data-bs-toggle="tab" data-bs-target="#auto-workflows" type="button">
                                    <i class="bi bi-diagram-3"></i> Workflows
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="auto-notifications-tab" data-bs-toggle="tab" data-bs-target="#auto-notifications" type="button">
                                    <i class="bi bi-envelope"></i> Notifications
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="auto-scheduling-tab" data-bs-toggle="tab" data-bs-target="#auto-scheduling" type="button">
                                    <i class="bi bi-clock-history"></i> Scheduling
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="auto-documents-tab" data-bs-toggle="tab" data-bs-target="#auto-documents" type="button">
                                    <i class="bi bi-file-earmark-text"></i> Documents
                                </button>
                            </li>
                        </ul>

                        <!-- Automation Tabs Content -->
                        <div class="tab-content" id="automationTabsContent">
                            <!-- Workflows Tab -->
                            <div class="tab-pane fade show active" id="auto-workflows" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-dark text-white">
                                        <i class="bi bi-diagram-3"></i> Active Workflows
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <p class="text-muted mb-0">Manage automated n8n workflows</p>
                                            <button class="btn btn-sm btn-dark" onclick="alert('Create workflow feature')">
                                                <i class="bi bi-plus-lg"></i> New Workflow
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card border-success">
                                                    <div class="card-body">
                                                        <h6><i class="bi bi-check-circle text-success"></i> Email Reminders</h6>
                                                        <p class="text-muted small">Auto-send exam reminders</p>
                                                        <span class="badge bg-success">Active</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card border-info">
                                                    <div class="card-body">
                                                        <h6><i class="bi bi-clock text-info"></i> Schedule Sync</h6>
                                                        <p class="text-muted small">Calendar synchronization</p>
                                                        <span class="badge bg-info">Active</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card border-secondary">
                                                    <div class="card-body">
                                                        <h6><i class="bi bi-pause-circle text-secondary"></i> Report Generator</h6>
                                                        <p class="text-muted small">Weekly report automation</p>
                                                        <span class="badge bg-secondary">Paused</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notifications Tab -->
                            <div class="tab-pane fade" id="auto-notifications" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-envelope"></i> Automated Notifications
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Configure automated email and SMS notifications</p>
                                        <div class="list-group">
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">Exam Assignment Notifications</h6>
                                                    <small class="text-muted">Notify teachers of new assignments</small>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" checked />
                                                </div>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">Approval Reminders</h6>
                                                    <small class="text-muted">Remind admins of pending approvals</small>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" checked />
                                                </div>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">Exam Day Reminders</h6>
                                                    <small class="text-muted">Send reminders before exam days</small>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduling Tab -->
                            <div class="tab-pane fade" id="auto-scheduling" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <i class="bi bi-clock-history"></i> Automated Scheduling
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Configure recurring automated tasks</p>
                                        <div class="alert alert-success">
                                            <strong>Scheduled Tasks:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Daily exam reminder emails @ 8:00 AM</li>
                                                <li>Weekly workload report @ Friday 5:00 PM</li>
                                                <li>Monthly statistics compilation @ 1st of month</li>
                                            </ul>
                                        </div>
                                        <button class="btn btn-success" onclick="alert('Add scheduled task')">
                                            <i class="bi bi-plus-lg"></i> Add Scheduled Task
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Tab -->
                            <div class="tab-pane fade" id="auto-documents" role="tabpanel">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="bi bi-file-earmark-text"></i> Document Automation
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Automate document generation and processing</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6><i class="bi bi-file-pdf"></i> Assignment Letters</h6>
                                                        <p class="small text-muted">Auto-generate duty letters</p>
                                                        <button class="btn btn-sm btn-warning" onclick="alert('Generate feature')">
                                                            <i class="bi bi-gear"></i> Configure
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6><i class="bi bi-file-excel"></i> Excel Reports</h6>
                                                        <p class="small text-muted">Auto-export data to Excel</p>
                                                        <button class="btn btn-sm btn-warning" onclick="alert('Configure feature')">
                                                            <i class="bi bi-gear"></i> Configure
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $html = ob_get_clean(); echo $html; exit;
                case 'user_management':
                    // ENHANCED: Advanced user management with search, filters, bulk actions
                    $users = getRegisteredUsers($pdo);
                    $colleges = getAllColleges($pdo);
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1">User Management</h3>
                                <p class="text-muted small mb-0">Manage all registered users, roles, and permissions</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-success btn-sm" onclick="location.href='register.php'">
                                    <i class="bi bi-plus-lg"></i> Add User
                                </button>
                                <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i> Export
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?action=export_csv&type=users">CSV Format</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="alert('Excel export coming soon!'); return false;">Excel Format</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="alert('PDF export coming soon!'); return false;">PDF Format</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Search and Filter Panel -->
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Search</label>
                                        <input type="text" id="searchUsers" class="form-control form-control-sm" placeholder="Name, email, college...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold">Role</label>
                                        <select id="roleFilter" class="form-select form-select-sm">
                                            <option value="">All Roles</option>
                                            <option value="teacher">Teacher</option>
                                            <option value="hod">HOD</option>
                                            <option value="vice_principal">Vice Principal</option>
                                            <option value="principal">Principal</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">College</label>
                                        <select id="collegeFilter" class="form-select form-select-sm">
                                            <option value="">All Colleges</option>
                                            <?php foreach ($colleges as $college): ?>
                                            <option value="<?= esc($college) ?>"><?= esc($college) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold">Status</label>
                                        <select id="statusFilter" class="form-select form-select-sm">
                                            <option value="">All Status</option>
                                            <option value="verified">Verified</option>
                                            <option value="pending">Pending</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button class="btn btn-sm btn-primary w-100" onclick="applyUserFilters()">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bulk Actions Bar -->
                        <div class="card shadow-sm mb-3 d-none" id="bulkActionsBar">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span id="selectedCount" class="fw-semibold">0</span> user(s) selected
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-success" onclick="bulkAction('verified')">
                                            <i class="bi bi-check-circle"></i> Verify
                                        </button>
                                        <button class="btn btn-danger" onclick="bulkAction('rejected')">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                        <button class="btn btn-secondary" onclick="clearSelection()">
                                            <i class="bi bi-x"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="card shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="usersTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40px;">
                                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                                </th>
                                                <th>Name</th>
                                                <th>Contact</th>
                                                <th>College</th>
                                                <th>Role</th>
                                                <th>Password</th>
                                                <th>Status</th>
                                                <th>Registered</th>
                                                <th style="width: 200px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($users)): ?>
                                                <tr><td colspan="9" class="text-center text-muted py-4">No users found</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($users as $user): ?>
                                                    <tr data-user-id="<?= (int)$user['id'] ?>">
                                                        <td>
                                                            <input type="checkbox" class="form-check-input user-checkbox" value="<?= (int)$user['id'] ?>">
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-sm me-2 bg-primary text-white rounded-circle d-flex align-items-center justify-center" style="width: 32px; height: 32px; font-size: 12px; font-weight: bold;">
                                                                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                                                </div>
                                                                <div>
                                                                    <strong><?= esc($user['name']) ?></strong>
                                                                    <br><small class="text-muted">#<?= (int)$user['id'] ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?= esc($user['email']) ?>
                                                            <br><small class="text-muted"><?= esc($user['phone']) ?></small>
                                                        </td>
                                                        <td><?= esc($user['college_name']) ?></td>
                                                        <td>
                                                            <select class="form-select form-select-sm role-selector" data-user-id="<?= (int)$user['id'] ?>" data-current-role="<?= esc($user['post']) ?>">
                                                                <option value="teacher" <?= $user['post'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                                                <option value="hod" <?= $user['post'] === 'hod' ? 'selected' : '' ?>>HOD</option>
                                                                <option value="vice_principal" <?= $user['post'] === 'vice_principal' ? 'selected' : '' ?>>Vice Principal</option>
                                                                <option value="principal" <?= $user['post'] === 'principal' ? 'selected' : '' ?>>Principal</option>
                                                                <option value="admin" <?= $user['post'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($user['has_password'])): ?>
                                                                <span class="badge bg-success" title="Password set">
                                                                    <i class="bi bi-key-fill"></i> Set
                                                                </span>
                                                                <button class="btn btn-sm btn-outline-warning ms-1" 
                                                                        onclick="resetPassword(<?= (int)$user['id'] ?>, '<?= esc($user['name']) ?>')" 
                                                                        title="Reset password to Welcome@123">
                                                                    <i class="bi bi-arrow-clockwise"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger" title="No password">
                                                                    <i class="bi bi-exclamation-triangle-fill"></i> Not Set
                                                                </span>
                                                                <button class="btn btn-sm btn-success ms-1" 
                                                                        onclick="setPassword(<?= (int)$user['id'] ?>, '<?= esc($user['name']) ?>')" 
                                                                        title="Set default password">
                                                                    <i class="bi bi-plus-lg"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge 
                                                                <?php if ($user['status'] === 'verified') echo 'bg-success';
                                                                      elseif ($user['status'] === 'pending') echo 'bg-warning text-dark';
                                                                      else echo 'bg-danger'; ?>">
                                                                <?= esc(ucfirst($user['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?= date('M d, Y', strtotime($user['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <?php if ($user['status'] === 'pending'): ?>
                                                                    <button class="btn btn-success user-action-btn" data-action="verify" data-id="<?= (int)$user['id'] ?>" title="Approve">
                                                                        <i class="bi bi-check-lg"></i>
                                                                    </button>
                                                                    <button class="btn btn-danger user-action-btn" data-action="reject" data-id="<?= (int)$user['id'] ?>" title="Reject">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-outline-secondary" title="Edit" onclick="alert('User editing coming soon!')">
                                                                        <i class="bi bi-pencil"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <script>
                        // User Management JavaScript
                        $(document).ready(function() {
                            // Select All checkbox
                            $('#selectAll').on('change', function() {
                                $('.user-checkbox').prop('checked', $(this).is(':checked'));
                                updateBulkActionsBar();
                            });

                            // Individual checkbox
                            $('.user-checkbox').on('change', function() {
                                updateBulkActionsBar();
                            });

                            // Role selector change
                            $('.role-selector').on('change', function() {
                                const userId = $(this).data('user-id');
                                const newRole = $(this).val();
                                const currentRole = $(this).data('current-role');
                                
                                if (newRole !== currentRole) {
                                    if (confirm(`Change user role to ${newRole}?`)) {
                                        $.post('?action=update_user_role', {
                                            user_id: userId,
                                            new_role: newRole,
                                            csrf_token: CSRF_TOKEN
                                        }, function(res) {
                                            if (res.success) {
                                                showAlert('success', res.message);
                                                $(`.role-selector[data-user-id="${userId}"]`).data('current-role', newRole);
                                            } else {
                                                showAlert('danger', res.message);
                                                // Revert select
                                                $(`.role-selector[data-user-id="${userId}"]`).val(currentRole);
                                            }
                                        }, 'json');
                                    } else {
                                        $(this).val(currentRole); // Revert
                                    }
                                }
                            });
                        });

                        function updateBulkActionsBar() {
                            const selected = $('.user-checkbox:checked').length;
                            $('#selectedCount').text(selected);
                            if (selected > 0) {
                                $('#bulkActionsBar').removeClass('d-none');
                            } else {
                                $('#bulkActionsBar').addClass('d-none');
                            }
                        }

                        function clearSelection() {
                            $('.user-checkbox').prop('checked', false);
                            $('#selectAll').prop('checked', false);
                            updateBulkActionsBar();
                        }

                        function bulkAction(status) {
                            const userIds = [];
                            $('.user-checkbox:checked').each(function() {
                                userIds.push($(this).val());
                            });

                            if (userIds.length === 0) {
                                alert('No users selected');
                                return;
                            }

                            if (!confirm(`${status === 'verified' ? 'Verify' : 'Reject'} ${userIds.length} user(s)?`)) {
                                return;
                            }

                            $.post('?action=bulk_update_status', {
                                user_ids: JSON.stringify(userIds),
                                status: status,
                                csrf_token: CSRF_TOKEN
                            }, function(res) {
                                if (res.success) {
                                    showAlert('success', res.message);
                                    setTimeout(function() { loadModule('user_management'); }, 1000);
                                } else {
                                    showAlert('danger', res.message);
                                }
                            }, 'json');
                        }

                        // Password Management Functions
                        function resetPassword(userId, userName) {
                            if (!confirm(`Reset password for ${userName}?\n\nNew password will be: Welcome@123`)) {
                                return;
                            }

                            $.post('?action=reset_user_password', {
                                user_id: userId,
                                csrf_token: CSRF_TOKEN
                            }, function(res) {
                                if (res.success) {
                                    showAlert('success', `Password reset successful! New password: Welcome@123`);
                                    setTimeout(function() { loadModule('user_management'); }, 2000);
                                } else {
                                    showAlert('danger', res.message || 'Failed to reset password');
                                }
                            }, 'json').fail(function() {
                                showAlert('danger', 'Network error. Please try again.');
                            });
                        }

                        function setPassword(userId, userName) {
                            if (!confirm(`Set password for ${userName}?\n\nPassword will be: Welcome@123`)) {
                                return;
                            }

                            $.post('?action=set_user_password', {
                                user_id: userId,
                                csrf_token: CSRF_TOKEN
                            }, function(res) {
                                if (res.success) {
                                    showAlert('success', `Password set successfully! Password: Welcome@123`);
                                    setTimeout(function() { loadModule('user_management'); }, 2000);
                                } else {
                                    showAlert('danger', res.message || 'Failed to set password');
                                }
                            }, 'json').fail(function() {
                                showAlert('danger', 'Network error. Please try again.');
                            });
                        }

                        function applyUserFilters() {
                            const filters = {
                                search: $('#searchUsers').val(),
                                role: $('#roleFilter').val(),
                                college: $('#collegeFilter').val(),
                                status: $('#statusFilter').val()
                            };

                            // Show loading
                            $('#usersTable tbody').html('<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Searching...</td></tr>');

                            $.get('?action=search_users', filters, function(data) {
                                if (data.success) {
                                    renderUsers(data.users);
                                } else {
                                    showAlert('danger', 'Search failed');
                                }
                            }, 'json');
                        }

                        function renderUsers(users) {
                            if (users.length === 0) {
                                $('#usersTable tbody').html('<tr><td colspan="9" class="text-center text-muted py-4">No users found</td></tr>');
                                return;
                            }

                            let html = '';
                            users.forEach(function(user) {
                                const statusClass = user.status === 'verified' ? 'bg-success' : 
                                                   user.status === 'pending' ? 'bg-warning text-dark' : 'bg-danger';
                                const hasPassword = user.has_password === 1 || user.has_password === '1';
                                const passwordHtml = hasPassword ? 
                                    `<span class="badge bg-success" title="Password set"><i class="bi bi-key-fill"></i> Set</span>
                                     <button class="btn btn-sm btn-outline-warning ms-1" onclick="resetPassword(${user.id}, '${escapeHtml(user.name)}')" title="Reset password">
                                        <i class="bi bi-arrow-clockwise"></i>
                                     </button>` :
                                    `<span class="badge bg-danger" title="No password"><i class="bi bi-exclamation-triangle-fill"></i> Not Set</span>
                                     <button class="btn btn-sm btn-success ms-1" onclick="setPassword(${user.id}, '${escapeHtml(user.name)}')" title="Set password">
                                        <i class="bi bi-plus-lg"></i>
                                     </button>`;
                                     
                                html += `<tr data-user-id="${user.id}">
                                    <td><input type="checkbox" class="form-check-input user-checkbox" value="${user.id}"></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 12px; font-weight: bold;">
                                                ${escapeHtml(user.name.substring(0, 2).toUpperCase())}
                                            </div>
                                            <div>
                                                <strong>${escapeHtml(user.name)}</strong>
                                                <br><small class="text-muted">#${user.id}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>${escapeHtml(user.email)}<br><small class="text-muted">${escapeHtml(user.phone)}</small></td>
                                    <td>${escapeHtml(user.college_name)}</td>
                                    <td><span class="badge bg-secondary">${escapeHtml(user.post)}</span></td>
                                    <td>${passwordHtml}</td>
                                    <td><span class="badge ${statusClass}">${user.status}</span></td>
                                    <td><small>${new Date(user.created_at).toLocaleDateString()}</small></td>
                                    <td>
                                        ${user.status === 'pending' ? 
                                            `<button class="btn btn-sm btn-success user-action-btn" data-action="verify" data-id="${user.id}"><i class="bi bi-check-lg"></i></button>
                                             <button class="btn btn-sm btn-danger user-action-btn" data-action="reject" data-id="${user.id}"><i class="bi bi-x-lg"></i></button>` :
                                            `<button class="btn btn-sm btn-outline-secondary" onclick="alert('User editing coming soon!')"><i class="bi bi-pencil"></i></button>`
                                        }
                                    </td>
                                </tr>`;
                            });
                            $('#usersTable tbody').html(html);
                            
                            // Re-bind checkbox events
                            $('.user-checkbox').on('change', updateBulkActionsBar);
                            
                            // Re-bind action buttons
                            $('.user-action-btn').on('click', function(){ 
                                const action = $(this).data('action');
                                const userId = $(this).data('id');
                                const status = action === 'verify' ? 'verified' : 'rejected';
                                handleUserStatusUpdate(userId, status);
                            });
                        }

                        function showAlert(type, message) {
                            const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                                ${escapeHtml(message)}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>`;
                            $('#mainContent').prepend(alert);
                            setTimeout(function() { $('.alert').fadeOut(); }, 3000);
                        }
                        </script>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;
                case 'approvals_verifications':
                    $pendingUsers = fetchPendingUsers($pdo);
                    $pendingApprovals = fetchPendingApprovals($pdo);
                    ob_start();
                    ?>
                    <div class="p-3">
                        <h3>Approvals & Verifications</h3>
                        <p class="text-muted">Review new user registrations and pending approval requests.</p>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>Pending User Verifications</span>
                                        <a class="btn btn-sm btn-outline-primary" href="verify_users.php">Open Full Verifier</a>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($pendingUsers)): ?>
                                            <p class="text-muted">No pending user verifications.</p>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Post</th>
                                                        <th>College</th>
                                                        <th>Contact</th>
                                                        <th>Since</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($pendingUsers as $u): ?>
                                                    <tr>
                                                        <td><strong><?= esc($u['name']) ?></strong><br><small class="text-muted">#<?= (int)$u['id'] ?></small></td>
                                                        <td><?= esc(ucwords(str_replace('_',' ',$u['post']))) ?></td>
                                                        <td><?= esc($u['college_name']) ?></td>
                                                        <td><?= esc($u['email']) ?><br><small class="text-muted"><?= esc($u['phone']) ?></small></td>
                                                        <td><?= esc(date('Y-m-d', strtotime($u['created_at']))) ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-success user-action-btn" data-action="verify" data-id="<?= (int)$u['id'] ?>">Approve</button>
                                                            <button class="btn btn-sm btn-danger user-action-btn" data-action="reject" data-id="<?= (int)$u['id'] ?>">Reject</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card mb-3">
                                    <div class="card-header">Pending Approvals</div>
                                    <div class="card-body">
                                        <?php if (empty($pendingApprovals)): ?>
                                            <p class="text-muted">No pending approvals.</p>
                                        <?php else: ?>
                                            <ul class="list-group">
                                                <?php foreach ($pendingApprovals as $p): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?= esc($p['type']) ?></strong>
                                                            <div class="text-muted small">Requested by #<?= (int)$p['requester_id'] ?> on <?= esc($p['created_at']) ?></div>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-sm btn-success approve-btn" data-id="<?= (int)$p['id'] ?>">Approve</button>
                                                            <button class="btn btn-sm btn-danger reject-btn" data-id="<?= (int)$p['id'] ?>">Reject</button>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;

                case 'exam_management':
                    // COLLEGE EXAM MANAGEMENT MODULE
                    // Fetch and manage all exam requirements posted by colleges
                    
                    // Step 1: Get filter parameters from request
                    $filterCollege = $_GET['filter_college'] ?? '';
                    $filterSubject = $_GET['filter_subject'] ?? '';
                    $filterStatus = $_GET['filter_status'] ?? '';
                    $filterDate = $_GET['filter_date'] ?? '';
                    $searchQuery = $_GET['search'] ?? '';
                    $page = max(1, (int)($_GET['page'] ?? 1));
                    $perPage = 15;
                    $offset = ($page - 1) * $perPage;

                    // Step 2: Build SQL query with filters
                    $sql = "SELECT 
                                e.id AS exam_id,
                                e.title AS exam_name,
                                COALESCE(e.subject, 'N/A') AS subject,
                                e.exam_date,
                                COALESCE(e.status, 'Pending') AS status,
                                COALESCE(e.description, '') AS description,
                                COALESCE(e.department, 'Unknown') AS college_name,
                                u.name AS created_by_name,
                                e.created_at
                            FROM exams e
                            LEFT JOIN users u ON e.created_by = u.id
                            WHERE 1=1";
                    
                    $params = [];
                    
                    // Apply college filter
                    if (!empty($filterCollege)) {
                        $sql .= " AND e.department = :college";
                        $params[':college'] = $filterCollege;
                    }
                    
                    // Apply subject filter
                    if (!empty($filterSubject)) {
                        $sql .= " AND e.subject = :subject";
                        $params[':subject'] = $filterSubject;
                    }
                    
                    // Apply status filter
                    if (!empty($filterStatus)) {
                        $sql .= " AND e.status = :status";
                        $params[':status'] = $filterStatus;
                    }
                    
                    // Apply date filter
                    if (!empty($filterDate)) {
                        $sql .= " AND DATE(e.exam_date) = :exam_date";
                        $params[':exam_date'] = $filterDate;
                    }
                    
                    // Apply search query
                    if (!empty($searchQuery)) {
                        $sql .= " AND (e.title LIKE :search OR e.subject LIKE :search OR e.department LIKE :search OR e.description LIKE :search)";
                        $params[':search'] = '%' . $searchQuery . '%';
                    }
                    
                    // Count total results for pagination
                    $countSql = "SELECT COUNT(*) " . substr($sql, strpos($sql, 'FROM'));
                    $countStmt = $pdo->prepare($countSql);
                    $countStmt->execute($params);
                    $totalExams = $countStmt->fetchColumn();
                    $totalPages = ceil($totalExams / $perPage);
                    
                    // Add ordering and pagination
                    $sql .= " ORDER BY e.exam_date DESC, e.created_at DESC LIMIT :limit OFFSET :offset";
                    $params[':limit'] = $perPage;
                    $params[':offset'] = $offset;
                    
                    // Step 3: Execute query
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Step 4: Get unique values for filter dropdowns
                    $colleges = $pdo->query("SELECT DISTINCT department FROM exams WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
                    $subjects = $pdo->query("SELECT DISTINCT subject FROM exams WHERE subject IS NOT NULL ORDER BY subject")->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Step 5: Get pending exams count for notification badge
                    $pendingCount = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'Pending'")->fetchColumn();
                    
                    ob_start();
                    ?>
                    <div class="p-3">
                        <!-- Header Section -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-calendar-check"></i> College Exam Management
                                    <?php if ($pendingCount > 0): ?>
                                        <span class="badge bg-warning text-dark ms-2"><?= $pendingCount ?> Pending</span>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-muted small mb-0">Manage exam requirements posted by colleges</p>
                            </div>
                            <div class="btn-group">
                                <?php 
                                // Show Add Exam button for roles that can create exams
                                $canCreateExams = in_array($currentUserRole, ['admin', 'principal', 'vice-principal', 'hod']);
                                if ($canCreateExams): 
                                ?>
                                <button class="btn btn-primary btn-sm" onclick="showAddExamModal()">
                                    <i class="bi bi-plus-lg"></i> Add Exam
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-success btn-sm" onclick="exportExams()">
                                    <i class="bi bi-download"></i> Export
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="loadModule('exam_management')">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Filters and Search Panel -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <i class="bi bi-funnel"></i> Filters & Search
                            </div>
                            <div class="card-body">
                                <form method="GET" id="examFiltersForm">
                                    <input type="hidden" name="action" value="load_module">
                                    <input type="hidden" name="module" value="exam_management">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Search</label>
                                            <input type="text" name="search" class="form-control form-control-sm" 
                                                   placeholder="Exam name, subject..." value="<?= esc($searchQuery) ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">College</label>
                                            <select name="filter_college" class="form-select form-select-sm">
                                                <option value="">All Colleges</option>
                                                <?php foreach ($colleges as $college): ?>
                                                    <option value="<?= esc($college) ?>" <?= $filterCollege === $college ? 'selected' : '' ?>>
                                                        <?= esc($college) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Subject</label>
                                            <select name="filter_subject" class="form-select form-select-sm">
                                                <option value="">All Subjects</option>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <option value="<?= esc($subject) ?>" <?= $filterSubject === $subject ? 'selected' : '' ?>>
                                                        <?= esc($subject) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Status</label>
                                            <select name="filter_status" class="form-select form-select-sm">
                                                <option value="">All Status</option>
                                                <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="Assigned" <?= $filterStatus === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                                                <option value="Cancelled" <?= $filterStatus === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Exam Date</label>
                                            <input type="date" name="filter_date" class="form-control form-control-sm" 
                                                   value="<?= esc($filterDate) ?>">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <?php if (!empty($searchQuery) || !empty($filterCollege) || !empty($filterSubject) || !empty($filterStatus) || !empty($filterDate)): ?>
                                    <div class="mt-2">
                                        <a href="?action=load_module&module=exam_management" class="btn btn-sm btn-link text-decoration-none">
                                            <i class="bi bi-x-circle"></i> Clear Filters
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Total Exams</div>
                                                <div class="h3 mb-0"><?= number_format($totalExams) ?></div>
                                            </div>
                                            <i class="bi bi-file-earmark-text" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Pending</div>
                                                <div class="h3 mb-0"><?= number_format($pendingCount) ?></div>
                                            </div>
                                            <i class="bi bi-clock-history" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Approved</div>
                                                <div class="h3 mb-0">
                                                    <?php 
                                                    $approved = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'Approved'")->fetchColumn();
                                                    echo number_format($approved);
                                                    ?>
                                                </div>
                                            </div>
                                            <i class="bi bi-check-circle" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Assigned</div>
                                                <div class="h3 mb-0">
                                                    <?php 
                                                    $assigned = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'Assigned'")->fetchColumn();
                                                    echo number_format($assigned);
                                                    ?>
                                                </div>
                                            </div>
                                            <i class="bi bi-people" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Exams Table -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-table"></i> Exam Listings</span>
                                <span class="badge bg-white text-primary">Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;">#</th>
                                                <th>Exam Name</th>
                                                <th>College</th>
                                                <th>Subject</th>
                                                <th>Exam Date</th>
                                                <th>Status</th>
                                                <th>Created By</th>
                                                <th style="width: 200px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($exams)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">
                                                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                                        <p class="mt-2 mb-0">No exams found. Try adjusting your filters.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($exams as $index => $exam): ?>
                                                    <tr id="exam-row-<?= $exam['exam_id'] ?>">
                                                        <td><?= $offset + $index + 1 ?></td>
                                                        <td>
                                                            <strong><?= esc($exam['exam_name']) ?></strong>
                                                            <?php if (!empty($exam['description'])): ?>
                                                                <br><small class="text-muted"><?= esc(substr($exam['description'], 0, 50)) ?><?= strlen($exam['description']) > 50 ? '...' : '' ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= esc($exam['college_name']) ?></td>
                                                        <td><span class="badge bg-info"><?= esc($exam['subject']) ?></span></td>
                                                        <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = [
                                                                'Pending' => 'warning',
                                                                'Approved' => 'success',
                                                                'Assigned' => 'primary',
                                                                'Cancelled' => 'danger'
                                                            ];
                                                            $class = $statusClass[$exam['status']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?= $class ?>"><?= esc($exam['status']) ?></span>
                                                        </td>
                                                        <td><?= esc($exam['created_by_name'] ?? 'System') ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <!-- View Details Button -->
                                                                <button class="btn btn-outline-info" onclick="viewExamDetails(<?= $exam['exam_id'] ?>)" 
                                                                        title="View Details">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                
                                                                <?php if ($exam['status'] === 'Pending'): ?>
                                                                    <!-- Approve Button -->
                                                                    <button class="btn btn-outline-success" onclick="updateExamStatus(<?= $exam['exam_id'] ?>, 'Approved')" 
                                                                            title="Approve">
                                                                        <i class="bi bi-check-circle"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($exam['status'] === 'Approved'): ?>
                                                                    <!-- Assign Faculty Button -->
                                                                    <button class="btn btn-outline-primary" onclick="assignFaculty(<?= $exam['exam_id'] ?>)" 
                                                                            title="Assign Faculty">
                                                                        <i class="bi bi-person-plus"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Edit Button -->
                                                                <button class="btn btn-outline-warning" onclick="editExam(<?= $exam['exam_id'] ?>)" 
                                                                        title="Edit">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                
                                                                <!-- Delete Button -->
                                                                <button class="btn btn-outline-danger" onclick="deleteExam(<?= $exam['exam_id'] ?>, '<?= esc($exam['exam_name']) ?>')" 
                                                                        title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="card-footer">
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                                            <!-- Previous Button -->
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?action=load_module&module=exam_management&page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&filter_college=<?= urlencode($filterCollege) ?>&filter_subject=<?= urlencode($filterSubject) ?>&filter_status=<?= urlencode($filterStatus) ?>&filter_date=<?= urlencode($filterDate) ?>">
                                                    Previous
                                                </a>
                                            </li>
                                            
                                            <!-- Page Numbers -->
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?action=load_module&module=exam_management&page=<?= $i ?>&search=<?= urlencode($searchQuery) ?>&filter_college=<?= urlencode($filterCollege) ?>&filter_subject=<?= urlencode($filterSubject) ?>&filter_status=<?= urlencode($filterStatus) ?>&filter_date=<?= urlencode($filterDate) ?>">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <!-- Next Button -->
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?action=load_module&module=exam_management&page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&filter_college=<?= urlencode($filterCollege) ?>&filter_subject=<?= urlencode($filterSubject) ?>&filter_status=<?= urlencode($filterStatus) ?>&filter_date=<?= urlencode($filterDate) ?>">
                                                    Next
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- JavaScript for Exam Management Actions -->
                    <script>
                    // View exam details in modal
                    function viewExamDetails(examId) {
                        // Fetch exam details via AJAX
                        $.get('?action=get_exam_details&exam_id=' + examId, function(response) {
                            if (response.success) {
                                const exam = response.exam;
                                const modalHtml = `
                                    <div class="modal fade" id="examDetailsModal" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">Exam Details</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="small text-muted">Exam Name</label>
                                                            <p class="fw-bold">${exam.exam_name}</p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="small text-muted">College</label>
                                                            <p class="fw-bold">${exam.college_name}</p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="small text-muted">Subject</label>
                                                            <p><span class="badge bg-info">${exam.subject}</span></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="small text-muted">Exam Date</label>
                                                            <p class="fw-bold">${exam.exam_date}</p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="small text-muted">Status</label>
                                                            <p><span class="badge bg-${exam.status === 'Pending' ? 'warning' : exam.status === 'Approved' ? 'success' : 'primary'}">${exam.status}</span></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="small text-muted">Created By</label>
                                                            <p class="fw-bold">${exam.created_by_name || 'System'}</p>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="small text-muted">Description</label>
                                                            <p>${exam.description || 'No description provided'}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                $('body').append(modalHtml);
                                $('#examDetailsModal').modal('show');
                                $('#examDetailsModal').on('hidden.bs.modal', function() {
                                    $(this).remove();
                                });
                            }
                        }, 'json');
                    }

                    // Update exam status (Approve)
                    function updateExamStatus(examId, newStatus) {
                        if (!confirm('Are you sure you want to update the status to ' + newStatus + '?')) return;
                        
                        $.post('?action=update_exam_status', {
                            exam_id: examId,
                            status: newStatus,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
                        }, function(response) {
                            if (response.success) {
                                showAlert('success', response.message);
                                loadModule('exam_management');
                            } else {
                                showAlert('danger', response.message);
                            }
                        }, 'json');
                    }

                    // Assign faculty to exam
                    function assignFaculty(examId) {
                        // Open modal with faculty selection
                        $.get('?action=get_available_faculty&exam_id=' + examId, function(response) {
                            if (response.success) {
                                const faculty = response.faculty;
                                let facultyOptions = '';
                                faculty.forEach(f => {
                                    facultyOptions += `<option value="${f.id}">${f.name} (${f.college_name})</option>`;
                                });
                                
                                const modalHtml = `
                                    <div class="modal fade" id="assignFacultyModal" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">Assign Faculty</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form id="assignFacultyForm">
                                                        <div class="mb-3">
                                                            <label class="form-label">Select Faculty</label>
                                                            <select class="form-select" name="faculty_id" required>
                                                                <option value="">Choose faculty...</option>
                                                                ${facultyOptions}
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Role</label>
                                                            <input type="text" class="form-control" name="role" value="Invigilator" required>
                                                        </div>
                                                        <input type="hidden" name="exam_id" value="${examId}">
                                                    </form>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="button" class="btn btn-primary" onclick="submitFacultyAssignment()">Assign</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                $('body').append(modalHtml);
                                $('#assignFacultyModal').modal('show');
                                $('#assignFacultyModal').on('hidden.bs.modal', function() {
                                    $(this).remove();
                                });
                            }
                        }, 'json');
                    }

                    function submitFacultyAssignment() {
                        const formData = $('#assignFacultyForm').serialize();
                        $.post('?action=assign_faculty_to_exam&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>', formData, function(response) {
                            if (response.success) {
                                showAlert('success', response.message);
                                $('#assignFacultyModal').modal('hide');
                                loadModule('exam_management');
                            } else {
                                showAlert('danger', response.message);
                            }
                        }, 'json');
                    }

                    // Edit exam
                    function editExam(examId) {
                        // Fetch exam data and show edit modal
                        $.get('?action=get_exam_details&exam_id=' + examId, function(response) {
                            if (response.success) {
                                const exam = response.exam;
                                const modalHtml = `
                                    <div class="modal fade" id="editExamModal" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title">Edit Exam</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form id="editExamForm">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Exam Name</label>
                                                                <input type="text" class="form-control" name="exam_name" value="${exam.exam_name}" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Subject</label>
                                                                <input type="text" class="form-control" name="subject" value="${exam.subject}" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Exam Date</label>
                                                                <input type="date" class="form-control" name="exam_date" value="${exam.exam_date_raw}" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="Pending" ${exam.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                                    <option value="Approved" ${exam.status === 'Approved' ? 'selected' : ''}>Approved</option>
                                                                    <option value="Assigned" ${exam.status === 'Assigned' ? 'selected' : ''}>Assigned</option>
                                                                    <option value="Cancelled" ${exam.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="3">${exam.description || ''}</textarea>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="exam_id" value="${examId}">
                                                    </form>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="button" class="btn btn-warning" onclick="submitExamEdit()">Save Changes</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                $('body').append(modalHtml);
                                $('#editExamModal').modal('show');
                                $('#editExamModal').on('hidden.bs.modal', function() {
                                    $(this).remove();
                                });
                            }
                        }, 'json');
                    }

                    function submitExamEdit() {
                        const formData = $('#editExamForm').serialize();
                        $.post('?action=update_exam&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>', formData, function(response) {
                            if (response.success) {
                                showAlert('success', response.message);
                                $('#editExamModal').modal('hide');
                                loadModule('exam_management');
                            } else {
                                showAlert('danger', response.message);
                            }
                        }, 'json');
                    }

                    // Delete exam
                    function deleteExam(examId, examName) {
                        if (!confirm('Are you sure you want to delete "' + examName + '"?\n\nThis action cannot be undone.')) return;
                        
                        $.post('?action=delete_exam', {
                            exam_id: examId,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
                        }, function(response) {
                            if (response.success) {
                                showAlert('success', response.message);
                                $('#exam-row-' + examId).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            } else {
                                showAlert('danger', response.message);
                            }
                        }, 'json');
                    }

                    // Export exams
                    function exportExams() {
                        window.location.href = '?action=export_exams&format=csv';
                    }

                    // Show add exam modal
                    function showAddExamModal() {
                        // Get user info from PHP session
                        const userRole = '<?= $currentUserRole ?>';
                        const userCollege = '<?= esc($_SESSION['college_name'] ?? '') ?>';
                        const userName = '<?= esc($_SESSION['name'] ?? 'User') ?>';
                        
                        // Auto-fill college for non-admin users
                        const collegeReadonly = (userRole !== 'admin') ? 'readonly' : '';
                        const collegeValue = (userRole !== 'admin') ? userCollege : '';
                        
                        const modalHtml = `
                            <div class="modal fade" id="addExamModal" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">
                                                <i class="bi bi-plus-circle me-2"></i>Add New Exam
                                                ${userRole !== 'admin' ? '<small class="ms-2">(' + userCollege + ')</small>' : ''}
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            ${userRole !== 'admin' ? '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>You are creating an exam for <strong>' + userCollege + '</strong>. It will be pending admin approval.</div>' : ''}
                                            <form id="addExamForm">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Exam Name *</label>
                                                        <input type="text" class="form-control" name="exam_name" required placeholder="e.g., Final Semester Mathematics">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Subject *</label>
                                                        <input type="text" class="form-control" name="subject" required placeholder="e.g., Mathematics">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">College/Department *</label>
                                                        <input type="text" class="form-control" name="college" value="${collegeValue}" ${collegeReadonly} required placeholder="Enter college name">
                                                        ${collegeReadonly ? '<small class="text-muted">Auto-filled from your profile</small>' : ''}
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Exam Date *</label>
                                                        <input type="date" class="form-control" name="exam_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                                        <small class="text-muted">Must be a future date</small>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Description</label>
                                                        <textarea class="form-control" name="description" rows="3" placeholder="Exam details, requirements, special instructions..."></textarea>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="bi bi-x-circle me-1"></i>Cancel
                                            </button>
                                            <button type="button" class="btn btn-primary" onclick="submitAddExam()">
                                                <i class="bi bi-check-circle me-1"></i>Create Exam
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('body').append(modalHtml);
                        $('#addExamModal').modal('show');
                        $('#addExamModal').on('hidden.bs.modal', function() {
                            $(this).remove();
                        });
                    }

                    function submitAddExam() {
                        const formData = $('#addExamForm').serialize();
                        $.post('?action=add_exam&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>', formData, function(response) {
                            if (response.success) {
                                showAlert('success', response.message);
                                $('#addExamModal').modal('hide');
                                loadModule('exam_management');
                            } else {
                                showAlert('danger', response.message);
                            }
                        }, 'json');
                    }
                    </script>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;

                case 'teacher_exams':
                case 'available_exams':
                    // TEACHER EXAM SELECTION MODULE
                    // Shows only exams that teachers can self-assign to
                    // Filters: Approved status, not from their college, not already assigned
                    
                    // Get current user's college to prevent conflict of interest
                    $teacherStmt = $pdo->prepare("SELECT college_name, name FROM users WHERE id = ?");
                    $teacherStmt->execute([$adminId]);
                    $teacherInfo = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                    $teacherCollege = $teacherInfo['college_name'] ?? '';
                    $teacherName = $teacherInfo['name'] ?? 'Teacher';
                    
                    // Get filter parameters
                    $filterSubject = $_GET['filter_subject'] ?? '';
                    $filterDate = $_GET['filter_date'] ?? '';
                    $searchQuery = $_GET['search'] ?? '';
                    
                    // Build query for eligible exams
                    // Criteria: Status='Approved', not from teacher's college, teacher not already assigned
                    $sql = "SELECT 
                                e.id AS exam_id,
                                e.title AS exam_name,
                                e.subject,
                                e.exam_date,
                                e.status,
                                e.description,
                                e.department AS college_name,
                                u.name AS created_by_name,
                                e.created_at,
                                (SELECT COUNT(*) FROM assignments WHERE exam_id = e.id) AS total_assigned
                            FROM exams e
                            LEFT JOIN users u ON e.created_by = u.id
                            WHERE e.status = 'Approved'
                            AND e.department != :teacher_college
                            AND e.id NOT IN (
                                SELECT exam_id FROM assignments WHERE faculty_id = :teacher_id
                            )";
                    
                    $params = [
                        ':teacher_college' => $teacherCollege,
                        ':teacher_id' => $adminId
                    ];
                    
                    // Apply filters
                    if (!empty($filterSubject)) {
                        $sql .= " AND e.subject = :subject";
                        $params[':subject'] = $filterSubject;
                    }
                    
                    if (!empty($filterDate)) {
                        $sql .= " AND DATE(e.exam_date) = :exam_date";
                        $params[':exam_date'] = $filterDate;
                    }
                    
                    if (!empty($searchQuery)) {
                        $sql .= " AND (e.title LIKE :search OR e.subject LIKE :search OR e.department LIKE :search)";
                        $params[':search'] = '%' . $searchQuery . '%';
                    }
                    
                    $sql .= " AND e.exam_date >= CURDATE()"; // Only future exams
                    $sql .= " ORDER BY e.exam_date ASC, e.created_at DESC";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $availableExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get teacher's already assigned exams
                    $assignedStmt = $pdo->prepare("
                        SELECT 
                            e.id AS exam_id,
                            e.title AS exam_name,
                            e.subject,
                            e.exam_date,
                            e.status,
                            e.department AS college_name,
                            a.assigned_at,
                            a.role as assignment_role
                        FROM assignments a
                        JOIN exams e ON a.exam_id = e.id
                        WHERE a.faculty_id = ?
                        ORDER BY e.exam_date ASC
                    ");
                    $assignedStmt->execute([$adminId]);
                    $assignedExams = $assignedStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get unique subjects for filter
                    $subjects = $pdo->query("SELECT DISTINCT subject FROM exams WHERE subject IS NOT NULL AND status = 'Approved' ORDER BY subject")->fetchAll(PDO::FETCH_COLUMN);
                    
                    ob_start();
                    ?>
                    <div class="p-3">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1">
                                    <i class="bi bi-calendar-check"></i> Available Exams
                                </h3>
                                <p class="text-muted small mb-0">Select exams you'd like to be assigned to as an examiner</p>
                            </div>
                            <div>
                                <button class="btn btn-outline-primary btn-sm" onclick="loadModule('available_exams')">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Info Alert -->
                        <div class="alert alert-info d-flex align-items-start" role="alert">
                            <i class="bi bi-info-circle me-2 mt-1"></i>
                            <div>
                                <strong>How it works:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>You can select exams from <strong>other colleges</strong> (not <?= esc($teacherCollege) ?>)</li>
                                    <li>Only <strong>approved exams</strong> are shown</li>
                                    <li>Once selected, you'll receive assignment details</li>
                                    <li>You cannot select exams you're already assigned to</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <i class="bi bi-funnel"></i> Filters
                            </div>
                            <div class="card-body">
                                <form method="GET" id="examFiltersForm">
                                    <input type="hidden" name="action" value="load_module">
                                    <input type="hidden" name="module" value="available_exams">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small">Search</label>
                                            <input type="text" name="search" class="form-control form-control-sm" 
                                                   placeholder="Exam name, subject..." value="<?= esc($searchQuery) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Subject</label>
                                            <select name="filter_subject" class="form-select form-select-sm">
                                                <option value="">All Subjects</option>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <option value="<?= esc($subject) ?>" <?= $filterSubject === $subject ? 'selected' : '' ?>>
                                                        <?= esc($subject) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Exam Date</label>
                                            <input type="date" name="filter_date" class="form-control form-control-sm" 
                                                   value="<?= esc($filterDate) ?>">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                <i class="bi bi-search"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Available Exams</div>
                                                <div class="h3 mb-0"><?= count($availableExams) ?></div>
                                            </div>
                                            <i class="bi bi-clipboard-check" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Your Assignments</div>
                                                <div class="h3 mb-0"><?= count($assignedExams) ?></div>
                                            </div>
                                            <i class="bi bi-check2-square" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <div class="card-body text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small opacity-75">Your College</div>
                                                <div class="h6 mb-0"><?= esc($teacherCollege) ?></div>
                                            </div>
                                            <i class="bi bi-building" style="font-size: 2rem; opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs for Available vs Assigned -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#available-tab" type="button">
                                    <i class="bi bi-clipboard-check"></i> Available Exams (<?= count($availableExams) ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assigned-tab" type="button">
                                    <i class="bi bi-check2-square"></i> My Assignments (<?= count($assignedExams) ?>)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Available Exams Tab -->
                            <div class="tab-pane fade show active" id="available-tab">
                                <div class="card shadow-sm">
                                    <div class="card-body p-0">
                                        <?php if (empty($availableExams)): ?>
                                            <div class="text-center py-5">
                                                <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                                                <p class="text-muted mt-3">No available exams at the moment. Check back later!</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Exam Name</th>
                                                            <th>College</th>
                                                            <th>Subject</th>
                                                            <th>Exam Date</th>
                                                            <th>Assigned Faculty</th>
                                                            <th style="width: 150px;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($availableExams as $exam): ?>
                                                            <tr id="exam-<?= $exam['exam_id'] ?>">
                                                                <td>
                                                                    <strong><?= esc($exam['exam_name']) ?></strong>
                                                                    <?php if (!empty($exam['description'])): ?>
                                                                        <br><small class="text-muted"><?= esc(substr($exam['description'], 0, 60)) ?><?= strlen($exam['description']) > 60 ? '...' : '' ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?= esc($exam['college_name']) ?></td>
                                                                <td><span class="badge bg-info"><?= esc($exam['subject']) ?></span></td>
                                                                <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                                                <td>
                                                                    <span class="badge bg-secondary"><?= (int)$exam['total_assigned'] ?> assigned</span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-success" 
                                                                            onclick="selectExam(<?= $exam['exam_id'] ?>, '<?= esc($exam['exam_name']) ?>')"
                                                                            title="Select this exam">
                                                                        <i class="bi bi-hand-index"></i> Select
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- My Assignments Tab -->
                            <div class="tab-pane fade" id="assigned-tab">
                                <div class="card shadow-sm">
                                    <div class="card-body p-0">
                                        <?php if (empty($assignedExams)): ?>
                                            <div class="text-center py-5">
                                                <i class="bi bi-calendar-x" style="font-size: 4rem; color: #ccc;"></i>
                                                <p class="text-muted mt-3">You haven't selected any exams yet.</p>
                                                <p class="text-muted">Click on "Available Exams" tab to select exams.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Exam Name</th>
                                                            <th>College</th>
                                                            <th>Subject</th>
                                                            <th>Exam Date</th>
                                                            <th>Role</th>
                                                            <th>Assigned On</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($assignedExams as $exam): ?>
                                                            <tr>
                                                                <td><strong><?= esc($exam['exam_name']) ?></strong></td>
                                                                <td><?= esc($exam['college_name']) ?></td>
                                                                <td><span class="badge bg-info"><?= esc($exam['subject']) ?></span></td>
                                                                <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                                                <td><span class="badge bg-primary"><?= esc($exam['assignment_role']) ?></span></td>
                                                                <td><?= date('M d, Y', strtotime($exam['assigned_at'])) ?></td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = $exam['status'] === 'Assigned' ? 'success' : 'warning';
                                                                    ?>
                                                                    <span class="badge bg-<?= $statusClass ?>"><?= esc($exam['status']) ?></span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    // Teacher self-assignment function
                    function selectExam(examId, examName) {
                        if (!confirm('Are you sure you want to select "' + examName + '" for examination duty?\n\nYou will receive further details after selection.')) {
                            return;
                        }
                        
                        const formData = new FormData();
                        formData.append('exam_id', examId);
                        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
                        
                        $.ajax({
                            url: '?action=teacher_select_exam',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showAlert('success', response.message);
                                    // Remove the exam row from available exams
                                    $('#exam-' + examId).fadeOut(400, function() {
                                        $(this).remove();
                                        // Check if no more exams
                                        if ($('#available-tab tbody tr').length === 0) {
                                            loadModule('available_exams');
                                        }
                                    });
                                    // Reload to update counts
                                    setTimeout(function() {
                                        loadModule('available_exams');
                                    }, 2000);
                                } else {
                                    showAlert('danger', response.message);
                                }
                            },
                            error: function() {
                                showAlert('danger', 'Failed to select exam. Please try again.');
                            }
                        });
                    }
                    </script>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;

                case 'permissions':
                    // GRANULAR PERMISSIONS CONTROL: Manage tab/module-level access
                    $users = getUsersForPermissionManagement($pdo);
                    
                    // Ensure permissions table has new columns
                    try {
                        $pdo->exec("ALTER TABLE permissions 
                            ADD COLUMN IF NOT EXISTS module_overview TINYINT(1) DEFAULT 1,
                            ADD COLUMN IF NOT EXISTS module_user_management TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_exam_management TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_approvals TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_available_exams TINYINT(1) DEFAULT 1,
                            ADD COLUMN IF NOT EXISTS module_permissions TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_analytics TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_audit_logs TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_settings TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_principal_dash TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_vice_dash TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_hod_dash TINYINT(1) DEFAULT 0,
                            ADD COLUMN IF NOT EXISTS module_teacher_dash TINYINT(1) DEFAULT 1");
                    } catch (Exception $e) {
                        // Columns may already exist
                    }
                    
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1"><i class="bi bi-shield-lock"></i> Granular Permissions Control</h3>
                                <p class="text-muted small mb-0">Control individual tab and module access for each user</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success" onclick="saveAllPermissions()">
                                    <i class="bi bi-save"></i> Save All Changes
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="loadModule('permissions')">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Info Alert -->
                        <div class="alert alert-info alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <div class="d-flex">
                                <i class="bi bi-info-circle fs-5 me-2"></i>
                                <div>
                                    <strong>How it works:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Click on a user row to expand and manage their module access</li>
                                        <li>Toggle switches control access to individual dashboard tabs/modules</li>
                                        <li>Dashboard access determines which role-based dashboard they can view</li>
                                        <li>Module access controls specific tabs within those dashboards</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter -->
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <input type="text" id="searchUsers" class="form-control" 
                                               placeholder="🔍 Search by name or email...">
                                    </div>
                                    <div class="col-md-3">
                                        <select id="filterRole" class="form-select">
                                            <option value="">All Roles</option>
                                            <option value="admin">Admin</option>
                                            <option value="principal">Principal</option>
                                            <option value="vice_principal">Vice Principal</option>
                                            <option value="hod">HOD</option>
                                            <option value="teacher">Teacher</option>
                                            <option value="faculty">Faculty</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-outline-secondary w-100" onclick="$('#searchUsers').val(''); $('#filterRole').val(''); filterUsers();">
                                            <i class="bi bi-x-circle"></i> Clear Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Users List -->
                        <div id="usersList">
                            <?php if (empty($users)): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> No verified users found
                                </div>
                            <?php else: ?>
                                <?php foreach ($users as $idx => $user): ?>
                                    <div class="card shadow-sm mb-3 user-card" data-user-id="<?= (int)$user['id'] ?>" data-user-role="<?= esc($user['post']) ?>">
                                        <div class="card-header bg-light cursor-pointer" onclick="toggleUserPermissions(<?= (int)$user['id'] ?>)" style="cursor: pointer;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><i class="bi bi-person-circle"></i> <?= esc($user['name']) ?></strong>
                                                    <small class="text-muted ms-2"><?= esc($user['email']) ?></small>
                                                    <span class="badge bg-secondary ms-2"><?= esc($user['post'] ?: 'No Role') ?></span>
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-primary me-2" onclick="event.stopPropagation(); saveUserPermissions(<?= (int)$user['id'] ?>)">
                                                        <i class="bi bi-save"></i> Save
                                                    </button>
                                                    <i class="bi bi-chevron-down expand-icon"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body permissions-detail" style="display: none;">
                                            <div class="row">
                                                <!-- Dashboard Access Column -->
                                                <div class="col-md-3 border-end">
                                                    <h6 class="text-primary mb-3"><i class="bi bi-layout-sidebar"></i> Dashboard Access</h6>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="principal_access" <?= !empty($user['principal_access']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label">Principal Dashboard</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="vice_access" <?= !empty($user['vice_access']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label">Vice Principal Dashboard</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="hod_access" <?= !empty($user['hod_access']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label">HOD Dashboard</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="teacher_access" <?= !empty($user['teacher_access']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label">Teacher Dashboard</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Admin Modules Column -->
                                                <div class="col-md-3 border-end">
                                                    <h6 class="text-success mb-3"><i class="bi bi-grid"></i> Admin Modules</h6>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_overview" <?= !empty($user['module_overview']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">📊 Overview</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_user_management" <?= !empty($user['module_user_management']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">👥 User Management</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_exam_management" <?= !empty($user['module_exam_management']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">📅 Exam Management</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_approvals" <?= !empty($user['module_approvals']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">✓ Approvals</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_available_exams" <?= !empty($user['module_available_exams']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">📚 Available Exams</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Settings & Analytics Column -->
                                                <div class="col-md-3 border-end">
                                                    <h6 class="text-warning mb-3"><i class="bi bi-gear"></i> Settings & Analytics</h6>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_permissions" <?= !empty($user['module_permissions']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">🔒 Permissions Control</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_analytics" <?= !empty($user['module_analytics']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">📈 Analytics</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_audit_logs" <?= !empty($user['module_audit_logs']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">🕐 Activity Logs</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_settings" <?= !empty($user['module_settings']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">⚙️ System Settings</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Role Dashboards Column -->
                                                <div class="col-md-3">
                                                    <h6 class="text-info mb-3"><i class="bi bi-people"></i> Role Dashboards</h6>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_principal_dash" <?= !empty($user['module_principal_dash']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">🏆 Principal Tab</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_vice_dash" <?= !empty($user['module_vice_dash']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">💼 Vice Principal Tab</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_hod_dash" <?= !empty($user['module_hod_dash']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">🏢 HOD Tab</label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" data-perm="module_teacher_dash" <?= !empty($user['module_teacher_dash']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">👨‍🏫 Teacher Tab</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Quick Action Templates -->
                                            <div class="mt-3 pt-3 border-top">
                                                <small class="text-muted">Quick Templates:</small>
                                                <div class="btn-group btn-group-sm ms-2" role="group">
                                                    <button class="btn btn-outline-secondary" onclick="applyTemplate(<?= (int)$user['id'] ?>, 'admin')">Full Admin</button>
                                                    <button class="btn btn-outline-secondary" onclick="applyTemplate(<?= (int)$user['id'] ?>, 'principal')">Principal</button>
                                                    <button class="btn btn-outline-secondary" onclick="applyTemplate(<?= (int)$user['id'] ?>, 'teacher')">Teacher Only</button>
                                                    <button class="btn btn-outline-secondary" onclick="applyTemplate(<?= (int)$user['id'] ?>, 'none')">Clear All</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <script>
                    // Toggle user permissions panel
                    function toggleUserPermissions(userId) {
                        const card = $('[data-user-id="' + userId + '"]');
                        const detail = card.find('.permissions-detail');
                        const icon = card.find('.expand-icon');
                        
                        if (detail.is(':visible')) {
                            detail.slideUp(200);
                            icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                        } else {
                            // Close all other panels
                            $('.permissions-detail').slideUp(200);
                            $('.expand-icon').removeClass('bi-chevron-up').addClass('bi-chevron-down');
                            
                            // Open this panel
                            detail.slideDown(200);
                            icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                        }
                    }

                    // Apply permission template
                    function applyTemplate(userId, template) {
                        const card = $('[data-user-id="' + userId + '"]');
                        const checkboxes = card.find('input[type="checkbox"]');
                        
                        // Uncheck all first
                        checkboxes.prop('checked', false);
                        
                        if (template === 'admin') {
                            checkboxes.prop('checked', true);
                        } else if (template === 'principal') {
                            card.find('[data-perm="principal_access"]').prop('checked', true);
                            card.find('[data-perm="module_overview"]').prop('checked', true);
                            card.find('[data-perm="module_user_management"]').prop('checked', true);
                            card.find('[data-perm="module_exam_management"]').prop('checked', true);
                            card.find('[data-perm="module_approvals"]').prop('checked', true);
                            card.find('[data-perm="module_available_exams"]').prop('checked', true);
                            card.find('[data-perm="module_analytics"]').prop('checked', true);
                            card.find('[data-perm="module_principal_dash"]').prop('checked', true);
                        } else if (template === 'teacher') {
                            card.find('[data-perm="teacher_access"]').prop('checked', true);
                            card.find('[data-perm="module_overview"]').prop('checked', true);
                            card.find('[data-perm="module_available_exams"]').prop('checked', true);
                            card.find('[data-perm="module_teacher_dash"]').prop('checked', true);
                        }
                        
                        showToast('Success', template === 'none' ? 'Permissions cleared' : template.charAt(0).toUpperCase() + template.slice(1) + ' template applied', 'info');
                    }

                    // Filter users
                    function filterUsers() {
                        const searchTerm = $('#searchUsers').val().toLowerCase();
                        const roleFilter = $('#filterRole').val().toLowerCase();
                        
                        $('.user-card').each(function() {
                            const card = $(this);
                            const name = card.find('strong').text().toLowerCase();
                            const email = card.find('small').first().text().toLowerCase();
                            const role = card.data('user-role').toLowerCase();
                            
                            let showCard = true;
                            
                            if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm)) {
                                showCard = false;
                            }
                            
                            if (roleFilter && role !== roleFilter) {
                                showCard = false;
                            }
                            
                            card.toggle(showCard);
                        });
                    }

                    $('#searchUsers, #filterRole').on('keyup change', filterUsers);

                    // Save individual user permissions
                    function saveUserPermissions(userId) {
                        const card = $('[data-user-id="' + userId + '"]');
                        const permissions = {};
                        
                        // Collect all permission checkboxes
                        card.find('input[type="checkbox"][data-perm]').each(function() {
                            const perm = $(this).data('perm');
                            permissions[perm] = $(this).is(':checked') ? 1 : 0;
                        });

                        $.post('?action=update_permissions', {
                            user_id: userId,
                            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                            ...permissions
                        }, function(response) {
                            if (response.success) {
                                showToast('Success', 'Permissions updated successfully', 'success');
                            } else {
                                showToast('Error', response.message || 'Failed to update permissions', 'danger');
                            }
                        }).fail(function() {
                            showToast('Error', 'Request failed. Please try again.', 'danger');
                        });
                    }

                    // Save all permissions
                    function saveAllPermissions() {
                        if (!confirm('Save permissions for all users? This will update all visible users.')) return;
                        
                        let updateCount = 0;
                        let errors = 0;
                        const totalUsers = $('.user-card:visible').length;

                        $('.user-card:visible').each(function() {
                            const userId = $(this).data('user-id');
                            const permissions = {};
                            
                            $(this).find('input[type="checkbox"][data-perm]').each(function() {
                                const perm = $(this).data('perm');
                                permissions[perm] = $(this).is(':checked') ? 1 : 0;
                            });

                            $.post('?action=update_permissions', {
                                user_id: userId,
                                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                                ...permissions
                            }, function(response) {
                                if (response.success) {
                                    updateCount++;
                                } else {
                                    errors++;
                                }

                                if (updateCount + errors === totalUsers) {
                                    if (errors === 0) {
                                        showToast('Success', `All ${updateCount} users updated successfully`, 'success');
                                    } else {
                                        showToast('Warning', `${updateCount} updated, ${errors} failed`, 'warning');
                                    }
                                    setTimeout(() => loadModule('permissions'), 1500);
                                }
                            }).fail(function() {
                                errors++;
                                if (updateCount + errors === totalUsers) {
                                    showToast('Error', `${updateCount} updated, ${errors} failed`, 'danger');
                                }
                            });
                        });
                    }

                    function showToast(title, message, type) {
                        const toast = $(`
                            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                                <div class="d-flex">
                                    <div class="toast-body">
                                        <strong>${title}:</strong> ${message}
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                </div>
                            </div>
                        `);
                        $('body').append(toast);
                        const bsToast = new bootstrap.Toast(toast[0]);
                        bsToast.show();
                        setTimeout(() => toast.remove(), 5000);
                    }
                    </script>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;

                case 'analytics':
                    // ANALYTICS & REPORTS: Dashboard statistics and visualizations
                    $stats = getDashboardStats($pdo);
                    $chartData = getChartData($pdo);
                    
                    // Additional analytics queries
                    $usersByRole = $pdo->query("SELECT post AS role, COUNT(*) AS count FROM users WHERE post IS NOT NULL GROUP BY post")->fetchAll();
                    $examsByStatus = $pdo->query("SELECT status, COUNT(*) AS count FROM exams GROUP BY status")->fetchAll();
                    $recentActivity = getAuditLogs($pdo, 10);
                    
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1"><i class="bi bi-graph-up"></i> Analytics & Reports</h3>
                                <p class="text-muted small mb-0">System-wide statistics and insights</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-primary" onclick="exportAnalyticsReport()">
                                    <i class="bi bi-download"></i> Export Report
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="loadModule('analytics')">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Summary Stats Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="card-body text-white">
                                        <div class="small opacity-75">Total Users</div>
                                        <div class="h2 mb-0"><?= number_format($stats['total_users']) ?></div>
                                        <div class="small mt-2">
                                            <i class="bi bi-arrow-up"></i> <?= $stats['recent_registrations'] ?> this week
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <div class="card-body text-white">
                                        <div class="small opacity-75">Total Colleges</div>
                                        <div class="h2 mb-0"><?= number_format($stats['total_colleges']) ?></div>
                                        <div class="small mt-2">Registered institutions</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <div class="card-body text-white">
                                        <div class="small opacity-75">Total Exams</div>
                                        <div class="h2 mb-0"><?= number_format($stats['total_exams']) ?></div>
                                        <div class="small mt-2">All time</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <div class="card-body text-white">
                                        <div class="small opacity-75">Pending Items</div>
                                        <div class="h2 mb-0"><?= number_format($stats['pending_users'] + $stats['pending_approvals']) ?></div>
                                        <div class="small mt-2">Requires attention</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <i class="bi bi-pie-chart"></i> Users by Role
                                    </div>
                                    <div class="card-body">
                                        <canvas id="usersByRoleChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <i class="bi bi-bar-chart"></i> User Registrations (Last 6 Months)
                                    </div>
                                    <div class="card-body">
                                        <canvas id="registrationsChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <i class="bi bi-clipboard-data"></i> Exams by Status
                                    </div>
                                    <div class="card-body">
                                        <canvas id="examsByStatusChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-light">
                                        <i class="bi bi-shield-check"></i> User Verification Status
                                    </div>
                                    <div class="card-body">
                                        <canvas id="verificationStatusChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <i class="bi bi-clock-history"></i> Recent Admin Activity
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                    <p class="text-muted mb-0">No recent activity</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Admin</th>
                                                    <th>Action</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentActivity as $activity): ?>
                                                    <tr>
                                                        <td><small><?= date('M d, H:i', strtotime($activity['created_at'])) ?></small></td>
                                                        <td><small><?= esc($activity['admin_name']) ?></small></td>
                                                        <td><span class="badge bg-info"><?= esc($activity['action']) ?></span></td>
                                                        <td><small class="text-muted"><?= esc($activity['details']) ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
                    <script>
                    // Users by Role Chart
                    const usersByRoleCtx = document.getElementById('usersByRoleChart').getContext('2d');
                    new Chart(usersByRoleCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?= json_encode($chartData['roles']['labels']) ?>,
                            datasets: [{
                                data: <?= json_encode($chartData['roles']['data']) ?>,
                                backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#fa709a', '#43e97b']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });

                    // Registrations Chart
                    const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
                    new Chart(registrationsCtx, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($chartData['registrations']['labels']) ?>,
                            datasets: [{
                                label: 'Registrations',
                                data: <?= json_encode($chartData['registrations']['data']) ?>,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });

                    // Exams by Status Chart
                    const examsByStatusCtx = document.getElementById('examsByStatusChart').getContext('2d');
                    new Chart(examsByStatusCtx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode(array_column($examsByStatus, 'status')) ?>,
                            datasets: [{
                                label: 'Count',
                                data: <?= json_encode(array_column($examsByStatus, 'count')) ?>,
                                backgroundColor: ['#4facfe', '#f093fb', '#43e97b', '#fa709a']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });

                    // Verification Status Chart
                    const verificationStatusCtx = document.getElementById('verificationStatusChart').getContext('2d');
                    new Chart(verificationStatusCtx, {
                        type: 'pie',
                        data: {
                            labels: <?= json_encode($chartData['verification_status']['labels']) ?>,
                            datasets: [{
                                data: <?= json_encode($chartData['verification_status']['data']) ?>,
                                backgroundColor: ['#43e97b', '#fa709a', '#4facfe']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });

                    function exportAnalyticsReport() {
                        window.location.href = '?action=export_csv&type=analytics';
                    }
                    </script>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;

                case 'settings':
                    // SYSTEM SETTINGS: Application configuration
                    // Get current settings from database or config
                    $settingsQuery = $pdo->query("
                        CREATE TABLE IF NOT EXISTS system_settings (
                            setting_key VARCHAR(100) PRIMARY KEY,
                            setting_value TEXT,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Fetch existing settings
                    $settingsData = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    // Default settings
                    $settings = [
                        'system_name' => $settingsData['system_name'] ?? 'External Exam Management System',
                        'system_email' => $settingsData['system_email'] ?? 'admin@eems.edu',
                        'default_password' => $settingsData['default_password'] ?? 'Welcome@123',
                        'auto_verify_users' => $settingsData['auto_verify_users'] ?? '0',
                        'email_notifications' => $settingsData['email_notifications'] ?? '1',
                        'maintenance_mode' => $settingsData['maintenance_mode'] ?? '0',
                        'max_exam_assignments' => $settingsData['max_exam_assignments'] ?? '10',
                        'session_timeout' => $settingsData['session_timeout'] ?? '30'
                    ];
                    
                    ob_start();
                    ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-1"><i class="bi bi-gear"></i> System Settings</h3>
                                <p class="text-muted small mb-0">Configure system-wide parameters and preferences</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success" onclick="saveSettings()">
                                    <i class="bi bi-save"></i> Save Settings
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="loadModule('settings')">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </button>
                            </div>
                        </div>

                        <!-- Settings Form -->
                        <form id="settingsForm">
                            <div class="row g-4">
                                <!-- General Settings -->
                                <div class="col-md-6">
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-primary text-white">
                                            <i class="bi bi-sliders"></i> General Settings
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">System Name</label>
                                                <input type="text" class="form-control" name="system_name" 
                                                       value="<?= esc($settings['system_name']) ?>" required>
                                                <small class="text-muted">Displayed in headers and emails</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">System Email</label>
                                                <input type="email" class="form-control" name="system_email" 
                                                       value="<?= esc($settings['system_email']) ?>" required>
                                                <small class="text-muted">Default sender email address</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Session Timeout (minutes)</label>
                                                <input type="number" class="form-control" name="session_timeout" 
                                                       value="<?= esc($settings['session_timeout']) ?>" min="5" max="120">
                                                <small class="text-muted">User session expiration time</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- User Management Settings -->
                                <div class="col-md-6">
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-success text-white">
                                            <i class="bi bi-people"></i> User Management
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Default Password</label>
                                                <input type="text" class="form-control" name="default_password" 
                                                       value="<?= esc($settings['default_password']) ?>">
                                                <small class="text-muted">Password for newly verified users</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Max Exam Assignments</label>
                                                <input type="number" class="form-control" name="max_exam_assignments" 
                                                       value="<?= esc($settings['max_exam_assignments']) ?>" min="1" max="50">
                                                <small class="text-muted">Maximum exams per teacher</small>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="auto_verify_users" 
                                                           value="1" <?= $settings['auto_verify_users'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold">Auto-verify New Users</label>
                                                </div>
                                                <small class="text-muted d-block">Skip manual verification step</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- System Features -->
                                <div class="col-md-6">
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-toggles"></i> System Features
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="email_notifications" 
                                                           value="1" <?= $settings['email_notifications'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold">Email Notifications</label>
                                                </div>
                                                <small class="text-muted d-block">Send email alerts to users</small>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                                           value="1" <?= $settings['maintenance_mode'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold">Maintenance Mode</label>
                                                </div>
                                                <small class="text-muted d-block">Restrict access to admins only</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Database & Logs -->
                                <div class="col-md-6">
                                    <div class="card shadow-sm">
                                        <div class="card-header bg-warning text-dark">
                                            <i class="bi bi-database"></i> Database & Logs
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <button type="button" class="btn btn-outline-primary w-100" onclick="backupDatabase()">
                                                    <i class="bi bi-cloud-download"></i> Backup Database
                                                </button>
                                            </div>
                                            <div class="mb-3">
                                                <button type="button" class="btn btn-outline-secondary w-100" onclick="clearOldLogs()">
                                                    <i class="bi bi-trash"></i> Clear Old Logs (>30 days)
                                                </button>
                                            </div>
                                            <div class="mb-0">
                                                <button type="button" class="btn btn-outline-danger w-100" onclick="if(confirm('Clear ALL audit logs?')) clearAllLogs()">
                                                    <i class="bi bi-exclamation-triangle"></i> Clear All Logs
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <script>
                    function saveSettings() {
                        const formData = new FormData($('#settingsForm')[0]);
                        formData.append('action', 'save_settings');
                        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

                        // Handle unchecked checkboxes
                        $('input[type="checkbox"]').each(function() {
                            if (!$(this).is(':checked')) {
                                formData.append($(this).attr('name'), '0');
                            }
                        });

                        $.ajax({
                            url: '',
                            method: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (response.success) {
                                    showAlert('success', 'Settings saved successfully');
                                    setTimeout(() => loadModule('settings'), 1500);
                                } else {
                                    showAlert('danger', response.message || 'Failed to save settings');
                                }
                            },
                            error: function() {
                                showAlert('danger', 'Request failed. Please try again.');
                            }
                        });
                    }

                    function backupDatabase() {
                        window.location.href = '?action=backup_database';
                    }

                    function clearOldLogs() {
                        if (confirm('Clear audit logs older than 30 days?')) {
                            $.post('', {action: 'clear_old_logs', csrf_token: '<?= $_SESSION['csrf_token'] ?>'}, function(resp) {
                                showAlert(resp.success ? 'success' : 'danger', resp.message);
                            });
                        }
                    }

                    function clearAllLogs() {
                        $.post('', {action: 'clear_all_logs', csrf_token: '<?= $_SESSION['csrf_token'] ?>'}, function(resp) {
                            showAlert(resp.success ? 'success' : 'danger', resp.message);
                        });
                    }

                    function showAlert(type, message) {
                        const alert = $(`
                            <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                                ${message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `);
                        $('body').append(alert);
                        setTimeout(() => alert.remove(), 5000);
                    }
                    </script>
                    <?php
                    $html = ob_get_clean();
                    echo $html;
                    exit;

                default:
                    http_response_code(400);
                    echo "Unknown module";
                    exit;
            }
        }

        if ($action === 'update_user_status') {
             if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
             $csrf = $_POST['csrf_token'] ?? '';
             if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF token']);
            
             $userId = (int)($_POST['user_id'] ?? 0);
             $status = (string)($_POST['status'] ?? ''); // 'verified' or 'rejected'

             if ($userId > 0 && in_array($status, ['verified', 'rejected'])) {
                 if (updateUserStatus($pdo, $userId, $status)) {
                     json_response(['success' => true, 'message' => 'User status updated successfully.']);
                 } else {
                     json_response(['success' => false, 'message' => 'Failed to update user status.']);
                 }
             } else {
                 json_response(['success' => false, 'message' => 'Invalid user ID or status provided.']);
             }
        }

        if ($action === 'get_users') {
            // Return JSON of users and permissions for the admin permission-management modal
            $users = getUsersForPermissionManagement($pdo);
            json_response(['success' => true, 'users' => $users]);
        }

        if ($action === 'update_permissions') {
            // Update permissions POST handler
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['success' => false, 'message' => 'Invalid request method']);
            }
            // CSRF check
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
                json_response(['success' => false, 'message' => 'Invalid CSRF token']);
            }
            // Ensure admin
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                json_response(['success' => false, 'message' => 'Access denied']);
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid user']);
            }
            // Parse permissions from form (checkboxes)
            $perms = [
                'principal_access' => isset($_POST['principal_access']) ? 1 : 0,
                'vice_access' => isset($_POST['vice_access']) ? 1 : 0,
                'hod_access' => isset($_POST['hod_access']) ? 1 : 0,
                'teacher_access' => isset($_POST['teacher_access']) ? 1 : 0,
            ];
            updateUserPermissions($pdo, $userId, $perms);
            json_response(['success' => true, 'message' => 'Permissions updated']);
        }

        if ($action === 'approve_request' || $action === 'reject_request') {
            // Simple approve/reject example for approvals
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) json_response(['success' => false, 'message' => 'Invalid id']);
            $status = $action === 'approve_request' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare('UPDATE approvals SET status = :status, processed_by = :admin, processed_at = NOW() WHERE id = :id');
            $stmt->execute([':status' => $status, ':admin' => $adminId, ':id' => $id]);
            logAdminActivity($pdo, $adminId, "Approval Decision", "Set approval ID $id to $status");
            json_response(['success' => true, 'message' => 'Request ' . $status]);
        }

        // NEW: Get dashboard statistics
        if ($action === 'get_stats') {
            $stats = getDashboardStats($pdo);
            json_response(['success' => true, 'stats' => $stats]);
        }

        // NEW: Get chart data for analytics
        if ($action === 'get_chart_data') {
            $charts = getChartData($pdo);
            json_response(['success' => true, 'charts' => $charts]);
        }

        // NEW: Search and filter users
        if ($action === 'search_users') {
            $filters = [
                'search' => $_GET['search'] ?? '',
                'role' => $_GET['role'] ?? '',
                'college' => $_GET['college'] ?? '',
                'status' => $_GET['status'] ?? ''
            ];
            $results = searchUsers($pdo, $filters);
            json_response(['success' => true, 'users' => $results]);
        }

        // NEW: Get audit logs
        if ($action === 'get_audit_logs') {
            $limit = (int)($_GET['limit'] ?? 50);
            $logs = getAuditLogs($pdo, $limit);
            json_response(['success' => true, 'logs' => $logs]);
        }

        // NEW: Get colleges list for filtering
        if ($action === 'get_colleges') {
            $colleges = getAllColleges($pdo);
            json_response(['success' => true, 'colleges' => $colleges]);
        }

        // NEW: Bulk update user status
        if ($action === 'bulk_update_status') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $userIds = json_decode($_POST['user_ids'] ?? '[]', true);
            $status = $_POST['status'] ?? '';
            
            if (!is_array($userIds) || empty($userIds)) {
                json_response(['success' => false, 'message' => 'No users selected']);
            }
            
            $updated = bulkUpdateUserStatus($pdo, $userIds, $status, $adminId);
            json_response(['success' => true, 'message' => "Updated $updated user(s)", 'count' => $updated]);
        }

        // NEW: Export data as CSV
        if ($action === 'export_csv') {
            $type = $_GET['type'] ?? 'users'; // users, exams, or audit_logs
            
            if ($type === 'users') {
                $users = getRegisteredUsers($pdo);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d') . '.csv');
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'College', 'Phone', 'Status', 'Registered']);
                foreach ($users as $u) {
                    fputcsv($output, [
                        $u['id'], $u['name'], $u['email'], $u['post'], 
                        $u['college_name'], $u['phone'], $u['status'], $u['created_at']
                    ]);
                }
                fclose($output);
                exit;
            } elseif ($type === 'exams') {
                $exams = $pdo->query("SELECT * FROM exams ORDER BY exam_date DESC")->fetchAll();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=exams_export_' . date('Y-m-d') . '.csv');
                $output = fopen('php://output', 'w');
                if (!empty($exams)) {
                    fputcsv($output, array_keys($exams[0]));
                    foreach ($exams as $e) {
                        fputcsv($output, $e);
                    }
                }
                fclose($output);
                exit;
            } elseif ($type === 'audit_logs') {
                // ENHANCED: Export audit logs
                $logs = getAuditLogs($pdo, 1000); // Get last 1000 logs for export
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=audit_logs_export_' . date('Y-m-d') . '.csv');
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Timestamp', 'Admin ID', 'Admin Name', 'Action', 'Details', 'IP Address']);
                foreach ($logs as $log) {
                    fputcsv($output, [
                        $log['id'],
                        $log['created_at'] ?? '',
                        $log['admin_id'],
                        $log['admin_name'] ?? 'Unknown',
                        $log['action'],
                        $log['details'],
                        $log['ip_address']
                    ]);
                }
                fclose($output);
                exit;
            }
        }

        // NEW: Update user role
        if ($action === 'update_user_role') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $userId = (int)($_POST['user_id'] ?? 0);
            $newRole = $_POST['new_role'] ?? '';
            
            $allowed_roles = ['teacher', 'hod', 'vice_principal', 'principal', 'admin'];
            if (!in_array($newRole, $allowed_roles)) {
                json_response(['success' => false, 'message' => 'Invalid role']);
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET post = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);
                logAdminActivity($pdo, $adminId, "Role Change", "Changed user ID $userId to role: $newRole");
                json_response(['success' => true, 'message' => 'Role updated successfully']);
            } catch (Throwable $e) {
                json_response(['success' => false, 'message' => 'Failed to update role']);
            }
        }

        // Reset user password to default
        if ($action === 'reset_user_password' || $action === 'set_user_password') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid user ID']);
            }
            
            try {
                // Set default password Welcome@123
                $defaultPassword = 'Welcome@123';
                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Get user email for logging
                $userStmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                $actionType = $action === 'reset_user_password' ? 'Password Reset' : 'Password Set';
                logAdminActivity($pdo, $adminId, $actionType, "Set password for user: {$userData['name']} ({$userData['email']})");
                
                json_response(['success' => true, 'message' => "Password updated successfully. New password: $defaultPassword"]);
            } catch (Throwable $e) {
                error_log("Password update error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to update password']);
            }
        }

        // ========================================
        // EXAM MANAGEMENT AJAX ENDPOINTS
        // ========================================
        
        // Get exam details
        if ($action === 'get_exam_details') {
            $examId = (int)($_GET['exam_id'] ?? 0);
            if ($examId <= 0) json_response(['success' => false, 'message' => 'Invalid exam ID']);
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        e.id AS exam_id,
                        e.title AS exam_name,
                        COALESCE(e.subject, 'N/A') AS subject,
                        e.exam_date,
                        DATE_FORMAT(e.exam_date, '%Y-%m-%d') AS exam_date_raw,
                        COALESCE(e.status, 'Pending') AS status,
                        COALESCE(e.description, '') AS description,
                        COALESCE(e.department, 'Unknown') AS college_name,
                        u.name AS created_by_name,
                        e.created_at
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.id = ?
                ");
                $stmt->execute([$examId]);
                $exam = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($exam) {
                    $exam['exam_date'] = date('M d, Y', strtotime($exam['exam_date']));
                    json_response(['success' => true, 'exam' => $exam]);
                } else {
                    json_response(['success' => false, 'message' => 'Exam not found']);
                }
            } catch (Exception $e) {
                error_log("Get exam details error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to fetch exam details']);
            }
        }
        
        // Update exam status (Approve/Reject)
        if ($action === 'update_exam_status') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            
            if ($examId <= 0 || empty($newStatus)) {
                json_response(['success' => false, 'message' => 'Invalid parameters']);
            }
            
            $allowedStatuses = ['Pending', 'Approved', 'Assigned', 'Cancelled'];
            if (!in_array($newStatus, $allowedStatuses)) {
                json_response(['success' => false, 'message' => 'Invalid status']);
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE exams SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $examId]);
                
                logAdminActivity($pdo, $adminId, 'Exam Status Updated', "Updated exam ID $examId to status: $newStatus");
                
                json_response(['success' => true, 'message' => "Exam status updated to $newStatus successfully"]);
            } catch (Exception $e) {
                error_log("Update exam status error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to update exam status']);
            }
        }
        
        // Get available faculty for assignment
        if ($action === 'get_available_faculty') {
            $examId = (int)($_GET['exam_id'] ?? 0);
            
            try {
                // Get all teachers and HODs
                $stmt = $pdo->query("
                    SELECT id, name, college_name, post 
                    FROM users 
                    WHERE post IN ('teacher', 'hod') AND status = 'verified'
                    ORDER BY name
                ");
                $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                json_response(['success' => true, 'faculty' => $faculty]);
            } catch (Exception $e) {
                error_log("Get faculty error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to fetch faculty']);
            }
        }
        
        // Assign faculty to exam
        if ($action === 'assign_faculty_to_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $facultyId = (int)($_POST['faculty_id'] ?? 0);
            $role = trim($_POST['role'] ?? 'Invigilator');
            
            if ($examId <= 0 || $facultyId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid parameters']);
            }
            
            try {
                // Check if assignment already exists
                $checkStmt = $pdo->prepare("SELECT id FROM assignments WHERE faculty_id = ? AND exam_id = ?");
                $checkStmt->execute([$facultyId, $examId]);
                if ($checkStmt->fetch()) {
                    json_response(['success' => false, 'message' => 'Faculty already assigned to this exam']);
                }
                
                // Insert assignment
                $stmt = $pdo->prepare("INSERT INTO assignments (faculty_id, exam_id, role) VALUES (?, ?, ?)");
                $stmt->execute([$facultyId, $examId, $role]);
                
                // Update exam status to Assigned
                $updateStmt = $pdo->prepare("UPDATE exams SET status = 'Assigned' WHERE id = ?");
                $updateStmt->execute([$examId]);
                
                logAdminActivity($pdo, $adminId, 'Faculty Assigned', "Assigned faculty ID $facultyId to exam ID $examId");
                
                json_response(['success' => true, 'message' => 'Faculty assigned successfully']);
            } catch (Exception $e) {
                error_log("Assign faculty error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to assign faculty']);
            }
        }
        
        // Update exam details
        if ($action === 'update_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $examName = trim($_POST['exam_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $examDate = trim($_POST['exam_date'] ?? '');
            $status = trim($_POST['status'] ?? 'Pending');
            $description = trim($_POST['description'] ?? '');
            
            if ($examId <= 0 || empty($examName) || empty($examDate)) {
                json_response(['success' => false, 'message' => 'Missing required fields']);
            }
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE exams 
                    SET title = ?, subject = ?, exam_date = ?, status = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$examName, $subject, $examDate, $status, $description, $examId]);
                
                logAdminActivity($pdo, $adminId, 'Exam Updated', "Updated exam: $examName (ID: $examId)");
                
                json_response(['success' => true, 'message' => 'Exam updated successfully']);
            } catch (Exception $e) {
                error_log("Update exam error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to update exam']);
            }
        }
        
        // Delete exam
        if ($action === 'delete_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            
            if ($examId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid exam ID']);
            }
            
            try {
                // Get exam name for logging
                $getStmt = $pdo->prepare("SELECT title FROM exams WHERE id = ?");
                $getStmt->execute([$examId]);
                $examName = $getStmt->fetchColumn();
                
                // Delete exam (will cascade delete assignments)
                $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
                $stmt->execute([$examId]);
                
                logAdminActivity($pdo, $adminId, 'Exam Deleted', "Deleted exam: $examName (ID: $examId)");
                
                json_response(['success' => true, 'message' => 'Exam deleted successfully']);
            } catch (Exception $e) {
                error_log("Delete exam error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to delete exam']);
            }
        }
        
        // ========================================================================
        // EXAM MANAGEMENT AJAX HANDLERS - Role-Based Workflow
        // ========================================================================
        
        /**
         * CREATE EXAM (Principal/Vice Principal only)
         * Workflow: Principal/VP posts exam requirement for their college
         * Status: Initially set to 'Pending' for admin approval
         */
        if ($action === 'add_exam' || $action === 'create_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Role-based access: VP, HOD, Principal, or Admin can create exams
            $userRole = normalize_role($_SESSION['role'] ?? '');
            $allowedRoles = ['principal', 'vice-principal', 'hod', 'admin'];
            if (!in_array($userRole, $allowedRoles)) {
                json_response(['success' => false, 'message' => 'Only VP, HOD, Principal, or Admin can create exams']);
            }
            
            // Validate required fields
            $examName = trim($_POST['exam_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $college = trim($_POST['college'] ?? '');
            $examDate = trim($_POST['exam_date'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($examName) || empty($subject) || empty($college) || empty($examDate)) {
                json_response(['success' => false, 'message' => 'All required fields must be filled']);
            }
            
            // Validate exam date is in the future
            if (strtotime($examDate) < strtotime('today')) {
                json_response(['success' => false, 'message' => 'Exam date must be in the future']);
            }
            
            try {
                // Insert exam with Pending status (requires admin approval)
                $stmt = $pdo->prepare("
                    INSERT INTO exams (title, subject, exam_date, department, description, status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())
                ");
                $stmt->execute([$examName, $subject, $examDate, $college, $description, $adminId]);
                
                $examId = $pdo->lastInsertId();
                
                logAdminActivity($pdo, $adminId, 'Exam Posted', "Posted new exam: $examName (ID: $examId) for $college");
                
                json_response([
                    'success' => true, 
                    'message' => 'Exam posted successfully and is pending admin approval',
                    'exam_id' => $examId
                ]);
            } catch (Exception $e) {
                error_log("Create exam error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to create exam: ' . $e->getMessage()]);
            }
        }
        
        /**
         * APPROVE/REJECT EXAM (Principal and Admin)
         * Workflow: Principal/Admin reviews pending exams created by VP/HOD and approves them
         * Status transition: Pending → Approved (or stays Pending if rejected)
         */
        if ($action === 'update_exam_status' || $action === 'approve_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only Principal and Admin can approve/reject exams
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if (!in_array($userRole, ['admin', 'principal'])) {
                json_response(['success' => false, 'message' => 'Only Principal or Administrator can approve exams']);
            }
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            
            $allowedStatuses = ['Pending', 'Approved', 'Assigned', 'Cancelled'];
            if (!in_array($newStatus, $allowedStatuses)) {
                json_response(['success' => false, 'message' => 'Invalid status']);
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE exams SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $examId]);
                
                // Get exam details for logging
                $examStmt = $pdo->prepare("SELECT title FROM exams WHERE id = ?");
                $examStmt->execute([$examId]);
                $examTitle = $examStmt->fetchColumn();
                
                logAdminActivity($pdo, $adminId, "Exam Status Updated", "Changed exam '$examTitle' (ID: $examId) status to: $newStatus");
                
                json_response(['success' => true, 'message' => "Exam status updated to $newStatus"]);
            } catch (Exception $e) {
                error_log("Update exam status error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to update status']);
            }
        }
        
        /**
         * TEACHER SELF-ASSIGNMENT
         * Workflow: Teachers select eligible exams (Approved status, not assigned yet, not from their college)
         * This creates an entry in the assignments table
         * Status transition: Approved → Assigned (when first teacher selects)
         */
        if ($action === 'teacher_select_exam' || $action === 'self_assign_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only teachers can self-assign
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'faculty' && $userRole !== 'teacher') {
                json_response(['success' => false, 'message' => 'Only teachers can self-assign to exams']);
            }
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $teacherId = $adminId; // Current logged-in user
            
            try {
                // Get exam details and check eligibility
                $examStmt = $pdo->prepare("SELECT e.*, u.college_name as creator_college FROM exams e LEFT JOIN users u ON e.created_by = u.id WHERE e.id = ?");
                $examStmt->execute([$examId]);
                $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$exam) {
                    json_response(['success' => false, 'message' => 'Exam not found']);
                }
                
                // Check if exam is approved
                if ($exam['status'] !== 'Approved') {
                    json_response(['success' => false, 'message' => 'This exam is not available for selection']);
                }
                
                // Check if teacher already assigned to this exam
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE exam_id = ? AND faculty_id = ?");
                $checkStmt->execute([$examId, $teacherId]);
                if ($checkStmt->fetchColumn() > 0) {
                    json_response(['success' => false, 'message' => 'You have already been assigned to this exam']);
                }
                
                // Get teacher's college to prevent conflict of interest
                $teacherStmt = $pdo->prepare("SELECT college_name FROM users WHERE id = ?");
                $teacherStmt->execute([$teacherId]);
                $teacherCollege = $teacherStmt->fetchColumn();
                
                // Prevent teachers from selecting exams from their own college
                if ($exam['department'] === $teacherCollege) {
                    json_response(['success' => false, 'message' => 'You cannot select an exam from your own college (Conflict of Interest)']);
                }
                
                // Create assignment
                $assignStmt = $pdo->prepare("
                    INSERT INTO assignments (exam_id, faculty_id, role, assigned_at, assigned_by) 
                    VALUES (?, ?, 'Examiner', NOW(), NULL)
                ");
                $assignStmt->execute([$examId, $teacherId]);
                
                // Update exam status to 'Assigned' if this is the first assignment
                $countAssignments = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE exam_id = ?");
                $countAssignments->execute([$examId]);
                if ($countAssignments->fetchColumn() === 1) {
                    $updateStatus = $pdo->prepare("UPDATE exams SET status = 'Assigned', updated_at = NOW() WHERE id = ?");
                    $updateStatus->execute([$examId]);
                }
                
                logAdminActivity($pdo, $teacherId, "Self-Assigned to Exam", "Teacher self-assigned to exam: {$exam['title']} (ID: $examId)");
                
                json_response([
                    'success' => true,
                    'message' => 'Successfully scheduled for this exam! You will receive further instructions.',
                    'exam_id' => $examId
                ]);
            } catch (Exception $e) {
                error_log("Teacher self-assign error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to assign: ' . $e->getMessage()]);
            }
        }
        
        /**
         * ADMIN ASSIGN FACULTY (Admin only)
         * Workflow: Admin manually assigns faculty to exams
         */
        if ($action === 'assign_faculty_to_exam' || $action === 'admin_assign_faculty') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only admin can manually assign faculty
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can assign faculty']);
            }
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $facultyId = (int)($_POST['faculty_id'] ?? 0);
            $role = trim($_POST['role'] ?? 'Examiner');
            
            if ($examId <= 0 || $facultyId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid exam or faculty ID']);
            }
            
            try {
                // Check if already assigned
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE exam_id = ? AND faculty_id = ?");
                $checkStmt->execute([$examId, $facultyId]);
                if ($checkStmt->fetchColumn() > 0) {
                    json_response(['success' => false, 'message' => 'Faculty already assigned to this exam']);
                }
                
                // Create assignment
                $stmt = $pdo->prepare("
                    INSERT INTO assignments (exam_id, faculty_id, role, assigned_at, assigned_by) 
                    VALUES (?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$examId, $facultyId, $role, $adminId]);
                
                // Update exam status to Assigned
                $updateStmt = $pdo->prepare("UPDATE exams SET status = 'Assigned', updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$examId]);
                
                // Get details for logging
                $detailsStmt = $pdo->prepare("
                    SELECT e.title, u.name as faculty_name 
                    FROM exams e, users u 
                    WHERE e.id = ? AND u.id = ?
                ");
                $detailsStmt->execute([$examId, $facultyId]);
                $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);
                
                logAdminActivity($pdo, $adminId, "Faculty Assigned", "Assigned {$details['faculty_name']} to exam: {$details['title']}");
                
                json_response(['success' => true, 'message' => 'Faculty assigned successfully']);
            } catch (Exception $e) {
                error_log("Assign faculty error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to assign faculty: ' . $e->getMessage()]);
            }
        }
        
        /**
         * GET AVAILABLE FACULTY FOR ASSIGNMENT
         * Returns list of teachers/HODs eligible for exam assignment
         */
        if ($action === 'get_available_faculty' || $action === 'get_eligible_faculty') {
            $examId = (int)($_GET['exam_id'] ?? 0);
            
            try {
                // Get exam college to exclude faculty from same college
                $examStmt = $pdo->prepare("SELECT department FROM exams WHERE id = ?");
                $examStmt->execute([$examId]);
                $examCollege = $examStmt->fetchColumn();
                
                // Get all teachers/HODs not from the exam's college and not already assigned
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name, u.college_name, u.post, u.email
                    FROM users u
                    WHERE u.post IN ('teacher', 'hod')
                    AND u.status = 'verified'
                    AND u.college_name != ?
                    AND u.id NOT IN (SELECT faculty_id FROM assignments WHERE exam_id = ?)
                    ORDER BY u.name ASC
                ");
                $stmt->execute([$examCollege, $examId]);
                $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                json_response(['success' => true, 'faculty' => $faculty]);
            } catch (Exception $e) {
                error_log("Get faculty error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to fetch faculty']);
            }
        }
        
        /**
         * GET EXAM DETAILS WITH ASSIGNMENTS
         * Shows exam info and list of assigned faculty
         */
        if ($action === 'get_exam_details') {
            $examId = (int)($_GET['exam_id'] ?? 0);
            
            try {
                // Get exam details
                $examStmt = $pdo->prepare("
                    SELECT e.*, u.name as created_by_name, u.post as creator_role
                    FROM exams e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.id = ?
                ");
                $examStmt->execute([$examId]);
                $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$exam) {
                    json_response(['success' => false, 'message' => 'Exam not found']);
                }
                
                // Get assigned faculty
                $assignStmt = $pdo->prepare("
                    SELECT u.id, u.name, u.college_name, u.post, u.email, a.role as assignment_role, a.assigned_at
                    FROM assignments a
                    JOIN users u ON a.faculty_id = u.id
                    WHERE a.exam_id = ?
                    ORDER BY a.assigned_at DESC
                ");
                $assignStmt->execute([$examId]);
                $assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $exam['assignments'] = $assignments;
                $exam['total_assigned'] = count($assignments);
                
                json_response(['success' => true, 'exam' => $exam]);
            } catch (Exception $e) {
                error_log("Get exam details error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to fetch exam details']);
            }
        }
        
        /**
         * UPDATE EXAM (Admin/Creator only)
         */
        if ($action === 'update_exam' || $action === 'edit_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $examName = trim($_POST['exam_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $college = trim($_POST['college'] ?? '');
            $examDate = trim($_POST['exam_date'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($examName) || empty($subject) || empty($college) || empty($examDate)) {
                json_response(['success' => false, 'message' => 'All required fields must be filled']);
            }
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE exams 
                    SET title = ?, subject = ?, exam_date = ?, department = ?, description = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$examName, $subject, $examDate, $college, $description, $examId]);
                
                logAdminActivity($pdo, $adminId, "Exam Updated", "Updated exam: $examName (ID: $examId)");
                
                json_response(['success' => true, 'message' => 'Exam updated successfully']);
            } catch (Exception $e) {
                error_log("Update exam error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to update exam: ' . $e->getMessage()]);
            }
        }
        
        /**
         * DELETE EXAM (Admin only)
         */
        if ($action === 'delete_exam') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only admin can delete exams
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can delete exams']);
            }
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            
            try {
                // Get exam title for logging
                $titleStmt = $pdo->prepare("SELECT title FROM exams WHERE id = ?");
                $titleStmt->execute([$examId]);
                $examTitle = $titleStmt->fetchColumn();
                
                // Delete associated assignments first (cascade)
                $deleteAssignments = $pdo->prepare("DELETE FROM assignments WHERE exam_id = ?");
                $deleteAssignments->execute([$examId]);
                
                // Delete exam
                $deleteExam = $pdo->prepare("DELETE FROM exams WHERE id = ?");
                $deleteExam->execute([$examId]);
                
                logAdminActivity($pdo, $adminId, "Exam Deleted", "Deleted exam: $examTitle (ID: $examId)");
                
                json_response(['success' => true, 'message' => 'Exam deleted successfully']);
            } catch (Exception $e) {
                error_log("Delete exam error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to delete exam: ' . $e->getMessage()]);
            }
        }
        
        /**
         * REMOVE FACULTY ASSIGNMENT (Admin only)
         */
        if ($action === 'remove_faculty_assignment') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can remove assignments']);
            }
            
            $assignmentId = (int)($_POST['assignment_id'] ?? 0);
            $examId = (int)($_POST['exam_id'] ?? 0);
            $facultyId = (int)($_POST['faculty_id'] ?? 0);
            
            try {
                // Delete assignment
                if ($assignmentId > 0) {
                    $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
                    $stmt->execute([$assignmentId]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM assignments WHERE exam_id = ? AND faculty_id = ?");
                    $stmt->execute([$examId, $facultyId]);
                }
                
                // Check if any assignments left, if not change exam status back to Approved
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE exam_id = ?");
                $countStmt->execute([$examId]);
                if ($countStmt->fetchColumn() === 0) {
                    $updateStatus = $pdo->prepare("UPDATE exams SET status = 'Approved', updated_at = NOW() WHERE id = ?");
                    $updateStatus->execute([$examId]);
                }
                
                logAdminActivity($pdo, $adminId, "Assignment Removed", "Removed faculty assignment from exam ID: $examId");
                
                json_response(['success' => true, 'message' => 'Assignment removed successfully']);
            } catch (Exception $e) {
                error_log("Remove assignment error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to remove assignment']);
            }
        }
        
        // Export exams to CSV
        if ($action === 'export_exams') {
            try {
                $stmt = $pdo->query("
                    SELECT 
                        e.id,
                        e.title AS exam_name,
                        COALESCE(e.subject, 'N/A') AS subject,
                        e.exam_date,
                        COALESCE(e.status, 'Pending') AS status,
                        COALESCE(e.department, 'Unknown') AS college_name,
                        COALESCE(e.description, '') AS description
                    FROM exams e
                    ORDER BY e.exam_date DESC
                ");
                $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Set headers for CSV download
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="exams_export_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // Add CSV headers
                fputcsv($output, ['ID', 'Exam Name', 'Subject', 'Exam Date', 'Status', 'College', 'Description']);
                
                // Add data rows
                foreach ($exams as $exam) {
                    fputcsv($output, $exam);
                }
                
                fclose($output);
                exit;
            } catch (Exception $e) {
                error_log("Export exams error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to export exams']);
            }
        }

        // ========================================
        // SYSTEM SETTINGS HANDLERS
        // ========================================
        
        if ($action === 'save_settings') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only admin can update settings
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can update settings']);
            }
            
            try {
                // Create settings table if not exists
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS system_settings (
                        setting_key VARCHAR(100) PRIMARY KEY,
                        setting_value TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                
                // Settings to save
                $settingsToSave = [
                    'system_name',
                    'system_email',
                    'default_password',
                    'auto_verify_users',
                    'email_notifications',
                    'maintenance_mode',
                    'max_exam_assignments',
                    'session_timeout'
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                
                foreach ($settingsToSave as $key) {
                    $value = $_POST[$key] ?? '';
                    $stmt->execute([$key, $value]);
                }
                
                logAdminActivity($pdo, $adminId, 'Settings Updated', 'Updated system settings');
                
                json_response(['success' => true, 'message' => 'Settings saved successfully']);
            } catch (Exception $e) {
                error_log("Save settings error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to save settings']);
            }
        }
        
        if ($action === 'backup_database') {
            // Only admin can backup database
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can backup database']);
            }
            
            try {
                // Simple database dump (requires exec permissions)
                $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backupPath = __DIR__ . '/backups/' . $backupFile;
                
                // Create backups directory if not exists
                if (!is_dir(__DIR__ . '/backups')) {
                    mkdir(__DIR__ . '/backups', 0755, true);
                }
                
                // Note: This requires mysqldump to be available
                // Alternative: use PHP to export tables
                logAdminActivity($pdo, $adminId, 'Database Backup', 'Initiated database backup');
                
                // For now, just create a simple export of critical tables
                $tables = ['users', 'exams', 'assignments', 'permissions'];
                $output = "-- Database backup: " . date('Y-m-d H:i:s') . "\n\n";
                
                foreach ($tables as $table) {
                    $result = $pdo->query("SELECT * FROM $table");
                    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        $output .= "-- Table: $table\n";
                        $columns = array_keys($rows[0]);
                        foreach ($rows as $row) {
                            $values = array_map(function($val) use ($pdo) {
                                return $pdo->quote($val);
                            }, array_values($row));
                            $output .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $output .= "\n";
                    }
                }
                
                file_put_contents($backupPath, $output);
                
                // Download the backup file
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $backupFile . '"');
                header('Content-Length: ' . filesize($backupPath));
                readfile($backupPath);
                exit;
            } catch (Exception $e) {
                error_log("Backup database error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to backup database']);
            }
        }
        
        if ($action === 'clear_old_logs') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only admin can clear logs
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can clear logs']);
            }
            
            try {
                $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                
                logAdminActivity($pdo, $adminId, 'Logs Cleared', "Deleted $deleted old audit logs (>30 days)");
                
                json_response(['success' => true, 'message' => "Cleared $deleted old log entries"]);
            } catch (Exception $e) {
                error_log("Clear old logs error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to clear logs']);
            }
        }
        
        if ($action === 'clear_all_logs') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Invalid method']);
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf)) json_response(['success' => false, 'message' => 'Invalid CSRF']);
            
            // Only admin can clear logs
            $userRole = normalize_role($_SESSION['role'] ?? '');
            if ($userRole !== 'admin') {
                json_response(['success' => false, 'message' => 'Only admins can clear logs']);
            }
            
            try {
                $countStmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
                $count = $countStmt->fetchColumn();
                
                $stmt = $pdo->prepare("TRUNCATE TABLE audit_logs");
                $stmt->execute();
                
                // Log this action to security.log file instead since we just cleared the database logs
                error_log("Admin ID $adminId cleared all $count audit logs");
                
                json_response(['success' => true, 'message' => "Cleared all $count log entries"]);
            } catch (Exception $e) {
                error_log("Clear all logs error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to clear logs']);
            }
        }

        // Unknown action
        json_response(['success' => false, 'message' => 'Unknown action']);
    } catch (Exception $ex) {
        // On error return friendly message and log details (not printed)
        // error_log($ex->getMessage());
        json_response(['success' => false, 'message' => 'Server error, try again later.']);
    }
}

// ---------------------------
// If not AJAX endpoint: render full HTML page
// ---------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EEMS</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap 5 CSS (for modals and some components) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar-link {
            transition: all 0.2s ease;
        }
        
        .sidebar-link:hover {
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        @media (max-width: 768px) {
            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar-mobile.open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

<!-- Top Navigation Bar -->
<nav class="glass-card shadow-lg sticky top-0 z-50 border-b border-gray-200">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo & Brand -->
            <div class="flex items-center space-x-4">
                <button id="mobileSidebarToggle" class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold gradient-text">EEMS Admin</h1>
                        <p class="text-xs text-gray-500">Management Panel</p>
                    </div>
                </div>
            </div>

            <!-- Right Side: Notifications & User Menu -->
            <div class="flex items-center space-x-3">
                <!-- Notifications -->
                <button id="notifBtn" class="relative p-2 rounded-lg hover:bg-purple-50 transition group">
                    <svg class="w-6 h-6 text-gray-600 group-hover:text-purple-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span id="notifCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-semibold">0</span>
                </button>

                <!-- User Dropdown -->
                <div class="relative">
                    <button id="userMenuBtn" class="flex items-center space-x-3 p-2 rounded-lg hover:bg-purple-50 transition">
                        <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-full flex items-center justify-center">
                            <span class="text-white font-semibold text-sm"><?= strtoupper(substr(esc($_SESSION['name'] ?? 'A'), 0, 1)) ?></span>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-gray-700"><?= esc($_SESSION['name'] ?? 'Admin') ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?= esc($_SESSION['role']) ?></p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <!-- Dropdown Menu (Hidden by default) -->
                    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-purple-50 to-indigo-50">
                            <p class="text-sm font-semibold text-gray-800"><?= esc($_SESSION['name'] ?? 'Admin') ?></p>
                            <p class="text-xs text-gray-500"><?= esc($_SESSION['user_id'] ?? 'ID: N/A') ?></p>
                        </div>
                        <div class="py-2">
                            <a href="#" id="managePermissions" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 transition">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                </svg>
                                Manage Permissions
                            </a>
                            <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 transition">
                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                User Dashboard
                            </a>
                        </div>
                        
                        <!-- Other Role Dashboards -->
                        <div class="border-t border-gray-100">
                            <div class="px-4 py-2 bg-gray-50">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">View Other Dashboards</p>
                            </div>
                            <a href="#" onclick="loadModule('principal'); return false;" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 transition">
                                <svg class="w-4 h-4 mr-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                Principal Dashboard
                            </a>
                            <a href="#" onclick="loadModule('vice'); return false;" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 transition">
                                <svg class="w-4 h-4 mr-3 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Vice Principal Dashboard
                            </a>
                            <a href="#" onclick="loadModule('hod'); return false;" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 transition">
                                <svg class="w-4 h-4 mr-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                HOD Dashboard
                            </a>
                            <a href="#" onclick="loadModule('teacher'); return false;" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-green-50 transition">
                                <svg class="w-4 h-4 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Teacher Dashboard
                            </a>
                            <a href="#" onclick="loadModule('n8n'); return false;" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 transition">
                                <svg class="w-4 h-4 mr-3 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Automation Dashboard
                            </a>
                        </div>
                        <div class="border-t border-gray-100">
                            <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="flex" style="height: calc(100vh - 4rem);">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar-mobile fixed md:sticky top-16 left-0 w-64 bg-white shadow-xl border-r border-gray-200 h-full overflow-y-auto z-40 md:translate-x-0">
        <div class="flex flex-col h-full p-4">
            <!-- MODERN ADMIN NAVIGATION - Exclusive admin-only controls -->
            <!-- Role dashboards removed: Admin dashboard is now unique and distinct -->
            <div class="flex-1">
                <!-- Admin Control Panel Section -->

                <div class="mb-6">
                    <h3 class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                        </svg>
                        Admin Control Panel
                    </h3>
                    <nav id="sidebarNav" class="space-y-1">
                        <!-- Dashboard & Analytics -->
                        <a href="#" class="sidebar-link active flex items-center px-4 py-3 text-white bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl text-sm font-medium transition shadow-lg" data-module="overview">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 12a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z"/>
                            </svg>
                            Dashboard Overview
                        </a>
                        
                        <!-- User Management -->
                        <a href="#" class="sidebar-link flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="user_management">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            All Users
                        </a>
                        
                        <!-- Approvals Center -->
                        <a href="#" class="sidebar-link flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="approvals_verifications">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Approval Center
                            <?php if ($PENDING_TOTAL > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full bg-red-500 text-white animate-pulse"><?= (int)$PENDING_TOTAL ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Exam Management -->
                        <a href="#" class="sidebar-link flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="exam_management">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                                Exam Management
                            </div>
                            <span id="pendingExamsBadge" class="hidden badge bg-warning text-dark px-2 py-1 rounded-full text-xs font-bold"></span>
                        </a>
                        
                        <!-- Available Exams for Teachers (Conditional) -->
                        <?php 
                        $currentUserRole = normalize_role($_SESSION['role'] ?? '');
                        if (in_array($currentUserRole, ['faculty', 'teacher', 'hod'])): 
                        ?>
                        <a href="#" class="sidebar-link flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="available_exams">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Available Exams
                            </div>
                            <span class="badge bg-success text-white px-2 py-1 rounded-full text-xs font-bold">Teacher</span>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Permissions & Access -->
                        <a href="#" class="sidebar-link flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="permissions">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Permissions Control
                        </a>
                        
                        <!-- Analytics & Reports -->
                        <a href="#" class="sidebar-link flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="analytics">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Analytics & Reports
                        </a>
                        
                        <!-- Activity Logs -->
                        <a href="#" class="sidebar-link flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="audit_logs">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Activity Logs
                        </a>
                        
                        <!-- System Settings -->
                        <a href="#" class="sidebar-link flex items-center px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-sm font-medium transition" data-module="settings">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            System Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="border-t border-gray-200 pt-4 mt-auto space-y-2">
                <?php 
                // Show Add Exam button for roles that can create exams
                $canCreateExams = in_array($currentUserRole, ['admin', 'principal', 'vice-principal', 'hod']);
                if ($canCreateExams): 
                ?>
                <button id="quickAddExam" class="w-full flex items-center justify-center px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:shadow-lg transition font-medium text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Exam
                </button>
                <?php endif; ?>
                <button id="exportReports" class="w-full flex items-center justify-center px-4 py-2.5 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export Reports
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto bg-gray-50" style="height: 100%;">
        <div id="mainContent" class="p-6 md:p-8">
            <!-- Loading State -->
            <div class="flex items-center justify-center py-20">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-purple-200 border-t-purple-600 mb-4"></div>
                    <p class="text-gray-500 font-medium">Loading dashboard...</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Permission modal -->
<div class="modal fade" id="permModal" tabindex="-1" aria-labelledby="permModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="permModalLabel">Manage User Permissions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="permAlert"></div>
        <div class="table-responsive">
            <table class="table table-sm" id="permTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Principal</th>
                        <th>Vice</th>
                        <th>HOD</th>
                        <th>Teacher</th>
                        <th>Save</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by JS via AJAX -->
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// CSRF token from PHP session
const CSRF_TOKEN = '<?= esc($_SESSION['csrf_token']) ?>';

$(function(){
    console.log('Document ready - initializing dashboard');
    console.log('jQuery version:', $.fn.jquery);
    console.log('CSRF Token:', CSRF_TOKEN ? 'Set' : 'Missing');
    
    // User dropdown toggle
    $('#userMenuBtn').on('click', function(e){
        e.stopPropagation();
        $('#userDropdown').toggleClass('hidden');
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function(e){
        if (!$(e.target).closest('#userMenuBtn, #userDropdown').length) {
            $('#userDropdown').addClass('hidden');
        }
    });
    
    // Mobile sidebar toggle
    $('#mobileSidebarToggle').on('click', function(){
        $('#sidebar').toggleClass('open');
    });
    
    // Close mobile sidebar when clicking outside
    $(document).on('click', function(e){
        if (window.innerWidth < 768 && !$(e.target).closest('#sidebar, #mobileSidebarToggle').length) {
            $('#sidebar').removeClass('open');
        }
    });
    
    // Load default module - show Overview dashboard (or Approvals if urgent pending items)
    const DEFAULT_MODULE = '<?= ($PENDING_TOTAL > 0 && ($_SESSION['role'] ?? '') === 'admin') ? 'approvals_verifications' : 'overview' ?>';
    console.log('About to load default module:', DEFAULT_MODULE);
    try {
        loadModule(DEFAULT_MODULE);
    } catch (error) {
        console.error('Error loading module:', error);
        $('#mainContent').html('<div class="alert alert-danger m-4">Error loading dashboard: ' + error.message + '</div>');
    }

    // Function to update pending exams badge in sidebar
    function updatePendingExamsBadge() {
        $.get('?action=get_stats', function(response) {
            if (response.success && response.stats.pending_exams > 0) {
                $('#pendingExamsBadge').text(response.stats.pending_exams).removeClass('hidden');
            } else {
                $('#pendingExamsBadge').addClass('hidden');
            }
        }, 'json');
    }
    
    // Update badge on page load
    updatePendingExamsBadge();
    
    // Refresh badge every 2 minutes
    setInterval(updatePendingExamsBadge, 120000);

    // Sidebar nav clicks
    $('#sidebarNav a').on('click', function(e){
        e.preventDefault();
        $('#sidebarNav a').removeClass('active text-white').addClass('text-gray-600 hover:bg-purple-50');
        $(this).removeClass('text-gray-600 hover:bg-purple-50').addClass('active text-white');
        const module = $(this).data('module');
        loadModule(module);
        
        // Close mobile sidebar after selection
        if (window.innerWidth < 768) {
            $('#sidebar').removeClass('open');
        }
    });

    // Quick actions
    $('#quickAddExam').on('click', function(){
        // Load exam management module and trigger add exam modal
        loadModule('exam_management');
        setTimeout(function() {
            if (typeof showAddExamModal === 'function') {
                showAddExamModal();
            }
        }, 500);
    });
    $('#exportReports').on('click', function(){
        // Show export options modal
        const exportModal = `
            <div class="modal fade" id="exportModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-download"></i> Export Reports</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">Select the type of report to export:</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="exportReport('users')">
                                    <i class="bi bi-people"></i> Users Report (CSV)
                                </button>
                                <button class="btn btn-outline-success" onclick="exportReport('exams')">
                                    <i class="bi bi-calendar-check"></i> Exams Report (CSV)
                                </button>
                                <button class="btn btn-outline-info" onclick="exportReport('audit_logs')">
                                    <i class="bi bi-clock-history"></i> Audit Logs (CSV)
                                </button>
                                <button class="btn btn-outline-warning" onclick="exportReport('analytics')">
                                    <i class="bi bi-graph-up"></i> Analytics Report (CSV)
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        $('#exportModal').remove();
        
        // Add and show modal
        $('body').append(exportModal);
        const modal = new bootstrap.Modal($('#exportModal')[0]);
        modal.show();
    });

    // Manage permissions modal
    $('#managePermissions').on('click', function(e){
        e.preventDefault();
        $('#userDropdown').addClass('hidden');
        $('#permModal').modal('show');
        fetchPermissions();
    });

    // Notification count: placeholder: in real app fetch count via AJAX
    $('#notifCount').text('<?= (int)0 ?>');
});

function loadModule(module) {
    console.log('Loading module:', module);
    $('#mainContent').html('<div class="d-flex justify-content-center p-5 text-muted">Loading...</div>');
    
    // Update active state in sidebar
    $('#sidebarNav a').removeClass('active text-white').addClass('text-gray-600 hover:bg-purple-50');
    $('#sidebarNav a[data-module="' + module + '"]').removeClass('text-gray-600 hover:bg-purple-50').addClass('active text-white');
    
    $.get('?action=load_module&module=' + encodeURIComponent(module), function(resp){
        console.log('Module loaded successfully:', module);
        $('#mainContent').html(resp);
        // Wire up approve/reject buttons for approvals
        $('.approve-btn').on('click', function(){ handleApproval($(this).data('id'), 'approve'); });
        $('.reject-btn').on('click', function(){ handleApproval($(this).data('id'), 'reject'); });
        // Wire up user management buttons
        $('.user-action-btn').on('click', function(){ 
            const action = $(this).data('action');
            const userId = $(this).data('id');
            const status = action === 'verify' ? 'verified' : 'rejected';
            handleUserStatusUpdate(userId, status);
        });
    }).fail(function(xhr){
        console.error('Failed to load module:', module, xhr);
        $('#mainContent').html('<div class="alert alert-danger">Failed to load module. Error: ' + xhr.status + ' ' + xhr.statusText + '</div>');
    });
}

function handleApproval(id, type) {
    if (!confirm('Are you sure?')) return;
    const action = type === 'approve' ? 'approve_request' : 'reject_request';
    $.post('?action=' + action, { id: id, csrf_token: CSRF_TOKEN }, function(res){
        if (res.success) {
            alert(res.message);
            // reload current module to update list
            const active = $('#sidebarNav a.active').data('module') || 'principal';
            loadModule(active);
        } else {
            alert('Error: ' + res.message);
        }
    }).fail(function(){ alert('Server error'); });
}

function handleUserStatusUpdate(userId, status) {
    if (!confirm('Are you sure you want to ' + status.slice(0, -1) + ' this user?')) return;
    
    $.post('?action=update_user_status', { 
        user_id: userId, 
        status: status,
        csrf_token: CSRF_TOKEN 
    }, function(res){
        if (res.success) {
            alert(res.message);
            // Reload the user management module to see the change
            loadModule('user_management');
        } else {
            alert('Error: ' + (res.message || 'An unknown error occurred.'));
        }
    }, 'json').fail(function(){ 
        alert('A server error occurred. Please try again.'); 
    });
}

function fetchPermissions(){
    $('#permTable tbody').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
    $.getJSON('?action=get_users', function(data){
        if (!data.success) {
            $('#permTable tbody').html('<tr><td colspan="6">Failed to load</td></tr>');
            return;
        }
        const rows = [];
        data.users.forEach(function(u){
            const uid = u.id;
            const checked = val => val == 1 ? 'checked' : '';
            rows.push('\n                <tr data-user-id="'+uid+'">\n                    <td>'+escapeHtml(u.name || u.email)+'</td>\n                    <td><input type="checkbox" class="form-check-input perm-chk principal" '+checked(u.principal_access)+'></td>\n                    <td><input type="checkbox" class="form-check-input perm-chk vice" '+checked(u.vice_access)+'></td>\n                    <td><input type="checkbox" class="form-check-input perm-chk hod" '+checked(u.hod_access)+'></td>\n                    <td><input type="checkbox" class="form-check-input perm-chk teacher" '+checked(u.teacher_access)+'></td>\n                    <td><button class="btn btn-sm btn-primary save-perm">Save</button></td>\n                </tr>');
        });
        $('#permTable tbody').html(rows.join(''));
        // Wire save buttons
        $('#permTable .save-perm').on('click', function(){
            const tr = $(this).closest('tr');
            const uid = (tr.data('user-id') || 0);
            const data = {
                action: 'update_permissions',
                user_id: uid,
                principal_access: tr.find('.principal').is(':checked') ? 1 : 0,
                vice_access: tr.find('.vice').is(':checked') ? 1 : 0,
                hod_access: tr.find('.hod').is(':checked') ? 1 : 0,
                teacher_access: tr.find('.teacher').is(':checked') ? 1 : 0,
                csrf_token: CSRF_TOKEN
            };
            $.post('?', data, function(res){
                if (res.success) {
                    showPermAlert('success', res.message);
                } else {
                    showPermAlert('danger', res.message);
                }
            }, 'json').fail(function(){ showPermAlert('danger', 'Server error'); });
        });
    }).fail(function(){
        $('#permTable tbody').html('<tr><td colspan="6">Server error</td></tr>');
    });
}

function showPermAlert(type, text) {
    $('#permAlert').html('<div class="alert alert-'+type+'">'+escapeHtml(text)+'</div>');
    setTimeout(function(){ $('#permAlert').html(''); }, 3500);
}

function escapeHtml(text) {
    return $('<div>').text(text).html();
}

function exportReport(type) {
    // Close the export modal
    $('#exportModal').modal('hide');
    
    // Show loading message
    const toast = $(`
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 11">
            <div class="toast show" role="alert">
                <div class="toast-header bg-primary text-white">
                    <strong class="me-auto">Exporting...</strong>
                </div>
                <div class="toast-body">
                    Generating ${type} report, please wait...
                </div>
            </div>
        </div>
    `);
    $('body').append(toast);
    
    // Redirect to export endpoint
    window.location.href = '?action=export_csv&type=' + encodeURIComponent(type);
    
    // Remove toast after 3 seconds
    setTimeout(() => toast.remove(), 3000);
}
</script>
</body>
</html>
