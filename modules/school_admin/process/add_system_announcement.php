<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$title = clean_input($_POST['title'] ?? '');
$content = clean_input($_POST['content'] ?? '');
$priority = clean_input($_POST['priority'] ?? 'normal');
$expires_at = clean_input($_POST['expires_at'] ?? null);

if (empty($title) || empty($content)) {
    echo json_encode(['status' => 'error', 'message' => 'Title and content are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO announcements (title, content, priority, target_audience, created_by, is_active, expires_at)
        VALUES (?, ?, ?, 'all', ?, 1, ?)
    ");
    
    $target = 'all';
    $stmt->bind_param("sssss", $title, $content, $priority, $_SESSION['user_id'], $expires_at);
    $stmt->execute();

    // Log action
    $ip = get_client_ip();
    $action = "Published institution-wide announcement: $title";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    echo json_encode(['status' => 'success', 'message' => 'Announcement published successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error publishing announcement']);
}
?>
