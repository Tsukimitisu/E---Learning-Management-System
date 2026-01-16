<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
$subject_id = (int)($_POST['subject_id'] ?? 0);
$section_name = clean_input($_POST['section_name'] ?? '');
$teacher_id = (int)($_POST['teacher_id'] ?? 0);
$room = clean_input($_POST['room'] ?? '');
$max_capacity = (int)($_POST['max_capacity'] ?? 0);
$schedule = clean_input($_POST['schedule'] ?? '');
$branch_id = (int)($_POST['branch_id'] ?? 0);

if ($academic_year_id == 0 || $subject_id == 0 || empty($section_name) || 
    $teacher_id == 0 || empty($room) || $max_capacity < 1 || empty($schedule) || $branch_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Insert class (Note: course_id can be NULL if using subject_id)
    $stmt = $conn->prepare("
        INSERT INTO classes (
            academic_year_id, subject_id, section_name, teacher_id, 
            room, schedule, max_capacity, current_enrolled, branch_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)
    ");
    $stmt->bind_param("iississi", $academic_year_id, $subject_id, $section_name, 
                      $teacher_id, $room, $schedule, $max_capacity, $branch_id);
    $stmt->execute();
    
    $class_id = $conn->insert_id();
    
    // Log audit
    $ip = get_client_ip();
    $action = "Scheduled class: $section_name - Room $room - $schedule";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Class scheduled successfully',
        'class_id' => $class_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to schedule class: ' . $e->getMessage()]);
}
?>