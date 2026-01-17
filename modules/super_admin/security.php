<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Security & Audit";
$stats = [
    'failed_logins_today' => 0,
    'locked_accounts' => 0,
    'suspicious_activities' => 0,
    'total_sessions' => 0
];

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type = 'login_failed' AND DATE(created_at) = '$today'");
if ($row = $result->fetch_assoc()) { $stats['failed_logins_today'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'");
if ($row = $result->fetch_assoc()) { $stats['locked_accounts'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM security_logs WHERE event_type = 'suspicious_activity' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($row = $result->fetch_assoc()) { $stats['suspicious_activities'] = $row['count']; }

$security_logs = $conn->query("
    SELECT sl.*, CONCAT(up.first_name, ' ', up.last_name) as user_name
    FROM security_logs sl
    LEFT JOIN user_profiles up ON sl.user_id = up.user_id
    ORDER BY sl.created_at DESC
    LIMIT 50
");

$audit_logs = $conn->query("
    SELECT al.*, CONCAT(up.first_name, ' ', up.last_name) as user_name
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    ORDER BY al.timestamp DESC
    LIMIT 100
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- THE SCROLL & SIDEBAR LOCK FIX --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }

    #content {
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    .custom-tabs-container {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }

    .nav-pills .nav-link {
        color: #666;
        font-weight: 600;
        border-radius: 8px;
        padding: 10px 20px;
        transition: 0.3s;
    }

    .nav-pills .nav-link.active {
        background-color: #800000;
        color: white;
        box-shadow: 0 4px 12px rgba(128,0,0,0.2);
    }

    /* Table Sticky Headers */
    .table thead th {
        background: #fcfcfc;
        position: sticky;
        top: -1px; 
        z-index: 10;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #888;
        border-bottom: 2px solid #eee;
    }

    .sec-stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        border-left: 4px solid #ddd;
    }
</style>

<!-- Part 1:  Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: #003366;"><i class="bi bi-shield-lock me-2"></i>Security & Audit</h4>
        <p class="text-muted small mb-0">Monitor system integrity and administrative logs</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-dark btn-sm px-4" onclick="exportLogs()">
            <i class="bi bi-download me-2"></i>Export Logs
        </button>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="sec-stat-box" style="border-left-color: #dc3545;">
                <small class="text-muted fw-bold d-block mb-1 text-uppercase">Failed Logins Today</small>
                <h3 class="text-danger fw-bold mb-0"><?php echo number_format($stats['failed_logins_today']); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sec-stat-box" style="border-left-color: #ffc107;">
                <small class="text-muted fw-bold d-block mb-1 text-uppercase">Locked Accounts</small>
                <h3 class="fw-bold mb-0" style="color: #ffc107;"><?php echo number_format($stats['locked_accounts']); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sec-stat-box" style="border-left-color: #fd7e14;">
                <small class="text-muted fw-bold d-block mb-1 text-uppercase">Suspicious (7 Days)</small>
                <h3 class="fw-bold mb-0" style="color: #fd7e14;"><?php echo number_format($stats['suspicious_activities']); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sec-stat-box" style="border-left-color: #28a745;">
                <small class="text-muted fw-bold d-block mb-1 text-uppercase">System Status</small>
                <h3 class="text-success fw-bold mb-0">SECURE</h3>
            </div>
        </div>
    </div>

    <!-- Tabs Container -->
    <div class="custom-tabs-container">
        <!-- Tab Navigation Buttons -->
        <ul class="nav nav-pills mb-4" id="securityTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sec-logs-tab" data-bs-toggle="pill" data-bs-target="#sec-logs" type="button" role="tab">Security Logs</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="audit-trail-tab" data-bs-toggle="pill" data-bs-target="#audit-trail" type="button" role="tab">Audit Trail</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="session-tab" data-bs-toggle="pill" data-bs-target="#session-mgt" type="button" role="tab">Active Sessions</button>
            </li>
        </ul>

        <!-- Tab Content Panes -->
        <div class="tab-content" id="securityTabContent">
            
            <!-- Tab 1: Security Logs  -->
            <div class="tab-pane fade show active" id="sec-logs" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Event</th>
                                <th>IP Address</th>
                                <th>Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $security_logs->fetch_assoc()): 
                                $sev = $log['severity'];
                                $badge = ($sev == 'critical' || $sev == 'high') ? 'danger' : (($sev == 'medium') ? 'warning' : 'secondary');
                            ?>
                            <tr>
                                <td><small class="text-muted"><?php echo date('M d, h:i A', strtotime($log['created_at'])); ?></small></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></td>
                                <td><span class="badge bg-<?php echo ($log['event_type'] == 'login_failed') ? 'danger' : 'success'; ?>"><?php echo str_replace('_', ' ', $log['event_type']); ?></span></td>
                                <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo strtoupper($sev); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Audit Trail  -->
            <div class="tab-pane fade" id="audit-trail" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>System User</th>
                                <th>Action Performed</th>
                                <th>Source IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $audit_logs->fetch_assoc()): ?>
                            <tr>
                                <td><small class="text-muted"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></small></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                <td><i class="bi bi-caret-right-fill text-maroon me-2"></i><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Sessions -->
            <div class="tab-pane fade text-center py-5" id="session-mgt" role="tabpanel">
                <i class="bi bi-info-circle display-4 text-muted mb-3"></i>
                <h5>Session Monitoring</h5>
                <p class="text-muted">Detailed session management tools are currently under maintenance.</p>
                <button class="btn btn-outline-danger btn-sm" onclick="alert('Action restricted')">Force Logout All</button>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function exportLogs() {
    window.location.href = 'process/export_logs.php';
}
</script>
</body>
</html>