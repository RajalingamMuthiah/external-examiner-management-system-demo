<?php
/**
 * ADD FACULTY PAGE
 * File: faculty_add.php
 * Purpose: Add new faculty members to the system
 * 
 * Features:
 * - Admin/Principal can add faculty
 * - Role selection (Teacher, HOD, etc.)
 * - College assignment
 * - Department assignment
 * - Email validation
 * - Auto-generate password option
 */

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = normalize_role($_SESSION['role'] ?? '');

// Only Admin and Principal can add faculty
$allowedRoles = ['admin', 'principal'];
if (!in_array($userRole, $allowedRoles)) {
    $_SESSION['error'] = 'Only Admin or Principal can add faculty members';
    header('Location: dashboard.php');
    exit;
}

// Get colleges for dropdown
$colleges = [];
try {
    $stmt = $pdo->query("SELECT college_id, college_name FROM colleges ORDER BY college_name");
    $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Get colleges error: ' . $e->getMessage());
}

// Get departments for dropdown
$departments = [];
try {
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Get departments error: ' . $e->getMessage());
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = 'Invalid security token';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $collegeId = intval($_POST['college_id'] ?? 0);
        $departmentId = intval($_POST['department_id'] ?? 0);
        $autoPassword = isset($_POST['auto_password']);
        $password = $autoPassword ? bin2hex(random_bytes(4)) : trim($_POST['password'] ?? '');
        
        // Validation
        if (empty($name)) {
            $errorMessage = 'Name is required';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Valid email is required';
        } elseif (empty($role)) {
            $errorMessage = 'Role is required';
        } elseif ($collegeId <= 0) {
            $errorMessage = 'College is required';
        } elseif (empty($password)) {
            $errorMessage = 'Password is required';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errorMessage = 'Email already registered';
                } else {
                    // Insert new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users 
                        (name, email, phone, password, role, college_id, department_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', NOW())
                    ");
                    
                    $stmt->execute([
                        $name,
                        $email,
                        $phone,
                        $hashedPassword,
                        $role,
                        $collegeId,
                        $departmentId > 0 ? $departmentId : null
                    ]);
                    
                    $newUserId = $pdo->lastInsertId();
                    
                    // Audit log
                    logAudit($pdo, 'user', $newUserId, 'create', $userId, [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role
                    ]);
                    
                    $successMessage = "Faculty member added successfully!";
                    if ($autoPassword) {
                        $successMessage .= " Temporary password: <strong>$password</strong> (Please share securely)";
                    }
                    
                    // Clear form
                    $_POST = [];
                }
            } catch (PDOException $e) {
                error_log('Add faculty error: ' . $e->getMessage());
                $errorMessage = 'Failed to add faculty member';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Faculty - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .faculty-card {
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .required::after {
            content: ' *';
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card faculty-card">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>Add New Faculty
                    </h4>
                    <a href="manage_faculty.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Faculty List
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= $successMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="addFacultyForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label required">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="e.g., Dr. John Smith" required
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label required">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="e.g., john.smith@college.edu" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="e.g., +1234567890"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label required">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                <option value="hod" <?= ($_POST['role'] ?? '') === 'hod' ? 'selected' : '' ?>>Head of Department (HOD)</option>
                                <option value="principal" <?= ($_POST['role'] ?? '') === 'principal' ? 'selected' : '' ?>>Principal</option>
                                <option value="vice_principal" <?= ($_POST['role'] ?? '') === 'vice_principal' ? 'selected' : '' ?>>Vice-Principal</option>
                                <?php if ($userRole === 'admin'): ?>
                                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="college_id" class="form-label required">College</label>
                            <select class="form-select" id="college_id" name="college_id" required>
                                <option value="">Select College</option>
                                <?php foreach ($colleges as $college): ?>
                                    <option value="<?= $college['college_id'] ?>" 
                                            <?= (intval($_POST['college_id'] ?? 0) === $college['college_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($college['college_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department (Optional)</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" 
                                            <?= (intval($_POST['department_id'] ?? 0) === $dept['department_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_password" name="auto_password" 
                                   onchange="togglePasswordField()">
                            <label class="form-check-label" for="auto_password">
                                Auto-generate temporary password
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="password_field">
                        <label for="password" class="form-label required">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Minimum 6 characters" minlength="6">
                        <small class="text-muted">User can change this after first login</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Faculty member will be added with "Verified" status and can login immediately.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="manage_faculty.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fas fa-user-plus me-1"></i>Add Faculty
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePasswordField() {
            const checkbox = document.getElementById('auto_password');
            const passwordField = document.getElementById('password_field');
            const passwordInput = document.getElementById('password');
            
            if (checkbox.checked) {
                passwordField.style.display = 'none';
                passwordInput.removeAttribute('required');
            } else {
                passwordField.style.display = 'block';
                passwordInput.setAttribute('required', 'required');
            }
        }
        
        // Form validation
        document.getElementById('addFacultyForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailPattern.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            const autoPassword = document.getElementById('auto_password').checked;
            const password = document.getElementById('password').value;
            
            if (!autoPassword && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters');
                return false;
            }
        });
    </script>
</body>
</html>
