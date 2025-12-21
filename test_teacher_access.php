<?php
session_start();

echo "<h1>Teacher Dashboard Access Test</h1>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";
echo "<p>User Role: " . ($_SESSION['role'] ?? 'NOT SET') . "</p>";
echo "<p>User Name: " . ($_SESSION['name'] ?? 'NOT SET') . "</p>";

echo "<hr>";
echo "<h2>Test Results:</h2>";

if (empty($_SESSION['user_id'])) {
    echo "<p style='color:red;'>❌ NOT LOGGED IN - Would redirect to login.php</p>";
} else {
    echo "<p style='color:green;'>✅ LOGGED IN as User ID: " . $_SESSION['user_id'] . "</p>";
}

$role = $_SESSION['role'] ?? '';
echo "<p>Your current role: <strong>" . htmlspecialchars($role) . "</strong></p>";

$allowed_roles = ['teacher', 'faculty', 'admin'];
if (in_array(strtolower($role), array_map('strtolower', $allowed_roles))) {
    echo "<p style='color:green;'>✅ Your role '$role' IS ALLOWED to access teacher_dashboard.php</p>";
    echo "<p><a href='teacher_dashboard.php' style='color: blue; font-weight:bold;'>Click here to go to Teacher Dashboard</a></p>";
} else {
    echo "<p style='color:red;'>❌ Your role '$role' is NOT ALLOWED to access teacher_dashboard.php</p>";
    echo "<p>Allowed roles are: " . implode(', ', $allowed_roles) . "</p>";
}

echo "<hr>";
echo "<p><a href='admin_dashboard.php'>Back to Admin Dashboard</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";
?>
