<?php
require_once __DIR__ . '/config/db.php';

try {
    // --- Fetch Recent Requests ---
   /* $stmt = $pdo->query("SELECT * FROM requests ORDER BY created_at DESC LIMIT 5");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Construct HTML Output ---
    if ($requests) {
        echo '<div class="list-group">';
        foreach ($requests as $request) {
            $statusClass = '';
            if ($request['status'] === 'pending') {
                $statusClass = 'bg-warning';
            } elseif ($request['status'] === 'approved') {
                $statusClass = 'bg-success';
            } elseif ($request['status'] === 'declined') {
                $statusClass = 'bg-danger';
            }

            echo '<div class="list-group-item border-0">';
            echo '  <strong>' . htmlspecialchars($request['requestor_name']) . '</strong> requested for <em>' . htmlspecialchars($request['description']) . '</em>';
            echo '  <span class="badge ' . $statusClass . ' float-end">' . htmlspecialchars($request['status']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-muted">No recent requests found.</p>';
    }*/

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>