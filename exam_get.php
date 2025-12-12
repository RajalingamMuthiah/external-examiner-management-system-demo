<?php
require_once 'includes/exam_functions.php';
require_once 'config/db.php';
header('Content-Type: application/json');
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM exams WHERE id=?');
    $stmt->execute([$id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($exam ?: []);
} else {
    echo json_encode([]);
}
