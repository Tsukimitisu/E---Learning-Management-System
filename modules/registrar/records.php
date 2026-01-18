<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Academic Records";

$stats = [
    'total_with_records' => 0,
    'complete_grades' => 0,
    'probation' => 0,
    'eligible_grad' => 0
];

$result = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments WHERE status = 'approved'");
if ($row = $result->fetch_assoc()) {
    $stats['total_with_records'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(DISTINCT g.student_id) as count FROM grades g");
if ($row = $result->fetch_assoc()) {
    $stats['complete_grades'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM (
    SELECT g.student_id, AVG(g.final_grade) as gpa FROM grades g GROUP BY g.student_id HAVING AVG(g.final_grade) < 75
) t");
if ($row = $result->fetch_assoc()) {
    $stats['probation'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM (
    SELECT g.student_id, AVG(g.final_grade) as gpa FROM grades g GROUP BY g.student_id HAVING AVG(g.final_grade) >= 85
) t");
if ($row = $result->fetch_assoc()) {
    $stats['eligible_grad'] = $row['count'] ?? 0;
}

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
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-file-earmark-text"></i> Academic Records
            </h4>
        </div>

        <div id="alertContainer"></div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <p><i class="bi bi-people"></i> Students with Records</p>
                    <h3><?php echo number_format($stats['total_with_records']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <p><i class="bi bi-check-circle"></i> Complete Grades</p>
                    <h3><?php echo number_format($stats['complete_grades']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-exclamation-triangle"></i> Probation</p>
                    <h3><?php echo number_format($stats['probation']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-award"></i> Dean's List+</p>
                    <h3><?php echo number_format($stats['eligible_grad']); ?></h3>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search student number or name">
                    </div>
                    <div class="col-md-3">
                        <select id="programFilter" class="form-select">
                            <option value="">All Programs</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="yearFilter" class="form-select">
                            <option value="">All Academic Years</option>
                            <?php while ($ay = $academic_years->fetch_assoc()): ?>
                                <option value="<?php echo $ay['id']; ?>">
                                    <?php echo htmlspecialchars($ay['year_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="standingFilter" class="form-select">
                            <option value="">All Standing</option>
                            <option value="Dean's List">Dean's List</option>
                            <option value="Good Standing">Good Standing</option>
                            <option value="Probation">Probation</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="recordsTable">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Student No.</th>
                                <th>Full Name</th>
                                <th>Program</th>
                                <th>GPA</th>
                                <th>Standing</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $records_result->fetch_assoc()): ?>
                            <tr data-name="<?php echo htmlspecialchars(strtolower($row['full_name'])); ?>"
                                data-student-no="<?php echo htmlspecialchars(strtolower($row['student_no'])); ?>"
                                data-program="<?php echo (int)($row['course_id'] ?? 0); ?>"
                                data-standing="<?php echo htmlspecialchars($row['academic_standing']); ?>">
                                <td><?php echo htmlspecialchars($row['student_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars(($row['course_code'] ?? 'N/A') . ' - ' . ($row['course_title'] ?? '')); ?></td>
                                <td><?php echo $row['gpa'] ? number_format($row['gpa'], 2) : '-'; ?></td>
                                <td><span class="badge bg-<?php echo $row['academic_standing'] === 'Probation' ? 'danger' : ($row['academic_standing'] === "Dean's List" ? 'success' : 'info'); ?>">
                                    <?php echo htmlspecialchars($row['academic_standing']); ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info me-1" onclick="viewRecord(<?php echo $row['user_id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="generateTranscript(<?php echo $row['user_id']; ?>)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </button>
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

<!-- Student Record Modal -->
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Student Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="recordContent">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('programFilter').addEventListener('change', applyFilters);
document.getElementById('standingFilter').addEventListener('change', applyFilters);

document.getElementById('yearFilter').addEventListener('change', function() {
    applyFilters();
});

async function viewRecord(studentId) {
    const response = await fetch(`process/get_student_record.php?student_id=${studentId}`);
    const data = await response.json();

    if (data.status !== 'success') {
        return;
    }

    const record = data;
    const gradesRows = record.grades.map(g => `
        <tr>
            <td>${g.subject_code}</td>
            <td>${g.subject_title}</td>
            <td>${g.units}</td>
            <td>${g.midterm ?? '-'}</td>
            <td>${g.final ?? '-'}</td>
            <td>${g.final_grade ?? '-'}</td>
            <td>${g.remarks ?? '-'}</td>
        </tr>
    `).join('');

    const enrollmentRows = record.enrollment_history.map(e => `
        <tr>
            <td>${e.academic_year}</td>
            <td>${e.semester}</td>
            <td>${e.class_count}</td>
            <td>${e.status}</td>
        </tr>
    `).join('');

    document.getElementById('recordContent').innerHTML = `
        <h6>${record.student.full_name} (${record.student.student_no})</h6>
        <p><strong>Email:</strong> ${record.student.email}</p>
        <p><strong>Program:</strong> ${record.student.program}</p>
        <p><strong>GPA:</strong> ${record.student.gpa}</p>
        <p><strong>Standing:</strong> ${record.student.academic_standing}</p>
        <hr>
        <h6>Enrollment History</h6>
        <table class="table table-sm">
            <thead><tr><th>Academic Year</th><th>Semester</th><th>Classes</th><th>Status</th></tr></thead>
            <tbody>${enrollmentRows}</tbody>
        </table>
        <h6>Grades</h6>
        <table class="table table-sm">
            <thead><tr><th>Subject</th><th>Title</th><th>Units</th><th>Midterm</th><th>Final</th><th>Final Grade</th><th>Remarks</th></tr></thead>
            <tbody>${gradesRows}</tbody>
        </table>
        <h6>Attendance Summary</h6>
        <p>${record.attendance_summary.present} present / ${record.attendance_summary.total_days} days (${record.attendance_summary.percentage}%)</p>
        <h6>Payment Summary</h6>
        <p>Total Paid: ${record.payment_summary.total_paid} | Verified: ${record.payment_summary.verified_payments} | Status: ${record.payment_summary.clearance_status}</p>
    `;

    new bootstrap.Modal(document.getElementById('recordModal')).show();
}

function generateTranscript(studentId) {
    window.open(`process/generate_transcript.php?student_id=${studentId}`, '_blank');
}
</script>
</body>
</html>
