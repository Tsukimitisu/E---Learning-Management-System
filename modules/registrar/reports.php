<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Enrollment Reports";

$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$programs = $conn->query("SELECT id, course_code, title FROM courses ORDER BY course_code");

$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
FROM enrollments")->fetch_assoc();

$program_counts = $conn->query("SELECT c.course_code, COUNT(e.id) as count
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN courses c ON s.course_id = c.id
    GROUP BY c.course_code
    ORDER BY c.course_code
");

$trend = $conn->query("SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as count
    FROM enrollments
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m')
");

$class_list = $conn->query("SELECT cl.id, cl.section_name, cs.subject_code, cs.subject_title
    FROM classes cl
    LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
    ORDER BY cs.subject_code, cl.section_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-file-earmark-bar-graph"></i> Reports
            </h4>
        </div>

        <div id="alertContainer"></div>

        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">Enrollment Statistics</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="program-tab" data-bs-toggle="tab" data-bs-target="#program" type="button" role="tab">Program-wise Report</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="class-tab" data-bs-toggle="tab" data-bs-target="#class" type="button" role="tab">Class Records</button>
            </li>
        </ul>

        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="stats" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <p><i class="bi bi-people"></i> Total Enrollments</p>
                            <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <p><i class="bi bi-check-circle"></i> Approved</p>
                            <h3><?php echo number_format($stats['approved'] ?? 0); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <p><i class="bi bi-clock-history"></i> Pending</p>
                            <h3><?php echo number_format($stats['pending'] ?? 0); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <p><i class="bi bi-x-circle"></i> Rejected</p>
                            <h3><?php echo number_format($stats['rejected'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header" style="background-color: #003366; color: white;">
                                Enrollment by Program
                            </div>
                            <div class="card-body">
                                <canvas id="enrollmentByProgramChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header" style="background-color: #800000; color: white;">
                                Enrollment Trend
                            </div>
                            <div class="card-body">
                                <canvas id="enrollmentTrendChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="program" role="tabpanel">
                <div class="d-flex justify-content-end mb-2">
                    <a href="process/export_report.php?type=program" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Export CSV</a>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Program</th>
                                        <th>Enrolled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $program_counts->data_seek(0); while ($row = $program_counts->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                            <td><?php echo number_format($row['count']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="class" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select class="form-select" id="classSelect">
                            <option value="">-- Select Class --</option>
                            <?php while ($cl = $class_list->fetch_assoc()): ?>
                                <option value="<?php echo $cl['id']; ?>">
                                    <?php echo htmlspecialchars(($cl['subject_code'] ?? 'N/A') . ' - ' . $cl['section_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="process/export_report.php?type=class" class="btn btn-sm btn-outline-primary" id="classExportBtn"><i class="bi bi-download"></i> Export CSV</a>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="classRecordsTable">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Student No</th>
                                        <th>Name</th>
                                        <th>Midterm</th>
                                        <th>Final</th>
                                        <th>Final Grade</th>
                                        <th>Remarks</th>
                                        <th>Attendance %</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="8" class="text-center text-muted">Select a class to view records</td></tr>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const programLabels = [<?php $program_counts->data_seek(0); $labels = []; while ($row = $program_counts->fetch_assoc()) { $labels[] = "'" . $row['course_code'] . "'"; } echo implode(',', $labels); ?>];
const programData = [<?php $program_counts->data_seek(0); $data = []; while ($row = $program_counts->fetch_assoc()) { $data[] = $row['count']; } echo implode(',', $data); ?>];

new Chart(document.getElementById('enrollmentByProgramChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: programLabels,
        datasets: [{
            data: programData,
            backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Enrollment Distribution by Program' } }
    }
});

const trendLabels = [<?php $trend->data_seek(0); $tl = []; while ($row = $trend->fetch_assoc()) { $tl[] = "'" . $row['month'] . "'"; } echo implode(',', $tl); ?>];
const trendData = [<?php $trend->data_seek(0); $td = []; while ($row = $trend->fetch_assoc()) { $td[] = $row['count']; } echo implode(',', $td); ?>];

new Chart(document.getElementById('enrollmentTrendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Enrollments',
            data: trendData,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4
        }]
    },
    options: { responsive: true, plugins: { title: { display: true, text: 'Enrollment Trend (Last 6 Months)' } } }
});

document.getElementById('classSelect').addEventListener('change', async function() {
    const classId = this.value;
    const tbody = document.querySelector('#classRecordsTable tbody');
    const exportBtn = document.getElementById('classExportBtn');
    if (classId) {
        exportBtn.href = `process/export_report.php?type=class&class_id=${classId}`;
    } else {
        exportBtn.href = 'process/export_report.php?type=class';
    }
    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Select a class to view records</td></tr>';
        return;
    }
    const response = await fetch(`process/get_class_records.php?class_id=${classId}`);
    const data = await response.json();
    if (data.status !== 'success') {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load records</td></tr>';
        return;
    }
    tbody.innerHTML = data.records.map(r => `
        <tr>
            <td>${r.student_no}</td>
            <td>${r.full_name}</td>
            <td>${r.midterm ?? '-'}</td>
            <td>${r.final ?? '-'}</td>
            <td>${r.final_grade ?? '-'}</td>
            <td>${r.remarks ?? '-'}</td>
            <td>${r.attendance_percentage ?? 0}%</td>
            <td>${r.payment_status}</td>
        </tr>
    `).join('');
});
</script>
</body>
</html>
