<?php
require_once __DIR__ . '/../config/init.php';

echo "=== students table ===\n";
$r = $conn->query("DESCRIBE students");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Key'] . "\n";
}

echo "\n=== student_term_enrollments table ===\n";
$r = $conn->query("DESCRIBE student_term_enrollments");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Key'] . "\n";
}

echo "\n=== certificates_issued table ===\n";
$r = $conn->query("DESCRIBE certificates_issued");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Key'] . "\n";
}

echo "\n=== grades table ===\n";
$r = $conn->query("DESCRIBE grades");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Key'] . "\n";
}

echo "\n=== Sample students (first 5) ===\n";
$r = $conn->query("SELECT s.user_id, s.student_no, s.course_id FROM students s LIMIT 5");
while ($row = $r->fetch_assoc()) {
    echo "user_id={$row['user_id']} student_no={$row['student_no']} course_id={$row['course_id']}\n";
}

echo "\n=== Sample student_term_enrollments (first 5) ===\n";
$r = $conn->query("SELECT id, student_id, program_id, year_level_id, academic_year_id FROM student_term_enrollments LIMIT 5");
while ($row = $r->fetch_assoc()) {
    echo "id={$row['id']} student_id={$row['student_id']} program_id={$row['program_id']} year_level_id={$row['year_level_id']} ay={$row['academic_year_id']}\n";
}

echo "\n=== year_levels table ===\n";
$r = $conn->query("SELECT * FROM year_levels LIMIT 10");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No year_levels table or empty\n";
    // Check if it exists
    $r2 = $conn->query("SHOW TABLES LIKE 'year_levels'");
    echo "Table exists: " . ($r2 && $r2->num_rows > 0 ? 'yes' : 'no') . "\n";
}

echo "\n=== programs table ===\n";
$r = $conn->query("SELECT id, program_code, program_name, program_type FROM programs LIMIT 10");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n=== shs_strands table ===\n";
$r = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands LIMIT 10");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n=== Logo path check ===\n";
$logo = __DIR__ . '/../assets/image/datamexlogo.png';
echo "Path: $logo\n";
echo "Exists: " . (file_exists($logo) ? 'yes' : 'no') . "\n";

$tcpdf_logo = __DIR__ . '/../config/../assets/image/datamexlogo.png';
echo "TCPDF Path: $tcpdf_logo\n";
echo "TCPDF Exists: " . (file_exists($tcpdf_logo) ? 'yes' : 'no') . "\n";

// Check what PDF_HEADER_LOGO resolves to from within generate_certificate.php context
require_once __DIR__ . '/../config/tcpdf_config.php';
echo "PDF_HEADER_LOGO: " . PDF_HEADER_LOGO . "\n";
echo "PDF_HEADER_LOGO exists: " . (file_exists(PDF_HEADER_LOGO) ? 'yes' : 'no') . "\n";
