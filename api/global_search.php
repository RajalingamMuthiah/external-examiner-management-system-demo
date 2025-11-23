<?php
/**
 * Global Search API
 * Searches across exams, users, and assignments
 */

session_start();
require_once '../config/db.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([
        'exams' => [],
        'users' => [],
        'assignments' => []
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $userPost = $_SESSION['post'] ?? '';
    
    $searchTerm = '%' . $query . '%';
    $results = [
        'exams' => [],
        'users' => [],
        'assignments' => []
    ];
    
    // Search Exams
    $examSql = "SELECT exam_id, exam_name, title, subject, exam_date, status, department, college_name
                FROM exams 
                WHERE (exam_name LIKE ? OR title LIKE ? OR subject LIKE ? OR description LIKE ?)";
    
    $examParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    // Filter by role
    if ($userPost === 'hod') {
        $examSql .= " AND department = (SELECT college_name FROM users WHERE user_id = ?)";
        $examParams[] = $userId;
    } elseif ($userPost === 'teacher') {
        $examSql .= " AND exam_id IN (SELECT exam_id FROM assignments WHERE faculty_id = ?)";
        $examParams[] = $userId;
    }
    
    $examSql .= " ORDER BY exam_date DESC LIMIT 5";
    
    $stmt = $pdo->prepare($examSql);
    $stmt->execute($examParams);
    $results['exams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search Users (admin/VP only)
    if (in_array($userPost, ['admin', 'vice_principal'])) {
        $userSql = "SELECT user_id, name, email, post, college_name, phone
                    FROM users 
                    WHERE (name LIKE ? OR email LIKE ? OR college_name LIKE ?)
                    AND status = 'Verified'
                    ORDER BY name 
                    LIMIT 5";
        
        $stmt = $pdo->prepare($userSql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($userPost === 'hod') {
        // HOD can search faculty in their college
        $userSql = "SELECT user_id, name, email, post, college_name, phone
                    FROM users 
                    WHERE (name LIKE ? OR email LIKE ?)
                    AND college_name = (SELECT college_name FROM users WHERE user_id = ?)
                    AND post = 'teacher'
                    AND status = 'Verified'
                    ORDER BY name 
                    LIMIT 5";
        
        $stmt = $pdo->prepare($userSql);
        $stmt->execute([$searchTerm, $searchTerm, $userId]);
        $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Search Assignments
    $assignmentSql = "SELECT 
                        a.assignment_id,
                        a.exam_id,
                        e.exam_name,
                        e.title,
                        e.exam_date,
                        u.name as faculty_name,
                        e.department
                      FROM assignments a
                      JOIN exams e ON a.exam_id = e.exam_id
                      JOIN users u ON a.faculty_id = u.user_id
                      WHERE (e.exam_name LIKE ? OR e.title LIKE ? OR u.name LIKE ?)";
    
    $assignmentParams = [$searchTerm, $searchTerm, $searchTerm];
    
    // Filter by role
    if ($userPost === 'hod') {
        $assignmentSql .= " AND e.department = (SELECT college_name FROM users WHERE user_id = ?)";
        $assignmentParams[] = $userId;
    } elseif ($userPost === 'teacher') {
        $assignmentSql .= " AND a.faculty_id = ?";
        $assignmentParams[] = $userId;
    }
    
    $assignmentSql .= " ORDER BY e.exam_date DESC LIMIT 5";
    
    $stmt = $pdo->prepare($assignmentSql);
    $stmt->execute($assignmentParams);
    $results['assignments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log search for analytics
    $logSql = "INSERT INTO search_logs (user_id, search_query, results_count, searched_at) 
               VALUES (?, ?, ?, NOW())";
    $totalResults = count($results['exams']) + count($results['users']) + count($results['assignments']);
    
    try {
        $stmt = $pdo->prepare($logSql);
        $stmt->execute([$userId, $query, $totalResults]);
    } catch (PDOException $e) {
        // Table might not exist yet, ignore logging error
        error_log('Search log error: ' . $e->getMessage());
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
