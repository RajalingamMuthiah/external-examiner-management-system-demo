<?php
// Recent Exam Assignments Widget
// Shows recent teacher/HOD exam selections across all dashboards
// Can be included in Principal, VP, and HOD dashboards

if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

// Get current user's college for filtering
$currentUserCollege = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT college_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentUserCollege = $userInfo['college_name'] ?? '';
    } catch (Exception $e) {
        error_log('Error fetching user college: ' . $e->getMessage());
    }
}

// Fetch recent assignments
try {
    $assignmentsStmt = $pdo->prepare("
        SELECT 
            a.id,
            a.exam_id,
            a.assigned_at,
            a.role AS assignment_role,
            a.status,
            u.name AS faculty_name,
            u.college_name AS faculty_college,
            u.email AS faculty_email,
            u.post AS faculty_post,
            e.title AS exam_name,
            e.subject,
            e.exam_date,
            e.department AS exam_college,
            assigner.name AS assigned_by_name
        FROM assignments a
        INNER JOIN users u ON a.faculty_id = u.id
        INNER JOIN exams e ON a.exam_id = e.id
        LEFT JOIN users assigner ON a.assigned_by = assigner.id
        WHERE e.department = ?
        ORDER BY a.assigned_at DESC
        LIMIT 10
    ");
    $assignmentsStmt->execute([$currentUserCollege]);
    $recentAssignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching assignments: ' . $e->getMessage());
    $recentAssignments = [];
}

// Get assignment statistics
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.faculty_id) AS total_faculty_assigned,
            COUNT(a.id) AS total_assignments,
            COUNT(DISTINCT a.exam_id) AS exams_with_assignments
        FROM assignments a
        INNER JOIN exams e ON a.exam_id = e.id
        WHERE e.department = ?
    ");
    $statsStmt->execute([$currentUserCollege]);
    $assignmentStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assignmentStats = [
        'total_faculty_assigned' => 0,
        'total_assignments' => 0,
        'exams_with_assignments' => 0
    ];
}
?>

<!-- Recent Exam Assignments Widget -->
<div class="dashboard-card">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <div class="rounded-3 p-2 me-3" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="bi bi-calendar-check text-white fs-4"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0">Recent Exam Assignments</h5>
                <small class="text-muted">Exams from your college</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary rounded-pill"><?= $assignmentStats['total_assignments'] ?> Total</span>
            <span class="badge bg-success rounded-pill"><?= $assignmentStats['total_faculty_assigned'] ?> Faculty</span>
        </div>
    </div>

    <?php if (empty($recentAssignments)): ?>
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <span>No exam assignments yet. Teachers can self-select exams or HODs can nominate faculty.</span>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Faculty</th>
                        <th>Exam Details</th>
                        <th>College</th>
                        <th>Date</th>
                        <th>Assigned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAssignments as $assignment): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" 
                                     style="width: 35px; height: 35px; font-size: 0.875rem; font-weight: 600;">
                                    <?= strtoupper(substr($assignment['faculty_name'], 0, 2)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($assignment['faculty_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $assignment['faculty_post']))) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($assignment['exam_name']) ?></div>
                            <small class="text-muted">
                                <i class="bi bi-book me-1"></i><?= htmlspecialchars($assignment['subject']) ?>
                            </small>
                            <br>
                            <a href="view_exam_details.php?id=<?= $assignment['exam_id'] ?>" class="btn btn-sm btn-outline-info mt-1">
                                <i class="bi bi-eye me-1"></i>View Details
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($assignment['faculty_college']) ?></span>
                        </td>
                        <td>
                            <small class="text-muted"><?= date('M d, Y', strtotime($assignment['exam_date'])) ?></small>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= date('M d, H:i', strtotime($assignment['assigned_at'])) ?>
                                <?php if ($assignment['assigned_by_name']): ?>
                                    <br><span class="badge bg-secondary" style="font-size: 0.7rem;">by <?= htmlspecialchars($assignment['assigned_by_name']) ?></span>
                                <?php else: ?>
                                    <br><span class="badge bg-success" style="font-size: 0.7rem;">Self-selected</span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <?php
                            $statusClass = 'secondary';
                            if ($assignment['status'] === 'Assigned') $statusClass = 'primary';
                            elseif ($assignment['status'] === 'Confirmed') $statusClass = 'success';
                            elseif ($assignment['status'] === 'Cancelled') $statusClass = 'danger';
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($assignment['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($recentAssignments) >= 10): ?>
            <div class="text-center mt-3">
                <a href="#" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-right me-1"></i>View All Assignments
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
