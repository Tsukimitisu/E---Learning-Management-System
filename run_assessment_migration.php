<?php
require_once __DIR__ . '/config/db.php';

// Check if columns already exist
$result = $conn->query("SHOW COLUMNS FROM assessment_scores LIKE 'submitted_file'");
if ($result && $result->num_rows > 0) {
    echo "Columns already exist - skipping migration.\n";
} else {
    $conn->query("ALTER TABLE assessment_scores 
        ADD COLUMN submitted_file VARCHAR(255) DEFAULT NULL AFTER feedback, 
        ADD COLUMN submitted_at DATETIME DEFAULT NULL AFTER submitted_file, 
        ADD COLUMN student_notes TEXT DEFAULT NULL AFTER submitted_at");
    if ($conn->error) {
        echo "Error: " . $conn->error . "\n";
    } else {
        echo "Migration successful - added submitted_file, submitted_at, student_notes columns.\n";
    }
}

// Create uploads/assessments directory
if (!is_dir(__DIR__ . '/uploads/assessments')) {
    mkdir(__DIR__ . '/uploads/assessments', 0755, true);
    echo "Created uploads/assessments directory.\n";
} else {
    echo "uploads/assessments directory already exists.\n";
}
