<?php
require_once '../../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in as registrar
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$enrollment_id = isset($_POST['enrollment_id']) ? (int)$_POST['enrollment_id'] : 0;

if (!$enrollment_id) {
    echo json_encode(['status' => 'error', 'message' => 'Enrollment ID is required']);
    exit;
}

// Get registrar's branch
$registrar_id = $_SESSION['user_id'];
$branch_query = "SELECT branch_id FROM user_profiles WHERE user_id = ?";
$branch_stmt = $conn->prepare($branch_query);
$branch_stmt->bind_param("i", $registrar_id);
$branch_stmt->execute();
$branch_result = $branch_stmt->get_result();
$registrar_profile = $branch_result->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Verify the enrollment belongs to student in this branch
$verify_query = "SELECT e.id, e.class_id, e.student_id, cl.branch_id, cl.section_name
                 FROM enrollments e
                 JOIN classes cl ON e.class_id = cl.id
                 WHERE e.id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("i", $enrollment_id);
$verify_stmt->execute();
$enrollment = $verify_stmt->get_result()->fetch_assoc();

if (!$enrollment) {
    echo json_encode(['status' => 'error', 'message' => 'Enrollment not found']);
    exit;
}

if ($enrollment['branch_id'] != $branch_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Enrollment does not belong to your branch']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Update enrollment status to dropped/withdrawn
    $update_query = "UPDATE enrollments SET status = 'dropped' WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $enrollment_id);
    $update_stmt->execute();
    
    // Update class current_enrolled count
    $decrement_query = "UPDATE classes SET current_enrolled = GREATEST(0, current_enrolled - 1) WHERE id = ?";
    $decrement_stmt = $conn->prepare($decrement_query);
    $decrement_stmt->bind_param("i", $enrollment['class_id']);
    $decrement_stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Student successfully removed from ' . $enrollment['section_name']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to unenroll student: ' . $e->getMessage()
    ]);
}
