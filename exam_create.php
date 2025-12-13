<?php
/**
 * COMPREHENSIVE EXAM CREATION PAGE
 * File: exam_create.php
 * Purpose: Unified exam creation for HOD, Principal, VP, Admin
 * 
 * Features:
 * - Role-based access control
 * - Auto-fill college from user profile
 * - Validation (future dates, required fields)
 * - CSRF protection
 * - Exam type selection (theory/practical)
 * - Duration and venue management
 */

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = normalize_role($_SESSION['role'] ?? '');
$userName = $_SESSION['name'] ?? '';

// Role-based access - only HOD, Principal, VP, Admin can create exams
$allowedRoles = ['hod', 'principal', 'vice_principal', 'admin'];
if (!in_array($userRole, $allowedRoles)) {
    $_SESSION['error'] = 'Only HOD, Principal, Vice-Principal, or Admin can create exams';
    header('Location: dashboard.php');
    exit;
}

// Get user's college
$userCollege = null;
$userCollegeId = null;
try {
    $stmt = $pdo->prepare("SELECT college_id, college_name FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCollegeId = $userData['college_id'] ?? null;
    
    if ($userCollegeId) {
        $stmt = $pdo->prepare("SELECT college_name FROM colleges WHERE college_id = ?");
        $stmt->execute([$userCollegeId]);
        $userCollege = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log('Get user college error: ' . $e->getMessage());
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = 'Invalid security token. Please refresh and try again.';
    } else {
        // Collect and validate input
        $examName = trim($_POST['exam_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $examDate = trim($_POST['exam_date'] ?? '');
        $examType = trim($_POST['exam_type'] ?? 'theory');
        $duration = intval($_POST['duration'] ?? 0);
        $venue = trim($_POST['venue'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $examinersNeeded = intval($_POST['examiners_needed'] ?? 2);
        
        // Validation
        if (empty($examName)) {
            $errorMessage = 'Exam name is required';
        } elseif (empty($subject)) {
            $errorMessage = 'Subject is required';
        } elseif (empty($examDate)) {
            $errorMessage = 'Exam date is required';
        } elseif (strtotime($examDate) < strtotime('today')) {
            $errorMessage = 'Exam date must be in the future';
        } elseif ($duration < 30) {
            $errorMessage = 'Duration must be at least 30 minutes';
        } elseif ($examinersNeeded < 1) {
            $errorMessage = 'At least 1 examiner is required';
        } else {
            try {
                // Insert exam
                $stmt = $pdo->prepare("
                    INSERT INTO exams 
                    (exam_name, subject, exam_date, exam_type, duration, venue, description, 
                     college_id, examiners_needed, status, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                
                $stmt->execute([
                    $examName,
                    $subject,
                    $examDate,
                    $examType,
                    $duration,
                    $venue,
                    $description,
                    $userCollegeId,
                    $examinersNeeded,
                    $userId
                ]);
                
                $examId = $pdo->lastInsertId();
                
                // Audit log
                logAudit($pdo, 'exam', $examId, 'create', $userId, [
                    'exam_name' => $examName,
                    'exam_date' => $examDate,
                    'college_id' => $userCollegeId
                ]);
                
                $successMessage = "Exam created successfully! (ID: $examId) - Awaiting approval.";
                
                // Redirect after 2 seconds
                header("refresh:2;url=dashboard.php");
                
            } catch (PDOException $e) {
                error_log('Create exam error: ' . $e->getMessage());
                $errorMessage = 'Failed to create exam. Please try again.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .create-card {
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .required::after {
            content: ' *';
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card create-card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Create New Exam
                    </h4>
                    <a href="dashboard.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="createExamForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <!-- Basic Information -->
                    <h5 class="mb-3 text-primary">
                        <i class="fas fa-info-circle me-2"></i>Basic Information
                    </h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="exam_name" class="form-label required">Exam Name</label>
                            <input type="text" class="form-control" id="exam_name" name="exam_name" 
                                   placeholder="e.g., Final Semester Mathematics" required
                                   value="<?= htmlspecialchars($_POST['exam_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="subject" class="form-label required">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   placeholder="e.g., Mathematics" required
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="college" class="form-label">College/Department</label>
                            <input type="text" class="form-control" id="college" 
                                   value="<?= htmlspecialchars($userCollege ?? 'Not Set') ?>" readonly>
                            <small class="text-muted">Auto-filled from your profile</small>
                        </div>
                        <div class="col-md-6">
                            <label for="exam_type" class="form-label required">Exam Type</label>
                            <select class="form-select" id="exam_type" name="exam_type" required>
                                <option value="theory" <?= ($_POST['exam_type'] ?? '') === 'theory' ? 'selected' : '' ?>>Theory</option>
                                <option value="practical" <?= ($_POST['exam_type'] ?? '') === 'practical' ? 'selected' : '' ?>>Practical</option>
                                <option value="viva" <?= ($_POST['exam_type'] ?? '') === 'viva' ? 'selected' : '' ?>>Viva</option>
                                <option value="project" <?= ($_POST['exam_type'] ?? '') === 'project' ? 'selected' : '' ?>>Project</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Schedule Information -->
                    <h5 class="mb-3 text-primary mt-4">
                        <i class="fas fa-calendar-alt me-2"></i>Schedule Information
                    </h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="exam_date" class="form-label required">Exam Date</label>
                            <input type="date" class="form-control" id="exam_date" name="exam_date" 
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                                   value="<?= htmlspecialchars($_POST['exam_date'] ?? '') ?>">
                            <small class="text-muted">Must be a future date</small>
                        </div>
                        <div class="col-md-4">
                            <label for="duration" class="form-label required">Duration (minutes)</label>
                            <input type="number" class="form-control" id="duration" name="duration" 
                                   min="30" max="480" step="15" value="<?= htmlspecialchars($_POST['duration'] ?? '180') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="examiners_needed" class="form-label required">Examiners Needed</label>
                            <input type="number" class="form-control" id="examiners_needed" name="examiners_needed" 
                                   min="1" max="10" value="<?= htmlspecialchars($_POST['examiners_needed'] ?? '2') ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue/Location</label>
                        <input type="text" class="form-control" id="venue" name="venue" 
                               placeholder="e.g., Main Examination Hall, Lab 201"
                               value="<?= htmlspecialchars($_POST['venue'] ?? '') ?>">
                    </div>
                    
                    <!-- Additional Details -->
                    <h5 class="mb-3 text-primary mt-4">
                        <i class="fas fa-file-alt me-2"></i>Additional Details
                    </h5>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description/Instructions</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Exam details, special requirements, instructions for examiners..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> After creation, the exam will have "Pending" status and require approval from the Principal before examiners can be assigned.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-check me-1"></i>Create Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="card create-card mt-4" style="background: rgba(255,255,255,0.95);">
            <div class="card-body">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-question-circle me-2"></i>Exam Creation Workflow
                </h5>
                <ol class="mb-0">
                    <li class="mb-2">
                        <strong>Create Exam:</strong> Fill in exam details and submit (Status: Pending)
                    </li>
                    <li class="mb-2">
                        <strong>Principal Approval:</strong> Principal reviews and approves the exam (Status: Approved)
                    </li>
                    <li class="mb-2">
                        <strong>Assign Examiners:</strong> HOD/VP assigns examiners to the approved exam
                    </li>
                    <li class="mb-2">
                        <strong>Examiner Acceptance:</strong> Invited examiners accept assignments
                    </li>
                    <li>
                        <strong>Conduct Exam:</strong> Exam is conducted on the scheduled date
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('createExamForm').addEventListener('submit', function(e) {
            const examDate = document.getElementById('exam_date').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (examDate <= today) {
                e.preventDefault();
                alert('Exam date must be in the future!');
                return false;
            }
            
            const duration = parseInt(document.getElementById('duration').value);
            if (duration < 30) {
                e.preventDefault();
                alert('Duration must be at least 30 minutes!');
                return false;
            }
        });
        
        // Auto-hide success message after 3 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }
        }, 3000);
    </script>
</body>
</html>
