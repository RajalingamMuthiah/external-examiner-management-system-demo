<?php
/**
 * UAT Test Scenarios
 * Display and execute test scenarios for user acceptance testing
 */

require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$currentUser = $_SESSION['user_id'];
$currentRole = $_SESSION['role'];

// Handle test execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_test') {
    $stmt = $db->prepare("
        INSERT INTO uat_test_results (
            scenario_id, user_id, status, notes, execution_time, tested_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_POST['scenario_id'],
        $currentUser,
        $_POST['status'],
        $_POST['notes'] ?? '',
        $_POST['execution_time'] ?? 0
    ]);
    $message = ['type' => 'success', 'text' => 'Test result submitted successfully!'];
}

// Get filter parameters
$filterRole = $_GET['role'] ?? 'all';
$filterCategory = $_GET['category'] ?? 'all';
$filterPriority = $_GET['priority'] ?? 'all';

// Build query
$query = "
    SELECT s.*, 
           COUNT(DISTINCT r.id) as total_tests,
           SUM(CASE WHEN r.status = 'pass' THEN 1 ELSE 0 END) as passed_tests,
           SUM(CASE WHEN r.status = 'fail' THEN 1 ELSE 0 END) as failed_tests
    FROM uat_test_scenarios s
    LEFT JOIN uat_test_results r ON s.id = r.scenario_id
    WHERE 1=1
";

$params = [];

if ($filterRole !== 'all') {
    $query .= " AND s.role = ?";
    $params[] = $filterRole;
}

if ($filterCategory !== 'all') {
    $query .= " AND s.category = ?";
    $params[] = $filterCategory;
}

if ($filterPriority !== 'all') {
    $query .= " AND s.priority = ?";
    $params[] = $filterPriority;
}

$query .= " GROUP BY s.id ORDER BY s.priority DESC, s.id";

$stmt = $db->prepare($query);
$stmt->execute($params);
$scenarios = $stmt->fetchAll();

// Get overall statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_scenarios,
        SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical_scenarios
    FROM uat_test_scenarios
");
$stats = $stmt->fetch();

