<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Security & Audit";

// Fetch security statistics
$stats = [
    'failed_logins_today' => 0,
    'locked_accounts' => 0,
    'suspicious_activities' => 0,
    'total_sessions' => 0
];

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type = 'login_failed' AND DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) {
    $stats['failed_logins_today'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'");
if ($row = $result->fetch_assoc()) {
    $stats['locked_accounts'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type = 'suspicious_activity' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($row = $result->fetch_assoc()) {
    $stats['suspicious_activities'] = $row['count'];
}

// Fetch recent security logs
$security_logs = $conn->query("
    SELECT sl.*, CONCAT(up.first_name, ' ', up.last_name) as user_name
    FROM security_logs sl
    LEFT JOIN user_profiles up ON sl.user_id = up.user_id
    ORDER BY sl.created_at DESC
    LIMIT 50
");

// Fetch audit logs
$audit_logs = $conn->query("
    SELECT al.*, CONCAT(up.first_name, ' ', up.last_name) as user_name
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    ORDER BY al.timestamp DESC
    LIMIT 100
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Security & Audit</h4>
                    <small class="text-muted">Monitor system security and access logs</small>
                </div>
                <div>
                    <button class="btn btn-minimal me-2" onclick="exportLogs()">
                        <i class="bi bi-download"></i> Export Logs
                    </button>
                    <a href="dashboard.php" class="btn btn-minimal">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Security Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #dc3545;">
                    <p><i class="bi bi-x-circle"></i> Failed Logins Today</p>
                    <h3 style="color: #dc3545;"><?php echo number_format($stats['failed_logins_today']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #ffc107;">
                    <p><i class="bi bi-lock"></i> Locked Accounts</p>
                    <h3 style="color: #ffc107;"><?php echo number_format($stats['locked_accounts']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #fd7e14;">
                    <p><i class="bi bi-exclamation-triangle"></i> Suspicious Activity</p>
                    <h3 style="color: #fd7e14;"><?php echo number_format($stats['suspicious_activities']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #28a745;">
                    <p><i class="bi bi-shield-check"></i> System Status</p>
                    <h3 style="color: #28a745; font-size: 1.5rem;">Secure</h3>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="minimal-card">
            <ul class="nav nav-tabs" id="securityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                        <i class="bi bi-shield-lock"></i> Security Logs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button">
                        <i class="bi bi-clock-history"></i> Audit Trail
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button">
                        <i class="bi bi-people"></i> Active Sessions
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-3" id="securityTabsContent">
                <!-- Security Logs Tab -->
                <div class="tab-pane fade show active" id="security" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead style="background-color: var(--light-gray);">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Event Type</th>
                                    <th>IP Address</th>
                                    <th>Severity</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $security_logs->fetch_assoc()): 
                                    $severity_colors = [
                                        'low' => 'secondary',
                                        'medium' => 'info',
                                        'high' => 'warning',
                                        'critical' => 'danger'
                                    ];
                                    $badge_color = $severity_colors[$log['severity']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><small><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="badge bg-<?php echo $log['event_type'] == 'login_failed' ? 'danger' : 'success'; ?>">
                                        <?php echo str_replace('_', ' ', ucwords($log['event_type'])); ?>
                                    </span></td>
                                    <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                    <td><span class="badge bg-<?php echo $badge_color; ?>"><?php echo strtoupper($log['severity']); ?></span></td>
                                    <td><small><?php echo htmlspecialchars($log['details'] ?? '-'); ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Audit Trail Tab -->
                <div class="tab-pane fade" id="audit" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead style="background-color: var(--light-gray);">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $audit_logs->fetch_assoc()): ?>
                                <tr>
                                    <td><small><?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Sessions Tab -->
                <div class="tab-pane fade" id="sessions" role="tabpanel">
                    <div class="alert alert-info alert-minimal" style="border-left-color: #17a2b8;">
                        <i class="bi bi-info-circle"></i> Session management requires additional implementation
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-minimal" onclick="alert('Force logout all users - Implementation pending')">
                            <i class="bi bi-power"></i> Force Logout All Users
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function exportLogs() {
    window.location.href = 'process/export_logs.php';
}
</script>
</body>
</html>