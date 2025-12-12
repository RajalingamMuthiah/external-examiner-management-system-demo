<?php
require_once __DIR__ . '/config/db.php';

$message = '';
$errors = [];

$name = $post = $college_name = $phone = $email = $password = $password_confirm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim inputs
    $name = trim($_POST['name'] ?? '');
    $post = strtolower(trim($_POST['post'] ?? ''));
    $college_name = trim($_POST['college_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $password_confirm = $_POST['password_confirm'] ?? '';

    // Basic validation
    if ($name === '') $errors[] = 'Name is required.';
    if (!in_array($post, ['teacher', 'hod', 'vice_principal', 'principal'], true)) $errors[] = 'Invalid post selected.';
    if ($college_name === '') $errors[] = 'College name is required.';
    if ($phone === '' || !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) $errors[] = 'A valid phone is required.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
  // Password validation: optional for non-admins, but accept and validate if provided
  if ($password !== '' || $password_confirm !== '') {
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password_confirm) $errors[] = 'Password and confirmation do not match.';
  }

    if (empty($errors)) {
        try {
            // Check duplicate email
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute([':email' => $email]);
            if ($check->fetch()) {
                $message = 'User already exists';
            } else {
        // Insert with default status 'pending'
        if ($password !== '') {
          // Hash password securely
          $hashed = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $pdo->prepare('INSERT INTO users (name, post, college_name, phone, email, password, status) VALUES (:name, :post, :college_name, :phone, :email, :password, :status)');
          $stmt->execute([
            ':name' => $name,
            ':post' => $post,
            ':college_name' => $college_name,
            ':phone' => $phone,
            ':email' => $email,
            ':password' => $hashed,
            ':status' => 'pending',
          ]);
        } else {
          $stmt = $pdo->prepare('INSERT INTO users (name, post, college_name, phone, email, status) VALUES (:name, :post, :college_name, :phone, :email, :status)');
          $stmt->execute([
            ':name' => $name,
            ':post' => $post,
            ':college_name' => $college_name,
            ':phone' => $phone,
            ':email' => $email,
            ':status' => 'pending',
          ]);
        }
                // After successful registration, auto-login and redirect to profile page
                session_start();
                $userId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = $post;
                $_SESSION['college_id'] = null; // Will be set in profile page
                $_SESSION['department_id'] = null; // Will be set in profile page
                
                header('Location: user_profile.php?new_user=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'An error occurred while submitting registration.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      background-attachment: fixed;
    }
    
    .register-container {
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
    
    .input-field:focus {
      transform: translateY(-2px);
      transition: all 0.3s ease;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
  
  <!-- Floating Background Shapes -->
  <div class="absolute inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
  </div>

  <div class="register-container relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden">
    <div class="md:flex">
      <!-- Left Panel -->
      <div class="hidden md:flex md:w-2/5 bg-gradient-to-br from-purple-600 to-indigo-700 p-8 flex-col justify-center text-white relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
        
        <div class="relative z-10">
          <div class="w-16 h-16 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
          </div>
          <h2 class="text-3xl font-bold mb-3">Join EEMS</h2>
          <p class="text-purple-100 text-sm mb-6">Register and get verified by your institution's hierarchy</p>
          
          <div class="space-y-3 text-sm">
            <div class="flex items-start">
              <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <span>Hierarchical verification system</span>
            </div>
            <div class="flex items-start">
              <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <span>Password sent via email after verification</span>
            </div>
            <div class="flex items-start">
              <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <span>Secure role-based access</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel - Registration Form -->
      <div class="w-full md:w-3/5 p-8">
        <div class="mb-6">
          <h3 class="text-2xl font-bold text-gray-800">Create Account</h3>
          <p class="text-gray-500 text-sm mt-1">Fill in your details for verification</p>
        </div>

        <?php if ($message): ?>
          <div class="mb-4 p-3 rounded text-sm <?= $message === 'User already exists' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
            <?= htmlspecialchars($message) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="mb-4 p-3 rounded bg-red-50 text-red-800 border border-red-200 text-sm">
            <ul class="list-disc list-inside">
              <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="space-y-4">
          <!-- Name and Role -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
              <input 
                name="name" 
                type="text" 
                value="<?= htmlspecialchars($name) ?>" 
                required
                placeholder="John Doe"
                class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
              />
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Role/Post *</label>
              <select 
                name="post" 
                required 
                class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
              >
                <option value="">Select your role</option>
                <option value="teacher" <?= ($post === 'teacher') ? 'selected' : '' ?>>👨‍🏫 Teacher</option>
                <option value="hod" <?= ($post === 'hod') ? 'selected' : '' ?>>👔 HOD (Head of Department)</option>
                <option value="vice_principal" <?= ($post === 'vice_principal') ? 'selected' : '' ?>>💼 Vice Principal</option>
                <option value="principal" <?= ($post === 'principal') ? 'selected' : '' ?>>🎓 Principal</option>
              </select>
            </div>
          </div>

          <!-- College Name -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">College/Institution Name *</label>
            <input 
              name="college_name" 
              type="text" 
              value="<?= htmlspecialchars($college_name) ?>" 
              required
              placeholder="Mumbai Engineering College"
              class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
            />
          </div>

          <!-- Phone and Email -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
              <input 
                name="phone" 
                type="tel" 
                value="<?= htmlspecialchars($phone) ?>" 
                required
                placeholder="+91 98765 43210"
                class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
              />
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
              <input 
                name="email" 
                type="email" 
                value="<?= htmlspecialchars($email) ?>" 
                required
                placeholder="john@college.edu"
                class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
              />
            </div>
          </div>

          <!-- Optional Password Section -->
          <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
            <p class="text-sm text-gray-600 mb-3">
              <strong>Note:</strong> Password will be generated and sent to your email after verification. You can optionally set it now:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Password (Optional)</label>
                <input 
                  name="password" 
                  type="password"
                  placeholder="Min. 8 characters"
                  class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
                />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                <input 
                  name="password_confirm" 
                  type="password"
                  placeholder="Confirm password"
                  class="input-field w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-all"
                />
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <button 
            type="submit" 
            class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-semibold py-3 px-4 rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5"
          >
            <span class="flex items-center justify-center">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Submit for Verification
            </span>
          </button>

          <!-- Login Link -->
          <p class="text-center text-sm text-gray-600 mt-4">
            Already have an account? 
            <a href="login.php" class="text-purple-600 hover:text-purple-800 font-semibold transition-colors">
              Login here
            </a>
          </p>
        </form>
      </div>
    </div>
  </div>
</body>
</html>