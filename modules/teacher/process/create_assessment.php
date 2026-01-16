<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$class_id = (int)($_POST['class_id'] ?? 0);
$title = clean_input($_POST['title'] ?? '');
$assessment_type = clean_input($_POST['assessment_type'] ?? '');
$max_score = floatval($_POST['max_score'] ?? 100);
$scheduled_date = !empty($_POST['scheduled_date']) ? clean_input($_POST['scheduled_date']) : NULL;
$duration_minutes = !empty($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : NULL;
$instructions = clean_input($_POST['instructions'] ?? '');

if ($class_id == 0 || empty($title) || empty($assessment_type)) {
    echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO assessments (class_id, title, assessment_type, max_score, scheduled_date, duration_minutes, instructions, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issdissi", $class_id, $title, $assessment_type, $max_score, $scheduled_date, $duration_minutes, $instructions, $_SESSION['user_id']);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created assessment: $title";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Assessment created successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create assessment']);
}
?>