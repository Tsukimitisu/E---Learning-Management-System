<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Management";
$branch_id = 1; // In production, fetch from user's assigned branch

// Fetch students enrolled in this branch
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

// Fetch available classes for assignment
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
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-people"></i> Student Management
            </h4>
            <div class="d-flex gap-2">
                <input type="text" id="searchStudent" class="form-control form-control-sm" placeholder="Search students...">
                <button class="btn btn-sm text-white" style="background-color: #800000;" onclick="refreshStudents()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Enrolled Classes</th>
                                <th>Subjects</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $student['enrolled_classes']; ?> classes</td>
                                <td>
                                    <small><?php echo htmlspecialchars($student['subjects'] ?? 'None enrolled'); ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary me-1" onclick="viewEnrollments(<?php echo $student['student_id']; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-success me-1" onclick="assignToClass(<?php echo $student['student_id']; ?>)">
                                        <i class="bi bi-plus-circle"></i> Assign
                                    </button>
                                    <button class="btn btn-sm btn-warning me-1" onclick="manageEnrollments(<?php echo $student['student_id']; ?>)">
                                        <i class="bi bi-pencil"></i> Manage
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

<!-- Assign to Class Modal -->
<div class="modal fade" id="assignClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Assign Student to Class</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignClassForm">
                <input type="hidden" name="student_id" id="assign_student_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Class</label>
                        <select class="form-select" name="class_id" id="class_select" required>
                            <option value="">-- Select Class --</option>
                            <?php
                            $available_classes->data_seek(0);
                            while ($class = $available_classes->fetch_assoc()):
                            ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title']); ?> |
                                    Section: <?php echo htmlspecialchars($class['section_name']); ?> |
                                    Teacher: <?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?> |
                                    Capacity: <?php echo $class['current_enrolled']; ?>/<?php echo $class['max_capacity']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Only classes with available slots are shown.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-plus-circle"></i> Assign Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Enrollments Modal -->
<div class="modal fade" id="viewEnrollmentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Student Enrollments</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="enrollmentsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('assignClassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Assigning...';

    try {
        const response = await fetch('process/assign_student.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Assign Student';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Assign Student';
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
                let html = '<div class="table-responsive"><table class="table table-sm">';
                html += '<thead><tr><th>Subject</th><th>Section</th><th>Teacher</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

                if (data.enrollments.length === 0) {
                    html += '<tr><td colspan="5" class="text-center text-muted">No enrollments found</td></tr>';
                } else {
                    data.enrollments.forEach(enrollment => {
                        html += `
                            <tr>
                                <td>${enrollment.subject_code} - ${enrollment.subject_title}</td>
                                <td>${enrollment.section_name}</td>
                                <td>${enrollment.teacher_name || 'Not Assigned'}</td>
                                <td><span class="badge bg-${enrollment.status === 'approved' ? 'success' : 'warning'}">${enrollment.status}</span></td>
                                <td>
                                    ${enrollment.status === 'pending' ?
                                        `<button class="btn btn-sm btn-success me-1" onclick="approveEnrollment(${enrollment.id})">Approve</button>` : ''}
                                    <button class="btn btn-sm btn-danger" onclick="removeEnrollment(${enrollment.id})">Remove</button>
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
        })
        .catch(error => {
            document.getElementById('enrollmentsContent').innerHTML = '<div class="alert alert-danger">An error occurred</div>';
        });
}

function manageEnrollments(studentId) {
    // For now, just show view modal. Could be expanded to bulk management
    viewEnrollments(studentId);
}

function approveEnrollment(enrollmentId) {
    if (confirm('Are you sure you want to approve this enrollment?')) {
        fetch('process/approve_enrollment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enrollment_id: enrollmentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                // Refresh the modal content
                const studentId = document.getElementById('assign_student_id').value;
                if (studentId) viewEnrollments(studentId);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function removeEnrollment(enrollmentId) {
    if (confirm('Are you sure you want to remove this enrollment?')) {
        fetch('process/remove_enrollment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enrollment_id: enrollmentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                // Refresh the modal content
                const studentId = document.getElementById('assign_student_id').value;
                if (studentId) viewEnrollments(studentId);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function refreshStudents() {
    location.reload();
}

// Search functionality
document.getElementById('searchStudent').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
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