<?php
require_once 'includes/exam_functions.php';
require_once 'config/db.php';
header('Content-Type: application/json');
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $success = approve_exam($pdo, $id);
    echo json_encode(['success' => $success, 'message' => $success ? 'Exam approved.' : 'Approve failed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid exam ID.']);
}
