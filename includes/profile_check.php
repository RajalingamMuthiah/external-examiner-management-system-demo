<?php
/**
 * Profile Completion Check Middleware
 * Redirects users to profile page if college/department is not set
 * Include this in dashboard pages after authentication check
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in - let the main auth check handle this
    return;
}

// Check if profile is complete
$userRole = normalize_role($_SESSION['role'] ?? '');

// Different roles have different requirements:
// - Admin: No college/dept required (system-wide access)
// - Principal, Vice Principal: Only college_id required (college-wide access)
// - HOD, Teacher: Both college_id and department_id required

$profileIncomplete = false;

if ($userRole === 'admin') {
    // Admin doesn't need college/department
    $profileIncomplete = false;
} elseif (in_array($userRole, ['principal', 'vice-principal', 'vice_principal'])) {
    // Principal and VP only need college_id
    $profileIncomplete = empty($_SESSION['college_id']);
} else {
    // HOD, Teacher, Faculty need both college_id and department_id
    $profileIncomplete = empty($_SESSION['college_id']) || empty($_SESSION['department_id']);
}

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);

// Don't redirect if already on profile page, logout page, or API endpoints
$excludedPages = ['user_profile.php', 'logout.php', 'login.php', 'register.php'];
$isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

if ($profileIncomplete && !in_array($currentPage, $excludedPages) && !$isApiRequest) {
    header('Location: user_profile.php?incomplete=1');
    exit;
}
?>
