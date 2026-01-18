<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $track_name = clean_input($_POST['track_name']);
    $track_code = clean_input($_POST['track_code'] ?? '');
    $written_weight = (float)($_POST['written_work_weight'] ?? 30);
    $performance_weight = (float)($_POST['performance_task_weight'] ?? 50);
    $quarterly_weight = (float)($_POST['quarterly_exam_weight'] ?? 20);
    $description = clean_input($_POST['description'] ?? '');

    // Validate weights sum to 100
    $total_weight = $written_weight + $performance_weight + $quarterly_weight;
    if ($total_weight != 100) {
        echo json_encode(['status' => 'error', 'message' => 'Weights must sum to 100%']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO shs_tracks (track_name, track_code, written_work_weight, performance_task_weight, quarterly_exam_weight, description, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("ssddds", $track_name, $track_code, $written_weight, $performance_weight, $quarterly_weight, $description);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Track added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add track']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
