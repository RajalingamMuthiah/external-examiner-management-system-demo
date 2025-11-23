<?php
/**
 * PDF Export API Endpoint
 * Handles PDF export requests with authentication
 */

session_start();
require_once '../config/db.php';
require_once '../includes/pdf_export.php';
require_once '../includes/security.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$export_type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'pdf';

if ($format !== 'pdf') {
    http_response_code(400);
    echo json_encode(['error' => 'Only PDF format is supported']);
    exit;
}

try {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $userPost = $_SESSION['post'] ?? '';
    
    // Fetch data based on export type
    switch ($export_type) {
        case 'users':
            // Only admin and VP can export users
            if (!in_array($userPost, ['admin', 'vice_principal'])) {
                throw new Exception('Permission denied');
            }
            
            $status = $_GET['status'] ?? '';
            $post = $_GET['post'] ?? '';
            
            $sql = "SELECT user_id, name, email, post, college_name, phone, status 
                    FROM users WHERE 1=1";
            $params = [];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            if ($post) {
                $sql .= " AND post = ?";
                $params[] = $post;
            }
            
            $sql .= " ORDER BY name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $filters = compact('status', 'post');
            exportToPDF('users', $data, $filters);
            break;
            
        case 'exams':
            $college = $_GET['college'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $sql = "SELECT e.*, 
                    COUNT(DISTINCT a.assignment_id) as faculty_count,
                    u.name as created_by_name
                    FROM exams e
                    LEFT JOIN assignments a ON e.exam_id = a.exam_id
                    LEFT JOIN users u ON e.created_by = u.user_id
                    WHERE 1=1";
            $params = [];
            
            // Filter by user role
            if ($userPost === 'hod') {
                $sql .= " AND e.department = (SELECT college_name FROM users WHERE user_id = ?)";
                $params[] = $userId;
            } elseif ($userPost === 'teacher') {
                $sql .= " AND e.exam_id IN (SELECT exam_id FROM assignments WHERE faculty_id = ?)";
                $params[] = $userId;
            }
            
            if ($college) {
                $sql .= " AND e.department = ?";
                $params[] = $college;
            }
            if ($status) {
                $sql .= " AND e.status = ?";
                $params[] = $status;
            }
            
            $sql .= " GROUP BY e.exam_id ORDER BY e.exam_date DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $filters = compact('college', 'status');
            exportToPDF('exams', $data, $filters);
            break;
            
        case 'workload':
            // Faculty workload report
            if (!in_array($userPost, ['admin', 'vice_principal', 'hod'])) {
                throw new Exception('Permission denied');
            }
            
            $college = $_GET['college'] ?? '';
            
            $sql = "SELECT 
                    u.user_id,
                    u.name as faculty_name,
                    u.college_name,
                    COUNT(DISTINCT a.assignment_id) as assignment_count,
                    SUM(CASE WHEN e.exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_assignments,
                    SUM(CASE WHEN e.exam_date < CURDATE() THEN 1 ELSE 0 END) as past_assignments
                    FROM users u
                    LEFT JOIN assignments a ON u.user_id = a.faculty_id
                    LEFT JOIN exams e ON a.exam_id = e.exam_id
                    WHERE u.post = 'teacher'";
            
            $params = [];
            if ($college) {
                $sql .= " AND u.college_name = ?";
                $params[] = $college;
            } elseif ($userPost === 'hod') {
                $sql .= " AND u.college_name = (SELECT college_name FROM users WHERE user_id = ?)";
                $params[] = $userId;
            }
            
            $sql .= " GROUP BY u.user_id ORDER BY assignment_count DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $filters = compact('college');
            exportToPDF('workload', $data, $filters);
            break;
            
        case 'analytics':
            // System analytics (admin/VP only)
            if (!in_array($userPost, ['admin', 'vice_principal'])) {
                throw new Exception('Permission denied');
            }
            
            // Gather analytics data
            $analytics = [];
            
            // Total users
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Verified'");
            $analytics['total_users'] = $stmt->fetchColumn();
            
            // Active exams
            $stmt = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'Approved' AND exam_date >= CURDATE()");
            $analytics['active_exams'] = $stmt->fetchColumn();
            
            // Pending approvals
            $stmt = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'Pending'");
            $analytics['pending_approvals'] = $stmt->fetchColumn();
            
            // Total assignments
            $stmt = $pdo->query("SELECT COUNT(*) FROM assignments");
            $analytics['total_assignments'] = $stmt->fetchColumn();
            
            // College-wise stats
            $stmt = $pdo->query("
                SELECT 
                    college_name,
                    COUNT(DISTINCT e.exam_id) as total_exams,
                    COUNT(DISTINCT u.user_id) as total_faculty,
                    SUM(CASE WHEN e.status = 'Pending' THEN 1 ELSE 0 END) as pending_exams
                FROM users u
                LEFT JOIN exams e ON u.college_name = e.department
                WHERE u.post IN ('teacher', 'hod')
                GROUP BY college_name
                ORDER BY total_exams DESC
                LIMIT 10
            ");
            $analytics['college_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            exportToPDF('analytics', $analytics);
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
} catch (Exception $e) {
    error_log('PDF Export Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
