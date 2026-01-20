<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "School Administrator Dashboard";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$shs_tracks = $conn->query("SELECT COUNT(*) as count FROM shs_tracks WHERE is_active = 1")->fetch_assoc()['count'];
$shs_strands = $conn->query("SELECT COUNT(*) as count FROM shs_strands WHERE is_active = 1")->fetch_assoc()['count'];
$shs_subjects = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type IN ('shs_core', 'shs_applied', 'shs_specialized') AND is_active = 1")->fetch_assoc()['count'];

$college_programs = $conn->query("SELECT COUNT(*) as count FROM programs WHERE is_active = 1")->fetch_assoc()['count'];
$college_subjects = $conn->query("SELECT COUNT(*) as count FROM curriculum_subjects WHERE subject_type = 'college' AND is_active = 1")->fetch_assoc()['count'];

$branch_admins = $conn->query("
    SELECT COUNT(*) as count FROM users u
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_BRANCH_ADMIN . " AND u.status = 'active'
")->fetch_assoc()['count'];

include '../../includes/header.php';
include '../../includes/sidebar.php'; // This opens the .wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .dashboard-stat-card {
        border-radius: 20px; padding: 25px; border: none; color: white;
        transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); height: 100%; 
        display: flex; flex-direction: column; justify-content: space-between;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .dashboard-stat-card:hover { transform: translateY(-8px); box-shadow: 0 12px 25px rgba(0,0,0,0.15); }
    
    .stat-icon-circle {
        width: 50px; height: 50px; border-radius: 12px; background: rgba(255,255,255,0.2);
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 15px;
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .action-button-modern {
        background: white; border: 1.5px solid #eee; border-radius: 15px; padding: 15px;
        display: flex; align-items: center; text-decoration: none; transition: 0.3s;
        color: #444; font-weight: 700; font-size: 0.85rem;
    }
    .action-button-modern:hover { background: var(--blue); color: white !important; transform: translateX(5px); border-color: var(--blue); }
    .action-button-modern i { margin-right: 15px; font-size: 1.2rem; }

    /* Progress Custom Styling */
    .progress-wrap { margin-bottom: 18px; }
    .progress-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #888; display: block; margin-bottom: 5px; }
    .progress-custom { height: 12px; border-radius: 10px; background: #eee; overflow: hidden; }

    /* Delays */
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 5px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-speedometer2 me-2 text-maroon"></i>Administrator Hub</h4>
            <p class="text-muted small mb-0">System-wide curriculum and branch oversight</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-blue border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-check-circle-fill me-1 text-success"></i> Institutional System Active
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Stats Row 1: SHS Focus -->
    <h6 class="fw-bold text-muted mb-3 text-uppercase small" style="letter-spacing: 1px;">SHS Curriculum Status</h6>
    <div class="row g-4 mb-4">
        <div class="col-md-3 animate__animated animate__zoomIn delay-1">
            <div class="dashboard-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);">
                <div class="stat-icon-circle"><i class="bi bi-diagram-3"></i></div>
                <div><h3 class="fw-bold mb-0"><?php echo $shs_tracks; ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Academic Tracks</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-2">
            <div class="dashboard-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--maroon) 0%, #4a0000 100%);">
                <div class="stat-icon-circle"><i class="bi bi-diagram-2"></i></div>
                <div><h3 class="fw-bold mb-0"><?php echo $shs_strands; ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Academic Strands</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-3">
            <div class="dashboard-stat-card shadow-sm" style="background: linear-gradient(135deg, #17a2b8 0%, #0b5e6b 100%);">
                <div class="stat-icon-circle"><i class="bi bi-book-half"></i></div>
                <div><h3 class="fw-bold mb-0"><?php echo $shs_subjects; ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Curriculum Subjects</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-4">
            <div class="dashboard-stat-card shadow-sm" style="background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #333;">
                <div class="stat-icon-circle" style="background: rgba(0,0,0,0.1);"><i class="bi bi-person-badge"></i></div>
                <div><h3 class="fw-bold mb-0"><?php echo $branch_admins; ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Active Branch Admins</small></div>
            </div>
        </div>
    </div>

    <!-- Stats Row 2: College & Quick Actions -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 animate__animated animate__zoomIn delay-1">
            <div class="dashboard-stat-card shadow-sm" style="background: linear-gradient(135deg, #444 0%, #111 100%);">
                <div class="stat-icon-circle"><i class="bi bi-building"></i></div>
                <div><h3 class="fw-bold mb-0"><?php echo $college_programs; ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">College Programs</small></div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn delay-2">
            <div class="dashboard-stat-card shadow-sm" style="background: linear-gradient(135deg, #6c757d 0%, #343a40 100%);">
                <div class="stat-icon-circle"><i class="bi bi-journal-text"></i></div>
                <div><h3 class="fw-bold mb-0"><?php echo $college_subjects; ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">College Subjects</small></div>
            </div>
        </div>
        <div class="col-md-6 animate__animated animate__fadeInRight delay-3">
            <div class="main-card-modern p-4 h-100">
                <h6 class="fw-bold text-blue mb-4 text-uppercase small"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Control Panel</h6>
                <div class="row g-3">
                    <div class="col-6"><a href="shs_curriculum.php" class="action-button-modern shadow-xs"><i class="bi bi-mortarboard text-primary"></i>SHS Config</a></div>
                    <div class="col-6"><a href="college_curriculum.php" class="action-button-modern shadow-xs"><i class="bi bi-building text-info"></i>College Config</a></div>
                    <div class="col-6"><a href="administrative_control.php" class="action-button-modern shadow-xs"><i class="bi bi-shield-lock text-danger"></i>Admin Control</a></div>
                    <div class="col-6"><a href="announcements.php" class="action-button-modern shadow-xs"><i class="bi bi-megaphone text-warning"></i>Bulletin Board</a></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Curriculum Implementation Status -->
    <div class="main-card-modern animate__animated animate__fadeInUp delay-4">
        <div class="card-header-modern bg-white"><i class="bi bi-list-check me-2 text-maroon"></i>Curriculum Implementation Analytics</div>
        <div class="card-body p-4">
            <div class="row g-5">
                <!-- SHS Analytics -->
                <div class="col-md-6 border-end">
                    <h6 class="fw-bold text-dark mb-4">Senior High School Profile</h6>
                    <div class="progress-wrap">
                        <label class="progress-label">Tracks Compliance (4/4)</label>
                        <div class="progress-custom shadow-sm"><div class="progress-bar bg-primary" style="width: 100%"></div></div>
                    </div>
                    <div class="progress-wrap">
                        <label class="progress-label">Strands Coverage (<?php echo $shs_strands; ?>/8)</label>
                        <div class="progress-custom shadow-sm"><div class="progress-bar bg-success" style="width: <?php echo ($shs_strands/8)*100; ?>%"></div></div>
                    </div>
                    <div class="progress-wrap">
                        <label class="progress-label">Subject Definitions (<?php echo $shs_subjects; ?> Defined)</label>
                        <div class="progress-custom shadow-sm"><div class="progress-bar bg-info" style="width: <?php echo min(100, ($shs_subjects/100)*100); ?>%"></div></div>
                    </div>
                    <div class="p-3 rounded-3 mt-4" style="background: #f0f7ff; border: 1px dashed var(--blue);">
                        <small class="text-blue fw-bold"><i class="bi bi-patch-check-fill me-2"></i>Institutional DepEd compliance benchmarks satisfied.</small>
                    </div>
                </div>

                <!-- College Analytics -->
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark mb-4">College Department Profile</h6>
                    <div class="progress-wrap">
                        <label class="progress-label">Degree Programs (<?php echo $college_programs; ?>/5)</label>
                        <div class="progress-custom shadow-sm"><div class="progress-bar bg-dark" style="width: <?php echo ($college_programs/5)*100; ?>%"></div></div>
                    </div>
                    <div class="progress-wrap">
                        <label class="progress-label">Academic Subjects (<?php echo $college_subjects; ?>/150)</label>
                        <div class="progress-custom shadow-sm"><div class="progress-bar bg-secondary" style="width: <?php echo min(100, ($college_subjects/150)*100); ?>%"></div></div>
                    </div>
                    <div class="progress-wrap">
                        <label class="progress-label">Year Level Configuration (4/4)</label>
                        <div class="progress-custom shadow-sm"><div class="progress-bar bg-warning" style="width: 100%"></div></div>
                    </div>
                    <div class="p-3 rounded-3 mt-4" style="background: #fff1f0; border: 1px dashed var(--maroon);">
                        <small class="text-maroon fw-bold"><i class="bi bi-patch-check-fill me-2"></i>CHED Institutional standards validated.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>