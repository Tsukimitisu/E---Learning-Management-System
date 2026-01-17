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
    $class_id = (int)($_POST['class_id'] ?? 0);
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);

    if (empty($class_id) || empty($student_ids) || !is_array($student_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
        exit();
    }

    // Check class capacity
    $check_capacity = $conn->prepare("SELECT current_enrolled, max_capacity FROM classes WHERE id = ?");
    $check_capacity->bind_param("i", $class_id);
    $check_capacity->execute();
    $capacity_result = $check_capacity->get_result()->fetch_assoc();

    if (!$capacity_result) {
        echo json_encode(['status' => 'error', 'message' => 'Class not found']);
        exit();
    }

    $current_enrolled = $capacity_result['current_enrolled'];
    $max_capacity = $capacity_result['max_capacity'];
    $available_slots = $max_capacity - $current_enrolled;

    if (count($student_ids) > $available_slots) {
        echo json_encode(['status' => 'error', 'message' => "Only $available_slots slots available in this class"]);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    $assigned_count = 0;
    $errors = [];

    foreach ($student_ids as $student_id) {
        $student_id = (int)$student_id;

        // Check if student is already enrolled
        $check_enrollment = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
        $check_enrollment->bind_param("ii", $student_id, $class_id);
        $check_enrollment->execute();

        if ($check_enrollment->get_result()->num_rows > 0) {
            $errors[] = "Student ID $student_id already enrolled";
            continue;
        }

        // Insert enrollment
        $insert_enrollment = $conn->prepare("
            INSERT INTO enrollments (student_id, class_id, status, enrolled_by, created_at)
            VALUES (?, ?, 'approved', ?, NOW())
        ");
        $insert_enrollment->bind_param("iii", $student_id, $class_id, $_SESSION['user_id']);

        if ($insert_enrollment->execute()) {
            $assigned_count++;
        } else {
            $errors[] = "Failed to enroll student ID $student_id";
        }
    }

    // Update class enrollment count
    if ($assigned_count > 0) {
        $update_class = $conn->prepare("
            UPDATE classes
            SET current_enrolled = current_enrolled + ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_class->bind_param("ii", $assigned_count, $class_id);

        if (!$update_class->execute()) {
            throw new Exception('Failed to update class capacity');
        }
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Bulk assigned $assigned_count students to class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    $message = "Successfully assigned $assigned_count student(s) to the class";
    if (!empty($errors)) {
        $message .= ". Some assignments failed: " . implode(', ', $errors);
    }

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'assigned_count' => $assigned_count
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>