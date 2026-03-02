<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SUPER_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Super Admin Dashboard";

// Statistics Logic (Backend untouched)
$stats = ['total_users' => 0, 'total_schools' => 0, 'system_health' => 'Excellent'];
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
if ($row = $result->fetch_assoc()) { $stats['total_users'] = $row['count']; }
$result = $conn->query("SELECT COUNT(*) as count FROM schools");
if ($row = $result->fetch_assoc()) { $stats['total_schools'] = $row['count']; }

$maintenance = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetch_assoc();
$is_maintenance = ($maintenance['setting_value'] ?? '0') == '1';

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/dashboard.css">

<div class="animate__animated animate__fadeIn">
    <!-- Header Summary -->
    <div class="mb-4 animate__animated animate__fadeInDown">
        <h4 class="fw-bold mb-1" style="color: var(--blue);">Super Administrator Dashboard</h4>
        <p class="text-muted small">System integrity and institutional oversight</p>
    </div>

    <?php if ($is_maintenance): ?>
    <div class="alert alert-warning border-0 shadow-sm animate__animated animate__pulse animate__infinite mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Security Alert:</strong> Maintenance Mode is currently active.
    </div>
    <?php endif; ?>

    <!-- Stats Grid (Animated Zoom In) -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 animate__animated animate__zoomIn delay-1">
            <div class="stat-card">
                <p>Total Active Users</p>
                <h3 id="total-users"><?php echo number_format($stats['total_users']); ?></h3>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-2">
            <div class="stat-card" style="border-left-color: var(--blue);">
                <p>Total Branches</p>
                <h3 id="total-branches"><?php echo number_format($stats['total_schools']); ?></h3>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-3">
            <div class="stat-card" style="border-left-color: #dc3545;">
                <p>Failed Logins Today</p>
                <h3 id="failed-logins" class="text-danger">0</h3>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-4">
            <div class="stat-card" style="border-left-color: #ffc107;">
                <p>Locked Accounts</p>
                <h3 id="locked-accounts" style="color: #ffc107;">0</h3>
            </div>
    </div>
    
    <!-- Maintenance Mode Badge -->
    <div class="text-end mb-3">
        <span class="badge" id="maintenance-mode-badge" style="background-color: #28a745; font-size: 1rem; padding: 8px 15px;">
            <i class="bi bi-check-circle me-2"></i>NORMAL
        </span>
    </div>

    <!-- Quick Navigation (Fade In Up) -->
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;">Management Hub</h6>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp delay-1">
            <a href="users.php" class="quick-action-btn shadow-sm">
                <i class="bi bi-people-fill"></i> Users
            </a>
        </div>
        <!-- Removed duplicate system_settings.php quick-action button -->
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp delay-3">
            <a href="security.php" class="quick-action-btn shadow-sm">
                <i class="bi bi-shield-lock-fill"></i> Security
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp delay-4">
            <a href="maintenance.php" class="quick-action-btn shadow-sm">
                <i class="bi bi-tools"></i> Maintenance
            </a>
        </div>
    </div>

    <!-- Recent Activity & Status -->
    <div class="row g-4">
        <!-- Audit Logs -->
        <div class="col-md-8 animate__animated animate__fadeInLeft">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-4" style="color: var(--blue);">Recent Audit Logs</h6>
                    <div class="table-responsive">
                        <div style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-uppercase"><th>User</th><th>Action</th><th>Timestamp</th></tr>
                            </thead>
                            <tbody id="recent-logs-tbody">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Services (Palette Changed) -->
        <div class="col-md-4 animate__animated animate__fadeInRight">
            <div class="live-services-card p-4">
                <h6 class="fw-bold mb-4"><i class="bi bi-broadcast me-2"></i>Live Services</h6>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="opacity-75">Database Status</span> 
                    <span id="db-status" class="fw-bold">● ...</span>
                </div>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="opacity-75">API Gateway</span> 
                    <span id="api-gateway-status" class="fw-bold">● ...</span>
                </div>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="opacity-75">Server Load</span> 
                    <span id="server-load-status" class="fw-bold">...</span>
                </div>
                <hr class="border-white opacity-25">
                <a href="security.php" class="btn btn-outline-light btn-sm w-100 mt-2 rounded-pill fw-bold">
                    View Network Health
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Admin Dashboard Library -->
<script src="../../assets/js/admin_dashboard.js"></script>

<script>
    // Auto-load dashboard stats when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardStats();
        // Refresh every 30 seconds
        setInterval(loadDashboardStats, 30000);
    });

    // Patch loadDashboardStats to update live services
    const origLoadDashboardStats = window.loadDashboardStats;
    window.loadDashboardStats = function() {
        fetch('process/dashboard_stats_api.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Stat cards
                    document.getElementById('total-users').textContent = formatNumber(data.stats.total_users);
                    document.getElementById('total-branches').textContent = formatNumber(data.stats.total_branches);
                    document.getElementById('failed-logins').textContent = formatNumber(data.stats.failed_logins_today);
                    document.getElementById('locked-accounts').textContent = formatNumber(data.stats.locked_accounts);
                    // Maintenance badge
                    const modeEl = document.getElementById('maintenance-mode-badge');
                    if (modeEl) {
                        if (data.stats.is_maintenance) {
                            modeEl.className = 'badge bg-warning';
                            modeEl.textContent = 'MAINTENANCE MODE';
                        } else {
                            modeEl.className = 'badge bg-success';
                            modeEl.textContent = 'NORMAL';
                        }
                    }
                    // Recent logs
                    const logsTable = document.getElementById('recent-logs-tbody');
                    if (logsTable) {
                        logsTable.innerHTML = data.recent_logs.map(log => `
                            <tr>
                                <td><small>${log.user_name || 'System'}</small></td>
                                <td><small>${log.action}</small></td>
                                <td><small>${new Date(log.created_at).toLocaleString()}</small></td>
                            </tr>
                        `).join('');
                    }
                    // Live services
                    const dbStatus = document.getElementById('db-status');
                    if (dbStatus) {
                        dbStatus.textContent = `● ${data.stats.db_status}`;
                        dbStatus.className = 'fw-bold ' + (data.stats.db_status === 'Active' ? 'text-success' : 'text-danger');
                    }
                    const apiStatus = document.getElementById('api-gateway-status');
                    if (apiStatus) {
                        apiStatus.textContent = `● ${data.stats.api_gateway}`;
                        apiStatus.className = 'fw-bold ' + (data.stats.api_gateway === 'Online' ? 'text-success' : 'text-danger');
                    }
                    const serverLoad = document.getElementById('server-load-status');
                    if (serverLoad) {
                        serverLoad.textContent = data.stats.server_load;
                        serverLoad.className = 'fw-bold text-warning';
                    }
                } else {
                    showAlert('Failed to load dashboard stats', 'error');
                }
            })
            .catch(err => showAlert('Error: ' + err.message, 'error'));
    };

    // Listen for realtime updates and auto-refresh dashboard stats
    window.addEventListener('elms-realtime-update', function(e) {
        if (e.detail && e.detail.event === 'data_updated') {
            loadDashboardStats();
        }
    });
</script>
</body>
</html>