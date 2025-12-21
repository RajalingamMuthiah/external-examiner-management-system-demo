<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Admin Dashboard Test</h2>";

// Test 1: Check if functions.php exists
echo "<h3>1. Checking functions.php</h3>";
if (file_exists(__DIR__ . '/includes/functions.php')) {
    echo "✓ functions.php exists<br>";
    require_once __DIR__ . '/includes/functions.php';
    echo "✓ functions.php loaded successfully<br>";
} else {
    echo "✗ functions.php NOT FOUND<br>";
}

// Test 2: Check database connection
echo "<h3>2. Checking Database Connection</h3>";
try {
    require_once __DIR__ . '/config/db.php';
    if (isset($pdo)) {
        echo "✓ Database connection successful<br>";
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "✓ Database query successful - Found " . $result['count'] . " users<br>";
    } else {
        echo "✗ PDO object not created<br>";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Test 3: Check session
echo "<h3>3. Checking Session</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✓ Session already active<br>";
} else {
    session_start();
    echo "✓ Session started<br>";
}

// Test 4: Check if user is logged in
echo "<h3>4. Checking Login Status</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✓ User logged in - ID: " . $_SESSION['user_id'] . "<br>";
    echo "✓ Role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
} else {
    echo "✗ User NOT logged in<br>";
    echo "→ You need to <a href='login.php'>login first</a><br>";
}

echo "<h3>5. Next Steps</h3>";
if (!isset($_SESSION['user_id'])) {
    echo "Please login at: <a href='login.php'>login.php</a><br>";
} else {
    echo "Try accessing: <a href='admin_dashboard.php'>admin_dashboard.php</a><br>";
}
?>
