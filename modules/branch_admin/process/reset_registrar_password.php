<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

// Get branch admin's branch
$admin_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = " . $_SESSION['user_id'])->fetch_assoc();
$branch_id = $admin_profile['branch_id'] ?? 0;

// Verify this registrar belongs to this branch
$verify = $conn->query("
    SELECT u.id FROM users u 
    INNER JOIN user_profiles up ON u.id = up.user_id 
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE u.id = $user_id AND ur.role_id = " . ROLE_REGISTRAR . " AND up.branch_id = $branch_id
");

if ($verify->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Registrar not found or not in your branch']);
    exit();
}

// Reset password
$new_password = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $new_password, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
}
?>
