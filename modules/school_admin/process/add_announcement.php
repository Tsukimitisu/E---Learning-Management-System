<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$title = clean_input($_POST['title'] ?? '');
$content = clean_input($_POST['content'] ?? '');
$target_audience = clean_input($_POST['target_audience'] ?? 'all');
$priority = clean_input($_POST['priority'] ?? 'normal');
$scope_type = clean_input($_POST['scope_type'] ?? 'system');
$school_id = ($scope_type === 'school') ? (int)($_POST['school_id'] ?? 0) : NULL;
$expires_at = !empty($_POST['expires_at']) ? clean_input($_POST['expires_at']) : NULL;

if (empty($title) || empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Title and content are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO announcements (title, content, target_audience, priority, school_id, created_by, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssis", $title, $content, $target_audience, $priority, $school_id, $_SESSION['user_id'], $expires_at);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created announcement: $title";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Announcement posted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to post announcement']);
}
?>