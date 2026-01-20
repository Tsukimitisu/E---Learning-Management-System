<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Academic Records";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$selected_term = $_GET['term'] ?? 'all';
$valid_terms = ['all', 'prelim', 'midterm', 'prefinal', 'final'];
if (!in_array($selected_term, $valid_terms)) { $selected_term = 'all'; }

$term_names = [
    'all' => 'All Terms (Final Average)',
    'prelim' => 'Prelim',
    'midterm' => 'Midterm',
    'prefinal' => 'Pre-Finals',
    'final' => 'Finals'
];

$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$stats = ['total_with_records' => 0, 'complete_grades' => 0, 'probation' => 0, 'eligible_grad' => 0];
$result = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments WHERE status = 'approved'");
if ($row = $result->fetch_assoc()) { $stats['total_with_records'] = $row['count'] ?? 0; }
$result = $conn->query("SELECT COUNT(DISTINCT g.student_id) as count FROM grades g");
if ($row = $result->fetch_assoc()) { $stats['complete_grades'] = $row['count'] ?? 0; }
$result = $conn->query("SELECT COUNT(*) as count FROM (SELECT g.student_id, AVG(g.final_grade) as gpa FROM grades g GROUP BY g.student_id HAVING AVG(g.final_grade) < 75) t");
if ($row = $result->fetch_assoc()) { $stats['probation'] = $row['count'] ?? 0; }
$result = $conn->query("SELECT COUNT(*) as count FROM (SELECT g.student_id, AVG(g.final_grade) as gpa FROM grades g GROUP BY g.student_id HAVING AVG(g.final_grade) >= 85) t");
if ($row = $result->fetch_assoc()) { $stats['eligible_grad'] = $row['count'] ?? 0; }

$records_query = "
    SELECT 
        s.user_id, s.student_no, s.course_id,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        c.course_code, c.title as course_title,
        COUNT(DISTINCT e.class_id) as total_classes,
        COUNT(DISTINCT g.id) as graded_classes,
        AVG(g.final_grade) as gpa,
        CASE 
            WHEN AVG(g.final_grade) >= 90 THEN 'Dean\'s List'
            WHEN AVG(g.final_grade) >= 75 THEN 'Good Standing'
            ELSE 'Probation'
        END as academic_standing
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN enrollments e ON s.user_id = e.student_id AND e.status = 'approved'
    LEFT JOIN grades g ON s.user_id = g.student_id
    GROUP BY s.user_id
    ORDER BY up.last_name, up.first_name
