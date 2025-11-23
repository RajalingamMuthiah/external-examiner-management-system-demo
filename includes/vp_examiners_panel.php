<?php
// VP: Examiners panel include
// Shows department-wise summary and a client container for the searchable examiner table.
// Secure session & role check.

if (session_status() === PHP_SESSION_NONE) session_start();

// Only visible to VP or Admin
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($role, ['vp','vice_principal','admin'])) {
    return;
}

// Ensure $pdo exists (use existing config/db.php)
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

try {
    $stmt = $pdo->prepare("SELECT dept, COUNT(*) AS cnt FROM external_examiners GROUP BY dept ORDER BY cnt DESC");
    $stmt->execute();
    $deptCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $deptCounts = [];
}
?>
<!-- VP: Examiners Panel (server-rendered summary + client container) -->
<div class="dashboard-card">
  <div class="d-flex align-items-center mb-4">
    <div class="rounded-3 p-2 me-3" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
      <i class="bi bi-people-fill text-white fs-4"></i>
    </div>
    <div>
      <h5 class="fw-bold mb-0">External Examiners</h5>
      <small class="text-muted">Department Summary</small>
    </div>
  </div>
  
  <?php if (empty($deptCounts)): ?>
    <div class="alert alert-info d-flex align-items-center" role="alert">
      <i class="bi bi-info-circle me-2"></i>
      <span>No examiner data available.</span>
    </div>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <?php foreach ($deptCounts as $d): ?>
        <div class="col-md-6">
          <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center">
              <i class="bi bi-mortarboard-fill text-primary me-2 fs-5"></i>
              <span class="fw-semibold"><?php echo htmlspecialchars($d['dept'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <span class="badge bg-primary rounded-pill fs-6"><?php echo (int)$d['cnt']; ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Client: container where client JS will render the full searchable table -->
  <div id="vp-examiners-client" class="mt-4"></div>

  <div class="mt-4">
    <button id="vp-open-examiner-search" class="btn btn-primary px-4 py-2">
      <i class="bi bi-search me-2"></i>Open Examiner Search
    </button>
  </div>
</div>

<!-- VP include end -->
