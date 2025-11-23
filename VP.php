<?php
/**
 * VICE PRINCIPAL DASHBOARD - Enhanced with Security & Privacy Controls
 * ===================================================================
 * Views approved exams from other colleges and nominates faculty
 * 
 * SECURITY FEATURES:
 * - Role-based access control: Only 'vice-principal' and 'admin' roles
 * - Session validation and hijacking prevention
 * - CSRF token protection on all nominations
 * - Data privacy: VP can ONLY see their college's data
 * - Input sanitization on all user inputs
 * - Security audit logging
 * 
 * PRIVACY REQUIREMENT:
 * VP at College A's data (faculty, exams, nominations) MUST NOT be visible
 * to VP at College B. All queries filtered by college_name.
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

// SECURITY: Enforce authentication and role-based access
require_auth();
require_role(['vice-principal', 'admin'], true);

// PRIVACY: Get current VP's info (filtered by user_id)
$currentUserId = get_current_user_id();
$userStmt = $pdo->prepare("
    SELECT name, college_name, email, phone, post
    FROM users 
    WHERE id = ? 
    LIMIT 1
");
$userStmt->execute([$currentUserId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

// SECURITY: Validate user exists
if (!$userInfo) {
    security_log('USER_NOT_FOUND_IN_DB', ['user_id' => $currentUserId]);
    destroy_session_and_redirect('User account not found.');
}

$currentUserName = $userInfo['name'] ?? 'Vice Principal';
$currentUserCollege = $userInfo['college_name'] ?? '';

// Fetch approved exams from OTHER colleges
$examsStmt = $pdo->prepare("
    SELECT 
        e.id AS exam_id,
        e.title AS exam_name,
        e.subject,
        e.exam_date,
        e.status,
        e.description,
        e.department AS college_name,
        u.name AS created_by_name,
        e.created_at,
        (SELECT COUNT(*) FROM assignments WHERE exam_id = e.id) AS total_assigned
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.status = 'Approved'
    AND e.department != ?
    AND e.exam_date >= CURDATE()
    ORDER BY e.exam_date ASC
");
$examsStmt->execute([$currentUserCollege]);
$approvedExams = $examsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exams from own college
$ownExamsStmt = $pdo->prepare("
    SELECT 
        e.id AS exam_id,
        e.title AS exam_name,
        e.subject,
        e.exam_date,
        e.status,
        e.description,
        e.department AS college_name,
        u.name AS created_by_name,
        e.created_at
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.department = ?
    ORDER BY e.created_at DESC
    LIMIT 10
");
$ownExamsStmt->execute([$currentUserCollege]);
$ownExams = $ownExamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalApproved = count($approvedExams);
$totalOwn = count($ownExams);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Vice Principal Dashboard - EEMS</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 0;
        }
        .main-container {
            min-height: 100vh;
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
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .exam-card {
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .exam-card:hover {
            transform: translateX(5px);
        }
        /* Custom styles for VP exclusive modules */
        .vp-module {
            color: #14b8a6;
        }
        .vp-module i {
            color: #14b8a6;
        }
        .vp-module.active {
            background: rgba(20, 184, 166, 0.1);
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-gradient-primary rounded-3 p-2 me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-building text-white fs-4"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold" style="color: #2d3748;">EEMS - Vice Principal</h5>
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
      <h6 class="text-uppercase small mb-4" style="color: #14b8a6; font-weight: 600; letter-spacing: 0.5px;">VP Exclusive</h6>
      <nav class="nav flex-column gap-2">
        <a href="#" onclick="loadModule('vp_dashboard'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #14b8a6;">
          <i class="bi bi-bar-chart-line me-2" style="color: #14b8a6;"></i>VP College Dashboard
        </a>
        <a href="#" onclick="loadModule('staff_support'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #14b8a6;">
          <i class="bi bi-people-fill me-2" style="color: #14b8a6;"></i>Staff Support Panel
        </a>
        <a href="#" onclick="loadModule('nomination_review'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #14b8a6;">
          <i class="bi bi-person-badge me-2" style="color: #14b8a6;"></i>Nomination Review
        </a>
        <a href="#" onclick="loadModule('task_assignment'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #14b8a6;">
          <i class="bi bi-list-task me-2" style="color: #14b8a6;"></i>Task Assignment
        </a>
        <a href="#" onclick="loadModule('schedule_notifications'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #14b8a6;">
          <i class="bi bi-bell me-2" style="color: #14b8a6;"></i>Schedule Notifications
        </a>
      </nav>
    </div>
  </div>

  <!-- Main Content -->
  <div class="flex-grow-1">
    <div class="main-container" style="padding: 2rem;">
    <!-- Header -->
    <div class="dashboard-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-0"><i class="bi bi-person-badge"></i> Vice Principal Dashboard</h2>
                <p class="text-muted mb-0">Welcome, <?= htmlspecialchars($currentUserName) ?> | <?= htmlspecialchars($currentUserCollege) ?></p>
            </div>
            <div>
                <button class="btn btn-success me-2" onclick="showAddExamModal()">
                    <i class="bi bi-plus-circle"></i> Create Exam
                </button>
                <a href="dashboard.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-house"></i> Main Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card">
                <h5><i class="bi bi-calendar-check"></i> Available Exams</h5>
                <h2><?= $totalApproved ?></h2>
                <small>Approved exams from other colleges</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h5><i class="bi bi-building"></i> Your College Exams</h5>
                <h2><?= $totalOwn ?></h2>
                <small>Recent exams from <?= htmlspecialchars($currentUserCollege) ?></small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h5><i class="bi bi-people"></i> Faculty Management</h5>
                <h2><i class="bi bi-arrow-right"></i></h2>
                <small>Nominate faculty for external exams</small>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="dashboard-card p-4">
        <ul class="nav nav-tabs mb-4" id="vpTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button">
                    <i class="bi bi-calendar-check"></i> Available Exams from Other Colleges
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="own-tab" data-bs-toggle="tab" data-bs-target="#own" type="button">
                    <i class="bi bi-building"></i> Your College Exams
                </button>
            </li>
        </ul>

        <div class="tab-content" id="vpTabsContent">
            <!-- Available Exams Tab -->
            <div class="tab-pane fade show active" id="available" role="tabpanel">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> These are approved exams from other colleges. You can view details and nominate your faculty members to be external examiners.
                </div>

                <?php if (empty($approvedExams)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                        <p class="text-muted mt-3">No approved exams from other colleges at the moment.</p>
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
                                    <th>Faculty Assigned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedExams as $exam): ?>
                                <tr class="exam-card">
                                    <td>
                                        <strong><?= htmlspecialchars($exam['exam_name']) ?></strong>
                                        <?php if ($exam['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($exam['description'], 0, 50)) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($exam['college_name']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($exam['subject']) ?></span></td>
                                    <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $exam['total_assigned'] ?> assigned</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewExamDetails(<?= $exam['exam_id'] ?>)">
                                            <i class="bi bi-eye"></i> View Details
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="nominateFaculty(<?= $exam['exam_id'] ?>)">
                                            <i class="bi bi-person-plus"></i> Nominate Faculty
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Own College Exams Tab -->
            <div class="tab-pane fade" id="own" role="tabpanel">
                <div class="alert alert-primary">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Info:</strong> These are exams created by your college. They need admin approval before becoming available to other colleges.
                </div>

                <?php if (empty($ownExams)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                        <p class="text-muted mt-3">No exams from your college yet.</p>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Exam (Principal Dashboard)
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Subject</th>
                                    <th>Exam Date</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ownExams as $exam): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($exam['exam_name']) ?></strong></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($exam['subject']) ?></span></td>
                                    <td><?= date('M d, Y', strtotime($exam['exam_date'])) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'Pending' => 'warning',
                                            'Approved' => 'success',
                                            'Assigned' => 'primary',
                                            'Cancelled' => 'danger'
                                        ];
                                        $color = $statusColors[$exam['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($exam['status']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($exam['created_by_name'] ?? 'System') ?></td>
                                    <td><?= date('M d, Y', strtotime($exam['created_at'])) ?></td>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// CSRF Token
const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

function showAddExamModal() {
    const userCollege = '<?= htmlspecialchars($currentUserCollege) ?>';
    
    const modalHtml = `
        <div class="modal fade" id="addExamModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2"></i>Create New Exam
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Creating exam for <strong>${userCollege}</strong>. It will be pending Principal approval.
                        </div>
                        <form id="addExamForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Exam Name *</label>
                                    <input type="text" class="form-control" name="exam_name" required placeholder="e.g., Final Semester Mathematics">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subject *</label>
                                    <input type="text" class="form-control" name="subject" required placeholder="e.g., Mathematics">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">College/Department *</label>
                                    <input type="text" class="form-control" name="college" value="${userCollege}" readonly required>
                                    <small class="text-muted">Auto-filled from your profile</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Exam Date *</label>
                                    <input type="date" class="form-control" name="exam_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                    <small class="text-muted">Must be a future date</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Exam details, requirements, special instructions..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitAddExam()">
                            <i class="bi bi-check-circle me-1"></i>Create Exam
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
    $('#addExamModal').modal('show');
    $('#addExamModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

function submitAddExam() {
    const formData = new FormData($('#addExamForm')[0]);
    formData.append('action', 'add_exam');
    formData.append('csrf_token', csrfToken);
    
    $.ajax({
        url: 'admin_dashboard.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#addExamModal').modal('hide');
                alert('Exam created successfully! Awaiting Principal approval.');
                location.reload();
            } else {
                alert('Error: ' + (response.message || 'Failed to create exam'));
            }
        },
        error: function() {
            alert('Network error. Please try again.');
        }
    });
}

function viewExamDetails(examId) {
    // Redirect to exam details page or show modal
    window.location.href = `view_exam_details.php?id=${examId}`;
}

function nominateFaculty(examId) {
    alert(`Feature coming soon: Nominate faculty for exam ID ${examId}`);
    // TODO: Implement faculty nomination workflow
    // This would:
    // 1. Show list of available faculty from your college
    // 2. Allow VP to select faculty members
    // 3. Submit nomination to system
    // 4. Notify selected faculty
}

// VP Module Loader
function loadModule(moduleName) {
  const mainContainer = document.querySelector('.main-container');
  if (!mainContainer) return;
  mainContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div><p class="mt-3">Loading module...</p></div>';
  fetch(`VP.php?action=load_module&module=${moduleName}`)
    .then(res => res.text())
    .then(html => { mainContainer.innerHTML = html; })
    .catch(() => { mainContainer.innerHTML = '<div class="alert alert-danger">Failed to load module.</div>'; });
}
</script>

<!-- Recent Exam Assignments Widget -->
<?php
    if (file_exists(__DIR__ . '/includes/assignment_widget.php')) {
        include __DIR__ . '/includes/assignment_widget.php';
    }
?>

<?php
// VP Module Routing & API Handlers
if (isset($_GET['action']) && $_GET['action'] === 'load_module' && isset($_GET['module'])) {
  $module = $_GET['module'];
  switch ($module) {
    case 'vp_dashboard':
      // VP College Dashboard module
      echo '<div class="p-4"><h3 style="color:#14b8a6"><i class="bi bi-bar-chart-line me-2"></i>VP College Dashboard</h3><p class="text-muted">Analytics and overview for your college.</p></div>';
      break;
    case 'staff_support':
      echo '<div class="p-4"><h3 style="color:#14b8a6"><i class="bi bi-people-fill me-2"></i>Staff Support Panel</h3><p class="text-muted">Support requests and staff management.</p></div>';
      break;
    case 'nomination_review':
      echo '<div class="p-4"><h3 style="color:#14b8a6"><i class="bi bi-person-badge me-2"></i>Nomination Review</h3><p class="text-muted">Review examiner nominations.</p></div>';
      break;
    case 'task_assignment':
      echo '<div class="p-4"><h3 style="color:#14b8a6"><i class="bi bi-list-task me-2"></i>Task Assignment</h3><p class="text-muted">Assign tasks to staff.</p></div>';
      break;
    case 'schedule_notifications':
      echo '<div class="p-4"><h3 style="color:#14b8a6"><i class="bi bi-bell me-2"></i>Schedule Notifications</h3><p class="text-muted">Manage notifications and reminders.</p></div>';
      break;
    default:
      echo '<div class="alert alert-warning">Module not found</div>';
      break;
  }
  exit;
}
?>

</div>
</div>

</body>
</html>
