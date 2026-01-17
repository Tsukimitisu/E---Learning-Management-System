<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $section_name = clean_input($_POST['section_name'] ?? '');
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $room = clean_input($_POST['room'] ?? '');
    $max_capacity = (int)($_POST['max_capacity'] ?? 35);
    $schedule = clean_input($_POST['schedule'] ?? '');
    $branch_id = (int)($_POST['branch_id'] ?? 1);

    // Validation
    if (empty($academic_year_id) || empty($subject_id) || empty($section_name) ||
        empty($teacher_id) || empty($room) || empty($schedule)) {
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
        exit();
    }

    // Check if section name already exists for this subject and academic year
    $check_section = $conn->prepare("
        SELECT id FROM classes
        WHERE subject_id = ? AND academic_year_id = ? AND section_name = ? AND branch_id = ?
    ");
    $check_section->bind_param("iiis", $subject_id, $academic_year_id, $section_name, $branch_id);
    $check_section->execute();
    if ($check_section->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'A section with this name already exists for the selected subject and academic year']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    // Insert new class/section
    $insert_class = $conn->prepare("
        INSERT INTO classes (
            academic_year_id, subject_id, section_name, teacher_id,
            room, schedule, max_capacity, current_enrolled, branch_id,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
    ");
    $insert_class->bind_param("iisisiiis",
        $academic_year_id, $subject_id, $section_name, $teacher_id,
        $room, $schedule, $max_capacity, $branch_id
    );

    if (!$insert_class->execute()) {
        throw new Exception('Failed to create section');
    }

    $class_id = $conn->insert_id;

    // Log the action
    $ip = get_client_ip();
    $action = "Created new section '$section_name' for subject ID $subject_id, assigned to teacher ID $teacher_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Section created successfully and assigned to teacher. Students can now be enrolled.',
        'class_id' => $class_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>