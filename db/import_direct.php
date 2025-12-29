<?php
/**
 * Direct Database Import Script
 * Imports the SQL file directly to database
 */

require_once __DIR__ . '/../config/db.php';

$sqlFile = __DIR__ . '/latest_db.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at " . $sqlFile);
}

echo "Starting database import...\n";
echo "File: " . $sqlFile . "\n";
echo "Size: " . filesize($sqlFile) . " bytes\n\n";

try {
    // Read the SQL file
    $sql = file_get_contents($sqlFile);
    
    if (!$sql) {
        die("Error: Could not read SQL file\n");
    }
    
    // Remove BOM if present
    $sql = str_replace("\xEF\xBB\xBF", '', $sql);
    
    // Split by semicolon but preserve them in statements
    $statements = array_filter(
        array_map(
            function($statement) {
                return trim($statement);
            },
            preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $sql)
        ),
        function($statement) {
            return !empty($statement) && !preg_match('/^(\/\*|--|#)/', $statement);
        }
    );
    
    echo "Statements to execute: " . count($statements) . "\n\n";
    
    $executed = 0;
    $errors = 0;
    $warnings = 0;
    
    foreach ($statements as $i => $statement) {
        $statement = trim($statement) . ';';
        
        // Skip comments
        if (preg_match('/^(\/\*|--|#|\/\/)/', $statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            if (($i + 1) % 50 === 0) {
                echo "Executed: " . ($i + 1) . " statements\n";
            }
        } catch (PDOException $e) {
            $errors++;
            // Log but continue
            error_log("SQL Error in statement " . ($i + 1) . ": " . $e->getMessage());
        }
    }
    
    echo "\n\nImport Summary:\n";
    echo "===============\n";
    echo "Executed: $executed statements\n";
    echo "Errors: $errors\n";
    
    // Verify
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema='eems'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $tableCount = $result['count'] ?? 0;
    
    echo "\nTables created: $tableCount\n";
    
    if ($tableCount > 0) {
        echo "\n✓ Database import SUCCESSFUL!\n";
        echo "\nYou can now:\n";
        echo "  1. Visit http://localhost/eems\n";
        echo "  2. Login with: arjun@gmail.com / 1234\n";
    } else {
        echo "\n✗ No tables created. Check the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    die(1);
}

?>
