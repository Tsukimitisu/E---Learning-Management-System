<?php
require_once 'config/init.php';

echo "<h2>Student Creation Diagnostics</h2>";

// Check if programs exist
echo "<h3>1. Check Programs</h3>";
$result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 LIMIT 5");
echo "Active Programs: " . $result->num_rows . "<br>";
if ($result->num_rows > 0) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}

// Check if SHS Strands exist
echo "<h3>2. Check SHS Strands</h3>";
$result = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands WHERE is_active = 1 LIMIT 5");
echo "Active SHS Strands: " . $result->num_rows . "<br>";
if ($result->num_rows > 0) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}

// Check if students exist
echo "<h3>3. Check Students</h3>";
$result = $conn->query("SELECT s.user_id, s.student_no, s.course_id, CONCAT(up.first_name, ' ', up.last_name) as name FROM students s LEFT JOIN user_profiles up ON s.user_id = up.user_id");
echo "Total Students: " . $result->num_rows . "<br>";
if ($result->num_rows > 0) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "No students found<br>";
}

// Check student users in registrar query format
echo "<h3>4. Check Student Users (Registrar View)</h3>";
$result = $conn->query("
    SELECT u.id, up.first_name, up.last_name, u.status, ur.role_id
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_STUDENT . "
");
echo "Student Users: " . $result->num_rows . "<br>";
if ($result->num_rows > 0) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}

// Test the exact query from program_enrollment.php
echo "<h3>5. Test Program Enrollment Query</h3>";
$branch_id = 1;
$current_ay_id = 1;
$students_query = "
    SELECT 
        u.id,
        u.email,
        up.first_name,
        up.last_name,
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        st.course_id,
        COALESCE(p.program_code, ss.strand_code) as current_program_code,
        COALESCE(p.program_name, ss.strand_name) as current_program_name,
        CASE 
            WHEN st.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM programs WHERE id = st.course_id) THEN 'college'
            WHEN st.course_id IS NOT NULL AND EXISTS (SELECT 1 FROM shs_strands WHERE id = st.course_id) THEN 'shs'
            ELSE NULL 
        END as program_type,
        (SELECT COUNT(*) FROM section_students ss2 
         INNER JOIN sections s ON ss2.section_id = s.id 
         WHERE ss2.student_id = u.id AND s.branch_id = $branch_id AND s.academic_year_id = $current_ay_id AND ss2.status = 'active') as section_count
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN programs p ON st.course_id = p.id
    LEFT JOIN shs_strands ss ON st.course_id = ss.id
    WHERE ur.role_id = " . ROLE_STUDENT . " 
    AND u.status = 'active'
    ORDER BY up.last_name, up.first_name
";
$students = $conn->query($students_query);
echo "Students returned: " . $students->num_rows . "<br>";
if ($students->num_rows > 0) {
    echo "<pre>";
    while ($row = $students->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "No students found - checking intermediate joins...<br>";
    
    echo "<h4>Just users with student role:</h4>";
    $check = $conn->query("
        SELECT u.id, u.email, up.first_name, up.last_name, u.status
        FROM users u
        INNER JOIN user_profiles up ON u.id = up.user_id
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE ur.role_id = " . ROLE_STUDENT . "
    ");
    echo "Rows: " . $check->num_rows;
    while ($row = $check->fetch_assoc()) {
        echo json_encode($row) . "<br>";
    }
}

// Check if create_student API exists
echo "<h3>6. Check create_student.php API</h3>";
if (file_exists('modules/registrar/process/create_student.php')) {
    echo "✓ API file exists<br>";
} else {
    echo "✗ API file NOT found<br>";
}
?>
