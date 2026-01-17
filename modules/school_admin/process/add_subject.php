<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Forward the request to the curriculum API
$_GET['action'] = 'add_subject';

// Include the curriculum API to handle the request
require_once '../../../api/curriculum.php';
?>