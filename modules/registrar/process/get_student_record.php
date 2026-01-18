<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$student_id = (int)($_GET['student_id'] ?? 0);
if ($student_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
    exit();
}

try {
    $student = $conn->query("
        SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name,
               u.email, c.course_code, c.title as course_title,
               AVG(g.final_grade) as gpa
        FROM students s
        INNER JOIN user_profiles up ON s.user_id = up.user_id
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN grades g ON s.user_id = g.student_id
        WHERE s.user_id = $student_id
        GROUP BY s.user_id
    ")->fetch_assoc();

    if (!$student) {
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit();
    }

    $standing = get_academic_standing($student['gpa'] ?? 0);

    $enrollment_history = [];
    $enrollments = $conn->query("
        SELECT ay.year_name as academic_year, cs.semester, COUNT(DISTINCT e.class_id) as class_count,
               'Enrolled' as status
        FROM enrollments e
        INNER JOIN classes cl ON e.class_id = cl.id
        LEFT JOIN academic_years ay ON cl.academic_year_id = ay.id
        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        WHERE e.student_id = $student_id AND e.status = 'approved'
        GROUP BY ay.year_name, cs.semester
        ORDER BY ay.year_name DESC, cs.semester ASC
    ");
    while ($row = $enrollments->fetch_assoc()) {
        $enrollment_history[] = $row;
    }

    $grades = [];
    $grades_result = $conn->query("
        SELECT cs.subject_code, cs.subject_title, cs.units,
               g.midterm, g.final, g.final_grade, g.remarks
        FROM grades g
        INNER JOIN classes cl ON g.class_id = cl.id
        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        WHERE g.student_id = $student_id
        ORDER BY cs.subject_code
    ");
    while ($row = $grades_result->fetch_assoc()) {
        $grades[] = $row;
    }

    $attendance_summary = $conn->query("
        SELECT COUNT(*) as total_days,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
        FROM attendance
        WHERE student_id = $student_id
    ")->fetch_assoc();

    $total_days = (int)($attendance_summary['total_days'] ?? 0);
    $present = (int)($attendance_summary['present_count'] ?? 0);
    $percentage = $total_days > 0 ? round(($present / $total_days) * 100, 2) : 0;

    $payment_summary = $conn->query("
        SELECT SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_paid,
               COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified_payments
        FROM payments
        WHERE student_id = $student_id
    ")->fetch_assoc();

    $total_paid = $payment_summary['total_paid'] ?? 0;
    $clearance_status = $total_paid > 0 ? 'CLEARED' : 'NOT CLEARED';

    echo json_encode([
        'status' => 'success',
        'student' => [
            'student_no' => $student['student_no'],
            'full_name' => $student['full_name'],
            'email' => $student['email'],
            'program' => ($student['course_code'] ?? 'N/A') . ' - ' . ($student['course_title'] ?? 'N/A'),
            'year_level' => 'N/A',
            'gpa' => $student['gpa'] ? round($student['gpa'], 2) : 0,
            'academic_standing' => $standing
        ],
        'enrollment_history' => $enrollment_history,
        'grades' => $grades,
        'attendance_summary' => [
            'total_days' => $total_days,
            'present' => $present,
            'absent' => $total_days - $present,
            'percentage' => $percentage
        ],
        'payment_summary' => [
            'total_paid' => $total_paid,
            'verified_payments' => $payment_summary['verified_payments'] ?? 0,
            'clearance_status' => $clearance_status
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>