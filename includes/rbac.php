<?php
/**
 * Role-Based Access Control (RBAC) System
 * ELMS - Electronic Learning Management System
 * 
 * Features:
 * - Strict permission boundaries per role
 * - Secure session handling with fingerprinting
 * - Concurrent user support with conflict-free operations
 * - CSRF protection
 * - Session timeout management
 * - Audit logging
 */

// Prevent direct access
if (!defined('BASE_URL')) {
    die('Direct access not permitted');
}

/**
 * Role Hierarchy Definition
 * Lower numbers = higher authority
 */
define('ROLE_HIERARCHY', [
    ROLE_SUPER_ADMIN => 1,   // Highest authority
    ROLE_SCHOOL_ADMIN => 2,
    ROLE_BRANCH_ADMIN => 3,
    ROLE_REGISTRAR => 4,
    ROLE_TEACHER => 4,       // Same level as Registrar
    ROLE_STUDENT => 5        // Lowest authority
]);

/**
 * Module Permissions Matrix
 * Defines which roles can access which modules
 */
define('MODULE_PERMISSIONS', [
    // Super Admin modules
    'super_admin' => [ROLE_SUPER_ADMIN],
    'super_admin/users' => [ROLE_SUPER_ADMIN],
    'super_admin/branches' => [ROLE_SUPER_ADMIN],
    'super_admin/security_settings' => [ROLE_SUPER_ADMIN],
    'super_admin/api_management' => [ROLE_SUPER_ADMIN],
    
    // School Admin modules
    'school_admin' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN],
    'school_admin/curriculum' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN],
    'school_admin/programs' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN],
    'school_admin/academic_years' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN],
    
    // Branch Admin modules
    'branch_admin' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, ROLE_BRANCH_ADMIN],
    'branch_admin/teachers' => [ROLE_BRANCH_ADMIN],
    'branch_admin/registrars' => [ROLE_BRANCH_ADMIN],
    'branch_admin/sections' => [ROLE_BRANCH_ADMIN],
    'branch_admin/scheduling' => [ROLE_BRANCH_ADMIN],
    
    // Registrar modules
    'registrar' => [ROLE_REGISTRAR],
    'registrar/students' => [ROLE_REGISTRAR],
    'registrar/enrollment' => [ROLE_REGISTRAR],
    'registrar/payments' => [ROLE_REGISTRAR],
    'registrar/records' => [ROLE_REGISTRAR],
    
    // Teacher modules
    'teacher' => [ROLE_TEACHER],
    'teacher/gradebook' => [ROLE_TEACHER],
    'teacher/attendance' => [ROLE_TEACHER],
    'teacher/materials' => [ROLE_TEACHER],
    
    // Student modules
    'student' => [ROLE_STUDENT],
    'student/grades' => [ROLE_STUDENT],
    'student/schedule' => [ROLE_STUDENT],
    'student/enrollment' => [ROLE_STUDENT],
    
    // Common modules (all authenticated users)
    'common/account_settings' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, ROLE_BRANCH_ADMIN, ROLE_REGISTRAR, ROLE_TEACHER, ROLE_STUDENT],
    'common/announcements' => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, ROLE_BRANCH_ADMIN, ROLE_REGISTRAR, ROLE_TEACHER, ROLE_STUDENT]
]);

/**
 * Action Permissions (CRUD operations)
 */
