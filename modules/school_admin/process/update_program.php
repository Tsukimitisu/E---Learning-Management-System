<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$program_id = (int)($_POST['program_id'] ?? 0);
$program_code = trim($_POST['program_code'] ?? '');
$program_name = trim($_POST['program_name'] ?? '');
$degree_level = trim($_POST['degree_level'] ?? '');
$school_id = (int)($_POST['school_id'] ?? 0);
$is_active = (int)($_POST['is_active'] ?? 1);

// If school_id missing, attempt to default to DATAMEX school entry
if ($school_id == 0) {
    $search = '%datamex%';
    $stmt_s = $conn->prepare("SELECT id FROM schools WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
    $stmt_s->bind_param('s', $search);
    $stmt_s->execute();
    $res_s = $stmt_s->get_result();
    if ($row_s = $res_s->fetch_assoc()) {
        $school_id = (int)$row_s['id'];
    }
}

// Validation

$action = $_POST['action'] ?? '';
// Only validate all fields for update action
if ($action === '' || $action === 'update') {
    if ($program_id == 0 || empty($program_code) || empty($program_name) || empty($degree_level) || $school_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit();
    }
}
if ($action === 'delete') {
    if ($program_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
        exit();
    }
    try {
        $stmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->bind_param("i", $program_id);
        if ($stmt->execute()) {
            send_realtime_update('curriculum_updated', [
                'action' => 'program_deleted',
                'program_id' => $program_id,
                'updated_by' => $_SESSION['user_id']
            ], 'school_admin');
            echo json_encode(['status' => 'success', 'message' => 'Program deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete program']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'toggle_status') {
    if ($program_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
        exit();
    }
    $new_status = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
    try {
        $stmt = $conn->prepare("UPDATE programs SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $program_id);
        if ($stmt->execute()) {
            send_realtime_update('curriculum_updated', [
                'action' => 'program_updated',
                'program_id' => $program_id,
                'updated_by' => $_SESSION['user_id']
            ], 'school_admin');
            echo json_encode(['status' => 'success', 'message' => $new_status ? 'Program activated successfully' : 'Program deactivated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update program status']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

try {
    // Check if program code already exists for another program
    $check = $conn->prepare("SELECT id FROM programs WHERE program_code = ? AND id != ?");
    $check->bind_param("si", $program_code, $program_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Program code already exists']);
        exit();
    }

    // Update the program
    $stmt = $conn->prepare("UPDATE programs SET program_code = ?, program_name = ?, degree_level = ?, school_id = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("sssiii", $program_code, $program_name, $degree_level, $school_id, $is_active, $program_id);
    
    if ($stmt->execute()) {
        send_realtime_update('curriculum_updated', [
            'action' => 'program_updated',
            'program_id' => $program_id,
            'updated_by' => $_SESSION['user_id']
        ], 'school_admin');
        echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update program']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
