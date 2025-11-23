<?php
/**
 * Diagnostic page to test admin dashboard functionality
 */

// Check if session is working
session_start();
echo "<h2>Session Diagnostics</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "</pre>";

// Check database connection
echo "<h2>Database Connection</h2>";
try {
    require_once __DIR__ . '/config/db.php';
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Total users in database: " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check if logged in
echo "<h2>Login Status</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User is logged in</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    echo "<p>Name: " . ($_SESSION['name'] ?? 'Not set') . "</p>";
} else {
    echo "<p style='color: red;'>❌ No active session - User NOT logged in</p>";
    echo "<p>You need to login first at: <a href='admin_login.php'>admin_login.php</a></p>";
}

// Check admin dashboard access
echo "<h2>Admin Dashboard Access</h2>";
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'])) {
    echo "<p style='color: green;'>✅ User has permission to access admin dashboard</p>";
    echo "<p><a href='admin_dashboard.php' style='padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px;'>Open Admin Dashboard</a></p>";
} else {
    echo "<p style='color: red;'>❌ User does NOT have admin/principal role</p>";
}

// PHP Info
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "</p>";
echo "<p>Error Reporting: " . ini_get('error_reporting') . "</p>";

// File permissions
echo "<h2>File Checks</h2>";
$files = [
    'admin_dashboard.php',
    'config/db.php',
    'includes/functions.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file NOT FOUND</p>";
    }
}

// JavaScript test
echo "<h2>JavaScript Test</h2>";
echo "<div id='js-test' style='color: red;'>❌ JavaScript NOT working</div>";
echo "<script>
document.getElementById('js-test').innerHTML = '<span style=\"color: green;\">✅ JavaScript is working</span>';
</script>";

// jQuery test
echo "<h2>jQuery Test</h2>";
echo "<div id='jquery-test' style='color: red;'>❌ jQuery NOT loaded</div>";
echo "<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>";
echo "<script>
$(document).ready(function() {
    $('#jquery-test').html('<span style=\"color: green;\">✅ jQuery is loaded and working</span>');
});
</script>";

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If not logged in: <a href='admin_login.php'>Login here</a></li>";
echo "<li>If logged in but wrong role: Check your user's 'post' column in database</li>";
echo "<li>If all checks pass: <a href='admin_dashboard.php'>Try Admin Dashboard</a></li>";
echo "</ol>";
?>
