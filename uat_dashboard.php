<?php
/**
 * User Acceptance Testing (UAT) Feedback System
 * Collect and manage user feedback during testing phase
 */

require_once 'config/db.php';
session_start();

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$currentUser = $_SESSION['user_id'];
$currentRole = $_SESSION['role'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_feedback':
                $stmt = $db->prepare("
                    INSERT INTO uat_feedback (
                        user_id, category, title, description, 
                        severity, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'new', NOW())
                ");
                $stmt->execute([
                    $currentUser,
                    $_POST['category'],
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['severity']
                ]);
                $message = ['type' => 'success', 'text' => 'Feedback submitted successfully!'];
                break;
                
            case 'update_status':
                if ($currentRole === 'admin') {
                    $stmt = $db->prepare("
                        UPDATE uat_feedback 
                        SET status = ?, updated_at = NOW(), admin_notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['status'],
                        $_POST['admin_notes'] ?? '',
                        $_POST['feedback_id']
                    ]);
                    $message = ['type' => 'success', 'text' => 'Status updated!'];
                }
                break;
                
            case 'submit_bug':
                $stmt = $db->prepare("
                    INSERT INTO uat_bugs (
                        user_id, title, description, steps_to_reproduce,
                        expected_behavior, actual_behavior, severity,
                        priority, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
                ");
                $stmt->execute([
                    $currentUser,
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['steps'],
                    $_POST['expected'],
                    $_POST['actual'],
                    $_POST['severity'],
                    $_POST['priority']
                ]);
                $message = ['type' => 'success', 'text' => 'Bug report submitted!'];
                break;
        }
    }
}

// Get statistics
$stats = [];

// Total feedback
$stmt = $db->query("SELECT COUNT(*) as count FROM uat_feedback");
$stats['total_feedback'] = $stmt->fetch()['count'];

