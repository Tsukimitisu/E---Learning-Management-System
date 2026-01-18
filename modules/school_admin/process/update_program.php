<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$program_id = (int)($_POST['program_id'] ?? 0);
$program_code = trim($_POST['program_code'] ?? '');
$program_name = trim($_POST['program_name'] ?? '');
$degree_level = trim($_POST['degree_level'] ?? '');
$school_id = (int)($_POST['school_id'] ?? 0);
$is_active = (int)($_POST['is_active'] ?? 1);

// Validation
if ($program_id == 0 || empty($program_code) || empty($program_name) || empty($degree_level) || $school_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    // Check if program code already exists for another program
    $check = $conn->prepare("SELECT id FROM programs WHERE program_code = ? AND id != ?");
    $check->bind_param("si", $program_code, $program_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Program code already exists']);
        exit();
    }

    // Update the program
    $stmt = $conn->prepare("UPDATE programs SET program_code = ?, program_name = ?, degree_level = ?, school_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssiii", $program_code, $program_name, $degree_level, $school_id, $is_active, $program_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update program']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
