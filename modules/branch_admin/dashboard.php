<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Admin Dashboard";
$admin_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

$branch_name = 'Unknown Branch';
$branch_stmt = $conn->prepare("SELECT name FROM branches WHERE id = ?");
$branch_stmt->bind_param("i", $branch_id);
$branch_stmt->execute();
$branch_result = $branch_stmt->get_result();
if ($branch_row = $branch_result->fetch_assoc()) {
    $branch_name = $branch_row['name'] ?? $branch_name;
}
$branch_stmt->close();

$stats = ['total_students' => 0, 'total_classes' => 0, 'active_teachers' => 0, 'today_attendance' => 0];

$result = $conn->query("SELECT COUNT(DISTINCT s.user_id) as count FROM students s INNER JOIN courses c ON s.course_id = c.id WHERE c.branch_id = $branch_id");
if ($row = $result->fetch_assoc()) { $stats['total_students'] = $row['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM classes WHERE branch_id = $branch_id");
if ($row = $result->fetch_assoc()) { $stats['total_classes'] = $row['count']; }

$result = $conn->query("SELECT COUNT(DISTINCT teacher_id) as count FROM classes WHERE branch_id = $branch_id AND teacher_id IS NOT NULL");
if ($row = $result->fetch_assoc()) { $stats['active_teachers'] = $row['count']; }

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance a INNER JOIN classes cl ON a.class_id = cl.id WHERE cl.branch_id = $branch_id AND a.attendance_date = '$today' AND a.status = 'present'");
if ($row = $result->fetch_assoc()) { $stats['today_attendance'] = $row['count']; }

// Header and Sidebar include (These contain the <html>, <head>, and Opening Wrapper)
include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%); /* Blue for Admin */
        border-radius: 20px; padding: 35px; color: white; margin-bottom: 30px;
        box-shadow: 0 10px 25px rgba(0, 51, 102, 0.2);
        position: relative; overflow: hidden;
    }
    .welcome-banner i.bg-icon { position: absolute; right: -20px; bottom: -20px; font-size: 10rem; opacity: 0.1; }

    .stat-card-modern {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center;
        gap: 15px; transition: 0.3s; height: 100%;
    }
    .stat-card-modern:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

    .content-card { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    .table-modern thead th { background: #fcfcfc; font-size: 0.7rem; text-transform: uppercase; color: #888; padding: 15px 20px; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.9rem; }

    .quick-link-btn {
        background: white; border: 1px solid #eee; border-radius: 12px; padding: 12px 15px;
        display: flex; align-items: center; color: #444; text-decoration: none; transition: 0.3s;
        font-weight: 600; font-size: 0.85rem; margin-bottom: 10px;
    }
    .quick-link-btn:hover { background: var(--maroon); color: white !important; transform: translateX(5px); }
    .quick-link-btn i { margin-right: 12px; font-size: 1.1rem; }

    .scope-box { background: #f8f9fa; border-radius: 15px; padding: 20px; border-left: 4px solid var(--maroon); }
    .scope-box h6 { font-weight: 800; font-size: 0.75rem; text-transform: uppercase; color: var(--blue); margin-top: 15px; }
    .scope-box ul { padding-left: 20px; margin-bottom: 0; }
    .scope-box li { font-size: 0.8rem; color: #555; margin-bottom: 5px; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- Part 1: Welcome Banner -->
    <div class="welcome-banner animate__animated animate__fadeInDown">
        <i class="bi bi-building-gear bg-icon"></i>
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1 text-white">Branch Admin: <?php echo htmlspecialchars(explode(' ', $_SESSION['name'])[0]); ?></h2>
                <p class="mb-0 opacity-75 fw-semibold">
                    <i class="bi bi-geo-alt-fill me-1"></i> Management Portal - <?php echo htmlspecialchars($branch_name); ?>
                </p>
                <div class="mt-2 d-flex gap-3 small opacity-50">
                    <span><i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?></span>
                    <span><i class="bi bi-shield-check me-1"></i>Active Session</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Part 2: Quick Stats -->
    <div class="row g-4 mb-5">
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.1s;">
            <div class="stat-card-modern border-bottom border-primary border-4">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['total_students']); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Total Students</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
            <div class="stat-card-modern border-bottom border-success border-4">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-door-open"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['total_classes']); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Total Classes</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.3s;">
            <div class="stat-card-modern border-bottom border-info border-4">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-badge"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['active_teachers']); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Active Teachers</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
            <div class="stat-card-modern border-bottom border-warning border-4">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-check"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['today_attendance']); ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Today's Attendance</small></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Part 3: Recent Classes Table -->
        <div class="col-lg-8 animate__animated animate__fadeInLeft">
            <div class="content-card">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-stars me-2"></i>Recent Branch Classes</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Subject & Section</th>
                                <th>Teacher</th>
                                <th>Schedule</th>
                                <th>Enrolled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_classes = $conn->query("
                                SELECT cl.id, cl.section_name, cl.schedule, cl.room, cl.current_enrolled, cl.max_capacity,
                                       s.subject_code, s.subject_title, CONCAT(up.first_name, ' ', up.last_name) as teacher_name
                                FROM classes cl
                                LEFT JOIN subjects s ON cl.subject_id = s.id
                                LEFT JOIN users u ON cl.teacher_id = u.id
                                LEFT JOIN user_profiles up ON u.id = up.user_id
                                WHERE cl.branch_id = $branch_id
                                ORDER BY cl.id DESC LIMIT 8
                            ");
                            
                            if ($recent_classes->num_rows == 0): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No classes recorded for this branch.</td></tr>
                            <?php else: while ($class = $recent_classes->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($class['section_name'] ?? '-'); ?></div>
                                    </td>
                                    <td><small class="fw-semibold"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($class['teacher_name'] ?? 'TBA'); ?></small></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($class['schedule'] ?? '-'); ?></small></td>
                                    <td>
                                        <span class="badge bg-dark text-blue border px-2 py-1">
                                            <?php echo $class['current_enrolled']; ?> / <?php echo $class['max_capacity']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Scope / Permissions Info Box -->
            <div class="content-card mt-4">
                <div class="card-header-modern"><i class="bi bi-shield-lock me-2"></i>Administrator Scope & Responsibilities</div>
                <div class="p-4">
                    <div class="scope-box">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Academic Implementation</h6>
                                <ul>
                                    <li>Manage classes, sections, and schedules</li>
                                    <li>Assign subjects to verified teachers</li>
                                    <li>Oversee student sectioning</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Academic Monitoring</h6>
                                <ul>
                                    <li>Track branch-wide attendance</li>
                                    <li>Monitor teacher compliance</li>
                                    <li>Generate performance summaries</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Part 4: Quick Actions Sidebar -->
        <div class="col-lg-4 animate__animated animate__fadeInRight">
            <div class="content-card mb-4">
                <div class="card-header-modern bg-white"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Management Actions</div>
                <div class="p-4">
                    <a href="scheduling.php" class="quick-link-btn"><i class="bi bi-calendar-plus text-primary"></i> Schedule Classes</a>
                    <a href="sectioning.php" class="quick-link-btn"><i class="bi bi-diagram-3 text-dark"></i> Manage Sections</a>
                    <a href="student_assignment.php" class="quick-link-btn"><i class="bi bi-person-check text-info"></i> Assign Students</a>
                    <a href="teachers.php" class="quick-link-btn"><i class="bi bi-person-badge text-secondary"></i> Manage Teachers</a>
                    <a href="students.php" class="quick-link-btn"><i class="bi bi-people text-dark"></i> Manage Students</a>
                    <a href="announcements.php" class="quick-link-btn"><i class="bi bi-megaphone text-warning"></i> Branch Announcements</a>
                    <a href="monitoring.php" class="quick-link-btn"><i class="bi bi-eye text-danger"></i> Compliance Monitor</a>
                    <a href="reports.php" class="quick-link-btn"><i class="bi bi-file-earmark-text text-info"></i> Generate Reports</a>
                </div>
            </div>

            <div class="alert alert-info border-0 shadow-sm rounded-4 p-3">
                <div class="d-flex gap-3">
                    <i class="bi bi-info-circle-fill fs-4"></i>
                    <div>
                        <small class="d-block fw-bold text-uppercase" style="font-size: 0.65rem;">Compliance Notice</small>
                        <small style="font-size: 0.75rem;">Ensure all class records are finalized before the end of the current academic term.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>