<?php
// VP API: Approve / Reject requests (POST) and list requests (GET).
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['vp','vice_principal','admin'])) {
    http_response_code(403);
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT id, hod_name, examiner_name, purpose, status, created_at FROM examiner_requests ORDER BY created_at DESC LIMIT 100");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['data'=>$data]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $action = isset($input['action']) ? $input['action'] : '';

    if (!$id || !in_array($action, ['approve','reject'])) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid input']);
        exit;
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("UPDATE examiner_requests SET status = :status, handled_by = :uid, handled_at = NOW() WHERE id = :id");
    $stmt->execute([':status'=>$newStatus, ':uid'=>$_SESSION['user_id'], ':id'=>$id]);

    echo json_encode(['success'=>true, 'status'=>$newStatus]);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed']);
