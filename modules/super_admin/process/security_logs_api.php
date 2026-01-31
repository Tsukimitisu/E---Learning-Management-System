<?php
/**
 * Security Logs API - Get, filter, search, and export security logs
 * Tracks login attempts, suspicious activities, force logouts
 * Concurrency: Uses transactions and proper indexing
 */

require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}


$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'list') {
        // Always return the latest 200 security logs for all users, no filters or pagination
        $query = "SELECT sl.id, sl.user_id, sl.event_type, sl.details, sl.ip_address, 
                         sl.severity, sl.created_at,
                         CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as user_name
                  FROM security_logs sl
                  LEFT JOIN user_profiles up ON sl.user_id = up.user_id
                  ORDER BY sl.created_at DESC
                  LIMIT 200";
        $result = $conn->query($query);
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        echo json_encode([
            'success' => true,
            'data' => $logs
        ]);
        
    } elseif ($action === 'export') {
        // Export as CSV
        $query = "SELECT sl.id, sl.user_id, sl.event_type, sl.details, sl.ip_address, 
                         sl.severity, sl.created_at,
                         CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as user_name
                  FROM security_logs sl
                  LEFT JOIN user_profiles up ON sl.user_id = up.user_id
                  WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if (!empty($event_type)) {
            $query .= " AND sl.event_type = ?";
            $params[] = $event_type;
            $types .= 's';
        }
        
        if (!empty($date_from)) {
            $query .= " AND DATE(sl.created_at) >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        
        if (!empty($date_to)) {
            $query .= " AND DATE(sl.created_at) <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        
        if ($user_id > 0) {
            $query .= " AND sl.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }
        
        $query .= " ORDER BY sl.created_at DESC LIMIT 10000";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Generate CSV
        $filename = 'security_logs_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=$filename");
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'User ID', 'User Name', 'Event Type', 'Details', 'IP Address', 'Severity', 'Timestamp']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['user_id'],
                $row['user_name'],
                $row['event_type'],
                $row['details'],
                $row['ip_address'],
                $row['severity'],
                $row['created_at']
            ]);
        }
        fclose($output);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>
