<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Enrollment Reports";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
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
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .report-stat-card {
        border-radius: 15px; padding: 25px; border: none; color: white;
        transition: 0.3s; height: 100%; display: flex; align-items: center; gap: 20px;
    }
    .report-stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }

    .nav-pills-modern .nav-link {
        color: #666; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
        padding: 12px 25px; border-radius: 10px; transition: 0.3s; margin-right: 10px;
        background: #fff; border: 1px solid #eee;
    }
    .nav-pills-modern .nav-link.active {
        background-color: var(--maroon); color: white; border-color: var(--maroon); box-shadow: 0 4px 12px rgba(128,0,0,0.2);
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;
    }
    .card-header-modern { background: #fcfcfc; padding: 15px 25px; border-bottom: 1px solid #eee; font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; }

    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }

    .btn-export { background: var(--blue); color: white; border: none; border-radius: 50px; padding: 8px 20px; font-weight: 600; font-size: 0.8rem; }
    .btn-export:hover { background: #002244; color: white; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .nav-pills-modern { flex-direction: column; } .nav-pills-modern .nav-link { margin-right: 0; margin-bottom: 5px; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-file-earmark-bar-graph me-2 text-maroon"></i>Reporting & Analytics</h4>
            <p class="text-muted small mb-0">Institutional data insights and academic metrics</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-graph-up me-1 text-primary"></i> Live Analytics Mode
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Tabbed Navigation -->
    <ul class="nav nav-pills nav-pills-modern mb-4 animate__animated animate__fadeIn" id="reportTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="stats-tab" data-bs-toggle="pill" data-bs-target="#stats" type="button" role="tab"><i class="bi bi-pie-chart-fill me-2"></i>Statistics Overview</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="program-tab" data-bs-toggle="pill" data-bs-target="#program" type="button" role="tab"><i class="bi bi-mortarboard-fill me-2"></i>Program Reports</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="class-tab" data-bs-toggle="pill" data-bs-target="#class" type="button" role="tab"><i class="bi bi-table me-2"></i>Class Records</button>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">
        <!-- TAB 1: STATISTICS OVERVIEW -->
        <div class="tab-pane fade show active" id="stats" role="tabpanel">
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="report-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);">
                        <div><h3 class="fw-bold mb-0"><?php echo number_format($stats['total'] ?? 0); ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Total Enrolled</small></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="report-stat-card shadow-sm" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                        <div><h3 class="fw-bold mb-0"><?php echo number_format($stats['approved'] ?? 0); ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Approved</small></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="report-stat-card shadow-sm" style="background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #333;">
                        <div><h3 class="fw-bold mb-0"><?php echo number_format($stats['pending'] ?? 0); ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Pending</small></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="report-stat-card shadow-sm" style="background: linear-gradient(135deg, var(--maroon) 0%, #4a0000 100%);">
                        <div><h3 class="fw-bold mb-0"><?php echo number_format($stats['rejected'] ?? 0); ?></h3><small class="text-uppercase fw-bold opacity-75" style="font-size:0.6rem;">Rejected</small></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="main-card-modern p-4">
                        <h6 class="fw-bold mb-4 text-blue"><i class="bi bi-pie-chart me-2"></i>Enrollment by Program</h6>
                        <canvas id="enrollmentByProgramChart" height="250"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="main-card-modern p-4">
                        <h6 class="fw-bold mb-4 text-maroon"><i class="bi bi-graph-up me-2"></i>Registration Trend (6 Months)</h6>
                        <canvas id="enrollmentTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PROGRAM REPORTS -->
        <div class="tab-pane fade" id="program" role="tabpanel">
            <div class="d-flex justify-content-end mb-3">
                <a href="process/export_report.php?type=program" class="btn btn-export shadow-sm"><i class="bi bi-file-earmark-excel me-2"></i>Export Program Data</a>
            </div>
            <div class="main-card-modern">
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0">
                        <thead><tr><th class="ps-4">Academic Program Code</th><th class="text-center">Enrolled Population</th></tr></thead>
                        <tbody>
                            <?php $program_counts->data_seek(0); while ($row = $program_counts->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($row['course_code']); ?></td>
                                    <td class="text-center"><span class="badge bg-light text-blue border border-blue px-3"><?php echo number_format($row['count']); ?> Students</span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: CLASS RECORDS -->
        <div class="tab-pane fade" id="class" role="tabpanel">
            <div class="main-card-modern p-4 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <label class="small fw-bold text-muted text-uppercase mb-2 d-block">Select Class Section</label>
                        <select class="form-select border-light shadow-sm rounded-pill" id="classSelect">
                            <option value="">-- Search & Choose Section --</option>
                            <?php while ($cl = $class_list->fetch_assoc()): ?>
                                <option value="<?php echo $cl['id']; ?>">
                                    <?php echo htmlspecialchars(($cl['subject_code'] ?? 'N/A') . ' - ' . $cl['section_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end pt-md-4">
                        <a href="process/export_report.php?type=class" class="btn btn-export w-100 shadow-sm" id="classExportBtn">
                            <i class="bi bi-download me-2"></i> Export Section CSV
                        </a>
                    </div>
                </div>
            </div>

            <div class="main-card-modern">
                <div class="table-responsive">
                    <table class="table table-hover table-modern align-middle mb-0" id="classRecordsTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Student ID</th>
                                <th>Student Name</th>
                                <th class="text-center">Midterm</th>
                                <th class="text-center">Final</th>
                                <th class="text-center">GWA</th>
                                <th class="text-center">Attendance</th>
                                <th class="text-center pe-4">Finance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="7" class="text-center py-5 text-muted small fst-italic">Please select a class section above to load data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- CHART & AJAX LOGIC - UNTOUCHED --- -->
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
            backgroundColor: ['#800000', '#003366', '#28a745', '#17a2b8', '#ffc107', '#fd7e14']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

const trendLabels = [<?php $trend->data_seek(0); $tl = []; while ($row = $trend->fetch_assoc()) { $tl[] = "'" . $row['month'] . "'"; } echo implode(',', $tl); ?>];
const trendData = [<?php $trend->data_seek(0); $td = []; while ($row = $trend->fetch_assoc()) { $td[] = $row['count']; } echo implode(',', $td); ?>];

new Chart(document.getElementById('enrollmentTrendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'New Enrollments',
            data: trendData,
            borderColor: '#800000',
            backgroundColor: 'rgba(128, 0, 0, 0.05)',
            fill: true,
            tension: 0.4
        }]
    },
    options: { responsive: true }
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
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted small">Select a class section above.</td></tr>';
        return;
    }

    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

    try {
        const response = await fetch(`process/get_class_records.php?class_id=${classId}`);
        const data = await response.json();
        if (data.status !== 'success') {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Fetch failed.</td></tr>';
            return;
        }
        tbody.innerHTML = data.records.map(r => `
            <tr>
                <td class="ps-4 fw-bold text-maroon">${r.student_no}</td>
                <td class="fw-bold text-dark">${r.full_name}</td>
                <td class="text-center">${r.midterm ?? '-'}</td>
                <td class="text-center">${r.final ?? '-'}</td>
                <td class="text-center fw-bold text-blue">${r.final_grade ?? '-'}</td>
                <td class="text-center"><span class="badge bg-light text-dark border">${r.attendance_percentage ?? 0}%</span></td>
                <td class="text-center pe-4"><span class="badge rounded-pill bg-info">${r.payment_status}</span></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Server Error.</td></tr>';
    }
});
</script>
</body>
</html>