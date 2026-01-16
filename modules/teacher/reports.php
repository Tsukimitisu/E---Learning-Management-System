<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Reports & Analytics";
$teacher_id = $_SESSION['user_id'];

// Fetch teacher's classes for dropdown
$classes = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code, s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.teacher_id = $teacher_id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Reports & Analytics</h4>
                    <small class="text-muted">Generate class performance and attendance reports</small>
                </div>
                <a href="dashboard.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Report Types -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="minimal-card">
                    <h5 class="section-title">Grade Summary Report</h5>
                    <p class="text-muted">Generate summary of student grades per class</p>
                    <form id="gradeSummaryForm">
                        <div class="mb-3">
                            <label class="form-label">Select Class</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php 
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): 
                                    $subject_code = $class['subject_code'] ?? 'N/A';
                                    $section_name = $class['section_name'] ?? 'N/A';
                                ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($subject_code . ' - ' . $section_name); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary-minimal w-100">
                            <i class="bi bi-file-earmark-pdf"></i> Generate PDF
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="minimal-card">
                    <h5 class="section-title">Attendance Report</h5>
                    <p class="text-muted">Generate attendance summary for selected period</p>
                    <form id="attendanceReportForm">
                        <div class="mb-3">
                            <label class="form-label">Select Class</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php 
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): 
                                    $subject_code = $class['subject_code'] ?? 'N/A';
                                    $section_name = $class['section_name'] ?? 'N/A';
                                ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($subject_code . ' - ' . $section_name); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">From</label>
                                <input type="date" class="form-control" name="date_from" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">To</label>
                                <input type="date" class="form-control" name="date_to" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-minimal w-100">
                            <i class="bi bi-file-earmark-excel"></i> Generate Excel
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="minimal-card">
                    <h5 class="section-title">Class Performance</h5>
                    <p class="text-muted">View overall class performance analytics</p>
                    <form id="performanceReportForm">
                        <div class="mb-3">
                            <label class="form-label">Select Class</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">-- Select Class --</option>
                                <?php 
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): 
                                    $subject_code = $class['subject_code'] ?? 'N/A';
                                    $section_name = $class['section_name'] ?? 'N/A';
                                ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($subject_code . ' - ' . $section_name); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary-minimal w-100">
                            <i class="bi bi-bar-chart"></i> View Analytics
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="minimal-card">
            <h5 class="section-title">Overall Teaching Statistics</h5>
            <div class="row">
                <?php
                // Get overall stats
                $total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE teacher_id = $teacher_id")->fetch_assoc()['count'];
                $total_students = $conn->query("
                    SELECT COUNT(DISTINCT e.student_id) as count 
                    FROM enrollments e
                    INNER JOIN classes cl ON e.class_id = cl.id
                    WHERE cl.teacher_id = $teacher_id AND e.status = 'approved'
                ")->fetch_assoc()['count'];
                $avg_grade = $conn->query("
                    SELECT AVG(g.final_grade) as avg 
                    FROM grades g
                    INNER JOIN classes cl ON g.class_id = cl.id
                    WHERE cl.teacher_id = $teacher_id AND g.final_grade > 0
                ")->fetch_assoc()['avg'];
                $pass_rate = $conn->query("
                    SELECT 
                        COUNT(CASE WHEN g.remarks = 'PASSED' THEN 1 END) * 100.0 / COUNT(*) as rate
                    FROM grades g
                    INNER JOIN classes cl ON g.class_id = cl.id
                    WHERE cl.teacher_id = $teacher_id AND g.final_grade > 0
                ")->fetch_assoc()['rate'];
                ?>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 style="color: var(--maroon);"><?php echo $total_classes; ?></h3>
                        <p class="text-muted">Total Classes</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 style="color: var(--navy);"><?php echo $total_students; ?></h3>
                        <p class="text-muted">Total Students</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 style="color: #28a745;"><?php echo $avg_grade ? number_format($avg_grade, 2) : '-'; ?></h3>
                        <p class="text-muted">Average Grade</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 style="color: #17a2b8;"><?php echo $pass_rate ? number_format($pass_rate, 1) : '0'; ?>%</h3>
                        <p class="text-muted">Pass Rate</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('gradeSummaryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const classId = e.target.querySelector('[name="class_id"]').value;
    window.open('process/generate_grade_report.php?class_id=' + classId, '_blank');
});

document.getElementById('attendanceReportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData).toString();
    window.open('process/generate_attendance_report.php?' + params, '_blank');
});

document.getElementById('performanceReportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const classId = e.target.querySelector('[name="class_id"]').value;
    window.location.href = 'performance_analytics.php?class_id=' + classId;
});
</script>
</body>
</html>