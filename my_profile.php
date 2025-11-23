<?php
/**
 * MY PROFILE - Universal Profile Completion Page
 * =================================================
 * For all roles: Principal, VP, HOD, Teacher
 * 
 * WORKFLOW:
 * 1. User registers → Status: Pending
 * 2. Admin verifies Principal (assigns staff_id, date_of_joining) → Status: Verified
 * 3. Principal verifies HOD/VP/Teacher (assigns staff_id, date_of_joining) → Status: Verified
 * 4. Upon first login, redirect here to complete profile
 * 5. After profile completion, redirect to role-specific dashboard
 * 
 * FIXED FIELDS (Read-only, set by admin/principal during verification):
 * - Staff ID, Date of Joining, College, Role
 * 
 * EDITABLE FIELDS (User can update):
 * - Personal Email, Alternate Phone, Current Address, Photo
 * - Aadhar, DOB, Gender, Qualification, Specialization, Experience
 * - Emergency Contact
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$userRole = normalize_role($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['post'] ?? '');

// Get user info including fixed fields set during verification
$userStmt = $pdo->prepare("
    SELECT 
        name, email, phone, post, college_name, college_id,
        staff_id, date_of_joining, 
        profile_completed, verified_by,
        personal_email, alternate_phone, current_address, permanent_address,
        aadhar_number, date_of_birth, gender,
        qualification, specialization, experience_years,
        emergency_contact_name, emergency_contact_phone,
        profile_photo
    FROM users 
    WHERE id = ?
");
$userStmt->execute([$currentUserId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found');
}

// If profile already completed, redirect to dashboard
if ($user['profile_completed']) {
    switch ($userRole) {
        case 'principal':
            header('Location: dashboard.php');
            exit;
        case 'vice-principal':
        case 'vp':
            header('Location: VP.php');
            exit;
        case 'hod':
            header('Location: hod_dashboard.php');
            exit;
        case 'teacher':
        case 'faculty':
            header('Location: teacher_dashboard.php');
            exit;
        case 'admin':
            header('Location: admin_dashboard.php');
            exit;
        default:
            header('Location: login.php');
            exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // EDITABLE FIELDS
    $personalEmail = trim($_POST['personal_email'] ?? '');
    $alternatePhone = trim($_POST['alternate_phone'] ?? '');
    $currentAddress = trim($_POST['current_address'] ?? '');
    $permanentAddress = trim($_POST['permanent_address'] ?? '');
    $aadharNumber = trim($_POST['aadhar_number'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experienceYears = (int)($_POST['experience_years'] ?? 0);
    $emergencyContactName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyContactPhone = trim($_POST['emergency_contact_phone'] ?? '');
    
    // Validation
    if (!empty($aadharNumber) && !preg_match('/^\d{12}$/', $aadharNumber)) {
        $errors[] = 'Aadhar number must be exactly 12 digits';
    }
    
    if (!empty($alternatePhone) && !preg_match('/^\d{10}$/', $alternatePhone)) {
        $errors[] = 'Alternate phone must be exactly 10 digits';
    }
    
    if (empty($currentAddress)) {
        $errors[] = 'Current address is required';
    }
    
    if (empty($qualification)) {
        $errors[] = 'Qualification is required';
    }
    
    if (empty($dateOfBirth)) {
        $errors[] = 'Date of birth is required';
    }
    
    if (empty($gender)) {
        $errors[] = 'Gender is required';
    }
    
    // Handle photo upload
    $photoPath = $user['profile_photo'];
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = 'Photo must be JPG, JPEG, or PNG format';
        } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Photo must be less than 2MB';
        } else {
            $fileName = 'profile_' . $currentUserId . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                $photoPath = 'uploads/profiles/' . $fileName;
            } else {
                $errors[] = 'Failed to upload photo';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE users SET
                    personal_email = ?,
                    alternate_phone = ?,
                    current_address = ?,
                    permanent_address = ?,
                    aadhar_number = ?,
                    date_of_birth = ?,
                    gender = ?,
                    qualification = ?,
                    specialization = ?,
                    experience_years = ?,
                    emergency_contact_name = ?,
                    emergency_contact_phone = ?,
                    profile_photo = ?,
                    profile_completed = 1,
                    last_profile_update = NOW()
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $personalEmail,
                $alternatePhone,
                $currentAddress,
                $permanentAddress,
                $aadharNumber,
                $dateOfBirth,
                $gender,
                $qualification,
                $specialization,
                $experienceYears,
                $emergencyContactName,
                $emergencyContactPhone,
                $photoPath,
                $currentUserId
            ]);
            
            $_SESSION['success_message'] = 'Profile completed successfully!';
            
            // Redirect to appropriate dashboard
            switch ($userRole) {
                case 'principal':
                    header('Location: dashboard.php');
                    break;
                case 'vice-principal':
                case 'vp':
                    header('Location: VP.php');
                    break;
                case 'hod':
                    header('Location: hod_dashboard.php');
                    break;
                case 'teacher':
                case 'faculty':
                    header('Location: teacher_dashboard.php');
                    break;
                case 'admin':
                    header('Location: admin_dashboard.php');
                    break;
                default:
                    header('Location: login.php');
                    break;
            }
            exit;
            
        } catch (Exception $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $errors[] = 'Failed to save profile. Please try again.';
        }
    }
}

// Get verifier name (who verified this account)
$verifierName = 'System';
if ($user['verified_by']) {
    $verifierStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $verifierStmt->execute([$user['verified_by']]);
    $verifier = $verifierStmt->fetch();
    if ($verifier) {
        $verifierName = $verifier['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            margin: 30px auto;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
        }
        .fixed-field {
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
            padding: 10px;
            margin-bottom: 10px;
        }
        .fixed-field-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .editable-section {
            border-left: 4px solid #28a745;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .btn-complete {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="profile-card">
            <div class="profile-header text-center">
                <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
                <h2 class="mt-3">Complete Your Profile</h2>
                <p class="mb-0">Welcome to EEMS, <?= htmlspecialchars($user['name']) ?>!</p>
                <small>Please complete your profile to access your dashboard</small>
            </div>
            
            <div class="p-4">
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
                
                <!-- FIXED INFORMATION (Read-Only) -->
                <div class="mb-4">
                    <h5 class="text-muted mb-3">
                        <i class="bi bi-lock-fill me-2"></i>Fixed Information
                        <small class="text-muted">(Set by <?= htmlspecialchars($verifierName) ?>)</small>
                    </h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fixed-field">
                                <div class="fixed-field-label">Staff ID</div>
                                <div class="h6 mb-0"><?= htmlspecialchars($user['staff_id'] ?? 'Not assigned') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fixed-field">
                                <div class="fixed-field-label">Date of Joining</div>
                                <div class="h6 mb-0"><?= $user['date_of_joining'] ? date('d M, Y', strtotime($user['date_of_joining'])) : 'Not assigned' ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fixed-field">
                                <div class="fixed-field-label">Role</div>
                                <div class="h6 mb-0"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['post']))) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fixed-field">
                                <div class="fixed-field-label">College</div>
                                <div class="h6 mb-0"><?= htmlspecialchars($user['college_name']) ?></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="fixed-field">
                                <div class="fixed-field-label">Official Email</div>
                                <div class="h6 mb-0"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- EDITABLE INFORMATION -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="editable-section">
                        <h5 class="text-success mb-3">
                            <i class="bi bi-pencil-fill me-2"></i>Complete Your Profile
                        </h5>
                        
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="text-muted border-bottom pb-2">Personal Information</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Aadhar Number *</label>
                                <input type="text" class="form-control" name="aadhar_number" 
                                       pattern="\d{12}" maxlength="12" required
                                       value="<?= htmlspecialchars($user['aadhar_number'] ?? '') ?>"
                                       placeholder="12-digit Aadhar number">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="date_of_birth" required
                                       value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>"
                                       max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Gender *</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" name="profile_photo" accept="image/jpeg,image/jpg,image/png">
                                <small class="text-muted">JPG, JPEG, PNG (Max 2MB)</small>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="col-12 mt-4">
                                <h6 class="text-muted border-bottom pb-2">Contact Information</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Official Phone</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly disabled>
                                <small class="text-muted">Fixed field</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Alternate Phone</label>
                                <input type="text" class="form-control" name="alternate_phone" 
                                       pattern="\d{10}" maxlength="10"
                                       value="<?= htmlspecialchars($user['alternate_phone'] ?? '') ?>"
                                       placeholder="10-digit phone number">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Personal Email</label>
                                <input type="email" class="form-control" name="personal_email" 
                                       value="<?= htmlspecialchars($user['personal_email'] ?? '') ?>"
                                       placeholder="your.email@example.com">
                            </div>
                            
                            <!-- Address -->
                            <div class="col-12 mt-4">
                                <h6 class="text-muted border-bottom pb-2">Address</h6>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Current Address *</label>
                                <textarea class="form-control" name="current_address" rows="2" required
                                          placeholder="House No, Street, Area, City, State, PIN"><?= htmlspecialchars($user['current_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Permanent Address</label>
                                <textarea class="form-control" name="permanent_address" rows="2"
                                          placeholder="Leave blank if same as current address"><?= htmlspecialchars($user['permanent_address'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Professional Information -->
                            <div class="col-12 mt-4">
                                <h6 class="text-muted border-bottom pb-2">Professional Information</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Highest Qualification *</label>
                                <input type="text" class="form-control" name="qualification" required
                                       value="<?= htmlspecialchars($user['qualification'] ?? '') ?>"
                                       placeholder="e.g., M.Sc, PhD, B.Tech">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Specialization/Subject *</label>
                                <input type="text" class="form-control" name="specialization" required
                                       value="<?= htmlspecialchars($user['specialization'] ?? '') ?>"
                                       placeholder="e.g., Physics, Computer Science">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" name="experience_years" min="0" max="50"
                                       value="<?= (int)($user['experience_years'] ?? 0) ?>"
                                       placeholder="Total years of experience">
                            </div>
                            
                            <!-- Emergency Contact -->
                            <div class="col-12 mt-4">
                                <h6 class="text-muted border-bottom pb-2">Emergency Contact</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name"
                                       value="<?= htmlspecialchars($user['emergency_contact_name'] ?? '') ?>"
                                       placeholder="Full name">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" name="emergency_contact_phone"
                                       pattern="\d{10}" maxlength="10"
                                       value="<?= htmlspecialchars($user['emergency_contact_phone'] ?? '') ?>"
                                       placeholder="10-digit phone number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-complete btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Complete Profile & Continue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
