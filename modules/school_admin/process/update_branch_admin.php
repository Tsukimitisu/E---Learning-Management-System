<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$user_id = (int)($_POST['user_id'] ?? 0);
$branch_id = (int)($_POST['branch_id'] ?? 0);

if ($user_id <= 0 || $branch_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    // Validate branch exists
    $branch_check = $conn->prepare("SELECT id FROM branches WHERE id = ?");
    $branch_check->bind_param("i", $branch_id);
    $branch_check->execute();
    if ($branch_check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Selected branch does not exist']);
        exit();
    }

    $role_branch_admin = ROLE_BRANCH_ADMIN;
    
    // Validate user is a branch admin
    $role_check = $conn->prepare("SELECT user_id FROM user_roles WHERE user_id = ? AND role_id = ?");
    $role_check->bind_param("ii", $user_id, $role_branch_admin);
    $role_check->execute();
    if ($role_check->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User is not a branch administrator']);
        exit();
    }

    // Ensure branch is not already assigned to another branch admin
    $assigned_check = $conn->prepare("
        SELECT up.user_id
        FROM user_profiles up
        INNER JOIN user_roles ur ON up.user_id = ur.user_id
        WHERE ur.role_id = ? AND up.branch_id = ? AND up.user_id <> ?
    ");
    $assigned_check->bind_param("iii", $role_branch_admin, $branch_id, $user_id);
    $assigned_check->execute();
    if ($assigned_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This branch already has a branch administrator assigned']);
        exit();
    }

    $conn->begin_transaction();

    $update = $conn->prepare("UPDATE user_profiles SET branch_id = ? WHERE user_id = ?");
    $update->bind_param("ii", $branch_id, $user_id);
    $update->execute();

    $ip = get_client_ip();
    $action = "Updated branch admin assignment for user ID $user_id to branch ID $branch_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Branch administrator updated successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>