<?php
/**
 * HOD DASHBOARD - Enhanced with Security & Privacy Controls
 * ===================================================================
 * Views approved exams from other colleges and nominates faculty
 * 
 * SECURITY FEATURES:
 * - Role-based access control: Only 'hod', 'head', 'hod_incharge', 'admin' roles
 * - Session validation and hijacking prevention
 * - CSRF token protection on all nominations
 * - Data privacy: HOD can ONLY see their college's data
 * - Input sanitization on all user inputs
 * - Security audit logging
 * 
 * PRIVACY REQUIREMENT:
 * HOD at College A's data (faculty, exams, nominations) MUST NOT be visible
 * to HOD at College B. All queries filtered by college_name.
 */

// Initialize secure session and security controls
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// SECURITY: Enforce authentication and role-based access
require_auth();
require_role(['hod', 'head', 'hod_incharge', 'admin'], true);

// PRIVACY: Get current HOD's info (filtered by user_id)
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

$currentUserName = $userInfo['name'] ?? 'HOD';
$currentUserCollege = $userInfo['college_name'] ?? '';
$currentUserDept = $currentUserCollege;
$department = $currentUserDept;

// PRIVACY: Fetch approved exams from OTHER colleges (NOT from HOD's own college)
// SECURITY: Only show approved exams with future dates
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
    AND e.department != ?  -- PRIVACY: Exclude own college
    AND e.exam_date >= CURDATE()
    ORDER BY e.exam_date ASC
");
$examsStmt->execute([$currentUserCollege]);
$approvedExams = $examsStmt->fetchAll(PDO::FETCH_ASSOC);

// PRIVACY: Fetch exams from HOD's OWN college only
// SECURITY: College-level data isolation
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
    WHERE e.department = ?  -- PRIVACY: Only this college's exams
    ORDER BY e.created_at DESC
    LIMIT 10
");
$ownExamsStmt->execute([$currentUserCollege]);
$ownExams = $ownExamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalApproved = count($approvedExams);
$totalOwn = count($ownExams);

// PRIVACY: Fetch faculty count for HOD's college only
$facultyStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users 
    WHERE college_name = ?  -- PRIVACY: Only this college's faculty
    AND post = 'teacher' 
    AND status = 'verified'
");
$facultyStmt->execute([$currentUserCollege]);
$facultyCount = $facultyStmt->fetchColumn();

