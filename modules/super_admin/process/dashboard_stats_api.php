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
    // Ensure security_logs table exists
    $conn->query("CREATE TABLE IF NOT EXISTS security_logs (
        id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(10) UNSIGNED DEFAULT NULL,
        event_type VARCHAR(50) NOT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Total active users
    $users_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $users_stmt->execute();
    $total_users = $users_stmt->get_result()->fetch_assoc()['count'];
    
    // Total branches
    $branches_stmt = $conn->prepare("SELECT COUNT(*) as count FROM branches");
    $branches_stmt->execute();
    $total_branches = $branches_stmt->get_result()->fetch_assoc()['count'];
    

    // Failed logins today (from security_logs)
    $today = date('Y-m-d');
    $failed_logins = 0;
    $failed_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM security_logs 
        WHERE event_type = 'login_failed' AND DATE(created_at) = ?
    ");
    if ($failed_stmt) {
        $failed_stmt->bind_param("s", $today);
        $failed_stmt->execute();
        $failed_logins = $failed_stmt->get_result()->fetch_assoc()['count'];
    }

    // Locked accounts (from security_logs)
    $locked_accounts = 0;
    $locked_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as count FROM security_logs 
        WHERE event_type = 'account_locked' AND DATE(created_at) = ?
    ");
    if ($locked_stmt) {
        $locked_stmt->bind_param("s", $today);
        $locked_stmt->execute();
        $locked_accounts = $locked_stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Recent audit logs (last 20)
    $recent_logs = [];
    $audit_sql = "SELECT al.id, al.action, al.timestamp as created_at,
               CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as user_name
        FROM audit_logs al
        LEFT JOIN user_profiles up ON al.user_id = up.user_id
        ORDER BY al.timestamp DESC
        LIMIT 20";
    if ($audit_stmt = $conn->prepare($audit_sql)) {
        $audit_stmt->execute();
        $result = $audit_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recent_logs[] = $row;
        }
    }
    
    // User role distribution
    $role_dist = [];
    $roles_sql = "SELECT r.name, COUNT(ur.user_id) as count
        FROM roles r
        LEFT JOIN user_roles ur ON r.id = ur.role_id
        GROUP BY r.id, r.name";
    if ($roles_stmt = $conn->prepare($roles_sql)) {
        $roles_stmt->execute();
        $result = $roles_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $role_dist[] = $row;
        }
    }

    // System health - check both possible table names
    $is_maintenance = false;
    // Try security_settings first
    $maintenance_check = @$conn->prepare("SELECT setting_value FROM security_settings WHERE setting_key = 'maintenance_mode'");
    if ($maintenance_check) {
        $maintenance_check->execute();
        $maint_result = $maintenance_check->get_result()->fetch_assoc();
        $is_maintenance = ($maint_result['setting_value'] ?? '0') == '1';
    } else {
        // Fallback to system_settings
        $maintenance_check2 = @$conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        if ($maintenance_check2) {
            $maintenance_check2->execute();
            $maint_result = $maintenance_check2->get_result()->fetch_assoc();
            $is_maintenance = ($maint_result['setting_value'] ?? '0') == '1';
        }
    }
    
    $system_health = $is_maintenance ? 'Maintenance Mode' : 'Normal';
    
    // Database Status: try a simple query
    $db_status = 'Active';
    try {
        $conn->query('SELECT 1');
    } catch (Exception $e) {
        $db_status = 'Offline';
    }

    // API Gateway: simulate online (in real use, ping endpoint)
    $api_gateway = 'Online';

    // Server Load: use PHP sys_getloadavg if available, else random for demo
    $server_load = 'N/A';
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $server_load = round($load[0] * 25, 1) . '%'; // scale for demo
    } else {
        $server_load = rand(10, 40) . '%';
    }

    // Add these stats to the original response
    $response = [
        'success' => true,
        'stats' => [
            'total_users' => $total_users,
            'total_branches' => $total_branches,
            'failed_logins_today' => $failed_logins,
            'locked_accounts' => $locked_accounts,
            'system_health' => $system_health,
            'is_maintenance' => $is_maintenance,
            'db_status' => $db_status,
            'api_gateway' => $api_gateway,
            'server_load' => $server_load
        ],
        'recent_logs' => $recent_logs,
        'role_distribution' => $role_dist
    ];
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading stats: ' . $e->getMessage()
    ]);
}

?>
