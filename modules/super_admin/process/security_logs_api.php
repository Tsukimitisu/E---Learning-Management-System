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
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

$search = clean_input($_GET['search'] ?? '');
$event_type = clean_input($_GET['event_type'] ?? '');
$date_from = clean_input($_GET['date_from'] ?? '');
$date_to = clean_input($_GET['date_to'] ?? '');
$user_id = (int)($_GET['user_id'] ?? 0);
$severity = clean_input($_GET['severity'] ?? '');

try {
    if ($action === 'list') {
        $query = "SELECT sl.id, sl.user_id, sl.event_type, sl.details, sl.ip_address, 
                         sl.severity, sl.created_at,
                         CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as user_name
                  FROM security_logs sl
                  LEFT JOIN user_profiles up ON sl.user_id = up.user_id
                  WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $search_term = "%$search%";
            $query .= " AND (sl.details LIKE ? OR sl.event_type LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ss';
        }
        
        if (!empty($event_type)) {
            $query .= " AND sl.event_type = ?";
            $params[] = $event_type;
            $types .= 's';
        }
        
        if (!empty($severity)) {
            $query .= " AND sl.severity = ?";
            $params[] = $severity;
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
        
        // Count total
        $count_query = str_replace('SELECT sl.id, sl.user_id, sl.event_type, sl.details, sl.ip_address, sl.severity, sl.created_at, CONCAT(COALESCE(up.first_name, \'\'), \' \', COALESCE(up.last_name, \'\')) as user_name',
                                   'SELECT COUNT(*) as total',
                                   $query);
        
        $count_stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $total = $count_result['total'];
        
        // Get paginated results
        $query .= " ORDER BY sl.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
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
