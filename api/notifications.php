<?php
/**
 * NOTIFICATIONS API
 * ===================================
 * AJAX endpoint for notification operations
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$currentUserId = get_current_user_id();

// Handle GET requests (fetch notifications)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    try {
        if ($action === 'count') {
            // Get unread count only
            $notifications = getUnreadNotifications($pdo, $currentUserId);
            echo json_encode(['success' => true, 'count' => count($notifications)]);
            exit;
        }
        
        if ($action === 'list') {
            // Get all notifications (paginated)
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT * FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$currentUserId, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
            $stmt->execute([$currentUserId]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        error_log('Notifications API error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
}

// Handle POST requests (mark read, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        if ($action === 'mark_read') {
            $notificationId = (int)($input['notification_id'] ?? 0);
            
            if ($notificationId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                exit;
            }
            
            // Use service layer function
            $result = markNotificationRead($pdo, $notificationId, $currentUserId);
            echo json_encode($result);
            exit;
        }
        
        if ($action === 'mark_all_read') {
            // Mark all notifications as read for current user
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$currentUserId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read',
                'updated' => $stmt->rowCount()
            ]);
            exit;
        }
        
        if ($action === 'delete') {
            $notificationId = (int)($input['notification_id'] ?? 0);
            
            if ($notificationId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
                exit;
            }
            
            // Soft delete notification (set deleted flag)
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_deleted = 1 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $currentUserId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Notification not found']);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
        
    } catch (Exception $e) {
        error_log('Notifications API error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
