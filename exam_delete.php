<?php
require_once 'includes/exam_functions.php';
require_once 'config/db.php';
header('Content-Type: application/json');
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $success = delete_exam($pdo, $id);
    echo json_encode(['success' => $success, 'message' => $success ? 'Exam deleted.' : 'Delete failed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid exam ID.']);
}
