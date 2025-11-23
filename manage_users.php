<?php
require_once __DIR__ . '/config/db.php';

// Basic admin guard (if you have session-based auth, replace this with a real check)
session_start();
// ensure CSRF token is set for forms below
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables to safe defaults to prevent errors in the template if the try block fails
$users = [];
$counts = [
    'total' => 0, 'pending' => 0, 'verified' => 0,
    'rejected' => 0, 'faculty' => 0, 'verified_faculty' => 0,
];

try {
  $filter = $_GET['filter'] ?? '';
  $allowedPosts = ['teacher','hod','vice_principal','vice principal','vice-principal','vp','principal','professor','lecturer','faculty','assistant_professor','assoc_professor','asst_professor'];

  // Check if users table has a 'role' column to build queries safely
  $hasRoleColumn = false;
  try {
      $hasRoleColumn = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
  } catch (Exception $e) {
      // If SHOW COLUMNS fails for any reason (e.g. permissions), assume column is not there
      $hasRoleColumn = false;
  }
  
  // --- Start Count Queries ---
  $counts['total'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $counts['pending'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
  $counts['verified'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='verified'")->fetchColumn();
  $counts['rejected'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='rejected'")->fetchColumn();
  
  $facultyPlaceholders = implode(',', array_fill(0, count($allowedPosts), '?'));
  // Dynamically build the SQL condition based on whether the 'role' column exists
  $facultyConditionSQL = $hasRoleColumn
    ? "(role = 'faculty' OR post IN ($facultyPlaceholders))"
    : "post IN ($facultyPlaceholders)";

  $stmt_faculty_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $facultyConditionSQL");
  $stmt_faculty_count->execute($allowedPosts);
  $counts['faculty'] = (int) $stmt_faculty_count->fetchColumn();

  $stmt_verified_faculty_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'verified' AND $facultyConditionSQL");
  $stmt_verified_faculty_count->execute($allowedPosts);
  $counts['verified_faculty'] = (int) $stmt_verified_faculty_count->fetchColumn();
  // --- End Count Queries ---

  // --- Start Main Data Fetch Query ---
  $baseSql = "SELECT id, name, post, college_name, phone, email, status, created_at FROM users";
  $whereClause = '';
  $params = [];

  if ($filter === 'pending') {
    $whereClause = "WHERE status = 'pending'";
  } elseif ($filter === 'verified') {
    $whereClause = "WHERE status = 'verified'";
  } elseif ($filter === 'rejected') {
    $whereClause = "WHERE status = 'rejected'";
  } elseif ($filter === 'faculty') {
    $whereClause = "WHERE $facultyConditionSQL";
    $params = $allowedPosts;
  } elseif ($filter === 'verified_faculty') {
    $whereClause = "WHERE status = 'verified' AND $facultyConditionSQL";
    $params = $allowedPosts;
  }

  $sql = "$baseSql $whereClause ORDER BY created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // --- End Main Data Fetch Query ---

} catch (Exception $e) {
  error_log('manage_users error: ' . $e->getMessage());
  // On failure, $users and $counts are already set to safe defaults from before the try block
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Manage Users — EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">All Users / Registrations</h1>
        <?php if (!empty($filter)): ?>
          <p class="text-sm text-gray-500">Filter: <span class="font-medium"><?= htmlspecialchars($filter) ?></span></p>
        <?php endif; ?>
      </div>
      <div class="text-right">
        <a href="dashboard.php" class="text-sm text-blue-600">← Back to dashboard</a>
      </div>
    </div>

<div class="mb-4">
        <a href="?" class="px-3 py-1.5 rounded-lg <?= empty($filter) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?>">Total Users (<?= $counts['total'] ?>)</a>
        <a href="?filter=pending" class="px-3 py-1.5 rounded-lg <?= $filter === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-700' ?>">Pending (<?= $counts['pending'] ?>)</a>
        <a href="?filter=verified" class="px-3 py-1.5 rounded-lg <?= $filter === 'verified' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700' ?>">Verified (<?= $counts['verified'] ?>)</a>
        <a href="?filter=rejected" class="px-3 py-1.5 rounded-lg <?= $filter === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700' ?>">Rejected (<?= $counts['rejected'] ?>)</a>
        <a href="?filter=faculty" class="px-3 py-1.5 rounded-lg <?= $filter === 'faculty' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700' ?>">All Faculty (<?= $counts['faculty'] ?>)</a>
        <a href="?filter=verified_faculty" class="px-3 py-1.5 rounded-lg <?= $filter === 'verified_faculty' ? 'bg-sky-600 text-white' : 'bg-gray-200 text-gray-700' ?>">Verified Faculty (<?= $counts['verified_faculty'] ?>)</a>
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
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (empty($users)): ?>
            <tr>
              <td class="px-4 py-6 text-center" colspan="8">No users found for this filter.</td>
            </tr>
          <?php else: foreach ($users as $u): ?>
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
              <td class="px-4 py-3 text-right text-sm">
                <?php if ($u['status'] === 'pending'): ?>
                  <form method="post" action="verify_users.php" style="display:inline" onsubmit="return confirm('Verify this user?')">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <input type="hidden" name="from" value="manage">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="text-sm text-green-600 hover:underline mr-3">Verify</button>
                  </form>
                  <form method="post" action="verify_users.php" style="display:inline" onsubmit="return confirm('Reject this user? This will mark the registration as rejected.')">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <input type="hidden" name="from" value="manage">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="text-sm text-red-600 hover:underline">Reject</button>
                  </form>
                <?php elseif ($u['status'] === 'rejected'): ?>
                  <form method="post" action="verify_users.php" style="display:inline" onsubmit="return confirm('Restore this rejected user back to pending?')">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <input type="hidden" name="from" value="manage">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="text-sm text-indigo-600 hover:underline mr-3">Restore</button>
                  </form>
                  <form method="post" action="verify_users.php" style="display:inline" onsubmit="return confirm('Delete this user permanently? This action cannot be undone.')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <input type="hidden" name="from" value="manage">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="text-sm text-red-600 hover:underline">Delete</button>
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

