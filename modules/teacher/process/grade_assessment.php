<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$assessment_id = (int)($input['assessment_id'] ?? 0);
$grades = $input['grades'] ?? [];

if ($assessment_id <= 0 || empty($grades)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// Verify assessment belongs to this teacher
$check = $conn->prepare("SELECT id, max_score FROM assessments WHERE id = ? AND created_by = ?");
$check->bind_param("ii", $assessment_id, $_SESSION['user_id']);
$check->execute();
$assessment = $check->get_result()->fetch_assoc();

if (!$assessment) {
    echo json_encode(['status' => 'error', 'message' => 'Assessment not found']);
    exit();
}

$max_score = $assessment['max_score'];
$graded_count = 0;

try {
    $conn->begin_transaction();
    
    foreach ($grades as $grade) {
        $student_id = (int)($grade['student_id'] ?? 0);
        $score = floatval($grade['score'] ?? 0);
        $feedback = clean_input($grade['feedback'] ?? '');
        
        if ($student_id <= 0 || $score < 0 || $score > $max_score) continue;
        
        // Check if score record exists
        $exists = $conn->prepare("SELECT id FROM assessment_scores WHERE assessment_id = ? AND student_id = ?");
        $exists->bind_param("ii", $assessment_id, $student_id);
        $exists->execute();
        $existing = $exists->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing score
            $update = $conn->prepare("UPDATE assessment_scores SET score = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE id = ?");
            $update->bind_param("dsi", $score, $feedback, $existing['id']);
            $update->execute();
        } else {
            // Insert new score
            $insert = $conn->prepare("INSERT INTO assessment_scores (assessment_id, student_id, score, feedback, status, submitted_at, graded_at) VALUES (?, ?, ?, ?, 'graded', NOW(), NOW())");
            $insert->bind_param("iids", $assessment_id, $student_id, $score, $feedback);
            $insert->execute();
        }
        $graded_count++;
    }
    
    $conn->commit();
    
    // Audit log
    $ip = get_client_ip();
    $action = "Graded $graded_count students for assessment #$assessment_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => "Grades saved successfully", 'graded_count' => $graded_count]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to save grades: ' . $e->getMessage()]);
}
?>
