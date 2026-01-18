<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

// Check if user is branch admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$registrar_id = (int)($_POST['registrar_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$registrar_id || !in_array($action, ['activate', 'deactivate'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Get branch admin's branch
$admin_branch_query = $conn->prepare("
    SELECT branch_id FROM user_profiles WHERE user_id = ?
");
$admin_branch_query->bind_param("i", $_SESSION['user_id']);
$admin_branch_query->execute();
$admin_branch_result = $admin_branch_query->get_result();
$admin_data = $admin_branch_result->fetch_assoc();

if (!$admin_data || !$admin_data['branch_id']) {
    echo json_encode(['success' => false, 'message' => 'Branch not found for admin']);
    exit;
}

$branch_id = $admin_data['branch_id'];

// Verify registrar belongs to this branch
$verify_stmt = $conn->prepare("
    SELECT u.id FROM users u
    JOIN user_profiles up ON u.id = up.user_id
    JOIN user_roles ur ON u.id = ur.user_id
    WHERE u.id = ? AND ur.role_id = ? AND up.branch_id = ?
");
$role_registrar = ROLE_REGISTRAR;
$verify_stmt->bind_param("iii", $registrar_id, $role_registrar, $branch_id);
$verify_stmt->execute();

if ($verify_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Registrar not found or does not belong to your branch']);
    exit;
}

// Update status
$new_status = $action === 'activate' ? 'active' : 'inactive';
$update_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_status, $registrar_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Registrar account ' . ($action === 'activate' ? 'activated' : 'deactivated') . ' successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating status']);
}
?>
