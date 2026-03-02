<?php
require_once __DIR__ . '/../config/init.php';

// Add program_type column
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS program_type ENUM('college','shs') DEFAULT NULL AFTER course_id");

// Auto-populate from student_term_enrollments
$conn->query("UPDATE students s INNER JOIN student_term_enrollments ste ON s.user_id = ste.student_id SET s.program_type = ste.program_type WHERE s.program_type IS NULL AND ste.program_type IS NOT NULL");

// Infer from course_id: if only in shs_strands, set shs
$conn->query("UPDATE students s SET s.program_type = 'shs' WHERE s.program_type IS NULL AND s.course_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM programs WHERE id = s.course_id) AND EXISTS (SELECT 1 FROM shs_strands WHERE id = s.course_id)");

// Infer from course_id: if only in programs, set college
$conn->query("UPDATE students s SET s.program_type = 'college' WHERE s.program_type IS NULL AND s.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM programs WHERE id = s.course_id) AND NOT EXISTS (SELECT 1 FROM shs_strands WHERE id = s.course_id)");

echo "=== All students with course_id ===" . PHP_EOL;
$r = $conn->query("SELECT user_id, course_id, program_type, student_type FROM students WHERE course_id IS NOT NULL ORDER BY user_id");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}

echo PHP_EOL . "=== Students with ambiguous course_id (program_type still NULL) ===" . PHP_EOL;
$r2 = $conn->query("SELECT s.user_id, s.course_id, s.program_type, p.program_code, ss.strand_code FROM students s LEFT JOIN programs p ON s.course_id = p.id LEFT JOIN shs_strands ss ON s.course_id = ss.id WHERE s.program_type IS NULL AND s.course_id IS NOT NULL");
$count = 0;
while ($row = $r2->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
    $count++;
}
if ($count === 0) echo "None" . PHP_EOL;
