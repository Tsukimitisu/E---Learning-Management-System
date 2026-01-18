<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_REGISTRAR) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$student_id = (int)($_POST['student_id'] ?? 0);
$class_id = (int)($_POST['class_id'] ?? 0);

if ($student_id == 0 || $class_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid student or class ID']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Step 1: Check if student exists
    $studentCheck = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
    $studentCheck->bind_param("i", $student_id);
    $studentCheck->execute();
    $studentResult = $studentCheck->get_result();
    
    if ($studentResult->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit();
    }
    
    // Step 2: Check if already enrolled in this class
    $enrollCheck = $conn->prepare("
        SELECT id FROM enrollments 
        WHERE student_id = ? AND class_id = ? AND status != 'rejected'
    ");
    $enrollCheck->bind_param("ii", $student_id, $class_id);
    $enrollCheck->execute();
    $enrollResult = $enrollCheck->get_result();
    
    if ($enrollResult->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Student is already enrolled in this class']);
        exit();
    }

    // Step 2.5: Check Payment Status (Optional - can be configured)
    $paymentCheck = $conn->prepare("
        SELECT SUM(amount) as total_paid 
        FROM payments 
        WHERE student_id = ? AND status = 'verified'
    ");
    $paymentCheck->bind_param("i", $student_id);
    $paymentCheck->execute();
    $paymentResult = $paymentCheck->get_result();
    $paymentData = $paymentResult->fetch_assoc();

    // Optional: Enforce minimum payment requirement
    // if (($paymentData['total_paid'] ?? 0) < MINIMUM_PAYMENT) {
    //     $conn->rollback();
    //     echo json_encode(['status' => 'error', 'message' => 'Student has insufficient payment']);
    //     exit();
    // }
    
    // Step 3: LOCK CLASS ROW and Check Capacity
    $capacityCheck = $conn->prepare("
        SELECT current_enrolled, max_capacity 
        FROM classes 
        WHERE id = ? 
        FOR UPDATE
    ");
    $capacityCheck->bind_param("i", $class_id);
    $capacityCheck->execute();
    $capacityResult = $capacityCheck->get_result();

    if ($capacityResult->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Class not found']);
        exit();
    }

    $classData = $capacityResult->fetch_assoc();
    $current_enrolled = $classData['current_enrolled'];
    $max_capacity = $classData['max_capacity'];

    // Step 4: Verify Capacity Available
    if ($current_enrolled >= $max_capacity) {
        $conn->rollback();
        echo json_encode([
            'status' => 'error', 
            'message' => 'Class is FULL. No available slots.',
            'current' => $current_enrolled,
            'max' => $max_capacity
        ]);
        exit();
    }

    // Step 5: Insert Enrollment Record
    $insertEnroll = $conn->prepare("
        INSERT INTO enrollments (student_id, class_id, status, created_at) 
        VALUES (?, ?, 'approved', NOW())
    ");
    $insertEnroll->bind_param("ii", $student_id, $class_id);

    if (!$insertEnroll->execute()) {
        throw new Exception("Failed to create enrollment record");
    }

    $enrollment_id = $conn->insert_id;

    // Step 6: Increment current_enrolled Counter
    $updateClass = $conn->prepare("
        UPDATE classes 
        SET current_enrolled = current_enrolled + 1 
        WHERE id = ?
    ");
    $updateClass->bind_param("i", $class_id);

    if (!$updateClass->execute()) {
        throw new Exception("Failed to update class enrollment count");
    }

    // Step 7: Log Audit Trail
    $ip_address = get_client_ip();
    $paid_amount = $paymentData['total_paid'] ?? 0;
    $action = "Enrolled student ID $student_id into class ID $class_id (Verified Payment: {$paid_amount})";
    $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $auditStmt->bind_param("iss", $_SESSION['user_id'], $action, $ip_address);
    $auditStmt->execute();

    // COMMIT TRANSACTION
    $conn->commit();

    // Get student and class info for response
    $studentInfo = $conn->query("
        SELECT CONCAT(first_name, ' ', last_name) as name 
        FROM user_profiles 
        WHERE user_id = $student_id
    ")->fetch_assoc();

    // Get class info - support both curriculum_subjects and courses
    $classInfo = $conn->query("
        SELECT 
            COALESCE(cs.subject_code, c.course_code, 'N/A') as subject_code,
            COALESCE(cs.subject_title, c.course_title, 'N/A') as subject_title,
            cl.section_name,
            cl.room 
        FROM classes cl 
        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        LEFT JOIN courses c ON cl.course_id = c.id 
        WHERE cl.id = $class_id
    ")->fetch_assoc();

    echo json_encode([
        'status' => 'success',
        'message' => sprintf(
            'Successfully enrolled %s in %s - %s',
            $studentInfo['name'] ?? 'Student',
            $classInfo['subject_code'] ?? 'Subject',
            $classInfo['section_name'] ?? 'Section'
        ),
        'payment_verified_total' => $paid_amount,
        'enrollment_id' => $enrollment_id,
        'new_count' => $current_enrolled + 1
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Enrollment Error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Enrollment failed. Please try again.'
    ]);
}

$conn->close();
?>