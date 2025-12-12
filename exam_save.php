<?php
require_once 'includes/exam_functions.php';
require_once 'config/db.php';
header('Content-Type: application/json');
$data = $_POST;
$id = (int)($data['id'] ?? 0);
unset($data['id']);
if ($id) {
    $success = update_exam($pdo, $id, $data);
    echo json_encode(['success' => $success, 'message' => $success ? 'Exam updated.' : 'Update failed.']);
} else {
    $success = create_exam($pdo, $data);
    echo json_encode(['success' => $success, 'message' => $success ? 'Exam created.' : 'Create failed.']);
}
