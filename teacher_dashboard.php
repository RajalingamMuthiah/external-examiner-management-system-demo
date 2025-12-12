<?php
/**
 * TEACHER DASHBOARD - Enhanced with Security & Privacy Controls
 * ===================================================================
 * View and self-select approved exams from other colleges
 * 
 * SECURITY FEATURES:
 * - Role-based access control (RBAC): Only 'teacher' and 'faculty' roles allowed
 * - Session validation and hijacking prevention
 * - CSRF token protection on all form submissions
 * - Data privacy: Teachers can ONLY see their own assignments and available exams
 * - Input sanitization on all user inputs
 * - Security audit logging for suspicious activities
 * 
 * PRIVACY REQUIREMENT:
 * Teacher A's data (assignments, profile, notifications) MUST NOT be visible
 * to Teacher B. All queries are filtered by user_id to ensure complete isolation.
 */

// Initialize secure session and security controls
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check profile completion - redirect if college/department not set
require_once __DIR__ . '/includes/profile_check.php';

// Privacy context for all teacher dashboard logic
$currentUserId = $_SESSION['user_id'] ?? get_current_user_id();
$currentUserRole = normalize_role($_SESSION['role'] ?? 'teacher');
$currentUserCollege = $_SESSION['college_id'] ?? null;
$currentUserDept = $_SESSION['department_id'] ?? null;

// SECURITY: Enforce authentication and role-based access
require_auth();
require_role(['teacher', 'faculty'], true);

// Handle AJAX request for selecting exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'teacher_select_exam') {
        // SECURITY: Sanitize and validate input
        $examId = sanitize_int($_POST['exam_id'] ?? 0);
        $csrfToken = sanitize_string($_POST['csrf_token'] ?? '');
        
        // SECURITY: Validate CSRF token to prevent CSRF attacks
        if (!validate_csrf_token($csrfToken)) {
            security_log('CSRF_VIOLATION_EXAM_SELECT', ['exam_id' => $examId]);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
            exit;
        }
        
        // SECURITY: Double-check authentication (redundant but safe)
        $currentUserId = get_current_user_id();
        if (!$currentUserId) {
            security_log('UNAUTHORIZED_EXAM_SELECT_ATTEMPT');
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        // SECURITY: Validate exam ID is positive integer
        if ($examId <= 0) {
            security_log('INVALID_EXAM_ID', ['exam_id' => $examId]);
            echo json_encode(['success' => false, 'message' => 'Invalid exam selection']);
            exit;
        }
        
        try {
            // PRIVACY: Get ONLY current user's college (filtered by user_id)
            $userStmt = $pdo->prepare("SELECT college_name FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$currentUserId]);
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            $userCollege = $userInfo['college_name'] ?? '';
            
            // PRIVACY: Check if THIS teacher already has ANY assignment (filtered by faculty_id)
            $hasAssignmentStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE faculty_id = ?");
            $hasAssignmentStmt->execute([$currentUserId]);
            if ($hasAssignmentStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'You have already selected an exam. You cannot select multiple exams.']);
                exit;
            }
            
            // PRIVACY: Check if THIS teacher is already assigned to this specific exam
            $checkStmt = $pdo->prepare("SELECT id FROM assignments WHERE exam_id = ? AND faculty_id = ? LIMIT 1");
            $checkStmt->execute([$examId, $currentUserId]);
            
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You are already assigned to this exam']);
                exit;
            }
            
            // Check if any faculty from same college is already assigned to this exam
            $collegeCheckStmt = $pdo->prepare("
                SELECT u.name 
                FROM assignments a
                INNER JOIN users u ON a.faculty_id = u.id
                WHERE a.exam_id = ? AND u.college_name = ?
                LIMIT 1
            ");
            $collegeCheckStmt->execute([$examId, $userCollege]);
            $existingFaculty = $collegeCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingFaculty) {
                // SECURITY: Sanitize output to prevent XSS attacks
                echo json_encode([
                    'success' => false, 
                    'message' => 'A faculty member from your college (' . sanitize_output($existingFaculty['name']) . ') has already been assigned to this exam.'
                ]);
                exit;
            }
            
            // SECURITY: Log successful assignment for audit trail
            security_log('EXAM_SELECTED', [
                'user_id' => $currentUserId,
                'exam_id' => $examId,
                'college' => $userCollege,
            ]);
            
            // PRIVACY: Create assignment for THIS user only (self-selected, so assigned_by is NULL)
            $insertStmt = $pdo->prepare("
                INSERT INTO assignments (exam_id, faculty_id, role, assigned_at, assigned_by, status) 
                VALUES (?, ?, 'External Examiner', NOW(), NULL, 'Assigned')
            ");
            $insertStmt->execute([$examId, $currentUserId]);
            
            echo json_encode(['success' => true, 'message' => 'Successfully selected for exam! Redirecting...']);
            exit;
        } catch (Exception $e) {
            // SECURITY: Log database errors for security audit
            security_log('DATABASE_ERROR_EXAM_SELECT', [
                'error' => $e->getMessage(),
                'user_id' => $currentUserId ?? 'unknown',
                'exam_id' => $examId ?? 'unknown',
            ]);
            error_log('Teacher select exam error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
            exit;
        }
    }
}

