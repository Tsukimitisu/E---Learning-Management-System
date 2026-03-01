<?php
require_once '../../../config/init.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [ROLE_REGISTRAR, ROLE_SCHOOL_ADMIN, ROLE_SUPER_ADMIN])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    // Discounts
    case 'list_discounts':    listDiscounts();    break;
    case 'get_discount':      getDiscount();      break;
    case 'add_discount':      addDiscount();      break;
    case 'update_discount':   updateDiscount();   break;
    case 'delete_discount':   deleteDiscount();   break;
    // Penalties
    case 'list_penalties':    listPenalties();    break;
    case 'get_penalty':       getPenalty();        break;
    case 'add_penalty':       addPenalty();        break;
    case 'update_penalty':    updatePenalty();     break;
    case 'delete_penalty':    deletePenalty();     break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ============================================================
//  DISCOUNT FUNCTIONS
// ============================================================

function listDiscounts() {
    global $conn;
    $result = $conn->query("
        SELECT d.*, ay.year_name as academic_year_name
        FROM tuition_discounts d
        LEFT JOIN academic_years ay ON d.academic_year_id = ay.id
        WHERE d.is_active = 1
        ORDER BY d.created_at DESC
    ");
    $discounts = [];
    while ($row = $result->fetch_assoc()) {
        $discounts[] = $row;
    }
    echo json_encode(['success' => true, 'discounts' => $discounts]);
}

function getDiscount() {
    global $conn;
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM tuition_discounts WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        echo json_encode(['success' => true, 'discount' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Discount not found']);
    }
}

function addDiscount() {
    global $conn;
    $name = trim($_POST['name'] ?? '');
    $discount_type = ($_POST['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
    $value = (float)($_POST['value'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $created_by = (int)$_SESSION['user_id'];

    // Get current academic year
    $ay_result = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
    $ay_row = $ay_result->fetch_assoc();
    $academic_year_id = $ay_row ? (int)$ay_row['id'] : null;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Discount name is required']);
        return;
    }
    if ($value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Discount value must be greater than 0']);
        return;
    }
    if ($discount_type === 'percentage' && $value > 100) {
        echo json_encode(['success' => false, 'message' => 'Percentage discount cannot exceed 100%']);
        return;
    }
    if (empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
        return;
    }
    if ($end_date < $start_date) {
        echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO tuition_discounts (name, discount_type, value, start_date, end_date, academic_year_id, description, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->bind_param("ssdssisi", $name, $discount_type, $value, $start_date, $end_date, $academic_year_id, $description, $created_by);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Discount added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add discount: ' . $conn->error]);
    }
}

function updateDiscount() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $discount_type = ($_POST['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
    $value = (float)($_POST['value'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Discount name is required']);
        return;
    }
    if ($value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Discount value must be greater than 0']);
        return;
    }
    if ($discount_type === 'percentage' && $value > 100) {
        echo json_encode(['success' => false, 'message' => 'Percentage discount cannot exceed 100%']);
        return;
    }
    if (empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
        return;
    }
    if ($end_date < $start_date) {
        echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE tuition_discounts SET name = ?, discount_type = ?, value = ?, start_date = ?, end_date = ?, description = ?
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param("ssdsssi", $name, $discount_type, $value, $start_date, $end_date, $description, $id);

    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        echo json_encode(['success' => true, 'message' => 'Discount updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update discount: ' . $conn->error]);
    }
}

function deleteDiscount() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE tuition_discounts SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Discount deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete discount']);
    }
}

// ============================================================
//  PENALTY FUNCTIONS
// ============================================================

function listPenalties() {
    global $conn;
    $result = $conn->query("
        SELECT p.*, ay.year_name as academic_year_name
        FROM tuition_penalties p
        LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
        WHERE p.is_active = 1
        ORDER BY p.created_at DESC
    ");
    $penalties = [];
    while ($row = $result->fetch_assoc()) {
        $penalties[] = $row;
    }
    echo json_encode(['success' => true, 'penalties' => $penalties]);
}

function getPenalty() {
    global $conn;
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM tuition_penalties WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        echo json_encode(['success' => true, 'penalty' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Penalty not found']);
    }
}

function addPenalty() {
    global $conn;
    $name = trim($_POST['name'] ?? '');
    $penalty_type = ($_POST['penalty_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
    $value = (float)($_POST['value'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $created_by = (int)$_SESSION['user_id'];

    $ay_result = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
    $ay_row = $ay_result->fetch_assoc();
    $academic_year_id = $ay_row ? (int)$ay_row['id'] : null;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Penalty name is required']);
        return;
    }
    if ($value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Penalty value must be greater than 0']);
        return;
    }
    if (empty($start_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date is required']);
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO tuition_penalties (name, penalty_type, value, start_date, academic_year_id, description, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->bind_param("ssdsssi", $name, $penalty_type, $value, $start_date, $academic_year_id, $description, $created_by);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Penalty added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add penalty: ' . $conn->error]);
    }
}

function updatePenalty() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $penalty_type = ($_POST['penalty_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
    $value = (float)($_POST['value'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Penalty name is required']);
        return;
    }
    if ($value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Penalty value must be greater than 0']);
        return;
    }
    if (empty($start_date)) {
        echo json_encode(['success' => false, 'message' => 'Start date is required']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE tuition_penalties SET name = ?, penalty_type = ?, value = ?, start_date = ?, description = ?
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param("ssdssi", $name, $penalty_type, $value, $start_date, $description, $id);

    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        echo json_encode(['success' => true, 'message' => 'Penalty updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update penalty: ' . $conn->error]);
    }
}

function deletePenalty() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE tuition_penalties SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Penalty deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete penalty']);
    }
}
