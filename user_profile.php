<?php
/**
 * User Profile / Personal Information Page
 * Allows users to complete their profile with college and department info
 */

require_once 'includes/functions.php';
start_secure_session();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userRole = normalize_role($_SESSION['role'] ?? '');

// Fetch current user data
$stmt = $pdo->prepare("
    SELECT u.*, c.college_name, d.dept_name as department_name 
    FROM users u
    LEFT JOIN colleges c ON u.college_id = c.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Check if profile is incomplete
$profileIncomplete = empty($user['college_id']) || empty($user['department_id']);

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $collegeId = $_POST['college_id'] ?? null;
    $departmentId = $_POST['department_id'] ?? null;
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    if (empty($name)) {
        $error = "Name is required";
    } elseif (empty($email)) {
        $error = "Email is required";
    } elseif (empty($collegeId)) {
        $error = "Please select your college";
    } elseif (empty($departmentId)) {
        $error = "Please select your department";
    } else {
        // Check if email is already taken by another user
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $userId]);
        
        if ($checkStmt->fetch()) {
            $error = "Email is already taken by another user";
        } else {
            try {
                // Update user profile
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, 
                        email = ?, 
                        college_id = ?, 
                        department_id = ?,
                        phone = ?
                    WHERE id = ?
                ");
                
                $updateStmt->execute([
                    $name,
                    $email,
                    $collegeId,
                    $departmentId,
                    $phone ?: null,
                    $userId
                ]);
                
                // Update session variables
                $_SESSION['college_id'] = $collegeId;
                $_SESSION['department_id'] = $departmentId;
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $profileIncomplete = false;
                
            } catch (PDOException $e) {
                $error = "Error updating profile: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Personal Information";
include 'includes/head.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($profileIncomplete): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Complete Your Profile!</strong> Please provide your college and department information to access the system.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-user-circle me-2"></i>Personal Information</h4>
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="college_id" class="form-label">College <span class="text-danger">*</span></label>
                                <select class="form-select" id="college_id" name="college_id" required>
                                    <option value="">Select your college...</option>
                                    <!-- Options will be loaded via AJAX -->
                                </select>
                                <div class="form-text">Select the college you belong to</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="department_id" name="department_id" required disabled>
                                    <option value="">Select college first...</option>
                                    <!-- Options will be loaded via AJAX -->
                                </select>
                                <div class="form-text">Select your department</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="+91 9876543210">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" 
                                       value="<?php echo ucfirst($userRole); ?>" disabled readonly>
                                <div class="form-text">Contact admin to change your role</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Assignment</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <p class="mb-1"><strong>College:</strong> 
                                        <?php echo $user['college_name'] ? htmlspecialchars($user['college_name']) : '<span class="text-muted">Not assigned</span>'; ?>
                                    </p>
                                    <p class="mb-0"><strong>Department:</strong> 
                                        <?php echo $user['department_name'] ? htmlspecialchars($user['department_name']) : '<span class="text-muted">Not assigned</span>'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?php echo redirect_by_role($userRole); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load colleges on page load
document.addEventListener('DOMContentLoaded', function() {
    loadColleges();
    
    // Add change event listener for college dropdown
    document.getElementById('college_id').addEventListener('change', function() {
        const collegeId = this.value;
        if (collegeId) {
            loadDepartments(collegeId);
        } else {
            const deptSelect = document.getElementById('department_id');
            deptSelect.disabled = true;
            deptSelect.innerHTML = '<option value="">Select college first...</option>';
        }
    });
});

// Load colleges from API
function loadColleges() {
    fetch('api/colleges.php?action=get_colleges')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const collegeSelect = document.getElementById('college_id');
                const currentCollegeId = <?php echo $user['college_id'] ?? 'null'; ?>;
                
                collegeSelect.innerHTML = '<option value="">Select your college...</option>';
                
                data.colleges.forEach(college => {
                    const option = document.createElement('option');
                    option.value = college.id;
                    option.textContent = college.name;
                    if (college.id == currentCollegeId) {
                        option.selected = true;
                    }
                    collegeSelect.appendChild(option);
                });
                
                // If user has a college, load departments
                if (currentCollegeId) {
                    loadDepartments(currentCollegeId);
                }
            } else {
                console.error('Error loading colleges:', data.message);
                alert('Error loading colleges. Please refresh the page.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error connecting to server. Please refresh the page.');
        });
}

// Load departments for selected college
function loadDepartments(collegeId) {
    const deptSelect = document.getElementById('department_id');
    const currentDeptId = <?php echo $user['department_id'] ?? 'null'; ?>;
    
    deptSelect.disabled = true;
    deptSelect.innerHTML = '<option value="">Loading departments...</option>';
    
    fetch(`api/colleges.php?action=get_departments&college_id=${collegeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                deptSelect.innerHTML = '<option value="">Select your department...</option>';
                
                data.departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = dept.name;
                    if (dept.id == currentDeptId) {
                        option.selected = true;
                    }
                    deptSelect.appendChild(option);
                });
                
                deptSelect.disabled = false;
            } else {
                console.error('Error loading departments:', data.message);
                deptSelect.innerHTML = '<option value="">Error loading departments</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            deptSelect.innerHTML = '<option value="">Error connecting to server</option>';
        });
}

// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const collegeId = document.getElementById('college_id').value;
    const deptId = document.getElementById('department_id').value;
    
    if (!collegeId) {
        e.preventDefault();
        alert('Please select your college');
        return false;
    }
    
    if (!deptId) {
        e.preventDefault();
        alert('Please select your department');
        return false;
    }
});
</script>

<?php include 'includes/footer_scripts.php'; ?>
