<?php
require_once '../config/init.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || ($user_role != ROLE_SCHOOL_ADMIN && $user_role != ROLE_SUPER_ADMIN)) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Curriculum management requires School Administrator privileges.']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // College Program Management
        case 'get_programs':
            $stmt = $conn->prepare("
                SELECT p.*, s.name as school_name
                FROM programs p
                LEFT JOIN schools s ON p.school_id = s.id
                WHERE p.is_active = 1
                ORDER BY p.program_code
            ");
            $stmt->execute();
            $programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $programs]);
            break;

        case 'add_program':
            $program_code = clean_input($_POST['program_code']);
            $program_name = clean_input($_POST['program_name']);
            $degree_level = clean_input($_POST['degree_level']);
            $school_id = (int)$_POST['school_id'];

            $stmt = $conn->prepare("
                INSERT INTO programs (program_code, program_name, degree_level, school_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("sssi", $program_code, $program_name, $degree_level, $school_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Program added successfully']);
            break;

        case 'update_program':
            $program_id = (int)$_POST['program_id'];
            $program_code = clean_input($_POST['program_code']);
            $program_name = clean_input($_POST['program_name']);
            $degree_level = clean_input($_POST['degree_level']);
            $duration_years = (int)$_POST['duration_years'];
            $total_units = (int)$_POST['total_units'] ?? 0;
            $description = clean_input($_POST['description'] ?? '');
            $is_active = (int)$_POST['is_active'];

            $stmt = $conn->prepare("
                UPDATE programs
                SET program_code = ?, program_name = ?, degree_level = ?,
                    duration_years = ?, total_units = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssiiisi", $program_code, $program_name, $degree_level, $duration_years, $total_units, $description, $is_active, $program_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
            break;

        case 'delete_program':
            $program_id = (int)$_POST['program_id'];

            // Check if program has associated year levels
            $check_years = $conn->prepare("SELECT COUNT(*) as count FROM program_year_levels WHERE program_id = ?");
            $check_years->bind_param("i", $program_id);
            $check_years->execute();
            $year_count = $check_years->get_result()->fetch_assoc()['count'];

            if ($year_count > 0) {
                echo json_encode(['status' => 'error', 'message' => "Cannot delete program with $year_count associated year levels. Delete year levels first."]);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
            $stmt->bind_param("i", $program_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Program deleted successfully']);
            break;

        // Year Level Management
        case 'get_year_levels':
            $program_id = (int)($_GET['program_id'] ?? 0);
            $stmt = $conn->prepare("
                SELECT yl.*, p.program_name
                FROM program_year_levels yl
                LEFT JOIN programs p ON yl.program_id = p.id
                WHERE yl.program_id = ? AND yl.is_active = 1
                ORDER BY yl.year_level
            ");
            $stmt->bind_param("i", $program_id);
            $stmt->execute();
            $year_levels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $year_levels]);
            break;

        case 'add_year_level':
            $program_id = (int)$_POST['program_id'];
            $year_level = (int)$_POST['year_level'];
            $year_name = clean_input($_POST['year_name']);
            $semesters_count = (int)$_POST['semesters_count'];

            $stmt = $conn->prepare("
                INSERT INTO program_year_levels (program_id, year_level, year_name, semesters_count)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iisi", $program_id, $year_level, $year_name, $semesters_count);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Year level added successfully']);
            break;

        case 'update_year_level':
            $year_id = (int)$_POST['year_id'];
            $year_name = clean_input($_POST['year_name']);
            $year_number = (int)$_POST['year_number'];
            $semesters = (int)$_POST['semesters'];
            $is_active = (int)$_POST['is_active'];

            $stmt = $conn->prepare("
                UPDATE program_year_levels
                SET year_name = ?, year_level = ?, semesters_count = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("siii", $year_name, $year_number, $semesters, $is_active, $year_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Year level updated successfully']);
            break;

        case 'delete_year_level':
            $year_id = (int)$_POST['year_id'];

            // Check if year level has associated subjects
            $check_subjects = $conn->prepare("SELECT COUNT(*) as count FROM curriculum_subjects WHERE year_level_id = ?");
            $check_subjects->bind_param("i", $year_id);
            $check_subjects->execute();
            $subject_count = $check_subjects->get_result()->fetch_assoc()['count'];

            if ($subject_count > 0) {
                echo json_encode(['status' => 'error', 'message' => "Cannot delete year level with $subject_count associated subjects. Delete subjects first."]);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM program_year_levels WHERE id = ?");
            $stmt->bind_param("i", $year_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Year level deleted successfully']);
            break;

        // SHS Track Management
        case 'get_tracks':
            $stmt = $conn->prepare("
                SELECT t.*, COUNT(s.id) as strand_count
                FROM shs_tracks t
                LEFT JOIN shs_strands s ON t.id = s.track_id
                GROUP BY t.id
                ORDER BY t.track_name
            ");
            $stmt->execute();
            $tracks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $tracks]);
            break;

        case 'add_track':
            $track_name = clean_input($_POST['track_name']);
            $track_code = clean_input($_POST['track_code']);
            $written_weight = (float)$_POST['written_work_weight'];
            $performance_weight = (float)$_POST['performance_task_weight'];
            $quarterly_weight = (float)$_POST['quarterly_exam_weight'];
            $description = clean_input($_POST['description'] ?? '');

            $stmt = $conn->prepare("
                INSERT INTO shs_tracks (track_name, track_code, written_work_weight, performance_task_weight, quarterly_exam_weight, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssddds", $track_name, $track_code, $written_weight, $performance_weight, $quarterly_weight, $description);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Track added successfully']);
            break;

        case 'update_track':
            $track_id = (int)$_POST['track_id'];
            $track_name = clean_input($_POST['track_name']);
            $description = clean_input($_POST['description'] ?? '');
            $is_active = (int)$_POST['is_active'];

            $stmt = $conn->prepare("
                UPDATE shs_tracks
                SET track_name = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssii", $track_name, $description, $is_active, $track_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Track updated successfully']);
            break;

        case 'delete_track':
            $track_id = (int)$_POST['track_id'];

            // Check if track has associated strands
            $check_strands = $conn->prepare("SELECT COUNT(*) as count FROM shs_strands WHERE track_id = ?");
            $check_strands->bind_param("i", $track_id);
            $check_strands->execute();
            $strand_count = $check_strands->get_result()->fetch_assoc()['count'];

            if ($strand_count > 0) {
                echo json_encode(['status' => 'error', 'message' => "Cannot delete track with $strand_count associated strands. Delete strands first."]);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM shs_tracks WHERE id = ?");
            $stmt->bind_param("i", $track_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Track deleted successfully']);
            break;

        // SHS Strand Management
        case 'get_strands':
            $track_id = (int)($_GET['track_id'] ?? 0);
            $stmt = $conn->prepare("
                SELECT s.*, t.track_name
                FROM shs_strands s
                LEFT JOIN shs_tracks t ON s.track_id = t.id
                WHERE s.track_id = ? AND s.is_active = 1
                ORDER BY s.strand_name
            ");
            $stmt->bind_param("i", $track_id);
            $stmt->execute();
            $strands = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $strands]);
            break;

        case 'add_strand':
            $track_id = (int)$_POST['track_id'];
            $strand_code = clean_input($_POST['strand_code']);
            $strand_name = clean_input($_POST['strand_name']);
            $description = clean_input($_POST['description'] ?? '');

            $stmt = $conn->prepare("
                INSERT INTO shs_strands (track_id, strand_code, strand_name, description)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $track_id, $strand_code, $strand_name, $description);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Strand added successfully']);
            break;

        case 'update_strand':
            $strand_id = (int)$_POST['strand_id'];
            $strand_name = clean_input($_POST['strand_name']);
            $description = clean_input($_POST['description'] ?? '');
            $is_active = (int)$_POST['is_active'];

            $stmt = $conn->prepare("
                UPDATE shs_strands
                SET strand_name = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssii", $strand_name, $description, $is_active, $strand_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Strand updated successfully']);
            break;

        case 'delete_strand':
            $strand_id = (int)$_POST['strand_id'];

            // Check if strand has associated subjects
            $check_subjects = $conn->prepare("SELECT COUNT(*) as count FROM curriculum_subjects WHERE shs_strand_id = ?");
            $check_subjects->bind_param("i", $strand_id);
            $check_subjects->execute();
            $subject_count = $check_subjects->get_result()->fetch_assoc()['count'];

            if ($subject_count > 0) {
                echo json_encode(['status' => 'error', 'message' => "Cannot delete strand with $subject_count associated subjects. Delete subjects first."]);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM shs_strands WHERE id = ?");
            $stmt->bind_param("i", $strand_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Strand deleted successfully']);
            break;

        // Add grade level
        case 'add_grade_level':
            $grade_name = clean_input($_POST['grade_name']);
            $semesters = (int)$_POST['semesters'];

            $stmt = $conn->prepare("
                INSERT INTO shs_grade_levels (grade_name, semesters_count)
                VALUES (?, ?)
            ");
            $stmt->bind_param("si", $grade_name, $semesters);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Grade level added successfully']);
            break;

        case 'update_grade_level':
            $grade_id = (int)$_POST['grade_id'];
            $grade_name = clean_input($_POST['grade_name']);
            $semesters = (int)$_POST['semesters'];
            $is_active = (int)$_POST['is_active'];

            $stmt = $conn->prepare("
                UPDATE shs_grade_levels
                SET grade_name = ?, semesters_count = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("siii", $grade_name, $semesters, $is_active, $grade_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Grade level updated successfully']);
            break;

        case 'delete_grade_level':
            $grade_id = (int)$_POST['grade_id'];

            $stmt = $conn->prepare("DELETE FROM shs_grade_levels WHERE id = ?");
            $stmt->bind_param("i", $grade_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Grade level deleted successfully']);
            break;

        // Add year level
        case 'add_year_level':
            $program_id = (int)$_POST['program_id'];
            $year_level = (int)$_POST['year_level'];
            $year_name = clean_input($_POST['year_name']);
            $semesters_count = (int)$_POST['semesters_count'];

            $stmt = $conn->prepare("
                INSERT INTO program_year_levels (program_id, year_level, year_name, semesters_count)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iisi", $program_id, $year_level, $year_name, $semesters_count);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Year level added successfully']);
            break;

        // Subject Management
        case 'get_subjects':
            $type = $_GET['type'] ?? 'college';
            $program_id = (int)($_GET['program_id'] ?? 0);
            $year_level_id = (int)($_GET['year_level_id'] ?? 0);
            $shs_strand_id = (int)($_GET['shs_strand_id'] ?? 0);
            $shs_grade_level_id = (int)($_GET['shs_grade_level_id'] ?? 0);

            $where = ["cs.is_active = 1"];
            $params = [];
            $types = "";

            if ($type === 'college' && $program_id > 0) {
                $where[] = "cs.program_id = ?";
                $params[] = $program_id;
                $types .= "i";
            }
            if ($year_level_id > 0) {
                $where[] = "cs.year_level_id = ?";
                $params[] = $year_level_id;
                $types .= "i";
            }
            if ($type === 'shs' && $shs_strand_id > 0) {
                $where[] = "cs.shs_strand_id = ?";
                $params[] = $shs_strand_id;
                $types .= "i";
            }
            if ($shs_grade_level_id > 0) {
                $where[] = "cs.shs_grade_level_id = ?";
                $params[] = $shs_grade_level_id;
                $types .= "i";
            }

            $where_clause = implode(" AND ", $where);

            $stmt = $conn->prepare("
                SELECT cs.*,
                       p.program_name,
                       yl.year_name,
                       ss.strand_name,
                       sgl.grade_name
                FROM curriculum_subjects cs
                LEFT JOIN programs p ON cs.program_id = p.id
                LEFT JOIN program_year_levels yl ON cs.year_level_id = yl.id
                LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
                LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
                WHERE $where_clause
                ORDER BY cs.subject_code
            ");

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $subjects]);
            break;

        // Update subject
        case 'update_subject':
            $subject_id = (int)$_POST['subject_id'];
            $subject_code = clean_input($_POST['subject_code']);
            $subject_title = clean_input($_POST['subject_title']);
            $units = (float)$_POST['units'];
            $lecture_hours = (int)$_POST['lecture_hours'];
            $lab_hours = (int)$_POST['lab_hours'];
            $prerequisites = clean_input($_POST['prerequisites'] ?? '');
            $is_active = (int)$_POST['is_active'];

            // Check if subject code conflicts with another subject
            $check_code = $conn->prepare("SELECT id FROM curriculum_subjects WHERE subject_code = ? AND id != ?");
            $check_code->bind_param("si", $subject_code, $subject_id);
            $check_code->execute();

            if ($check_code->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Subject code already exists']);
                exit();
            }

            $stmt = $conn->prepare("
                UPDATE curriculum_subjects
                SET subject_code = ?, subject_title = ?, units = ?,
                    lecture_hours = ?, lab_hours = ?, prerequisites = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssdiiisi", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $prerequisites, $is_active, $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully']);
            break;

        case 'delete_subject':
            $subject_id = (int)$_POST['subject_id'];

            $stmt = $conn->prepare("DELETE FROM curriculum_subjects WHERE id = ?");
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Subject deleted successfully']);
            break;

        // Remove subject assignment
        case 'remove_subject_assignment':
            $subject_id = (int)$_POST['subject_id'];

            $stmt = $conn->prepare("
                UPDATE curriculum_subjects
                SET program_id = NULL, year_level_id = NULL,
                    shs_strand_id = NULL, shs_grade_level_id = NULL
                WHERE id = ?
            ");
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Subject assignment removed successfully']);
            break;

        // College Course Management
        case 'get_college_courses':
            $stmt = $conn->prepare("
                SELECT id, subject_code as course_code, subject_title as course_title, units, 
                       lecture_hours, lab_hours, prerequisites, program_id, year_level_id, 
                       semester, is_active
                FROM curriculum_subjects
                WHERE subject_type = 'college' AND is_active = 1
                ORDER BY subject_code
            ");
            $stmt->execute();
            $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $courses]);
            break;

        case 'add_college_course':
            $subject_code = clean_input($_POST['course_code']);
            $subject_title = clean_input($_POST['course_title']);
            $units = (float)($_POST['units'] ?? 3);
            $lecture_hours = (int)($_POST['lecture_hours'] ?? 0);
            $lab_hours = (int)($_POST['lab_hours'] ?? 0);
            $prerequisites = clean_input($_POST['prerequisites'] ?? '');
            $program_id = (int)($_POST['program_id'] ?? 0) ?: null;
            $year_level_id = (int)($_POST['year_level_id'] ?? 0) ?: null;
            $semester = (int)($_POST['semester'] ?? 1);
            $created_by = (int)$_SESSION['user_id'];

            $stmt = $conn->prepare("
                INSERT INTO curriculum_subjects (subject_code, subject_title, units, lecture_hours, lab_hours, 
                    subject_type, program_id, year_level_id, semester, prerequisites, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, 'college', ?, ?, ?, ?, 1, ?)
            ");
            $stmt->bind_param("ssdiiiiisi", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $program_id, $year_level_id, $semester, $prerequisites, $created_by);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'College course added successfully']);
            break;

        case 'update_college_course':
            $subject_id = (int)($_POST['id'] ?? 0);
            $subject_code = clean_input($_POST['course_code']);
            $subject_title = clean_input($_POST['course_title']);
            $units = (float)($_POST['units'] ?? 3);
            $lecture_hours = (int)($_POST['lecture_hours'] ?? 0);
            $lab_hours = (int)($_POST['lab_hours'] ?? 0);
            $prerequisites = clean_input($_POST['prerequisites'] ?? '');
            $program_id = (int)($_POST['program_id'] ?? 0) ?: null;
            $year_level_id = (int)($_POST['year_level_id'] ?? 0) ?: null;
            $semester = (int)($_POST['semester'] ?? 1);
            $is_active = (int)($_POST['is_active'] ?? 1);

            $stmt = $conn->prepare("
                UPDATE curriculum_subjects
                SET subject_code = ?, subject_title = ?, units = ?, lecture_hours = ?,
                    lab_hours = ?, prerequisites = ?, program_id = ?, year_level_id = ?, semester = ?, is_active = ?
                WHERE id = ? AND subject_type = 'college'
            ");
            $stmt->bind_param("ssdiiiiiii", $subject_code, $subject_title, $units, $lecture_hours, $lab_hours, $prerequisites, $program_id, $year_level_id, $semester, $is_active, $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'College course updated successfully']);
            break;

        case 'delete_college_course':
            $subject_id = (int)($_POST['id'] ?? 0);

            $stmt = $conn->prepare("DELETE FROM curriculum_subjects WHERE id = ? AND subject_type = 'college'");
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'College course deleted successfully']);
            break;

        // Get all programs with year levels
        case 'get_program_structure':
            $stmt = $conn->prepare("
                SELECT p.*, GROUP_CONCAT(yl.year_name ORDER BY yl.year_level) as year_levels
                FROM programs p
                LEFT JOIN program_year_levels yl ON p.id = yl.program_id AND yl.is_active = 1
                WHERE p.is_active = 1
                GROUP BY p.id
                ORDER BY p.program_code
            ");
            $stmt->execute();
            $programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $programs]);
            break;

        // Get all SHS tracks with strands and grade levels
        case 'get_shs_structure':
            $stmt = $conn->prepare("
                SELECT
                    t.id as track_id, t.track_name, t.track_code,
                    s.id as strand_id, s.strand_name, s.strand_code,
                    gl.id as grade_level_id, gl.grade_name, gl.grade_level
                FROM shs_tracks t
                LEFT JOIN shs_strands s ON t.id = s.track_id AND s.is_active = 1
                LEFT JOIN shs_grade_levels gl ON s.id = gl.strand_id AND gl.is_active = 1
                WHERE t.id IS NOT NULL
                ORDER BY t.track_name, s.strand_name, gl.grade_level
            ");
            $stmt->execute();
            $shs_structure = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $shs_structure]);
            break;

        // Get unassigned subjects (for assignment)
        case 'get_unassigned_subjects':
            $stmt = $conn->prepare("
                SELECT cs.*
                FROM curriculum_subjects cs
                WHERE cs.is_active = 1
                  AND (cs.program_id IS NULL AND cs.shs_strand_id IS NULL)
                ORDER BY cs.subject_code
            ");
            $stmt->execute();
            $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $subjects]);
            break;

        case 'add_subject':
            $subject_code = clean_input($_POST['subject_code']);
            $subject_title = clean_input($_POST['subject_title']);
            $units = (float)$_POST['units'];
            $lecture_hours = (int)$_POST['lecture_hours'];
            $lab_hours = (int)$_POST['lab_hours'];
            $subject_type = clean_input($_POST['subject_type']);
            $semester = (int)$_POST['semester'];
            $prerequisites = clean_input($_POST['prerequisites'] ?? '');
            $created_by = $_SESSION['user_id'];

            // Initialize assignment fields
            $program_id = null;
            $year_level_id = null;
            $shs_strand_id = null;
            $shs_grade_level_id = null;

            // Validate and assign based on subject type
            if ($subject_type === 'college') {
                $program_id = (int)$_POST['program_id'];
                $year_level_id = (int)$_POST['year_level_id'];

                // Validate that program and year level exist and are related
                $validate_program = $conn->prepare("
                    SELECT pyl.id FROM program_year_levels pyl
                    WHERE pyl.program_id = ? AND pyl.id = ? AND pyl.is_active = 1
                ");
                $validate_program->bind_param("ii", $program_id, $year_level_id);
                $validate_program->execute();

                if ($validate_program->get_result()->num_rows == 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid program or year level assignment']);
                    exit();
                }

            } elseif (in_array($subject_type, ['shs_core', 'shs_applied', 'shs_specialized'])) {
                $shs_strand_id = (int)$_POST['shs_strand_id'];
                $shs_grade_level_id = (int)$_POST['shs_grade_level_id'];

                // Validate that strand and grade level exist and are related
                $validate_shs = $conn->prepare("
                    SELECT sgl.id FROM shs_grade_levels sgl
                    WHERE sgl.strand_id = ? AND sgl.id = ? AND sgl.is_active = 1
                ");
                $validate_shs->bind_param("ii", $shs_strand_id, $shs_grade_level_id);
                $validate_shs->execute();

                if ($validate_shs->get_result()->num_rows == 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid strand or grade level assignment']);
                    exit();
                }

            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid subject type']);
                exit();
            }

            // Check for duplicate subject code
            $check_duplicate = $conn->prepare("SELECT id FROM curriculum_subjects WHERE subject_code = ?");
            $check_duplicate->bind_param("s", $subject_code);
            $check_duplicate->execute();

            if ($check_duplicate->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Subject code already exists']);
                exit();
            }

            $stmt = $conn->prepare("
                INSERT INTO curriculum_subjects (
                    subject_code, subject_title, units, lecture_hours, lab_hours,
                    subject_type, program_id, year_level_id, shs_strand_id,
                    shs_grade_level_id, semester, prerequisites, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssdiisiiiisisi",
                $subject_code, $subject_title, $units, $lecture_hours, $lab_hours,
                $subject_type, $program_id, $year_level_id, $shs_strand_id,
                $shs_grade_level_id, $semester, $prerequisites, $created_by
            );
            $stmt->execute();

            $subject_id = $conn->insert_id;

            echo json_encode([
                'status' => 'success',
                'message' => 'Subject added successfully',
                'subject_id' => $subject_id
            ]);
            break;

        // Assign subject to course (College)
        case 'assign_subject_to_course':
            $subject_id = (int)$_POST['subject_id'];
            $program_id = (int)$_POST['program_id'];
            $year_level_id = (int)$_POST['year_level_id'];
            $semester = (int)$_POST['semester'];

            // Validate the assignment
            $validate = $conn->prepare("
                SELECT pyl.id FROM program_year_levels pyl
                WHERE pyl.program_id = ? AND pyl.id = ? AND pyl.is_active = 1
            ");
            $validate->bind_param("ii", $program_id, $year_level_id);
            $validate->execute();

            if ($validate->get_result()->num_rows == 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid program or year level']);
                exit();
            }

            // Update subject assignment
            $stmt = $conn->prepare("
                UPDATE curriculum_subjects
                SET program_id = ?, year_level_id = ?, semester = ?,
                    shs_strand_id = NULL, shs_grade_level_id = NULL,
                    subject_type = 'college'
                WHERE id = ?
            ");
            $stmt->bind_param("iiii", $program_id, $year_level_id, $semester, $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Subject assigned to course successfully']);
            break;

        // Assign subject to strand (SHS)
        case 'assign_subject_to_strand':
            $subject_id = (int)$_POST['subject_id'];
            $shs_strand_id = (int)$_POST['shs_strand_id'];
            $shs_grade_level_id = (int)$_POST['shs_grade_level_id'];
            $semester = (int)$_POST['semester'];
            $subject_type = clean_input($_POST['subject_type']); // core, applied, specialized

            // Validate the assignment
            $validate = $conn->prepare("
                SELECT sgl.id FROM shs_grade_levels sgl
                WHERE sgl.strand_id = ? AND sgl.id = ? AND sgl.is_active = 1
            ");
            $validate->bind_param("ii", $shs_strand_id, $shs_grade_level_id);
            $validate->execute();

            if ($validate->get_result()->num_rows == 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid strand or grade level']);
                exit();
            }

            // Update subject assignment
            $stmt = $conn->prepare("
                UPDATE curriculum_subjects
                SET shs_strand_id = ?, shs_grade_level_id = ?, semester = ?,
                    program_id = NULL, year_level_id = NULL,
                    subject_type = ?
                WHERE id = ?
            ");
            $stmt->bind_param("iiisi", $shs_strand_id, $shs_grade_level_id, $semester, $subject_type, $subject_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Subject assigned to strand successfully']);
            break;

        // Get subjects by course
        case 'get_subjects_by_course':
            $program_id = (int)$_GET['program_id'];
            $year_level = (int)($_GET['year_level'] ?? 0);
            $semester = (int)($_GET['semester'] ?? 0);

            $where = ["cs.program_id = ?", "cs.is_active = 1"];
            $params = [$program_id];
            $types = "i";

            if ($year_level > 0) {
                $where[] = "cs.year_level_id IN (SELECT id FROM program_year_levels WHERE year_level = ?)";
                $params[] = $year_level;
                $types .= "i";
            }

            if ($semester > 0) {
                $where[] = "cs.semester = ?";
                $params[] = $semester;
                $types .= "i";
            }

            $where_clause = implode(" AND ", $where);

            $stmt = $conn->prepare("
                SELECT cs.*, p.program_name, yl.year_name
                FROM curriculum_subjects cs
                LEFT JOIN programs p ON cs.program_id = p.id
                LEFT JOIN program_year_levels yl ON cs.year_level_id = yl.id
                WHERE $where_clause
                ORDER BY cs.subject_code
            ");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $subjects]);
            break;

        // Get subjects by strand
        case 'get_subjects_by_strand':
            $shs_strand_id = (int)$_GET['shs_strand_id'];
            $grade_level = (int)($_GET['grade_level'] ?? 0);
            $semester = (int)($_GET['semester'] ?? 0);

            $where = ["cs.shs_strand_id = ?", "cs.is_active = 1"];
            $params = [$shs_strand_id];
            $types = "i";

            if ($grade_level > 0) {
                $where[] = "cs.shs_grade_level_id IN (SELECT id FROM shs_grade_levels WHERE grade_level = ?)";
                $params[] = $grade_level;
                $types .= "i";
            }

            if ($semester > 0) {
                $where[] = "cs.semester = ?";
                $params[] = $semester;
                $types .= "i";
            }

            $where_clause = implode(" AND ", $where);

            $stmt = $conn->prepare("
                SELECT cs.*, ss.strand_name, sgl.grade_name
                FROM curriculum_subjects cs
                LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
                LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
                WHERE $where_clause
                ORDER BY cs.subject_code
            ");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $subjects]);
            break;

        // Branch Admin: Get approved subjects for class scheduling
        case 'get_approved_subjects':
            $action_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
            if ($action_role != ROLE_BRANCH_ADMIN && $action_role != ROLE_SCHOOL_ADMIN && $action_role != ROLE_SUPER_ADMIN) {
                echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                exit();
            }

            $type = $_GET['type'] ?? 'college';
            $program_id = (int)($_GET['program_id'] ?? 0);
            $year_level = (int)($_GET['year_level'] ?? 0);
            $shs_strand_id = (int)($_GET['shs_strand_id'] ?? 0);
            $grade_level = (int)($_GET['grade_level'] ?? 0);

            $where = ["cs.is_active = 1"];
            $params = [];
            $types = "";

            if ($type === 'college' && $program_id > 0) {
                $where[] = "cs.program_id = ?";
                $params[] = $program_id;
                $types .= "i";
            }
            if ($year_level > 0) {
                $where[] = "cs.year_level_id IN (SELECT id FROM program_year_levels WHERE year_level = ?)";
                $params[] = $year_level;
                $types .= "i";
            }
            if ($type === 'shs' && $shs_strand_id > 0) {
                $where[] = "cs.shs_strand_id = ?";
                $params[] = $shs_strand_id;
                $types .= "i";
            }
            if ($grade_level > 0) {
                $where[] = "cs.shs_grade_level_id IN (SELECT id FROM shs_grade_levels WHERE grade_level = ?)";
                $params[] = $grade_level;
                $types .= "i";
            }

            $where_clause = implode(" AND ", $where);

            $stmt = $conn->prepare("
                SELECT cs.id, cs.subject_code, cs.subject_title, cs.units,
                       cs.lecture_hours, cs.lab_hours, cs.subject_type,
                       cs.semester, cs.prerequisites,
                       p.program_name,
                       yl.year_name,
                       ss.strand_name,
                       sgl.grade_name
                FROM curriculum_subjects cs
                LEFT JOIN programs p ON cs.program_id = p.id
                LEFT JOIN program_year_levels yl ON cs.year_level_id = yl.id
                LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
                LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
                WHERE $where_clause
                ORDER BY cs.subject_code
            ");

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $subjects]);
            break;

        // Validate curriculum prerequisites
        case 'validate_prerequisites':
            $student_id = (int)$_GET['student_id'];
            $curriculum_subject_id = (int)$_GET['curriculum_subject_id'];

            // Get subject prerequisites
            $prereq_stmt = $conn->prepare("
                SELECT prerequisites FROM curriculum_subjects
                WHERE id = ? AND is_active = 1
            ");
            $prereq_stmt->bind_param("i", $curriculum_subject_id);
            $prereq_stmt->execute();
            $prereq_result = $prereq_stmt->get_result();

            if ($prereq_result->num_rows == 0) {
                echo json_encode(['status' => 'error', 'message' => 'Subject not found']);
                exit();
            }

            $prerequisites = $prereq_result->fetch_assoc()['prerequisites'];

            if (empty($prerequisites)) {
                echo json_encode(['status' => 'success', 'valid' => true, 'message' => 'No prerequisites required']);
                exit();
            }

            // Parse prerequisites (assuming comma-separated subject codes)
            $required_codes = array_map('trim', explode(',', $prerequisites));

            // Check if student has completed required subjects
            $placeholders = str_repeat('?,', count($required_codes) - 1) . '?';
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as completed_count
                FROM grades g
                JOIN curriculum_subjects cs ON g.class_id IN (
                    SELECT c.id FROM classes c WHERE c.curriculum_subject_id = cs.id
                )
                WHERE g.student_id = ?
                  AND cs.subject_code IN ($placeholders)
                  AND g.final_grade >= 75
            ");

            $params = array_merge([$student_id], $required_codes);
            $check_stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $check_stmt->execute();

            $completed = $check_stmt->get_result()->fetch_assoc()['completed_count'];

            $valid = $completed >= count($required_codes);
            $message = $valid ?
                'Prerequisites satisfied' :
                "Missing prerequisites. Completed: $completed/" . count($required_codes) . " required subjects";

            echo json_encode([
                'status' => 'success',
                'valid' => $valid,
                'message' => $message,
                'required_subjects' => $required_codes,
                'completed_count' => $completed
            ]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Operation failed: ' . $e->getMessage()]);
}
?>