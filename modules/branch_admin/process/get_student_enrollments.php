<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$student_id = (int)($_GET['student_id'] ?? 0);

if (empty($student_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID required']);
    exit();
}

try {
    $query = "
        SELECT
            e.id,
            e.status,
            e.created_at,
            cl.section_name,
            s.subject_code,
            s.subject_title,
            CONCAT(up.first_name, ' ', up.last_name) as teacher_name
        FROM enrollments e
        INNER JOIN classes cl ON e.class_id = cl.id
        LEFT JOIN subjects s ON cl.subject_id = s.id
        LEFT JOIN users u ON cl.teacher_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE e.student_id = ?
        ORDER BY s.subject_code, cl.section_name
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $enrollments = [];
    while ($row = $result->fetch_assoc()) {
        $enrollments[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'enrollments' => $enrollments
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>