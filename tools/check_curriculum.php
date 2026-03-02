<?php
require_once __DIR__ . '/../config/init.php';

echo "=== Curriculum subjects by strand & grade level ===" . PHP_EOL;
$r = $conn->query("SELECT DISTINCT shs_strand_id, shs_grade_level_id, semester, COUNT(*) as cnt FROM curriculum_subjects WHERE shs_strand_id IS NOT NULL GROUP BY shs_strand_id, shs_grade_level_id, semester ORDER BY shs_strand_id, shs_grade_level_id, semester");
while ($row = $r->fetch_assoc()) echo json_encode($row) . PHP_EOL;

echo PHP_EOL . "=== shs_grade_levels (for reference) ===" . PHP_EOL;
$r2 = $conn->query("SELECT id, strand_id, grade_level, grade_name FROM shs_grade_levels ORDER BY id");
while ($row = $r2->fetch_assoc()) echo json_encode($row) . PHP_EOL;

echo PHP_EOL . "=== SHS Sections ===" . PHP_EOL;
$r3 = $conn->query("SELECT id, section_name, shs_strand_id, shs_grade_level_id, semester FROM sections WHERE shs_strand_id IS NOT NULL");
while ($row = $r3->fetch_assoc()) echo json_encode($row) . PHP_EOL;

echo PHP_EOL . "=== DESCRIBE curriculum_subjects (shs fields) ===" . PHP_EOL;
$r4 = $conn->query("SHOW COLUMNS FROM curriculum_subjects LIKE 'shs%'");
while ($row = $r4->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . PHP_EOL;
