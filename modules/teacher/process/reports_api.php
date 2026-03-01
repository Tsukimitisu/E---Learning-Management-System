<?php
/**
 * Reports API - Returns JSON data for the teacher reports page
 * Actions: grades, attendance, analytics
 */
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] ?? 0) != ROLE_TEACHER) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'You are not assigned to this class']);
    exit();
}

switch ($action) {
    case 'grades':
        handleGrades($conn, $section_id, $subject_id);
        break;
    case 'analytics':
        handleAnalytics($conn, $section_id, $subject_id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Grades Tab - Returns student list with all grading periods
 */
function handleGrades($conn, $section_id, $subject_id) {
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
            CONCAT(up.last_name, ', ', up.first_name) as student_name,
            g.prelim,
            g.midterm,
            g.prefinal,
            g.final,
            g.final_grade,
            g.remarks
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        INNER JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN students st ON u.id = st.user_id
        LEFT JOIN grades g ON u.id = g.student_id AND g.section_id = ? AND g.subject_id = ?
        WHERE ss.section_id = ? AND ss.status = 'active'
        ORDER BY up.last_name, up.first_name
    ");
    $stmt->bind_param("iii", $section_id, $subject_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    $graded_count = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['final_grade'] > 0) $graded_count++;
        $students[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'graded_count' => $graded_count,
        'total_count' => count($students)
    ]);
}

/**
 * Analytics Tab - Returns grade distribution, pass/fail, period averages, stats
 */
function handleAnalytics($conn, $section_id, $subject_id) {
    // Get all grades for this class
    $stmt = $conn->prepare("
        SELECT g.prelim, g.midterm, g.prefinal, g.final, g.final_grade, g.remarks
        FROM section_students ss
        INNER JOIN users u ON ss.student_id = u.id
        LEFT JOIN grades g ON u.id = g.student_id AND g.section_id = ? AND g.subject_id = ?
        WHERE ss.section_id = ? AND ss.status = 'active'
    ");
    $stmt->bind_param("iii", $section_id, $subject_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = [];
    $passed = 0;
    $failed = 0;
    $no_grade = 0;
    $prelim_sum = 0; $prelim_count = 0;
    $midterm_sum = 0; $midterm_count = 0;
    $prefinal_sum = 0; $prefinal_count = 0;
    $final_sum = 0; $final_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['final_grade'] > 0) {
            $grades[] = (float)$row['final_grade'];
        }
        
        // Pass/fail counts
        if ($row['remarks'] === 'PASSED') $passed++;
        elseif ($row['remarks'] === 'FAILED') $failed++;
        else $no_grade++;
        
        // Period averages
        if ($row['prelim'] > 0) { $prelim_sum += $row['prelim']; $prelim_count++; }
        if ($row['midterm'] > 0) { $midterm_sum += $row['midterm']; $midterm_count++; }
        if ($row['prefinal'] > 0) { $prefinal_sum += $row['prefinal']; $prefinal_count++; }
        if ($row['final'] > 0) { $final_sum += $row['final']; $final_count++; }
    }
    
    // Stats
    $stats = ['highest' => null, 'lowest' => null, 'mean' => null, 'median' => null];
    if (!empty($grades)) {
        sort($grades);
        $stats['highest'] = max($grades);
        $stats['lowest'] = min($grades);
        $stats['mean'] = array_sum($grades) / count($grades);
        $mid = floor(count($grades) / 2);
        $stats['median'] = count($grades) % 2 === 0 
            ? ($grades[$mid - 1] + $grades[$mid]) / 2 
            : $grades[$mid];
    }
    
    // Grade distribution: Below 60, 60-69, 70-79, 80-89, 90-100
    $dist = [0, 0, 0, 0, 0];
    foreach ($grades as $g) {
        if ($g < 60) $dist[0]++;
        elseif ($g < 70) $dist[1]++;
        elseif ($g < 80) $dist[2]++;
        elseif ($g < 90) $dist[3]++;
        else $dist[4]++;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'distribution' => [
            'labels' => ['Below 60', '60-69', '70-79', '80-89', '90-100'],
            'counts' => $dist
        ],
        'pass_fail' => [
            'passed' => $passed,
            'failed' => $failed,
            'no_grade' => $no_grade
        ],
        'period_avg' => [
            'prelim' => $prelim_count > 0 ? round($prelim_sum / $prelim_count, 2) : null,
            'midterm' => $midterm_count > 0 ? round($midterm_sum / $midterm_count, 2) : null,
            'prefinal' => $prefinal_count > 0 ? round($prefinal_sum / $prefinal_count, 2) : null,
            'final' => $final_count > 0 ? round($final_sum / $final_count, 2) : null
        ]
    ]);
}
