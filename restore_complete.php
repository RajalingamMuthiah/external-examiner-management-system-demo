<?php
/**
 * PC 2: Complete Database Restore
 * Run this on PC 2 to import ALL data from PC 1
 * 
 * Usage:
 * 1. Save this file as: restore_complete.php
 * 2. Upload the SQL backup file from PC 1 (in form below)
 * 3. Click "Restore Database"
 */

require_once __DIR__ . '/config/db.php';

// Infer DB name from config/db.php
$dbName = isset($db) ? $db : 'eems';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = 'processing';

    // Ensure MySQL is reachable and database exists
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Exception $e) {
        $message = 'Error creating database: ' . $e->getMessage();
        $status = 'error';
    }

    // Check if file was uploaded
    if ($status !== 'error') {
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Please upload a valid .sql backup file exported using mysqldump.';
            $status = 'error';
        } else {
            try {
                // Persist upload to a known path
                $uploadsDir = __DIR__ . '/db/uploads';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0775, true);
                }
                $targetPath = $uploadsDir . '/' . (basename($_FILES['sql_file']['name']) ?: ('backup_' . date('Ymd_His') . '.sql'));
                if (!move_uploaded_file($_FILES['sql_file']['tmp_name'], $targetPath)) {
                    throw new RuntimeException('Failed to move uploaded file.');
                }

                // Prefer MySQL CLI import to handle complex dumps (triggers, procedures, delimiters)
                $mysqlPathCandidates = [
                    'C:\\xampp\\mysql\\bin\\mysql.exe',
                    'C:\\Program Files\\MySQL\\MySQL Server\\bin\\mysql.exe',
                    'mysql' // fall back to PATH
                ];
                $mysqlBin = null;
                foreach ($mysqlPathCandidates as $candidate) {
                    // file_exists works for absolute paths; for 'mysql' on PATH we just assign if nothing else found
                    if ($candidate === 'mysql') {
                        if ($mysqlBin === null) $mysqlBin = $candidate;
                        break;
                    }
                    if (file_exists($candidate)) { $mysqlBin = $candidate; break; }
                }

                if ($mysqlBin === null) {
                    throw new RuntimeException('MySQL client not found. Ensure XAMPP/MySQL is installed.');
                }

                // Build a safe command using "source" to avoid shell redirection quirks on Windows
                // Also set binary-mode to handle NULL bytes safely
                $cmd = '"' . $mysqlBin . '" --binary-mode=1 -u ' . escapeshellarg($user ?? 'root') . ' ' . escapeshellarg($dbName) . ' -e ' . escapeshellarg('source ' . $targetPath);

                // Execute and capture output
                $output = [];
                $exitCode = 0;
                @exec($cmd . ' 2>&1', $output, $exitCode);

                if ($exitCode !== 0) {
                    // Provide a concise error plus first lines of output for context
                    $preview = implode("\n", array_slice($output, 0, 5));
                    throw new RuntimeException('MySQL import failed. Exit code ' . $exitCode . ".\n" . $preview);
                }

                // Basic verification: count tables
                $countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = :db');
                $countStmt->execute([':db' => $dbName]);
                $tableCount = (int)($countStmt->fetchColumn() ?: 0);

                $message = '✓ Database restored successfully! Tables found: ' . $tableCount . '.';
                $status = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $status = 'error';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EEMS Database Restore - PC 2</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 600px; width: 100%; padding: 40px; }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .step { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .step-number { display: inline-block; background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; text-align: center; line-height: 30px; margin-right: 10px; font-weight: bold; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #333; font-weight: 500; margin-bottom: 8px; }
        input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #667eea; border-radius: 5px; cursor: pointer; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
        button:active { transform: translateY(0); }
        .message { padding: 15px; border-radius: 5px; margin-top: 20px; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .instructions { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-top: 30px; font-size: 13px; }
        .instructions h3 { color: #333; margin-bottom: 10px; }
        .instructions ol { margin-left: 20px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 EEMS Database Restore</h1>
        <p class="subtitle">Import complete database from PC 1 to PC 2</p>
        
        <!-- Status Message -->
        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Step 1 -->
            <div class="step">
                <span class="step-number">1</span>
                <strong>Get SQL File from PC 1</strong>
                <p style="margin-top: 5px; color: #666; font-size: 14px;">
                    On PC 1, export using mysqldump for best compatibility:<br>
                    <code style="background: #f0f0f0; padding: 2px 5px;">"C:\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction --routines --events --triggers --default-character-set=utf8mb4 eems &gt; C:\xampp\htdocs\eems\db\eems_full_backup.sql</code>
                </p>
            </div>
            
            <!-- Step 2 -->
            <div class="step">
                <span class="step-number">2</span>
                <strong>Upload SQL File</strong>
                <div class="form-group" style="margin-top: 10px;">
                    <input type="file" name="sql_file" accept=".sql" required>
                    <p style="margin-top: 5px; color: #666; font-size: 13px;">Select the .sql file from PC 1 (mysqldump output)</p>
                </div>
            </div>
            
            <!-- Step 3 -->
            <div class="step">
                <span class="step-number">3</span>
                <strong>Restore Database</strong>
                <button type="submit" style="margin-top: 10px;">Restore Database Now</button>
            </div>
        </form>
        
        <div class="instructions">
            <h3>📋 Quick Reference</h3>
            <ol>
                <li><strong>On PC 1:</strong> Visit <code>http://localhost/eems/backup_complete.php</code></li>
                <li><strong>Click:</strong> "Download SQL File"</li>
                <li><strong>Transfer:</strong> Move the .sql file to PC 2 (USB, email, cloud, etc.)</li>
                <li><strong>On PC 2:</strong> Upload the file here and click "Restore Database"</li>
                <li><strong>Done!</strong> All data will be imported automatically</li>
            </ol>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 13px; text-align: center;">
            <p>After restore, login with:<br><strong>Email:</strong> arjun@gmail.com | <strong>Password:</strong> 1234</p>
        </div>
    </div>
</body>
</html>
