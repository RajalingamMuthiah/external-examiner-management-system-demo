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

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verify_csrf_token($token)) {
        $message = 'Invalid CSRF token';
        $message_type = 'error';
    } else if ($user_id > 0) {
        if ($action === 'verify') {
            // Verify user and generate password
            $result = verify_user_and_send_password($pdo, $user_id, $current_user_id);
            
            if ($result['success']) {
                $message = $result['message'] . " (Password: " . $result['password'] . ")";
                $message_type = 'success';
            } else {
                $message = $result['message'];
                $message_type = 'error';
            }
        } else if ($action === 'reject') {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', verified_by = ? WHERE id = ?");
                $stmt->execute([$current_user_id, $user_id]);
                $message = 'User rejected successfully';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error rejecting user: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get verifiable users based on role hierarchy
$verifiable_users = get_verifiable_users($pdo, $current_user_id, $current_user_role);

// Get statistics
$stats = get_verification_stats($pdo, $current_user_role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Users - EEMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .verify-card {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Hierarchical User Verification</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Your Role: <span class="font-semibold capitalize"><?= htmlspecialchars($current_user_role) ?></span>
                        <?php if ($current_user_role === 'principal'): ?>
                            - Can verify: Vice Principals
                        <?php elseif ($current_user_role === 'vice-principal'): ?>
                            - Can verify: HODs
                        <?php elseif ($current_user_role === 'hod'): ?>
                            - Can verify: Teachers
                        <?php elseif ($current_user_role === 'admin'): ?>
                            - Can verify: All roles
                        <?php endif; ?>
                    </p>
                </div>
                <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Statistics Card -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Pending Verification</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['pending_count'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Your Authority</p>
                        <p class="text-2xl font-bold text-gray-800 capitalize"><?= htmlspecialchars($current_user_role) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Hierarchy Level</p>
                        <p class="text-lg font-bold text-gray-800">
                            <?php if ($current_user_role === 'admin'): ?>
                                Level 0 (All)
                            <?php elseif ($current_user_role === 'principal'): ?>
                                Level 1
                            <?php elseif ($current_user_role === 'vice-principal'): ?>
                                Level 2
                            <?php elseif ($current_user_role === 'hod'): ?>
                                Level 3
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="verify-card mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
                <div class="flex items-center">
                    <?php if ($message_type === 'success'): ?>
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-green-800 font-medium"><?= htmlspecialchars($message) ?></p>
                    <?php else: ?>
                        <svg class="w-6 h-6 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-red-800 font-medium"><?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Verification Table -->
        <div class="verify-card bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Pending Verifications</h2>
                <p class="text-sm text-gray-600 mt-1">Review and verify users according to hierarchy</p>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($verifiable_users)): ?>
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-gray-500 font-medium">No pending verifications</p>
                        <p class="text-sm text-gray-400 mt-1">All users in your hierarchy are verified</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Institution</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($verifiable_users as $user): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-full flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $role = $user['post'];
                                        $role_colors = [
                                            'vice_principal' => 'bg-blue-100 text-blue-800',
                                            'vice-principal' => 'bg-blue-100 text-blue-800',
                                            'hod' => 'bg-green-100 text-green-800',
                                            'teacher' => 'bg-purple-100 text-purple-800',
                                            'principal' => 'bg-indigo-100 text-indigo-800'
                                        ];
                                        $color = $role_colors[strtolower($role)] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="role-badge <?= $color ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($user['college_name']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($user['phone']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <form method="POST" class="inline-block mr-2" onsubmit="return confirm('Verify this user? A password will be generated and sent to their email.');">
                                            <input type="hidden" name="action" value="verify">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Verify
                                            </button>
                                        </form>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Reject this user? They will not be able to access the system.');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hierarchy Information -->
        <div class="mt-8 bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Hierarchical Verification System
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gradient-to-br from-red-50 to-pink-50 rounded-lg border border-red-100">
                    <div class="text-3xl mb-2">👑</div>
                    <div class="font-semibold text-gray-800">Admin</div>
                    <div class="text-xs text-gray-600 mt-1">Verifies: All roles</div>
                </div>
                <div class="text-center p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg border border-indigo-100">
                    <div class="text-3xl mb-2">🎓</div>
                    <div class="font-semibold text-gray-800">Principal</div>
                    <div class="text-xs text-gray-600 mt-1">Verifies: Vice Principals</div>
                </div>
                <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg border border-blue-100">
                    <div class="text-3xl mb-2">💼</div>
                    <div class="font-semibold text-gray-800">Vice Principal</div>
                    <div class="text-xs text-gray-600 mt-1">Verifies: HODs</div>
                </div>
                <div class="text-center p-4 bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg border border-green-100">
                    <div class="text-3xl mb-2">👔</div>
                    <div class="font-semibold text-gray-800">HOD</div>
                    <div class="text-xs text-gray-600 mt-1">Verifies: Teachers</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
