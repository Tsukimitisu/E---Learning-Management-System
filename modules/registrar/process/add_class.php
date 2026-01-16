<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$course_id = (int)($_POST['course_id'] ?? 0);
$teacher_id = (int)($_POST['teacher_id'] ?? 0);
$room = clean_input($_POST['room'] ?? '');
$max_capacity = (int)($_POST['max_capacity'] ?? 0);

if ($course_id == 0 || $teacher_id == 0 || empty($room) || $max_capacity < 1) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO classes (course_id, teacher_id, room, max_capacity, current_enrolled) 
        VALUES (?, ?, ?, ?, 0)
    ");
    $stmt->bind_param("iisi", $course_id, $teacher_id, $room, $max_capacity);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created class: Room $room";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Class created successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create class']);
}
?>