<?php
require_once 'config/init.php';

// Log audit before destroying session
if (isset($_SESSION['user_id'])) {
    $ip_address = get_client_ip();
    $action = "User logged out";
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $_SESSION['user_id'], $action, $ip_address);
    $stmt->execute();
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: index.php');
exit();
?>