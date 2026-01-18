<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $program_code = strtoupper(clean_input($_POST['program_code']));
    $program_name = clean_input($_POST['program_name']);
    $degree_level = clean_input($_POST['degree_level']);
    $school_id = (int)$_POST['school_id'];
    $description = clean_input($_POST['description'] ?? '');

    // Check if program code already exists
    $check = $conn->prepare("SELECT id FROM programs WHERE program_code = ?");
    $check->bind_param("s", $program_code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Program code already exists']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO programs (program_code, program_name, degree_level, school_id, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("sssi", $program_code, $program_name, $degree_level, $school_id);
    
    if ($stmt->execute()) {
        $ip = get_client_ip();
        $action = "Created program: $program_code - $program_name";
        $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
        $audit->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Program added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add program']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
