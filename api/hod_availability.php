<?php
// API: HOD availability actions
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['hod','head','hod_incharge','admin'])) {
    http_response_code(403);
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    // CSRF verification
    require_once __DIR__ . '/../includes/functions.php';
    $token = null;
    // prefer header
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($token) && !empty($input['csrf_token'])) $token = $input['csrf_token'];
    if (!verify_csrf_token($token)) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token']); exit; }

    $input = $input;
    $faculty_id = isset($input['faculty_id']) ? (int)$input['faculty_id'] : 0;
    $date = $input['date'] ?? '';
    if (!$faculty_id || !$date) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid input']);
        exit;
    }
    // basic date validation
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid date']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO faculty_availability (faculty_id, unavailable_date, created_by) VALUES (:fid, :dt, :uid)");
        $stmt->execute([':fid'=>$faculty_id, ':dt'=>$date, ':uid'=>$_SESSION['user_id']]);
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error'=>'Failed to save availability']);
    }
    exit;
}

// GET: optional ?faculty_id= to fetch unavailable dates
if ($method === 'GET') {
    $fid = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
    if (!$fid) { echo json_encode(['data'=>[]]); exit; }
    $stmt = $pdo->prepare("SELECT unavailable_date FROM faculty_availability WHERE faculty_id = ? ORDER BY unavailable_date ASC");
    $stmt->execute([$fid]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    echo json_encode(['data'=>$rows]);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
