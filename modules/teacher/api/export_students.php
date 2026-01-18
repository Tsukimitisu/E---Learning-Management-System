<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../../index.php');
    exit();
}

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
    LEFT JOIN strands st ON s.strand_id = st.id
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
$students_query = "
    SELECT 
        u.id,
        u.email,
        up.first_name,
        up.last_name,
        up.student_id as student_no,
        ss.status
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    WHERE ss.section_id = ? AND ss.status = 'active'
    ORDER BY up.last_name, up.first_name
";

$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $section_id);
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
        ucfirst($student['status'])
    ]);
}

fclose($output);
exit();
