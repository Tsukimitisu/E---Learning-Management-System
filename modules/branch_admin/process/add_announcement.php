<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$title = clean_input($_POST['title'] ?? '');
$content = clean_input($_POST['content'] ?? '');
$target_audience = clean_input($_POST['target_audience'] ?? 'all');
$priority = clean_input($_POST['priority'] ?? 'normal');
$branch_id = (int)($_POST['branch_id'] ?? 0);

if (empty($title) || empty($content) || $branch_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO announcements (title, content, target_audience, priority, branch_id, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssis", $title, $content, $target_audience, $priority, $branch_id, $_SESSION['user_id']);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created branch announcement: $title";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Announcement posted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to post announcement']);
}
?>