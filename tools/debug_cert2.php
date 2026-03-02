<?php
require_once __DIR__ . '/../config/init.php';

// Check programs
$r = $conn->query("SELECT COUNT(*) as c FROM programs");
echo "programs count: " . $r->fetch_assoc()['c'] . "\n";

$r = $conn->query("SELECT * FROM programs LIMIT 5");
while ($row = $r->fetch_assoc()) echo json_encode($row) . "\n";

// Check year_levels
$r = $conn->query("SHOW TABLES LIKE 'year_levels'");
echo "\nyear_levels exists: " . ($r && $r->num_rows > 0 ? 'yes' : 'no') . "\n";

// Check what the enrollment query would return
$r = $conn->query("
    SELECT ste.*, ay.year_name
    FROM student_term_enrollments ste
    LEFT JOIN academic_years ay ON ste.academic_year_id = ay.id
    WHERE ste.student_id = 235
    ORDER BY ste.created_at DESC LIMIT 1
");
if ($r === false) {
    echo "enrollment query error: " . $conn->error . "\n";
} else {
    echo "\nenrollment for student 235: ";
    $row = $r->fetch_assoc();
    echo $row ? json_encode($row) : "none";
    echo "\n";
}

// Test the full certificate query - simulate what generate_certificate.php does
$test_id = 235;
echo "\n--- Simulating certificate generation for student $test_id ---\n";

$student = $conn->query("
    SELECT s.student_no, s.course_id, s.lrn,
           CONCAT(up.first_name, ' ', up.last_name) as full_name,
           p.program_code, p.program_name,
           ss.strand_code, ss.strand_name,
           CASE 
               WHEN p.id IS NOT NULL THEN 'college'
               WHEN ss.id IS NOT NULL THEN 'shs'
               ELSE 'unknown'
           END as program_type
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    WHERE s.user_id = $test_id
");
if ($student === false) {
    echo "Student query error: " . $conn->error . "\n";
} else {
    $row = $student->fetch_assoc();
    echo "Student: " . ($row ? json_encode($row) : "NOT FOUND") . "\n";
}

// Test enrollment with year_levels join (this should fail)
$enr = $conn->query("
    SELECT ste.*, ay.year_name, yl.level_name as year_level_name
    FROM student_term_enrollments ste
    LEFT JOIN academic_years ay ON ste.academic_year_id = ay.id
    LEFT JOIN year_levels yl ON ste.year_level_id = yl.id
    WHERE ste.student_id = $test_id AND ste.enrollment_status = 'approved'
    ORDER BY ste.created_at DESC LIMIT 1
");
if ($enr === false) {
    echo "Enrollment query ERROR: " . $conn->error . "\n";
} else {
    $row = $enr->fetch_assoc();
    echo "Enrollment: " . ($row ? json_encode($row) : "NOT FOUND") . "\n";
}

// Check classes table
$r = $conn->query("SHOW TABLES LIKE 'classes'");
echo "\nclasses table exists: " . ($r && $r->num_rows > 0 ? 'yes' : 'no') . "\n";

// Check if classes has curriculum_subject_id or subject_id
$r = $conn->query("DESCRIBE classes");
if ($r) {
    echo "classes columns:\n";
    while ($row = $r->fetch_assoc()) echo "  " . $row['Field'] . " | " . $row['Type'] . "\n";
}
