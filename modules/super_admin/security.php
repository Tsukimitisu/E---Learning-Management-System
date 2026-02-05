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
?>

<link rel="stylesheet" href="css/security.css"> 

<!-- Part 1:  Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: #003366;"><i class="bi bi-shield-lock me-2"></i>Security & Audit</h4>
        <p class="text-muted small mb-0">Monitor system integrity and administrative logs</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm px-4" onclick="exportAuditLogs({})">
            <i class="bi bi-download me-2"></i>Export Audit Logs
        </button>
        <button class="btn btn-info btn-sm px-4" onclick="exportSecurityLogs({})">
            <i class="bi bi-download me-2"></i>Export Security Logs
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
                <div class="mb-3">
                    <input type="text" id="security-search" class="form-control" placeholder="Search security logs...">
                    <select id="event-type-filter" class="form-select mt-2">
                        <option value="">All Event Types</option>
                        <option value="login_failed">Login Failed</option>
                        <option value="login_success">Login Success</option>
                        <option value="force_logout">Force Logout</option>
                        <option value="suspicious_activity">Suspicious Activity</option>
                    </select>
                    <select id="severity-filter" class="form-select mt-2">
                        <option value="">All Severities</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="info">Info</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadSecurityLogs(1, {search: document.getElementById('security-search').value, event_type: document.getElementById('event-type-filter').value, severity: document.getElementById('severity-filter').value})">Search</button>
                </div>
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
                        <tbody id="security-logs-tbody">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div id="security-logs-pagination" class="mt-3"></div>
            </div>

            <!-- Tab 2: Audit Trail  -->
            <div class="tab-pane fade" id="audit-trail" role="tabpanel">
                <div class="mb-3">
                    <input type="text" id="audit-search" class="form-control" placeholder="Search audit logs...">
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadAuditLogs(1, {search: document.getElementById('audit-search').value})">Search</button>
                </div>
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
                        <tbody id="audit-logs-tbody">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div id="audit-logs-pagination" class="mt-3"></div>
            </div>

            <!-- Tab 3: Sessions -->
            <div class="tab-pane fade" id="session-mgt" role="tabpanel">
                <div class="mb-3">
                    <input type="text" id="user-search" class="form-control" placeholder="Search users by name or ID...">
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="searchUsersForLogout()">Search Users</button>
                </div>
                <div id="users-list-container"></div>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Admin Dashboard Library -->
<script src="../../assets/js/admin_dashboard.js"></script>

<script>
    // Load logs when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadSecurityLogs(1, {});
        loadAuditLogs(1, {});
    });

    // Search function for force logout
    function searchUsersForLogout() {
        const searchTerm = document.getElementById('user-search').value;
        if (!searchTerm.trim()) {
            alert('Please enter a user name or ID');
            return;
        }
        // This would require an API to search users - placeholder for now
        alert('Search API not yet implemented. Use force logout from user management.');
    }
</script>
</body>
</html>