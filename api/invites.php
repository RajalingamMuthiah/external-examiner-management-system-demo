<?php
/**
 * Invite API Endpoint
 * Handles exam invitation responses and queries
 * 
 * Endpoints:
 * POST /api/invites.php?action=respond - Respond to invite
 * GET /api/invites.php?action=get&token={token} - Get invite details
 * POST /api/invites.php?action=create - Create new invite (authenticated)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'respond':
            // Public endpoint - no auth required, uses token
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['success' => false, 'message' => 'Invalid method']);
            }
            
            $token = $_POST['token'] ?? '';
            $response = $_POST['response'] ?? '';
            $comment = $_POST['comment'] ?? '';
            $availability = $_POST['availability'] ?? null;
            
            if (empty($token)) {
                json_response(['success' => false, 'message' => 'Token required']);
            }
            
            if (!in_array($response, ['accepted', 'declined'])) {
                json_response(['success' => false, 'message' => 'Invalid response. Must be accepted or declined']);
            }
            
            $result = respondToInvite($pdo, $token, $response, $comment, $availability);
            json_response($result);
            break;
            
        case 'get':
            // Public endpoint - get invite details by token
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                json_response(['success' => false, 'message' => 'Token required']);
            }
            
            $stmt = $pdo->prepare("
                SELECT ei.*, 
                       e.title as exam_title, 
                       e.exam_date, 
                       e.start_time, 
                       e.end_time,
                       e.description
                FROM exam_invites ei 
                JOIN exams e ON ei.exam_id = e.id
                WHERE ei.token = ?
            ");
            $stmt->execute([$token]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($invite) {
                json_response(['success' => true, 'invite' => $invite]);
            } else {
                json_response(['success' => false, 'message' => 'Invalid token']);
            }
            break;
            
        case 'create':
            // Authenticated endpoint - create new invite
            start_secure_session();
            require_login();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['success' => false, 'message' => 'Invalid method']);
            }
            
            $examId = (int)($_POST['exam_id'] ?? 0);
            $inviteeIdentifier = $_POST['invitee'] ?? ''; // User ID or email
            $role = $_POST['role'] ?? '';
            $inviteeName = $_POST['invitee_name'] ?? null;
            $userId = $_SESSION['user_id'];
            
            if (empty($examId) || empty($inviteeIdentifier) || empty($role)) {
                json_response(['success' => false, 'message' => 'Missing required fields']);
            }
            
            $result = inviteExaminer($pdo, $examId, $inviteeIdentifier, $role, $userId, $inviteeName);
            json_response($result);
            break;
            
        case 'list':
            // Authenticated endpoint - list invites for exam
            start_secure_session();
            require_login();
            
            $examId = (int)($_GET['exam_id'] ?? 0);
            
            if (empty($examId)) {
                json_response(['success' => false, 'message' => 'Exam ID required']);
            }
            
            $stmt = $pdo->prepare("
                SELECT ei.*, u.name as invitee_name_user
                FROM exam_invites ei
                LEFT JOIN users u ON ei.invitee_user_id = u.id
                WHERE ei.exam_id = ?
                ORDER BY ei.invited_on DESC
            ");
            $stmt->execute([$examId]);
            $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            json_response(['success' => true, 'invites' => $invites]);
            break;
            
        default:
            json_response(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function json_response($data) {
    echo json_encode($data);
    exit;
}
?>
