<?php
// API: HOD nominations: submit and list
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
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($token) && !empty($input['csrf_token'])) $token = $input['csrf_token'];
    if (!verify_csrf_token($token)) { http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token']); exit; }

    $name = trim($input['name'] ?? '');
    $roleTxt = trim($input['role'] ?? '');
    if ($name === '') { http_response_code(400); echo json_encode(['error'=>'Name required']); exit; }

    // determine dept from user
    try {
        $stmt = $pdo->prepare("SELECT dept FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $dept = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) { $dept = ''; }

    try {
        $stmt = $pdo->prepare("INSERT INTO examiner_nominations (dept, examiner_name, role, status, created_by, created_at) VALUES (:dept, :name, :role, 'pending', :uid, NOW())");
        $stmt->execute([':dept'=>$dept, ':name'=>$name, ':role'=>$roleTxt, ':uid'=>$_SESSION['user_id']]);
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error'=>'Failed to submit nomination']);
    }
    exit;
}

if ($method === 'GET') {
    // return recent nominations for this HOD's department
    try {
        $stmt = $pdo->prepare("SELECT dept FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $dept = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) { $dept = ''; }

    $stmt = $pdo->prepare("SELECT id, examiner_name, role, status, created_at FROM examiner_nominations WHERE dept = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$dept]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['data'=>$rows]);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
