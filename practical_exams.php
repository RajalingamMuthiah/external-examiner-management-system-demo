<?php
/**
 * Practical Exam Management
 * File: practical_exams.php
 * Purpose: Manage practical exam sessions, student attempts, evaluations
 * 
 * Features:
 * - Create practical exam sessions with time slots
 * - Track student attempts and submissions
 * - Examiner evaluation interface
 * - Real-time status updates
 * - Performance analytics
 * 
 * Tables: practical_exam_sessions, practical_exam_attempts, exam_assignments, exams, users
 * Roles: Teacher, HOD, Vice-Principal, Principal
 */

require_once 'includes/functions.php';
require_once 'config/db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];
$currentUserName = $_SESSION['name'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    // Create new practical session
    if ($action === 'create_session') {
        $examId = intval($_POST['exam_id']);
        $sessionDate = $_POST['session_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $maxStudents = intval($_POST['max_students']);
        $labRoom = $_POST['lab_room'];
        $instructions = $_POST['instructions'];
        
        try {
            // Verify user is assigned as examiner
            $stmt = $pdo->prepare("
                SELECT ea.assignment_id, e.exam_name 
                FROM exam_assignments ea
                JOIN exams e ON ea.exam_id = e.exam_id
                WHERE ea.exam_id = ? AND ea.user_id = ? 
                AND ea.assignment_status = 'accepted'
            ");
            $stmt->execute([$examId, $currentUserId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assignment) {
                echo json_encode(['success' => false, 'message' => 'You are not assigned as examiner for this exam']);
                exit;
            }
            
            // Validate date/time
            $sessionDateTime = strtotime("$sessionDate $startTime");
            $endDateTime = strtotime("$sessionDate $endTime");
            
            if ($sessionDateTime < time()) {
                echo json_encode(['success' => false, 'message' => 'Session date/time must be in the future']);
                exit;
            }
            
            if ($endDateTime <= $sessionDateTime) {
                echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
                exit;
            }
            
            // Check for conflicts (same examiner, overlapping time)
            $stmt = $pdo->prepare("
                SELECT session_id, lab_room 
                FROM practical_exam_sessions 
                WHERE examiner_id = ? 
                AND session_date = ?
                AND status != 'cancelled'
                AND (
                    (start_time <= ? AND end_time > ?)
                    OR (start_time < ? AND end_time >= ?)
                    OR (start_time >= ? AND end_time <= ?)
                )
            ");
            $stmt->execute([$currentUserId, $sessionDate, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Time slot conflicts with existing session']);
                exit;
            }
            
            // Insert session
            $stmt = $pdo->prepare("
                INSERT INTO practical_exam_sessions 
                (exam_id, examiner_id, session_date, start_time, end_time, max_students, lab_room, instructions, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
            ");
            $stmt->execute([$examId, $currentUserId, $sessionDate, $startTime, $endTime, $maxStudents, $labRoom, $instructions]);
            
            $sessionId = $pdo->lastInsertId();
            
            // Audit log
            logAudit($pdo, 'practical_session', $sessionId, 'create', $currentUserId, [
                'exam_id' => $examId,
                'session_date' => $sessionDate,
                'lab_room' => $labRoom
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Practical session created successfully', 'session_id' => $sessionId]);
            exit;
            
        } catch (PDOException $e) {
            error_log('Create session error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Update session status
    if ($action === 'update_session_status') {
        $sessionId = intval($_POST['session_id']);
        $newStatus = $_POST['status'];
        
        $allowedStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        try {
            // Verify user is the examiner
            $stmt = $pdo->prepare("SELECT examiner_id, status FROM practical_exam_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Session not found']);
                exit;
            }
            
            if ($session['examiner_id'] != $currentUserId && !in_array($currentUserRole, ['admin', 'principal'])) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            // Update status
            $stmt = $pdo->prepare("UPDATE practical_exam_sessions SET status = ? WHERE session_id = ?");
            $stmt->execute([$newStatus, $sessionId]);
            
            // Audit log
            logAudit($pdo, 'practical_session', $sessionId, 'status_update', $currentUserId, [
                'old_status' => $session['status'],
                'new_status' => $newStatus
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Session status updated']);
            exit;
            
        } catch (PDOException $e) {
            error_log('Update session status error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }
    
    // Record student attempt
    if ($action === 'record_attempt') {
        $sessionId = intval($_POST['session_id']);
        $studentName = trim($_POST['student_name']);
        $studentRollNo = trim($_POST['student_roll_no']);
        $performanceNotes = trim($_POST['performance_notes']);
        $marksObtained = floatval($_POST['marks_obtained']);
        $totalMarks = floatval($_POST['total_marks']);
        
        try {
            // Verify examiner
            $stmt = $pdo->prepare("
                SELECT pes.examiner_id, pes.max_students, pes.status,
                       (SELECT COUNT(*) FROM practical_exam_attempts WHERE session_id = pes.session_id) as current_count
                FROM practical_exam_sessions pes
                WHERE pes.session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Session not found']);
                exit;
            }
            
            if ($session['examiner_id'] != $currentUserId) {
                echo json_encode(['success' => false, 'message' => 'Only the session examiner can record attempts']);
                exit;
            }
            
            if ($session['status'] === 'cancelled') {
                echo json_encode(['success' => false, 'message' => 'Cannot record attempts for cancelled session']);
                exit;
            }
            
            // Check capacity
            if ($session['current_count'] >= $session['max_students']) {
                echo json_encode(['success' => false, 'message' => 'Session has reached maximum capacity']);
                exit;
            }
            
            // Validate marks
            if ($marksObtained < 0 || $marksObtained > $totalMarks) {
                echo json_encode(['success' => false, 'message' => 'Invalid marks: must be between 0 and total marks']);
                exit;
            }
            
            $percentage = ($totalMarks > 0) ? ($marksObtained / $totalMarks * 100) : 0;
            $result = ($percentage >= 40) ? 'pass' : 'fail';
            
            // Insert attempt
            $stmt = $pdo->prepare("
                INSERT INTO practical_exam_attempts 
                (session_id, student_name, student_roll_no, performance_notes, marks_obtained, total_marks, percentage, result, evaluated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$sessionId, $studentName, $studentRollNo, $performanceNotes, $marksObtained, $totalMarks, $percentage, $result]);
            
            $attemptId = $pdo->lastInsertId();
            
            // Audit log
            logAudit($pdo, 'practical_attempt', $attemptId, 'record', $currentUserId, [
                'session_id' => $sessionId,
                'student_roll_no' => $studentRollNo,
                'marks' => "$marksObtained/$totalMarks",
                'result' => $result
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Student attempt recorded successfully',
                'attempt_id' => $attemptId,
                'percentage' => round($percentage, 2),
                'result' => $result
            ]);
            exit;
            
        } catch (PDOException $e) {
            error_log('Record attempt error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }
    
    // Delete attempt
    if ($action === 'delete_attempt') {
        $attemptId = intval($_POST['attempt_id']);
        
        try {
            // Verify examiner
            $stmt = $pdo->prepare("
                SELECT pea.attempt_id, pes.examiner_id 
                FROM practical_exam_attempts pea
                JOIN practical_exam_sessions pes ON pea.session_id = pes.session_id
                WHERE pea.attempt_id = ?
            ");
            $stmt->execute([$attemptId]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                echo json_encode(['success' => false, 'message' => 'Attempt not found']);
                exit;
            }
            
            if ($attempt['examiner_id'] != $currentUserId && !in_array($currentUserRole, ['admin', 'principal'])) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            // Delete attempt
            $stmt = $pdo->prepare("DELETE FROM practical_exam_attempts WHERE attempt_id = ?");
            $stmt->execute([$attemptId]);
            
            // Audit log
            logAudit($pdo, 'practical_attempt', $attemptId, 'delete', $currentUserId, []);
            
            echo json_encode(['success' => true, 'message' => 'Attempt deleted successfully']);
            exit;
            
        } catch (PDOException $e) {
            error_log('Delete attempt error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }
}

// Fetch user's assigned exams
$assignedExams = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.exam_id, e.exam_name, e.exam_date, e.exam_type, 
               c.college_name, ea.duty_type
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN colleges c ON e.college_id = c.college_id
        WHERE ea.user_id = ? 
        AND ea.assignment_status = 'accepted'
        AND e.exam_type = 'practical'
        ORDER BY e.exam_date DESC
    ");
    $stmt->execute([$currentUserId]);
    $assignedExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch assigned exams error: ' . $e->getMessage());
}

// Fetch user's practical sessions
$mySessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT pes.*, e.exam_name, e.exam_date, c.college_name,
               (SELECT COUNT(*) FROM practical_exam_attempts WHERE session_id = pes.session_id) as attempt_count
        FROM practical_exam_sessions pes
        JOIN exams e ON pes.exam_id = e.exam_id
        JOIN colleges c ON e.college_id = c.college_id
        WHERE pes.examiner_id = ?
        ORDER BY pes.session_date DESC, pes.start_time DESC
        LIMIT 50
    ");
    $stmt->execute([$currentUserId]);
    $mySessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch sessions error: ' . $e->getMessage());
}

// Fetch attempts for a specific session (if session_id in query)
$sessionAttempts = [];
$currentSession = null;
if (isset($_GET['session_id'])) {
    $sessionId = intval($_GET['session_id']);
    
    try {
        // Get session details
        $stmt = $pdo->prepare("
            SELECT pes.*, e.exam_name, e.exam_date, c.college_name, u.name as examiner_name
            FROM practical_exam_sessions pes
            JOIN exams e ON pes.exam_id = e.exam_id
            JOIN colleges c ON e.college_id = c.college_id
            JOIN users u ON pes.examiner_id = u.user_id
            WHERE pes.session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $currentSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get attempts
        if ($currentSession) {
            $stmt = $pdo->prepare("
                SELECT * FROM practical_exam_attempts 
                WHERE session_id = ?
                ORDER BY evaluated_at DESC
            ");
            $stmt->execute([$sessionId]);
            $sessionAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Fetch session details error: ' . $e->getMessage());
    }
}

// Calculate statistics
$stats = [
    'total_sessions' => count($mySessions),
    'upcoming_sessions' => 0,
    'completed_sessions' => 0,
    'total_evaluations' => 0
];

foreach ($mySessions as $session) {
    if ($session['status'] === 'completed') {
        $stats['completed_sessions']++;
    } elseif ($session['status'] === 'scheduled') {
        $stats['upcoming_sessions']++;
    }
    $stats['total_evaluations'] += $session['attempt_count'];
}

require_once 'includes/head.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical Exam Management - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .session-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .session-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .session-card.status-completed {
            border-left-color: #198754;
        }
        .session-card.status-in_progress {
            border-left-color: #ffc107;
        }
        .session-card.status-cancelled {
            border-left-color: #dc3545;
        }
        .attempt-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            transition: background 0.2s;
        }
        .attempt-row:hover {
            background: #e9ecef;
        }
        .result-badge-pass {
            background: #d1e7dd;
            color: #0f5132;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .result-badge-fail {
            background: #f8d7da;
            color: #842029;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
        }
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .time-slot {
            background: #e7f3ff;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-flask me-2"></i>Practical Exam Management</h2>
                        <p class="text-muted mb-0">Manage practical exam sessions and evaluate student performance</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                        <i class="fas fa-plus me-2"></i>Create New Session
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?= $stats['total_sessions'] ?></h3>
                                    <small>Total Sessions</small>
                                </div>
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?= $stats['upcoming_sessions'] ?></h3>
                                    <small>Upcoming</small>
                                </div>
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?= $stats['completed_sessions'] ?></h3>
                                    <small>Completed</small>
                                </div>
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?= $stats['total_evaluations'] ?></h3>
                                    <small>Evaluations</small>
                                </div>
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($currentSession): ?>
                    <!-- Session Detail View -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Session Details
                                </h5>
                                <a href="practical_exams.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Sessions
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Exam:</strong> <?= htmlspecialchars($currentSession['exam_name']) ?></p>
                                    <p><strong>College:</strong> <?= htmlspecialchars($currentSession['college_name']) ?></p>
                                    <p><strong>Examiner:</strong> <?= htmlspecialchars($currentSession['examiner_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Date:</strong> <?= date('d M Y', strtotime($currentSession['session_date'])) ?></p>
                                    <p><strong>Time:</strong> <span class="time-slot"><?= date('h:i A', strtotime($currentSession['start_time'])) ?> - <?= date('h:i A', strtotime($currentSession['end_time'])) ?></span></p>
                                    <p><strong>Lab Room:</strong> <?= htmlspecialchars($currentSession['lab_room']) ?></p>
                                    <p><strong>Capacity:</strong> <?= count($sessionAttempts) ?> / <?= $currentSession['max_students'] ?> students</p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?= $currentSession['status'] === 'completed' ? 'success' : ($currentSession['status'] === 'in_progress' ? 'warning' : 'primary') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $currentSession['status'])) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <?php if (!empty($currentSession['instructions'])): ?>
                                <div class="alert alert-info mt-3">
                                    <strong>Instructions:</strong><br>
                                    <?= nl2br(htmlspecialchars($currentSession['instructions'])) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <?php if ($currentSession['examiner_id'] == $currentUserId): ?>
                                <div class="mt-3">
                                    <?php if ($currentSession['status'] === 'scheduled'): ?>
                                        <button class="btn btn-warning" onclick="updateSessionStatus(<?= $currentSession['session_id'] ?>, 'in_progress')">
                                            <i class="fas fa-play me-1"></i>Start Session
                                        </button>
                                    <?php elseif ($currentSession['status'] === 'in_progress'): ?>
                                        <button class="btn btn-success" onclick="updateSessionStatus(<?= $currentSession['session_id'] ?>, 'completed')">
                                            <i class="fas fa-check me-1"></i>Mark as Completed
                                        </button>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordAttemptModal">
                                            <i class="fas fa-plus me-1"></i>Record Student Attempt
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($currentSession['status'] === 'scheduled'): ?>
                                        <button class="btn btn-danger" onclick="updateSessionStatus(<?= $currentSession['session_id'] ?>, 'cancelled')">
                                            <i class="fas fa-times me-1"></i>Cancel Session
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Student Attempts -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Student Attempts (<?= count($sessionAttempts) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sessionAttempts)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No student attempts recorded yet.
                                </div>
                            <?php else: ?>
                                <?php foreach ($sessionAttempts as $attempt): ?>
                                    <div class="attempt-row">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($attempt['student_name']) ?></h6>
                                                <small class="text-muted">Roll No: <?= htmlspecialchars($attempt['student_roll_no']) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <div class="mb-1">
                                                    <strong><?= $attempt['marks_obtained'] ?> / <?= $attempt['total_marks'] ?></strong>
                                                    <span class="text-muted ms-2">(<?= round($attempt['percentage'], 2) ?>%)</span>
                                                </div>
                                                <span class="result-badge-<?= $attempt['result'] ?>">
                                                    <?= strtoupper($attempt['result']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if (!empty($attempt['performance_notes'])): ?>
                                            <div class="mt-2 text-muted">
                                                <small><i class="fas fa-comment me-1"></i><?= htmlspecialchars($attempt['performance_notes']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>Evaluated: <?= date('d M Y, h:i A', strtotime($attempt['evaluated_at'])) ?>
                                            </small>
                                            <?php if ($currentSession['examiner_id'] == $currentUserId): ?>
                                                <button class="btn btn-sm btn-danger float-end" onclick="deleteAttempt(<?= $attempt['attempt_id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Sessions List View -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>My Practical Sessions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($mySessions)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No practical sessions created yet. Click "Create New Session" to get started.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($mySessions as $session): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card session-card status-<?= $session['status'] ?>">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="mb-0"><?= htmlspecialchars($session['exam_name']) ?></h5>
                                                        <span class="badge bg-<?= $session['status'] === 'completed' ? 'success' : ($session['status'] === 'in_progress' ? 'warning' : ($session['status'] === 'cancelled' ? 'danger' : 'primary')) ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $session['status'])) ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-muted mb-2">
                                                        <i class="fas fa-university me-1"></i><?= htmlspecialchars($session['college_name']) ?>
                                                    </p>
                                                    <p class="mb-2">
                                                        <i class="fas fa-calendar me-1"></i><?= date('d M Y', strtotime($session['session_date'])) ?>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="time-slot">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?= date('h:i A', strtotime($session['start_time'])) ?> - <?= date('h:i A', strtotime($session['end_time'])) ?>
                                                        </span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <i class="fas fa-door-open me-1"></i><?= htmlspecialchars($session['lab_room']) ?>
                                                    </p>
                                                    <p class="mb-3">
                                                        <i class="fas fa-users me-1"></i>
                                                        <?= $session['attempt_count'] ?> / <?= $session['max_students'] ?> students evaluated
                                                    </p>
                                                    <a href="practical_exams.php?session_id=<?= $session['session_id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Session Modal -->
    <div class="modal fade" id="createSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create Practical Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createSessionForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select class="form-control" name="exam_id" required>
                                <option value="">Select Exam</option>
                                <?php foreach ($assignedExams as $exam): ?>
                                    <option value="<?= $exam['exam_id'] ?>">
                                        <?= htmlspecialchars($exam['exam_name']) ?> - <?= htmlspecialchars($exam['college_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Session Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="session_date" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="end_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lab Room <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="lab_room" placeholder="e.g., Lab 101" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Students <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="max_students" min="1" max="100" value="30" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Instructions</label>
                            <textarea class="form-control" name="instructions" rows="3" placeholder="Session instructions for students..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Session
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Attempt Modal -->
    <?php if ($currentSession && $currentSession['examiner_id'] == $currentUserId): ?>
    <div class="modal fade" id="recordAttemptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Record Student Attempt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="recordAttemptForm">
                    <input type="hidden" name="session_id" value="<?= $currentSession['session_id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="student_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Roll Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="student_roll_no" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Marks Obtained <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="marks_obtained" min="0" step="0.5" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="total_marks" min="1" step="0.5" value="50" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Performance Notes</label>
                            <textarea class="form-control" name="performance_notes" rows="3" placeholder="Comments on student performance..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Attempt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create session
        document.getElementById('createSessionForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_session');
            
            try {
                const response = await fetch('practical_exams.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    window.location.href = 'practical_exams.php?session_id=' + result.session_id;
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });

        // Record attempt
        document.getElementById('recordAttemptForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'record_attempt');
            
            try {
                const response = await fetch('practical_exams.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message + '\nResult: ' + result.result.toUpperCase() + ' (' + result.percentage + '%)');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });

        // Update session status
        async function updateSessionStatus(sessionId, newStatus) {
            const confirmMessages = {
                'in_progress': 'Start this session?',
                'completed': 'Mark this session as completed?',
                'cancelled': 'Cancel this session? This cannot be undone.'
            };
            
            if (!confirm(confirmMessages[newStatus])) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_session_status');
            formData.append('session_id', sessionId);
            formData.append('status', newStatus);
            
            try {
                const response = await fetch('practical_exams.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }

        // Delete attempt
        async function deleteAttempt(attemptId) {
            if (!confirm('Delete this student attempt? This cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_attempt');
            formData.append('attempt_id', attemptId);
            
            try {
                const response = await fetch('practical_exams.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
    </script>
</body>
</html>
