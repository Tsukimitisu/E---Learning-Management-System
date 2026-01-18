<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Reports & Analytics";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$classes = $conn->query("
    SELECT cl.id, cl.section_name, s.subject_code, s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.teacher_id = $teacher_id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
        z-index: 10;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px;
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC REPORT UI --- */
    .report-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        padding: 30px;
    }

    .report-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0, 51, 102, 0.1);
    }

    .report-icon-bg {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 20px;
    }

    .stats-summary-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        border: none;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    }

    .stat-divider {
        width: 1px;
        background: #eee;
        height: 50px;
    }

    .btn-generate {
        background-color: var(--maroon);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 12px;
        transition: 0.3s;
    }
    .btn-generate:hover {
        background-color: #600000;
        color: white;
        transform: scale(1.02);
    }

    /* Mobile Logic */
    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
        .stat-divider { display: none; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-bar-chart-line-fill me-2"></i>Reports & Analytics</h4>
        <p class="text-muted small mb-0">Generate academic insights and documentation</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm rounded-pill">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <div class="row g-4 mb-5">
        <!-- Grade Summary -->
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="report-card">
                <div class="report-icon-bg bg-light text-maroon"><i class="bi bi-file-earmark-pdf"></i></div>
                <h5 class="fw-bold text-dark mb-2">Grade Summary</h5>
                <p class="text-muted small mb-4">Export a comprehensive PDF of student final marks for a specific class.</p>
                <form id="gradeSummaryForm" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TARGET CLASS</label>
                        <select class="form-select border-light shadow-sm" name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php 
                            $classes->data_seek(0);
                            while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['section_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-generate w-100 shadow-sm">
                        <i class="bi bi-file-pdf me-2"></i> Generate PDF
                    </button>
                </form>
            </div>
        </div>

        <!-- Attendance -->
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="report-card">
                <div class="report-icon-bg bg-light text-primary"><i class="bi bi-calendar-check"></i></div>
                <h5 class="fw-bold text-dark mb-2">Attendance Sheet</h5>
                <p class="text-muted small mb-4">Export attendance logs in Excel format for local records or auditing.</p>
                <form id="attendanceReportForm" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TARGET CLASS</label>
                        <select class="form-select border-light shadow-sm" name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php 
                            $classes->data_seek(0);
                            while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['section_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">FROM</label>
                            <input type="date" class="form-control form-control-sm border-light shadow-sm" name="date_from" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">TO</label>
                            <input type="date" class="form-control form-control-sm border-light shadow-sm" name="date_to" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-generate w-100 shadow-sm" style="background-color: var(--blue);">
                        <i class="bi bi-file-excel me-2"></i> Generate Excel
                    </button>
                </form>
            </div>
        </div>

        <!-- Class Performance -->
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="report-card">
                <div class="report-icon-bg bg-light text-success"><i class="bi bi-graph-up-arrow"></i></div>
                <h5 class="fw-bold text-dark mb-2">Class Analytics</h5>
                <p class="text-muted small mb-4">Visualize grade distributions and performance trends for your sections.</p>
                <form id="performanceReportForm" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TARGET CLASS</label>
                        <select class="form-select border-light shadow-sm" name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php 
                            $classes->data_seek(0);
                            while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['section_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-generate w-100 shadow-sm" style="background-color: #28a745;">
                        <i class="bi bi-pie-chart me-2"></i> View Analytics
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Stats Summary -->
    <div class="stats-summary-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
        <h6 class="fw-bold mb-4 text-uppercase small opacity-75" style="letter-spacing: 1px;">Overall Teaching Summary</h6>
        <?php
        /** BACKEND STATS - UNTOUCHED */
        $total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE teacher_id = $teacher_id")->fetch_assoc()['count'];
        $total_students = $conn->query("SELECT COUNT(DISTINCT e.student_id) as count FROM enrollments e INNER JOIN classes cl ON e.class_id = cl.id WHERE cl.teacher_id = $teacher_id AND e.status = 'approved'")->fetch_assoc()['count'];
        $avg_grade = $conn->query("SELECT AVG(g.final_grade) as avg FROM grades g INNER JOIN classes cl ON g.class_id = cl.id WHERE cl.teacher_id = $teacher_id AND g.final_grade > 0")->fetch_assoc()['avg'];
        $pass_rate = $conn->query("SELECT COUNT(CASE WHEN g.remarks = 'PASSED' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as rate FROM grades g INNER JOIN classes cl ON g.class_id = cl.id WHERE cl.teacher_id = $teacher_id AND g.final_grade > 0")->fetch_assoc()['rate'];
        ?>
        <div class="row align-items-center text-center g-4">
            <div class="col-md-3">
                <h3 class="fw-bold mb-0" style="color: var(--maroon);"><?php echo $total_classes; ?></h3>
                <small class="text-muted fw-bold">Active Classes</small>
            </div>
            <div class="col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0" style="color: var(--blue);"><?php echo $total_students; ?></h3>
                    <small class="text-muted fw-bold">Total Students</small>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0 text-success"><?php echo $avg_grade ? number_format($avg_grade, 2) : '0.00'; ?></h3>
                    <small class="text-muted fw-bold">Mean Grade</small>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0 text-info"><?php echo $pass_rate ? number_format($pass_rate, 1) : '0'; ?>%</h3>
                    <small class="text-muted fw-bold">Success Rate</small>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED --- -->
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