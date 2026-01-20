<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "School Admin Dashboard";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$stats = [
    'total_programs' => 0,
    'total_subjects' => 0,
    'active_courses' => 0,
    'total_announcements' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1");
if ($row = $result->fetch_assoc()) { $stats['total_programs'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM subjects WHERE is_active = 1");
if ($row = $result->fetch_assoc()) { $stats['total_subjects'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM courses");
if ($row = $result->fetch_assoc()) { $stats['active_courses'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
if ($row = $result->fetch_assoc()) { $stats['total_announcements'] = $row['count']; }

$recent_activity = $conn->query("
    SELECT al.action, al.timestamp, 
           CONCAT(up.first_name, ' ', up.last_name) as user_name
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    ORDER BY al.timestamp DESC
    LIMIT 10
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- FANTASTIC UI COMPONENTS --- */
    .welcome-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border-left: 6px solid var(--maroon);
        margin-bottom: 30px;
    }

    .admin-stat-card {
        border-radius: 15px; padding: 25px; border: none; color: white;
        transition: 0.3s; height: 100%; display: flex; align-items: center; gap: 20px;
    }
    .admin-stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    
    .stat-icon-bg {
        width: 55px; height: 55px; border-radius: 12px; background: rgba(255,255,255,0.2);
        display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }
    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }

    .action-pill-btn {
        background: white; border: 1.5px solid #eee; border-radius: 12px; padding: 15px;
        display: flex; align-items: center; color: #444; text-decoration: none; transition: 0.3s;
        font-weight: 700; font-size: 0.85rem; margin-bottom: 10px;
    }
    .action-pill-btn:hover { background: var(--blue); color: white !important; transform: translateX(10px); border-color: var(--blue); }
    .action-pill-btn i { margin-right: 15px; font-size: 1.3rem; }

    /* Staggered Delays */
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
</style>

<!-- Part 1: Dashboard Body -->
<div class="animate__animated animate__fadeIn">
    
    <!-- Header Summary -->
    <div class="welcome-card p-4 animate__animated animate__fadeInDown">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-0 text-blue">School Administrator Dashboard</h4>
                <p class="text-muted small mb-0">Academic standards and curriculum oversight</p>
            </div>
            <div class="text-end">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                    <i class="bi bi-person-badge me-1 text-maroon"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 animate__animated animate__zoomIn delay-1">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);">
                <div class="stat-icon-bg"><i class="bi bi-mortarboard"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_programs']); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Total Programs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-2">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--maroon) 0%, #4a0000 100%);">
                <div class="stat-icon-bg"><i class="bi bi-book"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_subjects']); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Total Subjects</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-3">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, #17a2b8 0%, #0b5e6b 100%);">
                <div class="stat-icon-bg"><i class="bi bi-list-check"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['active_courses']); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Active Courses</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-4">
            <div class="admin-stat-card shadow-sm" style="background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #333;">
                <div class="stat-icon-bg" style="background: rgba(0,0,0,0.1);"><i class="bi bi-megaphone"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_announcements']); ?></h3>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Announcements</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Activity Table -->
        <div class="col-lg-8 animate__animated animate__fadeInLeft">
            <div class="main-card-modern">
                <div class="card-header-modern"><i class="bi bi-activity me-2"></i>Recent System Activity</div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Administrative User</th>
                                <th>Action Performed</th>
                                <th class="pe-4">Time Elapsed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $recent_activity->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-light text-blue d-flex align-items-center justify-content-center fw-bold me-3 border" style="width:35px; height:35px; font-size:0.75rem;">
                                            <?php echo strtoupper(substr($log['user_name'] ?? 'S', 0, 1)); ?>
                                        </div>
                                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></div>
                                    </div>
                                </td>
                                <td class="text-muted small fw-semibold"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td class="pe-4 small text-muted"><i class="bi bi-clock me-1"></i><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div class="col-lg-4 animate__animated animate__fadeInRight">
            <h6 class="fw-bold mb-3 text-uppercase small opacity-75" style="letter-spacing: 1.5px;">Curriculum Control</h6>
            <a href="programs.php" class="action-pill-btn shadow-sm">
                <i class="bi bi-mortarboard-fill text-primary"></i> Manage Academic Programs
            </a>
            <a href="curriculum.php" class="action-pill-btn shadow-sm">
                <i class="bi bi-book-half text-success"></i> Subject Catalog
            </a>
            <a href="announcements.php" class="action-pill-btn shadow-sm">
                <i class="bi bi-megaphone-fill text-warning"></i> Campus Announcements
            </a>
            
            <div class="alert bg-white border-0 shadow-sm rounded-4 mt-4 p-4">
                <h6 class="fw-bold text-blue mb-2"><i class="bi bi-info-circle-fill me-2"></i>Quick Help</h6>
                <p class="small text-muted mb-0">Use the Subject Catalog to define course requirements before assigning them to Academic Programs.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>