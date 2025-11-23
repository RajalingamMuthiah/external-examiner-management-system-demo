<?php
require_once __DIR__ . '/includes/functions.php';
start_secure_session();
require_once __DIR__ . '/config/db.php';

// Require login
require_login();

// Get current user's role
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
$current_user_role = normalize_role($current_user_role);

// Check if user has verification authority
$can_verify = in_array($current_user_role, ['admin', 'principal', 'vice-principal', 'hod']);
if (!$can_verify) {
    http_response_code(403);
    echo "<h1>403 - Unauthorized</h1><p>You do not have permission to verify users.</p>";
    exit;
}

// Recompute dashboard-like counts so this page shows up-to-date totals
try {
  $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $pendingUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
  $verifiedUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='verified'")->fetchColumn();
} catch (Exception $e) {
  error_log('verify_users stats error: ' . $e->getMessage());
  $totalUsers = $pendingUsers = $verifiedUsers = 0;
}

// ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Prefer POST for state-changing actions. Validate CSRF token on POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
  $from = $_POST['from'] ?? '';
  $token = $_POST['csrf_token'] ?? '';

  if (!hash_equals($_SESSION['csrf_token'], (string) $token)) {
    // invalid CSRF
    if ($from === 'manage') header('Location: ./manage_users.php?error=csrf');
    else header('Location: ./verify_users.php?error=csrf');
    exit;
  }

  try {
    if ($action === 'verify') {
      $sql = "UPDATE users SET status = 'verified' WHERE id = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$id]);
      if ($from === 'manage') header('Location: ./manage_users.php?success=1');
      else header('Location: ./verify_users.php?success=1');
      exit;
    }

    if ($action === 'reject') {
      $sql = "UPDATE users SET status = 'rejected' WHERE id = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$id]);
      if ($from === 'manage') header('Location: ./manage_users.php?rejected=1');
      else header('Location: ./verify_users.php?rejected=1');
      exit;
    }

    if ($action === 'restore') {
      $sql = "UPDATE users SET status = 'pending' WHERE id = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$id]);
      if ($from === 'manage') header('Location: ./manage_users.php?restored=1');
      else header('Location: ./verify_users.php?restored=1');
      exit;
    }

    if ($action === 'delete') {
      $sql = "DELETE FROM users WHERE id = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$id]);
      if ($from === 'manage') header('Location: ./manage_users.php?deleted=1');
      else header('Location: ./verify_users.php?deleted=1');
      exit;
    }
  } catch (PDOException $e) {
    error_log('Action failed: ' . $e->getMessage());
    if ($from === 'manage') header('Location: ./manage_users.php?error=1');
    else header('Location: ./verify_users.php?error=1');
    exit;
  }
}

// Handle user verification (keeps GET behavior for now; can be converted to POST later)
if (isset($_GET['verify_id'])) {
  $id = (int) $_GET['verify_id'];
  try {
    $sql = "UPDATE users SET status = 'verified' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    // After verifying, prefer to return to the referring page (manage_users.php) so the change is visible
    // Prefer explicit from=manage param to return to Manage Users view
    if (isset($_GET['from']) && $_GET['from'] === 'manage') {
      header('Location: ./manage_users.php?success=1');
      exit;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?success=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      // preserve a success query if not present
      if (strpos($return, 'success=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'success=1';
      }
    }
    header("Location: " . $return);
    exit;
  } catch (PDOException $e) {
    error_log('Verification failed: ' . $e->getMessage());
    if (isset($_GET['from']) && $_GET['from'] === 'manage') {
      header('Location: ./manage_users.php?error=1');
      exit;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?error=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'error=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'error=1';
      }
    }
    header("Location: " . $return);
    exit;
  }
}

// Handle user rejection
if (isset($_GET['reject_id'])) {
  $id = (int) $_GET['reject_id'];
  try {
    $sql = "UPDATE users SET status = 'rejected' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    if (isset($_GET['from']) && $_GET['from'] === 'manage') {
      header('Location: ./manage_users.php?rejected=1');
      exit;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?rejected=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'rejected=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'rejected=1';
      }
    }
    header("Location: " . $return);
    exit;
  } catch (PDOException $e) {
    error_log('Rejection failed: ' . $e->getMessage());
    if (isset($_GET['from']) && $_GET['from'] === 'manage') {
      header('Location: ./manage_users.php?error=1');
      exit;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?error=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'error=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'error=1';
      }
    }
    header("Location: " . $return);
    exit;
  }
}

