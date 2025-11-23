<?php
// Teacher Onboarding - Collect personal details after first login
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Only teachers can access
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array(strtolower($role), ['teacher', 'faculty'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];

// Check if profile already completed
$checkStmt = $pdo->prepare("SELECT profile_completed FROM users WHERE id = ?");
$checkStmt->execute([$currentUserId]);
$user = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['profile_completed']) {
    header('Location: teacher_dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aadharNumber = trim($_POST['aadhar_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $alternatePhone = trim($_POST['alternate_phone'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experience = intval($_POST['experience'] ?? 0);
    $emergencyContactName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyContactPhone = trim($_POST['emergency_contact_phone'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($aadharNumber) || !preg_match('/^\d{12}$/', $aadharNumber)) {
        $errors[] = 'Valid 12-digit Aadhar number is required';
    }
    if (empty($phone) || !preg_match('/^\d{10}$/', $phone)) {
        $errors[] = 'Valid 10-digit phone number is required';
    }
    if (empty($dateOfBirth)) {
        $errors[] = 'Date of birth is required';
    }
    if (empty($address) || empty($city) || empty($state) || empty($pincode)) {
        $errors[] = 'Complete address is required';
    }
    if (empty($qualification)) {
        $errors[] = 'Qualification is required';
    }
    
    if (empty($errors)) {
        try {
            // Check if profile table exists, if not use JSON in users table
            $profileData = json_encode([
                'aadhar_number' => $aadharNumber,
                'phone' => $phone,
                'alternate_phone' => $alternatePhone,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'pincode' => $pincode,
                'qualification' => $qualification,
                'specialization' => $specialization,
                'experience_years' => $experience,
                'emergency_contact_name' => $emergencyContactName,
                'emergency_contact_phone' => $emergencyContactPhone,
                'profile_completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update user profile
            $updateStmt = $pdo->prepare("UPDATE users SET profile_data = ?, profile_completed = 1, phone = ? WHERE id = ?");
            $updateStmt->execute([$profileData, $phone, $currentUserId]);
            
            $_SESSION['success_message'] = 'Profile completed successfully! Welcome to EEMS.';
            header('Location: teacher_dashboard.php');
            exit;
        } catch (Exception $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $errors[] = 'Failed to save profile. Please try again.';
        }
    }
}

// Get user basic info
$userStmt = $pdo->prepare("SELECT name, email, college_name FROM users WHERE id = ?");
$userStmt->execute([$currentUserId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - EEMS</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .onboarding-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 3rem;
        }
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-top: 2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="text-center mb-4">
            <div class="bg-gradient rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-person-fill text-white" style="font-size: 2.5rem;"></i>
            </div>
            <h2 class="fw-bold mb-2">Complete Your Profile</h2>
            <p class="text-muted">Welcome, <?= htmlspecialchars($userInfo['name'] ?? 'Teacher') ?>! Please provide your details to get started.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Personal Information -->
            <div class="section-title">
                <i class="bi bi-person-badge me-2"></i>Personal Information
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="aadhar_number" class="form-label">Aadhar Number *</label>
                    <input type="text" class="form-control" id="aadhar_number" name="aadhar_number" 
                           pattern="\d{12}" maxlength="12" placeholder="XXXXXXXXXXXX" required>
                    <small class="text-muted">12 digits without spaces</small>
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                           max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender *</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           pattern="\d{10}" maxlength="10" placeholder="9876543210" required>
                </div>
                <div class="col-md-6">
                    <label for="alternate_phone" class="form-label">Alternate Phone</label>
                    <input type="tel" class="form-control" id="alternate_phone" name="alternate_phone" 
                           pattern="\d{10}" maxlength="10" placeholder="9876543210">
                </div>
            </div>

            <!-- Address Information -->
            <div class="section-title">
                <i class="bi bi-geo-alt me-2"></i>Address Details
            </div>
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label for="address" class="form-label">Street Address *</label>
                    <textarea class="form-control" id="address" name="address" rows="2" 
                              placeholder="House No., Street Name, Area" required></textarea>
                </div>
                <div class="col-md-4">
                    <label for="city" class="form-label">City *</label>
                    <input type="text" class="form-control" id="city" name="city" placeholder="Bangalore" required>
                </div>
                <div class="col-md-4">
                    <label for="state" class="form-label">State *</label>
                    <input type="text" class="form-control" id="state" name="state" placeholder="Karnataka" required>
                </div>
                <div class="col-md-4">
                    <label for="pincode" class="form-label">Pincode *</label>
                    <input type="text" class="form-control" id="pincode" name="pincode" 
                           pattern="\d{6}" maxlength="6" placeholder="560001" required>
                </div>
            </div>

            <!-- Educational & Professional Information -->
            <div class="section-title">
                <i class="bi bi-mortarboard me-2"></i>Educational & Professional Details
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="qualification" class="form-label">Highest Qualification *</label>
                    <select class="form-select" id="qualification" name="qualification" required>
                        <option value="">Select Qualification</option>
                        <option value="B.Tech">B.Tech</option>
                        <option value="M.Tech">M.Tech</option>
                        <option value="M.Sc">M.Sc</option>
                        <option value="Ph.D">Ph.D</option>
                        <option value="MBA">MBA</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="specialization" class="form-label">Specialization/Subject</label>
                    <input type="text" class="form-control" id="specialization" name="specialization" 
                           placeholder="e.g., Computer Science, Mathematics">
                </div>
                <div class="col-md-6">
                    <label for="experience" class="form-label">Years of Experience</label>
                    <input type="number" class="form-control" id="experience" name="experience" 
                           min="0" max="50" value="0">
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="section-title">
                <i class="bi bi-telephone me-2"></i>Emergency Contact
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="emergency_contact_name" class="form-label">Contact Person Name</label>
                    <input type="text" class="form-control" id="emergency_contact_name" 
                           name="emergency_contact_name" placeholder="Name">
                </div>
                <div class="col-md-6">
                    <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                    <input type="tel" class="form-control" id="emergency_contact_phone" 
                           name="emergency_contact_phone" pattern="\d{10}" maxlength="10" placeholder="9876543210">
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle me-2"></i>Complete Profile & Continue
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
