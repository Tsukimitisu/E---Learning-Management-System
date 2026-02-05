<?php
/**
 * Branch Management API - Add, edit, delete branches (School Admin only)
 * Moved from super_admin to school_admin
 * Concurrency: Uses transactions and proper validation
 */

// Ensure JSON content-type and capture unexpected output/errors
header('Content-Type: application/json');
ob_start();
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err) {
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error', 'error' => $err]);
    } else {
        if (ob_get_length()) ob_end_flush();
    }
});

require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - School Admin only']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$branch_id = (int)($_POST['branch_id'] ?? $_GET['branch_id'] ?? $_POST['branch-id'] ?? $_POST['id'] ?? 0);

// Helper to normalize incoming field names (compatibility)
function get_post($keys, $default = '') {
    foreach ((array)$keys as $k) {
        if (isset($_POST[$k])) return $_POST[$k];
        if (isset($_REQUEST[$k])) return $_REQUEST[$k];
    }
    return $default;
}

try {
    if ($action === 'list') {
        $sql = "SELECT id, name, address FROM branches ORDER BY name";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'SQL prepare failed', 'error' => $conn->error]);
            exit();
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $branches = [];
        while ($row = $result->fetch_assoc()) $branches[] = $row;
        echo json_encode(['success' => true, 'status' => 'success', 'branches' => $branches]);

    } elseif ($action === 'create') {
        $name = clean_input(get_post(['name','branch_name','branchName']) ?? '');
        $address = clean_input(get_post(['address','branch_address','branchAddress']) ?? '');
        $school_id = 1; // Default to Datamex, or fetch from session if multi-school
        if (isset($_SESSION['school_id'])) {
            $school_id = (int)$_SESSION['school_id'];
        }

        if (empty($name)) { echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Branch name is required']); exit(); }

        $conn->begin_transaction();
        try {
            $check_stmt = $conn->prepare("SELECT id FROM branches WHERE LOWER(name) = LOWER(?) AND school_id = ?");
            $check_stmt->bind_param("si", $name, $school_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'Branch name already exists']); exit(); }

            $insert_stmt = $conn->prepare("INSERT INTO branches (school_id, name, address) VALUES (?, ?, ?)");
            if (!$insert_stmt) {
                echo json_encode(['success' => false, 'message' => 'SQL prepare failed', 'error' => $conn->error]);
                exit();
            }
            $insert_stmt->bind_param("iss", $school_id, $name, $address);
            $insert_stmt->execute();
            $new_branch_id = $conn->insert_id;

            $ip = get_client_ip();
            $action_log = "Created branch: $name";
            try { log_audit($conn, $_SESSION['user_id'], $action_log, null, $ip); } catch (Exception $e) { }

            $conn->commit();
            echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Branch created successfully', 'branch_id' => $new_branch_id]);
        } catch (Exception $e) { $conn->rollback(); throw $e; }

    } elseif ($action === 'update') {
        if ($branch_id <= 0) { echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid branch ID']); exit(); }
        $name = clean_input(get_post(['name','branch_name','branchName']) ?? '');
        $address = clean_input(get_post(['address','branch_address','branchAddress']) ?? '');
        if (empty($name)) { echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Branch name is required']); exit(); }

        $conn->begin_transaction();
        try {
            $check_stmt = $conn->prepare("SELECT id FROM branches WHERE id = ?");
            $check_stmt->bind_param("i", $branch_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows === 0) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'Branch not found']); exit(); }

            $dup_stmt = $conn->prepare("SELECT id FROM branches WHERE LOWER(name) = LOWER(?) AND id != ?");
            $dup_stmt->bind_param("si", $name, $branch_id);
            $dup_stmt->execute();
            if ($dup_stmt->get_result()->num_rows > 0) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'Branch name already exists']); exit(); }

            $update_stmt = $conn->prepare("UPDATE branches SET name = ?, address = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $name, $address, $branch_id);
            $update_stmt->execute();

            // Optionally handle activate/deactivate if status is provided (only if column exists)
            // $status = get_post(['status', 'branch_status', 'active']);
            // if ($status !== '') {
            //     $active = ($status == '1' || $status === true || strtolower($status) === 'active') ? 1 : 0;
            //     $status_stmt = $conn->prepare("UPDATE branches SET active = ? WHERE id = ?");
            //     $status_stmt->bind_param("ii", $active, $branch_id);
            //     $status_stmt->execute();
            // }

            $ip = get_client_ip();
            $action_log = "Updated branch: $name (ID: $branch_id)";
            try { log_audit($conn, $_SESSION['user_id'], $action_log, null, $ip); } catch (Exception $e) { }

            $conn->commit();
            echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Branch updated successfully']);
        } catch (Exception $e) { $conn->rollback(); throw $e; }

    } elseif ($action === 'delete') {
        if ($branch_id <= 0) { echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid branch ID']); exit(); }

        $conn->begin_transaction();
        try {
            $staff_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_profiles WHERE branch_id = ?");
            $staff_stmt->bind_param("i", $branch_id);
            $staff_stmt->execute();
            $staff_result = $staff_stmt->get_result()->fetch_assoc();
            if ($staff_result['count'] > 0) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'Cannot delete branch with assigned staff. Reassign them first.']); exit(); }

            $name_stmt = $conn->prepare("SELECT name FROM branches WHERE id = ?");
            $name_stmt->bind_param("i", $branch_id);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result()->fetch_assoc();
            $branch_name = $name_result['name'] ?? 'Unknown';

            $delete_stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
            $delete_stmt->bind_param("i", $branch_id);
            $delete_stmt->execute();
            if ($delete_stmt->affected_rows === 0) { $conn->rollback(); echo json_encode(['success' => false, 'message' => 'Branch not found']); exit(); }

            $ip = get_client_ip();
            $action_log = "Deleted branch: $branch_name (ID: $branch_id)";
            try { log_audit($conn, $_SESSION['user_id'], $action_log, null, $ip); } catch (Exception $e) { }

            $conn->commit();
            echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Branch deleted successfully']);
        } catch (Exception $e) { $conn->rollback(); throw $e; }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
