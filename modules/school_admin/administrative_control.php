<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Administrative Control";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */

// Fetch Branch Administrators
$branch_admins_query = "
    SELECT 
        u.id, u.email, u.status,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        b.name as branch_name,
        u.created_at, u.last_login
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN branches b ON up.branch_id = b.id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_BRANCH_ADMIN . "
    ORDER BY u.created_at DESC
";
$branch_admins_result = $conn->query($branch_admins_query);
$branch_admins = [];
while ($admin = $branch_admins_result->fetch_assoc()) { $branch_admins[] = $admin; }

// Fetch Announcements (System-wide)
$announcements_query = "
    SELECT 
        a.id, a.title, a.content, a.priority, a.is_active, a.created_at, a.expires_at,
        CONCAT(up.first_name, ' ', up.last_name) as created_by_name
    FROM announcements a
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    WHERE a.school_id IS NULL AND a.branch_id IS NULL
    ORDER BY a.created_at DESC
    LIMIT 10
";
$announcements_result = $conn->query($announcements_query);

// Fetch Branch Performance Data
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$branch_performance_query = "
    SELECT 
        b.id, b.name,
        (SELECT COUNT(DISTINCT ss.student_id) FROM section_students ss INNER JOIN sections s ON ss.section_id = s.id WHERE s.branch_id = b.id AND s.academic_year_id = $current_ay_id AND ss.status = 'active') as student_count,
        (SELECT COUNT(DISTINCT tsa.teacher_id) FROM teacher_subject_assignments tsa WHERE tsa.branch_id = b.id AND tsa.academic_year_id = $current_ay_id AND tsa.is_active = 1) as teacher_count,
        (SELECT COUNT(*) FROM sections s WHERE s.branch_id = b.id AND s.academic_year_id = $current_ay_id AND s.is_active = 1) as section_count
    FROM branches b
    ORDER BY b.name ASC
";
$branch_performance_result = $conn->query($branch_performance_query);

// Fetch Branches for dropdown
$branches_result = $conn->query("SELECT id, name FROM branches ORDER BY name");
$branches = [];
while ($branch = $branches_result->fetch_assoc()) { $branches[] = $branch; }

// Get audit logs for monitoring
$audit_logs_query = "
    SELECT al.action, al.timestamp, CONCAT(up.first_name, ' ', up.last_name) as user_name, u.email
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.timestamp DESC
    LIMIT 20
