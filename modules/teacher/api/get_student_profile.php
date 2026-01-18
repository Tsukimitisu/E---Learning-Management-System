<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = (int)($_GET['student_id'] ?? 0);

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID']);
    exit();
}

// Get student profile info
$query = "
    SELECT 
        u.id,
        u.email,
        up.first_name,
        up.last_name,
        up.student_id,
        up.phone,
        up.gender,
        up.birthday,
        up.address
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if ($student) {
    echo json_encode(['success' => true, 'student' => $student]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}
