<?php
/**
 * Migration: Create year_levels table
 * Required by student_term_enrollments.year_level_id
 */
require_once __DIR__ . '/../config/init.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS year_levels (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        level_name VARCHAR(50) NOT NULL,
        level_order INT UNSIGNED NOT NULL DEFAULT 0,
        program_type ENUM('college','shs','both') NOT NULL DEFAULT 'both',
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO year_levels (id, level_name, level_order, program_type) VALUES
        (1, '1st Year', 1, 'college'),
        (2, '2nd Year', 2, 'college'),
        (3, '3rd Year', 3, 'college'),
        (4, '4th Year', 4, 'college'),
        (5, 'Grade 11', 5, 'shs'),
        (6, 'Grade 12', 6, 'shs')"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
}

echo "\nVerify:\n";
$r = $conn->query("SELECT * FROM year_levels ORDER BY id");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['id']} name={$row['level_name']} type={$row['program_type']}\n";
}
echo "Done.\n";
