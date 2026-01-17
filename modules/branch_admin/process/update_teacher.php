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
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $first_name = clean_input($_POST['first_name'] ?? '');
    $last_name = clean_input($_POST['last_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $address = clean_input($_POST['address'] ?? '');
    $status = clean_input($_POST['status'] ?? 'active');

    // Validation
    if (empty($teacher_id) || empty($first_name) || empty($last_name) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit();
    }

    // Check if email already exists for another user
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_email->bind_param("si", $email, $teacher_id);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    // Update user
    $update_user = $conn->prepare("
        UPDATE users
        SET first_name = ?, last_name = ?, email = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $update_user->bind_param("ssssi", $first_name, $last_name, $email, $status, $teacher_id);

    if (!$update_user->execute()) {
        throw new Exception('Failed to update user account');
    }

    // Update user profile
    $update_profile = $conn->prepare("
        UPDATE user_profiles
        SET first_name = ?, last_name = ?, address = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $update_profile->bind_param("sssi", $first_name, $last_name, $address, $teacher_id);

    if (!$update_profile->execute()) {
        throw new Exception('Failed to update user profile');
    }

    // Log the action
    $ip = get_client_ip();
    $action = "Updated teacher account for $first_name $last_name ($email)";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Teacher account updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>