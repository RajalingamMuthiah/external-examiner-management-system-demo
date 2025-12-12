<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // adjust path/name if needed

if (!isset($_SESSION['userid'])) {
    header('Location: ../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_title   = trim($_POST['exam_name'] ?? '');
    $exam_date   = $_POST['exam_date'] ?? '';
    $duration    = (int)($_POST['duration'] ?? 0);
    $class_grade = $_POST['class_grade'] ?? '';

    if ($exam_name && $exam_date && $duration >= 1 && $class_grade !== '') {
        try {
            // Adjust column names to your table
            $stmt = $pdo->prepare(
                "INSERT INTO exams (title, exam_date, duration, classgrade, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, 'Pending', ?, NOW())"
            );
            $stmt->execute([
                $exam_title,
                $exam_date,
                $duration,
                $class_grade,
                $_SESSION['userid']
            ]);

            header('Location: ../admin_dashboard.php?action=load_module&module=exammanagement');
            exit;
        } catch (Throwable $e) {
            $message = 'Server error. Please try again.';
        }
    } else {
        $message = 'Please fill all required fields correctly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Exam Page</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <a href="../admin_dashboard.php?action=load_module&module=exammanagement"
       class="btn btn-secondary mb-3">
        ← Back to Exam Management
    </a>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Create New Exam</h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="exam_name" class="form-label">Exam Name</label>
                    <input type="text" id="exam_name" name="exam_name" class="form-control"
                           required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="exam_date" class="form-label">Exam Date</label>
                        <input type="date" id="exam_date" name="exam_date" class="form-control"
                               required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input type="number" id="duration" name="duration" class="form-control"
                               min="1" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="class_grade" class="form-label">Class / Grade</label>
                    <select id="class_grade" name="class_grade" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Grade 9">Grade 9</option>
                        <option value="Grade 10">Grade 10</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                        <option value="College 1st Year">College 1st Year</option>
                        <option value="College 2nd Year">College 2nd Year</option>
                        <option value="College 3rd Year">College 3rd Year</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-success w-100">
                    Submit Exam
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
