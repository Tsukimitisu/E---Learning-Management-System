<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = (int)($_POST['student_id'] ?? 0);
$class_id = (int)($_POST['class_id'] ?? 0);
$midterm = floatval($_POST['midterm'] ?? 0);
$final = floatval($_POST['final'] ?? 0);
$final_grade = floatval($_POST['final_grade'] ?? 0);
$remarks = clean_input($_POST['remarks'] ?? '');
$grade_id = (int)($_POST['grade_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($student_id == 0 || $class_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// Verify class belongs to teacher
$verify = $conn->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
$verify->bind_param("ii", $class_id, $teacher_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    if ($grade_id > 0) {
        // Update existing grade
        $stmt = $conn->prepare("
            UPDATE grades 
            SET midterm = ?, final = ?, final_grade = ?, remarks = ?
            WHERE id = ? AND student_id = ? AND class_id = ?
        ");
        $stmt->bind_param("dddisii", $midterm, $final, $final_grade, $remarks, $grade_id, $student_id, $class_id);
        $stmt->execute();
        
        $return_grade_id = $grade_id;
    } else {
        // Insert new grade
        $stmt = $conn->prepare("
            INSERT INTO grades (student_id, class_id, midterm, final, final_grade, remarks) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiddds", $student_id, $class_id, $midterm, $final, $final_grade, $remarks);
        $stmt->execute();
        
        $return_grade_id = $conn->insert_id;
    }
    
    // Log audit
    $ip = get_client_ip();
    $action = "Updated grade for student ID $student_id in class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $teacher_id, $action, $ip);
    $audit->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Grade saved successfully',
        'grade_id' => $return_grade_id
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save grade']);
}
?>