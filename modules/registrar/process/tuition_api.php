<?php
require_once '../../../config/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [ROLE_REGISTRAR, ROLE_SCHOOL_ADMIN, ROLE_SUPER_ADMIN])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'add':
        addTuitionFee();
        break;
    case 'get':
        getTuitionFee();
        break;
    case 'update':
        updateTuitionFee();
        break;
    case 'delete':
        deleteTuitionFee();
        break;
    case 'get_by_program':
        getTuitionByProgram();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function addTuitionFee() {
    global $conn;
    
    $program_id = (int)$_POST['program_id'];
    $program_type = ($_POST['program_type'] ?? 'college') === 'shs' ? 'shs' : 'college';
    $year_level_id = !empty($_POST['year_level_id']) ? (int)$_POST['year_level_id'] : null;
    $semester = $conn->real_escape_string($_POST['semester']);
    $tuition_fee = floatval($_POST['tuition_fee']);
    $misc_fee = floatval($_POST['misc_fee'] ?? 0);
    $lab_fee = floatval($_POST['lab_fee'] ?? 0);
    $other_fees = floatval($_POST['other_fees'] ?? 0);
    
    // Get current academic year
    $ay_result = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
    $ay_row = $ay_result->fetch_assoc();
    $academic_year_id = $ay_row ? (int)$ay_row['id'] : null;
    
    // Check for duplicate
    $check_sql = "SELECT id FROM program_tuition_fees WHERE program_id = ? AND program_type = ? AND semester = ?";
    $params = [$program_id, $program_type, $semester];
    $types = "iss";
    
    if ($year_level_id) {
        $check_sql .= " AND year_level_id = ?";
        $params[] = $year_level_id;
        $types .= "i";
    } else {
        $check_sql .= " AND year_level_id IS NULL";
    }
    
    $check_sql .= " AND is_active = 1";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Tuition fee for this program/year/semester already exists']);
        return;
    }
    
    // Insert with all fee fields
    $sql = "INSERT INTO program_tuition_fees (program_id, program_type, year_level_id, semester, tuition_fee, misc_fee, lab_fee, other_fees, academic_year_id, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isisddddi", $program_id, $program_type, $year_level_id, $semester, $tuition_fee, $misc_fee, $lab_fee, $other_fees, $academic_year_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tuition fee added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add tuition fee: ' . $conn->error]);
    }
}

function getTuitionFee() {
    global $conn;
    
    $id = (int)$_GET['id'];
    $sql = "SELECT * FROM program_tuition_fees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'tuition' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tuition fee not found']);
    }
}

function updateTuitionFee() {
    global $conn;
    
    $id = (int)$_POST['id'];
    $program_id = (int)$_POST['program_id'];
    $program_type = ($_POST['program_type'] ?? 'college') === 'shs' ? 'shs' : 'college';
    $year_level_id = !empty($_POST['year_level_id']) ? (int)$_POST['year_level_id'] : null;
    $semester = $conn->real_escape_string($_POST['semester']);
    $tuition_fee = floatval($_POST['tuition_fee']);
    $misc_fee = floatval($_POST['misc_fee'] ?? 0);
    $lab_fee = floatval($_POST['lab_fee'] ?? 0);
    $other_fees = floatval($_POST['other_fees'] ?? 0);
    
    // Check for duplicate (excluding current record)
    $check_sql = "SELECT id FROM program_tuition_fees WHERE program_id = ? AND program_type = ? AND semester = ? AND id != ?";
    $params = [$program_id, $program_type, $semester, $id];
    $types = "issi";
    
    if ($year_level_id) {
        $check_sql .= " AND year_level_id = ?";
        $params[] = $year_level_id;
        $types .= "i";
    } else {
        $check_sql .= " AND year_level_id IS NULL";
    }
    
    $check_sql .= " AND is_active = 1";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Tuition fee for this program/year/semester already exists']);
        return;
    }
    
    // Update with all fee fields
    $sql = "UPDATE program_tuition_fees SET program_id = ?, program_type = ?, year_level_id = ?, semester = ?, tuition_fee = ?, misc_fee = ?, lab_fee = ?, other_fees = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isisddddi", $program_id, $program_type, $year_level_id, $semester, $tuition_fee, $misc_fee, $lab_fee, $other_fees, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tuition fee updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update tuition fee: ' . $conn->error]);
    }
}

function deleteTuitionFee() {
    global $conn;
    
    $id = (int)$_POST['id'];
    
    // Soft delete
    $sql = "UPDATE program_tuition_fees SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tuition fee deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete tuition fee']);
    }
}

function getTuitionByProgram() {
    global $conn;
    
    $program_id = (int)$_GET['program_id'];
    $year_level_id = !empty($_GET['year_level_id']) ? (int)$_GET['year_level_id'] : null;
    $semester = $conn->real_escape_string($_GET['semester'] ?? '1st');
    
    // Try to get specific year level tuition first
    $sql = "SELECT * FROM program_tuition_fees WHERE program_id = ? AND semester = ? AND is_active = 1";
    
    if ($year_level_id) {
        // Try specific year level first
        $sql_specific = $sql . " AND year_level_id = ?";
        $stmt = $conn->prepare($sql_specific);
        $stmt->bind_param("isi", $program_id, $semester, $year_level_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => true, 'tuition' => $result->fetch_assoc()]);
            return;
        }
    }
    
    // If not found, try general program tuition (NULL year level)
    $sql_general = $sql . " AND year_level_id IS NULL";
    $stmt = $conn->prepare($sql_general);
    $stmt->bind_param("is", $program_id, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'tuition' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No tuition fee configured for this program']);
    }
}
?>
