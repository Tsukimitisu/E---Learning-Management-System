<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$user_id = (int)($_GET['id'] ?? 0);

if ($user_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit();
}

try {
    // Get user data with profile and role
    $query = "
        SELECT u.id, u.email, u.status,
               up.first_name, up.last_name, up.contact_no, up.address,
               ur.role_id
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch user data: ' . $e->getMessage()]);
}
?>
