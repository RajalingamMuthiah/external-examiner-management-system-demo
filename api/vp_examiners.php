<?php
// VP API: returns list of examiners (supports q and dept filters)
// Secure: session & role check. PDO prepared statements.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['vp','vice_principal','admin'])) {
    http_response_code(403);
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php'; // provides $pdo

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$dept = isset($_GET['dept']) ? trim($_GET['dept']) : '';

$sql = "SELECT id, name, expertise, dept, availability, past_assignments AS past, status FROM external_examiners WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (name LIKE :q OR expertise LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($dept !== '') {
    $sql .= " AND dept = :dept";
    $params[':dept'] = $dept;
}
$sql .= " ORDER BY availability ASC, name ASC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode(['data'=>$rows]);
