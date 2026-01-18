<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
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

$enrollment_id = (int)($_POST['enrollment_id'] ?? 0);

if ($enrollment_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid enrollment ID']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Get enrollment details and verify it belongs to this branch
    $enrollmentQuery = $conn->prepare("
        SELECT e.id, e.class_id, cl.branch_id, cl.current_enrolled
        FROM enrollments e
        INNER JOIN classes cl ON e.class_id = cl.id
        WHERE e.id = ?
    ");
    $enrollmentQuery->bind_param("i", $enrollment_id);
    $enrollmentQuery->execute();
    $enrollmentResult = $enrollmentQuery->get_result();
    
    if ($enrollmentResult->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Enrollment not found']);
        exit();
    }
    
    $enrollmentData = $enrollmentResult->fetch_assoc();
    $class_id = $enrollmentData['class_id'];
    
    // Verify the enrollment's class belongs to this branch
    if ($enrollmentData['branch_id'] != $branch_id) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Cannot unenroll from this class']);
        exit();
    }

    // Delete the enrollment
    $deleteStmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $deleteStmt->bind_param("i", $enrollment_id);
    
    if (!$deleteStmt->execute()) {
        throw new Exception("Failed to delete enrollment");
    }

    // Decrease current_enrolled count
    $updateClass = $conn->prepare("
        UPDATE classes 
        SET current_enrolled = current_enrolled - 1 
        WHERE id = ? AND current_enrolled > 0
    ");
    $updateClass->bind_param("i", $class_id);
    
    if (!$updateClass->execute()) {
        throw new Exception("Failed to update class enrollment count");
    }

    // Log audit trail
    $ip_address = get_client_ip();
    $action = "Branch admin unenrolled student from class ID $class_id (enrollment ID: $enrollment_id)";
    $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $auditStmt->bind_param("iss", $_SESSION['user_id'], $action, $ip_address);
    $auditStmt->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Student successfully unenrolled from the class'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Unenrollment Error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Unenrollment failed. Please try again.'
    ]);
}

$conn->close();
?>