";
$records_result = $conn->query($records_query);
$courses = $conn->query("SELECT id, course_code, title FROM courses ORDER BY course_code");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC RECORDS UI --- */
    .record-stat-card {
        background: white; border-radius: 15px; padding: 20px; border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; transition: 0.3s;
    }
    .record-stat-card:hover { transform: translateY(-5px); }

    .main-card-modern { background: white; border-radius: 20px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .action-btn-circle { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; border: 1px solid #eee; background: white; }
    .action-btn-circle:hover { transform: scale(1.1); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

    .filter-row-card { background: white; border-radius: 15px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; }
    .modern-select { border-radius: 50px; border: 1px solid #ddd; font-size: 0.8rem; font-weight: 600; color: #555; }

    .gpa-value { font-weight: 800; font-size: 1rem; color: var(--blue); }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-file-earmark-text-fill me-2 text-maroon"></i>Academic Master Records</h4>
            <p class="text-muted small mb-0">Centralized student performance tracking and transcripts</p>
        </div>
        <div class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
            <i class="bi bi-calendar3 me-1 text-maroon"></i> Term: <?php echo $term_names[$selected_term]; ?>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Stats Summary Row -->
    <div class="row g-3 mb-4 animate__animated animate__fadeIn">
        <div class="col-md-3">
            <div class="record-stat-card border-start border-primary border-5">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded"><i class="bi bi-people fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['total_with_records']); ?></h4><small class="text-muted">Total Records</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="record-stat-card border-start border-success border-5">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded"><i class="bi bi-check-circle fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['complete_grades']); ?></h4><small class="text-muted">Finalized</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="record-stat-card border-start border-danger border-5">
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['probation']); ?></h4><small class="text-muted">On Probation</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="record-stat-card border-start border-warning border-5">
                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded"><i class="bi bi-award fs-4"></i></div>
                <div><h4 class="mb-0 fw-bold"><?php echo number_format($stats['eligible_grad']); ?></h4><small class="text-muted">Honor List</small></div>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- Multi-Filter Bar -->
    <div class="filter-row-card animate__animated animate__fadeIn">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control modern-select" placeholder="Search Student # or Name">
            </div>
            <div class="col-md-2">
                <select id="programFilter" class="form-select modern-select">
                    <option value="">All Programs</option>
                    <?php $courses->data_seek(0); while ($course = $courses->fetch_assoc()): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select id="yearFilter" class="form-select modern-select">
                    <option value="">All Academic Years</option>
                    <?php $academic_years->data_seek(0); while ($ay = $academic_years->fetch_assoc()): ?>
                        <option value="<?php echo $ay['id']; ?>"><?php echo htmlspecialchars($ay['year_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select id="termFilter" class="form-select modern-select" onchange="window.location.href='?term='+this.value">
                    <?php foreach ($term_names as $key => $name): ?>
                    <option value="<?php echo $key; ?>" <?php echo $selected_term == $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="standingFilter" class="form-select modern-select">
                    <option value="">All Standing</option>
                    <option value="Dean's List">Dean's List</option>
                    <option value="Good Standing">Good Standing</option>
                    <option value="Probation">Probation</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="main-card-modern animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0" id="recordsTable">
                <thead>
                    <tr>
                        <th class="ps-4">Student ID</th>
                        <th>Name & Identity</th>
                        <th>Assigned Program</th>
                        <th class="text-center">GWA</th>
                        <th class="text-center">Standing</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $records_result->fetch_assoc()): 
                        $standing = $row['academic_standing'];
                        $s_clr = $standing === 'Probation' ? 'danger' : ($standing === "Dean's List" ? 'success' : 'info');
                    ?>
                    <tr data-name="<?php echo htmlspecialchars(strtolower($row['full_name'])); ?>"
                        data-student-no="<?php echo htmlspecialchars(strtolower($row['student_no'])); ?>"
                        data-program="<?php echo (int)($row['course_id'] ?? 0); ?>"
                        data-standing="<?php echo htmlspecialchars($row['academic_standing']); ?>">
                        <td class="ps-4 fw-bold text-maroon"><?php echo htmlspecialchars($row['student_no']); ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></div>
                            <small class="text-muted small">Academic Dossier Ready</small>
                        </td>
                        <td><small class="text-muted fw-bold"><?php echo htmlspecialchars(($row['course_code'] ?? 'N/A') . ' - ' . ($row['course_title'] ?? '')); ?></small></td>
                        <td class="text-center gpa-value"><?php echo $row['gpa'] ? number_format($row['gpa'], 2) : '---'; ?></td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-<?php echo $s_clr; ?> px-3 py-2">
                                <?php echo htmlspecialchars($standing); ?>
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center gap-2">
                                <button class="action-btn-circle text-info" onclick="viewRecord(<?php echo $row['user_id']; ?>)" title="View Record"><i class="bi bi-eye-fill"></i></button>
                                <button class="action-btn-circle text-primary" onclick="generateTranscript(<?php echo $row['user_id']; ?>)" title="PDF Transcript"><i class="bi bi-file-earmark-pdf-fill"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Student Record Modal (Modernized) -->
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Comprehensive Student Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="recordContent">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const program = document.getElementById('programFilter').value;
    const standing = document.getElementById('standingFilter').value;

    document.querySelectorAll('#recordsTable tbody tr').forEach(row => {
        const name = row.dataset.name || '';
        const studentNo = row.dataset.studentNo || '';
        const rowProgram = row.dataset.program || '';
        const rowStanding = row.dataset.standing || '';

        const matchesSearch = name.includes(search) || studentNo.includes(search);
        const matchesProgram = !program || rowProgram === program;
        const matchesStanding = !standing || rowStanding === standing;

        row.style.display = (matchesSearch && matchesProgram && matchesStanding) ? '' : 'none';
    });
}

['searchInput', 'programFilter', 'standingFilter', 'yearFilter'].forEach(id => {
    document.getElementById(id).addEventListener('input', applyFilters);
    document.getElementById(id).addEventListener('change', applyFilters);
});

async function viewRecord(studentId) {
    document.getElementById('recordContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Fetching academic history...</p></div>';
    new bootstrap.Modal(document.getElementById('recordModal')).show();

    try {
        const response = await fetch(`process/get_student_record.php?student_id=${studentId}`);
        const data = await response.json();

        if (data.status !== 'success') { showAlert('Failed to load record', 'danger'); return; }

        const record = data;
        const gradesRows = record.grades.map(g => `<tr><td>${g.subject_code}</td><td>${g.subject_title}</td><td class="text-center">${g.units}</td><td class="text-center">${g.midterm ?? '-'}</td><td class="text-center">${g.final ?? '-'}</td><td class="text-center fw-bold">${g.final_grade ?? '-'}</td><td class="text-center"><span class="badge bg-light text-dark border small">${g.remarks ?? '-'}</span></td></tr>`).join('');
        const enrollmentRows = record.enrollment_history.map(e => `<tr><td>${e.academic_year}</td><td>${e.semester}</td><td>${e.class_count}</td><td><span class="badge bg-success">${e.status}</span></td></tr>`).join('');

        document.getElementById('recordContent').innerHTML = `
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <h4 class="fw-bold text-blue mb-1">${record.student.full_name}</h4>
                    <span class="badge bg-maroon rounded-pill px-3 mb-3">${record.student.student_no}</span>
                    <p class="mb-1 small"><i class="bi bi-envelope me-2"></i>${record.student.email}</p>
                    <p class="mb-0 small"><i class="bi bi-mortarboard me-2"></i>${record.student.program}</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-inline-block bg-white border rounded-3 p-3 text-center">
                        <div class="h2 fw-bold text-blue mb-0">${record.student.gpa}</div>
                        <small class="text-muted fw-bold">CURRENT GWA</small>
                    </div>
                </div>
            </div>
            <hr>
            <h6 class="fw-bold text-maroon text-uppercase small mb-3">Enrollment History</h6>
            <div class="table-responsive mb-4"><table class="table table-sm table-bordered bg-white"><thead><tr class="table-light"><th>Academic Year</th><th>Semester</th><th>Classes</th><th>Status</th></tr></thead><tbody>${enrollmentRows}</tbody></table></div>
            
            <h6 class="fw-bold text-maroon text-uppercase small mb-3">Academic Performance</h6>
            <div class="table-responsive mb-4"><table class="table table-sm table-bordered bg-white"><thead><tr class="table-light"><th>Subject</th><th>Title</th><th class="text-center">Units</th><th class="text-center">Midterm</th><th class="text-center">Final</th><th class="text-center">Average</th><th class="text-center">Remarks</th></tr></thead><tbody>${gradesRows}</tbody></table></div>
            
            <div class="row g-3">
                <div class="col-md-6"><div class="card bg-white border-0 shadow-xs"><div class="card-body"><h6>Attendance Summary</h6><p class="mb-0 small">${record.attendance_summary.present} present / ${record.attendance_summary.total_days} days (${record.attendance_summary.percentage}%)</p></div></div></div>
                <div class="col-md-6"><div class="card bg-white border-0 shadow-xs"><div class="card-body"><h6>Clearance Status</h6><p class="mb-0 small">Verified: ${record.payment_summary.verified_payments} | ${record.payment_summary.clearance_status}</p></div></div></div>
            </div>
        `;
    } catch (e) { document.getElementById('recordContent').innerHTML = '<div class="alert alert-danger">Error retrieving Dossier.</div>'; }
}

function generateTranscript(studentId) {
    window.open(`process/generate_transcript.php?student_id=${studentId}`, '_blank');
}
</script>
</body>
</html>