<?php
/**
 * NOTIFICATIONS COMPONENT
 * ===================================
 * Reusable notification panel for all dashboards
 * Shows unread notifications with badge count
 */

// This file should be included in dashboards
// Usage: include __DIR__ . '/includes/notifications_panel.php';

if (!isset($pdo) || !isset($_SESSION['user_id'])) {
    return; // Silent fail if not properly included
}

$currentUserId = $_SESSION['user_id'];

// Fetch unread notifications
try {
    $notifications = getUnreadNotifications($pdo, $currentUserId);
    $unreadCount = count($notifications);
} catch (Exception $e) {
    error_log('Notifications panel error: ' . $e->getMessage());
    $notifications = [];
    $unreadCount = 0;
}

// Notification icon color based on count
$badgeColor = $unreadCount > 10 ? 'danger' : ($unreadCount > 0 ? 'warning' : 'secondary');
?>

<!-- Notification Dropdown -->
<div class="dropdown notification-dropdown">
    <button class="btn btn-outline-secondary position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-<?= $badgeColor ?>">
            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
            <span class="visually-hidden">unread notifications</span>
        </span>
        <?php endif; ?>
    </button>
    
    <ul class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationDropdown" style="width: 350px; max-height: 500px; overflow-y: auto;">
        <li class="dropdown-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bell me-2"></i>Notifications</span>
            <?php if ($unreadCount > 0): ?>
            <a href="#" onclick="markAllNotificationsRead(); return false;" class="badge bg-primary text-decoration-none">
                Mark all read
            </a>
            <?php endif; ?>
        </li>
        <li><hr class="dropdown-divider"></li>
        
        <?php if (empty($notifications)): ?>
        <li class="text-center py-4">
            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
            <p class="text-muted mt-2 mb-0">All caught up!</p>
            <small class="text-muted">No new notifications</small>
        </li>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <li>
                <a class="dropdown-item notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>" 
                   href="#" 
                   onclick="handleNotificationClick(<?= $notif['id'] ?>, '<?= htmlspecialchars($notif['link'] ?? '#', ENT_QUOTES) ?>'); return false;"
                   data-notification-id="<?= $notif['id'] ?>">
                    <div class="d-flex">
                        <div class="notification-icon me-2">
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
                        <div class="notification-content flex-grow-1">
                            <p class="mb-1 fw-semibold"><?= htmlspecialchars($notif['title']) ?></p>
                            <p class="mb-1 small text-muted"><?= htmlspecialchars($notif['message']) ?></p>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                <?= timeAgo($notif['created_at']) ?>
                            </small>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                        <div class="notification-badge">
                            <span class="badge bg-primary rounded-pill">New</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php endforeach; ?>
            
            <li class="text-center py-2">
                <a href="notifications.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-list-ul me-1"></i>View All Notifications
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>

<style>
.notification-menu {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.notification-item {
    padding: 12px 16px;
    transition: background-color 0.2s;
    border-left: 3px solid transparent;
}

.notification-item.unread {
    background-color: #f0f7ff;
    border-left-color: #0d6efd;
}

.notification-item:hover {
    background-color: #e9ecef;
}

.notification-icon {
    font-size: 1.5rem;
    min-width: 30px;
}

.notification-content p {
    line-height: 1.4;
}

.notification-badge {
    align-self: flex-start;
}

.dropdown-header {
    font-size: 1rem;
    font-weight: 600;
    padding: 12px 16px;
}
</style>

<script>
// Handle notification click
function handleNotificationClick(notificationId, link) {
    // Mark as read
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'mark_read',
            notification_id: notificationId,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    }).then(res => res.json())
      .then(data => {
          if (data.success) {
              // Update UI
              const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
              if (item) {
                  item.classList.remove('unread');
                  item.classList.add('read');
                  const badge = item.querySelector('.notification-badge');
                  if (badge) badge.remove();
              }
              
              // Update counter
              updateNotificationCount();
              
              // Navigate if link exists
              if (link && link !== '#') {
                  window.location.href = link;
              }
          }
      });
}

// Mark all notifications as read
function markAllNotificationsRead() {
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'mark_all_read',
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    }).then(res => res.json())
      .then(data => {
          if (data.success) {
              location.reload();
          }
      });
}

// Update notification count badge
function updateNotificationCount() {
    fetch('api/notifications.php?action=count')
        .then(res => res.json())
        .then(data => {
            const badge = document.querySelector('.notification-dropdown .badge');
            if (badge) {
                const count = data.count || 0;
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        });
}

// Auto-refresh notification count every 30 seconds
setInterval(updateNotificationCount, 30000);
</script>

<?php
/**
 * Helper function for time ago display
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>