";
$audit_logs_result = $conn->query($audit_logs_query);

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .nav-pills-modern .nav-link {
        color: #666; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
        padding: 12px 20px; border-radius: 10px; transition: 0.3s; margin-right: 10px;
        background: #fff; border: 1.5px solid #eee;
    }
    .nav-pills-modern .nav-link.active {
        background-color: var(--blue); color: white; border-color: var(--blue); box-shadow: 0 4px 12px rgba(0,51,102,0.2);
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;
    }

    .table-modern thead th { 
        background: #fcfcfc; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; color: #888; padding: 15px 20px; border-bottom: 2px solid #eee;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    /* Announcement Cards */
    .ann-card {
        border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: 0.3s; overflow: hidden; background: white; margin-bottom: 20px;
    }
    .ann-card:hover { transform: translateY(-5px); }
    .ann-priority-urgent { border-left: 6px solid var(--maroon); }
    .ann-priority-high { border-left: 6px solid #ffc107; }
    .ann-priority-normal { border-left: 6px solid var(--blue); }

    .action-btn-circle {
        width: 32px; height: 32px; border-radius: 50%; display: inline-flex;
        align-items: center; justify-content: center; transition: 0.2s; border: 1px solid #eee;
    }
    .action-btn-circle:hover { transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

    .policy-card { border-radius: 15px; border: none; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
    .policy-header-college { background: var(--blue); color: white; padding: 12px 20px; border-radius: 15px 15px 0 0; }
    .policy-header-shs { background: var(--maroon); color: white; padding: 12px 20px; border-radius: 15px 15px 0 0; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .nav-pills-modern { flex-direction: column; } .nav-pills-modern .nav-link { margin-right: 0; margin-bottom: 5px; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-shield-check me-2 text-maroon"></i>Administrative Control</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-maroon text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Admin Control</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-outline-secondary btn-sm px-4 rounded-pill shadow-sm" onclick="goBack()">
            <i class="bi bi-arrow-left me-1"></i> Back
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Modern Tab Navigation -->
    <ul class="nav nav-pills nav-pills-modern mb-4 animate__animated animate__fadeIn" id="controlTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#branch-admins" type="button"><i class="bi bi-people-fill me-2"></i>Branch Admins</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#announcements" type="button"><i class="bi bi-megaphone me-2"></i>Announcements</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#performance" type="button"><i class="bi bi-bar-chart me-2"></i>Performance</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#policies" type="button"><i class="bi bi-file-earmark-text me-2"></i>Policies</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#monitoring" type="button"><i class="bi bi-eye me-2"></i>Monitoring</button></li>
    </ul>

    <div class="tab-content" id="controlTabContent">

        <!-- TAB 1: BRANCH ADMINISTRATORS -->
        <div class="tab-pane fade show active" id="branch-admins" role="tabpanel">
            <div class="main-card-modern animate__animated animate__fadeInUp">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light">
                    <h6 class="fw-bold mb-0 text-blue">Branch Access Management</h6>
                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addAdminModal" style="background-color: var(--blue);">
                        <i class="bi bi-plus-lg"></i> Add Admin
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Administrator Name</th>
                                <th>Email</th>
                                <th>Branch Assignment</th>
                                <th class="text-center">Status</th>
                                <th>Last Login</th>
                                <th class="text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($branch_admins)): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No branch administrators registered.</td></tr>
                            <?php else: foreach ($branch_admins as $admin): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small></td>
                                    <td>
                                        <?php if (empty($admin['branch_name'])): ?>
                                            <span class="badge bg-warning text-dark px-3 rounded-pill">⚠ UNASSIGNED</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark text-dark border border-blue px-3 rounded-pill"><?php echo htmlspecialchars($admin['branch_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'secondary'; ?> px-3 rounded-pill">
                                            <?php echo strtoupper($admin['status']); ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?php echo $admin['last_login'] ? date('M d, H:i', strtotime($admin['last_login'])) : 'Never'; ?></small></td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-1">
                                            <button class="action-btn-circle text-info" onclick="editBranchAdmin(<?php echo $admin['id']; ?>)"><i class="bi bi-pencil-fill"></i></button>
                                            <button class="action-btn-circle text-danger" onclick="deactivateAdmin(<?php echo $admin['id']; ?>)"><i class="bi bi-lock-fill"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: ANNOUNCEMENTS -->
        <div class="tab-pane fade" id="announcements" role="tabpanel">
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-danger btn-sm rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal" style="background-color: var(--maroon);">
                    <i class="bi bi-megaphone me-1"></i> New Announcement
                </button>
            </div>
            <div class="row g-4">
                <?php while ($ann = $announcements_result->fetch_assoc()): 
                    $p_cls = 'ann-priority-' . $ann['priority'];
                ?>
                <div class="col-md-6 col-xl-4 animate__animated animate__zoomIn">
                    <div class="ann-card <?php echo $p_cls; ?>">
                        <div class="p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <span class="badge bg-light text-dark border small"><?php echo strtoupper($ann['priority']); ?></span>
                                <small class="text-muted"><?php echo date('M d', strtotime($ann['created_at'])); ?></small>
                            </div>
                            <h6 class="fw-bold text-blue mb-2"><?php echo htmlspecialchars($ann['title']); ?></h6>
                            <p class="small text-muted mb-4 line-clamp-3"><?php echo htmlspecialchars($ann['content']); ?></p>
                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <small class="text-muted fw-bold">By <?php echo htmlspecialchars($ann['created_by_name']); ?></small>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-light border-0 text-warning" onclick="editAnnouncement(<?php echo $ann['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-light border-0 text-danger" onclick="deleteAnnouncement(<?php echo $ann['id']; ?>)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- TAB 3: BRANCH PERFORMANCE -->
        <div class="tab-pane fade" id="performance" role="tabpanel">
            <div class="main-card-modern">
                <div class="p-4 border-bottom bg-light">
                    <h6 class="fw-bold mb-0 text-blue">Branch Metrics Overview</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Branch Institution</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">Faculty</th>
                                <th class="text-center">Academic Sections</th>
                                <th class="text-end pe-4">Analysis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($perf = $branch_performance_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($perf['name']); ?></td>
                                <td class="text-center"><span class="badge bg-primary rounded-pill px-3"><?php echo $perf['student_count']; ?></span></td>
                                <td class="text-center"><span class="badge bg-info rounded-pill px-3"><?php echo $perf['teacher_count']; ?></span></td>
                                <td class="text-center"><span class="badge bg-success rounded-pill px-3"><?php echo $perf['section_count']; ?></span></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="viewBranchDetails(<?php echo $perf['id']; ?>)">
                                        <i class="bi bi-graph-up me-1"></i> Details
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: ACADEMIC POLICIES -->
        <div class="tab-pane fade" id="policies" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold text-blue mb-0">Institutional Policy Framework</h6>
                <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addPolicyModal" style="background-color: var(--blue);">
                    <i class="bi bi-plus-lg"></i> New Policy
                </button>
            </div>
            <div class="row g-4">
                <!-- SHS Policies -->
                <div class="col-lg-6">
                    <div class="policy-card shadow-sm">
                        <div class="policy-header-shs"><h6 class="mb-0 fw-bold">SHS ACADEMIC POLICIES</h6></div>
                        <div class="p-4">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex justify-content-between"><span>Minimum GPA Requirement</span><strong>1.50</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Semester Unit Load</span><strong>12 - 18</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Attendance Threshold</span><strong>85%</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Probation Threshold</span><strong>2 Consecutive Terms</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- College Policies -->
                <div class="col-lg-6">
                    <div class="policy-card shadow-sm">
                        <div class="policy-header-college"><h6 class="mb-0 fw-bold">COLLEGE ACADEMIC POLICIES</h6></div>
                        <div class="p-4">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex justify-content-between"><span>Graduation Min GPA</span><strong>2.00</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Semester Unit Load</span><strong>9 - 18</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Attendance Threshold</span><strong>80%</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Dean's List GPA</span><strong>1.25</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Policy Settings -->
            <div class="main-card-modern mt-4 p-4">
                <h6 class="fw-bold text-blue mb-3">Enforcement Settings</h6>
                <div class="row g-3">
                    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked><label class="form-check-label small fw-bold">Enforce minimum GPA checks</label></div></div>
                    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked><label class="form-check-label small fw-bold">Validate prerequisites on enrollment</label></div></div>
                    <div class="col-md-12 mt-4"><button class="btn btn-maroon-save shadow-sm" style="background-color: var(--maroon); color:white; border:none; border-radius:10px; padding: 10px 30px;"><i class="bi bi-save me-2"></i>Save Policy Configuration</button></div>
                </div>
            </div>
        </div>

        <!-- TAB 5: ACTIVITY MONITORING -->
        <div class="tab-pane fade" id="monitoring" role="tabpanel">
            <div class="main-card-modern">
                <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-blue">System Audit Trail</h6>
                    <span class="badge bg-dark rounded-pill">LAST 20 ACTIONS</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead><tr><th class="ps-4">User Activity</th><th>Operation</th><th class="pe-4">Timestamp</th></tr></thead>
                        <tbody>
                            <?php while ($log = $audit_logs_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark small"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['email'] ?? '-'); ?></small>
                                </td>
                                <td class="text-muted small fw-semibold"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td class="pe-4 small text-muted"><i class="bi bi-clock-history me-1"></i><?php echo date('M d, H:i:s', strtotime($log['timestamp'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- --- MODALS (UNTOUCHED LOGIC) --- -->

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Create Branch Administrator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAdminForm">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3"><label class="form-label small fw-bold">Full Name *</label><input type="text" class="form-control border-light shadow-sm" name="full_name" required></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Email Address *</label><input type="email" class="form-control border-light shadow-sm" name="email" required></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Initial Password *</label><input type="password" class="form-control border-light shadow-sm" name="password" required minlength="6"></div>
                    <div class="mb-0"><label class="form-label small fw-bold">Assign to Branch *</label><select class="form-select border-light shadow-sm" name="branch_id" required><option value="">-- Choose Branch --</option><?php foreach ($branches as $b): ?><option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="modal-footer border-0 p-4"><button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" style="background-color: var(--blue); border:none; border-radius:10px;">Register Administrator</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold">Update Admin Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAdminForm">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3"><label class="form-label small fw-bold">Administrator</label><input type="text" class="form-control bg-white" id="edit_full_name" readonly></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Current Email</label><input type="email" class="form-control bg-white" id="edit_email" readonly></div>
                    <div class="mb-0"><label class="form-label small fw-bold">New Branch Assignment *</label><select class="form-select border-light shadow-sm" name="branch_id" id="edit_branch_id" required><?php foreach ($branches as $b): ?><option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="modal-footer border-0 p-4"><button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" style="background-color: var(--blue); border:none; border-radius:10px;">Update Assignment</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-megaphone me-2"></i>Publish Institutional Bulletin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAnnouncementForm">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3"><label class="form-label small fw-bold">TITLE *</label><input type="text" class="form-control border-light shadow-sm" name="title" required></div>
                    <div class="mb-3"><label class="form-label small fw-bold">CONTENT *</label><textarea class="form-control border-light shadow-sm" name="content" rows="5" required></textarea></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">PRIORITY</label><select class="form-select border-light shadow-sm" name="priority" required><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">EXPIRATION DATE</label><input type="date" class="form-control border-light shadow-sm" name="expires_at"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger px-4 shadow-sm" style="background-color: var(--maroon); border:none; border-radius:10px;">Publish to System</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Branch Details Modal (Template) -->
<div class="modal fade" id="branchDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue);">
                <h5 class="modal-title fw-bold" id="branchDetailsTitle">Branch Analytics</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="branchDetailsContent"></div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
const BASE_URL = '/elms_system/';

function goBack() { window.history.back(); }

function showAlert(m, t = 'info') {
    const html = `<div class="alert alert-${t} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert">${m}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = html;
}

/** 1. AJAX: BRANCH ADMINS (UNTOUCHED LOGIC) */
$('#addAdminForm').on('submit', function(e) {
    e.preventDefault();
    fetch(BASE_URL + 'modules/school_admin/process/add_branch_admin.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); else showAlert(d.message, 'danger'); });
});

function editBranchAdmin(adminId) {
    fetch(BASE_URL + 'modules/school_admin/process/get_branch_admin.php?admin_id=' + adminId)
    .then(r => r.json()).then(d => {
        if (d.status === 'success') {
            document.getElementById('edit_user_id').value = d.admin.id;
            document.getElementById('edit_full_name').value = d.admin.full_name;
            document.getElementById('edit_email').value = d.admin.email;
            document.getElementById('edit_branch_id').value = d.admin.branch_id ?? '';
            new bootstrap.Modal(document.getElementById('editAdminModal')).show();
        }
    });
}

$('#editAdminForm').on('submit', function(e) {
    e.preventDefault();
    fetch(BASE_URL + 'modules/school_admin/process/update_branch_admin.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); else showAlert(d.message, 'danger'); });
});

function deactivateAdmin(adminId) {
    if (confirm('Restrict access for this administrator?')) {
        fetch(BASE_URL + 'modules/school_admin/process/deactivate_admin.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ admin_id: adminId }) })
        .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); });
    }
}

/** 2. AJAX: ANNOUNCEMENTS (UNTOUCHED LOGIC) */
$('#addAnnouncementForm').on('submit', function(e) {
    e.preventDefault();
    fetch(BASE_URL + 'modules/school_admin/process/add_system_announcement.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); else showAlert(d.message, 'danger'); });
});

function deleteAnnouncement(announcementId) {
    if (confirm('Permanently remove this institutional bulletin?')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_announcement.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ announcement_id: announcementId }) })
        .then(r => r.json()).then(d => { if (d.status === 'success') location.reload(); });
    }
}

/** 3. AJAX: BRANCH ANALYTICS (UNTOUCHED LOGIC) */
function viewBranchDetails(branchId) {
    document.getElementById('branchDetailsContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('branchDetailsModal')).show();
    fetch(BASE_URL + 'modules/school_admin/process/get_branch_details.php?branch_id=' + branchId)
    .then(r => r.json()).then(data => {
        if (data.status === 'success') {
            const b = data.branch;
            document.getElementById('branchDetailsContent').innerHTML = `
                <div class="row g-4 text-center mb-4">
                    <div class="col-3"><div class="p-3 border rounded"><h4>${b.student_count}</h4><small class="text-muted">Students</small></div></div>
                    <div class="col-3"><div class="p-3 border rounded"><h4>${b.teacher_count}</h4><small class="text-muted">Faculty</small></div></div>
                    <div class="col-3"><div class="p-3 border rounded"><h4>${b.section_count}</h4><small class="text-muted">Sections</small></div></div>
                    <div class="col-3"><div class="p-3 border rounded"><h4>${b.subject_count}</h4><small class="text-muted">Units</small></div></div>
                </div>
                <hr><p class="small"><strong>Location:</strong> ${b.address || 'N/A'}</p>
            `;
        }
    });
}
</script>
</body>
</html>