<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$admin_id = (int)($_GET['admin_id'] ?? 0);
if ($admin_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid administrator ID']);
    exit();
}

$role_branch_admin = ROLE_BRANCH_ADMIN;
$stmt = $conn->prepare("
    SELECT u.id, u.email,
           CONCAT(up.first_name, ' ', up.last_name) as full_name,
           up.branch_id
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE u.id = ? AND ur.role_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $admin_id, $role_branch_admin);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Branch administrator not found']);
    exit();
}

$admin = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'admin' => [
        'id' => (int)$admin['id'],
        'email' => $admin['email'],
        'full_name' => $admin['full_name'],
        'branch_id' => $admin['branch_id'] !== null ? (int)$admin['branch_id'] : null
    ]
]);
?>