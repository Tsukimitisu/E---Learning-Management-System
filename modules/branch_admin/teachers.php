<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Teacher Management";
$branch_id = 1; // In production, fetch from user's assigned branch

// Check if viewing specific teacher's sections
$view_teacher_sections = isset($_GET['view_sections']) ? (int)$_GET['view_sections'] : null;

// Fetch all teachers (users with teacher role)
$teachers_query = "
    SELECT
        u.id,
        u.email,
        u.status,
        u.created_at,
        up.first_name,
        up.last_name,
        up.address,
        COUNT(DISTINCT cl.id) as assigned_classes,
        GROUP_CONCAT(DISTINCT s.subject_code SEPARATOR ', ') as subjects
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN classes cl ON cl.teacher_id = u.id AND cl.branch_id = $branch_id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE ur.role_id = " . ROLE_TEACHER . "
    GROUP BY u.id, u.email, u.status, u.created_at, up.first_name, up.last_name, up.address
    ORDER BY up.first_name, up.last_name
";

$teachers = $conn->query($teachers_query);

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-person-badge"></i>
                <?php echo $view_teacher_sections ? 'Teacher Sections' : 'Teacher Management'; ?>
            </h4>
            <div class="d-flex gap-2">
                <?php if (!$view_teacher_sections): ?>
                <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="bi bi-plus-circle"></i> Add Teacher
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" onclick="window.location.href='teachers.php'">
                    <i class="bi bi-arrow-left"></i> Back to Teachers
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div id="alertContainer"></div>

        <?php if ($view_teacher_sections): ?>
        <!-- Teacher Sections View -->
        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background-color: #17a2b8; color: white;">
                <h5 class="mb-0">
                    <?php
                    $teacher_info = $conn->query("SELECT CONCAT(up.first_name, ' ', up.last_name) as name FROM users u INNER JOIN user_profiles up ON u.id = up.user_id WHERE u.id = $view_teacher_sections")->fetch_assoc();
                    echo htmlspecialchars($teacher_info['name'] ?? 'Unknown Teacher');
                    ?>'s Sections
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Section</th>
                                <th>Subject</th>
                                <th>Program</th>
                                <th>Academic Year</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Enrolled Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $teacher_sections = $conn->query("
                                SELECT
                                    cl.id,
                                    cl.section_name,
                                    s.subject_code,
                                    s.subject_title,
                                    p.program_name,
                                    ay.year_name,
                                    cl.schedule,
                                    cl.room,
                                    cl.current_enrolled,
                                    cl.max_capacity
                                FROM classes cl
                                LEFT JOIN subjects s ON cl.subject_id = s.id
                                LEFT JOIN programs p ON s.program_id = p.id
                                LEFT JOIN academic_years ay ON cl.academic_year_id = ay.id
                                WHERE cl.teacher_id = $view_teacher_sections AND cl.branch_id = $branch_id
                                ORDER BY ay.year_name DESC, s.subject_code, cl.section_name
                            ");

                            while ($section = $teacher_sections->fetch_assoc()):
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($section['section_name']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($section['subject_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($section['subject_title']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($section['program_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($section['year_name'] ?? 'N/A'); ?></td>
                                <td><small><?php echo htmlspecialchars($section['schedule'] ?? '-'); ?></small></td>
                                <td><?php echo htmlspecialchars($section['room'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $section['current_enrolled']; ?>/<?php echo $section['max_capacity']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info me-1" onclick="window.location.href='sectioning.php'">
                                        <i class="bi bi-eye"></i> View All
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="window.location.href='students.php?section_id=<?php echo $section['id']; ?>'">
                                        <i class="bi bi-people"></i> Students
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Assigned Classes</th>
                                <th>Subjects</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $teacher['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($teacher['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $teacher['assigned_classes'] ?? 0; ?> classes</td>
                                <td>
                                    <small><?php echo htmlspecialchars($teacher['subjects'] ?? 'None assigned'); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning me-1" onclick="editTeacher(<?php echo $teacher['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info me-1" onclick="viewClasses(<?php echo $teacher['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-<?php echo $teacher['status'] == 'active' ? 'secondary' : 'success'; ?>"
                                            onclick="toggleStatus(<?php echo $teacher['id']; ?>, '<?php echo $teacher['status']; ?>')">
                                        <i class="bi bi-<?php echo $teacher['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
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

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Teacher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTeacherForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body">
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
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                        <small class="text-muted">This will be used as login username</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Temporary Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" value="teacher123" required>
                        <small class="text-muted">Default: teacher123 (user should change on first login)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-person-plus"></i> Create Teacher Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Teacher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTeacherForm">
                <input type="hidden" name="teacher_id" id="edit_teacher_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #003366;">
                        <i class="bi bi-save"></i> Update Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addTeacherForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';

    try {
        const response = await fetch('process/add_teacher.php', {
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
            submitBtn.innerHTML = '<i class="bi bi-person-plus"></i> Create Teacher Account';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-person-plus"></i> Create Teacher Account';
    }
});

document.getElementById('editTeacherForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';

    try {
        const response = await fetch('process/update_teacher.php', {
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
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Update Teacher';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Update Teacher';
    }
});

function editTeacher(id) {
    // Fetch teacher data and populate edit modal
    fetch(`process/get_teacher.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('edit_teacher_id').value = data.teacher.id;
                document.getElementById('edit_first_name').value = data.teacher.first_name;
                document.getElementById('edit_last_name').value = data.teacher.last_name;
                document.getElementById('edit_email').value = data.teacher.email;
                document.getElementById('edit_address').value = data.teacher.address || '';
                document.getElementById('edit_status').value = data.teacher.status;

                new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
            } else {
                showAlert('Failed to load teacher data', 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
}

function viewClasses(id) {
    // Redirect to scheduling page with teacher filter or show modal
    window.location.href = `scheduling.php?teacher_id=${id}`;
}

function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'deactivate';

    if (confirm(`Are you sure you want to ${action} this teacher?`)) {
        fetch('process/toggle_teacher_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ teacher_id: id, status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
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