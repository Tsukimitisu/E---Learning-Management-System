<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$id = (int)($_GET['id'] ?? 0);

if ($id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
    exit();
}

try {
    $query = $conn->prepare("SELECT * FROM programs WHERE id = ?");
    $query->bind_param("i", $id);
    $query->execute();
    $result = $query->get_result();
    
    if ($program = $result->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'program' => $program]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Program not found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
