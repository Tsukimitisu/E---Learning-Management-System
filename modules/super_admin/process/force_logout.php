<?php
/**
 * Force Logout - Terminate all sessions of a specific user
 * Security: Super Admin only
 * Concurrency: Uses transactions and pessimistic locking
 */

require_once '../../../config/init.php';

header('Content-Type: application/json');

// Authorization check
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$target_user_id = (int)($_POST['user_id'] ?? 0);

if ($target_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

// Prevent admin from logging themselves out
if ($target_user_id === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot force logout your own account']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Get user info for audit (with read lock)
    $user_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
    $user_stmt->bind_param("i", $target_user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Invalidate all active sessions for the target user
    $sessions_stmt = $conn->prepare("
        UPDATE active_sessions 
        SET is_active = 0, invalidated_at = NOW() 
        WHERE user_id = ? AND is_active = 1
    ");
    $sessions_stmt->bind_param("i", $target_user_id);
    $sessions_stmt->execute();
    $invalidated_count = $sessions_stmt->affected_rows;
    
    // Log the force logout action
    $ip = get_client_ip();
    $action = "Force logged out user ID: $target_user_id (invalidated $invalidated_count session(s))";
    $audit_stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, ip_address, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $audit_stmt->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit_stmt->execute();
    
    // Log security event
    $event_type = 'force_logout';
    $event_details = "Super Admin force logged out user $target_user_id";
    $security_log_stmt = $conn->prepare("
        INSERT INTO security_logs (user_id, event_type, details, ip_address, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $security_log_stmt->bind_param("isss", $_SESSION['user_id'], $event_type, $event_details, $ip);
    $security_log_stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "User has been force logged out ($invalidated_count session(s) terminated)"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Force logout failed: ' . $e->getMessage()]);
}

?>
