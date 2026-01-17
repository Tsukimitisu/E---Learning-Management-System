<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$class_id = (int)($_POST['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid class ID']);
    exit();
}

// Verify class
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Check if midterm or final grading periods are locked
$check_lock = $conn->prepare("
    SELECT grading_period, is_locked, locked_by
    FROM grade_locks
    WHERE class_id = ? AND grading_period IN ('midterm', 'final') AND is_locked = 1
");
$check_lock->bind_param("i", $class_id);
$check_lock->execute();
$locked_periods = $check_lock->get_result();

if ($locked_periods->num_rows > 0) {
    $locked_list = [];
    while ($row = $locked_periods->fetch_assoc()) {
        $locked_list[] = ucfirst($row['grading_period']);
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Cannot import grades. The following grading periods are locked: ' . implode(', ', $locked_list)
    ]);
    exit();
}

// Check file upload
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['excel_file'];
$allowed = ['csv', 'xls', 'xlsx'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Please upload CSV or Excel file']);
    exit();
}

try {
    $conn->begin_transaction();
    
    $imported = 0;
    $errors = [];
    
    if ($extension == 'csv') {
        // Process CSV
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            // Skip header row
            fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 5) continue; // Skip invalid rows
                
                $student_no = trim($data[0]);
                $midterm = floatval($data[2]);
                $final = floatval($data[3]);
                
                // Get student ID
                $student_query = $conn->prepare("SELECT user_id FROM students WHERE student_no = ?");
                $student_query->bind_param("s", $student_no);
                $student_query->execute();
                $student_result = $student_query->get_result();
                
                if ($student_result->num_rows == 0) {
                    $errors[] = "Student $student_no not found";
                    continue;
                }
                
                $student_id = $student_result->fetch_assoc()['user_id'];
                
                // Calculate final grade
                $final_grade = ($midterm * 0.4) + ($final * 0.6);
                $remarks = $final_grade >= 75 ? 'PASSED' : 'FAILED';
                
                // Check if grade exists and lock for update
                $check = $conn->prepare("SELECT id, version FROM grades WHERE student_id = ? AND class_id = ? FOR UPDATE");
                $check->bind_param("ii", $student_id, $class_id);
                $check->execute();
                $grade_result = $check->get_result();
                $exists = $grade_result->num_rows > 0;

                if ($exists) {
                    $current_grade = $grade_result->fetch_assoc();
                    // Update with version increment
                    $stmt = $conn->prepare("
                        UPDATE grades
                        SET midterm = ?, final = ?, final_grade = ?, remarks = ?, version = version + 1
                        WHERE student_id = ? AND class_id = ?
                    ");
                    $stmt->bind_param("dddsii", $midterm, $final, $final_grade, $remarks, $student_id, $class_id);
                } else {
                    // Insert
                    $stmt = $conn->prepare("
                        INSERT INTO grades (student_id, class_id, midterm, final, final_grade, remarks, version)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->bind_param("iiddds", $student_id, $class_id, $midterm, $final, $final_grade, $remarks);
                }
                
                $stmt->execute();
                $imported++;
            }
            fclose($handle);
        }
    }
    
    // Log audit
    $ip = get_client_ip();
    $action = "Imported $imported grades for class ID $class_id";
    $audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $audit->bind_param("iss", $teacher_id, $action, $ip);
    $audit->execute();
    
    $conn->commit();
    
    $message = "Successfully imported $imported grades";
    if (!empty($errors)) {
        $message .= ". Errors: " . implode(", ", array_slice($errors, 0, 5));
    }
    
    echo json_encode(['status' => 'success', 'message' => $message, 'imported' => $imported]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Import failed: ' . $e->getMessage()]);
}
?>