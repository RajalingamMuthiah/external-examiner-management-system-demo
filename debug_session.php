<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        h2 { color: #333; }
        pre { background: #eee; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Session Data</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
    
    <div class="box">
        <h2>Role Checks</h2>
        <p><strong>$_SESSION['role']:</strong> <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'NOT SET' ?></p>
        <p><strong>$_SESSION['user_role']:</strong> <?= isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role']) : 'NOT SET' ?></p>
        <p><strong>$_SESSION['post']:</strong> <?= isset($_SESSION['post']) ? htmlspecialchars($_SESSION['post']) : 'NOT SET' ?></p>
        <p><strong>$_SESSION['user_id']:</strong> <?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : 'NOT SET' ?></p>
        <p><strong>$_SESSION['user_name']:</strong> <?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'NOT SET' ?></p>
    </div>
    
    <div class="box">
        <h2>Teacher Dashboard Check</h2>
        <?php
        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
        $isTeacher = in_array(strtolower($role), ['teacher', 'faculty']);
        $hasUserId = isset($_SESSION['user_id']);
        ?>
        <p><strong>Role value:</strong> "<?= htmlspecialchars($role) ?>"</p>
        <p><strong>Lowercase role:</strong> "<?= htmlspecialchars(strtolower($role)) ?>"</p>
        <p><strong>Is Teacher/Faculty?</strong> <?= $isTeacher ? 'YES' : 'NO' ?></p>
        <p><strong>Has User ID?</strong> <?= $hasUserId ? 'YES' : 'NO' ?></p>
        <p><strong>Can Access Teacher Dashboard?</strong> <?= ($hasUserId && $isTeacher) ? 'YES - Should work!' : 'NO - Will redirect to login' ?></p>
    </div>
    
    <div class="box">
        <h2>Actions</h2>
        <p><a href="teacher_dashboard.php" style="color: blue;">Try to access Teacher Dashboard</a></p>
        <p><a href="login.php" style="color: blue;">Go to Login</a></p>
        <p><a href="logout.php" style="color: red;">Logout</a></p>
    </div>
</body>
</html>
