<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_BRANCH_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Get user's branch_id
$branch_id_query = "SELECT branch_id FROM user_profiles WHERE user_id = ?";
$stmt = $conn->prepare($branch_id_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$branch_result = $stmt->get_result();
$branch_data = $branch_result->fetch_assoc();
$branch_id = $branch_data['branch_id'] ?? 0;

if (!$branch_id) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied: Branch assignment required']);
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
    
    // Step 1: Verify class belongs to this branch
    $classCheck = $conn->prepare("SELECT branch_id FROM classes WHERE id = ?");
    $classCheck->bind_param("i", $class_id);
    $classCheck->execute();
    $classResult = $classCheck->get_result();
    
    if ($classResult->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Class not found']);
        exit();
    }
    
    $classData = $classResult->fetch_assoc();
    if ($classData['branch_id'] != $branch_id) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Class does not belong to your branch']);
        exit();
    }

    // Step 2: Check if student exists
    $studentCheck = $conn->prepare("SELECT user_id FROM students WHERE user_id = ?");
    $studentCheck->bind_param("i", $student_id);
    $studentCheck->execute();
    $studentResult = $studentCheck->get_result();
    
    if ($studentResult->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit();
    }
    
    // Step 3: Check if already enrolled in this class
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

    // Step 4: Check Capacity
    $capacityCheck = $conn->prepare("
        SELECT current_enrolled, max_capacity 
        FROM classes 
        WHERE id = ? 
        FOR UPDATE
    ");
    $capacityCheck->bind_param("i", $class_id);
    $capacityCheck->execute();
    $capacityResult = $capacityCheck->get_result();
    $classCapData = $capacityResult->fetch_assoc();
    $current_enrolled = $classCapData['current_enrolled'];
    $max_capacity = $classCapData['max_capacity'];

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
    $action = "Branch admin enrolled student ID $student_id into class ID $class_id";
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

    // Get class info
    $classInfo = $conn->query("
        SELECT 
            COALESCE(cs.subject_code, 'N/A') as subject_code,
            COALESCE(cs.subject_title, 'N/A') as subject_title,
            cl.section_name,
            cl.room 
        FROM classes cl 
        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
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
