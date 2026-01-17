<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
$curriculum_subject_id = (int)($_POST['curriculum_subject_id'] ?? 0);
$section_name = clean_input($_POST['section_name'] ?? '');
$teacher_id = (int)($_POST['teacher_id'] ?? 0);
$room = clean_input($_POST['room'] ?? '');
$max_capacity = (int)($_POST['max_capacity'] ?? 0);
$schedule = clean_input($_POST['schedule'] ?? '');
$branch_id = (int)($_POST['branch_id'] ?? 0);

if ($academic_year_id == 0 || $curriculum_subject_id == 0 || empty($section_name) ||
    $teacher_id == 0 || empty($room) || $max_capacity < 1 || empty($schedule) || $branch_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

// Validate that the subject is from approved curriculum
$subject_check = $conn->prepare("
    SELECT cs.id, cs.subject_title, cs.subject_type, cs.program_id, cs.shs_strand_id
    FROM curriculum_subjects cs
    WHERE cs.id = ? AND cs.is_active = 1
");
$subject_check->bind_param("i", $curriculum_subject_id);
$subject_check->execute();
$subject_result = $subject_check->get_result();

if ($subject_result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid subject. Only subjects from approved curriculum can be used.']);
    exit();
}

$subject_info = $subject_result->fetch_assoc();

// Determine course_id based on subject type
$course_id = null;
if ($subject_info['subject_type'] === 'college' && $subject_info['program_id']) {
    // For college subjects, we might need to get the course from context or use program as course
    $course_id = $subject_info['program_id']; // Using program_id as course_id for now
}

try {
    $conn->begin_transaction();
    
    // Insert class
    $stmt = $conn->prepare("
        INSERT INTO classes (
            academic_year_id, curriculum_subject_id, course_id, section_name, teacher_id,
            room, schedule, max_capacity, current_enrolled, branch_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
    ");
    $stmt->bind_param("iiissisii", $academic_year_id, $curriculum_subject_id, $course_id, $section_name,
                       $teacher_id, $room, $schedule, $max_capacity, $branch_id);
    $stmt->execute();
    
    $class_id = $conn->insert_id();
    
    // Log audit
    $ip = get_client_ip();
    $action = "Scheduled class: $section_name - Room $room - $schedule";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Class scheduled successfully',
        'class_id' => $class_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to schedule class: ' . $e->getMessage()]);
}
?>