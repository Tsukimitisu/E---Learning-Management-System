<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $grade_id = (int)$_POST['grade_id'];
    $grade_name = clean_input($_POST['grade_name']);
    $semesters = (int)$_POST['semesters'];
    $is_active = (int)$_POST['is_active'];

    $stmt = $conn->prepare("
        UPDATE shs_grade_levels
        SET grade_name = ?, semesters_count = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->bind_param("siii", $grade_name, $semesters, $is_active, $grade_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Grade level updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update grade level']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
