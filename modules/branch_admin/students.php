<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Management";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

// Fetch students enrolled in this branch (Logic Untouched)
$students_query = "
    SELECT
        u.id as student_id,
        u.email,
        u.status,
        up.first_name,
        up.last_name,
        COUNT(DISTINCT e.class_id) as enrolled_classes,
        GROUP_CONCAT(DISTINCT CONCAT(s.subject_code, ' (', cl.section_name, ')') SEPARATOR ', ') as subjects
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    INNER JOIN students st ON u.id = st.user_id
    INNER JOIN courses c ON st.course_id = c.id
    LEFT JOIN enrollments e ON u.id = e.student_id AND e.status = 'approved'
    LEFT JOIN classes cl ON e.class_id = cl.id AND cl.branch_id = $branch_id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE ur.role_id = " . ROLE_STUDENT . " AND c.branch_id = $branch_id
    GROUP BY u.id, u.email, u.status, up.first_name, up.last_name
    ORDER BY up.last_name, up.first_name
";

$students = $conn->query($students_query);

// Fetch available classes for assignment (Logic Untouched)
$available_classes = $conn->query("
    SELECT
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        cl.current_enrolled,
        cl.max_capacity,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.branch_id = $branch_id AND cl.current_enrolled < cl.max_capacity
    ORDER BY s.subject_code, cl.section_name
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SHARED UI DESIGN SYSTEM --- */
    .page-header {
        background: white; padding: 20px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
    }

    .content-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
    
    .card-header-modern {
        background: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee;
        font-weight: 700; color: var(--blue); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;
    }

    /* Table Styling */
    .table-modern thead th { 
        background: #f8f9fa; font-size: 0.7rem; text-transform: uppercase; 
        color: #888; padding: 15px 20px; border-bottom: 1px solid #eee;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.85rem; }
    
    .status-pill {
        padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    }
    .status-active { background: #e6f4ea; color: #1e7e34; }
    .status-inactive { background: #f8f9fa; color: #6c757d; border: 1px solid #eee; }

    .search-input-group {
        background: #f1f3f5; border-radius: 10px; padding: 5px 15px; display: flex; align-items: center; gap: 10px;
    }
    .search-input-group input {
        background: transparent; border: none; outline: none; font-size: 0.85rem; width: 250px;
    }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-people-fill me-2 text-maroon"></i>Student Management
            </h4>
            <p class="text-muted small mb-0">Managing student directory and class assignments for this branch.</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <div class="search-input-group d-none d-md-flex">
                <i class="bi bi-search text-muted"></i>
                <input type="text" id="searchStudent" placeholder="Find student by name...">
            </div>
            <button class="btn btn-outline-secondary btn-sm px-4 rounded-pill fw-bold" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> REFRESH
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. STUDENT DIRECTORY TABLE -->
    <div class="content-card">
        <div class="card-header-modern bg-white">
            <i class="bi bi-mortarboard me-2"></i> Branch Student List
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Institutional Email</th>
                        <th>Account Status</th>
                        <th>Class Count</th>
                        <th>Assigned Subject Sections</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                            <small class="text-muted" style="font-size: 0.65rem;">STUDENT ID: #<?php echo $student['student_id']; ?></small>
                        </td>
                        <td><small><?php echo htmlspecialchars($student['email']); ?></small></td>
                        <td>
                            <span class="status-pill <?php echo $student['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($student['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-dark text-blue border px-3"><?php echo $student['enrolled_classes']; ?> Classes</span>
                        </td>
                        <td>
                            <small class="text-muted line-clamp-1" style="max-width: 250px;">
                                <?php echo htmlspecialchars($student['subjects'] ?? 'No active enrollments'); ?>
                            </small>
                        </td>
                        <td class="text-end">
                            <div class="btn-group shadow-sm">
                                <button class="btn btn-sm btn-white border" onclick="viewEnrollments(<?php echo $student['student_id']; ?>)" title="View Details">
                                    <i class="bi bi-eye text-primary"></i>
                                </button>
                                <button class="btn btn-sm btn-white border" onclick="assignToClass(<?php echo $student['student_id']; ?>)" title="Assign to Class">
                                    <i class="bi bi-plus-circle-fill text-success"></i>
                                </button>
                                <button class="btn btn-sm btn-white border" onclick="manageEnrollments(<?php echo $student['student_id']; ?>)" title="Management">
                                    <i class="bi bi-sliders text-info"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Student Modal -->
<div class="modal fade" id="assignClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-maroon text-dark py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-plus-circle me-2"></i>Assign Student to Subject Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignClassForm">
                <input type="hidden" name="student_id" id="assign_student_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Available Branch Classes</label>
                        <select class="form-select shadow-sm" name="class_id" id="class_select" required>
                            <option value="">-- Choose a class section --</option>
                            <?php
                            $available_classes->data_seek(0);
                            while ($class = $available_classes->fetch_assoc()):
                            ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title']); ?> | Section: <?php echo htmlspecialchars($class['section_name']); ?> | (<?php echo $class['current_enrolled']; ?>/<?php echo $class['max_capacity']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="alert alert-info border-0 shadow-sm py-2 mb-0" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle-fill me-1"></i> Only class sections with remaining capacity are displayed above.
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon btn-sm px-4 fw-bold">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Enrollments Modal -->
<div class="modal fade" id="viewEnrollmentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-blue text-white py-3" style="background: var(--blue);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-eye me-2"></i>Current Student Enrollments</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="enrollmentsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted small fw-bold">Fetching academic records...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Logic preserved exactly as requested
document.getElementById('assignClassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Assigning...';

    try {
        const response = await fetch('process/assign_student.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Enroll Student';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
    }
});

function assignToClass(studentId) {
    document.getElementById('assign_student_id').value = studentId;
    new bootstrap.Modal(document.getElementById('assignClassModal')).show();
}

function viewEnrollments(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('viewEnrollmentsModal'));
    modal.show();

    fetch(`process/get_student_enrollments.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '<div class="table-responsive"><table class="table table-sm table-modern align-middle">';
                html += '<thead><tr><th>Subject Code/Title</th><th>Section</th><th>Instructor</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>';

                if (data.enrollments.length === 0) {
                    html += '<tr><td colspan="5" class="text-center text-muted py-5">No active enrollments recorded.</td></tr>';
                } else {
                    data.enrollments.forEach(enrollment => {
                        html += `
                            <tr>
                                <td><div class="fw-bold">${enrollment.subject_code}</div><small class="text-muted">${enrollment.subject_title}</small></td>
                                <td><span class="badge bg-light text-dark border">${enrollment.section_name}</span></td>
                                <td><small>${enrollment.teacher_name || 'TBA'}</small></td>
                                <td><span class="badge bg-${enrollment.status === 'approved' ? 'success' : 'warning'} px-2">${enrollment.status}</span></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        ${enrollment.status === 'pending' ? `<button class="btn btn-xs btn-outline-success" onclick="approveEnrollment(${enrollment.id})"><i class="bi bi-check"></i></button>` : ''}
                                        <button class="btn btn-xs btn-outline-danger" onclick="removeEnrollment(${enrollment.id})"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                }
                html += '</tbody></table></div>';
                document.getElementById('enrollmentsContent').innerHTML = html;
            } else {
                document.getElementById('enrollmentsContent').innerHTML = '<div class="alert alert-danger">Failed to load enrollments</div>';
            }
        });
}

function manageEnrollments(studentId) { viewEnrollments(studentId); }

function approveEnrollment(enrollmentId) {
    if (confirm('Approve this enrollment record?')) {
        fetch('process/approve_enrollment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enrollment_id: enrollmentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            }
        });
    }
}

function removeEnrollment(enrollmentId) {
    if (confirm('Permanently remove this student from the section?')) {
        fetch('process/remove_enrollment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enrollment_id: enrollmentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            }
        });
    }
}

document.getElementById('searchStudent').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>