$stmt = $db->query("
    SELECT 
        COUNT(*) as total_tests,
        SUM(CASE WHEN status = 'pass' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN status = 'fail' THEN 1 ELSE 0 END) as failed
    FROM uat_test_results
");
$testStats = $stmt->fetch();

$passRate = $testStats['total_tests'] > 0 ? 
    round(($testStats['passed'] / $testStats['total_tests']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAT Test Scenarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .scenario-card {
            border-left: 4px solid;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .scenario-card:hover {
            transform: translateX(5px);
        }
        .priority-critical { border-left-color: #dc3545; }
        .priority-high { border-left-color: #fd7e14; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-low { border-left-color: #0dcaf0; }
        
        .test-status-badge {
            font-size: 0.85rem;
        }
        
        .steps-list {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php if (isset($message)): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="bi bi-list-check"></i> User Acceptance Test Scenarios</h1>
                <p class="text-muted">Follow these scenarios to test the system comprehensively</p>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Total Scenarios</h6>
                        <h2><?= $stats['total_scenarios'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Tests Executed</h6>
                        <h2><?= $testStats['total_tests'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Pass Rate</h6>
                        <h2 class="<?= $passRate >= 80 ? 'text-success' : 'text-warning' ?>">
                            <?= $passRate ?>%
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body">
                        <h6 class="text-muted">Failed Tests</h6>
                        <h2 class="text-danger"><?= $testStats['failed'] ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $filterRole === 'all' ? 'selected' : '' ?>>All Roles</option>
                            <option value="teacher" <?= $filterRole === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="hod" <?= $filterRole === 'hod' ? 'selected' : '' ?>>HOD</option>
                            <option value="principal" <?= $filterRole === 'principal' ? 'selected' : '' ?>>Principal</option>
                            <option value="vp" <?= $filterRole === 'vp' ? 'selected' : '' ?>>VP</option>
                            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>All Categories</option>
                            <option value="authentication" <?= $filterCategory === 'authentication' ? 'selected' : '' ?>>Authentication</option>
                            <option value="functionality" <?= $filterCategory === 'functionality' ? 'selected' : '' ?>>Functionality</option>
                            <option value="security" <?= $filterCategory === 'security' ? 'selected' : '' ?>>Security</option>
                            <option value="performance" <?= $filterCategory === 'performance' ? 'selected' : '' ?>>Performance</option>
                            <option value="administration" <?= $filterCategory === 'administration' ? 'selected' : '' ?>>Administration</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $filterPriority === 'all' ? 'selected' : '' ?>>All Priorities</option>
                            <option value="critical" <?= $filterPriority === 'critical' ? 'selected' : '' ?>>Critical</option>
                            <option value="high" <?= $filterPriority === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="medium" <?= $filterPriority === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="low" <?= $filterPriority === 'low' ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="?" class="btn btn-outline-secondary">Reset Filters</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Test Scenarios -->
        <div class="row">
            <?php foreach ($scenarios as $scenario): ?>
            <div class="col-md-6 mb-3">
                <div class="card scenario-card priority-<?= $scenario['priority'] ?> shadow">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= htmlspecialchars($scenario['scenario_name']) ?></h5>
                            <div>
                                <span class="badge bg-<?= 
                                    $scenario['priority'] === 'critical' ? 'danger' :
                                    ($scenario['priority'] === 'high' ? 'warning' :
                                    ($scenario['priority'] === 'medium' ? 'info' : 'secondary'))
                                ?>">
                                    <?= ucfirst($scenario['priority']) ?>
                                </span>
                                <span class="badge bg-secondary"><?= ucfirst($scenario['role']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            <strong>Feature:</strong> <?= htmlspecialchars($scenario['feature']) ?><br>
                            <strong>Category:</strong> <?= htmlspecialchars($scenario['category']) ?>
                        </p>
                        
                        <p><?= htmlspecialchars($scenario['description']) ?></p>
                        
                        <div class="steps-list mb-3">
                            <strong>Steps:</strong>
                            <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem;"><?= htmlspecialchars($scenario['steps']) ?></pre>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <strong>Expected Result:</strong><br>
                            <?= htmlspecialchars($scenario['expected_result']) ?>
                        </div>
                        
                        <!-- Test Results -->
                        <?php if ($scenario['total_tests'] > 0): ?>
                        <div class="mb-3">
                            <div class="progress" style="height: 25px;">
                                <?php 
                                $passPercent = ($scenario['passed_tests'] / $scenario['total_tests']) * 100;
                                $failPercent = ($scenario['failed_tests'] / $scenario['total_tests']) * 100;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?= $passPercent ?>%">
                                    Pass: <?= $scenario['passed_tests'] ?>
                                </div>
                                <div class="progress-bar bg-danger" style="width: <?= $failPercent ?>%">
                                    Fail: <?= $scenario['failed_tests'] ?>
                                </div>
                            </div>
                            <small class="text-muted">Tested <?= $scenario['total_tests'] ?> times</small>
                        </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary" onclick="openTestModal(<?= $scenario['id'] ?>, '<?= htmlspecialchars($scenario['scenario_name']) ?>')">
                            <i class="bi bi-play-circle"></i> Execute Test
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($scenarios)): ?>
        <div class="alert alert-info">
            No test scenarios match your filters. Try adjusting the filters above.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Test Execution Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Execute Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_test">
                        <input type="hidden" name="scenario_id" id="test_scenario_id">
                        
                        <p class="mb-3">
                            <strong>Scenario:</strong><br>
                            <span id="test_scenario_name"></span>
                        </p>
                        
                        <div class="mb-3">
                            <label class="form-label">Test Result</label>
                            <select name="status" class="form-select" required>
                                <option value="">Select result...</option>
                                <option value="pass">✓ Pass</option>
                                <option value="fail">✗ Fail</option>
                                <option value="blocked">⊘ Blocked</option>
                                <option value="skip">⇢ Skip</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Execution Time (seconds)</label>
                            <input type="number" name="execution_time" class="form-control" 
                                   min="0" placeholder="How long did the test take?">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="Any observations, issues, or comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openTestModal(scenarioId, scenarioName) {
            document.getElementById('test_scenario_id').value = scenarioId;
            document.getElementById('test_scenario_name').textContent = scenarioName;
            new bootstrap.Modal(document.getElementById('testModal')).show();
        }
    </script>
</body>
</html>
