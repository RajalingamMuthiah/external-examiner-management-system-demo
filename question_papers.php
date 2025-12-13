<?php
/**
 * QUESTION PAPER MANAGEMENT
 * ===================================
 * Upload, manage, lock/unlock exam question papers
 * Principal-only locking for security
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();
require_role(['admin', 'principal', 'vice_principal', 'hod', 'teacher'], true);

$currentUserId = get_current_user_id();
$currentUserRole = normalize_role($_SESSION['role'] ?? 'teacher');
$examId = (int)($_GET['exam_id'] ?? 0);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['question_paper'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $_SESSION['error_message'] = 'Invalid CSRF token';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    $file = $_FILES['question_paper'];
    $version = (int)($_POST['version'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate file
    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'File upload failed';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    if ($file['size'] > $maxSize) {
        $_SESSION['error_message'] = 'File too large (max 10MB)';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $_SESSION['error_message'] = 'Invalid file type. Only PDF, DOC, DOCX allowed';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    // Check if exam exists and user has permission
    $stmt = $pdo->prepare("SELECT id, title, created_by, status FROM exams WHERE id = ?");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        $_SESSION['error_message'] = 'Exam not found';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    // Only creator, HOD, Principal can upload
    if ($exam['created_by'] != $currentUserId && !in_array($currentUserRole, ['admin', 'principal', 'vice_principal', 'hod'])) {
        $_SESSION['error_message'] = 'Permission denied';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    // Check if paper is locked
    $stmt = $pdo->prepare("SELECT is_locked FROM question_papers WHERE exam_id = ? ORDER BY version DESC LIMIT 1");
    $stmt->execute([$examId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing && $existing['is_locked']) {
        $_SESSION['error_message'] = 'Question paper is locked. Cannot upload new version.';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/uploads/question_papers/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'QP_' . $examId . '_v' . $version . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $_SESSION['error_message'] = 'Failed to save file';
        header('Location: question_papers.php?exam_id=' . $examId);
        exit;
    }
    
    // Save to database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO question_papers (exam_id, version, filename, original_filename, file_path, file_size, mime_type, uploaded_by, upload_notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $examId,
            $version,
            $filename,
            $file['name'],
            'uploads/question_papers/' . $filename,
            $file['size'],
            $mimeType,
            $currentUserId,
            $notes
        ]);
        
        $paperId = $pdo->lastInsertId();
        
        // Log audit
        logAudit($pdo, 'question_paper', $paperId, 'upload', $currentUserId, [
            'exam_id' => $examId,
            'version' => $version,
            'filename' => $filename
        ]);
        
        // Send notification to Principal
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'principal' AND college_id = (SELECT college_id FROM exams WHERE id = ?)");
        $stmt->execute([$examId]);
        $principals = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($principals as $principalId) {
            sendNotification($pdo, $principalId, 'document_ready', [
                'title' => 'Question Paper Uploaded',
                'message' => 'New question paper uploaded for ' . $exam['title'],
                'link' => 'question_papers.php?exam_id=' . $examId
            ]);
        }
        
        $_SESSION['success_message'] = 'Question paper uploaded successfully';
        
    } catch (Exception $e) {
        unlink($filepath); // Delete file on error
        error_log('Question paper upload error: ' . $e->getMessage());
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }
    
    header('Location: question_papers.php?exam_id=' . $examId);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $paperId = (int)($_POST['paper_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        if ($action === 'lock_paper') {
            // Only Principal can lock
            if (!in_array($currentUserRole, ['admin', 'principal'])) {
                echo json_encode(['success' => false, 'message' => 'Only Principal can lock question papers']);
                exit;
            }
            
            $result = lockQuestionPaper($pdo, $paperId, $currentUserId, $currentUserRole);
            echo json_encode($result);
            exit;
        }
        
        if ($action === 'unlock_paper') {
            // Only Principal can unlock
            if (!in_array($currentUserRole, ['admin', 'principal'])) {
                echo json_encode(['success' => false, 'message' => 'Only Principal can unlock question papers']);
                exit;
            }
            
            $result = unlockQuestionPaper($pdo, $paperId, $currentUserId, $currentUserRole);
            echo json_encode($result);
            exit;
        }
        
        if ($action === 'delete_paper') {
            // Cannot delete locked papers
            $stmt = $pdo->prepare("SELECT is_locked, file_path FROM question_papers WHERE id = ?");
            $stmt->execute([$paperId]);
            $paper = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$paper) {
                echo json_encode(['success' => false, 'message' => 'Paper not found']);
                exit;
            }
            
            if ($paper['is_locked']) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete locked paper']);
                exit;
            }
            
            // Delete file and record
            if (file_exists(__DIR__ . '/' . $paper['file_path'])) {
                unlink(__DIR__ . '/' . $paper['file_path']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM question_papers WHERE id = ?");
            $stmt->execute([$paperId]);
            
            logAudit($pdo, 'question_paper', $paperId, 'delete', $currentUserId, ['file_path' => $paper['file_path']]);
            
            echo json_encode(['success' => true, 'message' => 'Paper deleted successfully']);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
        
    } catch (Exception $e) {
        error_log('Question paper action error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch exam details
$exam = null;
if ($examId > 0) {
    $stmt = $pdo->prepare("
        SELECT e.*, c.college_name, d.dept_name, u.name as creator_name
        FROM exams e
        LEFT JOIN colleges c ON e.college_id = c.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch question papers for exam
$papers = [];
if ($examId > 0) {
    $stmt = $pdo->prepare("
        SELECT qp.*, u.name as uploader_name, 
               lu.name as locked_by_name, uu.name as unlocked_by_name
        FROM question_papers qp
        LEFT JOIN users u ON qp.uploaded_by = u.id
        LEFT JOIN users lu ON qp.locked_by = lu.id
        LEFT JOIN users uu ON qp.unlocked_by = uu.id
        WHERE qp.exam_id = ?
        ORDER BY qp.version DESC, qp.created_at DESC
    ");
    $stmt->execute([$examId]);
    $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get latest paper status
$latestPaper = !empty($papers) ? $papers[0] : null;
$isLocked = $latestPaper ? (bool)$latestPaper['is_locked'] : false;

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
    <title>Question Papers - EEMS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .page-header { background: white; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .upload-zone { border: 2px dashed #ccc; border-radius: 8px; padding: 3rem; text-align: center; transition: all 0.3s; cursor: pointer; }
        .upload-zone:hover { border-color: #0d6efd; background: #f8f9fa; }
        .upload-zone.dragover { border-color: #0d6efd; background: #e7f1ff; }
        .paper-card { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; border-left: 4px solid #0d6efd; }
        .paper-card.locked { border-left-color: #dc3545; background: #fff5f5; }
        .lock-badge { font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-file-earmark-text me-2"></i>Question Papers</h2>
                <?php if ($exam): ?>
                <p class="text-muted mb-0"><?= htmlspecialchars($exam['title']) ?> - <?= htmlspecialchars($exam['subject'] ?? '') ?></p>
                <?php endif; ?>
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
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>
    
    <?php if (!$exam): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Please select an exam to manage question papers
    </div>
    <?php else: ?>
    
    <div class="row">
        <!-- Upload Section -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Question Paper</h5>
                </div>
                <div class="card-body">
                    <?php if ($isLocked): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-lock-fill me-2"></i>
                        <strong>Paper Locked!</strong><br>
                        The current question paper is locked by Principal. Upload disabled.
                    </div>
                    <?php else: ?>
                    
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="version" value="<?= count($papers) + 1 ?>">
                        
                        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                            <i class="bi bi-cloud-arrow-up" style="font-size: 3rem; color: #0d6efd;"></i>
                            <h5 class="mt-3">Click to Upload or Drag & Drop</h5>
                            <p class="text-muted">PDF, DOC, DOCX (Max 10MB)</p>
                            <input type="file" name="question_paper" id="fileInput" accept=".pdf,.doc,.docx" style="display: none;" required onchange="handleFileSelect(this)">
                            <p id="selectedFile" class="mt-2 fw-bold text-success"></p>
                        </div>
                        
                        <div class="mb-3 mt-3">
                            <label class="form-label">Version</label>
                            <input type="number" class="form-control" name="version" value="<?= count($papers) + 1 ?>" readonly>
                            <small class="text-muted">Auto-incremented version number</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Upload Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="e.g., Final version, Corrections made, etc."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <i class="bi bi-upload me-2"></i>Upload Question Paper
                        </button>
                    </form>
                    
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Info -->
            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <h6><i class="bi bi-info-circle me-2"></i>Important Notes</h6>
                    <ul class="small mb-0">
                        <li>Only PDF, DOC, DOCX formats allowed</li>
                        <li>Maximum file size: 10 MB</li>
                        <li>Version automatically increments</li>
                        <li>Locked papers cannot be modified</li>
                        <li>Only Principal can lock/unlock papers</li>
                        <li>Locked papers are tamper-proof</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Papers List -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-files me-2"></i>Uploaded Papers (<?= count($papers) ?>)</h5>
                    <?php if ($isLocked): ?>
                    <span class="badge bg-danger px-3 py-2">
                        <i class="bi bi-lock-fill me-1"></i>LOCKED
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($papers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-earmark-x" style="font-size: 4rem; color: #ccc;"></i>
                        <p class="text-muted mt-3">No question papers uploaded yet</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($papers as $paper): ?>
                        <div class="paper-card <?= $paper['is_locked'] ? 'locked' : '' ?>" id="paper-<?= $paper['id'] ?>">
                            <div class="d-flex align-items-start">
                                <div class="lock-badge me-3">
                                    <?php if ($paper['is_locked']): ?>
                                    <i class="bi bi-lock-fill text-danger" title="Locked"></i>
                                    <?php else: ?>
                                    <i class="bi bi-unlock text-success" title="Unlocked"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                                <?= htmlspecialchars($paper['original_filename']) ?>
                                            </h6>
                                            <div class="text-muted small">
                                                <span class="badge bg-primary me-2">Version <?= $paper['version'] ?></span>
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($paper['uploader_name']) ?>
                                                <i class="bi bi-clock ms-2 me-1"></i><?= date('M j, Y g:i A', strtotime($paper['created_at'])) ?>
                                                <i class="bi bi-file-earmark ms-2 me-1"></i><?= number_format($paper['file_size'] / 1024, 2) ?> KB
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($paper['upload_notes']): ?>
                                    <p class="small text-muted mb-2">
                                        <i class="bi bi-sticky me-1"></i><?= htmlspecialchars($paper['upload_notes']) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($paper['is_locked']): ?>
                                    <div class="alert alert-danger alert-sm py-2 mb-2">
                                        <i class="bi bi-shield-lock me-2"></i>
                                        <strong>Locked by:</strong> <?= htmlspecialchars($paper['locked_by_name']) ?>
                                        on <?= date('M j, Y g:i A', strtotime($paper['locked_at'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= htmlspecialchars($paper['file_path']) ?>" target="_blank" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="<?= htmlspecialchars($paper['file_path']) ?>" download class="btn btn-outline-success">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                        
                                        <?php if (in_array($currentUserRole, ['admin', 'principal'])): ?>
                                            <?php if ($paper['is_locked']): ?>
                                            <button onclick="unlockPaper(<?= $paper['id'] ?>)" class="btn btn-outline-warning">
                                                <i class="bi bi-unlock"></i> Unlock
                                            </button>
                                            <?php else: ?>
                                            <button onclick="lockPaper(<?= $paper['id'] ?>)" class="btn btn-outline-danger">
                                                <i class="bi bi-lock"></i> Lock
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (!$paper['is_locked'] && in_array($currentUserRole, ['admin', 'principal', 'hod'])): ?>
                                        <button onclick="deletePaper(<?= $paper['id'] ?>)" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfToken = '<?= $csrfToken ?>';

// Drag & Drop functionality
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');

if (uploadZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'), false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'), false);
    });
    
    uploadZone.addEventListener('drop', handleDrop, false);
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect(fileInput);
    }
}

function handleFileSelect(input) {
    if (input.files.length > 0) {
        const file = input.files[0];
        document.getElementById('selectedFile').textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
    }
}

// Lock paper
function lockPaper(paperId) {
    if (!confirm('Lock this question paper? This will prevent any modifications.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'lock_paper');
    formData.append('paper_id', paperId);
    formData.append('csrf_token', csrfToken);
    
    fetch('question_papers.php?exam_id=<?= $examId ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
    });
}

// Unlock paper
function unlockPaper(paperId) {
    if (!confirm('Unlock this question paper? This will allow modifications again.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'unlock_paper');
    formData.append('paper_id', paperId);
    formData.append('csrf_token', csrfToken);
    
    fetch('question_papers.php?exam_id=<?= $examId ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
    });
}

// Delete paper
function deletePaper(paperId) {
    if (!confirm('Delete this question paper? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_paper');
    formData.append('paper_id', paperId);
    formData.append('csrf_token', csrfToken);
    
    fetch('question_papers.php?exam_id=<?= $examId ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
    });
}

// Form submission with loading state
document.getElementById('uploadForm')?.addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
});
</script>

</body>
</html>