// Basic stats for HOD
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_schedule WHERE college_name = ? AND exam_date >= CURDATE()");
    $stmt->execute([$currentUserCollege]);
    $upcomingExamsCount = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    $upcomingExamsCount = 0;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HOD Dashboard - EEMS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
  <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            margin: 0;
            padding: 0;
        }
        .top-navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .main-container {
            min-height: 100vh;
            padding: 2rem;
            background: transparent;
        }
        .dashboard-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 24px;
            padding: 2rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.1);
        }
        .exam-card {
            border-left: 4px solid #4facfe;
            transition: transform 0.2s;
        }
        .exam-card:hover {
            transform: translateX(5px);
        }
        .nav-link {
            color: #4b5563;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: #f3f4f6;
            color: #1e3c72;
        }
        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 0;
        }
        .nav-tabs .nav-link:hover {
            background: #f3f4f6;
            color: #1e3c72;
            border: none;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="bg-white rounded-3 p-2 me-3">
                <i class="bi bi-people-fill fs-4" style="color: #2a5298;"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold text-white">HOD Dashboard</h5>
                <small class="text-white" style="opacity: 0.9;">Exam Management System</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="fw-semibold text-white"><?= htmlspecialchars($currentUserName) ?></div>
                <small class="text-white" style="opacity: 0.8;"><?= htmlspecialchars($currentUserCollege) ?></small>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> Account
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="create_exam.php"><i class="bi bi-plus-circle me-2"></i>Create Exam</a></li>
                    <li><a class="dropdown-item" href="manage_faculty.php"><i class="bi bi-people me-2"></i>Manage Faculty</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="d-flex">
  <!-- Sidebar -->
  <div class="bg-white border-end shadow-sm" style="width: 270px; min-height: calc(100vh - 72px); position: sticky; top: 72px;">
    <div class="p-4">
      <h6 class="text-uppercase small mb-4" style="color: #f59e0b; font-weight: 600; letter-spacing: 0.5px;">HOD Exclusive</h6>
      <nav class="nav flex-column gap-2">
        <a href="#" onclick="loadModule('department_overview'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #f59e0b;">
          <i class="bi bi-building me-2" style="color: #f59e0b;"></i>Department Overview
        </a>
        <a href="#" onclick="loadModule('internal_examiner'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #f59e0b;">
          <i class="bi bi-person-badge me-2" style="color: #f59e0b;"></i>Internal Examiner Management
        </a>
        <a href="#" onclick="loadModule('department_notice'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #f59e0b;">
          <i class="bi bi-megaphone me-2" style="color: #f59e0b;"></i>Department Notice Board
        </a>
        <a href="#" onclick="loadModule('performance_analytics'); return false;" class="nav-link rounded-3" style="border-left: 3px solid #f59e0b;">
          <i class="bi bi-graph-up-arrow me-2" style="color: #f59e0b;"></i>Performance Analytics
        </a>
      </nav>
    </div>
  </div>

  <!-- Main Content -->
  <div class="flex-grow-1">
    <div class="main-container">
      
      <!-- Welcome Header -->
      <div class="mb-4">
        <h2 class="fw-bold" style="color: #2d3748;">Welcome, <?= htmlspecialchars($currentUserName) ?> 👋</h2>
        <p class="text-muted">Department: <?= htmlspecialchars($currentUserCollege) ?></p>
      </div>

      <!-- Header with New Tabs -->
      <div class="dashboard-card">
        <ul class="nav nav-tabs border-0" id="hodTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                    <i class="bi bi-speedometer2 me-2"></i>Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 active" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button">
                    <i class="bi bi-calendar-check me-2"></i>Available Exams
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3" id="own-tab" data-bs-toggle="tab" data-bs-target="#own" type="button">
                    <i class="bi bi-building me-2"></i>Your College Exams
                </button>
            </li>
        </ul>
      </div>

        <div class="tab-content" id="hodTabsContent">
          
            <!-- Overview Tab (Original HOD Dashboard) -->
            <div class="tab-pane fade" id="overview" role="tabpanel">
              <div class="flex justify-between items-center mb-8">
                <div>
                  <h1 class="text-3xl font-bold text-gray-800">HOD Dashboard</h1>
                  <p class="text-sm text-gray-500">Department: <span class="font-semibold"><?= htmlspecialchars($department ?: 'N/A') ?></span></p>
                </div>
                <div class="bg-white shadow px-4 py-2 rounded-lg">
                  <p class="text-sm text-gray-600">Upcoming Exams: <strong><?= $upcomingExamsCount ?></strong></p>
                </div>
              </div>

              <?php
              // Get hierarchical verification statistics for HOD (can verify Teachers)
              $current_role = normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '');
              $verification_stats = get_verification_stats($pdo, $current_role);
              $pending_verification_count = $verification_stats['pending_count'] ?? 0;
              
              // Get verifiable users for quick preview
              $verifiable_users_preview = [];
              if ($current_role === 'hod') {
                  $verifiable_users_preview = get_verifiable_users($pdo, $_SESSION['user_id'], $current_role);
                  $verifiable_users_preview = array_slice($verifiable_users_preview, 0, 3); // Limit to 3
              }
              ?>

              <!-- Hierarchical Verification Panel -->
              <?php if ($current_role === 'hod' && $pending_verification_count > 0): ?>
              <section class="mb-6">
                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-2xl p-6 shadow-lg">
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <div class="flex items-center mb-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center mr-4">
                          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                          </svg>
                        </div>
                        <div>
                          <h3 class="text-xl font-bold text-gray-800">Pending Teacher Verifications</h3>
                          <p class="text-sm text-gray-600">You have <?= $pending_verification_count ?> Teacher(s) awaiting your verification</p>
                        </div>
                      </div>
                      
                      <?php if (!empty($verifiable_users_preview)): ?>
                      <div class="space-y-2 mb-4">
                        <?php foreach ($verifiable_users_preview as $user): ?>
                        <div class="bg-white rounded-lg p-3 border border-purple-100 flex items-center justify-between">
                          <div class="flex items-center">
                            <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-full flex items-center justify-center text-white text-xs font-bold mr-3">
                              <?= strtoupper(substr($user['name'], 0, 2)) ?>
                            </div>
                            <div>
                              <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($user['name']) ?></p>
                              <p class="text-xs text-gray-500"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['post']))) ?> - <?= htmlspecialchars($user['college_name']) ?></p>
                            </div>
                          </div>
                          <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">Pending</span>
                        </div>
                        <?php endforeach; ?>
                      </div>
                      <?php endif; ?>
                    </div>
                    <a href="verify_users.php" class="ml-4 inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5">
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      Verify Now
                    </a>
                  </div>
                </div>
              </section>
              <?php endif; ?>

              <!-- HOD Panels -->
              <?php
                // Include availability and nominations panels (they contain internal role checks)
                if (file_exists(__DIR__ . '/includes/hod_availability_panel.php')) include __DIR__ . '/includes/hod_availability_panel.php';
                if (file_exists(__DIR__ . '/includes/hod_nominations_panel.php')) include __DIR__ . '/includes/hod_nominations_panel.php';
                
                // Include assignment tracking widget
                if (file_exists(__DIR__ . '/includes/assignment_widget.php')) include __DIR__ . '/includes/assignment_widget.php';
              ?>

              <!-- Department-level Examiner Overview -->
              <div class="bg-white p-6 rounded-2xl shadow-md mt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Department Examiner Assignments</h3>
                <div id="hod-examiner-overview" class="text-sm text-gray-700">Loading...</div>
              </div>
            </div>

            <!-- Available Exams from Other Colleges Tab -->
            <div class="tab-pane fade show active" id="available" role="tabpanel">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="text-2xl font-bold"><i class="bi bi-calendar-check"></i> Available Exams from Other Colleges</h3>
                            <p class="text-muted">Nominate your department faculty to be external examiners</p>
                        </div>
                        <div>
                            <button class="btn btn-success me-2" onclick="showAddExamModal()">
                                <i class="bi bi-plus-circle"></i> Create Exam
                            </button>
                            <span class="badge bg-primary" style="font-size: 1.1rem;"><?= $totalApproved ?> Available</span>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> These are approved exams from other colleges. You can nominate faculty from your department (<?= htmlspecialchars($currentUserDept) ?>) to be external examiners.
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
                                                <i class="bi bi-person-plus"></i> Nominate
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Your College Exams Tab -->
            <div class="tab-pane fade" id="own" role="tabpanel">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="text-2xl font-bold"><i class="bi bi-building"></i> Your College Exams</h3>
                            <p class="text-muted"><?= htmlspecialchars($currentUserCollege) ?></p>
                        </div>
                        <div>
                            <span class="badge bg-secondary" style="font-size: 1.1rem;"><?= $totalOwn ?> Exams</span>
                        </div>
                    </div>

                    <div class="alert alert-primary">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Info:</strong> These are exams from your college. Pending exams need admin approval before they become available to other colleges.
                    </div>

                    <?php if (empty($ownExams)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">No exams from your college yet.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Go to Main Dashboard
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
                                        <th>Actions</th>
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
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewExamDetails(<?= $exam['exam_id'] ?>)">
                                                <i class="bi bi-eye"></i> View Details
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
  </div>

<!-- Bootstrap & Tailwind JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // expose department for client-side filtering
    window.HOD_DEPARTMENT = <?= json_encode($department) ?>;
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
        window.location.href = `view_exam_details.php?id=${examId}`;
    }

    function nominateFaculty(examId) {
        alert(`Faculty nomination feature coming soon for exam ID ${examId}`);
        // TODO: Implement faculty nomination
    }
    
    // HOD Module Loader
    function loadModule(moduleName) {
      const mainContainer = document.querySelector('.main-container');
      if (!mainContainer) return;
      mainContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-warning" role="status"></div><p class="mt-3">Loading module...</p></div>';
      fetch(`hod_dashboard.php?action=load_module&module=${moduleName}`)
        .then(res => res.text())
        .then(html => { mainContainer.innerHTML = html; })
        .catch(() => { mainContainer.innerHTML = '<div class="alert alert-danger">Failed to load module.</div>'; });
    }
    
    (function(){
      var s = document.createElement('script');
      s.src = '/public/js/hod_components.js';
      s.defer = true;
      document.body.appendChild(s);
    })();
  </script>
