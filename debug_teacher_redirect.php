<?php
session_start();

echo "<h1>Teacher Dashboard Redirect Debug</h1>";
echo "<hr>";

echo "<h2>Session Information:</h2>";
echo "<pre>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "Name: " . ($_SESSION['name'] ?? 'NOT SET') . "\n";
echo "College ID: " . ($_SESSION['college_id'] ?? 'NOT SET') . "\n";
echo "Department ID: " . ($_SESSION['department_id'] ?? 'NOT SET') . "\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Test Navigation:</h2>";

// Method 1: Direct link
echo "<p><strong>Method 1:</strong> <a href='teacher_dashboard.php'>Direct link to teacher_dashboard.php</a></p>";

// Method 2: JavaScript redirect button
echo "<p><strong>Method 2:</strong> <button onclick=\"window.location.href='teacher_dashboard.php'\">JavaScript redirect</button></p>";

// Method 3: Form POST
echo "<p><strong>Method 3:</strong></p>";
echo "<form action='teacher_dashboard.php' method='GET'>";
echo "<button type='submit'>Form GET redirect</button>";
echo "</form>";

// Method 4: Meta refresh (will activate in 10 seconds)
echo "<hr>";
echo "<h2>Automatic Tests:</h2>";
echo "<p>In 10 seconds, this page will automatically redirect to teacher_dashboard.php...</p>";
echo "<meta http-equiv='refresh' content='10;url=teacher_dashboard.php'>";

echo "<hr>";
echo "<p><a href='admin_dashboard.php'>Back to Admin Dashboard</a></p>";
?>
