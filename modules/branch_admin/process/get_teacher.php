<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Teacher ID required']);
    exit();
}

$teacher_id = (int)$_GET['id'];

try {
    $query = "
        SELECT
            u.id,
            u.email,
            u.status,
            up.first_name,
            up.last_name,
            up.address
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = ? AND ur.role_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $teacher_id, ROLE_TEACHER);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Teacher not found']);
        exit();
    }

    $teacher = $result->fetch_assoc();

    echo json_encode([
        'status' => 'success',
        'teacher' => $teacher
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>