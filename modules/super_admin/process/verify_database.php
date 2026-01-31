<?php
/**
 * Database Schema Migration for Admin System
 * Ensures all required tables exist for the admin system functionality
 */

require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$migrations = [];
$results = [];

// Migration 1: Create audit_logs table
$migrations['audit_logs'] = "
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action TEXT NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Migration 2: Create security_logs table
$migrations['security_logs'] = "
CREATE TABLE IF NOT EXISTS security_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    severity VARCHAR(20) DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Migration 3: Create security_settings table
$migrations['security_settings'] = "
CREATE TABLE IF NOT EXISTS security_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Migration 4: Create active_sessions table
$migrations['active_sessions'] = "
CREATE TABLE IF NOT EXISTS active_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(255) UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    invalidated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Execute migrations
foreach ($migrations as $table => $sql) {
    try {
        if ($conn->query($sql)) {
            $results[$table] = ['status' => 'success', 'message' => 'Table created or already exists'];
        } else {
            $results[$table] = ['status' => 'error', 'message' => $conn->error];
        }
    } catch (Exception $e) {
        $results[$table] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Seed default security settings if they don't exist
$default_settings = [
    ['registration_enabled', '1', 'boolean', 'Allow new user registration'],
    ['max_login_attempts', '5', 'number', 'Maximum failed login attempts before lockout'],
    ['lockout_duration', '30', 'number', 'Minutes to lock account after failed attempts'],
    ['password_min_length', '8', 'number', 'Minimum password length'],
    ['password_require_uppercase', '1', 'boolean', 'Require uppercase letters in passwords'],
    ['password_require_lowercase', '1', 'boolean', 'Require lowercase letters in passwords'],
    ['password_require_number', '1', 'boolean', 'Require numbers in passwords'],
    ['password_require_special', '0', 'boolean', 'Require special characters in passwords'],
    ['session_timeout', '30', 'number', 'Session timeout in minutes'],
    ['maintenance_mode', '0', 'boolean', 'Enable maintenance mode'],
];

$results['security_settings_seed'] = ['status' => 'success', 'message' => 'Checking default settings...'];

foreach ($default_settings as [$key, $value, $type, $desc]) {
    $check = $conn->query("SELECT * FROM security_settings WHERE setting_key = '$key'");
    if ($check->num_rows == 0) {
        $insert = "INSERT INTO security_settings (setting_key, setting_value, setting_type, description) 
                   VALUES ('$key', '$value', '$type', '$desc')";
        if ($conn->query($insert)) {
            $results["setting_$key"] = ['status' => 'inserted', 'message' => "Setting '$key' created"];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 40px 20px; background: #f8f9fa; }
        .container { max-width: 900px; }
        .card { box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .status-badge { font-weight: 600; padding: 5px 15px; border-radius: 20px; }
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-inserted { background: #cfe2ff; color: #084298; }
        h2 { color: var(--maroon); margin-bottom: 30px; }
        .result-item { padding: 15px; border-bottom: 1px solid #eee; }
        .result-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="bi bi-database-check"></i> Database Migration Results</h2>
    
    <div class="card">
        <div class="card-header" style="background: var(--maroon); color: white; font-weight: 700;">
            Table Creation Status
        </div>
        <div class="card-body p-0">
            <?php foreach ($results as $name => $result): ?>
            <div class="result-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo htmlspecialchars($name); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($result['message']); ?></small>
                </div>
                <span class="status-badge status-<?php echo $result['status']; ?>">
                    <?php echo strtoupper($result['status']); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="alert alert-info border-0">
        <i class="bi bi-info-circle me-2"></i>
        <strong>All tables have been verified and created if necessary.</strong>
        The system is ready to use. You can now access the admin dashboard, settings, and security features.
    </div>

    <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i> Back
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