// Handle restore of rejected user -> back to pending
if (isset($_GET['restore_id'])) {
  $id = (int) $_GET['restore_id'];
  try {
    $sql = "UPDATE users SET status = 'pending' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    if (isset($_GET['from']) && $_GET['from'] === 'manage') {
      header('Location: ./manage_users.php?restored=1');
      exit;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?restored=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'restored=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'restored=1';
      }
    }
    header("Location: " . $return);
    exit;
  } catch (PDOException $e) {
    error_log('Restore failed: ' . $e->getMessage());
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?error=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'error=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'error=1';
      }
    }
    header("Location: " . $return);
    exit;
  }
}

// Handle delete user (permanent)
if (isset($_GET['delete_id'])) {
  $id = (int) $_GET['delete_id'];
  try {
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    if (isset($_GET['from']) && $_GET['from'] === 'manage') {
      header('Location: ./manage_users.php?deleted=1');
      exit;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?deleted=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'deleted=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'deleted=1';
      }
    }
    header("Location: " . $return);
    exit;
  } catch (PDOException $e) {
    error_log('Delete failed: ' . $e->getMessage());
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $return = './verify_users.php?error=1';
    if ($referer && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
      $return = $referer;
      if (strpos($return, 'error=1') === false) {
        $sep = strpos($return, '?') === false ? '?' : '&';
        $return .= $sep . 'error=1';
      }
    }
    header("Location: " . $return);
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Users</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
  <h1 class="text-3xl font-bold mb-6 text-center">Pending User Verifications</h1>

  <p class="text-center mb-4">
    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">← Back to Dashboard</a>
  </p>

  <?php if (isset($_GET['success'])): ?>
    <p class="text-green-700 bg-green-100 p-2 rounded text-center mb-4">
      ✅ User verified successfully!
    </p>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
    <p class="text-red-700 bg-red-100 p-2 rounded text-center mb-4">
      ❌ Error occurred during verification. Please try again.
    </p>
  <?php endif; ?>
  <?php if (isset($_GET['rejected'])): ?>
    <p class="text-red-700 bg-red-100 p-2 rounded text-center mb-4">
      ⛔ User rejected successfully.
    </p>
  <?php endif; ?>
  <?php if (isset($_GET['restored'])): ?>
    <p class="text-green-700 bg-green-100 p-2 rounded text-center mb-4">
      ♻️ User restored to pending successfully.
    </p>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
    <p class="text-red-700 bg-red-100 p-2 rounded text-center mb-4">
      🗑️ User deleted successfully.
    </p>
  <?php endif; ?>

  <div class="overflow-x-auto">
    <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
      <thead>
        <tr class="bg-gray-200 text-gray-700 text-left">
          <th class="py-2 px-4">ID</th>
          <th class="py-2 px-4">Name</th>
          <th class="py-2 px-4">Post</th>
          <th class="py-2 px-4">College</th>
          <th class="py-2 px-4">Email</th>
          <th class="py-2 px-4">Phone</th>
          <th class="py-2 px-4">Status</th>
          <th class="py-2 px-4">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // Re-query pending users (counts at top are already computed earlier)
        $stmt = $pdo->query("SELECT * FROM users WHERE status = 'pending'");
        $users = $stmt->fetchAll();

        if ($users) {
          foreach ($users as $user) {
            ?>
            <tr class="border-t">
              <td class="py-2 px-4"><?= htmlspecialchars($user['id']) ?></td>
              <td class="py-2 px-4"><?= htmlspecialchars($user['name']) ?></td>
              <td class="py-2 px-4"><?= htmlspecialchars($user['post']) ?></td>
              <td class="py-2 px-4"><?= htmlspecialchars($user['college_name']) ?></td>
              <td class="py-2 px-4"><?= htmlspecialchars($user['email']) ?></td>
              <td class="py-2 px-4"><?= htmlspecialchars($user['phone']) ?></td>
              <td class="py-2 px-4 text-yellow-600 font-semibold"><?= htmlspecialchars($user['status']) ?></td>
              <td class="py-2 px-4">
                <a href="verify_users.php?verify_id=<?= urlencode($user['id']) ?>" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700" onclick="return confirm('Verify this user?')">Verify ✅</a>
                <a href="verify_users.php?reject_id=<?= urlencode($user['id']) ?>" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 ml-2" onclick="return confirm('Reject this user? This will mark the registration as rejected.')">Reject ✖</a>
              </td>
            </tr>
            <?php
          }
        } else {
          echo "<tr><td colspan='8' class='text-center py-4 text-gray-500'>No pending users.</td></tr>";
        }
        ?>
      </tbody>  
    </table>
  </div>
</body>
</html>
