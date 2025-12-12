<?php
/**
 * Public Invite Response Page
 * Allows external examiners to accept/decline exam invitations via token link
 * No login required - token-based authentication
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$invite = null;
$exam = null;

// Get invite details if token provided
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT ei.*, 
                   e.title as exam_title, 
                   e.course_code,
                   e.exam_date, 
                   e.start_time, 
                   e.end_time,
                   e.description,
                   c.college_name,
                   d.dept_name
            FROM exam_invites ei 
            JOIN exams e ON ei.exam_id = e.id
            LEFT JOIN colleges c ON e.college_id = c.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE ei.token = ?
        ");
        $stmt->execute([$token]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $response = $_POST['response'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    $availability = $_POST['availability'] ?? null;
    
    if (in_array($response, ['accepted', 'declined'])) {
        $result = respondToInvite($pdo, $token, $response, $comment, $availability);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
            // Reload invite to show updated status
            header('Location: invite_response.php?token=' . $token . '&success=1');
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } else {
        $message = 'Please select accept or decline';
        $messageType = 'warning';
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $message = 'Thank you! Your response has been recorded.';
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Invitation Response - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .invite-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 700px;
            margin: 0 auto;
        }
        .exam-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .btn-accept {
            background: #28a745;
            color: white;
        }
        .btn-decline {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="invite-card p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-envelope-check text-primary" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Exam Invitation</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$token): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Invalid or missing invitation token. Please check your invitation link.
                </div>
            <?php elseif (!$invite): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i>
                    This invitation link is invalid or has expired.
                </div>
            <?php elseif ($invite['status'] !== 'pending'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    You have already responded to this invitation.
                    <br><br>
                    <strong>Your response:</strong> 
                    <span class="badge bg-<?= $invite['status'] === 'accepted' ? 'success' : 'danger' ?>">
                        <?= ucfirst($invite['status']) ?>
                    </span>
                    <?php if ($invite['response_comment']): ?>
                        <br><br>
                        <strong>Your comment:</strong> <?= htmlspecialchars($invite['response_comment']) ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Display exam details -->
                <div class="exam-details">
                    <h4 class="mb-3"><i class="bi bi-file-text"></i> Exam Details</h4>
                    
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Exam Title:</div>
                        <div class="col-md-8"><?= htmlspecialchars($invite['exam_title']) ?></div>
                    </div>
                    
                    <?php if ($invite['course_code']): ?>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Course Code:</div>
                        <div class="col-md-8"><?= htmlspecialchars($invite['course_code']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Your Role:</div>
                        <div class="col-md-8">
                            <span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $invite['role'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">College:</div>
                        <div class="col-md-8"><?= htmlspecialchars($invite['college_name'] ?? 'N/A') ?></div>
                    </div>
                    
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Department:</div>
                        <div class="col-md-8"><?= htmlspecialchars($invite['dept_name'] ?? 'N/A') ?></div>
                    </div>
                    
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Exam Date:</div>
                        <div class="col-md-8">
                            <i class="bi bi-calendar-event"></i>
                            <?= date('l, F j, Y', strtotime($invite['exam_date'])) ?>
                        </div>
                    </div>
                    
                    <?php if ($invite['start_time'] && $invite['end_time']): ?>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Time:</div>
                        <div class="col-md-8">
                            <i class="bi bi-clock"></i>
                            <?= date('g:i A', strtotime($invite['start_time'])) ?> - 
                            <?= date('g:i A', strtotime($invite['end_time'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($invite['description']): ?>
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Description:</div>
                        <div class="col-md-8"><?= nl2br(htmlspecialchars($invite['description'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-2">
                        <div class="col-md-4 fw-bold">Invited On:</div>
                        <div class="col-md-8"><?= date('F j, Y g:i A', strtotime($invite['invited_on'])) ?></div>
                    </div>
                </div>

                <!-- Response form -->
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Your Response</label>
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" name="response" value="accepted" class="btn btn-accept btn-lg flex-fill">
                                <i class="bi bi-check-circle"></i> Accept Invitation
                            </button>
                            <button type="submit" name="response" value="declined" class="btn btn-decline btn-lg flex-fill">
                                <i class="bi bi-x-circle"></i> Decline Invitation
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="comment" class="form-label">Comments (Optional)</label>
                        <textarea 
                            class="form-control" 
                            id="comment" 
                            name="comment" 
                            rows="3"
                            placeholder="Add any comments or questions..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="availability" class="form-label">Your Availability (Optional)</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="availability" 
                            name="availability"
                            placeholder="E.g., Available all day, Morning only, etc.">
                        <small class="text-muted">Let us know your availability preferences</small>
                    </div>
                </form>

                <div class="alert alert-info mt-4">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note:</strong> Once you respond, you will receive a confirmation email with further details.
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <small class="text-muted">
                    External Examiner Management System (EEMS)
                    <br>
                    &copy; <?= date('Y') ?> All Rights Reserved
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
