<?php
require_once __DIR__ . '/includes/functions.php';
start_secure_session();

// Unset all session variables
$_SESSION = [];
// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Finally destroy the session
session_destroy();

set_flash('info', 'You have been logged out.');
header('Location: login.php?logged_out=1');
exit;
