<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Branch Reports";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

// Get date range for reports (default to current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Attendance Report Data
$attendance_report = $conn->query("
    SELECT
        cl.id as class_id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        COUNT(DISTINCT e.student_id) as total_enrolled,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) as total_present,
        ROUND(
            (COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) * 100.0) /
            NULLIF(COUNT(DISTINCT e.student_id), 0), 2
        ) as attendance_rate
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN attendance a ON cl.id = a.class_id AND a.attendance_date BETWEEN '$start_date' AND '$end_date'
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title
    ORDER BY s.subject_code, cl.section_name
");

// Academic Performance Report
$performance_report = $conn->query("
    SELECT
        cl.id as class_id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        COUNT(DISTINCT e.student_id) as total_students,
        AVG(g.final_grade) as avg_final_grade,
        COUNT(CASE WHEN g.remarks = 'PASSED' THEN 1 END) as passed_count,
        ROUND(
            (COUNT(CASE WHEN g.remarks = 'PASSED' THEN 1 END) * 100.0) / NULLIF(COUNT(g.student_id), 0), 2
        ) as pass_rate
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN grades g ON cl.id = g.class_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title
    ORDER BY s.subject_code, cl.section_name
");

// Enrollment Statistics
$enrollment_stats = $conn->query("
    SELECT
        COUNT(DISTINCT cl.id) as total_classes,
        SUM(cl.max_capacity) as total_capacity,
        SUM(cl.current_enrolled) as total_enrolled,
        ROUND((SUM(cl.current_enrolled) * 100.0) / NULLIF(SUM(cl.max_capacity), 0), 2) as utilization_rate,
        COUNT(DISTINCT CASE WHEN cl.current_enrolled >= cl.max_capacity THEN cl.id END) as full_classes,
        COUNT(DISTINCT CASE WHEN cl.current_enrolled >= cl.max_capacity * 0.8 THEN cl.id END) as almost_full_classes
    FROM classes cl
    WHERE cl.branch_id = $branch_id
")->fetch_assoc();

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-file-earmark-text"></i> Branch Reports
            </h4>
            <div class="d-flex gap-2">
                <input type="date" id="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                <input type="date" id="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                <button class="btn btn-sm text-white" style="background-color: #800000;" onclick="updateReports()">
                    <i class="bi bi-arrow-clockwise"></i> Update
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Enrollment Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-bar-chart"></i> Enrollment Overview
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-2">
                                <h3><?php echo number_format($enrollment_stats['total_classes'] ?? 0); ?></h3>
                                <p class="text-muted">Total Classes</p>
                            </div>
                            <div class="col-md-2">
                                <h3><?php echo number_format($enrollment_stats['total_enrolled'] ?? 0); ?>/<?php echo number_format($enrollment_stats['total_capacity'] ?? 0); ?></h3>
                                <p class="text-muted">Enrolled/Capacity</p>
                            </div>
                            <div class="col-md-2">
                                <h3><?php echo number_format($enrollment_stats['utilization_rate'] ?? 0, 1); ?>%</h3>
                                <p class="text-muted">Utilization Rate</p>
                            </div>
                            <div class="col-md-2">
                                <h3><?php echo number_format($enrollment_stats['full_classes'] ?? 0); ?></h3>
                                <p class="text-muted">Full Classes</p>
                            </div>
                            <div class="col-md-2">
                                <h3><?php echo number_format($enrollment_stats['almost_full_classes'] ?? 0); ?></h3>
                                <p class="text-muted">Almost Full</p>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary btn-sm w-100" onclick="exportReport('enrollment')">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Report -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #800000; color: white;">
                        <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Attendance Report (<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)</h5>
                        <button class="btn btn-sm btn-light" onclick="exportReport('attendance')">
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Enrolled</th>
                                        <th>Present</th>
                                        <th>Attendance Rate</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $attendance_report->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['section_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['subject_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo number_format($row['total_enrolled'] ?? 0); ?></td>
                                        <td><?php echo number_format($row['total_present'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($row['attendance_rate'] ?? 0) >= 80 ? 'success' : (($row['attendance_rate'] ?? 0) >= 60 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($row['attendance_rate'] ?? 0, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $rate = $row['attendance_rate'] ?? 0;
                                            if ($rate >= 80) echo '<span class="text-success">Excellent</span>';
                                            elseif ($rate >= 60) echo '<span class="text-warning">Good</span>';
                                            else echo '<span class="text-danger">Needs Attention</span>';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Performance Report -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #003366; color: white;">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Academic Performance Report</h5>
                        <button class="btn btn-sm btn-light" onclick="exportReport('performance')">
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Students</th>
                                        <th>Avg Grade</th>
                                        <th>Passed</th>
                                        <th>Pass Rate</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $performance_report->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['section_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['subject_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo number_format($row['total_students'] ?? 0); ?></td>
                                        <td><?php echo $row['avg_final_grade'] ? number_format($row['avg_final_grade'], 2) : '-'; ?></td>
                                        <td><?php echo number_format($row['passed_count'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($row['pass_rate'] ?? 0) >= 75 ? 'success' : (($row['pass_rate'] ?? 0) >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($row['pass_rate'] ?? 0, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $rate = $row['pass_rate'] ?? 0;
                                            if ($rate >= 75) echo '<span class="text-success">Excellent</span>';
                                            elseif ($rate >= 50) echo '<span class="text-warning">Average</span>';
                                            else echo '<span class="text-danger">Needs Improvement</span>';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateReports() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    if (startDate && endDate) {
        window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
    }
}

function exportReport(type) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    // For now, show alert. In production, implement actual export
    showAlert(`Export functionality for ${type} report will be implemented.`, 'info');
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>