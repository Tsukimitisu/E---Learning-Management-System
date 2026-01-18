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
    $student_id = (int)($_POST['student_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $branch_id = get_user_branch_id();

    if ($branch_id === null) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied: Branch assignment required']);
        exit();
    }

    if (empty($student_id) || empty($class_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Student ID and Class ID are required']);
        exit();
    }

    // Validate class belongs to branch
    $class_check = $conn->prepare("SELECT id FROM classes WHERE id = ? AND branch_id = ?");
    $class_check->bind_param("ii", $class_id, $branch_id);
    $class_check->execute();
    if ($class_check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied: This class belongs to a different branch']);
        exit();
    }

    // Validate student belongs to branch
    $student_check = $conn->prepare("\
        SELECT st.user_id
        FROM students st
        LEFT JOIN courses c ON st.course_id = c.id
        LEFT JOIN user_profiles up ON st.user_id = up.user_id
        WHERE st.user_id = ? AND (c.branch_id = ? OR up.branch_id = ?)
    ");
    $student_check->bind_param("iii", $student_id, $branch_id, $branch_id);
    $student_check->execute();
    if ($student_check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied: This student belongs to a different branch']);
        exit();
    }

    // Check if student is already enrolled in this class
    $check_enrollment = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
    $check_enrollment->bind_param("ii", $student_id, $class_id);
    $check_enrollment->execute();
    if ($check_enrollment->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Student is already enrolled in this class']);
        exit();
    }

    // Check class capacity
    $check_capacity = $conn->prepare("SELECT current_enrolled, max_capacity FROM classes WHERE id = ?");
    $check_capacity->bind_param("i", $class_id);
    $check_capacity->execute();
    $capacity_result = $check_capacity->get_result()->fetch_assoc();

    if ($capacity_result['current_enrolled'] >= $capacity_result['max_capacity']) {
        echo json_encode(['status' => 'error', 'message' => 'Class is at full capacity']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    // Insert enrollment
    $insert_enrollment = $conn->prepare("
        INSERT INTO enrollments (student_id, class_id, status, enrolled_by, created_at)
        VALUES (?, ?, 'approved', ?, NOW())
    ");
    $insert_enrollment->bind_param("iii", $student_id, $class_id, $_SESSION['user_id']);

    if (!$insert_enrollment->execute()) {
        throw new Exception('Failed to enroll student');
    }

    // Update class enrollment count
    $update_class = $conn->prepare("
        UPDATE classes
        SET current_enrolled = current_enrolled + 1, updated_at = NOW()
        WHERE id = ?
    ");
    $update_class->bind_param("i", $class_id);

    if (!$update_class->execute()) {
        throw new Exception('Failed to update class capacity');
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Enrolled student ID $student_id in class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Student successfully assigned to class'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>