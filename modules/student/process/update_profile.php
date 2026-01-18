<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$student_id = $_SESSION['user_id'];
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$birth_date = trim($_POST['birth_date'] ?? '');

// Validation
if (empty($first_name) || empty($last_name)) {
    echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
    exit();
}

// Update user_profiles
$stmt = $conn->prepare("
    UPDATE user_profiles 
    SET first_name = ?, middle_name = ?, last_name = ?, 
        contact_number = ?, address = ?, birth_date = ?
    WHERE user_id = ?
");

$stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $contact_number, $address, $birth_date, $student_id);

if ($stmt->execute()) {
    // Update session name
    $_SESSION['name'] = $first_name . ' ' . $last_name;
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
