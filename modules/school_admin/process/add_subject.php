<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$subject_code = strtoupper(clean_input($_POST['subject_code'] ?? ''));
$subject_title = clean_input($_POST['subject_title'] ?? '');
$units = (int)($_POST['units'] ?? 0);
$program_id = (int)($_POST['program_id'] ?? 0);
$year_level = (int)($_POST['year_level'] ?? 1);
$semester = (int)($_POST['semester'] ?? 1);

if (empty($subject_code) || empty($subject_title) || $units < 1 || $program_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO subjects (subject_code, subject_title, units, program_id, year_level, semester) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssiiii", $subject_code, $subject_title, $units, $program_id, $year_level, $semester);
    $stmt->execute();
    
    $ip = get_client_ip();
    $action = "Created subject: $subject_code - $subject_title";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Subject added successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add subject: ' . $e->getMessage()]);
}
?>