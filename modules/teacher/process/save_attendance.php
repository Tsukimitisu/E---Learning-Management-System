<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$section_id = (int)($input['section_id'] ?? 0);
$subject_id = (int)($input['subject_id'] ?? 0);
$attendance_date = $input['attendance_date'] ?? '';
$attendance_records = $input['attendance'] ?? [];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0 || empty($attendance_date) || empty($attendance_records)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $_SESSION['user_id'], $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
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
        $check = $conn->prepare("SELECT id FROM attendance WHERE section_id = ? AND subject_id = ? AND student_id = ? AND attendance_date = ?");
        $check->bind_param("iiis", $section_id, $subject_id, $student_id, $attendance_date);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        
        if ($exists) {
            // Update
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET status = ?, time_in = ?, time_out = ?, remarks = ?, recorded_by = ? 
                WHERE section_id = ? AND subject_id = ? AND student_id = ? AND attendance_date = ?
            ");
            $stmt->bind_param("ssssiiiis", $status, $time_in, $time_out, $remarks, $_SESSION['user_id'], $section_id, $subject_id, $student_id, $attendance_date);
        } else {
            // Insert
            $stmt = $conn->prepare("
                INSERT INTO attendance (section_id, subject_id, student_id, attendance_date, status, time_in, time_out, remarks, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiisssssi", $section_id, $subject_id, $student_id, $attendance_date, $status, $time_in, $time_out, $remarks, $_SESSION['user_id']);
        }
        
        $stmt->execute();
        $saved++;
    }
    
    // Log audit
    $ip = get_client_ip();
    $action = "Saved attendance for section ID $section_id, subject ID $subject_id on $attendance_date ($saved students)";
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