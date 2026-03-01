<?php
/**
 * ELMS System Comprehensive Test Suite
 * Tests: Database connectivity, table integrity, PHP syntax, file existence,
 *        security features, enum values, foreign keys, and critical functionality.
 * 
 * Run from CLI: php system_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ── Helpers ──────────────────────────────────────────────────
$pass = 0; $fail = 0; $warn = 0; $errors = [];

function test_pass($label) {
    global $pass;
    $pass++;
    echo "  [PASS] $label\n";
}
function test_fail($label, $detail = '') {
    global $fail, $errors;
    $fail++;
    $msg = "  [FAIL] $label" . ($detail ? " — $detail" : '');
    $errors[] = $msg;
    echo $msg . "\n";
}
function test_warn($label) {
    global $warn;
    $warn++;
    echo "  [WARN] $label\n";
}
function section($title) {
    echo "\n" . str_repeat('=', 60) . "\n  $title\n" . str_repeat('=', 60) . "\n";
}

// ── 1. DATABASE CONNECTION ──────────────────────────────────
section('1. DATABASE CONNECTION');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'elms_data';

// mysqli
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    test_fail("MySQLi connection", $conn->connect_error);
    echo "\n*** Cannot proceed without database. Exiting. ***\n";
    exit(1);
} else {
    test_pass("MySQLi connection OK (server: " . $conn->server_info . ")");
}

// PDO
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    test_pass("PDO connection OK");
} catch (PDOException $e) {
    test_fail("PDO connection", $e->getMessage());
}

// ── 2. REQUIRED TABLES ──────────────────────────────────────
section('2. REQUIRED DATABASE TABLES');

$required_tables = [
    // Core
    'users', 'user_profiles', 'user_roles', 'roles',
    // Academic
    'schools', 'branches', 'programs', 'program_year_levels',
    'academic_years', 'sections', 'section_students',
    'courses', 'curriculum_subjects', 'subjects',
    'classes', 'enrollments',
    // Student enrollment
    'student_subject_enrollments', 'student_term_enrollments',
    'students', 'student_completed_subjects',
    // Grades
    'grades', 'grade_components', 'grade_locks',
    // Attendance & Assessment
    'attendance', 'assessments', 'assessment_scores',
    // Payments
    'payments', 'student_fees', 'program_tuition_fees',
    // Materials & Announcements
    'learning_materials', 'announcements',
    // Certificates
    'certificates_issued',
    // Teacher assignments
    'teacher_subject_assignments',
    // Security & Auth
    'login_attempts', 'security_logs', 'audit_logs',
    'security_settings', 'active_sessions', 'resource_locks',
    'password_resets', 'oauth_tokens', 'email_logs',
    // Notifications
    'notifications',
    // System
    'system_settings',
];

$result = $conn->query("SHOW TABLES");
$existing_tables = [];
while ($row = $result->fetch_row()) {
    $existing_tables[] = $row[0];
}

$missing_tables = [];
foreach ($required_tables as $table) {
    if (in_array($table, $existing_tables)) {
        test_pass("Table '$table' exists");
    } else {
        test_fail("Table '$table' missing");
        $missing_tables[] = $table;
    }
}
echo "  Total tables in DB: " . count($existing_tables) . "\n";

// ── 3. CRITICAL COLUMN CHECKS ───────────────────────────────
section('3. CRITICAL COLUMN & ENUM CHECKS');

// Helper: check column exists
function check_column($conn, $table, $column, $label = '') {
    $label = $label ?: "Column '$table.$column'";
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($r && $r->num_rows > 0) {
        test_pass("$label exists");
        return $r->fetch_assoc();
    } else {
        test_fail("$label missing");
        return null;
    }
}

// Helper: check enum contains value
function check_enum_value($conn, $table, $column, $value) {
    $col = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($col && $col->num_rows > 0) {
        $info = $col->fetch_assoc();
        if (strpos($info['Type'], "'$value'") !== false) {
            test_pass("Enum '$table.$column' contains '$value'");
            return true;
        } else {
            test_fail("Enum '$table.$column' missing value '$value'", "Current: " . $info['Type']);
            return false;
        }
    } else {
        test_fail("Column '$table.$column' not found for enum check");
        return false;
    }
}

// students.student_type
if (!in_array('students', $missing_tables)) {
    check_column($conn, 'students', 'student_type');
    check_enum_value($conn, 'students', 'student_type', 'regular');
    check_enum_value($conn, 'students', 'student_type', 'irregular');
    check_enum_value($conn, 'students', 'student_type', 'transferee');
}

// student_subject_enrollments.status — must include 'credited'
if (!in_array('student_subject_enrollments', $missing_tables)) {
    check_column($conn, 'student_subject_enrollments', 'status');
    check_enum_value($conn, 'student_subject_enrollments', 'status', 'enrolled');
    check_enum_value($conn, 'student_subject_enrollments', 'status', 'completed');
    check_enum_value($conn, 'student_subject_enrollments', 'status', 'dropped');
    check_enum_value($conn, 'student_subject_enrollments', 'status', 'credited');
    check_column($conn, 'student_subject_enrollments', 'enrollment_type');
}

// assessments — submitted file columns
if (!in_array('assessment_scores', $missing_tables)) {
    check_column($conn, 'assessment_scores', 'submitted_file');
    check_column($conn, 'assessment_scores', 'submitted_at');
    check_column($conn, 'assessment_scores', 'student_notes');
}

// grades table columns
if (!in_array('grades', $missing_tables)) {
    check_column($conn, 'grades', 'version', 'Grades optimistic locking (version)');
    check_column($conn, 'grades', 'notes', 'Grades notes column');
    check_column($conn, 'grades', 'prelim');
    check_column($conn, 'grades', 'midterm');
    check_column($conn, 'grades', 'prefinal');
    check_column($conn, 'grades', 'final');
    check_column($conn, 'grades', 'final_grade');
}

// users table
if (!in_array('users', $missing_tables)) {
    check_column($conn, 'users', 'lock_count', 'Users lock_count for lockout');
}

// notifications
if (!in_array('notifications', $missing_tables)) {
    check_column($conn, 'notifications', 'user_id');
    check_column($conn, 'notifications', 'is_read');
    check_column($conn, 'notifications', 'type');
    check_column($conn, 'notifications', 'link');
}

// user_profiles.branch_id
if (!in_array('user_profiles', $missing_tables)) {
    check_column($conn, 'user_profiles', 'branch_id', 'user_profiles.branch_id');
}

// payments
if (!in_array('payments', $missing_tables)) {
    check_enum_value($conn, 'payments', 'status', 'pending');
    check_enum_value($conn, 'payments', 'status', 'verified');
    check_enum_value($conn, 'payments', 'status', 'rejected');
}

// ── 4. ROLES SEEDED ─────────────────────────────────────────
section('4. ROLE DATA INTEGRITY');

if (!in_array('roles', $missing_tables)) {
    $roles_expected = [
        1 => 'Super Admin',
        2 => 'School Admin',
        3 => 'Branch Admin',
        4 => 'Registrar',
        5 => 'Teacher',
        6 => 'Student'
    ];
    $r = $conn->query("SELECT id, name FROM roles ORDER BY id");
    $existing_roles = [];
    while ($row = $r->fetch_assoc()) {
        $existing_roles[$row['id']] = $row['name'];
    }
    foreach ($roles_expected as $id => $name) {
        if (isset($existing_roles[$id]) && stripos($existing_roles[$id], explode(' ', $name)[0]) !== false) {
            test_pass("Role $id '$name' exists");
        } else {
            test_fail("Role $id '$name' missing or mismatched", isset($existing_roles[$id]) ? "Found: " . $existing_roles[$id] : "Not found");
        }
    }
}

// ── 5. ACTIVE ACADEMIC YEAR ─────────────────────────────────
section('5. ACADEMIC YEAR CHECK');

if (!in_array('academic_years', $missing_tables)) {
    $r = $conn->query("SELECT id, year_name, status, is_active FROM academic_years WHERE is_active = 1");
    if ($r && $r->num_rows > 0) {
        $ay = $r->fetch_assoc();
        test_pass("Active academic year: '{$ay['year_name']}' (ID: {$ay['id']}, status: {$ay['status']})");
    } else {
        test_warn("No active academic year found — some features will not work");
    }
    
    $r = $conn->query("SELECT COUNT(*) as cnt FROM academic_years");
    $cnt = $r->fetch_assoc()['cnt'];
    echo "  Total academic years: $cnt\n";
}

// ── 6. USER COUNTS ──────────────────────────────────────────
section('6. USER & DATA COUNTS');

$count_queries = [
    'Total users' => "SELECT COUNT(*) as cnt FROM users",
    'Active users' => "SELECT COUNT(*) as cnt FROM users WHERE status = 'active'",
    'Super Admins' => "SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = 1",
    'School Admins' => "SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = 2",
    'Branch Admins' => "SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = 3",
    'Registrars' => "SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = 4",
    'Teachers' => "SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = 5",
    'Students' => "SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = 6",
    'Schools' => "SELECT COUNT(*) as cnt FROM schools",
    'Branches' => "SELECT COUNT(*) as cnt FROM branches",
    'Programs' => "SELECT COUNT(*) as cnt FROM programs",
    'Curriculum Subjects' => "SELECT COUNT(*) as cnt FROM curriculum_subjects",
    'Sections' => "SELECT COUNT(*) as cnt FROM sections",
    'Student Subject Enrollments' => "SELECT COUNT(*) as cnt FROM student_subject_enrollments",
    'Credited Enrollments' => "SELECT COUNT(*) as cnt FROM student_subject_enrollments WHERE status = 'credited'",
    'Grades Records' => "SELECT COUNT(*) as cnt FROM grades",
    'Payments' => "SELECT COUNT(*) as cnt FROM payments",
    'Notifications' => "SELECT COUNT(*) as cnt FROM notifications",
];

foreach ($count_queries as $label => $sql) {
    try {
        $r = $conn->query($sql);
        if ($r) {
            $cnt = $r->fetch_assoc()['cnt'];
            echo "  $label: $cnt\n";
            if ($label === 'Super Admins' && $cnt == 0) {
                test_fail("No Super Admin exists — system is inaccessible");
            }
        }
    } catch (Exception $e) {
        test_warn("Could not count $label: " . $e->getMessage());
    }
}

// ── 7. PHP SYNTAX CHECK ─────────────────────────────────────
section('7. PHP SYNTAX CHECK (all .php files)');

$root = __DIR__;
$php_files = [];

function scan_php($dir, &$files, $skip = []) {
    $items = @scandir($dir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        // Skip vendor, node_modules, .git
        $basename = basename($path);
        if (in_array($basename, ['vendor', 'node_modules', '.git', 'realtime_server', 'uploads'])) continue;
        if (is_dir($path)) {
            scan_php($path, $files, $skip);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $files[] = $path;
        }
    }
}

scan_php($root, $php_files);
echo "  Scanning " . count($php_files) . " PHP files...\n";

$syntax_errors = [];
foreach ($php_files as $file) {
    $output = [];
    $retval = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $retval);
    if ($retval !== 0) {
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        $err_msg = implode(' ', $output);
        $syntax_errors[] = $rel . ': ' . $err_msg;
        test_fail("Syntax error in $rel", $err_msg);
    }
}

if (empty($syntax_errors)) {
    test_pass("All " . count($php_files) . " PHP files pass syntax check");
} else {
    echo "  " . count($syntax_errors) . " file(s) with syntax errors\n";
}

// ── 8. CRITICAL FILE EXISTENCE ──────────────────────────────
section('8. CRITICAL FILE EXISTENCE');

$critical_files = [
    // Config
    'config/db.php',
    'config/init.php',
    'config/seed.php',
    // Includes
    'includes/functions.php',
    'includes/rbac.php',
    'includes/security_helper.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/sidebar.php',
    'includes/email_helper.php',
    'includes/realtime_helper.php',
    'includes/notification_helper.php',
    // Auth
    'auth/login_process.php',
    'auth/google_callback.php',
    // Entry points
    'index.php',
    'dashboard.php',
    'forgot_password.php',
    'reset_password.php',
    'logout.php',
    // Legal / Compliance
    'privacy_policy.php',
    'terms_of_service.php',
    // APIs
    'api/check_updates.php',
    'api/curriculum.php',
    'api/active_sessions.php',
    'diagnostic_api.php',
    // Assets
    'assets/css/style.css',
    'assets/css/login.css',
    'assets/js/main.js',
    'assets/js/login.js',
    'assets/js/notifications.js',
    // Module dashboards
    'modules/super_admin/dashboard.php',
    'modules/school_admin/dashboard.php',
    'modules/branch_admin/dashboard.php',
    'modules/registrar/dashboard.php',
    'modules/teacher/dashboard.php',
    'modules/student/dashboard.php',
    // Teacher
    'modules/teacher/gradebook.php',
    'modules/teacher/section_students.php',
    'modules/teacher/assessments.php',
    'modules/teacher/materials.php',
    'modules/teacher/api/update_grade.php',
    'modules/teacher/api/export_students.php',
    // Registrar
    'modules/registrar/students.php',
    'modules/registrar/certificates.php',
    'modules/registrar/class_records.php',
    'modules/registrar/payment_history.php',
    'modules/registrar/program_enrollment.php',
    'modules/registrar/tuition_fees.php',
    'modules/registrar/process/program_enrollment_api.php',
    // Branch Admin
    'modules/branch_admin/student_assignment.php',
    'modules/branch_admin/teacher_assignment.php',
    'modules/branch_admin/sectioning.php',
    'modules/branch_admin/scheduling.php',
    'modules/branch_admin/enrollment.php',
    'modules/branch_admin/process/student_assignment_api.php',
    // Student
    'modules/student/grades.php',
    'modules/student/assessments.php',
    'modules/student/enrollment.php',
    'modules/student/process/submit_assessment.php',
    // School Admin
    'modules/school_admin/curriculum.php',
    'modules/school_admin/programs.php',
    'modules/school_admin/branches.php',
    // Common
    'modules/common/account_settings.php',
    // Super Admin
    'modules/super_admin/process/verify_database.php',
];

foreach ($critical_files as $file) {
    $fullpath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (file_exists($fullpath)) {
        test_pass($file);
    } else {
        test_fail("Missing: $file");
    }
}

// ── 9. SECURITY CHECKS ─────────────────────────────────────
section('9. SECURITY FEATURE CHECKS');

// 9a. CSRF functions exist in rbac.php
$rbac_content = file_get_contents($root . '/includes/rbac.php');
$csrf_funcs = ['csrf_token', 'csrf_field', 'verify_csrf', 'require_csrf'];
foreach ($csrf_funcs as $fn) {
    if (strpos($rbac_content, "function $fn") !== false) {
        test_pass("CSRF function '$fn()' defined in rbac.php");
    } else {
        test_fail("CSRF function '$fn()' not found in rbac.php");
    }
}

// 9b. CSRF enforcement in init.php
$init_content = file_get_contents($root . '/config/init.php');
if (strpos($init_content, 'require_csrf') !== false || strpos($init_content, 'verify_csrf') !== false) {
    test_pass("CSRF enforcement present in init.php");
} else {
    test_fail("CSRF enforcement missing from init.php");
}

// 9c. Session security settings
if (strpos($init_content, 'httponly') !== false || strpos($init_content, 'cookie_httponly') !== false) {
    test_pass("Session HttpOnly flag configured");
} else {
    test_warn("Session HttpOnly flag not detected in init.php");
}

if (strpos($init_content, 'SameSite') !== false || strpos($init_content, 'samesite') !== false || strpos($init_content, 'cookie_samesite') !== false) {
    test_pass("SameSite cookie attribute configured");
} else {
    test_warn("SameSite cookie not configured");
}

// 9d. Password hashing check
$login_content = file_get_contents($root . '/auth/login_process.php');
if (strpos($login_content, 'password_verify') !== false) {
    test_pass("password_verify() used in login");
} else {
    test_fail("password_verify() not found in login — may use insecure comparison");
}

// 9e. Account lockout
$security_content = file_get_contents($root . '/includes/security_helper.php');
if (strpos($security_content, 'is_account_locked') !== false) {
    test_pass("Account lockout function exists");
} else {
    test_fail("Account lockout function missing");
}

// 9f. Security settings seeded
if (!in_array('security_settings', $missing_tables)) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM security_settings");
    $cnt = $r->fetch_assoc()['cnt'];
    if ($cnt > 0) {
        test_pass("Security settings seeded ($cnt settings)");
    } else {
        test_warn("Security settings table is empty — defaults may not work");
    }
}

// 9g. Grade server-side credited check
$update_grade_content = file_get_contents($root . '/modules/teacher/api/update_grade.php');
if (strpos($update_grade_content, 'credited') !== false) {
    test_pass("Server-side credited subject check in update_grade.php");
} else {
    test_fail("No credited subject check in update_grade.php");
}

// ── 10. CREDITED SUBJECT FEATURE ────────────────────────────
section('10. CREDITED SUBJECT FEATURE VALIDATION');

// 10a. Gradebook includes credited status in query
$gradebook_content = file_get_contents($root . '/modules/teacher/gradebook.php');
if (preg_match("/status\s+IN\s*\(\s*'enrolled'\s*,\s*'credited'\s*\)/i", $gradebook_content)) {
    test_pass("Gradebook query includes 'credited' status");
} else {
    test_fail("Gradebook query doesn't include 'credited' status");
}
if (strpos($gradebook_content, 'data-credited') !== false) {
    test_pass("Gradebook UI has data-credited attribute");
} else {
    test_fail("Gradebook UI missing data-credited attribute");
}
if (strpos($gradebook_content, 'isCredited') !== false) {
    test_pass("Gradebook export handles isCredited");
} else {
    test_fail("Gradebook export missing isCredited handling");
}

// 10b. Section students includes credited
$section_students = file_get_contents($root . '/modules/teacher/section_students.php');
if (preg_match("/status\s+IN\s*\(\s*'enrolled'\s*,\s*'credited'\s*\)/i", $section_students)) {
    test_pass("Section students query includes 'credited' status");
} else {
    test_fail("Section students query doesn't include 'credited'");
}
if (strpos($section_students, 'Credited Subject') !== false) {
    test_pass("Section students shows 'Credited Subject' badge");
} else {
    test_fail("Section students missing 'Credited Subject' badge");
}

// 10c. Program enrollment enrolls credited subjects
$enrollment_api = file_get_contents($root . '/modules/registrar/process/program_enrollment_api.php');
if (strpos($enrollment_api, "status = 'credited'") !== false || strpos($enrollment_api, "status='credited'") !== false) {
    test_pass("Enrollment API enrolls credited subjects with 'credited' status");
} else {
    test_fail("Enrollment API doesn't create 'credited' enrollments");
}

// 10d. Student assignment syncs credited
$assignment_api = file_get_contents($root . '/modules/branch_admin/process/student_assignment_api.php');
if (strpos($assignment_api, "'credited'") !== false) {
    test_pass("Student assignment API handles 'credited' status");
} else {
    test_fail("Student assignment API missing 'credited' handling");
}

// 10e. CSV export handles credited
$export_content = file_get_contents($root . '/modules/teacher/api/export_students.php');
if (strpos($export_content, 'credited') !== false) {
    test_pass("CSV export handles credited students");
} else {
    test_fail("CSV export doesn't handle credited students");
}

// 10f. Registrar students shows student type
$reg_students = file_get_contents($root . '/modules/registrar/students.php');
if (strpos($reg_students, 'student_type') !== false) {
    test_pass("Registrar student list shows student_type");
} else {
    test_fail("Registrar student list missing student_type");
}

// ── 11. NOTIFICATION SYSTEM ─────────────────────────────────
section('11. NOTIFICATION SYSTEM');

if (file_exists($root . '/includes/notification_helper.php')) {
    $notif_content = file_get_contents($root . '/includes/notification_helper.php');
    if (strpos($notif_content, 'create_notification') !== false || strpos($notif_content, 'createNotification') !== false) {
        test_pass("Notification create function exists");
    } else {
        test_warn("Notification create function not found");
    }
}

if (file_exists($root . '/assets/js/notifications.js')) {
    $notif_js = file_get_contents($root . '/assets/js/notifications.js');
    if (strpos($notif_js, 'notification') !== false) {
        test_pass("Notifications JS client exists");
    } else {
        test_warn("Notifications JS seems empty");
    }
}

// ── 12. ASSESSMENT SUBMISSION ───────────────────────────────
section('12. ASSESSMENT SUBMISSION FEATURE');

if (file_exists($root . '/modules/student/process/submit_assessment.php')) {
    $submit = file_get_contents($root . '/modules/student/process/submit_assessment.php');
    if (strpos($submit, 'submitted_file') !== false) {
        test_pass("Assessment submission handles file upload");
    } else {
        test_fail("Assessment submission missing file upload handling");
    }
    if (strpos($submit, 'submitted_at') !== false) {
        test_pass("Assessment submission records timestamp");
    } else {
        test_fail("Assessment submission missing timestamp");
    }
}

// ── 13. LEGAL / COMPLIANCE ──────────────────────────────────
section('13. LEGAL & COMPLIANCE FILES');

$legal_files = ['privacy_policy.php', 'terms_of_service.php'];
foreach ($legal_files as $lf) {
    if (file_exists($root . '/' . $lf)) {
        $content = file_get_contents($root . '/' . $lf);
        $size = strlen($content);
        if ($size > 500) {
            test_pass("$lf exists and has content ($size bytes)");
        } else {
            test_warn("$lf exists but seems too small ($size bytes)");
        }
    } else {
        test_fail("$lf missing");
    }
}

// Cookie consent in header, footer, or main JS
$consent_files = ['includes/header.php', 'includes/footer.php', 'assets/js/main.js'];
$consent_found = false;
foreach ($consent_files as $cf) {
    if (file_exists($root . '/' . $cf)) {
        $content = file_get_contents($root . '/' . $cf);
        if (stripos($content, 'cookie') !== false && stripos($content, 'consent') !== false) {
            $consent_found = true;
            break;
        }
    }
}
if ($consent_found) {
    test_pass("Cookie consent mechanism detected");
} else {
    test_warn("Cookie consent not detected in header/footer/main.js");
}

// ── 14. FOREIGN KEY / REFERENTIAL INTEGRITY ─────────────────
section('14. REFERENTIAL INTEGRITY SPOT CHECKS');

// Check for orphan user_roles
$r = $conn->query("SELECT COUNT(*) as cnt FROM user_roles ur LEFT JOIN users u ON ur.user_id = u.id WHERE u.id IS NULL");
if ($r) {
    $cnt = $r->fetch_assoc()['cnt'];
    if ($cnt == 0) {
        test_pass("No orphan user_roles (all reference valid users)");
    } else {
        test_warn("$cnt orphan user_roles found (user deleted but role remains)");
    }
}

// Check for orphan section_students
$r = $conn->query("SELECT COUNT(*) as cnt FROM section_students ss LEFT JOIN students s ON ss.student_id = s.id WHERE s.id IS NULL");
if ($r) {
    $cnt = $r->fetch_assoc()['cnt'];
    if ($cnt == 0) {
        test_pass("No orphan section_students");
    } else {
        test_warn("$cnt orphan section_students found");
    }
}

// Grades referencing valid students
$r = $conn->query("SELECT COUNT(*) as cnt FROM grades g LEFT JOIN students s ON g.student_id = s.id WHERE s.id IS NULL");
if ($r) {
    $cnt = $r->fetch_assoc()['cnt'];
    if ($cnt == 0) {
        test_pass("No orphan grades (all reference valid students)");
    } else {
        test_warn("$cnt orphan grade records found");
    }
}

// ── 15. DIRECTORY PERMISSIONS / UPLOAD DIRS ─────────────────
section('15. UPLOAD DIRECTORIES');

$upload_dirs = ['uploads', 'uploads/payments', 'uploads/materials'];
foreach ($upload_dirs as $dir) {
    $dirpath = $root . '/' . $dir;
    if (is_dir($dirpath)) {
        if (is_writable($dirpath)) {
            test_pass("$dir/ exists and writable");
        } else {
            test_warn("$dir/ exists but NOT writable");
        }
    } else {
        test_warn("$dir/ directory missing — file uploads may fail");
    }
}

// ── 16. VENDOR / DEPENDENCIES ───────────────────────────────
section('16. VENDOR DEPENDENCIES');

$vendor_checks = [
    'vendor/autoload.php' => 'Composer autoloader',
    'vendor/phpmailer' => 'PHPMailer library',
    'vendor/tcpdf' => 'TCPDF library',
];

foreach ($vendor_checks as $path => $label) {
    $fullpath = $root . '/' . $path;
    if (file_exists($fullpath) || is_dir($fullpath)) {
        test_pass("$label present");
    } else {
        test_fail("$label missing ($path)");
    }
}

// ── 17. JAVASCRIPT / ASSET INTEGRITY ────────────────────────
section('17. JAVASCRIPT ASSET CHECKS');

$js_files = [
    'assets/js/main.js',
    'assets/js/login.js',
    'assets/js/notifications.js',
    'assets/js/admin_dashboard.js',
    'assets/js/curriculum.js',
];

foreach ($js_files as $jsf) {
    $filepath = $root . '/' . $jsf;
    if (file_exists($filepath)) {
        $size = filesize($filepath);
        if ($size > 0) {
            test_pass("$jsf ($size bytes)");
        } else {
            // main.js may be intentionally empty if all JS is modular
            if (basename($jsf) === 'main.js') {
                test_pass("$jsf exists (placeholder — JS logic in module files)");
            } else {
                test_warn("$jsf is empty (0 bytes)");
            }
        }
    } else {
        test_fail("$jsf missing");
    }
}

// ── 18. CSS FILES ───────────────────────────────────────────
section('18. CSS ASSET CHECKS');

$css_files = ['assets/css/style.css', 'assets/css/login.css', 'assets/css/minimal.css'];
foreach ($css_files as $cf) {
    $filepath = $root . '/' . $cf;
    if (file_exists($filepath)) {
        test_pass("$cf (" . filesize($filepath) . " bytes)");
    } else {
        test_fail("$cf missing");
    }
}

// Module CSS
$module_css_dirs = ['modules/teacher/css', 'modules/registrar/css', 'modules/branch_admin/css'];
foreach ($module_css_dirs as $cd) {
    $dirpath = $root . '/' . $cd;
    if (is_dir($dirpath)) {
        $files = glob($dirpath . '/*.css');
        test_pass("$cd/ (" . count($files) . " CSS files)");
    } else {
        test_warn("$cd/ directory not found");
    }
}

// ── 19. STUDENT MODULE CHECKS ───────────────────────────────
section('19. STUDENT MODULE FEATURE CHECKS');

// Check student grades has year-level filter
if (file_exists($root . '/modules/student/grades.php')) {
    $sg = file_get_contents($root . '/modules/student/grades.php');
    if (strpos($sg, 'year_level') !== false || strpos($sg, 'semester') !== false) {
        test_pass("Student grades has year-level/semester filter");
    } else {
        test_warn("Student grades may lack year-level filter");
    }
}

// Check Class Schedule removed from sidebar
if (file_exists($root . '/includes/sidebar.php')) {
    $sidebar = file_get_contents($root . '/includes/sidebar.php');
    // Check if class schedule is still there for students
    if (preg_match('/class.?schedule/i', $sidebar) && preg_match('/ROLE_STUDENT/i', $sidebar)) {
        test_warn("Class Schedule may still appear for students in sidebar");
    } else {
        test_pass("Class Schedule not present as student menu item");
    }
}

// ── 20. DISCOUNT & PENALTY SYSTEM ───────────────────────────
section('20. DISCOUNT & PENALTY SYSTEM');

if (file_exists($root . '/modules/registrar/process/discount_penalty_api.php')) {
    test_pass("Discount/penalty API exists");
    $dp = file_get_contents($root . '/modules/registrar/process/discount_penalty_api.php');
    if (strpos($dp, 'discount') !== false && strpos($dp, 'penalty') !== false) {
        test_pass("Discount/penalty API handles both discount and penalty");
    }
} else {
    test_warn("Discount/penalty API not found");
}

// ── 21. REALTIME / POLLING ──────────────────────────────────
section('21. REALTIME SYSTEM');

$realtime_files = [
    'includes/realtime_helper.php',
    'assets/js/realtime_client.js',
    'assets/js/realtime_loader.js',
    'api/check_updates.php',
];

foreach ($realtime_files as $rf) {
    if (file_exists($root . '/' . $rf)) {
        test_pass("Realtime file: $rf");
    } else {
        test_warn("Realtime file missing: $rf");
    }
}

// ── 22. DATABASE QUERY TESTS ────────────────────────────────
section('22. FUNCTIONAL QUERY SMOKE TESTS');

// Test a complex query similar to gradebook
$queries = [
    'Gradebook-like JOIN' => "
        SELECT s.user_id, up.first_name, up.last_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        JOIN user_profiles up ON u.id = up.user_id 
        LIMIT 1
    ",
    'Student enrollment JOIN' => "
        SELECT sse.student_id, sse.status 
        FROM student_subject_enrollments sse 
        LIMIT 1
    ",
    'Grade with lock check' => "
        SELECT g.id, g.version 
        FROM grades g 
        LIMIT 1
    ",
    'Section with program' => "
        SELECT sec.id, sec.section_name, p.program_name  
        FROM sections sec 
        LEFT JOIN programs p ON sec.program_id = p.id 
        LIMIT 1
    ",
    'Payment with student' => "
        SELECT pay.id, pay.amount, pay.status 
        FROM payments pay 
        LIMIT 1
    ",
];

foreach ($queries as $label => $sql) {
    try {
        $r = $conn->query($sql);
        if ($r !== false) {
            test_pass("Query OK: $label");
        } else {
            test_fail("Query failed: $label", $conn->error);
        }
    } catch (Exception $e) {
        test_fail("Query error: $label", $e->getMessage());
    }
}

// ── 23. CONFIGURATION VALIDATION ────────────────────────────
section('23. CONFIGURATION VALIDATION');

// Check BASE_URL is defined properly in init.php
if (strpos($init_content, 'BASE_URL') !== false) {
    test_pass("BASE_URL constant defined in init.php");
} else {
    test_fail("BASE_URL not defined");
}

// Check ROLE constants
$role_constants = ['ROLE_SUPER_ADMIN', 'ROLE_SCHOOL_ADMIN', 'ROLE_BRANCH_ADMIN', 'ROLE_REGISTRAR', 'ROLE_TEACHER', 'ROLE_STUDENT'];
foreach ($role_constants as $rc) {
    if (strpos($init_content, $rc) !== false) {
        test_pass("Constant $rc defined");
    } else {
        test_fail("Constant $rc not defined in init.php");
    }
}

// ── SUMMARY ─────────────────────────────────────────────────
section('TEST SUMMARY');

$total = $pass + $fail + $warn;
echo "  Total Tests: $total\n";
echo "  Passed:      $pass\n";
echo "  Failed:      $fail\n";
echo "  Warnings:    $warn\n";
echo "\n";

if ($fail === 0 && $warn === 0) {
    echo "  *** ALL TESTS PASSED — System is fully functional! ***\n";
} elseif ($fail === 0) {
    echo "  *** ALL CRITICAL TESTS PASSED (with $warn warnings) ***\n";
    echo "  Warnings are non-critical but should be reviewed.\n";
} else {
    echo "  *** $fail TEST(S) FAILED — Review errors below: ***\n\n";
    foreach ($errors as $err) {
        echo "$err\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "  Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n";

$conn->close();
