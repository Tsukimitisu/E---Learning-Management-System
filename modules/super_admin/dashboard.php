<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Super Admin Dashboard";

// Fetch Statistics
$stats = [
    'total_users' => 0,
    'active_sessions' => 0,
    'total_schools' => 0,
    'system_health' => 'Excellent'
];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if ($row = $result->fetch_assoc()) {
    $stats['total_users'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM schools");
if ($row = $result->fetch_assoc()) {
    $stats['total_schools'] = $row['count'];
}

// Check maintenance mode
$maintenance = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetch_assoc();
$is_maintenance = ($maintenance['setting_value'] ?? '0') == '1';

include '../../includes/header.php';
?>

<style>
:root {
    --maroon: #800000;
    --navy: #003366;
    --white: #FFFFFF;
    --light-gray: #F8F9FA;
    --border: #E0E0E0;
}

body {
    background-color: var(--light-gray);
    font-family: 'Inter', 'Segoe UI', sans-serif;
}

.minimal-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    transition: box-shadow 0.2s;
}

.minimal-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-box {
    background: white;
    border-left: 4px solid var(--maroon);
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.stat-box h3 {
    color: var(--navy);
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
}

.stat-box p {
    color: #666;
    margin: 5px 0 0 0;
    font-size: 0.9rem;
}

.btn-minimal {
    border: 1px solid var(--border);
    background: white;
    color: var(--navy);
    padding: 10px 20px;
    border-radius: 4px;
    transition: all 0.2s;
}

.btn-minimal:hover {
    background: var(--navy);
    color: white;
    border-color: var(--navy);
}

.btn-primary-minimal {
    background: var(--maroon);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
}

.btn-primary-minimal:hover {
    background: #660000;
    color: white;
}

.section-title {
    color: var(--navy);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--maroon);
}

.alert-minimal {
    border-left: 4px solid;
    border-radius: 4px;
    padding: 15px 20px;
}
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <!-- Header -->
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Super Administrator</h4>
                    <small class="text-muted">System-wide control and monitoring</small>
                </div>
                <div>
                    <span class="badge" style="background: var(--navy); padding: 8px 15px;">
                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($is_maintenance): ?>
        <div class="alert alert-warning alert-minimal" style="border-left-color: #ffc107;">
            <i class="bi bi-exclamation-triangle"></i> <strong>Maintenance Mode Active</strong> - System is currently under maintenance
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-box">
                    <p><i class="bi bi-people"></i> Total Users</p>
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: var(--navy);">
                    <p><i class="bi bi-building"></i> Schools</p>
                    <h3><?php echo number_format($stats['total_schools']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #28a745;">
                    <p><i class="bi bi-activity"></i> System Health</p>
                    <h3 style="color: #28a745; font-size: 1.5rem;"><?php echo $stats['system_health']; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #17a2b8;">
                    <p><i class="bi bi-clock-history"></i> Uptime</p>
                    <h3 style="font-size: 1.5rem;">99.9%</h3>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="minimal-card">
            <h5 class="section-title">Quick Actions</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <a href="users.php" class="btn btn-minimal w-100">
                        <i class="bi bi-people"></i><br>User Management
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="system_settings.php" class="btn btn-minimal w-100">
                        <i class="bi bi-gear"></i><br>System Settings
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="security.php" class="btn btn-minimal w-100">
                        <i class="bi bi-shield-check"></i><br>Security & Audit
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="maintenance.php" class="btn btn-minimal w-100">
                        <i class="bi bi-tools"></i><br>Maintenance
                    </a>
                </div>
            </div>
        </div>

        <!-- System Overview -->
        <div class="row">
            <div class="col-md-8">
                <div class="minimal-card">
                    <h5 class="section-title">Recent Activity</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th>User</th>
                                <th>Action</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $logs = $conn->query("
                                SELECT al.action, al.timestamp, 
                                       CONCAT(up.first_name, ' ', up.last_name) as user_name
                                FROM audit_logs al
                                LEFT JOIN user_profiles up ON al.user_id = up.user_id
                                ORDER BY al.timestamp DESC
                                LIMIT 8
                            ");
                            
                            while ($log = $logs->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                <td><small><?php echo htmlspecialchars($log['action']); ?></small></td>
                                <td><small class="text-muted"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></small></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-4">
                <div class="minimal-card">
                    <h5 class="section-title">System Status</h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Database</span>
                            <span class="badge bg-success">Online</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>API Services</span>
                            <span class="badge bg-success">Operational</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Storage</span>
                            <span class="badge bg-success">65% Used</span>
                        </div>
                    </div>
                    <hr>
                    <a href="monitoring.php" class="btn btn-primary-minimal w-100">
                        <i class="bi bi-graph-up"></i> View Full Monitoring
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>