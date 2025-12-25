<?php
/**
 * PC 1: Complete Database Backup Export
 * Run this on PC 1 to export ALL data including structure
 * 
 * Usage: 
 * 1. Save this file as: backup_complete.php
 * 2. Access via browser: http://localhost/eems/backup_complete.php
 * 3. Download the SQL file
 * 4. Transfer to PC 2 and import
 */

require_once __DIR__ . '/config/db.php';

// Function to export database
function exportDatabase($pdo, $dbName) {
    $output = "-- EEMS Complete Database Backup\n";
    $output .= "-- Exported: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database: $dbName\n";
    $output .= "\n";
    
    // Drop and create database
    $output .= "DROP DATABASE IF EXISTS `$dbName`;\n";
    $output .= "CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $output .= "USE `$dbName`;\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Get table structure
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= $row['Create Table'] . ";\n\n";
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            
            $output .= "INSERT INTO `$table` ($columnList) VALUES\n";
            
            $valuesList = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $col) {
                    $val = $row[$col];
                    if ($val === null) {
                        $values[] = 'NULL';
                    } else {
                        // Properly escape the value
                        $values[] = "'" . addslashes($val) . "'";
                    }
                }
                $valuesList[] = "(" . implode(", ", $values) . ")";
            }
            
            $output .= implode(",\n", $valuesList) . ";\n\n";
        }
    }
    
    return $output;
}

// Check if we should download or just display
$action = $_GET['action'] ?? 'download';

try {
    $dbName = 'eems';
    $sqlContent = exportDatabase($pdo, $dbName);
    
    if ($action === 'download') {
        // Send as download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="eems_complete_' . date('Y-m-d_H-i-s') . '.sql"');
        header('Content-Length: ' . strlen($sqlContent));
        echo $sqlContent;
        exit;
    } else {
        // Display in page
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>EEMS Database Export</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .container { max-width: 1200px; margin: 0 auto; }
                textarea { width: 100%; height: 400px; font-family: monospace; }
                .button { padding: 10px 20px; background: #667eea; color: white; border: none; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>EEMS Database Export</h1>
                <p>Complete database backup ready for transfer to PC 2</p>
                
                <textarea id="sqlContent" readonly><?php echo htmlspecialchars($sqlContent); ?></textarea>
                
                <p>
                    <button class="button" onclick="downloadFile()">Download SQL File</button>
                    <button class="button" onclick="copyToClipboard()">Copy to Clipboard</button>
                </p>
                
                <p>
                    <strong>Instructions:</strong><br>
                    1. Click "Download SQL File"<br>
                    2. Transfer the file to PC 2<br>
                    3. Run the import script on PC 2<br>
                </p>
            </div>
            
            <script>
                function downloadFile() {
                    const element = document.createElement('a');
                    element.setAttribute('href', '?action=download');
                    element.setAttribute('download', 'eems_complete_<?php echo date("Y-m-d_H-i-s"); ?>.sql');
                    element.style.display = 'none';
                    document.body.appendChild(element);
                    element.click();
                    document.body.removeChild(element);
                }
                
                function copyToClipboard() {
                    const textarea = document.getElementById('sqlContent');
                    textarea.select();
                    document.execCommand('copy');
                    alert('SQL content copied to clipboard!');
                }
            </script>
        </body>
        </html>
        <?php
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