// PRIVACY: Get current user info (ONLY for logged-in user, enforced by user_id filter)
$currentUserId = get_current_user_id(); // From security middleware
$userStmt = $pdo->prepare("
    SELECT name, college_name, email, profile_completed, phone, post
    FROM users 
    WHERE id = ? 
    LIMIT 1
");
$userStmt->execute([$currentUserId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

// SECURITY: Validate user exists in database
if (!$userInfo) {
    security_log('USER_NOT_FOUND_IN_DB', ['user_id' => $currentUserId]);
    destroy_session_and_redirect('User account not found. Please contact administrator.');
}

// Check if profile is completed, if not redirect to onboarding
if (!$userInfo['profile_completed']) {
    header('Location: teacher_onboarding.php');
    exit;
}

$currentUserName = $userInfo['name'] ?? 'Teacher';
$currentUserCollege = $userInfo['college_name'] ?? '';
$currentUserEmail = $userInfo['email'] ?? '';

// PRIVACY: Check if THIS teacher has already selected any exam (filtered by faculty_id)
$hasAssignmentStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE faculty_id = ?");
$hasAssignmentStmt->execute([$currentUserId]);
$hasExistingAssignment = $hasAssignmentStmt->fetchColumn() > 0;

// Use getVisibleExamsForUser() function for proper role-based filtering
$allExams = getVisibleExamsForUser($pdo, $currentUserId, $currentUserRole, $currentUserCollege, $currentUserDept);

// Split exams into assigned and available based on exam_source field
$assignedExams = [];
$availableExams = [];

foreach ($allExams as $exam) {
    if (isset($exam['exam_source']) && $exam['exam_source'] === 'assigned') {
        $assignedExams[] = $exam;
    } elseif (isset($exam['exam_source']) && $exam['exam_source'] === 'available') {
        $availableExams[] = $exam;
    }
}

// PRIVACY: If teacher has an assignment, hide ALL available exams (one-exam-per-teacher rule)
if ($hasExistingAssignment) {
    $availableExams = [];
}

// Stats
$totalAvailable = count($availableExams);
$totalAssigned = count($assignedExams);

// SECURITY: Generate CSRF token for form protection
$csrfToken = get_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Teacher Dashboard - EEMS</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .dashboard-container {
            padding: 2rem;
        }
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .exam-card {
            border-left: 5px solid #667eea;
            transition: all 0.3s;
            cursor: pointer;
        }
        .exam-card:hover {
            transform: translateX(10px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .badge-custom {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        /* Pink/Rose color theme overrides */
        :root {
            --bs-primary: #ec4899;
            --bs-primary-rgb: 236, 72, 153;
            --bs-success: #10b981;
            --bs-info: #0dcaf0;
            --bs-warning: #ffc107;
            --bs-danger: #dc3545;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }
        .bg-pink {
            background-color: #ec4899 !important;
        }
        .text-pink {
            color: #ec4899 !important;
        }
        .border-pink {
            border-color: #ec4899 !important;
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-gradient-primary rounded-3 p-2 me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-person-workspace text-white fs-4"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold" style="color: #2d3748;">EEMS - Teacher</h5>
                <small class="text-muted">Exam Management System</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="fw-semibold text-dark"><?= htmlspecialchars($currentUserName) ?></div>
                <small class="text-muted"><?= htmlspecialchars($currentUserCollege) ?></small>
            </div>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="d-flex">
  <!-- Sidebar -->
  <div class="bg-white border-end shadow-sm" style="width: 270px; min-height: calc(100vh - 72px); position: sticky; top: 72px;">
    <div class="p-4">
      <h6 class="text-uppercase small mb-4" style="color: #ec4899; font-weight: 600; letter-spacing: 0.5px;">Teacher Exclusive</h6>
      <nav class="nav flex-column gap-2">
        <a href="#" onclick="loadModule('subject_dashboard'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #ec4899;">
          <i class="bi bi-journal-bookmark me-2" style="color: #ec4899;"></i>Subject Dashboard
        </a>
        <a href="#" onclick="loadModule('exam_templates'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #ec4899;">
          <i class="bi bi-file-earmark-text me-2" style="color: #ec4899;"></i>Exam Templates
        </a>
        <a href="#" onclick="loadModule('map_nearby_exams'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #ec4899;">
          <i class="bi bi-geo-alt me-2" style="color: #ec4899;"></i>Nearby Exams (Map)
        </a>
        <a href="#" onclick="loadModule('faculty_finder'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #ec4899;">
          <i class="bi bi-search me-2" style="color: #ec4899;"></i>Faculty Finder
        </a>
        <a href="#" onclick="loadModule('results_entry'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #ec4899;">
          <i class="bi bi-pencil-square me-2" style="color: #ec4899;"></i>Results Entry
        </a>
        <a href="#" onclick="loadModule('training_resources'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #ec4899;">
          <i class="bi bi-mortarboard me-2" style="color: #ec4899;"></i>Training Resources
        </a>
      </nav>
    </div>
  </div>

  <!-- Main Content -->
  <div class="flex-grow-1">
    <div class="dashboard-container">
    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="bi bi-calendar-check"></i> Available Exams</h5>
                        <h2 class="mb-0"><?= $totalAvailable ?></h2>
                        <small>From other colleges - you can self-select</small>
                    </div>
                    <i class="bi bi-calendar-check" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="bi bi-bookmark-check"></i> My Assignments</h5>
                        <h2 class="mb-0"><?= $totalAssigned ?></h2>
                        <small>Exams you're assigned to as examiner</small>
                    </div>
                    <i class="bi bi-bookmark-check" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="dashboard-card p-4">
        <ul class="nav nav-tabs nav-fill mb-4" id="teacherTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button">
                    <i class="bi bi-calendar-check"></i> Available Exams (<?= $totalAvailable ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assigned" type="button">
                    <i class="bi bi-bookmark-check"></i> My Assignments (<?= $totalAssigned ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="teacherTabsContent">
            <!-- Available Exams Tab -->
            <div class="tab-pane fade show active" id="available" role="tabpanel">
                <?php if ($hasExistingAssignment): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-3 fs-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">You've Already Selected an Exam</h5>
                            <p class="mb-0">You have been assigned to an exam. Check your "My Assignments" tab to view details. You cannot select additional exams.</p>
                        </div>
                    </div>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-check" style="font-size: 5rem; color: #10b981;"></i>
                        <h4 class="mt-3">Assignment Complete</h4>
                        <p class="text-muted">Switch to "My Assignments" tab to see your exam details.</p>
                        <button class="btn btn-primary mt-3" onclick="document.querySelector('#assignments-tab').click()">
                            <i class="bi bi-list-check me-2"></i>View My Assignments
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>How it works:</strong> These are approved exams from other colleges (not <?= htmlspecialchars($currentUserCollege) ?>). 
                        Click "Select Exam" to volunteer as an external examiner. <strong>Once you select an exam, you cannot select any other exams.</strong>
                        Exams already assigned to faculty from your college are not shown.
                    </div>

                    <?php if (empty($availableExams)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">
                                No available exams at the moment.<br>
                                <small>Exams may not be available if they're already assigned to faculty from your college or if all exams have been filled.</small>
                            </p>
                        </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($availableExams as $exam): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card exam-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?= htmlspecialchars($exam['title']) ?></h5>
                                                <h5 class="card-title mb-0"><?= htmlspecialchars($exam['title']) ?></h5>
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($exam['title']) ?></h5>
                                        <?php if ($exam['is_assigned']): ?>
                                            <span class="badge bg-success badge-custom">Already Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-3"><i class="bi bi-building"></i> <?= htmlspecialchars($exam['college_name']) ?></p>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Subject:</small><br>
                                            <span class="badge bg-info"><?= htmlspecialchars($exam['subject']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Exam Date:</small><br>
                                            <strong><?= date('M d, Y', strtotime($exam['exam_date'])) ?></strong>
                                        </div>
                                        <div class="col-12">
                                            <small class="text-muted">Faculty Assigned:</small><br>
                                            <span class="badge bg-primary"><?= $exam['total_assigned'] ?> assigned</span>
                                        </div>
                                    </div>

                                    <?php if ($exam['description']): ?>
                                    <p class="small text-muted mb-3">
                                        <?= htmlspecialchars(substr($exam['description'], 0, 100)) ?><?= strlen($exam['description']) > 100 ? '...' : '' ?>
                                    </p>
                                    <?php endif; ?>

                                    <div class="d-grid gap-2">
                                        <?php if (!$exam['is_assigned']): ?>
                                            <button class="btn btn-primary" onclick="selectExam(<?= $exam['exam_id'] ?>)">
                                                <i class="bi bi-check-circle"></i> Select This Exam
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class="bi bi-check-circle-fill"></i> Already Selected
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-info btn-sm" onclick="viewExamDetails(<?= $exam['exam_id'] ?>)">
                                            <i class="bi bi-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- My Assignments Tab -->
            <div class="tab-pane fade" id="assigned" role="tabpanel">
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Your Assignments:</strong> These are exams where you're assigned as an external examiner.
                </div>

                <?php if (empty($assignedExams)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                        <p class="text-muted mt-3">You haven't been assigned to any exams yet.</p>
                        <button class="btn btn-primary" onclick="document.getElementById('available-tab').click()">
                            <i class="bi bi-search"></i> Browse Available Exams
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Exam Name</th>
                                    <th>College</th>
                                    <th>Subject</th>
                                    <th>Exam Date</th>
                                    <th>Assigned On</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedExams as $exam): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($exam['title']) ?></strong></td>
                                            <td><strong><?= htmlspecialchars($exam['title']) ?></strong></td>
                                        <td><strong><?= htmlspecialchars($exam['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($exam['college_name']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($exam['subject']) ?></span></td>
                                    <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($exam['assigned_at'])) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($exam['assignment_role'] ?? 'Examiner') ?></span></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</div>
</div>

<!-- Bootstrap JS & jQuery -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// SECURITY: CSRF token for AJAX requests
const csrfToken = '<?= sanitize_attr($csrfToken) ?>';

function selectExam(examId) {
    if (!confirm('⚠️ IMPORTANT: You can only select ONE exam.\n\nOnce you select this exam, you will NOT be able to select any other exams.\n\nDo you want to proceed?')) {
        return;
    }

    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';

    $.ajax({
        url: 'teacher_dashboard.php',
        method: 'POST',
        data: {
            action: 'teacher_select_exam',
            exam_id: examId,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('✅ ' + response.message);
                location.reload();
            } else {
                alert('❌ ' + response.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },
        error: function() {
            alert('❌ Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

// Teacher Module Loader
function loadModule(moduleName) {
  const mainContainer = document.querySelector('.main-container');
  if (!mainContainer) return;
  mainContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-pink" role="status"></div><p class="mt-3">Loading module...</p></div>';
  fetch(`teacher_dashboard.php?action=load_module&module=${moduleName}`)
    .then(res => res.text())
    .then(html => { mainContainer.innerHTML = html; })
    .catch(() => { mainContainer.innerHTML = '<div class="alert alert-danger">Failed to load module.</div>'; });
}
</script>

<?php
// Teacher Module Routing & API Handlers
if (isset($_GET['action']) && $_GET['action'] === 'load_module' && isset($_GET['module'])) {
  $module = $_GET['module'];
  switch ($module) {
    case 'subject_dashboard':
      echo '<div class="p-4"><h3 style="color:#ec4899"><i class="bi bi-journal-bookmark me-2"></i>Subject Dashboard</h3><p class="text-muted">Overview of your subjects and assignments.</p></div>';
      break;
    case 'exam_templates':
      echo '<div class="p-4"><h3 style="color:#ec4899"><i class="bi bi-file-earmark-text me-2"></i>Exam Templates</h3><p class="text-muted">Create and manage exam templates.</p></div>';
      break;
    case 'map_nearby_exams':
      echo '<div class="p-4"><h3 style="color:#ec4899"><i class="bi bi-geo-alt me-2"></i>Nearby Exams (Map)</h3><p class="text-muted">View nearby exams on a map.</p></div>';
      break;
    case 'faculty_finder':
      echo '<div class="p-4"><h3 style="color:#ec4899"><i class="bi bi-search me-2"></i>Faculty Finder</h3><p class="text-muted">Find faculty for collaboration.</p></div>';
      break;
    case 'results_entry':
      echo '<div class="p-4"><h3 style="color:#ec4899"><i class="bi bi-pencil-square me-2"></i>Results Entry</h3><p class="text-muted">Enter and manage exam results.</p></div>';
      break;
    case 'training_resources':
      echo '<div class="p-4"><h3 style="color:#ec4899"><i class="bi bi-mortarboard me-2"></i>Training Resources</h3><p class="text-muted">Access training and onboarding resources.</p></div>';
      break;
    default:
      echo '<div class="alert alert-warning">Module not found</div>';
      break;
  }
  exit;
}
?>

</body>
</html>
