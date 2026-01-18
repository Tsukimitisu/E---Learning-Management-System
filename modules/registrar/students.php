<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Management";

// Statistics
$stats = [
    'total_students' => 0,
    'pending_accounts' => 0,
    'active_students' => 0,
    'new_today' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($row = $result->fetch_assoc()) {
    $stats['total_students'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users u INNER JOIN students s ON u.id = s.user_id WHERE u.status = 'inactive'");
if ($row = $result->fetch_assoc()) {
    $stats['pending_accounts'] = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users u INNER JOIN students s ON u.id = s.user_id WHERE u.status = 'active'");
if ($row = $result->fetch_assoc()) {
    $stats['active_students'] = $row['count'] ?? 0;
}

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM users u INNER JOIN students s ON u.id = s.user_id WHERE DATE(u.created_at) = '$today'");
if ($row = $result->fetch_assoc()) {
    $stats['new_today'] = $row['count'] ?? 0;
}

// Student List
$students_query = "
    SELECT 
        s.user_id, s.student_no, s.course_id,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        u.email, u.status,
        c.course_code, c.title as course_title,
        COALESCE(SUM(p.amount), 0) as total_paid,
        COALESCE(MAX(p.status), 'pending') as payment_status
    FROM students s
    INNER JOIN users u ON s.user_id = u.id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN payments p ON s.user_id = p.student_id
    GROUP BY s.user_id
    ORDER BY s.student_no DESC
";
$students_result = $conn->query($students_query);

$student_no_preview = generate_student_number($conn);

// Programs & Strands for filters and modals
$courses_result = $conn->query("SELECT id, course_code, title FROM courses ORDER BY course_code");
$strands_result = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands WHERE is_active = 1 ORDER BY strand_name");

$program_year_levels_result = $conn->query("SELECT id, program_id, year_name FROM program_year_levels WHERE is_active = 1 ORDER BY program_id, year_level");
$program_year_levels = [];
while ($row = $program_year_levels_result->fetch_assoc()) {
    $program_year_levels[$row['program_id']][] = $row;
}

$shs_grade_levels_result = $conn->query("SELECT id, strand_id, grade_name FROM shs_grade_levels WHERE is_active = 1 ORDER BY strand_id, grade_level");
$shs_grade_levels = [];
while ($row = $shs_grade_levels_result->fetch_assoc()) {
    $shs_grade_levels[$row['strand_id']][] = $row;
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-people-fill"></i> Student Management
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-plus-circle"></i> Add Student
            </button>
        </div>

        <div id="alertContainer"></div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <p><i class="bi bi-people"></i> Total Students</p>
                    <h3><?php echo number_format($stats['total_students']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <p><i class="bi bi-hourglass-split"></i> Pending Accounts</p>
                    <h3><?php echo number_format($stats['pending_accounts']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <p><i class="bi bi-check-circle"></i> Active Students</p>
                    <h3><?php echo number_format($stats['active_students']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <p><i class="bi bi-calendar-plus"></i> Added Today</p>
                    <h3><?php echo number_format($stats['new_today']); ?></h3>
                </div>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name or student number">
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="programFilter" class="form-select">
                            <option value="">All Programs</option>
                            <?php
                            $courses_result->data_seek(0);
                            while ($course = $courses_result->fetch_assoc()):
                            ?>
                                <option value="course-<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                </option>
                            <?php endwhile; ?>
                            <?php
                            $strands_result->data_seek(0);
                            while ($strand = $strands_result->fetch_assoc()):
                            ?>
                                <option value="strand-<?php echo $strand['id']; ?>">
                                    <?php echo htmlspecialchars($strand['strand_code'] . ' - ' . $strand['strand_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student List Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Student No.</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Program/Course</th>
                                <th>Status</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()):
                                $program_label = $student['course_code'] ? ($student['course_code'] . ' - ' . $student['course_title']) : 'SHS';
                                $program_key = $student['course_id'] ? 'course-' . $student['course_id'] : 'shs';
                                $status_class = $student['status'] === 'active' ? 'success' : 'secondary';
                                $payment_status = $student['payment_status'] ?? 'pending';
                                $payment_class = $payment_status === 'verified' ? 'success' : ($payment_status === 'rejected' ? 'danger' : 'warning');
                            ?>
                            <tr data-name="<?php echo htmlspecialchars(strtolower($student['full_name'])); ?>"
                                data-student-no="<?php echo htmlspecialchars(strtolower($student['student_no'])); ?>"
                                data-program="<?php echo htmlspecialchars($program_key); ?>"
                                data-status="<?php echo htmlspecialchars($student['status']); ?>">
                                <td><?php echo htmlspecialchars($student['student_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($program_label); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $payment_class; ?>">
                                        <?php echo ucfirst($payment_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning me-1" onclick="openEditStudent(this)"
                                            data-student-id="<?php echo $student['user_id']; ?>"
                                            data-student-no="<?php echo htmlspecialchars($student['student_no']); ?>"
                                            data-full-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($student['email']); ?>"
                                            data-status="<?php echo htmlspecialchars($student['status']); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info me-1" onclick="viewStudentDetails(<?php echo $student['user_id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-<?php echo $student['status'] === 'active' ? 'secondary' : 'success'; ?>"
                                            onclick="toggleStudentStatus(<?php echo $student['user_id']; ?>, '<?php echo $student['status']; ?>')">
                                        <i class="bi bi-<?php echo $student['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
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

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body">
                    <h6 class="text-primary">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_no">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>

                    <hr>
                    <h6 class="text-primary">Academic Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Program Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="program_type" id="program_type" required>
                                <option value="college" selected>College</option>
                                <option value="shs">SHS</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="collegeProgramCol">
                            <label class="form-label">Program/Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" id="course_id">
                                <option value="">-- Select Course --</option>
                                <?php
                                $courses_result->data_seek(0);
                                while ($course = $courses_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="shsStrandCol" style="display:none;">
                            <label class="form-label">SHS Strand <span class="text-danger">*</span></label>
                            <select class="form-select" name="shs_strand_id" id="shs_strand_id">
                                <option value="">-- Select Strand --</option>
                                <?php
                                $strands_result->data_seek(0);
                                while ($strand = $strands_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $strand['id']; ?>">
                                        <?php echo htmlspecialchars($strand['strand_code'] . ' - ' . $strand['strand_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year_level_id" id="year_level_id">
                                <option value="">-- Select Year Level --</option>
                                <?php foreach ($program_year_levels as $pid => $levels): ?>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>" data-program-id="<?php echo $pid; ?>">
                                            <?php echo htmlspecialchars($level['year_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php foreach ($shs_grade_levels as $sid => $levels): ?>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>" data-strand-id="<?php echo $sid; ?>">
                                            <?php echo htmlspecialchars($level['grade_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary">Account Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Number (Auto-generated)</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student_no_preview); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Temporary Password <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="password" value="student123" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Create Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm">
                <input type="hidden" name="student_id" id="edit_student_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Number</label>
                            <input type="text" class="form-control" id="edit_student_no" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> Editing full academic details will be available in the next update.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addStudentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch('process/create_student.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
});

function openEditStudent(button) {
    document.getElementById('edit_student_id').value = button.dataset.studentId;
    document.getElementById('edit_full_name').value = button.dataset.fullName;
    document.getElementById('edit_email').value = button.dataset.email;
    document.getElementById('edit_student_no').value = button.dataset.studentNo;
    document.getElementById('edit_status').value = button.dataset.status;

    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    showAlert('Edit functionality will be enabled in the next update.', 'warning');
});

function viewStudentDetails(id) {
    window.location.href = `students.php?view=${id}`;
}

function toggleStudentStatus(id, status) {
    showAlert('Status toggle will be enabled in the next update.', 'warning');
}

function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const program = document.getElementById('programFilter').value;
    const status = document.getElementById('statusFilter').value;

    document.querySelectorAll('#studentsTable tbody tr').forEach(row => {
        const name = row.dataset.name || '';
        const studentNo = row.dataset.studentNo || '';
        const rowProgram = row.dataset.program || '';
        const rowStatus = row.dataset.status || '';

        const matchesSearch = name.includes(search) || studentNo.includes(search);
        const matchesProgram = !program || rowProgram === program;
        const matchesStatus = !status || rowStatus === status;

        row.style.display = (matchesSearch && matchesProgram && matchesStatus) ? '' : 'none';
    });
}

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('programFilter').addEventListener('change', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);

document.getElementById('program_type').addEventListener('change', function() {
    const isCollege = this.value === 'college';
    document.getElementById('collegeProgramCol').style.display = isCollege ? 'block' : 'none';
    document.getElementById('shsStrandCol').style.display = isCollege ? 'none' : 'block';
});

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