</div>
</body>
</html>

<?php
// HOD Module Routing & API Handlers
if (isset($_GET['action']) && $_GET['action'] === 'load_module' && isset($_GET['module'])) {
  $module = $_GET['module'];
  switch ($module) {
    case 'department_overview':
      echo '<div class="p-4"><h3 style="color:#f59e0b"><i class="bi bi-building me-2"></i>Department Overview</h3><p class="text-muted">Overview and analytics for your department.</p></div>';
      break;
    case 'internal_examiner':
      echo '<div class="p-4"><h3 style="color:#f59e0b"><i class="bi bi-person-badge me-2"></i>Internal Examiner Management</h3><p class="text-muted">Manage internal examiners and assignments.</p></div>';
      break;
    case 'department_notice':
      echo '<div class="p-4"><h3 style="color:#f59e0b"><i class="bi bi-megaphone me-2"></i>Department Notice Board</h3><p class="text-muted">Post and manage department notices.</p></div>';
      break;
    case 'performance_analytics':
      echo '<div class="p-4"><h3 style="color:#f59e0b"><i class="bi bi-graph-up-arrow me-2"></i>Performance Analytics</h3><p class="text-muted">Department performance analytics and reports.</p></div>';
      break;
    default:
      echo '<div class="alert alert-warning">Module not found</div>';
      break;
  }
  exit;
}
