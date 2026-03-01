<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$type = clean_input($data['type'] ?? '');

if (empty($id) || empty($type)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

try {
    $message = '';
    $target_user_id = null;

    switch ($type) {
        case 'academic':
            // Notification to student about academic performance
            $target_user_id = $id;
            $message = "Academic Alert: Your performance in one or more subjects requires attention. Please contact your teachers for support.";
            break;

        case 'grades':
            // Reminder to teacher to submit grades
            $query = "
                SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
                       cl.section_name, s.subject_code
                FROM classes cl
                LEFT JOIN subjects s ON cl.subject_id = s.id
                LEFT JOIN users u ON cl.teacher_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE cl.id = ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result) {
                $target_user_id = $result['id'];
                $message = "Reminder: Please submit grades for {$result['subject_code']} - {$result['section_name']} at your earliest convenience.";
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid reminder type']);
            exit();
    }

    if ($target_user_id && $message) {
        // Create persistent notification using the helper
        create_notification(
            $target_user_id,
            'Branch Admin Reminder',
            $message,
            'system',
            null,
            $_SESSION['user_id']
        );

        if (true) {
            // Log the action
            $ip = get_client_ip();
            $action = "Sent $type reminder to user ID $target_user_id";
            $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
            $audit->execute();

            echo json_encode([
                'status' => 'success',
                'message' => 'Reminder sent successfully'
            ]);
        } else {
            throw new Exception('Failed to send reminder');
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not determine reminder target']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>