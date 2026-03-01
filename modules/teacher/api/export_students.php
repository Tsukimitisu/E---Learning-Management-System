<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../../index.php');
    exit();
}

// Compatibility guard for student type label support.
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular' AFTER course_id");
$conn->query("ALTER TABLE students MODIFY COLUMN student_type ENUM('regular','irregular','transferee') NOT NULL DEFAULT 'regular'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) DEFAULT NULL AFTER student_type");

$teacher_id = $_SESSION['user_id'];
$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

if (!$section_id) {
    die('Invalid section');
}

// Get section info
$section_query = "
    SELECT s.*, 
        COALESCE(p.program_code, st.strand_code) as program_code,
        COALESCE(p.program_name, st.strand_name) as program_name
    FROM sections s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands st ON s.shs_strand_id = st.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($section_query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

if (!$section) {
    die('Section not found');
}

// Get subject info if provided
$subject_info = null;
if ($subject_id) {
    $subj_query = "SELECT subject_code, subject_title FROM curriculum_subjects WHERE id = ?";
    $stmt = $conn->prepare($subj_query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $subject_info = $stmt->get_result()->fetch_assoc();
}

// Get enrolled students
// Get current AY for subject-level roster filtering
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = (int)($current_ay['id'] ?? 0);

$has_subject_enrollment_table = false;
$check_table = $conn->query("SHOW TABLES LIKE 'student_subject_enrollments'");
if ($check_table && $check_table->num_rows > 0) {
    $has_subject_enrollment_table = true;
}

if ($subject_id > 0 && $has_subject_enrollment_table) {
    $students_query = "
        SELECT 
            u.id,
            u.email,
            up.first_name,
            up.last_name,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            CASE
                WHEN COALESCE(st.student_type, 'regular') = 'regular' THEN 'regular'
                WHEN st.student_type = 'transferee' THEN 'transferee'
                ELSE 'irregular'
            END as student_type,
            sse.status as enrollment_status
        FROM student_subject_enrollments sse
        INNER JOIN users u ON sse.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        WHERE sse.section_id = ?
          AND sse.subject_id = ?
          AND sse.academic_year_id = ?
          AND sse.status IN ('enrolled','credited')
        ORDER BY up.last_name, up.first_name
    ";
    $stmt = $conn->prepare($students_query);
    $stmt->bind_param("iii", $section_id, $subject_id, $current_ay_id);
} else {
    $students_query = "
        SELECT 
            u.id,
            u.email,
            up.first_name,
            up.last_name,
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            CASE
                WHEN COALESCE(st.student_type, 'regular') = 'regular' THEN 'regular'
                WHEN st.student_type = 'transferee' THEN 'transferee'
                ELSE 'irregular'
            END as student_type,
            ss.status
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        WHERE ss.section_id = ? AND ss.status = 'active'
        ORDER BY up.last_name, up.first_name
    ";
    $stmt = $conn->prepare($students_query);
    $stmt->bind_param("i", $section_id);
}
$stmt->execute();
$students = $stmt->get_result();

// Set headers for CSV download
$filename_prefix = $subject_info ? $subject_info['subject_code'] . '_' : '';
$filename = $filename_prefix . $section['section_name'] . '_students_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Header
fputcsv($output, [
    'No.',
    'Student No.',
    'Last Name',
    'First Name',
    'Email',
    'Student Type',
    'Status'
]);

// CSV Data
$counter = 1;
while ($student = $students->fetch_assoc()) {
    fputcsv($output, [
        $counter++,
        $student['student_no'] ?? 'N/A',
        $student['last_name'],
        $student['first_name'],
        $student['email'],
        ($student['enrollment_status'] ?? '') === 'credited' ? 'Credited (Transferee)' : ucfirst((string)($student['student_type'] ?? 'regular')),
        ($student['enrollment_status'] ?? '') === 'credited' ? 'Credited' : ucfirst($student['status'] ?? $student['enrollment_status'] ?? 'enrolled')
    ]);
}

fclose($output);
exit();
