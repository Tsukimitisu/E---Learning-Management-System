<?php
require_once '../../../config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$program_id = (int)($_GET['program_id'] ?? 0);

if (!$program_id) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare("SELECT id, year_name, year_level FROM program_year_levels WHERE program_id = ? AND is_active = 1 ORDER BY year_level");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();

$year_levels = [];
while ($row = $result->fetch_assoc()) {
    $year_levels[] = $row;
}

echo json_encode($year_levels);
