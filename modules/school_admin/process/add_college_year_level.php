<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $program_id = (int)$_POST['program_id'];
    $year_level = (int)$_POST['year_level'];
    $year_name = clean_input($_POST['year_name']);
    $semesters_count = (int)$_POST['semesters_count'];

    $stmt = $conn->prepare("
        INSERT INTO program_year_levels (program_id, year_level, year_name, semesters_count, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("iisi", $program_id, $year_level, $year_name, $semesters_count);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Year level added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add year level']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
