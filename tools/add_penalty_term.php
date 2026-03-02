<?php
require_once __DIR__.'/../config/db.php';

$sql = "ALTER TABLE tuition_penalties ADD COLUMN applicable_term ENUM('all','prelim','midterm','prefinals','finals') NOT NULL DEFAULT 'all' COMMENT 'Which payment term this penalty applies to' AFTER start_date";

if ($conn->query($sql)) {
    echo "OK - applicable_term column added\n";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "Column already exists\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
