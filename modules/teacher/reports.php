<?php
require_once '../../config/init.php';

// Fix: Check both role_id and role for compatibility
$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Reports & Analytics";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC
 */
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;
$semester_map = [1 => '1st', 2 => '2nd', 3 => 'summer'];

// Get teacher's assigned subjects
$subjects_query = $conn->prepare("
    SELECT 
        tsa.id as assignment_id,
        tsa.curriculum_subject_id as subject_id,
        cs.subject_code,
        cs.subject_title,
        cs.semester,
        cs.program_id,
        cs.year_level_id,
        cs.shs_strand_id,
        cs.shs_grade_level_id,
        tsa.branch_id
    FROM teacher_subject_assignments tsa
    INNER JOIN curriculum_subjects cs ON tsa.curriculum_subject_id = cs.id
    WHERE tsa.teacher_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1
    ORDER BY cs.subject_code
");
$subjects_query->bind_param("ii", $teacher_id, $current_ay_id);
$subjects_query->execute();
$teacher_subjects = $subjects_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Build sections list with subject info for unified dropdown
$section_options = [];
foreach ($teacher_subjects as $subject) {
    $semester_str = $semester_map[$subject['semester']] ?? '1st';
    
    if (!empty($subject['program_id'])) {
        $sections_query = $conn->prepare("
            SELECT s.id, s.section_name, p.program_code
            FROM sections s
            LEFT JOIN programs p ON s.program_id = p.id
            WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ?
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ");
        $sections_query->bind_param("iisii", $subject['program_id'], $subject['year_level_id'], 
            $semester_str, $subject['branch_id'], $current_ay_id);
    } else {
        $sections_query = $conn->prepare("
            SELECT s.id, s.section_name, ss.strand_code as program_code
            FROM sections s
            LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
            WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ?
            AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1
        ");
        $sections_query->bind_param("iisii", $subject['shs_strand_id'], $subject['shs_grade_level_id'], 
            $semester_str, $subject['branch_id'], $current_ay_id);
    }
    $sections_query->execute();
    $sections_result = $sections_query->get_result();
    
    while ($section = $sections_result->fetch_assoc()) {
        $section_options[] = [
            'section_id' => $section['id'],
            'subject_id' => $subject['subject_id'],
            'label' => $subject['subject_code'] . ' - ' . $section['section_name'],
            'subject_title' => $subject['subject_title'],
            'program_code' => $section['program_code'] ?? ''
        ];
    }
}

/** STATS - De-duplicated counting (same pattern as dashboard.php) */
$total_subjects = count($teacher_subjects);

// Collect unique section filter keys
$section_filters = [];
foreach ($teacher_subjects as $subject) {
    $semester_str = $semester_map[$subject['semester']] ?? '1st';
    if (!empty($subject['program_id'])) {
        $key = "college_{$subject['program_id']}_{$subject['year_level_id']}_{$semester_str}_{$subject['branch_id']}";
        if (!isset($section_filters[$key])) {
            $section_filters[$key] = ['type' => 'college', 'program_id' => $subject['program_id'], 'year_level_id' => $subject['year_level_id'], 'semester' => $semester_str, 'branch_id' => $subject['branch_id']];
        }
    } else {
        $key = "shs_{$subject['shs_strand_id']}_{$subject['shs_grade_level_id']}_{$semester_str}_{$subject['branch_id']}";
        if (!isset($section_filters[$key])) {
            $section_filters[$key] = ['type' => 'shs', 'shs_strand_id' => $subject['shs_strand_id'], 'shs_grade_level_id' => $subject['shs_grade_level_id'], 'semester' => $semester_str, 'branch_id' => $subject['branch_id']];
        }
    }
}

$all_section_ids = [];
foreach ($section_filters as $filter) {
    if ($filter['type'] === 'college') {
        $count_query = $conn->prepare("SELECT s.id FROM sections s WHERE s.program_id = ? AND s.year_level_id = ? AND s.semester = ? AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1");
        $count_query->bind_param("iisii", $filter['program_id'], $filter['year_level_id'], $filter['semester'], $filter['branch_id'], $current_ay_id);
    } else {
        $count_query = $conn->prepare("SELECT s.id FROM sections s WHERE s.shs_strand_id = ? AND s.shs_grade_level_id = ? AND s.semester = ? AND s.branch_id = ? AND s.academic_year_id = ? AND s.is_active = 1");
        $count_query->bind_param("iisii", $filter['shs_strand_id'], $filter['shs_grade_level_id'], $filter['semester'], $filter['branch_id'], $current_ay_id);
    }
    $count_query->execute();
    $sec_result = $count_query->get_result();
    while ($sec = $sec_result->fetch_assoc()) {
        $all_section_ids[$sec['id']] = true;
    }
}

$total_sections = count($all_section_ids);
$total_students = 0;
$avg_grade = 0;
$pass_rate = 0;

if (!empty($all_section_ids)) {
    $section_id_list = implode(',', array_map('intval', array_keys($all_section_ids)));
    $total_students = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM section_students WHERE section_id IN ($section_id_list) AND status = 'active'")->fetch_assoc()['cnt'] ?? 0;
    
    $grade_stats = $conn->query("
        SELECT AVG(final_grade) as avg_grade,
               COUNT(CASE WHEN remarks = 'PASSED' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as pass_rate
        FROM grades WHERE section_id IN ($section_id_list) AND final_grade > 0
    ")->fetch_assoc();
    $avg_grade = $grade_stats['avg_grade'] ?? 0;
    $pass_rate = $grade_stats['pass_rate'] ?? 0;
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/reports.css">

<!-- Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-bar-chart-line-fill me-2"></i>Reports & Analytics</h4>
            <p class="text-muted small mb-0">Select a class, then view grades or analytics</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
    <!-- Unified class selector -->
    <div class="class-selector-bar">
        <div class="row align-items-center g-2">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-mortarboard-fill text-maroon"></i></span>
                    <select class="form-select border-start-0 shadow-sm" id="classSelector">
                        <option value="">-- Choose a class to get started --</option>
                        <?php foreach ($section_options as $i => $opt): ?>
                            <option value="<?php echo $opt['section_id'] . '_' . $opt['subject_id']; ?>" 
                                    data-label="<?php echo htmlspecialchars($opt['label']); ?>"
                                    data-subject="<?php echo htmlspecialchars($opt['subject_title']); ?>"
                                    data-program="<?php echo htmlspecialchars($opt['program_code']); ?>">
                                <?php echo htmlspecialchars($opt['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6 d-flex gap-2 justify-content-md-end" id="quickActions" style="display: none !important;">
                <button class="btn btn-sm btn-outline-danger rounded-pill px-3 quick-action-btn" onclick="exportGradePDF()" title="Export grades as printable report">
                    <i class="bi bi-file-pdf me-1"></i> Export PDF
                </button>

            </div>
        </div>
    </div>
</div>

<!-- Scrollable Body -->
<div class="body-scroll-part">

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-5 animate__animated animate__fadeIn">
        <div class="empty-state-icon mb-4">
            <i class="bi bi-clipboard2-data" style="font-size: 4rem; color: #ccc;"></i>
        </div>
        <h5 class="text-muted fw-bold">Select a Class Above</h5>
        <p class="text-muted small mx-auto" style="max-width: 400px;">
            Choose a subject and section from the dropdown to view grade summaries and class analytics.
        </p>

        <?php if (empty($section_options)): ?>
            <div class="alert alert-warning d-inline-block mt-3">
                <i class="bi bi-exclamation-triangle me-2"></i>
                No classes assigned for this academic year. Contact your administrator.
            </div>
        <?php endif; ?>
    </div>

    <!-- Report Content (hidden until class is selected) -->
    <div id="reportContent" style="display: none;">
        
        <!-- Class info banner -->
        <div class="class-info-banner animate__animated animate__fadeInUp mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0" id="selectedClassName">-</h5>
                    <small class="text-muted" id="selectedClassDetail">-</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-maroon-light px-3 py-2" id="badgeStudents">0 Students</span>
                    <span class="badge bg-blue-light px-3 py-2" id="badgeGraded">0 Graded</span>
                </div>
            </div>
        </div>

        <!-- Report Tabs -->
        <ul class="nav nav-pills report-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#gradesTab" type="button" role="tab">
                    <i class="bi bi-journal-text me-1"></i> Grades
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#analyticsTab" type="button" role="tab">
                    <i class="bi bi-graph-up me-1"></i> Analytics
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- GRADES TAB -->
            <div class="tab-pane fade show active" id="gradesTab" role="tabpanel">
                <div class="report-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-table me-2 text-maroon"></i>Grade Summary</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" onclick="exportGradePDF()">
                            <i class="bi bi-file-pdf me-1"></i> Download PDF
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="gradesTable">
                            <thead>
                                <tr>
                                    <th class="text-muted small">#</th>
                                    <th class="text-muted small">STUDENT NO</th>
                                    <th class="text-muted small">STUDENT NAME</th>
                                    <th class="text-muted small text-center">PRELIM</th>
                                    <th class="text-muted small text-center">MIDTERM</th>
                                    <th class="text-muted small text-center">PREFINAL</th>
                                    <th class="text-muted small text-center">FINAL</th>
                                    <th class="text-muted small text-center">FINAL GRADE</th>
                                    <th class="text-muted small text-center">REMARKS</th>
                                </tr>
                            </thead>
                            <tbody id="gradesBody">
                                <tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS TAB -->
            <div class="tab-pane fade" id="analyticsTab" role="tabpanel">
                <div class="row g-4">
                    <!-- Grade Distribution -->
                    <div class="col-md-6">
                        <div class="report-panel h-100">
                            <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart me-2 text-success"></i>Grade Distribution</h6>
                            <canvas id="gradeDistChart" height="220"></canvas>
                        </div>
                    </div>
                    <!-- Pass / Fail -->
                    <div class="col-md-6">
                        <div class="report-panel h-100">
                            <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-info"></i>Pass vs Fail</h6>
                            <div class="d-flex align-items-center justify-content-center" style="min-height: 220px;">
                                <canvas id="passFailChart" height="200" width="200"></canvas>
                                <div class="ms-4 text-center" id="passFailLegend"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Stats cards row -->
                    <div class="col-12">
                        <div class="row g-3" id="analyticsStats">
                            <div class="col-6 col-md-3">
                                <div class="analytics-stat-card text-center">
                                    <div class="analytics-stat-value text-maroon" id="statHighest">-</div>
                                    <div class="analytics-stat-label">Highest Grade</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="analytics-stat-card text-center">
                                    <div class="analytics-stat-value text-danger" id="statLowest">-</div>
                                    <div class="analytics-stat-label">Lowest Grade</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="analytics-stat-card text-center">
                                    <div class="analytics-stat-value text-success" id="statMean">-</div>
                                    <div class="analytics-stat-label">Class Average</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="analytics-stat-card text-center">
                                    <div class="analytics-stat-value text-primary" id="statMedian">-</div>
                                    <div class="analytics-stat-label">Median Grade</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Grade per period comparison -->
                    <div class="col-12">
                        <div class="report-panel">
                            <h6 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow me-2 text-warning"></i>Average by Grading Period</h6>
                            <canvas id="periodChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Teaching Summary -->
    <div class="stats-summary-card mt-5 animate__animated animate__fadeInUp">
        <h6 class="fw-bold mb-4 text-uppercase small opacity-75" style="letter-spacing: 1px;">
            <i class="bi bi-clipboard-data me-2"></i>Overall Teaching Summary
        </h6>
        <div class="row align-items-center text-center g-4">
            <div class="col-6 col-md-3">
                <h3 class="fw-bold mb-0" style="color: var(--maroon);"><?php echo $total_subjects; ?></h3>
                <small class="text-muted fw-bold">My Subjects</small>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0" style="color: var(--blue);"><?php echo $total_sections; ?></h3>
                    <small class="text-muted fw-bold">Sections</small>
                </div>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0" style="color: var(--blue);"><?php echo $total_students; ?></h3>
                    <small class="text-muted fw-bold">Total Students</small>
                </div>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-center justify-content-center">
                <div class="stat-divider d-none d-md-block"></div>
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-0 text-info"><?php echo $pass_rate ? number_format($pass_rate, 1) : '0'; ?>%</h3>
                    <small class="text-muted fw-bold">Overall Pass Rate</small>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/** ============================
 *  Class Reports - Full JS Engine
 *  ============================ */
let currentSectionId = null;
let currentSubjectId = null;
let gradeDistChart = null;
let passFailChart = null;
let periodChart = null;

// Unified class selector handler
document.getElementById('classSelector').addEventListener('change', function() {
    const val = this.value;
    if (!val) {
        document.getElementById('emptyState').style.display = '';
        document.getElementById('reportContent').style.display = 'none';
        document.getElementById('quickActions').style.display = 'none !important';
        document.getElementById('quickActions').classList.add('d-none');
        return;
    }
    
    const [sectionId, subjectId] = val.split('_');
    currentSectionId = sectionId;
    currentSubjectId = subjectId;
    
    // Update header info
    const opt = this.options[this.selectedIndex];
    document.getElementById('selectedClassName').textContent = opt.dataset.label || opt.textContent.trim();
    document.getElementById('selectedClassDetail').textContent = (opt.dataset.subject || '') + (opt.dataset.program ? ' | ' + opt.dataset.program : '');
    
    // Show content
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('reportContent').style.display = '';
    document.getElementById('quickActions').style.cssText = '';
    document.getElementById('quickActions').classList.remove('d-none');
    
    // Load all tabs
    loadGrades();
    loadAnalytics();
});

function apiCall(action, params = {}) {
    params.action = action;
    params.section_id = currentSectionId;
    params.subject_id = currentSubjectId;
    const qs = new URLSearchParams(params).toString();
    return fetch('process/reports_api.php?' + qs)
        .then(r => r.json())
        .catch(err => { console.error(err); return { success: false, error: 'Network error' }; });
}

/* ---- GRADES TAB ---- */
function loadGrades() {
    document.getElementById('gradesBody').innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading grades...</td></tr>';
    
    apiCall('grades').then(data => {
        if (!data.success) {
            document.getElementById('gradesBody').innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">' + (data.error || 'Failed to load') + '</td></tr>';
            return;
        }
        
        document.getElementById('badgeStudents').textContent = data.students.length + ' Students';
        document.getElementById('badgeGraded').textContent = data.graded_count + ' Graded';
        
        if (data.students.length === 0) {
            document.getElementById('gradesBody').innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No students enrolled in this class</td></tr>';
            return;
        }
        
        let html = '';
        data.students.forEach((s, i) => {
            const remarkClass = s.remarks === 'PASSED' ? 'badge bg-success' : (s.remarks === 'FAILED' ? 'badge bg-danger' : 'text-muted');
            html += '<tr>' +
                '<td class="text-muted small">' + (i+1) + '</td>' +
                '<td class="small">' + escHtml(s.student_no) + '</td>' +
                '<td class="fw-medium">' + escHtml(s.student_name) + '</td>' +
                '<td class="text-center small">' + fmtGrade(s.prelim) + '</td>' +
                '<td class="text-center small">' + fmtGrade(s.midterm) + '</td>' +
                '<td class="text-center small">' + fmtGrade(s.prefinal) + '</td>' +
                '<td class="text-center small">' + fmtGrade(s.final) + '</td>' +
                '<td class="text-center fw-bold">' + fmtGrade(s.final_grade) + '</td>' +
                '<td class="text-center"><span class="' + remarkClass + '">' + escHtml(s.remarks || '-') + '</span></td>' +
            '</tr>';
        });
        document.getElementById('gradesBody').innerHTML = html;
    });
}

/* ---- ANALYTICS TAB ---- */
function loadAnalytics() {
    apiCall('analytics').then(data => {
        if (!data.success) return;
        
        // Stats
        document.getElementById('statHighest').textContent = data.stats.highest ? parseFloat(data.stats.highest).toFixed(2) : '-';
        document.getElementById('statLowest').textContent = data.stats.lowest ? parseFloat(data.stats.lowest).toFixed(2) : '-';
        document.getElementById('statMean').textContent = data.stats.mean ? parseFloat(data.stats.mean).toFixed(2) : '-';
        document.getElementById('statMedian').textContent = data.stats.median ? parseFloat(data.stats.median).toFixed(2) : '-';
        
        // Grade Distribution chart
        if (gradeDistChart) gradeDistChart.destroy();
        const distCtx = document.getElementById('gradeDistChart').getContext('2d');
        gradeDistChart = new Chart(distCtx, {
            type: 'bar',
            data: {
                labels: data.distribution.labels,
                datasets: [{
                    label: 'Students',
                    data: data.distribution.counts,
                    backgroundColor: [
                        '#dc3545', '#fd7e14', '#ffc107', '#20c997', '#198754'
                    ],
                    borderRadius: 8,
                    maxBarThickness: 50
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
        
        // Pass/Fail pie chart
        if (passFailChart) passFailChart.destroy();
        const pfCtx = document.getElementById('passFailChart').getContext('2d');
        const passed = data.pass_fail.passed || 0;
        const failed = data.pass_fail.failed || 0;
        const noGrade = data.pass_fail.no_grade || 0;
        
        passFailChart = new Chart(pfCtx, {
            type: 'doughnut',
            data: {
                labels: ['Passed', 'Failed', 'No Grade'],
                datasets: [{
                    data: [passed, failed, noGrade],
                    backgroundColor: ['#198754', '#dc3545', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });
        
        document.getElementById('passFailLegend').innerHTML = 
            '<div class="mb-2"><span class="d-inline-block rounded-circle me-2" style="width:12px;height:12px;background:#198754;"></span><strong>' + passed + '</strong> Passed</div>' +
            '<div class="mb-2"><span class="d-inline-block rounded-circle me-2" style="width:12px;height:12px;background:#dc3545;"></span><strong>' + failed + '</strong> Failed</div>' +
            '<div><span class="d-inline-block rounded-circle me-2" style="width:12px;height:12px;background:#e9ecef;"></span><strong>' + noGrade + '</strong> No Grade</div>';
        
        // Period averages chart
        if (periodChart) periodChart.destroy();
        const perCtx = document.getElementById('periodChart').getContext('2d');
        periodChart = new Chart(perCtx, {
            type: 'line',
            data: {
                labels: ['Prelim', 'Midterm', 'Pre-Final', 'Final'],
                datasets: [{
                    label: 'Class Average',
                    data: [
                        data.period_avg.prelim || 0,
                        data.period_avg.midterm || 0,
                        data.period_avg.prefinal || 0,
                        data.period_avg.final || 0
                    ],
                    borderColor: '#800000',
                    backgroundColor: 'rgba(128,0,0,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#800000',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { min: 0, max: 100, ticks: { stepSize: 20 } },
                    x: { grid: { display: false } }
                }
            }
        });
    });
}

/* ---- EXPORT ACTIONS ---- */
function exportGradePDF() {
    if (!currentSectionId) return;
    window.open('process/generate_grade_report.php?section_id=' + currentSectionId + '&subject_id=' + currentSubjectId, '_blank');
}

/* ---- HELPERS ---- */
function fmtGrade(val) {
    if (val === null || val === undefined || val === '' || val == 0) return '<span class="text-muted">-</span>';
    return parseFloat(val).toFixed(2);
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
</body>
</html>