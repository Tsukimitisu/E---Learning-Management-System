<?php
/**
 * Sanitize user input
 */
if (!function_exists('clean_input')) {
    function clean_input($data) {
        if (is_array($data)) {
            return array_map('clean_input', $data);
        }
        if ($data === null) return '';
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Redirect helper
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit();
        } else {
            echo '<script>window.location.href="' . $url . '";</script>';
            exit();
        }
    }
}

/**
 * Check if user is authenticated
 */
if (!function_exists('check_auth')) {
    function check_auth() {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            redirect(BASE_URL . 'index.php');
            exit();
        }
        return true;
    }
}

/**
 * Check if user has specific role
 */
if (!function_exists('check_role')) {
    function check_role($allowed_roles = []) {
        check_auth();
        
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;

        if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
            redirect(BASE_URL . 'dashboard.php');
            exit();
        }
        return true;
    }
}

/**
 * Check if user is School Admin (curriculum management authority)
 */
if (!function_exists('check_school_admin')) {
    function check_school_admin() {
        check_auth();
        
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;

        if ($user_role != ROLE_SCHOOL_ADMIN && $user_role != ROLE_SUPER_ADMIN) {
            redirect(BASE_URL . 'dashboard.php');
            exit();
        }
        return true;
    }
}

/**
 * Check if user can access curriculum management (School Admin only)
 */
if (!function_exists('require_curriculum_access')) {
    function require_curriculum_access() {
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
        
        if (!isset($_SESSION['user_id']) || ($user_role != ROLE_SCHOOL_ADMIN && $user_role != ROLE_SUPER_ADMIN)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Curriculum management requires School Administrator privileges.']);
            exit();
        }
        return true;
    }
}

/**
 * Get current timestamp
 */
if (!function_exists('get_timestamp')) {
    function get_timestamp() {
        return date('Y-m-d H:i:s');
    }
}

/**
 * Get Client IP Address
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return $ip;
    }
}

/**
 * Get current user's branch id from session
 */
if (!function_exists('get_user_branch_id')) {
    function get_user_branch_id() {
        return $_SESSION['branch_id'] ?? null;
    }
}

/**
 * Enforce branch assignment for branch admins
 */
if (!function_exists('require_branch_assignment')) {
    function require_branch_assignment() {
        $user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
        $branch_id = $_SESSION['branch_id'] ?? null;

        if ($user_role == ROLE_BRANCH_ADMIN && empty($branch_id)) {
            echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
            exit();
        }

        return true;
    }
}

/**
 * Log audit trail
 */
if (!function_exists('log_audit')) {
    function log_audit($conn, $user_id, $action, $details = null, $ip_address = null) {
        if ($ip_address === null) {
            $ip_address = get_client_ip();
        }

        static $has_details = null;
        if ($has_details === null) {
            $check = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'details'");
            $has_details = $check && $check->num_rows > 0;
        }

        if ($has_details) {
            $sql = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
        } else {
            $sql = "INSERT INTO audit_logs (user_id, action, ip_address, timestamp) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $action, $ip_address);
        }

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

/**
 * Generate next student number
 * Format: YYYY-XXXX (e.g., 2026-0001)
 */
if (!function_exists('generate_student_number')) {
    function generate_student_number($conn) {
        $current_year = date('Y');

        $query = "SELECT student_no FROM students 
                  WHERE student_no LIKE '$current_year-%' 
                  ORDER BY student_no DESC LIMIT 1";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $last_no = $result->fetch_assoc()['student_no'];
            $parts = explode('-', $last_no);
            $next_num = intval($parts[1] ?? 0) + 1;
        } else {
            $next_num = 1;
        }

        return sprintf('%s-%04d', $current_year, $next_num);
    }
}

/**
 * Format currency for Philippine Peso
 */
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return '₱' . number_format((float)$amount, 2);
    }
}

/**
 * Check student payment status
 */
if (!function_exists('check_payment_status')) {
    function check_payment_status($conn, $student_id) {
        $query = "SELECT 
                    SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as verified_amount,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                  FROM payments WHERE student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return [
            'verified_amount' => $result['verified_amount'] ?? 0,
            'pending_count' => $result['pending_count'] ?? 0,
            'has_payment' => ($result['verified_amount'] ?? 0) > 0
        ];
    }
}

/**
 * Calculate GPA from grades array
 */
if (!function_exists('calculate_gpa')) {
    function calculate_gpa($grades) {
        if (empty($grades)) return 0;

        $total_points = 0;
        $total_units = 0;

        foreach ($grades as $grade) {
            $total_points += ($grade['final_grade'] ?? 0) * ($grade['units'] ?? 0);
            $total_units += ($grade['units'] ?? 0);
        }

        return $total_units > 0 ? round($total_points / $total_units, 2) : 0;
    }
}

/**
 * Determine academic standing based on GPA
 */
if (!function_exists('get_academic_standing')) {
    function get_academic_standing($gpa) {
        if ($gpa >= 90) return "Dean's List";
        if ($gpa >= 85) return 'Good Standing';
        if ($gpa >= 75) return 'Satisfactory';
        return 'Probation';
    }
}

/**
 * Generate certificate reference number
 */
if (!function_exists('generate_certificate_reference')) {
    function generate_certificate_reference($type, $student_id) {
        $prefix = [
            'enrollment' => 'EC',
            'grade_report' => 'GR',
            'completion' => 'CC',
            'transcript' => 'TOR'
        ];

        $suffix = random_int(1000, 9999);
        return ($prefix[$type] ?? 'CERT') . '-' . date('Ymd') . '-' . str_pad($student_id, 4, '0', STR_PAD_LEFT) . '-' . $suffix;
    }
}

/**
 * Format academic year display
 */
if (!function_exists('format_academic_year')) {
    function format_academic_year($year_start) {
        return $year_start . '-' . ($year_start + 1);
    }
}
?>