define('ACTION_PERMISSIONS', [
    'create_user' => [
        ROLE_SUPER_ADMIN => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN],
        ROLE_SCHOOL_ADMIN => [ROLE_BRANCH_ADMIN],
        ROLE_BRANCH_ADMIN => [ROLE_TEACHER, ROLE_REGISTRAR],
        ROLE_REGISTRAR => [ROLE_STUDENT]
    ],
    'edit_user' => [
        ROLE_SUPER_ADMIN => [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, ROLE_BRANCH_ADMIN, ROLE_REGISTRAR, ROLE_TEACHER, ROLE_STUDENT],
        ROLE_SCHOOL_ADMIN => [ROLE_BRANCH_ADMIN],
        ROLE_BRANCH_ADMIN => [ROLE_TEACHER, ROLE_REGISTRAR],
        ROLE_REGISTRAR => [ROLE_STUDENT]
    ],
    'delete_user' => [
        ROLE_SUPER_ADMIN => [ROLE_SCHOOL_ADMIN, ROLE_BRANCH_ADMIN, ROLE_REGISTRAR, ROLE_TEACHER, ROLE_STUDENT],
        ROLE_SCHOOL_ADMIN => [ROLE_BRANCH_ADMIN],
        ROLE_BRANCH_ADMIN => [ROLE_TEACHER, ROLE_REGISTRAR],
        ROLE_REGISTRAR => [ROLE_STUDENT]
    ],
    'view_grades' => [
        ROLE_SUPER_ADMIN => true,
        ROLE_SCHOOL_ADMIN => true,
        ROLE_BRANCH_ADMIN => true,
        ROLE_REGISTRAR => true,
        ROLE_TEACHER => 'own_students', // Only their assigned students
        ROLE_STUDENT => 'own_grades'    // Only their own grades
    ],
    'edit_grades' => [
        ROLE_TEACHER => 'own_students'
    ],
    'manage_curriculum' => [
        ROLE_SUPER_ADMIN => true,
        ROLE_SCHOOL_ADMIN => true
    ],
    'manage_enrollment' => [
        ROLE_REGISTRAR => true
    ],
    'view_reports' => [
        ROLE_SUPER_ADMIN => true,
        ROLE_SCHOOL_ADMIN => true,
        ROLE_BRANCH_ADMIN => 'own_branch',
        ROLE_REGISTRAR => 'own_branch'
    ]
]);

/**
 * RBAC Class for managing access control
 */
class RBAC {
    private static $instance = null;
    private $conn;
    private $user_id;
    private $role_id;
    private $branch_id;
    
