<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$issue_type = clean_input($data['issue_type'] ?? '');

if (empty($id) || empty($issue_type)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit();
}

try {
    $issue_description = '';
    $severity = 'medium';

    switch ($issue_type) {
        case 'missing_grades':
            // Escalate missing grades issue
            $query = "
                SELECT cl.section_name, s.subject_code, s.subject_title,
                       CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
                       COUNT(e.student_id) as affected_students
                FROM classes cl
                LEFT JOIN subjects s ON cl.subject_id = s.id
                LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
                LEFT JOIN users u ON cl.teacher_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE cl.id = ?
                GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, up.first_name, up.last_name
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result) {
                $issue_description = "URGENT: Grades not submitted for {$result['subject_code']} - {$result['section_name']} (Teacher: {$result['teacher_name']}). {$result['affected_students']} students affected.";
                $severity = 'high';
            }
            break;

        case 'low_attendance':
            $issue_description = "Persistent low attendance in class ID: $id";
            $severity = 'medium';
            break;

        case 'academic_failure':
            $issue_description = "Multiple students failing in class ID: $id";
            $severity = 'high';
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid issue type']);
            exit();
    }

    if ($issue_description) {
        // Get school admin users
        $school_admins = $conn->query("
            SELECT u.id
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = " . ROLE_SCHOOL_ADMIN . " AND u.status = 'active'
        ");

        $escalated_count = 0;
        while ($admin = $school_admins->fetch_assoc()) {
            // Insert escalation notification
            $insert_escalation = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, priority, created_by, created_at)
                VALUES (?, 'Issue Escalation', ?, 'escalation', ?, ?, NOW())
            ");
            $insert_escalation->bind_param("issii", $admin['id'], $issue_description, $severity, $_SESSION['user_id']);
            $insert_escalation->execute();
            $escalated_count++;
        }

        if ($escalated_count > 0) {
            // Log the escalation
            $ip = get_client_ip();
            $action = "Escalated $issue_type issue (ID: $id) to $escalated_count school administrators";
            $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
            $audit->execute();

            echo json_encode([
                'status' => 'success',
                'message' => "Issue escalated to $escalated_count school administrators"
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No school administrators found to escalate to']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not generate issue description']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>