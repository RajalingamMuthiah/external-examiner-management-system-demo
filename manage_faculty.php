<?php
require_once __DIR__ . '/config/db.php';

session_start();
// ensure CSRF token is set for forms below
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables to safe defaults to prevent errors if the try block fails
$facultyMembers = [];
$counts = ['all_faculty' => 0, 'verified_faculty' => 0];

try {
  $filter = $_GET['filter'] ?? '';
  $allowedPosts = ['teacher','hod','vice_principal','vice principal','vice-principal','vp','principal','professor','lecturer','faculty','assistant_professor','assoc_professor','asst_professor'];

  // Check if users table has a 'role' column to build queries safely
  $hasRoleColumn = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
  
  $facultyPlaceholders = implode(',', array_fill(0, count($allowedPosts), '?'));
  $facultyConditionSQL = $hasRoleColumn
    ? "(role = 'faculty' OR post IN ($facultyPlaceholders))"
    : "post IN ($facultyPlaceholders)";

  // --- Start Count Queries ---
  $stmt_all_faculty_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $facultyConditionSQL");
  $stmt_all_faculty_count->execute($allowedPosts);
  $counts['all_faculty'] = (int) $stmt_all_faculty_count->fetchColumn();

  $stmt_verified_faculty_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'verified' AND $facultyConditionSQL");
  $stmt_verified_faculty_count->execute($allowedPosts);
  $counts['verified_faculty'] = (int) $stmt_verified_faculty_count->fetchColumn();
  // --- End Count Queries ---

  // --- Start Main Data Fetch Query ---
  $baseSql = "SELECT id, name, post, college_name, phone, email, status, created_at FROM users";
  $whereClause = "WHERE $facultyConditionSQL";
  $params = $allowedPosts;

  if ($filter === 'verified_faculty') {
    $whereClause = "WHERE status = 'verified' AND $facultyConditionSQL";
  }

  $sql = "$baseSql $whereClause ORDER BY created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $facultyMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // --- End Main Data Fetch Query ---

} catch (Exception $e) {
  error_log('manage_faculty error: ' . $e->getMessage());
  $facultyMembers = []; // Ensure empty array on failure
  $counts = ['all_faculty' => 0, 'verified_faculty' => 0]; // Ensure zero counts
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Manage Faculty — EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Manage Faculty</h1>
        <p class="text-sm text-gray-500">Showing all registered faculty members.</p>
      </div>
      <div class="text-right">
        <a href="dashboard.php" class="text-sm text-blue-600">← Back to dashboard</a>
      </div>
    </div>

    <div class="mb-4">
      <a href="manage_faculty.php" class="px-3 py-1.5 rounded-lg <?= empty($filter) ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700' ?>">All Faculty (<?= $counts['all_faculty'] ?>)</a>
      <a href="manage_faculty.php?filter=verified_faculty" class="px-3 py-1.5 rounded-lg <?= $filter === 'verified_faculty' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700' ?>">Verified Faculty (<?= $counts['verified_faculty'] ?>)</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Post</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (empty($facultyMembers)): ?>
            <tr>
              <td class="px-4 py-6 text-center" colspan="8">No faculty members found for this filter.</td>
            </tr>
          <?php else: foreach ($facultyMembers as $u): ?>
            <tr>
              <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($u['id']) ?></td>
              <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($u['name']) ?></td>
              <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($u['post']) ?></td>
              <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($u['email']) ?></td>
              <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($u['phone']) ?></td>
              <td class="px-4 py-3 text-sm">
                <?php if ($u['status'] === 'pending'): ?>
                  <span class="inline-block bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Pending</span>
                <?php elseif ($u['status'] === 'verified'): ?>
                  <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-xs">Verified</span>
                <?php elseif ($u['status'] === 'rejected'): ?>
                  <span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded text-xs">Rejected</span>
                <?php else: ?>
                  <span class="inline-block bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs"><?= htmlspecialchars($u['status']) ?></span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars($u['created_at']) ?></td>
              <td class="px-4 py-3 text-left text-sm">
                <?php if ($u['status'] === 'pending'): ?>
                  <form method="post" action="verify_users.php" style="display:inline" onsubmit="return confirm('Verify this user?')">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <input type="hidden" name="from" value="manage_faculty">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="text-sm text-green-600 hover:underline">Verify</button>
                  </form>
                <?php else: ?>
                  <span class="text-sm text-gray-500">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>