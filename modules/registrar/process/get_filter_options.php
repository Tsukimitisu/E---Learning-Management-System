<?php
/**
 * Returns filter options for the certificate student selector:
 * programs, shs_strands, year_levels
 */
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$programs = [];
$r = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");
if ($r) while ($row = $r->fetch_assoc()) $programs[] = $row;

$strands = [];
$r = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands ORDER BY strand_code");
if ($r) while ($row = $r->fetch_assoc()) $strands[] = $row;

// Get college year levels from program_year_levels
$college_year_levels = [];
$r = $conn->query("SELECT DISTINCT year_level, MIN(year_name) as level_name FROM program_year_levels WHERE is_active = 1 GROUP BY year_level ORDER BY year_level");
if ($r) while ($row = $r->fetch_assoc()) $college_year_levels[] = ['id' => $row['year_level'], 'level_name' => $row['level_name']];

// SHS grade levels
$shs_grade_levels = [
    ['id' => 11, 'level_name' => 'Grade 11'],
    ['id' => 12, 'level_name' => 'Grade 12']
];

// Get program-specific year levels for dynamic filtering
$program_year_levels = [];
$r = $conn->query("SELECT id, program_id, year_level, year_name FROM program_year_levels WHERE is_active = 1 ORDER BY program_id, year_level");
if ($r) while ($row = $r->fetch_assoc()) $program_year_levels[] = $row;

echo json_encode([
    'programs'    => $programs,
    'strands'     => $strands,
    'college_year_levels' => $college_year_levels,
    'shs_grade_levels'    => $shs_grade_levels,
    'program_year_levels' => $program_year_levels
]);
