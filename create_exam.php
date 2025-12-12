<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Set CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed.');
    }

    try {
        // --- Input Sanitation ---
        $exam_name = trim(filter_input(INPUT_POST, 'exam_name', FILTER_SANITIZE_STRING));
        $exam_date = trim(filter_input(INPUT_POST, 'exam_date', FILTER_SANITIZE_STRING));
        $start_time = trim(filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING));
        $end_time = trim(filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING));
        $exam_type = trim(filter_input(INPUT_POST, 'exam_type', FILTER_SANITIZE_STRING));
        $department = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING));
        $semester = trim(filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING));
        $batch = trim(filter_input(INPUT_POST, 'batch', FILTER_SANITIZE_STRING));
        $venue = trim(filter_input(INPUT_POST, 'venue', FILTER_SANITIZE_STRING));
        $coordinator = trim(filter_input(INPUT_POST, 'coordinator', FILTER_SANITIZE_STRING));
        $examiners_needed = filter_input(INPUT_POST, 'examiners_needed', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $details = trim(filter_input(INPUT_POST, 'details', FILTER_SANITIZE_STRING));

        // Auto-filled from session
        $college_name = $_SESSION['user_college'] ?? 'N/A';
        $created_by_id = $_SESSION['user_id'];
        
        // Basic validation
        if (empty($exam_name) || empty($exam_date) || empty($start_time) || empty($end_time) || $examiners_needed === false) {
            throw new Exception("Please fill out all required fields correctly.");
        }

        // --- Database Insertion ---
        $sql = "INSERT INTO exam_schedule (exam_name, exam_date, start_time, end_time, exam_type, department, semester, batch, college_name, venue, coordinator, examiners_needed, details, created_by_id) 
            VALUES (:exam_name, :exam_date, :start_time, :end_time, :exam_type, :department, :semester, :batch, :college_name, :venue, :coordinator, :examiners_needed, :details, :created_by_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':exam_name', $exam_name);
        $stmt->bindParam(':exam_date', $exam_date);
        $stmt->bindParam(':start_time', $start_time);
        $stmt->bindParam(':end_time', $end_time);
        $stmt->bindParam(':exam_type', $exam_type);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':semester', $semester);
        $stmt->bindParam(':batch', $batch);
        $stmt->bindParam(':college_name', $college_name);
        $stmt->bindParam(':venue', $venue);
        $stmt->bindParam(':coordinator', $coordinator);
        $stmt->bindParam(':examiners_needed', $examiners_needed, PDO::PARAM_INT);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':created_by_id', $created_by_id, PDO::PARAM_INT);
        $stmt->execute();

        // --- ALSO insert into exams table so admin can approve this exam ---
        try {
            // Map principal form fields to exams columns
            $exTitle      = $exam_name;      // main exam name from form
            $exExamDate   = $exam_date;      // 'Y-m-d'
            $exDepartment = $department;     // or $college_name
            $exDesc       = $details;        // long text
            $exSubject    = $exam_type;      // use your exam_type as subject
            $exCollegeId  = null;            // or a real college id if you have one
            $createdBy    = $created_by_id;  // principal's user id from session

            $sql2 = "INSERT INTO exams
                        (title, exam_date, department, status,
                         description, subject, college_id, created_by)
                     VALUES
                        (:title, :exam_date, :department, 'Pending',
                         :description, :subject, :college_id, :created_by)";

            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([
                ':title'       => $exTitle,
                ':exam_date'   => $exExamDate,
                ':department'  => $exDepartment,
                ':description' => $exDesc,
                ':subject'     => $exSubject,
                ':college_id'  => $exCollegeId,
                ':created_by'  => $createdBy,
            ]);
        } catch (Throwable $e) {
            // Optional: log, but do not block scheduling in principal dashboard
            // error_log('Error inserting into exams: ' . $e->getMessage());
        }

        $_SESSION['success_message'] = "Exam scheduled successfully!";
        header('Location: dashboard.php');
        exit;

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log('CREATE_EXAM_ERROR: ' . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Create Exam — EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Create New External Exam</h1>
        <p class="text-sm text-gray-500">Fill out the details below to schedule a new exam.</p>
      </div>
      <div class="text-right">
        <a href="dashboard.php" class="text-sm text-blue-600">← Back to dashboard</a>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow p-8">
      <?php if ($error_message): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
          <?= htmlspecialchars($error_message) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="create_exam.php" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="exam_name" class="block text-sm font-medium text-gray-700">Exam Name *</label>
            <input type="text" id="exam_name" name="exam_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div>
            <label for="exam_date" class="block text-sm font-medium text-gray-700">Exam Date *</label>
            <input type="date" id="exam_date" name="exam_date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" min="<?= date('Y-m-d') ?>">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="start_time" class="block text-sm font-medium text-gray-700">Start Time *</label>
            <input type="time" id="start_time" name="start_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div>
            <label for="end_time" class="block text-sm font-medium text-gray-700">End Time *</label>
            <input type="time" id="end_time" name="end_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="exam_type" class="block text-sm font-medium text-gray-700">Exam Type</label>
            <select id="exam_type" name="exam_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option>Written</option>
              <option>Viva</option>
              <option>Practical</option>
              <option>Project Review</option>
            </select>
          </div>
          <div>
            <label for="department" class="block text-sm font-medium text-gray-700">Department / Subject</label>
            <input type="text" id="department" name="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="semester" class="block text-sm font-medium text-gray-700">Semester / Year</label>
              <input type="text" id="semester" name="semester" placeholder="e.g., 5th Sem" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
              <label for="batch" class="block text-sm font-medium text-gray-700">Batch / Section</label>
              <input type="text" id="batch" name="batch" placeholder="e.g., 2022-26 A" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div>
          <label for="college_name" class="block text-sm font-medium text-gray-700">College Name</label>
          <input type="text" id="college_name" name="college_name" value="<?= htmlspecialchars($_SESSION['user_college'] ?? 'N/A') ?>" readonly disabled class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="venue" class="block text-sm font-medium text-gray-700">Venue / Room</label>
            <input type="text" id="venue" name="venue" placeholder="e.g., Main Auditorium" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div>
            <label for="coordinator" class="block text-sm font-medium text-gray-700">Exam Coordinator</label>
            <input type="text" id="coordinator" name="coordinator" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
        </div>

        <div>
          <label for="examiners_needed" class="block text-sm font-medium text-gray-700">Number of External Examiners Needed *</label>
          <input type="number" id="examiners_needed" name="examiners_needed" required value="1" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
          <label for="details" class="block text-sm font-medium text-gray-700">Exam Details / Remarks</label>
          <textarea id="details" name="details" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        </div>

        <div class="flex justify-end">
          <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Schedule Exam
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
