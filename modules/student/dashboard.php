<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Dashboard";
$student_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$student_profile = $conn->query("
    SELECT up.*, b.name as branch_name, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           st.student_no
    FROM user_profiles up
    LEFT JOIN branches b ON up.branch_id = b.id
    LEFT JOIN students st ON up.user_id = st.user_id
    LEFT JOIN programs p ON st.course_id = p.id
    LEFT JOIN shs_strands ss ON st.course_id = ss.id
    WHERE up.user_id = $student_id
")->fetch_assoc();

$section_info = $conn->query("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level,
           b.name as branch_name
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

$section_id = $section_info['id'] ?? 0;
$subjects = [];
if ($section_id > 0) {
    $subjects_query = $conn->query("
        SELECT cs.*, tsa.teacher_id, CONCAT(up.first_name, ' ', up.last_name) as teacher_name
        FROM teacher_subject_assignments tsa
        INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
        LEFT JOIN user_profiles up ON tsa.teacher_id = up.user_id
        WHERE tsa.branch_id = " . ($section_info['branch_id'] ?? 0) . "
        AND tsa.academic_year_id = $current_ay_id
        AND tsa.is_active = 1 AND cs.is_active = 1
        AND (
            (cs.program_id = " . ($section_info['program_id'] ?? 0) . " AND cs.year_level_id = " . ($section_info['year_level_id'] ?? 0) . ")
            OR (cs.shs_strand_id = " . ($section_info['shs_strand_id'] ?? 0) . " AND cs.shs_grade_level_id = " . ($section_info['shs_grade_level_id'] ?? 0) . ")
        )
        ORDER BY cs.subject_code
    ");
    while ($row = $subjects_query->fetch_assoc()) { $subjects[] = $row; }
}

$stats = ['total_subjects' => count($subjects), 'total_attendance' => 0, 'attendance_rate' => 0, 'average_grade' => 0, 'pending_assessments' => 0];
$attendance_stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count FROM attendance WHERE student_id = $student_id")->fetch_assoc();
if ($attendance_stats['total'] > 0) {
    $stats['total_attendance'] = $attendance_stats['total'];
    $stats['attendance_rate'] = round(($attendance_stats['present_count'] / $attendance_stats['total']) * 100, 1);
}
$grade_avg = $conn->query("SELECT AVG(final_grade) as avg_grade FROM grades WHERE student_id = $student_id AND final_grade > 0")->fetch_assoc();
$stats['average_grade'] = $grade_avg['avg_grade'] ? round($grade_avg['avg_grade'], 2) : 0;
$pending = $conn->query("SELECT COUNT(*) as count FROM assessment_scores ascore INNER JOIN assessments a ON ascore.assessment_id = a.id WHERE ascore.student_id = $student_id AND ascore.status = 'pending'")->fetch_assoc();
$stats['pending_assessments'] = $pending['count'] ?? 0;

$announcements = $conn->query("SELECT a.*, CONCAT(up.first_name, ' ', up.last_name) as author_name FROM announcements a LEFT JOIN user_profiles up ON a.created_by = up.user_id WHERE a.is_active = 1 AND (a.target_audience = 'all' OR a.target_audience = 'students') AND (a.expires_at IS NULL OR a.expires_at > NOW()) ORDER BY a.priority DESC, a.created_at DESC LIMIT 5");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .main-content-body { flex: 1; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC STUDENT UI --- */
    .welcome-banner {
        background: linear-gradient(135deg, var(--maroon) 0%, #500000 100%);
        border-radius: 20px; padding: 35px; color: white; margin-bottom: 30px;
        box-shadow: 0 10px 25px rgba(128, 0, 0, 0.2);
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
    .quick-link-btn:hover { background: var(--blue); color: white !important; transform: translateX(5px); }
    .quick-link-btn i { margin-right: 12px; font-size: 1.1rem; }

    .announcement-item { border-left: 4px solid #eee; padding: 10px 15px; margin-bottom: 15px; transition: 0.2s; cursor: pointer; }
    .announcement-item:hover { border-left-color: var(--maroon); background: #fafafa; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- Part 1: Welcome Banner -->
    <div class="welcome-banner animate__animated animate__fadeInDown">
        <i class="bi bi-mortarboard bg-icon"></i>
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1 text-white">Hello, <?php echo htmlspecialchars(explode(' ', $_SESSION['name'])[0]); ?>!</h2>
                <?php if ($section_info): ?>
                    <p class="mb-0 opacity-75 fw-semibold">
                        <?php echo htmlspecialchars($section_info['program_code'] . ' - ' . $section_info['section_name']); ?>
                    </p>
                    <div class="mt-2 d-flex gap-3 small opacity-50">
                        <span><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($section_info['branch_name'] ?? 'Main'); ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($current_ay['year_name'] ?? 'Current AY'); ?></span>
                    </div>
                <?php else: ?>
                    <p class="mb-0 opacity-75"><i class="bi bi-exclamation-circle me-2"></i>Status: Enrollment Pending Section Assignment</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Part 2: Quick Stats Staggered Animation -->
    <div class="row g-4 mb-5">
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.1s;">
            <div class="stat-card-modern border-bottom border-primary border-4">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-book"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['total_subjects']; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Subjects</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
            <div class="stat-card-modern border-bottom border-success border-4">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-calendar-check"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['attendance_rate']; ?>%</h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Attendance</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.3s;">
            <div class="stat-card-modern border-bottom border-info border-4">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-graph-up"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['average_grade'] ?: '0.00'; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Avg Grade</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
            <div class="stat-card-modern border-bottom border-warning border-4">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo $stats['pending_assessments']; ?></h4><small class="text-muted fw-bold text-uppercase" style="font-size:0.6rem;">Pending Tasks</small></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Part 3: Subject List -->
        <div class="col-lg-8 animate__animated animate__fadeInLeft">
            <div class="content-card">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text me-2"></i>My Curriculum Subjects</span>
                    <a href="my_classes.php" class="btn btn-sm btn-light border text-primary fw-bold px-3" style="font-size:0.7rem;">VIEW ALL</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th>Teacher</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No subject assignments found for this period.</td></tr>
                            <?php else: foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><span class="badge bg-light text-maroon border border-maroon px-3 py-2"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($subject['subject_title']); ?></td>
                                    <td><small class="text-muted"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($subject['teacher_name'] ?? 'TBA'); ?></small></td>
                                    <td class="text-end">
                                        <a href="subject_view.php?id=<?php echo $subject['id']; ?>&teacher=<?php echo $subject['teacher_id']; ?>" class="btn btn-sm btn-white border shadow-sm rounded-circle"><i class="bi bi-chevron-right"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Part 4: Announcements & Links -->
        <div class="col-lg-4 animate__animated animate__fadeInRight">
            <!-- Announcements -->
            <div class="content-card mb-4">
                <div class="card-header-modern bg-white"><i class="bi bi-megaphone-fill me-2 text-danger"></i>Latest Announcements</div>
                <div class="p-4" style="max-height: 350px; overflow-y: auto;">
                    <?php if ($announcements->num_rows == 0): ?>
                        <div class="text-center py-3 text-muted small"><i class="bi bi-bell-slash d-block fs-2 opacity-25"></i>No updates available.</div>
                    <?php else: while ($ann = $announcements->fetch_assoc()): 
                        $p_color = ['low' => 'secondary', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$ann['priority']];
                    ?>
                        <div class="announcement-item">
                            <span class="badge bg-<?php echo $p_color; ?> mb-1" style="font-size:0.6rem;"><?php echo $ann['priority']; ?></span>
                            <div class="fw-bold text-dark small line-clamp-1"><?php echo htmlspecialchars($ann['title']); ?></div>
                            <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></small>
                        </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>

            <!-- Quick Access -->
            <div class="content-card">
                <div class="card-header-modern"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Access</div>
                <div class="p-4">
                    <a href="grades.php" class="quick-link-btn"><i class="bi bi-bar-chart-fill text-primary"></i> Academic Grades</a>
                    <a href="attendance.php" class="quick-link-btn"><i class="bi bi-calendar-check text-success"></i> View Attendance</a>
                    <a href="assessments.php" class="quick-link-btn"><i class="bi bi-clipboard-check text-warning"></i> My Assessments</a>
                    <a href="enrollment.php" class="quick-link-btn"><i class="bi bi-clipboard-data text-info"></i> Enrollment Info</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>