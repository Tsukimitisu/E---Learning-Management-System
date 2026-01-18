<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$category = clean_input($_POST['category'] ?? '');
$title = clean_input($_POST['title'] ?? '');
$description = clean_input($_POST['description'] ?? '');
$enforcement = clean_input($_POST['enforcement'] ?? 'mandatory');

if (empty($category) || empty($title) || empty($description)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

try {
    // Check if policies table exists, create if not
    $conn->query("
        CREATE TABLE IF NOT EXISTS academic_policies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            enforcement ENUM('mandatory', 'recommended', 'optional') DEFAULT 'mandatory',
            is_active TINYINT(1) DEFAULT 1,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Insert policy
    $stmt = $conn->prepare("INSERT INTO academic_policies (category, title, description, enforcement, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $category, $title, $description, $enforcement, $_SESSION['user_id']);
    $stmt->execute();

    // Log action
    $ip = get_client_ip();
    $action = "Added academic policy: $title ($category)";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $_SESSION['user_id'], $action, $ip);
    $audit->execute();

    echo json_encode(['status' => 'success', 'message' => 'Policy added successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error adding policy: ' . $e->getMessage()]);
}
?>
