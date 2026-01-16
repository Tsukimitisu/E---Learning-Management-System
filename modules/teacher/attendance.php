<?php
require_once '../../config/init.php';if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
header('Location: ../../index.php');
exit();
}$page_title = "Attendance Management";
$teacher_id = $_SESSION['user_id'];// Fetch teacher's classes
$classes = $conn->query("
SELECT
cl.id,
cl.section_name,
s.subject_code,
s.subject_title,
COUNT(DISTINCT e.student_id) as student_count
FROM classes cl
LEFT JOIN subjects s ON cl.subject_id = s.id
LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
WHERE cl.teacher_id = $teacher_id
GROUP BY cl.id
ORDER BY s.subject_code
");include '../../includes/header.php';
?><link rel="stylesheet" href="../../assets/css/minimal.css"><div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?><div id="content">
    <div class="minimal-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Attendance Management</h4>
                <small class="text-muted">Track student attendance</small>
            </div>
            <a href="dashboard.php" class="btn btn-minimal">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>    <div id="alertContainer"></div>    <!-- Class Selection -->
    <div class="minimal-card">
        <h5 class="section-title">Select Class</h5>
        <div class="row">
            <?php while ($class = $classes->fetch_assoc()): ?>
            <div class="col-md-6 mb-3">
                <div class="card h-100" style="border-left: 4px solid var(--maroon);">
                    <div class="card-body">
                        <h6 class="card-title" style="color: var(--navy);">
                            <?php echo htmlspecialchars($class['subject_code'] ?: 'N/A'); ?> - <?php echo htmlspecialchars($class['section_name'] ?: 'N/A'); ?>
                        </h6>
                        <p class="card-text mb-2">
                            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?: 'N/A'); ?></small>
                        </p>
                        <div class="mb-3">
                            <span class="badge bg-info"><?php echo $class['student_count']; ?> Students</span>
                        </div>
                        <div class="d-grid">
                            <a href="attendance_sheet.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary-minimal">
                                <i class="bi bi-calendar-check"></i> Take Attendance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
