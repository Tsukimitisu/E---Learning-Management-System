<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    die('Unauthorized');
}

$class_id = (int)($_GET['class_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0 || empty($date_from) || empty($date_to)) {
    die('Invalid parameters');
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

// Get attendance summary
$attendance = $conn->query("
    SELECT 
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        COUNT(a.id) as total_days
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN attendance a ON s.user_id = a.student_id 
        AND a.class_id = $class_id 
        AND a.attendance_date BETWEEN '$date_from' AND '$date_to'
    WHERE e.class_id = $class_id AND e.status = 'approved'
    GROUP BY s.user_id
    ORDER BY up.last_name, up.first_name
");

// Export as CSV
$filename = 'attendance_' . ($class_info['subject_code'] ?? 'class') . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Student No',
    'Student Name',
    'Present',
    'Absent',
    'Late',
    'Total Days',
    'Attendance %'
]);

while ($row = $attendance->fetch_assoc()) {
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