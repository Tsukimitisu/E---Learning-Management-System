<?php
header('Content-Type: application/json');
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - School Admin only']);
    exit();
}

try {
    $name = clean_input($_POST['name'] ?? $_POST['branch_name'] ?? '');
    $address = clean_input($_POST['address'] ?? $_POST['branch_address'] ?? '');
    $contact = clean_input($_POST['branch_contact'] ?? $_POST['contact'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required']);
        exit();
    }

    // Use transaction
    $conn->begin_transaction();
    $check = $conn->prepare("SELECT id FROM branches WHERE LOWER(name) = LOWER(?)");
    $check->bind_param('s', $name);
    $check->execute();
    $res = $check->get_result();
    if ($res && $res->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Branch name already exists']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO branches (name, address, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    $stmt->bind_param('ss', $name, $address);
    $stmt->execute();
    $new_id = $conn->insert_id;

    // Log audit using helper
    $ip = get_client_ip();
    try { log_audit($conn, $_SESSION['user_id'], "Created branch: $name", null, $ip); } catch (Exception $e) { }

    $conn->commit();

    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Branch created successfully', 'branch_id' => $new_id]);
    exit();
} catch (Exception $e) {
    if ($conn && $conn->errno) $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
    exit();
}

?>