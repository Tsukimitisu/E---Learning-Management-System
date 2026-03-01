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

$student_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$assessment_id = (int)($_POST['assessment_id'] ?? 0);

if ($assessment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assessment ID']);
    exit();
}

// Verify the assessment exists and belongs to a class the student is enrolled in
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = (int)($current_ay['id'] ?? 0);

$section_info = $conn->query("
    SELECT s.id, s.section_name, s.branch_id
    FROM section_students ss
    INNER JOIN sections s ON ss.section_id = s.id
    WHERE ss.student_id = $student_id AND ss.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

if (!$section_info) {
    echo json_encode(['success' => false, 'message' => 'You are not enrolled in any active section']);
    exit();
}

$section_name = $conn->real_escape_string($section_info['section_name']);
$branch_id = (int)$section_info['branch_id'];

// Check assessment exists for the student's class 
$assessment = $conn->query("
    SELECT a.* FROM assessments a
    INNER JOIN classes cl ON a.class_id = cl.id
    WHERE a.id = $assessment_id 
      AND cl.section_name = '$section_name'
      AND cl.branch_id = $branch_id
      AND cl.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

if (!$assessment) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found or you do not have access']);
    exit();
}

// Get or create existing score record
$existing = $conn->query("
    SELECT * FROM assessment_scores 
    WHERE assessment_id = $assessment_id AND student_id = $student_id
    LIMIT 1
")->fetch_assoc();

switch ($action) {
    case 'submit':
        handleSubmission($conn, $student_id, $assessment_id, $existing);
        break;
    case 'mark_done':
        handleMarkDone($conn, $student_id, $assessment_id, $existing);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Handle file upload and submission
 */
function handleSubmission($conn, $student_id, $assessment_id, $existing) {
    // Don't allow resubmission of graded assessments
    if ($existing && $existing['status'] === 'graded') {
        echo json_encode(['success' => false, 'message' => 'This assessment has already been graded and cannot be resubmitted']);
        return;
    }

    $student_notes = trim($_POST['student_notes'] ?? '');
    $submitted_file = null;

    // Handle file upload if provided
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        
        // Validate file size (max 10MB)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds the 10MB limit']);
            return;
        }

        // Allowed file types
        $allowed_types = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'text/plain', 'application/zip', 'application/x-rar-compressed'
        ];

        $allowed_extensions = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','webp','txt','zip','rar'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed. Accepted: ' . implode(', ', $allowed_extensions)]);
            return;
        }

        // Generate unique filename
        $upload_dir = '../../../uploads/assessments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $safe_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $new_filename = 'student_' . $student_id . '_assess_' . $assessment_id . '_' . time() . '_' . $safe_name . '.' . $ext;
        $upload_path = $upload_dir . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file. Please try again.']);
            return;
        }

        $submitted_file = 'uploads/assessments/' . $new_filename;

        // Delete old file if resubmitting
        if ($existing && !empty($existing['submitted_file'])) {
            $old_file = '../../../' . $existing['submitted_file'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
    } elseif (!$existing || empty($existing['submitted_file'])) {
        // No file uploaded and no previous file — still allow text-only submission
        if (empty($student_notes)) {
            echo json_encode(['success' => false, 'message' => 'Please upload a file or add notes for your submission']);
            return;
        }
    }

    $now = date('Y-m-d H:i:s');

    if ($existing) {
        // Update existing record
        if ($submitted_file) {
            $stmt = $conn->prepare("UPDATE assessment_scores SET status = 'submitted', student_notes = ?, submitted_file = ?, submitted_at = ? WHERE assessment_id = ? AND student_id = ?");
            $stmt->bind_param("sssii", $student_notes, $submitted_file, $now, $assessment_id, $student_id);
        } else {
            $stmt = $conn->prepare("UPDATE assessment_scores SET status = 'submitted', student_notes = ?, submitted_at = ? WHERE assessment_id = ? AND student_id = ?");
            $stmt->bind_param("ssii", $student_notes, $now, $assessment_id, $student_id);
        }
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO assessment_scores (assessment_id, student_id, status, student_notes, submitted_file, submitted_at) VALUES (?, ?, 'submitted', ?, ?, ?)");
        $stmt->bind_param("iisss", $assessment_id, $student_id, $student_notes, $submitted_file, $now);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assessment submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit: ' . $stmt->error]);
    }
    $stmt->close();
}

/**
 * Handle "Mark as Done" (submit without file, just mark status as submitted)
 */
function handleMarkDone($conn, $student_id, $assessment_id, $existing) {
    if ($existing && $existing['status'] === 'graded') {
        echo json_encode(['success' => false, 'message' => 'This assessment has already been graded']);
        return;
    }

    if ($existing && $existing['status'] === 'submitted') {
        echo json_encode(['success' => false, 'message' => 'This assessment is already marked as submitted']);
        return;
    }

    $now = date('Y-m-d H:i:s');

    if ($existing) {
        $stmt = $conn->prepare("UPDATE assessment_scores SET status = 'submitted', submitted_at = ? WHERE assessment_id = ? AND student_id = ?");
        $stmt->bind_param("sii", $now, $assessment_id, $student_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO assessment_scores (assessment_id, student_id, status, submitted_at) VALUES (?, ?, 'submitted', ?)");
        $stmt->bind_param("iis", $assessment_id, $student_id, $now);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assessment marked as done!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
    }
    $stmt->close();
}
