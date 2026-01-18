<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../../index.php');
    exit();
}

$type = clean_input($_GET['type'] ?? 'program');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w');

if ($type === 'program') {
    fputcsv($output, ['Program', 'Enrolled']);
    $result = $conn->query("SELECT c.course_code, COUNT(e.id) as count
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.user_id
        INNER JOIN courses c ON s.course_id = c.id
        GROUP BY c.course_code
        ORDER BY c.course_code
    ");
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['course_code'], $row['count']]);
    }
} elseif ($type === 'class') {
    fputcsv($output, ['Student No', 'Name', 'Midterm', 'Final', 'Final Grade', 'Remarks', 'Attendance %', 'Payment']);
    $class_id = (int)($_GET['class_id'] ?? 0);
    if ($class_id > 0) {
        $stmt = $conn->prepare("SELECT 
            s.student_no,
            CONCAT(up.first_name, ' ', up.last_name) as full_name,
            g.midterm, g.final, g.final_grade, g.remarks,
            ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT a.id),0)) * 100, 2) as attendance_percentage,
            COALESCE(p.payment_status, 'No Payment') as payment_status
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.user_id
        INNER JOIN user_profiles up ON s.user_id = up.user_id
        LEFT JOIN grades g ON s.user_id = g.student_id AND g.class_id = e.class_id
        LEFT JOIN attendance a ON s.user_id = a.student_id AND a.class_id = e.class_id
        LEFT JOIN (
            SELECT student_id, 
                   CASE WHEN SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) > 0 
                        THEN 'Verified' ELSE 'Pending' END as payment_status
            FROM payments GROUP BY student_id
        ) p ON s.user_id = p.student_id
        WHERE e.class_id = ? AND e.status = 'approved'
        GROUP BY s.user_id
        ORDER BY up.last_name, up.first_name
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['student_no'],
                $row['full_name'],
                $row['midterm'],
                $row['final'],
                $row['final_grade'],
                $row['remarks'],
                $row['attendance_percentage'],
                $row['payment_status']
            ]);
        }
    }
}

fclose($output);
exit();
