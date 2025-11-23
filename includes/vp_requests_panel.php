<?php
// VP: Requests panel include
// Shows recent HOD nominations/requests with action buttons.
// Secure session & PDO prepared statements.

if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($role, ['vp','vice_principal','admin'])) return;

if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

try {
    $stmt = $pdo->prepare("SELECT id, hod_name, examiner_name, purpose, status, created_at FROM examiner_requests ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}
?>
<div class="dashboard-card">
  <div class="d-flex align-items-center mb-4">
    <div class="rounded-3 p-2 me-3" style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);">
      <i class="bi bi-inbox-fill text-white fs-4"></i>
    </div>
    <div>
      <h5 class="fw-bold mb-0">Recent Examiner Requests</h5>
      <small class="text-muted">Latest nominations from HODs</small>
    </div>
  </div>
  
  <?php if (empty($requests)): ?>
    <div class="alert alert-info d-flex align-items-center" role="alert">
      <i class="bi bi-info-circle me-2"></i>
      <span>No requests found.</span>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th class="fw-semibold">HOD</th>
            <th class="fw-semibold">Examiner</th>
            <th class="fw-semibold">Purpose</th>
            <th class="fw-semibold">Status</th>
            <th class="text-end fw-semibold">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['hod_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($r['examiner_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($r['purpose'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php 
                $statusClass = 'secondary';
                if ($r['status'] === 'Approved') $statusClass = 'success';
                elseif ($r['status'] === 'Rejected') $statusClass = 'danger';
                elseif ($r['status'] === 'Pending') $statusClass = 'warning';
                ?>
                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8'); ?></span>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-success vp-approve-request" data-id="<?php echo (int)$r['id']; ?>">
                    <i class="bi bi-check-lg"></i> Approve
                  </button>
                  <button class="btn btn-danger vp-reject-request" data-id="<?php echo (int)$r['id']; ?>">
                    <i class="bi bi-x-lg"></i> Reject
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- VP requests include end -->
