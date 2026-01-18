<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $year_id = (int)$_POST['year_id'];
    $year_name = clean_input($_POST['year_name']);
    $year_number = (int)$_POST['year_number'];
    $semesters = (int)$_POST['semesters'];
    $is_active = (int)$_POST['is_active'];

    $stmt = $conn->prepare("
        UPDATE program_year_levels
        SET year_name = ?, year_level = ?, semesters_count = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->bind_param("siiii", $year_name, $year_number, $semesters, $is_active, $year_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Year level updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update year level']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
