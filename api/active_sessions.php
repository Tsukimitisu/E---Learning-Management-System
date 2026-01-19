<?php
/**
 * Active Sessions API
 * Monitor and manage concurrent user sessions
 * Super Admin only
 */
require_once '../../config/init.php';

header('Content-Type: application/json');

// Only Super Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        // Get all active sessions with user info
        $query = "
            SELECT 
                s.id,
                s.session_id,
                s.user_id,
                CONCAT(up.first_name, ' ', up.last_name) as user_name,
                u.email,
                r.name as role_name,
                s.ip_address,
                s.last_activity,
                s.created_at,
                TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as idle_minutes
            FROM active_sessions s
            JOIN users u ON s.user_id = u.id
            JOIN user_profiles up ON u.id = up.user_id
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            ORDER BY s.last_activity DESC
        ";
        
        $result = $conn->query($query);
        $sessions = [];
        
        while ($row = $result->fetch_assoc()) {
            $sessions[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'email' => $row['email'],
                'role' => $row['role_name'],
                'ip_address' => $row['ip_address'],
                'last_activity' => $row['last_activity'],
                'idle_minutes' => (int)$row['idle_minutes'],
                'status' => $row['idle_minutes'] < 5 ? 'active' : ($row['idle_minutes'] < 30 ? 'idle' : 'inactive')
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'total' => count($sessions),
            'sessions' => $sessions
        ]);
        break;
        
    case 'stats':
        // Get session statistics by role
        $stats = [];
        
        // Total active users
        $result = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM active_sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $stats['total_active'] = (int)$result->fetch_assoc()['total'];
        
        // By role
        $query = "
            SELECT r.name as role, COUNT(DISTINCT s.user_id) as count
            FROM active_sessions s
            JOIN user_roles ur ON s.user_id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            GROUP BY r.id
        ";
        $result = $conn->query($query);
        $stats['by_role'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_role'][$row['role']] = (int)$row['count'];
        }
        
        // Concurrent sessions (same user, different sessions)
        $query = "
            SELECT user_id, COUNT(*) as session_count
            FROM active_sessions
            WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            GROUP BY user_id
            HAVING session_count > 1
        ";
        $result = $conn->query($query);
        $stats['users_with_multiple_sessions'] = $result->num_rows;
        
        echo json_encode([
            'status' => 'success',
            'stats' => $stats
        ]);
        break;
        
    case 'terminate':
        // Terminate a specific session (Super Admin only)
        $session_id = $_POST['session_id'] ?? '';
        
        if (empty($session_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
            exit();
        }
        
        // Don't allow terminating own session
        if ($session_id === session_id()) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot terminate your own session']);
            exit();
        }
        
        // Get user info for audit
        $stmt = $conn->prepare("SELECT user_id FROM active_sessions WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $terminated_user_id = $result['user_id'];
            
            // Delete session record
            $stmt = $conn->prepare("DELETE FROM active_sessions WHERE session_id = ?");
            $stmt->bind_param("s", $session_id);
            $stmt->execute();
            
            // Log audit
            $ip = get_client_ip();
            $action = "Terminated session for user ID: $terminated_user_id";
            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $_SESSION['user_id'], $action, $ip);
            $stmt->execute();
            
            echo json_encode(['status' => 'success', 'message' => 'Session terminated']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Session not found']);
        }
        break;
        
    case 'terminate_user':
        // Terminate all sessions for a specific user
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($target_user_id == 0) {
            echo json_encode(['status' => 'error', 'message' => 'User ID required']);
            exit();
        }
        
        // Don't allow terminating own sessions
        if ($target_user_id == $_SESSION['user_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot terminate your own sessions']);
            exit();
        }
        
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE user_id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        
        // Log audit
        $ip = get_client_ip();
        $action = "Terminated all sessions ($affected) for user ID: $target_user_id";
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $_SESSION['user_id'], $action, $ip);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => "Terminated $affected session(s)"
        ]);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
