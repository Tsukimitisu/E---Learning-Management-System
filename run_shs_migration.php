<?php
/**
 * Run SHS Academic Features Migration
 * Adds: LRN field, SHS quarter-based grading, SHS enrollment enhancements
 */

require_once __DIR__ . '/config/db.php';

echo "<h2>Running SHS Academic Features Migration</h2><pre>";

$sql = file_get_contents(__DIR__ . '/migrations/shs_academic_features.sql');
if (!$sql) {
    die("ERROR: Could not read migration file.\n");
}

// Remove SQL comments
$sql = preg_replace('/--.*$/m', '', $sql);

// Split on semicolons
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($statements as $stmt) {
    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
    
    echo "\n> " . substr($stmt, 0, 80) . "...\n";
    
    if ($conn->query($stmt)) {
        if ($conn->affected_rows >= 0) {
            echo "  ✓ OK\n";
            $success++;
        }
    } else {
        $err = $conn->error;
        // Skip duplicate column/index errors
        if (strpos($err, 'Duplicate column') !== false || strpos($err, 'Duplicate key name') !== false || strpos($err, 'already exists') !== false) {
            echo "  ○ Skipped (already exists)\n";
            $skipped++;
        } else {
            echo "  ✗ ERROR: $err\n";
            $errors++;
        }
    }
}

echo "\n\n========================================\n";
echo "Migration Complete!\n";
echo "Success: $success | Skipped: $skipped | Errors: $errors\n";
echo "========================================\n";
echo "</pre>";
