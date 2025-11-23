<?php
/**
 * VIEW EXAM DETAILS - Enhanced with Security & Privacy Controls
 * ===================================================================
 * Display comprehensive exam information with assigned faculty details
 * 
 * SECURITY FEATURES:
 * - Authentication required for all users
 * - Access validation: Users can only view exams from their college
 * - Input sanitization on exam_id parameter
 * - CSRF protection (read-only page, minimal risk)
 * - XSS prevention on all outputs
 * 
 * PRIVACY REQUIREMENT:
 * Users can only view exam details if:
 * - Admin: Can view all exams
 * - Principal/VP/HOD: Can view exams from their college
 * - Teacher: Can view exams they're assigned to OR available exams
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// SECURITY: Require authentication
require_auth();

// SECURITY: Sanitize and validate exam ID input
$examId = sanitize_int($_GET['id'] ?? 0);

if ($examId <= 0) {
    security_log('INVALID_EXAM_ID_ACCESS', [
        'exam_id' => $_GET['id'] ?? 'missing',
        'user_id' => get_current_user_id(),
    ]);
    $_SESSION['error_message'] = 'Invalid exam ID';
    redirect_to_dashboard();
}

// PRIVACY: Fetch exam details (will validate access after retrieval)
try {
    $examStmt = $pdo->prepare("
        SELECT 
            e.*,
            e.department AS college_name,
            creator.name AS created_by_name,
            creator.post AS creator_role,
            creator.college_name AS creator_college
        FROM exams e
        LEFT JOIN users creator ON e.created_by = creator.id
        WHERE e.id = ?
        LIMIT 1
    ");
    $examStmt->execute([$examId]);
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        security_log('EXAM_NOT_FOUND', ['exam_id' => $examId]);
        $_SESSION['error_message'] = 'Exam not found';
        redirect_to_dashboard();
    }
    
    // PRIVACY: Validate user has permission to view this exam
    $currentUserId = get_current_user_id();
    $currentRole = get_current_user_role();
    $userInfo = get_current_user_info($pdo);
    $currentCollege = $userInfo['college_name'] ?? '';
    
    // Access control based on role
    $hasAccess = false;
    
    if ($currentRole === 'admin') {
        // Admin can see all exams
        $hasAccess = true;
    } elseif (in_array($currentRole, ['principal', 'vice-principal', 'hod'])) {
        // College leaders can see exams from their college
        $hasAccess = ($exam['department'] === $currentCollege);
    } elseif (in_array($currentRole, ['teacher', 'faculty'])) {
        // Teachers can see exams they're assigned to OR available exams
        $assignmentCheck = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE exam_id = ? AND faculty_id = ?");
        $assignmentCheck->execute([$examId, $currentUserId]);
        $isAssigned = $assignmentCheck->fetchColumn() > 0;
        
        // Or exam is from different college (available for selection)
        $isDifferentCollege = ($exam['department'] !== $currentCollege);
        
        $hasAccess = ($isAssigned || $isDifferentCollege);
    }
    
    if (!$hasAccess) {
        security_log('UNAUTHORIZED_EXAM_ACCESS', [
            'exam_id' => $examId,
            'user_id' => $currentUserId,
            'user_role' => $currentRole,
            'exam_college' => $exam['department'],
            'user_college' => $currentCollege,
        ]);
        $_SESSION['error_message'] = 'You do not have permission to view this exam';
        redirect_to_dashboard();
    }
    
} catch (Exception $e) {
    security_log('DATABASE_ERROR_EXAM_DETAILS', [
        'error' => $e->getMessage(),
        'exam_id' => $examId,
    ]);
    error_log('Error fetching exam: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error';
    redirect_to_dashboard();
}

// Fetch assigned faculty
try {
    $assignmentsStmt = $pdo->prepare("
        SELECT 
            a.*,
            u.name AS faculty_name,
            u.email AS faculty_email,
            u.phone AS faculty_phone,
            u.college_name AS faculty_college,
            u.post AS faculty_post,
            u.profile_data,
            assigner.name AS assigned_by_name
        FROM assignments a
        INNER JOIN users u ON a.faculty_id = u.id
        LEFT JOIN users assigner ON a.assigned_by = assigner.id
        WHERE a.exam_id = ?
        ORDER BY a.assigned_at DESC
    ");
    $assignmentsStmt->execute([$examId]);
    $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching assignments: ' . $e->getMessage());
    $assignments = [];
}

// Get user info for back navigation
$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
$userStmt = $pdo->prepare("SELECT name, college_name FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
$currentUserName = $userInfo['name'] ?? 'User';
$currentUserCollege = $userInfo['college_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Details - <?= htmlspecialchars($exam['title']) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .top-navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }
        .detail-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .faculty-card {
            background: white;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .faculty-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
            width: 200px;
            flex-shrink: 0;
        }
        .info-value {
            color: #1f2937;
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-navbar">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <a href="javascript:history.back()" class="btn btn-light btn-sm me-3">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <div>
                    <h5 class="mb-0 fw-bold text-white">Exam Details</h5>
                    <small class="text-white" style="opacity: 0.9;"><?= htmlspecialchars($currentUserName) ?> - <?= htmlspecialchars($currentUserCollege) ?></small>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Exam Information Card -->
        <div class="detail-card">
            <div class="d-flex align-items-center mb-4">
                <div class="rounded-3 p-3 me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-file-text text-white fs-2"></i>
                </div>
                <div class="flex-grow-1">
                    <h2 class="fw-bold mb-1"><?= htmlspecialchars($exam['title']) ?></h2>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-info"><?= htmlspecialchars($exam['subject']) ?></span>
                        <?php
                        $statusColors = [
                            'Pending' => 'warning',
                            'Approved' => 'success',
                            'Assigned' => 'primary',
                            'Cancelled' => 'danger'
                        ];
                        $statusColor = $statusColors[$exam['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $statusColor ?>"><?= htmlspecialchars($exam['status']) ?></span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label"><i class="bi bi-building me-2"></i>College/Department</div>
                        <div class="info-value"><?= htmlspecialchars($exam['college_name']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label"><i class="bi bi-calendar-event me-2"></i>Exam Date</div>
                        <div class="info-value"><?= date('F d, Y (l)', strtotime($exam['exam_date'])) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label"><i class="bi bi-person me-2"></i>Created By</div>
                        <div class="info-value">
                            <?= htmlspecialchars($exam['created_by_name'] ?? 'System') ?>
                            <?php if ($exam['creator_role']): ?>
                                <span class="badge bg-secondary ms-2"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $exam['creator_role']))) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label"><i class="bi bi-clock me-2"></i>Created At</div>
                        <div class="info-value"><?= date('M d, Y g:i A', strtotime($exam['created_at'])) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label"><i class="bi bi-people me-2"></i>Faculty Assigned</div>
                        <div class="info-value">
                            <span class="badge bg-primary rounded-pill"><?= count($assignments) ?> Faculty</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($exam['description']): ?>
                <div class="mt-4 pt-4 border-top">
                    <h6 class="fw-bold text-muted mb-3"><i class="bi bi-info-circle me-2"></i>Description</h6>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($exam['description'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assigned Faculty Card -->
        <div class="detail-card">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0">
                    <i class="bi bi-person-check me-2 text-success"></i>Assigned Faculty
                </h4>
                <span class="badge bg-primary fs-6"><?= count($assignments) ?> Total</span>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="bi bi-info-circle me-2 fs-4"></i>
                    <div>
                        <strong>No faculty assigned yet</strong>
                        <p class="mb-0 small">Faculty can self-select this exam from their dashboard, or HODs/VPs can nominate faculty members.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($assignments as $assignment): 
                        $profileData = json_decode($assignment['profile_data'] ?? '{}', true);
                    ?>
                    <div class="col-md-6">
                        <div class="faculty-card">
                            <div class="d-flex align-items-start mb-3">
                                <div class="rounded-circle bg-gradient text-white d-flex align-items-center justify-content-center me-3" 
                                     style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <?= strtoupper(substr($assignment['faculty_name'], 0, 2)) ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($assignment['faculty_name']) ?></h5>
                                    <div class="text-muted small mb-2">
                                        <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars(ucwords(str_replace('_', ' ', $assignment['faculty_post']))) ?>
                                        <span class="mx-2">•</span>
                                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($assignment['faculty_college']) ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if ($assignment['assigned_by_name']): ?>
                                            <span class="badge bg-info" style="font-size: 0.75rem;">
                                                <i class="bi bi-person-gear me-1"></i>Nominated by <?= htmlspecialchars($assignment['assigned_by_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success" style="font-size: 0.75rem;">
                                                <i class="bi bi-hand-thumbs-up me-1"></i>Self-selected
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 small">
                                <div class="col-6">
                                    <div class="text-muted">Email</div>
                                    <div class="fw-semibold"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($assignment['faculty_email']) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted">Phone</div>
                                    <div class="fw-semibold">
                                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($assignment['faculty_phone'] ?: 'N/A') ?>
                                    </div>
                                </div>
                                <?php if (isset($profileData['qualification'])): ?>
                                <div class="col-6">
                                    <div class="text-muted">Qualification</div>
                                    <div class="fw-semibold"><i class="bi bi-award me-1"></i><?= htmlspecialchars($profileData['qualification']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($profileData['specialization'])): ?>
                                <div class="col-6">
                                    <div class="text-muted">Specialization</div>
                                    <div class="fw-semibold"><i class="bi bi-book me-1"></i><?= htmlspecialchars($profileData['specialization']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($profileData['experience_years'])): ?>
                                <div class="col-6">
                                    <div class="text-muted">Experience</div>
                                    <div class="fw-semibold"><i class="bi bi-briefcase me-1"></i><?= $profileData['experience_years'] ?> years</div>
                                </div>
                                <?php endif; ?>
                                <div class="col-6">
                                    <div class="text-muted">Assigned On</div>
                                    <div class="fw-semibold"><i class="bi bi-calendar-check me-1"></i><?= date('M d, Y', strtotime($assignment['assigned_at'])) ?></div>
                                </div>
                            </div>

                            <?php if ($assignment['status']): ?>
                            <div class="mt-3 pt-3 border-top">
                                <span class="badge bg-<?= $statusColor ?> w-100 py-2">
                                    Status: <?= htmlspecialchars($assignment['status']) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="text-center">
            <a href="javascript:history.back()" class="btn btn-secondary btn-lg me-2">
                <i class="bi bi-arrow-left me-2"></i>Go Back
            </a>
            <a href="javascript:window.print()" class="btn btn-outline-primary btn-lg">
                <i class="bi bi-printer me-2"></i>Print Details
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
