<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$program_code = strtoupper(clean_input($_POST['program_code'] ?? ''));
$program_name = clean_input($_POST['program_name'] ?? '');
$degree_level = clean_input($_POST['degree_level'] ?? '');
$school_id = (int)($_POST['school_id'] ?? 0);

if (empty($program_code) || empty($program_name) || empty($degree_level) || $school_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    // Check if program code already exists
    $check = $conn->prepare("SELECT id FROM programs WHERE program_code = ?");
    $check->bind_param("s", $program_code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Program code already exists']);
        exit();
    }
    
    $stmt = $conn->prepare("
        INSERT INTO programs (program_code, program_name, degree_level, school_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("sssi", $program_code, $program_name, $degree_level, $school_id);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created program: $program_code - $program_name";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Program created successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create program']);
}
?>