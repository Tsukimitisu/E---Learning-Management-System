<?php
/**
 * Student Search API for Certificate Generation
 * Returns students filtered by search term, program/strand, and year level.
 * 
 * GET params:
 *   q          - search term (name or student_no)
 *   program_id - filter by program id  
 *   strand_id  - filter by shs strand id
 *   year_level - filter by year_level_id from latest enrollment
 *   limit      - max results (default 20)
 */
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$search     = clean_input($_GET['q'] ?? '');
$program_id = (int)($_GET['program_id'] ?? 0);
$strand_id  = (int)($_GET['strand_id'] ?? 0);
$year_level = (int)($_GET['year_level'] ?? 0);
$edu_type   = clean_input($_GET['education_type'] ?? ''); // 'college' or 'shs'
$semester   = (int)($_GET['semester'] ?? 0);
$limit      = min((int)($_GET['limit'] ?? 20), 100);

// Build the query
$where = [];
$params = [];
$types = '';

$sql = "
    SELECT DISTINCT
        s.user_id,
        s.student_no,
        s.course_id,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        up.first_name,
        up.last_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        CASE 
            WHEN p.id IS NOT NULL THEN 'college'
            WHEN ss.id IS NOT NULL THEN 'shs'
            ELSE 'unknown'
        END as program_type,
        latest_enroll.year_level_id,
        COALESCE(pyl.year_name, '') as year_level_name
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id AND p.id IS NULL
    LEFT JOIN (
        SELECT ste.student_id, ste.year_level_id, ste.semester
        FROM student_term_enrollments ste
        WHERE ste.status = 'enrolled'
        ORDER BY ste.created_at DESC
    ) latest_enroll ON latest_enroll.student_id = s.user_id
    LEFT JOIN program_year_levels pyl ON latest_enroll.year_level_id = pyl.id
";

// Search by name or student number
if ($search !== '') {
    $where[] = "(CONCAT(up.first_name, ' ', up.last_name) LIKE ? OR s.student_no LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = &$searchTerm;
    $params[] = &$searchTerm;
    $types .= 'ss';
}

// Filter by program (college)
if ($program_id > 0) {
    $where[] = "p.id = ?";
    $params[] = &$program_id;
    $types .= 'i';
}

// Filter by strand (SHS)
if ($strand_id > 0) {
    $where[] = "ss.id = ?";
    $params[] = &$strand_id;
    $types .= 'i';
}

// Filter by year level
if ($year_level > 0) {
    $where[] = "latest_enroll.year_level_id = ?";
    $params[] = &$year_level;
    $types .= 'i';
}

// Filter by education type (college or shs)
if ($edu_type === 'college') {
    $where[] = "p.id IS NOT NULL";
} elseif ($edu_type === 'shs') {
    $where[] = "(p.id IS NULL AND ss.id IS NOT NULL)";
}

// Filter by semester
if ($semester > 0) {
    $where[] = "latest_enroll.semester = ?";
    $params[] = &$semester;
    $types .= 'i';
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

// Group to deduplicate from subquery
$sql .= " GROUP BY s.user_id";
$sql .= " ORDER BY up.last_name, up.first_name";
$sql .= " LIMIT ?";
$params[] = &$limit;
$types .= 'i';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['error' => 'Query error: ' . $conn->error, 'students' => []]);
    exit();
}

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'user_id'         => (int)$row['user_id'],
        'student_no'      => $row['student_no'],
        'full_name'       => $row['full_name'],
        'program_code'    => $row['program_code'] ?? '',
        'program_name'    => $row['program_name'] ?? '',
        'program_type'    => $row['program_type'],
        'year_level_id'   => (int)($row['year_level_id'] ?? 0),
        'year_level_name' => $row['year_level_name'] ?? ''
    ];
}

echo json_encode(['students' => $students]);
