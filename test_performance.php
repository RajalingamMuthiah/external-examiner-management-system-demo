<?php
/**
 * PERFORMANCE TESTING TOOL
 * File: test_performance.php
 * Purpose: Benchmark database queries and identify bottlenecks
 * 
 * Features:
 * - Query execution time measurement
 * - Index usage analysis
 * - Slow query identification
 * - Memory usage tracking
 * - Recommendations for optimization
 */

require_once 'config/db.php';

// Performance tracking
$benchmarks = [];
$totalQueries = 0;
$totalTime = 0;

function benchmark($name, $query, $params = []) {
    global $pdo, $benchmarks, $totalQueries, $totalTime;
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rowCount = count($results);
    } catch (PDOException $e) {
        $rowCount = 0;
        $error = $e->getMessage();
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    
    $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    $memoryUsed = ($endMemory - $startMemory) / 1024; // Convert to KB
    
    $totalQueries++;
    $totalTime += $executionTime;
    
    // Analyze query plan
    $explainStmt = $pdo->prepare("EXPLAIN $query");
    $explainStmt->execute($params);
    $explainResults = $explainStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $usesIndex = false;
    $usesFilesort = false;
    $usesTemporary = false;
    
    foreach ($explainResults as $row) {
        if (isset($row['key']) && $row['key'] !== null) {
            $usesIndex = true;
        }
        if (isset($row['Extra'])) {
            if (strpos($row['Extra'], 'filesort') !== false) $usesFilesort = true;
            if (strpos($row['Extra'], 'temporary') !== false) $usesTemporary = true;
        }
    }
    
    $benchmarks[] = [
        'name' => $name,
        'time' => $executionTime,
        'memory' => $memoryUsed,
        'rows' => $rowCount,
        'uses_index' => $usesIndex,
        'uses_filesort' => $usesFilesort,
        'uses_temporary' => $usesTemporary,
        'error' => $error ?? null
    ];
    
    return $benchmarks[count($benchmarks) - 1];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Test - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .fast { background: #d4edda; }
        .medium { background: #fff3cd; }
        .slow { background: #f8d7da; }
        .no-index { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4">
        <i class="fas fa-tachometer-alt me-2"></i>Performance Benchmark Results
    </h1>
    
    <?php
    // ============================================================================
    // RUN BENCHMARKS
    // ============================================================================
    
    echo "<h3 class='mt-4'>Running Benchmarks...</h3>";
    
    // Test 1: Exam listing with filters
    benchmark(
        'Exam Listing (with college filter)',
        'SELECT * FROM exams WHERE college_id = ? AND status = ? ORDER BY exam_date DESC LIMIT 20',
        [1, 'approved']
    );
    
    // Test 2: User's assignments
    benchmark(
        'User Assignments',
        'SELECT ea.*, e.exam_name FROM exam_assignments ea JOIN exams e ON ea.exam_id = e.exam_id WHERE ea.user_id = ?',
        [1]
    );
    
    // Test 3: Unread notifications count
    benchmark(
        'Unread Notifications Count',
        'SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_on IS NULL',
        [1]
    );
    
    // Test 4: Faculty listing
    benchmark(
        'Faculty List (role + college filter)',
        'SELECT * FROM users WHERE role IN ("teacher", "hod") AND college_id = ? AND status = "verified" ORDER BY name LIMIT 50',
        [1]
    );
    
    // Test 5: Exam with assignments (complex join)
    benchmark(
        'Exam Details with Assignments',
        'SELECT e.*, u.name as creator_name, COUNT(ea.assignment_id) as assignment_count 
         FROM exams e 
         LEFT JOIN users u ON e.created_by = u.user_id 
         LEFT JOIN exam_assignments ea ON e.exam_id = ea.exam_id 
         WHERE e.exam_id = ?
         GROUP BY e.exam_id',
        [1]
    );
    
    // Test 6: Practical sessions for examiner
    benchmark(
        'Practical Sessions (date range)',
        'SELECT * FROM practical_exam_sessions 
         WHERE examiner_id = ? 
         AND session_date BETWEEN ? AND ? 
         ORDER BY session_date DESC',
        [1, date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]
    );
    
    // Test 7: Examiner ratings
    benchmark(
        'Examiner Ratings (with average)',
        'SELECT examiner_id, AVG(rating_score) as avg_rating, COUNT(*) as rating_count 
         FROM ratings 
         WHERE examiner_id = ?
         GROUP BY examiner_id',
        [1]
    );
    
    // Test 8: Question papers for exam
    benchmark(
        'Question Papers List',
        'SELECT * FROM question_papers WHERE exam_id = ? ORDER BY version DESC',
        [1]
    );
    
    // Test 9: Audit log for entity
    benchmark(
        'Audit Log (entity type filter)',
        'SELECT * FROM audit_logs WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC LIMIT 20',
        ['exam', 1]
    );
    
    // Test 10: Dashboard statistics (complex aggregation)
    benchmark(
        'Dashboard Statistics',
        'SELECT 
            COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = "approved" THEN 1 END) as approved_count,
            COUNT(CASE WHEN exam_date >= CURDATE() THEN 1 END) as upcoming_count
         FROM exams 
         WHERE college_id = ?',
        [1]
    );
    
    // ============================================================================
    // DISPLAY RESULTS
    // ============================================================================
    
    echo "<h3 class='mt-5 mb-4'>Benchmark Results</h3>";
    echo "<table class='table table-bordered'>";
    echo "<thead class='table-dark'>
        <tr>
            <th>Query Name</th>
            <th>Time (ms)</th>
            <th>Memory (KB)</th>
            <th>Rows</th>
            <th>Index Used</th>
            <th>Filesort</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>";
    
    foreach ($benchmarks as $b) {
        // Determine speed class
        $speedClass = 'fast';
        $speedLabel = 'Fast';
        if ($b['time'] > 10) {
            $speedClass = 'medium';
            $speedLabel = 'Medium';
        }
        if ($b['time'] > 50) {
            $speedClass = 'slow';
            $speedLabel = 'Slow';
        }
        
        $indexClass = $b['uses_index'] ? '' : 'no-index';
        
        echo "<tr class='$speedClass $indexClass'>";
        echo "<td><strong>{$b['name']}</strong></td>";
        echo "<td>" . number_format($b['time'], 2) . " ms</td>";
        echo "<td>" . number_format($b['memory'], 2) . " KB</td>";
        echo "<td>{$b['rows']}</td>";
        echo "<td>" . ($b['uses_index'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>') . "</td>";
        echo "<td>" . ($b['uses_filesort'] ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-success">No</span>') . "</td>";
        echo "<td><span class='badge bg-" . ($speedClass === 'fast' ? 'success' : ($speedClass === 'medium' ? 'warning' : 'danger')) . "'>$speedLabel</span></td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    
    // ============================================================================
    // STATISTICS
    // ============================================================================
    
    $avgTime = $totalTime / $totalQueries;
    $slowQueries = array_filter($benchmarks, fn($b) => $b['time'] > 50);
    $noIndexQueries = array_filter($benchmarks, fn($b) => !$b['uses_index']);
    
    echo "<div class='row mt-4'>";
    echo "<div class='col-md-3'>
        <div class='card'>
            <div class='card-body text-center'>
                <h3>$totalQueries</h3>
                <p class='mb-0'>Total Queries</p>
            </div>
        </div>
    </div>";
    
    echo "<div class='col-md-3'>
        <div class='card'>
            <div class='card-body text-center'>
                <h3>" . number_format($totalTime, 2) . " ms</h3>
                <p class='mb-0'>Total Time</p>
            </div>
        </div>
    </div>";
    
    echo "<div class='col-md-3'>
        <div class='card'>
            <div class='card-body text-center'>
                <h3>" . number_format($avgTime, 2) . " ms</h3>
                <p class='mb-0'>Average Time</p>
            </div>
        </div>
    </div>";
    
    echo "<div class='col-md-3'>
        <div class='card'>
            <div class='card-body text-center'>
                <h3>" . count($slowQueries) . "</h3>
                <p class='mb-0'>Slow Queries</p>
            </div>
        </div>
    </div>";
    echo "</div>";
    
    // ============================================================================
    // RECOMMENDATIONS
    // ============================================================================
    
    echo "<div class='card mt-4'>";
    echo "<div class='card-body'>";
    echo "<h4><i class='fas fa-lightbulb me-2'></i>Performance Recommendations</h4>";
    echo "<ul class='mb-0'>";
    
    if (count($noIndexQueries) > 0) {
        echo "<li class='text-danger'><strong>Critical:</strong> " . count($noIndexQueries) . " quer(ies) not using indexes. Run db/optimize_performance.sql to add indexes.</li>";
    } else {
        echo "<li class='text-success'><strong>Good:</strong> All queries using indexes efficiently.</li>";
    }
    
    if (count($slowQueries) > 0) {
        echo "<li class='text-warning'><strong>Warning:</strong> " . count($slowQueries) . " slow quer(ies) detected (>50ms). Consider optimization.</li>";
    } else {
        echo "<li class='text-success'><strong>Excellent:</strong> All queries executing under 50ms.</li>";
    }
    
    if ($avgTime < 10) {
        echo "<li class='text-success'><strong>Excellent:</strong> Average query time is under 10ms - system is well optimized.</li>";
    } elseif ($avgTime < 50) {
        echo "<li class='text-info'><strong>Good:</strong> Average query time is acceptable. Monitor for growth.</li>";
    } else {
        echo "<li class='text-danger'><strong>Action Required:</strong> Average query time is high. Implement caching and optimize queries.</li>";
    }
    
    echo "<li><strong>Next Steps:</strong>
        <ol>
            <li>Run <code>db/optimize_performance.sql</code> to add indexes</li>
            <li>Enable query caching in MySQL configuration</li>
            <li>Implement application-level caching (Redis/Memcached)</li>
            <li>Review slow queries and optimize</li>
            <li>Re-run this test after optimizations</li>
        </ol>
    </li>";
    
    echo "</ul>";
    echo "</div></div>";
    
    ?>
</div>
</body>
</html>
