<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $subject_id = (int)$_POST['subject_id'];
    $subject_code = clean_input($_POST['subject_code']);
    $subject_title = clean_input($_POST['subject_title']);
    $units = (float)$_POST['units'];
    $lecture_hours = (int)($_POST['hours'] ?? $_POST['lecture_hours'] ?? 0);
    $lab_hours = (int)($_POST['lab_hours'] ?? 0);
    $subject_type = clean_input($_POST['category'] ?? $_POST['subject_type'] ?? 'college');
    $prerequisites = clean_input($_POST['prerequisites'] ?? '');
    $is_active = (int)$_POST['is_active'];

    // Check if subject code conflicts with another subject
    $check_code = $conn->prepare("SELECT id FROM curriculum_subjects WHERE subject_code = ? AND id != ?");
    $check_code->bind_param("si", $subject_code, $subject_id);
    $check_code->execute();

    if ($check_code->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Subject code already exists']);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE curriculum_subjects
        SET subject_code = ?, subject_title = ?, units = ?, lecture_hours = ?, lab_hours = ?, subject_type = ?, prerequisites = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssdiissii", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $subject_type, $prerequisites, $is_active, $subject_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update subject']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
