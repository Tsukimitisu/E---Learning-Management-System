<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$class_id = (int)($_GET['class_id'] ?? 0);
if ($class_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid class ID']);
    exit();
}

$query = "
    SELECT 
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        g.midterm, g.final, g.final_grade, g.remarks,
        COUNT(DISTINCT a.id) as total_attendance,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
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
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

echo json_encode(['status' => 'success', 'records' => $records]);
