<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0) {
    die('Invalid class ID');
}

// Verify class
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    die('Unauthorized');
}

// Get class info
$class_info = $conn->query("
    SELECT cl.*, s.subject_code, s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.id = $class_id
")->fetch_assoc();

// Get students with grades
$students = $conn->query("
    SELECT 
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        g.midterm,
        g.final,
        g.final_grade,
        g.remarks
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN grades g ON s.user_id = g.student_id AND g.class_id = $class_id
    WHERE e.class_id = $class_id AND e.status = 'approved'
    ORDER BY up.last_name, up.first_name
");

// Set headers for Excel download
$filename = 'grades_' . ($class_info['subject_code'] ?? 'class') . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'Student No',
    'Student Name',
    'Midterm',
    'Final',
    'Final Grade',
    'Remarks'
]);

// Write data
while ($student = $students->fetch_assoc()) {
    fputcsv($output, [
        $student['student_no'],
        $student['student_name'],
        $student['midterm'] ?? '',
        $student['final'] ?? '',
        $student['final_grade'] ?? '',
        $student['remarks'] ?? ''
    ]);
}

fclose($output);
exit();
?>