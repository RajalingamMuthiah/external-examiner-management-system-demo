<?php
// HOD: Faculty availability panel
// - Allows marking unavailable dates for department faculty
// - Shows simple conflict checks using exam_schedule

if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($role, ['hod','head','hod_incharge','admin'])) return;

if (!isset($pdo)) require_once __DIR__ . '/../config/db.php';

// Get faculty in department
$dept = '';
try {
    $stmt = $pdo->prepare("SELECT dept FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $dept = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {
    $dept = '';
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE dept = ? AND status='verified'");
    $stmt->execute([$dept]);
    $facultyList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $facultyList = [];
}

?>
<div class="bg-white p-6 rounded-2xl shadow-md">
  <h3 class="text-lg font-semibold text-gray-800 mb-3">Faculty Availability & Conflict Checker</h3>

  <?php if (empty($facultyList)): ?>
    <p class="text-sm text-gray-500">No verified faculty found in your department.</p>
  <?php else: ?>
    <?php // generate CSRF token for these forms ?>
    <?php if (function_exists('generate_csrf_token')) { $csrf = generate_csrf_token(); } else { $csrf = ''; } ?>
    <form id="hod-availability-form" class="grid grid-cols-1 md:grid-cols-3 gap-4" method="post">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Select Faculty</label>
        <select id="hod_faculty_id" name="faculty_id" class="w-full px-3 py-2 border rounded">
          <option value="">-- Choose Faculty --</option>
          <?php foreach ($facultyList as $f): ?>
            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-700 mb-1">Select Date</label>
        <input type="date" id="hod_unavailable_date" class="w-full px-3 py-2 border rounded" />
      </div>

      <div class="md:self-end">
        <button id="hod-mark-unavailable" class="bg-cyan-500 hover:bg-cyan-600 text-white px-4 py-2 rounded">Mark Unavailable</button>
        <input type="hidden" id="hod_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
      </div>
    </form>

    <div id="hod-availability-result" class="mt-4 text-sm text-gray-700"></div>
  <?php endif; ?>

</div>

<script>
  // expose CSRF token to HOD client scripts (same-origin)
  window.CSRF_TOKEN = window.CSRF_TOKEN || <?= json_encode($csrf ?? '') ?>;
</script>


<!-- HOD availability panel end -->
