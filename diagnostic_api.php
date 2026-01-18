<?php
require_once 'config/init.php';

header('Content-Type: application/json');

$data = [
    'database' => [
        'connected' => $conn ? true : false
    ],
    'students' => [],
    'branch_admins' => [],
    'registrars' => []
];

// Students
$total_result = $conn->query("SELECT COUNT(*) as cnt FROM students");
$total = $total_result->fetch_assoc()['cnt'];

$with_branch_result = $conn->query("SELECT COUNT(*) as cnt FROM students s INNER JOIN user_profiles up ON s.user_id = up.user_id WHERE up.branch_id IS NOT NULL");
$with_branch = $with_branch_result->fetch_assoc()['cnt'];

$data['students']['total'] = $total;
$data['students']['with_branch_id'] = $with_branch;
$data['students']['without_branch_id'] = $total - $with_branch;

// By branch
$by_branch_result = $conn->query("
    SELECT b.id, b.name, COUNT(s.user_id) as count
    FROM branches b
    LEFT JOIN user_profiles up ON b.id = up.branch_id
    LEFT JOIN students s ON up.user_id = s.user_id
    GROUP BY b.id, b.name
    ORDER BY b.id
");

$by_branch = [];
while ($row = $by_branch_result->fetch_assoc()) {
    $by_branch[$row['name']] = (int)$row['count'];
}
$data['students']['by_branch'] = $by_branch;

// Branch Admins
$admins_result = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, b.name as branch
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN branches b ON up.branch_id = b.id
    WHERE ur.role_id = " . ROLE_BRANCH_ADMIN . "
");

while ($row = $admins_result->fetch_assoc()) {
    $data['branch_admins'][] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'branch' => $row['branch']
    ];
}

// Registrars
$registrars_result = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name, b.name as branch
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN branches b ON up.branch_id = b.id
    WHERE ur.role_id = " . ROLE_REGISTRAR . "
");

while ($row = $registrars_result->fetch_assoc()) {
    $data['registrars'][] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'branch' => $row['branch']
    ];
}

echo json_encode($data, JSON_PRETTY_PRINT);
?>
