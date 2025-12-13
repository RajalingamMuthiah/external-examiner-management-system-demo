<?php
/**
 * NOTIFICATIONS PAGE
 * ===================================
 * View all notifications with filtering and bulk actions
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();

$currentUserId = get_current_user_id();
$currentUserRole = normalize_role($_SESSION['role'] ?? 'teacher');

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $notificationIds = $_POST['notification_ids'] ?? [];
    
    if (!empty($notificationIds) && is_array($notificationIds)) {
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        $params = array_merge($notificationIds, [$currentUserId]);
        
        if ($action === 'mark_read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id IN ($placeholders) AND user_id = ?");
            $stmt->execute($params);
            $_SESSION['flash_message'] = 'Selected notifications marked as read';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_deleted = 1 WHERE id IN ($placeholders) AND user_id = ?");
            $stmt->execute($params);
            $_SESSION['flash_message'] = 'Selected notifications deleted';
        }
        
        header('Location: notifications.php');
        exit;
    }
}

// Fetch notifications with filters
$filter = $_GET['filter'] ?? 'all'; // all, unread, read
$type = $_GET['type'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$whereConditions = ["user_id = ?", "is_deleted = 0"];
$params = [$currentUserId];

if ($filter === 'unread') {
    $whereConditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $whereConditions[] = "is_read = 1";
}

if ($type !== 'all') {
    $whereConditions[] = "type = ?";
    $params[] = $type;
}

$whereClause = implode(' AND ', $whereConditions);

// Get notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE $whereClause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE $whereClause");
$stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

// Get notification types for filter
$stmt = $pdo->prepare("SELECT DISTINCT type FROM notifications WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$currentUserId]);
$availableTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - EEMS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .page-header { background: white; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .notification-card { background: white; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; transition: all 0.2s; border-left: 4px solid transparent; }
        .notification-card.unread { background: #f0f7ff; border-left-color: #0d6efd; }
        .notification-card:hover { transform: translateX(5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .notification-icon { font-size: 2rem; min-width: 50px; }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-bell-fill me-2"></i>Notifications</h2>
                <p class="text-muted mb-0">Manage your system notifications</p>
            </div>
            <div>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_message']); endif; ?>
    
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Filters</h6>
                </div>
                <div class="card-body">
                    <h6>Status</h6>
                    <div class="list-group mb-3">
                        <a href="?filter=all&type=<?= $type ?>" class="list-group-item list-group-item-action <?= $filter === 'all' ? 'active' : '' ?>">
                            All Notifications
                        </a>
                        <a href="?filter=unread&type=<?= $type ?>" class="list-group-item list-group-item-action <?= $filter === 'unread' ? 'active' : '' ?>">
                            <i class="bi bi-circle-fill text-primary me-2"></i>Unread
                        </a>
                        <a href="?filter=read&type=<?= $type ?>" class="list-group-item list-group-item-action <?= $filter === 'read' ? 'active' : '' ?>">
                            <i class="bi bi-check-circle me-2"></i>Read
                        </a>
                    </div>
                    
                    <?php if (!empty($availableTypes)): ?>
                    <h6>Type</h6>
                    <div class="list-group">
                        <a href="?filter=<?= $filter ?>&type=all" class="list-group-item list-group-item-action <?= $type === 'all' ? 'active' : '' ?>">
                            All Types
                        </a>
                        <?php foreach ($availableTypes as $availableType): ?>
                        <a href="?filter=<?= $filter ?>&type=<?= $availableType ?>" class="list-group-item list-group-item-action <?= $type === $availableType ? 'active' : '' ?>">
                            <?= ucwords(str_replace('_', ' ', $availableType)) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <form method="post" id="bulkForm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="button" onclick="selectAll()" class="btn btn-sm btn-outline-secondary">Select All</button>
                                <button type="button" onclick="deselectAll()" class="btn btn-sm btn-outline-secondary">Deselect All</button>
                            </div>
                            <div class="btn-group">
                                <button type="submit" name="bulk_action" value="mark_read" class="btn btn-sm btn-primary" onclick="return confirm('Mark selected as read?')">
                                    <i class="bi bi-check"></i> Mark Read
                                </button>
                                <button type="submit" name="bulk_action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete selected notifications?')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        
                        <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">No notifications found</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notification-card <?= $notif['is_read'] ? 'read' : 'unread' ?>">
                                <div class="d-flex align-items-start">
                                    <div class="form-check me-3">
                                        <input class="form-check-input notif-checkbox" type="checkbox" name="notification_ids[]" value="<?= $notif['id'] ?>">
                                    </div>
                                    <div class="notification-icon text-center me-3">
                                        <?php
                                        $iconClass = match($notif['type']) {
                                            'exam_assigned' => 'bi-calendar-check text-primary',
                                            'exam_approved' => 'bi-check-circle text-success',
                                            'exam_rejected' => 'bi-x-circle text-danger',
                                            'invite_received' => 'bi-envelope text-info',
                                            'invite_accepted' => 'bi-check2-circle text-success',
                                            'invite_declined' => 'bi-dash-circle text-warning',
                                            'rating_received' => 'bi-star-fill text-warning',
                                            'document_ready' => 'bi-file-earmark-pdf text-danger',
                                            default => 'bi-info-circle text-secondary'
                                        };
                                        ?>
                                        <i class="bi <?= $iconClass ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($notif['title']) ?></h6>
                                        <p class="mb-2 text-muted"><?= htmlspecialchars($notif['message']) ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i><?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                                        </small>
                                        <?php if ($notif['link']): ?>
                                        <a href="<?= htmlspecialchars($notif['link']) ?>" class="btn btn-sm btn-outline-primary ms-2">
                                            View <i class="bi bi-arrow-right"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                    <span class="badge bg-primary">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </form>
                    
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?filter=<?= $filter ?>&type=<?= $type ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectAll() {
    document.querySelectorAll('.notif-checkbox').forEach(cb => cb.checked = true);
}

function deselectAll() {
    document.querySelectorAll('.notif-checkbox').forEach(cb => cb.checked = false);
}
</script>

</body>
</html>
