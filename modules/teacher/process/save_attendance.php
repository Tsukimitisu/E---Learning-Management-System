<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$class_id = (int)($input['class_id'] ?? 0);
$attendance_date = $input['attendance_date'] ?? '';
$attendance_records = $input['attendance'] ?? [];

if ($class_id == 0 || empty($attendance_date) || empty($attendance_records)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

try {
    $conn->begin_transaction();
    
    $saved = 0;
    foreach ($attendance_records as $record) {
        $student_id = (int)$record['student_id'];
        $status = clean_input($record['status']);
        $time_in = !empty($record['time_in']) ? $record['time_in'] : NULL;
        $time_out = !empty($record['time_out']) ? $record['time_out'] : NULL;
        $remarks = clean_input($record['remarks'] ?? '');
        
        // Check if attendance exists
        $check = $conn->prepare("SELECT id FROM attendance WHERE class_id = ? AND student_id = ? AND attendance_date = ?");
        $check->bind_param("iis", $class_id, $student_id, $attendance_date);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        
        if ($exists) {
            // Update
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET status = ?, time_in = ?, time_out = ?, remarks = ?, recorded_by = ? 
                WHERE class_id = ? AND student_id = ? AND attendance_date = ?
            ");
            $stmt->bind_param("ssssiiss", $status, $time_in, $time_out, $remarks, $_SESSION['user_id'], $class_id, $student_id, $attendance_date);
        } else {
            // Insert
            $stmt = $conn->prepare("
                INSERT INTO attendance (class_id, student_id, attendance_date, status, time_in, time_out, remarks, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisssssi", $class_id, $student_id, $attendance_date, $status, $time_in, $time_out, $remarks, $_SESSION['user_id']);
        }
        
        $stmt->execute();
        $saved++;
    }
    
    // Log audit
    $ip = get_client_ip();
    $action = "Saved attendance for class ID $class_id on $attendance_date ($saved students)";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    $conn->commit();
    
    echo json_encode(['status' => 'success', 'message' => "Attendance saved for $saved students"]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to save attendance']);
}
?>