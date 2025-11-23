<?php
require_once __DIR__ . '/includes/functions.php';
start_secure_session();

header('Content-Type: application/json');

echo json_encode([
    'session_started' => session_status() === PHP_SESSION_ACTIVE,
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'name' => $_SESSION['name'] ?? null,
    'logged_in' => !empty($_SESSION['user_id']),
    'cookie_params' => session_get_cookie_params()
], JSON_PRETTY_PRINT);
