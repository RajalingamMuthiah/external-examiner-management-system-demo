<?php
// HOD: Examiner nominations panel
// - Submit and view nominations for external/internal examiners for department

if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($role, ['hod','head','hod_incharge','admin'])) return;

if (!isset($pdo)) require_once __DIR__ . '/../config/db.php';

// Fetch recent nominations for this department
$dept = '';
try {
    $stmt = $pdo->prepare("SELECT dept FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $dept = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {
    $dept = '';
}

try {
    $stmt = $pdo->prepare("SELECT id, examiner_name, role, status, created_at FROM examiner_nominations WHERE dept = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$dept]);
    $noms = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $noms = [];
}

?>
<div class="bg-white p-6 rounded-2xl shadow-md mt-6">
  <h3 class="text-lg font-semibold text-gray-800 mb-3">Nominate Examiner</h3>

  <?php if (function_exists('generate_csrf_token')) { $csrf = generate_csrf_token(); } else { $csrf = ''; } ?>
  <form id="hod-nomination-form" class="grid grid-cols-1 md:grid-cols-3 gap-4" method="post">
    <div>
      <label class="block text-sm text-gray-700 mb-1">Examiner Name</label>
      <input type="text" id="nom_examiner_name" class="w-full px-3 py-2 border rounded" />
    </div>
    <div>
      <label class="block text-sm text-gray-700 mb-1">Role / Expertise</label>
      <input type="text" id="nom_role" class="w-full px-3 py-2 border rounded" />
    </div>
    <div class="md:self-end">
      <button id="hod-submit-nom" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Submit Nomination</button>
      <input type="hidden" id="hod_nom_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
    </div>
  </form>

  <h4 class="mt-6 text-sm font-semibold text-gray-700">Recent Nominations</h4>
  <?php if (empty($noms)): ?>
    <p class="text-sm text-gray-500">No nominations found.</p>
  <?php else: ?>
    <ul class="mt-2 space-y-2 text-sm text-gray-700">
      <?php foreach ($noms as $n): ?>
        <li class="flex justify-between items-center p-2 bg-gray-50 rounded">
          <div>
            <div class="font-semibold"><?= htmlspecialchars($n['examiner_name']) ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($n['role']) ?> • <?= date('M j, Y', strtotime($n['created_at'])) ?></div>
          </div>
          <div class="text-sm font-medium <?= $n['status']==='approved' ? 'text-green-600' : ($n['status']==='rejected' ? 'text-red-600' : 'text-amber-600') ?>"><?= htmlspecialchars($n['status']) ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

</div>
<script>
  // expose CSRF token for HOD nomination scripts
  window.CSRF_TOKEN = window.CSRF_TOKEN || <?= json_encode($csrf ?? '') ?>;
</script>
