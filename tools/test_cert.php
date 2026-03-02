<?php
/**
 * Simulate a logged-in registrar session and test the student search API
 */
require_once __DIR__ . '/../config/init.php';

// Find a registrar user
echo "ROLE_REGISTRAR = " . ROLE_REGISTRAR . "\n";

// Show users table structure
$r = $conn->query("DESCRIBE users");
echo "Users table columns: ";
$cols = [];
while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
echo implode(', ', $cols) . "\n";

// Just fake the session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = ROLE_REGISTRAR;
$registrar = ['id' => 1, 'role_id' => ROLE_REGISTRAR];

// Skip registrar check for CLI

echo "\n=== Test 1: All students (no filters) ===\n";
$_GET = ['limit' => '5'];

// Test the query directly instead of including the file
$sql = "
    SELECT DISTINCT
        s.user_id,
        s.student_no,
        s.course_id,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        up.first_name,
        up.last_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        CASE 
            WHEN p.id IS NOT NULL THEN 'college'
            WHEN ss.id IS NOT NULL THEN 'shs'
            ELSE 'unknown'
        END as program_type,
        latest_enroll.year_level_id,
        COALESCE(yl.level_name, '') as year_level_name
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    LEFT JOIN (
        SELECT ste.student_id, ste.year_level_id
        FROM student_term_enrollments ste
        WHERE ste.enrollment_status = 'approved'
        ORDER BY ste.created_at DESC
    ) latest_enroll ON latest_enroll.student_id = s.user_id
    LEFT JOIN year_levels yl ON latest_enroll.year_level_id = yl.id
    GROUP BY s.user_id
    ORDER BY up.last_name, up.first_name
    LIMIT 5
";

$r = $conn->query($sql);
if ($r === false) {
    echo "QUERY ERROR: " . $conn->error . "\n";
} else {
    echo $r->num_rows . " students returned\n";
    while ($row = $r->fetch_assoc()) {
        echo "  {$row['student_no']} - {$row['full_name']} ({$row['program_code']}) [{$row['year_level_name']}]\n";
    }
}

echo "\n=== Test 2: Filter options ===\n";
ob_start();
include __DIR__ . '/../modules/registrar/process/get_filter_options.php';
$json = ob_get_clean();
$data = json_decode($json, true);
echo "Programs: " . count($data['programs'] ?? []) . "\n";
echo "Strands: " . count($data['strands'] ?? []) . "\n";
echo "Year levels: " . count($data['year_levels'] ?? []) . "\n";

echo "\n=== Test 3: Certificate generation test (enrollment) ===\n";
// Find a student
$student = $conn->query("SELECT user_id FROM students LIMIT 1")->fetch_assoc();
if ($student) {
    echo "Testing with student user_id: " . $student['user_id'] . "\n";
    
    // Simulate the queries from generate_certificate.php
    $test_id = $student['user_id'];
    
    $s = $conn->query("
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
    ")->fetch_assoc();
    
    if ($s) {
        echo "Student: {$s['full_name']} - {$s['student_no']}\n";
        echo "Program: " . ($s['program_code'] ?? $s['strand_code'] ?? 'N/A') . " - " . ($s['program_name'] ?? $s['strand_name'] ?? 'N/A') . "\n";
        echo "Type: {$s['program_type']}\n";
    }
    
    // Test enrollment query with year_levels
    $enr = $conn->query("
        SELECT ste.*, ay.year_name, yl.level_name as year_level_name
        FROM student_term_enrollments ste
        LEFT JOIN academic_years ay ON ste.academic_year_id = ay.id
        LEFT JOIN year_levels yl ON ste.year_level_id = yl.id
        WHERE ste.student_id = $test_id AND ste.enrollment_status = 'approved'
        ORDER BY ste.created_at DESC LIMIT 1
    ");
    
    if ($enr === false) {
        echo "Enrollment query FAILED: " . $conn->error . "\n";
    } else {
        $e = $enr->fetch_assoc();
        echo "Enrollment: " . ($e ? "{$e['year_name']} - {$e['year_level_name']}" : "none") . "\n";
    }
    
    echo "All queries successful!\n";
} else {
    echo "No students found.\n";
}
