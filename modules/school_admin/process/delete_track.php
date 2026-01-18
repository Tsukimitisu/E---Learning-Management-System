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
    $track_id = (int)$data['track_id'];

    // Check if track has associated strands
    $check_strands = $conn->prepare("SELECT COUNT(*) as count FROM shs_strands WHERE track_id = ?");
    $check_strands->bind_param("i", $track_id);
    $check_strands->execute();
    $strand_count = $check_strands->get_result()->fetch_assoc()['count'];

    if ($strand_count > 0) {
        echo json_encode(['status' => 'error', 'message' => "Cannot delete track with $strand_count associated strands. Delete strands first."]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM shs_tracks WHERE id = ?");
    $stmt->bind_param("i", $track_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Track deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete track']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
