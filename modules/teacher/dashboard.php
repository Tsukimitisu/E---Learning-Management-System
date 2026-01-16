<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Teacher Dashboard";
$teacher_id = $_SESSION['user_id'];

// Fetch Statistics
$stats = [
    'my_classes' => 0,
    'total_students' => 0,
    'pending_assessments' => 0,
    'grading_progress' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM classes WHERE teacher_id = $teacher_id");
if ($row = $result->fetch_assoc()) {
    $stats['my_classes'] = $row['count'];
}

$result = $conn->query("
    SELECT COUNT(DISTINCT e.student_id) as count 
    FROM enrollments e
    INNER JOIN classes cl ON e.class_id = cl.id
    WHERE cl.teacher_id = $teacher_id AND e.status = 'approved'
");
if ($row = $result->fetch_assoc()) {
    $stats['total_students'] = $row['count'];
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM assessment_scores ascore
    INNER JOIN assessments a ON ascore.assessment_id = a.id
    WHERE a.created_by = $teacher_id AND ascore.status = 'submitted'
");
if ($row = $result->fetch_assoc()) {
    $stats['pending_assessments'] = $row['count'];
}

// Calculate grading progress
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
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Teacher Dashboard</h4>
                    <small class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
                </div>
                <div>
                    <span class="badge" style="background: var(--navy); padding: 8px 15px;">
                        <?php echo date('F d, Y'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-box">
                    <p><i class="bi bi-door-open"></i> My Classes</p>
                    <h3><?php echo number_format($stats['my_classes']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: var(--navy);">
                    <p><i class="bi bi-people"></i> Total Students</p>
                    <h3><?php echo number_format($stats['total_students']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #ffc107;">
                    <p><i class="bi bi-clipboard-check"></i> Pending Assessments</p>
                    <h3><?php echo number_format($stats['pending_assessments']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-left-color: #28a745;">
                    <p><i class="bi bi-graph-up"></i> Grading Progress</p>
                    <h3><?php echo $stats['grading_progress']; ?>%</h3>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="minimal-card">
            <h5 class="section-title">Quick Actions</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <a href="my_classes.php" class="btn btn-minimal w-100">
                        <i class="bi bi-door-open"></i><br>View Classes
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="grading.php" class="btn btn-minimal w-100">
                        <i class="bi bi-calculator"></i><br>Grade Management
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="attendance.php" class="btn btn-minimal w-100">
                        <i class="bi bi-calendar-check"></i><br>Attendance
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="assessments.php" class="btn btn-minimal w-100">
                        <i class="bi bi-clipboard-check"></i><br>Assessments
                    </a>
                </div>
            </div>
        </div>

        <!-- Today's Classes -->
        <div class="minimal-card">
            <h5 class="section-title">My Classes</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead style="background-color: var(--light-gray);">
                        <tr>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Schedule</th>
                            <th>Room</th>
                            <th>Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $classes = $conn->query("
                            SELECT 
                                cl.id,
                                cl.section_name,
                                cl.schedule,
                                cl.room,
                                cl.current_enrolled,
                                s.subject_code,
                                s.subject_title
                            FROM classes cl
                            LEFT JOIN subjects s ON cl.subject_id = s.id
                            WHERE cl.teacher_id = $teacher_id
                            ORDER BY cl.id
                            LIMIT 10
                        ");
                        
                        while ($class = $classes->fetch_assoc()):
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($class['section_name'] ?? '-'); ?></td>
                            <td><small><?php echo htmlspecialchars($class['schedule'] ?? '-'); ?></small></td>
                            <td><?php echo htmlspecialchars($class['room'] ?? '-'); ?></td>
                            <td><span class="badge bg-info"><?php echo $class['current_enrolled']; ?></span></td>
                            <td>
                                <a href="classroom.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary-minimal">
                                    <i class="bi bi-box-arrow-in-right"></i> Enter
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>