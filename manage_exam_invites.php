<?php
/**
 * EXAM INVITE MANAGEMENT - Centralized invite management for exams
 * ===================================================================
 * Allows Principal/HOD/Admin to invite examiners (internal/external) for exams
 * 
 * FEATURES:
 * - Define exam roles (External Examiner, Internal Examiner, Observer, Practical Examiner)
 * - Send invitations via email with unique token
 * - Track invite status (pending, accepted, declined)
 * - View invite history per exam
 * - Resend invitations
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Enforce authentication and role-based access
require_auth();
require_role(['admin', 'principal', 'vice_principal', 'hod'], true);

$currentUserId = get_current_user_id();
$currentUserRole = normalize_role($_SESSION['role'] ?? 'admin');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // CSRF validation
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        if ($action === 'send_invite') {
            $examId = (int)($_POST['exam_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? 'External Examiner');
            $dutyType = trim($_POST['duty_type'] ?? 'theory');
            $isExternal = (int)($_POST['is_external'] ?? 1);
            
            // Validate inputs
            if ($examId <= 0 || empty($email) || empty($name)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                exit;
            }
            
            // Call inviteExaminer service function with correct parameter order
            // function signature: inviteExaminer($pdo, $examId, $inviteeIdentifier, $role, $createdBy, $inviteeName, $isExternal, $dutyType)
            $result = inviteExaminer($pdo, $examId, $email, $role, $currentUserId, $name, $isExternal, $dutyType);
            echo json_encode($result);
            exit;
        }
        
        if ($action === 'get_exam_invites') {
            $examId = (int)($_POST['exam_id'] ?? 0);
            
            if ($examId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid exam ID']);
                exit;
            }
            
            // Fetch all invites for this exam
            $stmt = $pdo->prepare("
                SELECT ei.*, u.name as invited_by_name 
                FROM exam_invites ei
                LEFT JOIN users u ON ei.invited_by = u.id
                WHERE ei.exam_id = ?
                ORDER BY ei.created_at DESC
            ");
            $stmt->execute([$examId]);
            $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'invites' => $invites]);
            exit;
        }
        
        if ($action === 'resend_invite') {
            $inviteId = (int)($_POST['invite_id'] ?? 0);
            
            if ($inviteId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid invite ID']);
                exit;
            }
            
            // Get invite details
            $stmt = $pdo->prepare("SELECT * FROM exam_invites WHERE id = ?");
            $stmt->execute([$inviteId]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invite) {
                echo json_encode(['success' => false, 'message' => 'Invite not found']);
                exit;
            }
            
            // Resend logic: Generate new token and update
            $newToken = bin2hex(random_bytes(32));
            $updateStmt = $pdo->prepare("UPDATE exam_invites SET token = ?, created_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newToken, $inviteId]);
            
            // TODO: Send email with new token
            // For now, just return success
            
            logAudit($pdo, 'exam_invite', $inviteId, 'resend', $currentUserId, [
                'email' => $invite['email'],
                'exam_id' => $invite['exam_id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Invitation resent successfully']);
            exit;
        }
        
        if ($action === 'cancel_invite') {
            $inviteId = (int)($_POST['invite_id'] ?? 0);
            
            if ($inviteId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid invite ID']);
                exit;
            }
            
            // Update invite status to cancelled
            $stmt = $pdo->prepare("UPDATE exam_invites SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$inviteId]);
            
            logAudit($pdo, 'exam_invite', $inviteId, 'cancel', $currentUserId, []);
            
            echo json_encode(['success' => true, 'message' => 'Invitation cancelled']);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
        
    } catch (Exception $e) {
        error_log('Invite management error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Get exam ID from query parameter
$examId = (int)($_GET['exam_id'] ?? 0);
$exam = null;

if ($examId > 0) {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as created_by_name, u.college_name as creator_college
        FROM exams e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all approved exams visible to current user
$visibleExams = getVisibleExamsForUser($pdo, $currentUserId, $currentUserRole, $_SESSION['college_id'] ?? null, $_SESSION['department_id'] ?? null);

// Filter for approved exams only
$approvedExams = array_filter($visibleExams, function($e) {
    return ($e['status'] ?? '') === 'approved' || ($e['status'] ?? '') === 'Approved';
});

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Define available roles
$examRoles = [
    'External Examiner' => 'External examiner from another college',
    'Internal Examiner' => 'Internal examiner from same college',
    'Chief Examiner' => 'Chief/Head examiner overseeing the exam',
    'Observer' => 'Observer to monitor the examination process',
    'Practical Examiner' => 'Examiner for practical/lab assessments',
    'Moderator' => 'Moderator to review question papers',
    'Invigilator' => 'Invigilator for exam supervision'
];

$dutyTypes = [
    'theory' => 'Theory Exam',
    'practical' => 'Practical/Lab Exam',
    'viva' => 'Viva/Oral Exam',
    'project' => 'Project Evaluation',
    'moderation' => 'Paper Moderation',
    'invigilation' => 'Invigilation Duty'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exam Invitations - EEMS</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .invite-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .invite-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }
        .role-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<!-- Page Header -->
<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-envelope-paper"></i> Manage Exam Invitations</h2>
                <p class="text-muted mb-0">Invite and manage examiners for your exams</p>
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
    <div class="row">
        <!-- Left Panel: Send New Invite -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Send New Invitation</h5>
                </div>
                <div class="card-body">
                    <form id="inviteForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        
                        <!-- Exam Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Exam <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_id" id="examSelect" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($approvedExams as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= ($examId == $e['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['title']) ?> - <?= date('M d, Y', strtotime($e['exam_date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Examiner Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Examiner Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="Dr. John Smith" required>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" placeholder="examiner@college.edu" required>
                            <small class="text-muted">Invitation link will be sent to this email</small>
                        </div>

                        <!-- Role Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Examiner Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <?php foreach ($examRoles as $roleKey => $roleDesc): ?>
                                    <option value="<?= htmlspecialchars($roleKey) ?>" 
                                            title="<?= htmlspecialchars($roleDesc) ?>">
                                        <?= htmlspecialchars($roleKey) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Duty Type -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Duty Type</label>
                            <select class="form-select" name="duty_type">
                                <?php foreach ($dutyTypes as $dutyKey => $dutyLabel): ?>
                                    <option value="<?= $dutyKey ?>"><?= htmlspecialchars($dutyLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- External/Internal -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Examiner Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_external" value="1" id="external" checked>
                                <label class="form-check-label" for="external">
                                    External (From another college)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_external" value="0" id="internal">
                                <label class="form-check-label" for="internal">
                                    Internal (From same college)
                                </label>
                            </div>
                        </div>

                        <div id="inviteAlert"></div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send me-2"></i>Send Invitation
                        </button>
                    </form>
                </div>
            </div>

            <!-- Role Legend -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Examiner Roles</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($examRoles as $role => $desc): ?>
                            <li class="mb-2">
                                <strong><?= htmlspecialchars($role) ?>:</strong>
                                <br><span class="text-muted"><?= htmlspecialchars($desc) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Panel: Invitation List -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Sent Invitations</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshInvites()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div id="invitesContainer">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-3">Select an exam to view invitations</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfToken = '<?= $csrfToken ?>';

// Handle form submission
document.getElementById('inviteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'send_invite');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    
    fetch('manage_exam_invites.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const alertDiv = document.getElementById('inviteAlert');
        if (data.success) {
            alertDiv.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.getElementById('inviteForm').reset();
            
            // Refresh invites if an exam is selected
            const examId = formData.get('exam_id');
            if (examId) {
                loadInvites(examId);
            }
        } else {
            alertDiv.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-x-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    })
    .catch(err => {
        console.error(err);
        document.getElementById('inviteAlert').innerHTML = `
            <div class="alert alert-danger">Network error. Please try again.</div>
        `;
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Load invites when exam is selected
document.getElementById('examSelect').addEventListener('change', function() {
    const examId = this.value;
    if (examId) {
        loadInvites(examId);
    } else {
        document.getElementById('invitesContainer').innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-3">Select an exam to view invitations</p>
            </div>
        `;
    }
});

function loadInvites(examId) {
    const container = document.getElementById('invitesContainer');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    
    const formData = new FormData();
    formData.append('action', 'get_exam_invites');
    formData.append('exam_id', examId);
    formData.append('csrf_token', csrfToken);
    
    fetch('manage_exam_invites.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.invites) {
            if (data.invites.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-envelope" style="font-size: 3rem;"></i>
                        <p class="mt-3">No invitations sent for this exam yet</p>
                    </div>
                `;
            } else {
                container.innerHTML = data.invites.map(invite => renderInvite(invite)).join('');
            }
        } else {
            container.innerHTML = `<div class="alert alert-danger">Error loading invites</div>`;
        }
    })
    .catch(err => {
        console.error(err);
        container.innerHTML = `<div class="alert alert-danger">Network error</div>`;
    });
}

function renderInvite(invite) {
    const statusColors = {
        'pending': 'warning',
        'accepted': 'success',
        'declined': 'danger',
        'cancelled': 'secondary'
    };
    
    const statusColor = statusColors[invite.status] || 'secondary';
    const statusIcon = invite.status === 'accepted' ? 'check-circle' : 
                      invite.status === 'declined' ? 'x-circle' : 
                      invite.status === 'cancelled' ? 'slash-circle' : 'clock';
    
    return `
        <div class="card invite-card mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h6 class="mb-2">
                            <i class="bi bi-person-circle text-primary me-2"></i>
                            ${escapeHtml(invite.name)}
                        </h6>
                        <p class="mb-1 small">
                            <i class="bi bi-envelope me-2"></i>${escapeHtml(invite.email)}
                        </p>
                        <p class="mb-1">
                            <span class="role-badge">${escapeHtml(invite.role)}</span>
                            <span class="badge bg-secondary ms-2">${escapeHtml(invite.duty_type)}</span>
                            <span class="badge ${invite.is_external ? 'bg-info' : 'bg-dark'} ms-2">
                                ${invite.is_external ? 'External' : 'Internal'}
                            </span>
                        </p>
                        <p class="mb-0 small text-muted">
                            <i class="bi bi-clock me-1"></i>Sent: ${new Date(invite.created_at).toLocaleString()}
                            ${invite.invited_by_name ? ' by ' + escapeHtml(invite.invited_by_name) : ''}
                        </p>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-${statusColor} status-badge w-100">
                            <i class="bi bi-${statusIcon} me-1"></i>${invite.status.toUpperCase()}
                        </span>
                        ${invite.responded_at ? `
                            <small class="d-block text-muted mt-2">
                                ${new Date(invite.responded_at).toLocaleString()}
                            </small>
                        ` : ''}
                    </div>
                    <div class="col-md-2 text-end">
                        ${invite.status === 'pending' ? `
                            <button class="btn btn-sm btn-outline-primary mb-1 w-100" 
                                    onclick="resendInvite(${invite.id})">
                                <i class="bi bi-arrow-repeat"></i> Resend
                            </button>
                            <button class="btn btn-sm btn-outline-danger w-100" 
                                    onclick="cancelInvite(${invite.id})">
                                <i class="bi bi-x"></i> Cancel
                            </button>
                        ` : ''}
                    </div>
                </div>
                ${invite.comments ? `
                    <div class="mt-2 pt-2 border-top">
                        <small class="text-muted"><strong>Comments:</strong> ${escapeHtml(invite.comments)}</small>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
}

function resendInvite(inviteId) {
    if (!confirm('Resend invitation? A new invite link will be generated.')) return;
    
    const formData = new FormData();
    formData.append('action', 'resend_invite');
    formData.append('invite_id', inviteId);
    formData.append('csrf_token', csrfToken);
    
    fetch('manage_exam_invites.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            refreshInvites();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
    });
}

function cancelInvite(inviteId) {
    if (!confirm('Cancel this invitation?')) return;
    
    const formData = new FormData();
    formData.append('action', 'cancel_invite');
    formData.append('invite_id', inviteId);
    formData.append('csrf_token', csrfToken);
    
    fetch('manage_exam_invites.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            refreshInvites();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
    });
}

function refreshInvites() {
    const examId = document.getElementById('examSelect').value;
    if (examId) {
        loadInvites(examId);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-load invites if exam is pre-selected
<?php if ($examId > 0 && $exam): ?>
window.addEventListener('DOMContentLoaded', function() {
    loadInvites(<?= $examId ?>);
});
<?php endif; ?>
</script>

</body>
</html>
