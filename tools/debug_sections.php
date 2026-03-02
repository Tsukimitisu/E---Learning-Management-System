<?php
require_once __DIR__ . '/../config/init.php';

echo "=== sections table ===\n";
$r = $conn->query("DESCRIBE sections");
if ($r) { while ($row = $r->fetch_assoc()) echo "  {$row['Field']} | {$row['Type']} | {$row['Key']}\n"; }
else echo "  ERROR: " . $conn->error . "\n";

echo "\n=== section_students table ===\n";
$r = $conn->query("DESCRIBE section_students");
if ($r) { while ($row = $r->fetch_assoc()) echo "  {$row['Field']} | {$row['Type']} | {$row['Key']}\n"; }
else echo "  ERROR: " . $conn->error . "\n";

echo "\n=== program_year_levels table ===\n";
$r = $conn->query("SHOW TABLES LIKE 'program_year_levels'");
echo "  Exists: " . ($r && $r->num_rows > 0 ? 'yes' : 'no') . "\n";
if ($r && $r->num_rows > 0) {
    $r = $conn->query("SELECT * FROM program_year_levels ORDER BY id");
    if ($r) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
}

echo "\n=== shs_grade_levels table ===\n";
$r = $conn->query("SHOW TABLES LIKE 'shs_grade_levels'");
echo "  Exists: " . ($r && $r->num_rows > 0 ? 'yes' : 'no') . "\n";
if ($r && $r->num_rows > 0) {
    $r = $conn->query("SELECT * FROM shs_grade_levels ORDER BY id");
    if ($r) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
}

echo "\n=== curriculum_subjects (SHS, first 10) ===\n";
$r = $conn->query("SELECT id, subject_code, subject_title, shs_strand_id, shs_grade_level_id, semester, is_active, subject_type FROM curriculum_subjects WHERE shs_strand_id IS NOT NULL LIMIT 10");
if ($r && $r->num_rows > 0) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
else echo "  None found or error: " . $conn->error . "\n";

echo "\n=== sections (SHS) ===\n";
$r = $conn->query("SELECT s.id, s.section_name, s.shs_strand_id, s.shs_grade_level_id, s.semester, s.academic_year_id, s.branch_id, ss.strand_code FROM sections s LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id WHERE s.shs_strand_id IS NOT NULL");
if ($r && $r->num_rows > 0) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
else echo "  None found\n";

echo "\n=== Student info (SHS students) ===\n";
$r = $conn->query("
    SELECT s.user_id, s.student_no, s.course_id, ss.strand_code, ss.strand_name,
           up.first_name, up.last_name, up.branch_id
    FROM students s
    LEFT JOIN shs_strands ss ON s.course_id = ss.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    WHERE EXISTS (SELECT 1 FROM shs_strands WHERE id = s.course_id)
    LIMIT 5
");
if ($r && $r->num_rows > 0) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
else echo "  None found\n";

echo "\n=== student_subject_enrollments (SHS students) ===\n";
$r = $conn->query("
    SELECT sse.student_id, sse.subject_id, sse.section_id, sse.academic_year_id, sse.status, cs.subject_code, cs.shs_strand_id
    FROM student_subject_enrollments sse
    LEFT JOIN curriculum_subjects cs ON sse.subject_id = cs.id
    WHERE cs.shs_strand_id IS NOT NULL
    LIMIT 10
");
if ($r) {
    if ($r->num_rows > 0) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
    else echo "  None found\n";
} else echo "  ERROR: " . $conn->error . "\n";

echo "\n=== student_term_enrollments (SHS students) ===\n";
$r = $conn->query("
    SELECT ste.student_id, ste.program_type, ste.program_id, ste.year_level_id, ste.semester, ste.enrollment_status
    FROM student_term_enrollments ste
    WHERE ste.program_type = 'shs'
    LIMIT 10
");
if ($r) {
    if ($r->num_rows > 0) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
    else echo "  None found\n";
} else echo "  ERROR: " . $conn->error . "\n";

echo "\n=== enrollments table (SHS) ===\n";
$r = $conn->query("SHOW TABLES LIKE 'enrollments'");
echo "  Exists: " . ($r && $r->num_rows > 0 ? 'yes' : 'no') . "\n";
if ($r && $r->num_rows > 0) {
    $r = $conn->query("DESCRIBE enrollments");
    while ($row = $r->fetch_assoc()) echo "  {$row['Field']} | {$row['Type']}\n";
}

echo "\n=== branches table ===\n";
$r = $conn->query("SELECT id, branch_name FROM branches LIMIT 5");
if ($r) { while ($row = $r->fetch_assoc()) echo "  id={$row['id']} name={$row['branch_name']}\n"; }
else echo "  ERROR: " . $conn->error . "\n";

echo "\n=== user_profiles branch_id sample ===\n";
$r = $conn->query("SELECT up.user_id, up.first_name, up.last_name, up.branch_id FROM user_profiles up INNER JOIN user_roles ur ON up.user_id = ur.user_id WHERE ur.role_id = " . ROLE_STUDENT . " LIMIT 5");
if ($r) { while ($row = $r->fetch_assoc()) echo "  " . json_encode($row) . "\n"; }
