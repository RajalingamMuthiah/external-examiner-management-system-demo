<?php
// admin_login.php
// Dedicated admin login page. Uses environment variables for admin credentials.
require_once __DIR__ . '/includes/functions.php';
start_secure_session();
require_once __DIR__ . '/config/db.php';

// If already logged in as admin, redirect
if (!empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'admin')) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid form submission (CSRF).';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // FIXED ADMIN CREDENTIALS
        $adminEmail = 'arjun@gmail.com';
        $adminPassword = '1234';

        // Allow login by username 'admin' or admin email
        if (($username === 'admin' || $username === $adminEmail) && $password === $adminPassword) {
            // Check if admin exists in database, if not create one
            try {
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $adminEmail]);
                $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$adminUser) {
                    // Create admin user in database
                    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $insertStmt = $pdo->prepare("INSERT INTO users (name, post, college_name, phone, email, password, status) VALUES (:name, :post, :college, :phone, :email, :password, :status)");
                    $insertStmt->execute([
                        ':name' => 'Arjun Admin',
                        ':post' => 'admin',
                        ':college' => 'System Admin',
                        ':phone' => '0000000000',
                        ':email' => $adminEmail,
                        ':password' => $hashedPassword,
                        ':status' => 'verified'
                    ]);
                    $adminId = $pdo->lastInsertId();
                    $adminName = 'Arjun Admin';
                } else {
                    $adminId = $adminUser['id'];
                    $adminName = $adminUser['name'];
                }
                
                login_user($adminId, $adminName, 'admin');
                redirect_by_role('admin');
            } catch (PDOException $e) {
                error_log('Admin login error: ' . $e->getMessage());
                // Fallback to session-only admin login
                login_user(0, 'Admin', 'admin');
                redirect_by_role('admin');
            }
        } else {
            $error = 'Invalid admin credentials. Use arjun@gmail.com / 1234';
        }
    }
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .login-container {
      backdrop-filter: blur(10px);
      animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .input-field {
      transition: all 0.3s ease;
    }
    
    .input-field:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .login-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: all 0.3s ease;
    }
    
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    .icon-wrapper {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0%, 100% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
      }
      50% {
        box-shadow: 0 0 0 15px rgba(102, 126, 234, 0);
      }
    }
    
    .error-shake {
      animation: shake 0.5s;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-8">
  <!-- Background Decorations -->
  <div class="absolute inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
  </div>

  <!-- Login Card -->
  <div class="login-container relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 md:p-10">
    <!-- Logo/Icon Section -->
    <div class="flex justify-center mb-8">
      <div class="icon-wrapper w-20 h-20 rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
    </div>

    <!-- Header -->
    <div class="text-center mb-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-2">Admin Access</h1>
      <p class="text-gray-500 text-sm">EEMS - Examination Management System</p>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="error-shake mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>
        <div>
          <p class="text-red-800 font-medium text-sm"><?php echo h($error); ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" class="space-y-6">
      <!-- Email Field -->
      <div>
        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
          Admin Email
        </label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </div>
          <input 
            type="text" 
            id="username"
            name="username" 
            placeholder="arjun@gmail.com"
            class="input-field w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-purple-500 text-gray-700"
            required
            autocomplete="email"
          >
        </div>
      </div>

      <!-- Password Field -->
      <div>
        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
          Password
        </label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
          </div>
          <input 
            type="password" 
            id="password"
            name="password" 
            placeholder="••••••••"
            class="input-field w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-purple-500 text-gray-700"
            required
            autocomplete="current-password"
          >
        </div>
      </div>

      <!-- CSRF Token -->
      <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

      <!-- Login Button -->
      <button 
        type="submit" 
        class="login-btn w-full text-white font-semibold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
      >
        <span class="flex items-center justify-center">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
          </svg>
          Sign In to Admin Panel
        </span>
      </button>
    </form>

    <!-- Divider -->
    <div class="relative my-8">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-4 bg-white text-gray-500">or</span>
      </div>
    </div>

    <!-- Back to Login Link -->
    <div class="text-center">
      <a href="login.php" class="text-sm text-purple-600 hover:text-purple-800 font-medium inline-flex items-center transition-colors">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to User Login
      </a>
    </div>

    <!-- Info Box -->
    <div class="mt-6 p-4 bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg border border-purple-100">
      <div class="flex items-start">
        <svg class="w-5 h-5 text-purple-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <div>
          <p class="text-xs text-gray-600 font-medium mb-1">Default Admin Credentials:</p>
          <p class="text-xs text-gray-500">Email: <code class="bg-white px-1 py-0.5 rounded">arjun@gmail.com</code></p>
          <p class="text-xs text-gray-500">Password: <code class="bg-white px-1 py-0.5 rounded">1234</code></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="absolute bottom-4 text-center w-full">
    <p class="text-white text-sm opacity-75">
      © 2025 EEMS - Secure Admin Access
    </p>
  </div>
</body>
</html>
