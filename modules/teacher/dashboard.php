<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Teacher Dashboard";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$stats = [
    'my_classes' => 0,
    'total_students' => 0,
    'pending_assessments' => 0,
    'grading_progress' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM classes WHERE teacher_id = $teacher_id");
if ($row = $result->fetch_assoc()) { $stats['my_classes'] = $row['count']; }

$result = $conn->query("
    SELECT COUNT(DISTINCT e.student_id) as count 
    FROM enrollments e
    INNER JOIN classes cl ON e.class_id = cl.id
    WHERE cl.teacher_id = $teacher_id AND e.status = 'approved'
");
if ($row = $result->fetch_assoc()) { $stats['total_students'] = $row['count']; }

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM assessment_scores ascore
    INNER JOIN assessments a ON ascore.assessment_id = a.id
    WHERE a.created_by = $teacher_id AND ascore.status = 'submitted'
");
if ($row = $result->fetch_assoc()) { $stats['pending_assessments'] = $row['count']; }

$total_expected = $conn->query("
    SELECT COUNT(*) as count
    FROM enrollments e
    INNER JOIN classes cl ON e.class_id = cl.id
    WHERE cl.teacher_id = $teacher_id AND e.status = 'approved'
")->fetch_assoc()['count'];

$graded = $conn->query("
    SELECT COUNT(*) as count
    FROM grades g
    INNER JOIN classes cl ON g.class_id = cl.id
    WHERE cl.teacher_id = $teacher_id AND g.final_grade > 0
")->fetch_assoc()['count'];

$stats['grading_progress'] = $total_expected > 0 ? round(($graded / $total_expected) * 100) : 0;

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- LAYOUT ENGINE: LOCKED SIDEBAR --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; /* Padding for bottom visibility */
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC TEACHER UI --- */
    .teacher-stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }
    .teacher-stat-card:hover { transform: translateY(-8px); box-shadow: 0 12px 20px rgba(0,0,0,0.1); }
    
    .stat-icon-circle {
        width: 60px; height: 60px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem;
    }

    .action-card-btn {
        background: white;
        border: 1px solid #eee;
        padding: 25px;
        border-radius: 20px;
        text-align: center;
        text-decoration: none;
        color: #555;
        transition: all 0.3s ease;
        display: block;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .action-card-btn:hover {
        background: var(--blue);
        color: white !important;
        border-color: var(--blue);
        transform: scale(1.05);
    }
    .action-card-btn i { font-size: 2rem; display: block; margin-bottom: 10px; }

    .table-modern {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    .table-modern thead th {
        background: #fcfcfc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px 20px;
        border-bottom: 2px solid #eee;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; }

    /* Progress bar styling */
    .progress-custom { height: 8px; border-radius: 10px; background: #eee; overflow: hidden; margin-top: 10px; }
    .progress-bar-maroon { background: var(--maroon); }

    /* Mobile Responsive Fixes */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 15px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Top Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);">Faculty Dashboard</h4>
        <p class="text-muted small mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
    </div>
    <span class="badge rounded-pill bg-light text-dark border px-3 py-2 shadow-sm">
        <i class="bi bi-calendar3 me-2 text-maroon"></i><?php echo date('F d, Y'); ?>
    </span>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <!-- Stats Grid (Animated) -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.1s;">
            <div class="teacher-stat-card border-start border-primary border-5">
                <div class="stat-icon-circle bg-light text-primary"><i class="bi bi-door-open"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['my_classes']); ?></h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">My Classes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
            <div class="teacher-stat-card border-start border-info border-5">
                <div class="stat-icon-circle bg-light text-info"><i class="bi bi-people"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.3s;">
            <div class="teacher-stat-card border-start border-warning border-5">
                <div class="stat-icon-circle bg-light text-warning"><i class="bi bi-clipboard-check"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['pending_assessments']); ?></h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">Pending Subs</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__zoomIn" style="animation-delay: 0.4s;">
            <div class="teacher-stat-card border-start border-success border-5">
                <div class="stat-icon-circle bg-light text-success"><i class="bi bi-graph-up"></i></div>
                <div>
                    <h3 class="fw-bold mb-0"><?php echo $stats['grading_progress']; ?>%</h3>
                    <p class="text-muted small text-uppercase fw-bold mb-0">Grading Progress</p>
                    <div class="progress-custom"><div class="progress-bar progress-bar-maroon" style="width: <?php echo $stats['grading_progress']; ?>%"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h6 class="fw-bold text-muted mb-3 text-uppercase small" style="letter-spacing: 1px;">Management Hub</h6>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <a href="my_classes.php" class="action-card-btn shadow-sm">
                <i class="bi bi-door-open-fill"></i> View Classes
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <a href="grading.php" class="action-card-btn shadow-sm">
                <i class="bi bi-calculator-fill"></i> Grading
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <a href="attendance.php" class="action-card-btn shadow-sm">
                <i class="bi bi-calendar-check-fill"></i> Attendance
            </a>
        </div>
        <div class="col-6 col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
            <a href="assessments.php" class="action-card-btn shadow-sm">
                <i class="bi bi-clipboard-check-fill"></i> Assessments
            </a>
        </div>
    </div>

    <!-- Class List Table -->
    <div class="table-modern animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
        <div class="bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-collection-play-fill me-2"></i>Current Teaching Schedule</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject Details</th>
                        <th>Section</th>
                        <th>Schedule</th>
                        <th>Room</th>
                        <th class="text-center">Students</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    /** BACKEND LOOP - UNTOUCHED */
                    $classes = $conn->query("
                        SELECT cl.id, cl.section_name, cl.schedule, cl.room, cl.current_enrolled, s.subject_code, s.subject_title
                        FROM classes cl
                        LEFT JOIN subjects s ON cl.subject_id = s.id
                        WHERE cl.teacher_id = $teacher_id
                        ORDER BY cl.id LIMIT 10
                    ");
                    
                    if ($classes->num_rows > 0):
                        while ($class = $classes->fetch_assoc()):
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($class['section_name'] ?? '-'); ?></span></td>
                        <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($class['schedule'] ?? '-'); ?></small></td>
                        <td><small class="fw-bold text-primary"><?php echo htmlspecialchars($class['room'] ?? '-'); ?></small></td>
                        <td class="text-center"><span class="badge bg-blue rounded-pill px-3"><?php echo $class['current_enrolled']; ?></span></td>
                        <td class="text-end">
                            <a href="classroom.php?id=<?php echo $class['id']; ?>" class="btn btn-maroon btn-sm px-3 fw-bold rounded-pill shadow-sm">
                                Enter Class
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No classes assigned to you yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>