    private function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->user_id = $_SESSION['user_id'] ?? null;
        $this->role_id = $_SESSION['role_id'] ?? null;
        $this->branch_id = $_SESSION['branch_id'] ?? null;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize secure session with fingerprinting
     */
    public static function initSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
        }
        
        // Generate session fingerprint
        $fingerprint = self::generateSessionFingerprint();
        
        // Check if session is valid
        if (isset($_SESSION['fingerprint'])) {
            if ($_SESSION['fingerprint'] !== $fingerprint) {
                // Potential session hijacking - destroy session
                self::destroySession();
                return false;
            }
        } else {
            $_SESSION['fingerprint'] = $fingerprint;
        }
        
        // Check session timeout
        if (self::isSessionExpired()) {
            self::destroySession();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Generate session fingerprint for security
     */
    private static function generateSessionFingerprint() {
        $data = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $data .= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        // Note: Don't include IP for users behind proxies
        return hash('sha256', $data);
    }
    
    /**
     * Check if session has expired
     */
    private static function isSessionExpired() {
        $timeout = (int)get_security_setting('session_timeout', 60) * 60; // Convert to seconds
        
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeout) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Destroy session securely
     */
    public static function destroySession() {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Regenerate session ID (call after login)
     */
    public static function regenerateSession() {
        session_regenerate_id(true);
        $_SESSION['fingerprint'] = self::generateSessionFingerprint();
        $_SESSION['last_activity'] = time();
        $_SESSION['session_created'] = time();
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return !empty($this->user_id) && !empty($this->role_id);
    }
    
    /**
     * Check if user has required role
     */
    public function hasRole($required_roles) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        if (!is_array($required_roles)) {
            $required_roles = [$required_roles];
        }
        
        return in_array($this->role_id, $required_roles);
    }
    
    /**
     * Check if user can access a module
     */
    public function canAccessModule($module) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super Admin can access everything
        if ($this->role_id == ROLE_SUPER_ADMIN) {
            return true;
        }
        
        if (isset(MODULE_PERMISSIONS[$module])) {
            return in_array($this->role_id, MODULE_PERMISSIONS[$module]);
        }
        
        return false;
    }
    
    /**
     * Check if user can perform an action on a target role
     */
    public function canPerformAction($action, $target_role = null) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        if (!isset(ACTION_PERMISSIONS[$action])) {
            return false;
        }
        
        $permissions = ACTION_PERMISSIONS[$action];
        
        if (!isset($permissions[$this->role_id])) {
            return false;
        }
        
        $permission = $permissions[$this->role_id];
        
        // Boolean permission
        if (is_bool($permission)) {
            return $permission;
        }
        
        // Array of allowed target roles
        if (is_array($permission)) {
            return $target_role === null || in_array($target_role, $permission);
        }
        
        // String permission (special cases)
        return $permission;
    }
    
    /**
     * Check if user has higher authority than target role
     */
    public function hasHigherAuthority($target_role_id) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $user_level = ROLE_HIERARCHY[$this->role_id] ?? 999;
        $target_level = ROLE_HIERARCHY[$target_role_id] ?? 999;
        
        return $user_level < $target_level;
    }
    
    /**
     * Check branch access (for multi-branch operations)
     */
    public function canAccessBranch($branch_id) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super Admin and School Admin can access all branches
        if (in_array($this->role_id, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN])) {
            return true;
        }
        
        // Others can only access their own branch
        return $this->branch_id == $branch_id;
    }
    
    /**
     * Check if user can access a specific resource
     */
    public function canAccessResource($resource_type, $resource_id, $owner_id = null) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super Admin can access everything
        if ($this->role_id == ROLE_SUPER_ADMIN) {
            return true;
        }
        
        switch ($resource_type) {
            case 'student':
                return $this->canAccessStudent($resource_id);
            case 'grade':
                return $this->canAccessGrade($resource_id, $owner_id);
            case 'class':
                return $this->canAccessClass($resource_id);
            default:
                return false;
        }
    }
    
    /**
     * Check student access based on role
     */
    private function canAccessStudent($student_id) {
        switch ($this->role_id) {
            case ROLE_SCHOOL_ADMIN:
                return true;
            case ROLE_BRANCH_ADMIN:
            case ROLE_REGISTRAR:
                // Check if student is in their branch
                $stmt = $this->conn->prepare("
                    SELECT 1 FROM students s
                    JOIN user_profiles up ON s.user_id = up.user_id
                    WHERE s.id = ? AND up.branch_id = ?
                ");
                $stmt->bind_param("ii", $student_id, $this->branch_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            case ROLE_TEACHER:
                // Check if student is in teacher's class
                $stmt = $this->conn->prepare("
                    SELECT 1 FROM enrollments e
                    JOIN class_schedules cs ON e.class_schedule_id = cs.id
                    WHERE e.student_id = ? AND cs.teacher_id = ?
                ");
                $stmt->bind_param("ii", $student_id, $this->user_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            case ROLE_STUDENT:
                // Students can only access their own data
                $stmt = $this->conn->prepare("SELECT 1 FROM students WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $student_id, $this->user_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            default:
                return false;
        }
    }
    
    /**
     * Check grade access
     */
    private function canAccessGrade($grade_id, $student_user_id = null) {
        switch ($this->role_id) {
            case ROLE_SCHOOL_ADMIN:
            case ROLE_BRANCH_ADMIN:
            case ROLE_REGISTRAR:
                return true;
            case ROLE_TEACHER:
                // Teacher can only access grades they've given
                $stmt = $this->conn->prepare("
                    SELECT 1 FROM grades g
                    JOIN class_schedules cs ON g.class_schedule_id = cs.id
                    WHERE g.id = ? AND cs.teacher_id = ?
                ");
                $stmt->bind_param("ii", $grade_id, $this->user_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            case ROLE_STUDENT:
                // Students can only view their own grades
                return $student_user_id !== null && $student_user_id == $this->user_id;
            default:
                return false;
        }
    }
    
    /**
     * Check class access
     */
    private function canAccessClass($class_id) {
        switch ($this->role_id) {
            case ROLE_SCHOOL_ADMIN:
                return true;
            case ROLE_BRANCH_ADMIN:
                $stmt = $this->conn->prepare("
                    SELECT 1 FROM class_schedules cs
                    JOIN sections sec ON cs.section_id = sec.id
                    WHERE cs.id = ? AND sec.branch_id = ?
                ");
                $stmt->bind_param("ii", $class_id, $this->branch_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            case ROLE_TEACHER:
                $stmt = $this->conn->prepare("SELECT 1 FROM class_schedules WHERE id = ? AND teacher_id = ?");
                $stmt->bind_param("ii", $class_id, $this->user_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            case ROLE_STUDENT:
                $stmt = $this->conn->prepare("
                    SELECT 1 FROM enrollments e
                    JOIN students s ON e.student_id = s.id
                    WHERE e.class_schedule_id = ? AND s.user_id = ?
                ");
                $stmt->bind_param("ii", $class_id, $this->user_id);
                $stmt->execute();
                return $stmt->get_result()->num_rows > 0;
            default:
                return false;
        }
    }
    
    /**
     * Log access attempt for audit
     */
    public function logAccess($action, $resource = null, $success = true) {
        global $conn;
        
        $ip = get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $details = json_encode([
            'action' => $action,
            'resource' => $resource,
            'success' => $success,
            'role_id' => $this->role_id,
            'branch_id' => $this->branch_id
        ]);
        
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $action_str = $success ? "RBAC: $action" : "RBAC DENIED: $action";
        $stmt->bind_param("isss", $this->user_id, $action_str, $ip, $details);
        $stmt->execute();
    }
    
    /**
     * Get current user's role ID
     */
    public function getRoleId() {
        return $this->role_id;
    }
    
    /**
     * Get current user's branch ID
     */
    public function getBranchId() {
        return $this->branch_id;
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return $this->user_id;
    }
}

/**
 * Middleware function to protect routes
 */
function require_auth($allowed_roles = null, $redirect = true) {
    $rbac = RBAC::getInstance();
    
    // Check authentication
    if (!$rbac->isAuthenticated()) {
        if ($redirect) {
            header('Location: ' . BASE_URL . 'index.php?error=session_expired');
            exit();
        }
        return false;
    }
    
    // Check role if specified
    if ($allowed_roles !== null) {
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        if (!$rbac->hasRole($allowed_roles)) {
            $rbac->logAccess('unauthorized_access', null, false);
            if ($redirect) {
                header('Location: ' . BASE_URL . 'dashboard.php?error=unauthorized');
                exit();
            }
            return false;
        }
    }
    
    return true;
}

/**
 * API middleware for JSON responses
 */
function require_api_auth($allowed_roles = null) {
    header('Content-Type: application/json');
    
    $rbac = RBAC::getInstance();
    
    if (!$rbac->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit();
    }
    
    if ($allowed_roles !== null) {
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        if (!$rbac->hasRole($allowed_roles)) {
            $rbac->logAccess('api_unauthorized', null, false);
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            exit();
        }
    }
    
    return $rbac;
}

/**
 * CSRF Token handling
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    $token = $token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check token expiry (1 hour)
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf() {
    if (!verify_csrf()) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page.']);
        } else {
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
        exit();
    }
}

/**
 * Concurrent operation lock using database
 * Prevents conflicts when multiple users modify same resource
 */
function acquire_resource_lock($resource_type, $resource_id, $timeout = 30) {
    global $conn;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $lock_key = "{$resource_type}_{$resource_id}";
    $expires_at = date('Y-m-d H:i:s', time() + $timeout);
    
    // Clean expired locks
    $conn->query("DELETE FROM resource_locks WHERE expires_at < NOW()");
    
    // Try to acquire lock
    $stmt = $conn->prepare("
        INSERT INTO resource_locks (lock_key, user_id, expires_at) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            user_id = IF(expires_at < NOW() OR user_id = VALUES(user_id), VALUES(user_id), user_id),
            expires_at = IF(expires_at < NOW() OR user_id = VALUES(user_id), VALUES(expires_at), expires_at)
    ");
    $stmt->bind_param("sis", $lock_key, $user_id, $expires_at);
    $stmt->execute();
    
    // Check if we got the lock
    $stmt = $conn->prepare("SELECT user_id FROM resource_locks WHERE lock_key = ? AND user_id = ?");
    $stmt->bind_param("si", $lock_key, $user_id);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}

function release_resource_lock($resource_type, $resource_id) {
    global $conn;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $lock_key = "{$resource_type}_{$resource_id}";
    
    $stmt = $conn->prepare("DELETE FROM resource_locks WHERE lock_key = ? AND user_id = ?");
    $stmt->bind_param("si", $lock_key, $user_id);
    $stmt->execute();
}

/**
 * Check if resource is locked by another user
 */
function is_resource_locked($resource_type, $resource_id) {
    global $conn;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $lock_key = "{$resource_type}_{$resource_id}";
    
    $stmt = $conn->prepare("
        SELECT user_id, 
               (SELECT CONCAT(first_name, ' ', last_name) FROM user_profiles WHERE user_id = resource_locks.user_id) as locked_by
        FROM resource_locks 
        WHERE lock_key = ? AND user_id != ? AND expires_at > NOW()
    ");
    $stmt->bind_param("si", $lock_key, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        return ['locked' => true, 'locked_by' => $result['locked_by']];
    }
    
    return ['locked' => false];
}
