<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Ensure user is logged in - redirect to login page if not
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_college'])) {
    header('Location: login.php');
    exit;
}

// Base URL
$base_url = ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

// Handle Application Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $exam_id = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF token invalid.']);
        exit;
    }

    if ($exam_id) {
        try {
            if ($action === 'apply') {
                // Apply for Exam
                $sql = "INSERT INTO examiner_applications (exam_id, user_id, status) VALUES (:exam_id, :user_id, 'pending')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['exam_id' => $exam_id, 'user_id' => $_SESSION['user_id']]);
                echo json_encode(['status' => 'success', 'message' => 'Application submitted.']);
                exit();
            } elseif ($action === 'unapply') {
                // Unapply for Exam
                $sql = "DELETE FROM examiner_applications WHERE exam_id = :exam_id AND user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['exam_id' => $exam_id, 'user_id' => $_SESSION['user_id']]);
                echo json_encode(['status' => 'success', 'message' => 'Application withdrawn.']);
                exit();
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
            exit();
        }
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

// Fetch Exams Data
$user_id = $_SESSION['user_id'];
$user_college = $_SESSION['user_college'];

// Data Arrays
$availableExams = [];
$appliedExams = [];
$summary = ['available' => 0, 'applied' => 0, 'pending_reports' => 0];
$error = null;

try {
    // Available Exams (not from user college and not yet applied for)
    $sql = "SELECT id, exam_name, exam_date, exam_type, college_name, details FROM exam_schedule 
            WHERE college_name != :user_college AND exam_date >= CURDATE() 
            AND id NOT IN (SELECT exam_id FROM examiner_applications WHERE user_id = :user_id) ORDER BY exam_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_college', $user_college, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $availableExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Applied Exams
    $sql = "SELECT es.id, es.exam_name, es.exam_date, exam_type, es.college_name, es.venue FROM exam_schedule es 
            INNER JOIN examiner_applications ea ON es.id = ea.exam_id WHERE ea.user_id = :user_id ORDER BY es.exam_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $appliedExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary Data
    $summary['available'] = count($availableExams);
    $summary['applied'] = count($appliedExams);
    $summary['pending_reports'] = 5;  // PlaceHolder: Replace with a real calculation

    // Generate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];


} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script>
        function applyForExam(examId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=apply&exam_id=${examId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`,
            }).then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        window.location.reload(); // Refresh page on success
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An unexpected error occurred.");
                });
        }

        function unapplyForExam(examId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=unapply&exam_id=${examId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`,
            }).then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        window.location.reload(); // Refresh page on success
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An unexpected error occurred.");
                });
        }


    </script>
</head>
<body class="bg-gray-100 h-screen flex">


    <!-- Sidebar -->
    <aside class="w-64 bg-blue-900 text-white flex flex-col">
        <div class="p-6 border-b border-blue-700">
            <h2 class="text-2xl font-bold">EEMS</h2>
            <p class="text-sm text-blue-200">Faculty Dashboard</p>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="#" class="flex items-center px-4 py-2 rounded bg-blue-800">
                <span class="mr-3"><i class="fas fa-home"></i></span>
                <span>Dashboard</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 rounded hover:bg-blue-800">
                <span class="mr-3"><i class="fas fa-check-square"></i></span>
                <span>My Applications</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 rounded hover:bg-blue-800">
                <span class="mr-3"><i class="fas fa-file-alt"></i></span>
                <span>Reports</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 rounded hover:bg-blue-800">
                <span class="mr-3"><i class="fas fa-cog"></i></span>
                <span>Settings</span>
            </a>
        </nav>
        <div class="p-4 border-t border-blue-700">
            <a href="logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-2 rounded font-semibold">Logout</a>
        </div>
    </aside>

    <div class="flex flex-col flex-1 overflow-hidden">
     <header class="flex justify-between items-center bg-white border-b border-gray-200 p-4">
            <div class="flex items-center">
                <h1 class="text-2xl font-semibold text-gray-800 mr-4">Faculty Dashboard</h1>
                <span class="text-gray-500">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            </div>
            <div class="text-sm text-gray-500">Today is <?php echo date('l, F j, Y'); ?></div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4">

  <!-- Summary Cards -->
            <?php if ($error) : ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="text-gray-700 font-semibold">Available Exams</h3>
                    <p class="text-3xl text-blue-500 font-bold"><?= htmlspecialchars($summary['available']) ?></p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="text-gray-700 font-semibold">Applied Exams</h3>
                    <p class="text-3xl text-green-500 font-bold"><?= htmlspecialchars($summary['applied']) ?></p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="text-gray-700 font-semibold">Pending Reports</h3>
                    <p class="text-3xl text-yellow-500 font-bold"><?= htmlspecialchars($summary['pending_reports']) ?></p>
                </div>
            </div>


              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
 <!-- Available Exams Section -->
                <div class="bg-white shadow rounded-lg">
                    <div class="p-4 border-b">
                        <h5 class="text-lg font-semibold text-gray-700">Available External Exams</h5>
                    </div>
                    <ul class="divide-y divide-gray-200">
                    <?php if (empty($availableExams)): ?>
                        <li class="p-4 text-gray-500">No available exams at this time.</li>
                    <?php else: ?>
                        <?php foreach ($availableExams as $exam): ?>
                            <li class="p-4 flex items-center justify-between">
                                <div>
                                    <h6 class="text-gray-800 font-semibold"><?= htmlspecialchars($exam['exam_name']) ?></h6>
                                    <p class="text-gray-500 text-sm"><?= htmlspecialchars($exam['exam_date']) ?> - <?= htmlspecialchars($exam['exam_type']) ?> (<?= htmlspecialchars($exam['college_name']) ?>)</p>
                                    <p class="text-gray-600 text-xs mt-1 truncate"><?= htmlspecialchars($exam['details']) ?></p>
                                </div>
                                <form method="post" class="flex-shrink-0">
                                    <input type="hidden" name="exam_id" value="<?= htmlspecialchars($exam['id']) ?>">
                                    <input type="hidden" name="action" value="apply">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Apply</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </ul>
                </div>

  <!-- My Applied Exams Section -->
                <div class="bg-white shadow rounded-lg">
                    <div class="p-4 border-b">
                        <h5 class="text-lg font-semibold text-gray-700">My Applied Exams</h5>
                    </div>
                    <ul class="divide-y divide-gray-200">
                    <?php if (empty($appliedExams)): ?>
                        <li class="p-4 text-gray-500">No exams applied for yet.</li>
                    <?php else: ?>
                        <?php foreach ($appliedExams as $exam): ?>
                            <li class="p-4 flex items-center justify-between">
                                <div>
                                    <h6 class="text-gray-800 font-semibold"><?= htmlspecialchars($exam['exam_name']) ?></h6>
                                    <p class="text-gray-500 text-sm"><?= htmlspecialchars($exam['exam_date']) ?> - <?= htmlspecialchars($exam['exam_type']) ?> (<?= htmlspecialchars($exam['college_name']) ?>)</p>
                                    <p class="text-gray-600 text-xs mt-1 truncate"><?= htmlspecialchars($exam['venue']) ?></p>
                                </div>
                                <form method="post" class="flex-shrink-0">
                                    <input type="hidden" name="exam_id" value="<?= htmlspecialchars($exam['id']) ?>">
                                    <input type="hidden" name="action" value="unapply">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Withdraw</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </ul>
                </div>
            </div>
             <footer class="bg-white p-4 text-center">
               <p class="text-gray-600 text-sm">Copyright © 2023 EEMS. All rights reserved.</p>
            </footer>
        </main>
    </div>
</body>
</html>