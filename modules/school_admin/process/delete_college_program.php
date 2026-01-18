<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_id = (int)$data['program_id'];

    // Check if program has associated year levels
    $check_years = $conn->prepare("SELECT COUNT(*) as count FROM program_year_levels WHERE program_id = ?");
    $check_years->bind_param("i", $program_id);
    $check_years->execute();
    $year_count = $check_years->get_result()->fetch_assoc()['count'];

    if ($year_count > 0) {
        echo json_encode(['status' => 'error', 'message' => "Cannot delete program with $year_count associated year levels. Delete year levels first."]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
    $stmt->bind_param("i", $program_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Program deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete program']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
