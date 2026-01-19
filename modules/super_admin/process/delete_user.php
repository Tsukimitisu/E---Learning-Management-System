<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SUPER_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$user_id = (int)($_POST['user_id'] ?? 0);

if ($user_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit();
}

// Prevent deleting own account
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own account']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id, email FROM users WHERE id = ?");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }
    
    $user = $checkResult->fetch_assoc();
    
    // Delete from user_roles
    $roleStmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $roleStmt->bind_param("i", $user_id);
    $roleStmt->execute();
    
    // Delete from user_profiles
    $profileStmt = $conn->prepare("DELETE FROM user_profiles WHERE user_id = ?");
    $profileStmt->bind_param("i", $user_id);
    $profileStmt->execute();
    
    // Delete any password reset tokens
    $resetStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $resetStmt->bind_param("i", $user_id);
    $resetStmt->execute();
    
    // Delete any login attempts (uses email, not user_id)
    $attemptStmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $attemptStmt->bind_param("s", $user['email']);
    $attemptStmt->execute();
    
    // Delete any oauth tokens
    $oauthStmt = $conn->prepare("DELETE FROM oauth_tokens WHERE user_id = ?");
    $oauthStmt->bind_param("i", $user_id);
    $oauthStmt->execute();
    
    // Finally delete the user
    $userStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $userStmt->bind_param("i", $user_id);
    
    if (!$userStmt->execute()) {
        throw new Exception("Failed to delete user");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User "' . htmlspecialchars($user['email']) . '" has been deleted successfully!'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