// Feedback by status
$stmt = $db->query("
    SELECT status, COUNT(*) as count 
    FROM uat_feedback 
    GROUP BY status
");
$stats['feedback_by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Total bugs
$stmt = $db->query("SELECT COUNT(*) as count FROM uat_bugs");
$stats['total_bugs'] = $stmt->fetch()['count'];

// Bugs by severity
$stmt = $db->query("
    SELECT severity, COUNT(*) as count 
    FROM uat_bugs 
    GROUP BY severity
");
$stats['bugs_by_severity'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent feedback
$stmt = $db->prepare("
    SELECT f.*, u.name as user_name, u.role
    FROM uat_feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_feedback = $stmt->fetchAll();

// Recent bugs
$stmt = $db->prepare("
    SELECT b.*, u.name as user_name, u.role
    FROM uat_bugs b
    JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_bugs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Acceptance Testing Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.new { border-left-color: #0dcaf0; }
        .stat-card.in-progress { border-left-color: #ffc107; }
        .stat-card.resolved { border-left-color: #28a745; }
        .stat-card.closed { border-left-color: #6c757d; }
        .stat-card.critical { border-left-color: #dc3545; }
        .stat-card.high { border-left-color: #fd7e14; }
        .stat-card.medium { border-left-color: #ffc107; }
        .stat-card.low { border-left-color: #0dcaf0; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php if (isset($message)): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="bi bi-clipboard-check"></i> User Acceptance Testing Dashboard</h1>
                <p class="text-muted">Help us improve the system by providing feedback and reporting bugs</p>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Total Feedback</h6>
                        <h2><?= $stats['total_feedback'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card new shadow">
                    <div class="card-body">
                        <h6 class="text-muted">New</h6>
                        <h2><?= $stats['feedback_by_status']['new'] ?? 0 ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card in-progress shadow">
                    <div class="card-body">
                        <h6 class="text-muted">In Progress</h6>
                        <h2><?= $stats['feedback_by_status']['in-progress'] ?? 0 ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card resolved shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Resolved</h6>
                        <h2><?= $stats['feedback_by_status']['resolved'] ?? 0 ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bug Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Total Bugs</h6>
                        <h2><?= $stats['total_bugs'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card critical shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Critical</h6>
                        <h2><?= $stats['bugs_by_severity']['critical'] ?? 0 ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card high shadow">
                    <div class="card-body">
                        <h6 class="text-muted">High Priority</h6>
                        <h2><?= $stats['bugs_by_severity']['high'] ?? 0 ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card medium shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Medium/Low</h6>
                        <h2><?= ($stats['bugs_by_severity']['medium'] ?? 0) + ($stats['bugs_by_severity']['low'] ?? 0) ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                    <i class="bi bi-chat-left-text"></i> Submit Feedback
                </button>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bugModal">
                    <i class="bi bi-bug"></i> Report Bug
                </button>
                <a href="uat_test_scenarios.php" class="btn btn-info">
                    <i class="bi bi-list-check"></i> View Test Scenarios
                </a>
                <?php if ($currentRole === 'admin'): ?>
                <a href="uat_analytics.php" class="btn btn-success">
                    <i class="bi bi-graph-up"></i> View Analytics
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="uatTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="feedback-tab" data-bs-toggle="tab" data-bs-target="#feedback" type="button">
                    Feedback
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="bugs-tab" data-bs-toggle="tab" data-bs-target="#bugs" type="button">
                    Bug Reports
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="uatTabContent">
            <!-- Feedback Tab -->
            <div class="tab-pane fade show active" id="feedback">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Feedback</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Title</th>
                                        <th>Submitted By</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <?php if ($currentRole === 'admin'): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_feedback as $feedback): ?>
                                    <tr>
                                        <td><?= $feedback['id'] ?></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($feedback['category']) ?></span></td>
                                        <td><?= htmlspecialchars($feedback['title']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($feedback['user_name']) ?>
                                            <small class="text-muted">(<?= $feedback['role'] ?>)</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $feedback['severity'] === 'critical' ? 'danger' :
                                                ($feedback['severity'] === 'high' ? 'warning' : 'secondary')
                                            ?>">
                                                <?= ucfirst($feedback['severity']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $feedback['status'] === 'new' ? 'info' :
                                                ($feedback['status'] === 'in-progress' ? 'warning' :
                                                ($feedback['status'] === 'resolved' ? 'success' : 'secondary'))
                                            ?>">
                                                <?= ucfirst(str_replace('-', ' ', $feedback['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($feedback['created_at'])) ?></td>
                                        <?php if ($currentRole === 'admin'): ?>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?= $feedback['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bugs Tab -->
            <div class="tab-pane fade" id="bugs">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Bug Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Reported By</th>
                                        <th>Severity</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <?php if ($currentRole === 'admin'): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bugs as $bug): ?>
                                    <tr>
                                        <td><?= $bug['id'] ?></td>
                                        <td><?= htmlspecialchars($bug['title']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($bug['user_name']) ?>
                                            <small class="text-muted">(<?= $bug['role'] ?>)</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $bug['severity'] === 'critical' ? 'danger' :
                                                ($bug['severity'] === 'high' ? 'warning' :
                                                ($bug['severity'] === 'medium' ? 'info' : 'secondary'))
                                            ?>">
                                                <?= ucfirst($bug['severity']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $bug['priority'] === 'urgent' ? 'danger' :
                                                ($bug['priority'] === 'high' ? 'warning' : 'secondary')
                                            ?>">
                                                <?= ucfirst($bug['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $bug['status'] === 'new' ? 'info' :
                                                ($bug['status'] === 'in-progress' ? 'warning' :
                                                ($bug['status'] === 'fixed' ? 'success' : 'secondary'))
                                            ?>">
                                                <?= ucfirst(str_replace('-', ' ', $bug['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($bug['created_at'])) ?></td>
                                        <?php if ($currentRole === 'admin'): ?>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewBug(<?= $bug['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_feedback">
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select category...</option>
                                <option value="usability">Usability</option>
                                <option value="performance">Performance</option>
                                <option value="functionality">Functionality</option>
                                <option value="design">Design/UI</option>
                                <option value="documentation">Documentation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="5" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select" required>
                                <option value="low">Low - Minor suggestion</option>
                                <option value="medium" selected>Medium - Improvement needed</option>
                                <option value="high">High - Significant issue</option>
                                <option value="critical">Critical - Blocks usage</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bug Report Modal -->
    <div class="modal fade" id="bugModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Bug</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_bug">
                        
                        <div class="mb-3">
                            <label class="form-label">Bug Title</label>
                            <input type="text" name="title" class="form-control" required 
                                   placeholder="Brief description of the bug">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required
                                      placeholder="Detailed description of what's wrong"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Steps to Reproduce</label>
                            <textarea name="steps" class="form-control" rows="4" required
                                      placeholder="1. Go to...&#10;2. Click on...&#10;3. See error"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Expected Behavior</label>
                            <textarea name="expected" class="form-control" rows="2" required
                                      placeholder="What should happen?"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Actual Behavior</label>
                            <textarea name="actual" class="form-control" rows="2" required
                                      placeholder="What actually happens?"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select" required>
                                    <option value="low">Low - Minor issue</option>
                                    <option value="medium" selected>Medium - Noticeable problem</option>
                                    <option value="high">High - Major functionality affected</option>
                                    <option value="critical">Critical - System broken</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select" required>
                                    <option value="low">Low - Can wait</option>
                                    <option value="medium" selected>Medium - Should fix soon</option>
                                    <option value="high">High - Fix ASAP</option>
                                    <option value="urgent">Urgent - Fix immediately</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Report Bug</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFeedback(id) {
            window.location.href = 'uat_feedback_detail.php?id=' + id;
        }
        
        function viewBug(id) {
            window.location.href = 'uat_bug_detail.php?id=' + id;
        }
    </script>
</body>
</html>
