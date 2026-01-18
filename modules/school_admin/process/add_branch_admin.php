<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

// Enable mysqli exception reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$full_name = clean_input($_POST['full_name'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$branch_id = (int)($_POST['branch_id'] ?? 0);

if (empty($full_name) || empty($email) || empty($password) || $branch_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
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

    // Ensure branch is not already assigned to another branch admin
    $branch_admin_role = ROLE_BRANCH_ADMIN;
    $assigned_check = $conn->prepare("
        SELECT up.user_id
        FROM user_profiles up
        INNER JOIN user_roles ur ON up.user_id = ur.user_id
        WHERE ur.role_id = ? AND up.branch_id = ?
    ");
    $assigned_check->bind_param("ii", $branch_admin_role, $branch_id);
    $assigned_check->execute();
    if ($assigned_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This branch already has a branch administrator assigned']);
        exit();
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit();
    }

    // Hash the provided password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (email, password, status) VALUES (?, ?, 'active')");
        if (!$stmt) {
            throw new Exception("Prepare user insert failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $email, $password_hash);
        if (!$stmt->execute()) {
            throw new Exception("User insert failed: " . $stmt->error);
        }
        $user_id = $conn->insert_id;

        // Insert user profile
        $name_parts = explode(' ', $full_name, 2);
        $first_name = $name_parts[0];
        $last_name = $name_parts[1] ?? $first_name; // Use first_name if no last_name

        $profile = $conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, branch_id) VALUES (?, ?, ?, ?)");
        if (!$profile) {
            throw new Exception("Prepare profile insert failed: " . $conn->error);
        }
        $profile->bind_param("issi", $user_id, $first_name, $last_name, $branch_id);
        if (!$profile->execute()) {
            throw new Exception("Profile insert failed: " . $profile->error);
        }

        // Assign role
        $role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        if (!$role) {
            throw new Exception("Prepare role insert failed: " . $conn->error);
        }
        $role_id = ROLE_BRANCH_ADMIN;
        $role->bind_param("ii", $user_id, $role_id);
        if (!$role->execute()) {
            throw new Exception("Role insert failed: " . $role->error);
        }

        // Commit transaction
        $conn->commit();

        // Log action
        $ip = get_client_ip();
        $action = "Created branch administrator: $full_name ($email)";
        $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
        $audit->execute();

        echo json_encode(['status' => 'success', 'message' => 'Branch administrator created successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
