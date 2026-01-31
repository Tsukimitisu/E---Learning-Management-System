<?php
/**
 * Dashboard Stats API - Comprehensive system stats
 * Concurrency: Uses READ-ONLY queries with proper indexing
 */

require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Total active users
    $users_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $users_stmt->execute();
    $total_users = $users_stmt->get_result()->fetch_assoc()['count'];
    
    // Total branches
    $branches_stmt = $conn->prepare("SELECT COUNT(*) as count FROM branches");
    $branches_stmt->execute();
    $total_branches = $branches_stmt->get_result()->fetch_assoc()['count'];
    
    // Failed logins today
    $today = date('Y-m-d');
    $failed_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM login_attempts 
        WHERE success = 0 AND DATE(attempted_at) = ?
    ");
    $failed_stmt->bind_param("s", $today);
    $failed_stmt->execute();
    $failed_logins = $failed_stmt->get_result()->fetch_assoc()['count'];
    
    // Locked accounts
    $locked_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'");
    $locked_stmt->execute();
    $locked_accounts = $locked_stmt->get_result()->fetch_assoc()['count'];
    
    // Recent audit logs (last 20)
    $audit_stmt = $conn->prepare("
        SELECT al.id, al.action, al.created_at,
               CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as user_name
        FROM audit_logs al
        LEFT JOIN user_profiles up ON al.user_id = up.user_id
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
    $audit_stmt->execute();
    $recent_logs = [];
    while ($row = $audit_stmt->get_result()->fetch_assoc()) {
        $recent_logs[] = $row;
    }
    
    // User role distribution
    $roles_stmt = $conn->prepare("
        SELECT r.name, COUNT(ur.user_id) as count
        FROM roles r
        LEFT JOIN user_roles ur ON r.id = ur.role_id
        GROUP BY r.id, r.name
    ");
    $roles_stmt->execute();
    $role_dist = [];
    while ($row = $roles_stmt->get_result()->fetch_assoc()) {
        $role_dist[] = $row;
    }
    
    // System health
    $maintenance_check = $conn->prepare("
        SELECT setting_value FROM security_settings WHERE setting_key = 'maintenance_mode'
    ");
    $maintenance_check->execute();
    $maint_result = $maintenance_check->get_result()->fetch_assoc();
    $is_maintenance = ($maint_result['setting_value'] ?? '0') == '1';
    
    $system_health = $is_maintenance ? 'Maintenance Mode' : 'Normal';
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $total_users,
            'total_branches' => $total_branches,
            'failed_logins_today' => $failed_logins,
            'locked_accounts' => $locked_accounts,
            'system_health' => $system_health,
            'is_maintenance' => $is_maintenance
        ],
        'recent_logs' => $recent_logs,
        'role_distribution' => $role_dist
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>
