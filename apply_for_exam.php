<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

// Validate Exam ID from Request
$examId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
if (!$examId) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid exam ID.']);
    exit;
}

// Prevent CSRF attacks
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'CSRF token invalid.']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Check if the user has already applied
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM examiner_applications WHERE exam_id = :examId AND user_id = :userId");
    $stmtCheck->execute(['examId' => $examId, 'userId' => $userId]);

    if ($stmtCheck->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'You have already applied for this exam.']);
        exit;
    }

    // Apply for the exam
    $sql = "INSERT INTO examiner_applications (exam_id, user_id, status) VALUES (:examId, :userId, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['examId' => $examId, 'userId' => $userId]);

    header('Content-Type: application/json');
    echo json_encode(['success' => 'Application submitted successfully.']);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    error_log("apply_for_exam.php error: " . $e->getMessage());
    exit;
}
?>