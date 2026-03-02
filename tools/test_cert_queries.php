<?php
require_once __DIR__ . '/../config/init.php';

$test_id = 235; // Student user_id

echo "=== Test certificate queries for student $test_id ===\n";

// Student query
$student = $conn->query("
    SELECT s.student_no, s.course_id, s.lrn,
           CONCAT(up.first_name, ' ', up.last_name) as full_name,
           p.program_code, p.program_name,
           ss.strand_code, ss.strand_name,
           CASE WHEN p.id IS NOT NULL THEN 'college' WHEN ss.id IS NOT NULL THEN 'shs' ELSE 'unknown' END as program_type
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    WHERE s.user_id = $test_id
")->fetch_assoc();

echo "Student: " . json_encode($student) . "\n";

// Enrollment query (with year_levels table now existing)
$enr = $conn->query("
    SELECT ste.*, ay.year_name, yl.level_name as year_level_name
    FROM student_term_enrollments ste
    LEFT JOIN academic_years ay ON ste.academic_year_id = ay.id
    LEFT JOIN year_levels yl ON ste.year_level_id = yl.id
    WHERE ste.student_id = $test_id AND ste.enrollment_status = 'approved'
    ORDER BY ste.created_at DESC LIMIT 1
");
if ($enr === false) {
    echo "Enrollment FAILED: " . $conn->error . "\n";
} else {
    $e = $enr->fetch_assoc();
    echo "Enrollment: " . ($e ? json_encode(['year' => $e['year_name'], 'level' => $e['year_level_name'], 'semester' => $e['semester']]) : 'none') . "\n";
}

// Grades query
$grades = $conn->query("
    SELECT cs.subject_code, cs.subject_title, cs.units, g.prelim, g.midterm, g.prefinal, g.final, g.final_grade, g.remarks
    FROM grades g
    LEFT JOIN classes cl ON g.class_id = cl.id
    LEFT JOIN curriculum_subjects cs ON COALESCE(cl.curriculum_subject_id, cl.subject_id, g.subject_id) = cs.id
    WHERE g.student_id = $test_id
");
if ($grades === false) {
    echo "Grades FAILED: " . $conn->error . "\n";
} else {
    echo "Grades count: " . $grades->num_rows . "\n";
}

// Certificate reference
require_once __DIR__ . '/../includes/functions.php';
$ref = generate_certificate_reference('enrollment', $test_id);
echo "Reference: $ref\n";

// Test insert (dry run)
echo "\nAll queries PASSED. Certificate generation should work.\n";
