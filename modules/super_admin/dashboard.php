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
include '../../includes/sidebar.php'; 
?>

<style>
    /* Transitions & Fantastic Effects */
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        border-left: 5px solid var(--maroon);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    }
    .stat-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 12px 20px rgba(0,0,0,0.1);
    }
    .stat-card h3 { font-size: 2.2rem; font-weight: 700; color: #333; margin: 0; }
    .stat-card p { color: #888; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; font-weight: 700; }
    
    .quick-action-btn {
        background: white;
        border: 1px solid #eee;
        padding: 20px;
        border-radius: 15px;
        text-align: center;
        text-decoration: none;
        color: #555;
        transition: all 0.3s ease;
        display: block;
    }
    .quick-action-btn:hover { 
        background: var(--maroon); 
        color: white !important; 
        border-color: var(--maroon);
        transform: scale(1.05);
    }
    .quick-action-btn i { font-size: 1.8rem; display: block; margin-bottom: 10px; }

    /* Live Services Card - Palette Fix */
    .live-services-card {
        background: var(--blue); /* Corporate Blue instead of Black */
        color: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 25px rgba(0, 51, 102, 0.2);
    }

    /* Staggered Animation Delays */
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
</style>

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
                <h3><?php echo number_format($stats['total_users']); ?></h3>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-2">
            <div class="stat-card" style="border-left-color: var(--blue);">
                <p>Registered Schools</p>
                <h3><?php echo number_format($stats['total_schools']); ?></h3>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-3">
            <div class="stat-card" style="border-left-color: #28a745;">
                <p>System Health</p>
                <h3 class="text-success"><?php echo $stats['system_health']; ?></h3>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-4">
            <div class="stat-card" style="border-left-color: #ffc107;">
                <p>Platform Uptime</p>
                <h3>99.9%</h3>
            </div>
        </div>
    </div>

    <!-- Quick Navigation (Fade In Up) -->
    <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1px;">Management Hub</h6>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp delay-1">
            <a href="users.php" class="quick-action-btn shadow-sm">
                <i class="bi bi-people-fill"></i> Users
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp delay-2">
            <a href="system_settings.php" class="quick-action-btn shadow-sm">
                <i class="bi bi-sliders"></i> Settings
            </a>
        </div>
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
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr class="small text-uppercase"><th>User</th><th>Action</th><th>Timestamp</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $logs = $conn->query("SELECT al.action, al.timestamp, CONCAT(up.first_name, ' ', up.last_name) as user_name FROM audit_logs al LEFT JOIN user_profiles up ON al.user_id = up.user_id ORDER BY al.timestamp DESC LIMIT 6");
                                while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td class="small fw-bold text-dark"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td class="small text-muted"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
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
                    <span class="text-success fw-bold">● Active</span>
                </div>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="opacity-75">API Gateway</span> 
                    <span class="text-success fw-bold">● Online</span>
                </div>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="opacity-75">Server Load</span> 
                    <span class="text-warning fw-bold">24%</span>
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