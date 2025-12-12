<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php'; // ensures $pdo is available

start_secure_session();

// If already logged in, redirect to appropriate dashboard
if (!empty($_SESSION['user_id'])) {
  $r = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
  redirect_by_role($r);
}

// Show notices from redirects
$successMessage = '';
if (isset($_GET['registered'])) {
  $successMessage = '✅ Registration submitted successfully! Your account is pending verification. You will receive an email with your password once verified.';
}
if (isset($_GET['logged_out'])) {
  set_flash('info', 'You have been logged out.');
}

// POST: handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $csrf = $_POST['csrf_token'] ?? '';

  // CSRF validation
  if (!verify_csrf_token($csrf)) {
    set_flash('error', 'Invalid or expired form submission. Please try again.');
    header('Location: login.php');
    exit;
  }

  // Check if admin credentials (allow admin login from this page)
  $adminEmail = 'arjun@gmail.com';
  $adminPassword = '1234';
  
  if ($email === $adminEmail && $password === $adminPassword) {
    // Admin login successful
    try {
      // Check if admin exists in database
      $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email LIMIT 1");
      $stmt->execute([':email' => $adminEmail]);
      $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$adminUser) {
        // Create admin user if doesn't exist
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
      header('Location: admin_dashboard.php');
      exit;
    } catch (PDOException $e) {
      error_log('Admin login error: ' . $e->getMessage());
      // Fallback to session-only admin login
      login_user(0, 'Admin', 'admin');
      header('Location: admin_dashboard.php');
      exit;
    }
  }

  try {
  // Allow login by email (regular users)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
      // only allow verified/active accounts
      $status = $user['status'] ?? '';
      if ($status !== 'verified' && $status !== 'active') {
        set_flash('error', 'Your account is not verified yet. Please wait for admin approval.');
        header('Location: login.php');
        exit;
      }

      // Password field name may be `password` or `password_hash` in older code
      $storedHash = $user['password'] ?? $user['password_hash'] ?? '';

      $authOk = false;
      if (!empty($storedHash)) {
        if (empty($password)) {
          set_flash('error', 'Password is required for this account.');
        } elseif (password_verify($password, $storedHash)) {
          $authOk = true;
        } else {
          set_flash('error', 'Invalid credentials.');
        }
      } else {
        set_flash('error', 'No password set for this account. Please contact admin.');
      }

      if ($authOk) {
        $role = $user['role'] ?? $user['post'] ?? '';
        $userId = $user['id'];
        $userName = $user['name'] ?? $user['username'] ?? $email;
        $collegeId = $user['college_id'] ?? null;
        $departmentId = $user['department_id'] ?? null;
        
        // Set session variables
        login_user($userId, $userName, $role);
        $_SESSION['college_id'] = $collegeId;
        $_SESSION['department_id'] = $departmentId;
        
        redirect_by_role($role);
      }
    } else {
      set_flash('error', 'Invalid email or phone number.');
    }
  } catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    set_flash('error', 'An unexpected error occurred. Please try again later.');
  }
  header('Location: login.php');
  exit;
}

// GET: render login form
$csrfToken = generate_csrf_token();
$flashError = get_flash('error') ?? get_flash('info');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      background-attachment: fixed;
    }
    
    .login-container {
      animation: slideInUp 0.5s ease-out;
    }
    
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .input-group {
      transition: all 0.3s ease;
    }
    
    .input-group:focus-within {
      transform: translateY(-2px);
    }
    
    .login-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: all 0.3s ease;
    }
    
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
    }
    
    .floating-shape {
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      animation: float 20s infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translate(0, 0); }
      25% { transform: translate(30px, -30px); }
      50% { transform: translate(-30px, 30px); }
      75% { transform: translate(30px, 30px); }
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }
    
    .role-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .role-badge:hover {
      transform: scale(1.05);
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-8 relative overflow-hidden">
  
  <!-- Floating Background Shapes -->
  <div class="floating-shape" style="width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s;"></div>
  <div class="floating-shape" style="width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 7s;"></div>
  <div class="floating-shape" style="width: 150px; height: 150px; top: 50%; left: 10%; animation-delay: 14s;"></div>

  <div class="login-container glass-effect rounded-3xl shadow-2xl w-full max-w-md p-8 md:p-10 relative z-10">
    
    <!-- Logo Section -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl shadow-lg mb-4">
        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
      </div>
      <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome Back</h1>
      <p class="text-gray-500">EEMS - Examination Management System</p>
    </div>
          <!-- Success Message (Registration) -->
          <?php if (!empty($successMessage)): ?>
          <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-400 p-5 rounded-xl shadow-lg">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="ml-3 flex-1">
                <h3 class="text-sm font-bold text-green-900 mb-1">Registration Successful!</h3>
                <p class="text-sm text-green-800 leading-relaxed"><?php echo htmlspecialchars($successMessage); ?></p>
                <div class="mt-3 p-3 bg-white rounded-lg border border-green-200">
                  <p class="text-xs text-gray-700">
                    <strong>📋 Next Steps:</strong><br>
                    1️⃣ Your request will be reviewed by your hierarchical authority<br>
                    2️⃣ Once verified, you'll receive login credentials via email<br>
                    3️⃣ Use those credentials to login here
                  </p>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

    <!-- Role Badges -->
    <div class="flex flex-wrap gap-2 justify-center mb-6">
      <span class="role-badge bg-purple-100 text-purple-700">
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
          <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
        </svg>
        Admin
      </span>
      <span class="role-badge bg-blue-100 text-blue-700">
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
        </svg>
        Faculty
      </span>
      <span class="role-badge bg-green-100 text-green-700">
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
        </svg>
        Principal
      </span>
    </div>

  <!-- Error/Info Message -->
  <?php if (!empty($flashError)): ?>
  <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg animate-pulse">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>
        <p class="text-red-800 font-medium text-sm"><?php echo h($flashError); ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-5">
      
      <!-- Email Field -->
      <div class="input-group">
        <label class="block text-sm font-semibold text-gray-700 mb-2" for="email">
          Email Address
        </label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>
          <input 
            type="email" 
            id="email" 
            name="email" 
            required
            placeholder="your@email.com"
            class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all text-gray-700"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
            autocomplete="email"
          >
        </div>
      </div>

      <!-- Password Field -->
      <div class="input-group">
        <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">
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
            class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all text-gray-700"
            autocomplete="current-password"
          >
        </div>
      </div>

      <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

      <!-- Login Button -->
      <button 
        type="submit" 
        class="login-btn w-full text-white font-semibold py-3 px-4 rounded-xl focus:outline-none focus:ring-4 focus:ring-purple-300 shadow-lg"
      >
        <span class="flex items-center justify-center">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
          </svg>
          Sign In
        </span>
      </button>
    </form>

    <!-- Divider -->
    <div class="relative my-6">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-200"></div>
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-4 bg-white text-gray-500">New user?</span>
      </div>
    </div>

    <!-- Register Link -->
    <div class="text-center">
      <a 
        href="register.php" 
        class="inline-flex items-center text-purple-600 hover:text-purple-800 font-semibold transition-colors"
      >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
        </svg>
        Create New Account
      </a>
    </div>
  </div>

  <!-- Footer -->
  <div class="absolute bottom-4 text-center w-full z-10">
    <p class="text-white text-sm opacity-90 font-medium">
      © 2025 EEMS - Secure Education Management
    </p>
  </div>

</body>
</html>
