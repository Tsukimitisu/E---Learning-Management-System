<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$subject_id = (int)($_POST['subject_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($subject_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid subject ID']);
    exit();
}

// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check file upload
if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] != 0) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['material_file'];
$allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
$max_size = 10 * 1024 * 1024; // 10MB

// Validate size
if ($file['size'] > $max_size) {
    echo json_encode(['status' => 'error', 'message' => 'File exceeds 10MB limit']);
    exit();
}

// Validate extension
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
    exit();
}

// Create upload directory
$upload_dir = UPLOAD_DIR . 'materials/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$new_filename = 'material_subj' . $subject_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Check for duplicate content using file hash
$file_hash = md5_file($file['tmp_name']);
$duplicate_check = $conn->prepare("
    SELECT lm.id, lm.file_path 
    FROM learning_materials lm
    WHERE lm.subject_id = ?
");
$duplicate_check->bind_param("i", $subject_id);
$duplicate_check->execute();
$existing_files = $duplicate_check->get_result();

while ($existing = $existing_files->fetch_assoc()) {
    $existing_path = $upload_dir . basename($existing['file_path']);
    if (file_exists($existing_path)) {
        $existing_hash = md5_file($existing_path);
        if ($existing_hash === $file_hash) {
            echo json_encode(['status' => 'error', 'message' => 'This file already exists for this subject']);
            exit();
        }
    }
}

// Move file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    exit();
}

try {
    // Insert to database - materials are per subject, section_id and class_id are NULL
    $stmt = $conn->prepare("INSERT INTO learning_materials (class_id, section_id, subject_id, file_path, uploaded_by) VALUES (NULL, NULL, ?, ?, ?)");
    $file_path = 'materials/' . $new_filename;
    $stmt->bind_param("isi", $subject_id, $file_path, $teacher_id);
    $stmt->execute();
    
    // Log audit
    $ip = get_client_ip();
    $action = "Uploaded material: $new_filename for subject ID $subject_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $teacher_id, $action, $ip);
    $audit->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Material uploaded successfully']);
} catch (Exception $e) {
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    echo json_encode(['status' => 'error', 'message' => 'Failed to save record']);
}
?>