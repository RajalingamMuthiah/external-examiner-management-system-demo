<?php
/**
 * check_pending_users.php
 *
 * A temporary diagnostic script to check for users with 'pending' status in the database.
 */

echo "<pre>"; // Use <pre> tag for readable output

// Include the database configuration
require_once __DIR__ . '/config/db.php';

echo "Attempting to connect to the database...\n";

try {
    // The $pdo object is now available from db.php
    echo "Database connection successful.\n\n";

    echo "Querying for users with 'pending' status...\n";
    
    // Prepare and execute the query
    $stmt = $pdo->prepare("SELECT id, name, email, status, created_at FROM users WHERE status = 'pending'");
    $stmt->execute();
    
    // Fetch all matching users
    $pending_users = $stmt->fetchAll();

    if (count($pending_users) > 0) {
        echo "Found " . count($pending_users) . " pending user(s):\n\n";
        print_r($pending_users);
    } else {
        echo "--------------------------------------------------\n";
        echo "RESULT: No users with 'pending' status were found in the 'users' table.\n";
        echo "This is likely why the admin dashboard appears empty.\n";
        echo "--------------------------------------------------\n";
    }

} catch (PDOException $e) {
    echo "--------------------------------------------------\n";
    echo "DATABASE ERROR:\n";
    echo "An error occurred while querying the database: " . $e->getMessage() . "\n";
    echo "Please check your database connection details in 'config/db.php'.\n";
    echo "--------------------------------------------------\n";
} catch (Exception $e) {
    echo "--------------------------------------------------\n";
    echo "SCRIPT ERROR:\n";
    echo "A general error occurred: " . $e->getMessage() . "\n";
    echo "--------------------------------------------------\n";
}

echo "</pre>";

?>
