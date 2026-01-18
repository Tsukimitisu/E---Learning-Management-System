<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    die('Unauthorized');
}

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0 || empty($date_from) || empty($date_to)) {
    die('Invalid parameters');
}

// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    die('Unauthorized');
}

// Get section info
$section_query = $conn->prepare("SELECT section_name FROM sections WHERE id = ?");
$section_query->bind_param("i", $section_id);
$section_query->execute();
$section_info = $section_query->get_result()->fetch_assoc();

// Get subject info
$subject_query = $conn->prepare("SELECT subject_code, subject_title FROM curriculum_subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_info = $subject_query->get_result()->fetch_assoc();

// Get attendance summary from section_students
$attendance = $conn->prepare("
    SELECT 
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        COUNT(a.id) as total_days
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN attendance a ON u.id = a.student_id 
        AND a.section_id = ? 
        AND a.subject_id = ?
        AND a.attendance_date BETWEEN ? AND ?
    WHERE ss.section_id = ? AND ss.status = 'active'
    GROUP BY u.id
    ORDER BY up.last_name, up.first_name
");
$attendance->bind_param("iissi", $section_id, $subject_id, $date_from, $date_to, $section_id);
$attendance->execute();
$attendance_result = $attendance->get_result();

// Export as Excel (CSV with Excel compatibility)
$filename = 'attendance_' . ($subject_info['subject_code'] ?? 'subject') . '_' . ($section_info['section_name'] ?? 'section') . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add header info
fputcsv($output, ['Attendance Report']);
fputcsv($output, ['Subject:', $subject_info['subject_code'] . ' - ' . $subject_info['subject_title']]);
fputcsv($output, ['Section:', $section_info['section_name']]);
fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
fputcsv($output, ['Generated:', date('F d, Y h:i A')]);
fputcsv($output, []);

fputcsv($output, [
    'Student No',
    'Student Name',
    'Present',
    'Absent',
    'Late',
    'Total Days',
    'Attendance %'
]);

while ($row = $attendance_result->fetch_assoc()) {
    $attendance_percent = $row['total_days'] > 0 
        ? round(($row['present_count'] / $row['total_days']) * 100, 2) 
        : 0;
    
    fputcsv($output, [
        $row['student_no'],
        $row['student_name'],
        $row['present_count'],
        $row['absent_count'],
        $row['late_count'],
        $row['total_days'],
        $attendance_percent . '%'
    ]);
}

fclose($output);
exit();
?>