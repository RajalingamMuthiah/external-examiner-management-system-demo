<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Require login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_college'])) {
    header('Location: login.php');
    exit;
}

// Initialize exams array
$otherExams = [];
$error_message = '';

try {
    $currentUserCollege = $_SESSION['user_college'];

    // --- Fetch Exams from Other Colleges ---
    // Fetches upcoming exams from all colleges except the user's own
    $sql = "SELECT id, title, exam_date, college_name, exam_type, details 
            FROM exam_schedule 
            WHERE college_name != :current_college AND exam_date >= CURDATE()
            ORDER BY exam_date ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':current_college', $currentUserCollege, PDO::PARAM_STR);
    $stmt->execute();
    
    $otherExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('View other exams error: ' . $e->getMessage());
    $error_message = "Sorry, we couldn't load the exam schedules at this time. Please try again later.";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Other College Exams — EEMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">External Exams at Other Colleges</h1>
        <p class="text-sm text-gray-500">Available opportunities for examiner duties.</p>
      </div>
      <div class="text-right">
        <a href="dashboard.php" class="text-sm text-blue-600">← Back to dashboard</a>
      </div>
    </div>

    <?php if ($error_message): ?>
      <div class="bg-red-100 text-red-700 p-4 rounded-lg">
        <?= htmlspecialchars($error_message) ?>
      </div>
    <?php elseif (empty($otherExams)): ?>
      <div class="bg-blue-100 text-blue-700 p-6 rounded-lg text-center">
        <h3 class="font-semibold">No Available Exams</h3>
        <p>There are currently no upcoming external exam schedules from other colleges.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($otherExams as $exam): ?>
          <div class="bg-white rounded-xl shadow p-6 flex flex-col">
            <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($exam['title']) ?></h3>
            <p class="text-sm font-semibold text-indigo-600 mt-1"><?= htmlspecialchars($exam['college_name']) ?></p>
            
            <div class="my-4 border-t border-gray-100"></div>
            
            <div class="text-sm space-y-2">
              <p><strong>Date:</strong> <?= date('F j, Y', strtotime($exam['exam_date'])) ?></p>
              <p><strong>Type:</strong> <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs"><?= htmlspecialchars($exam['exam_type']) ?></span></p>
              <p class="text-gray-600 truncate"><?= htmlspecialchars(substr($exam['details'], 0, 80)) . (strlen($exam['details']) > 80 ? '...' : '') ?></p>
            </div>

            <div class="mt-auto pt-4">
              <a href="view_exam_details.php?id=<?= $exam['id'] ?>" class="block text-center w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow">
                View Details / Apply
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
