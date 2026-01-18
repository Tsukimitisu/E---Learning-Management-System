<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $strand_id = (int)($_POST['strand_id'] ?? 0);
    $grade_level = (int)$_POST['grade_level'];
    $grade_name = clean_input($_POST['grade_name']);
    $semesters = (int)($_POST['semesters'] ?? 2);

    if (!$strand_id) {
        echo json_encode(['status' => 'error', 'message' => 'Strand ID is required']);
        exit();
    }

    // Check if grade level already exists for this strand
    $check = $conn->prepare("SELECT id FROM shs_grade_levels WHERE strand_id = ? AND grade_level = ?");
    $check->bind_param("ii", $strand_id, $grade_level);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Grade level already exists for this strand']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO shs_grade_levels (strand_id, grade_level, grade_name, semesters_count, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("iisi", $strand_id, $grade_level, $grade_name, $semesters);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Grade level added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add grade level']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
