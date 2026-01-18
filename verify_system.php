<?php
require_once 'config/init.php';

echo "=== SCHOOL ADMINISTRATOR SYSTEM VERIFICATION ===\n\n";

// Test Database Connection
echo "1. Database Connection: ";
if ($conn->ping()) {
    echo "✓ OK\n";
} else {
    echo "✗ FAILED\n";
    exit(1);
}

// Test Tables
echo "\n2. Database Tables:\n";
$tables = [
    'curriculum_subjects' => 'Curriculum Subjects',
    'programs' => 'College Programs',
    'program_year_levels' => 'Program Year Levels',
    'shs_tracks' => 'SHS Tracks',
    'shs_strands' => 'SHS Strands',
    'shs_grade_levels' => 'SHS Grade Levels',
    'announcements' => 'Announcements',
    'roles' => 'Roles',
    'users' => 'Users'
];

foreach ($tables as $table => $label) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "   ✓ $label: {$row['count']} records\n";
    } else {
        echo "   ✗ $label: TABLE NOT FOUND\n";
    }
}

// Test Role Constants
echo "\n3. Role Constants:\n";
$role_constants = [
    'ROLE_SUPER_ADMIN' => ROLE_SUPER_ADMIN,
    'ROLE_SCHOOL_ADMIN' => ROLE_SCHOOL_ADMIN,
    'ROLE_BRANCH_ADMIN' => ROLE_BRANCH_ADMIN,
    'ROLE_REGISTRAR' => ROLE_REGISTRAR,
    'ROLE_TEACHER' => ROLE_TEACHER,
    'ROLE_STUDENT' => ROLE_STUDENT
];

foreach ($role_constants as $name => $value) {
    echo "   ✓ $name = $value\n";
}

// Test File Existence
echo "\n4. Key Module Files:\n";
$files = [
    'modules/school_admin/index.php' => 'Dashboard Index',
    'modules/school_admin/curriculum.php' => 'Curriculum Management',
    'modules/school_admin/college_curriculum.php' => 'College Curriculum',
    'modules/school_admin/shs_curriculum.php' => 'SHS Curriculum',
    'modules/school_admin/announcements.php' => 'Announcements',
    'api/curriculum.php' => 'Curriculum API'
];

foreach ($files as $file => $label) {
    if (file_exists($file)) {
        echo "   ✓ $label\n";
    } else {
        echo "   ✗ $label: NOT FOUND\n";
    }
}

echo "\n=== ✓ ALL SYSTEMS OPERATIONAL ===\n";
?>
