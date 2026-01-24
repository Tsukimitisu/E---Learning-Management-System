<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Teacher Management";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

// Check if viewing specific teacher's sections
$view_teacher_sections = isset($_GET['view_sections']) ? (int)$_GET['view_sections'] : null;

// Fetch teachers assigned to this branch
$current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$teachers_query = "
    SELECT DISTINCT
        u.id,
        u.email,
        u.status,
        u.created_at,
        up.first_name,
        up.last_name,
        up.address,
        (SELECT COUNT(DISTINCT tsa2.curriculum_subject_id) 
         FROM teacher_subject_assignments tsa2 
         WHERE tsa2.teacher_id = u.id AND tsa2.branch_id = $branch_id 
         AND tsa2.academic_year_id = $current_ay_id AND tsa2.is_active = 1) as assigned_subjects,
        (SELECT GROUP_CONCAT(DISTINCT cs2.subject_code SEPARATOR ', ')
         FROM teacher_subject_assignments tsa3
         INNER JOIN curriculum_subjects cs2 ON tsa3.curriculum_subject_id = cs2.id
         WHERE tsa3.teacher_id = u.id AND tsa3.branch_id = $branch_id 
         AND tsa3.academic_year_id = $current_ay_id AND tsa3.is_active = 1) as subjects
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . " AND up.branch_id = $branch_id
    GROUP BY u.id, u.email, u.status, u.created_at, up.first_name, up.last_name, up.address
    ORDER BY up.first_name, up.last_name
";

$teachers = $conn->query($teachers_query);

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

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-person-badge-fill me-2 text-maroon"></i>
                <?php echo $view_teacher_sections ? 'Teacher Assigned Sections' : 'Teacher Management'; ?>
            </h4>
            <p class="text-muted small mb-0">Managing academic staff and class workload for the current branch.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$view_teacher_sections): ?>
                <button class="btn btn-maroon btn-sm px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Teacher
                </button>
            <?php else: ?>
                <a href="teachers.php" class="btn btn-outline-secondary btn-sm px-4 rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div id="alertContainer"></div>

    <?php if ($view_teacher_sections): ?>
    <!-- Teacher Sections Detail View -->
    <div class="content-card mb-4 animate__animated animate__fadeInUp">
        <div class="card-header-modern bg-blue text-white" style="background: var(--blue) !important;">
            <i class="bi bi-journal-text me-2"></i> Assigned Sections: 
            <span class="text-warning">
                <?php
                $teacher_info = $conn->query("SELECT CONCAT(up.first_name, ' ', up.last_name) as name FROM users u INNER JOIN user_profiles up ON u.id = up.user_id WHERE u.id = $view_teacher_sections")->fetch_assoc();
                echo htmlspecialchars($teacher_info['name'] ?? 'Unknown Teacher');
                ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Subject Info</th>
                        <th>Program / Level</th>
                        <th>Academic Year</th>
                        <th>Schedule & Room</th>
                        <th class="text-center">Enrolled</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $teacher_sections = $conn->query("
                        SELECT cl.id, cl.section_name, cs.subject_code, cs.subject_title, p.program_name, ss.strand_name,
                               pyl.year_name as program_year_name, sgl.grade_name as shs_grade_name, ay.year_name,
                               cl.schedule, cl.room, cl.current_enrolled, cl.max_capacity
                        FROM classes cl
                        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
                        LEFT JOIN programs p ON cs.program_id = p.id
                        LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
                        LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
                        LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
                        LEFT JOIN academic_years ay ON cl.academic_year_id = ay.id
                        WHERE cl.teacher_id = $view_teacher_sections AND cl.branch_id = $branch_id
                        ORDER BY ay.year_name DESC, cs.subject_code, cl.section_name
                    ");

                    while ($section = $teacher_sections->fetch_assoc()):
                        $curriculum_label = $section['program_name'] ?? $section['strand_name'] ?? 'General';
                        $year_label = $section['program_year_name'] ?? $section['shs_grade_name'] ?? 'N/A';
                    ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($section['section_name']); ?></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($section['subject_code']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($section['subject_title']); ?></small>
                        </td>
                        <td>
                            <div class="small fw-bold"><?php echo htmlspecialchars($curriculum_label); ?></div>
                            <small class="text-muted">Year: <?php echo htmlspecialchars($year_label); ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($section['year_name'] ?? 'N/A'); ?></span></td>
                        <td>
                            <small class="d-block fw-bold"><?php echo htmlspecialchars($section['schedule'] ?? 'TBA'); ?></small>
                            <small class="text-muted">Room: <?php echo htmlspecialchars($section['room'] ?? '-'); ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-blue bg-opacity-10 text-blue px-3"><?php echo $section['current_enrolled']; ?> / <?php echo $section['max_capacity']; ?></span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <a href="sectioning.php" class="btn btn-sm btn-white border"><i class="bi bi-gear"></i></a>
                                <a href="students.php?section_id=<?php echo $section['id']; ?>" class="btn btn-sm btn-white border"><i class="bi bi-people"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Teachers Table -->
    <div class="content-card">
        <div class="card-header-modern bg-white">
            <i class="bi bi-people me-2"></i> Faculty Directory
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Instructor Name</th>
                        <th>Email / Contact</th>
                        <th>Status</th>
                        <th>Workload</th>
                        <th>Subject Codes</th>
                        <th>Date Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                            <small class="text-muted" style="font-size: 0.65rem;">TEACHER ID: #<?php echo $teacher['id']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                        <td>
                            <span class="status-pill <?php echo $teacher['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($teacher['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-3"><?php echo $teacher['assigned_subjects'] ?? 0; ?> Subjects</span>
                        </td>
                        <td>
                            <small class="text-muted d-block line-clamp-1" style="max-width: 200px;">
                                <?php echo htmlspecialchars($teacher['subjects'] ?? 'None assigned'); ?>
                            </small>
                        </td>
                        <td><small><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></small></td>
                        <td class="text-end">
                            <div class="btn-group shadow-sm">
                                <button class="btn btn-sm btn-white border" onclick="editTeacher(<?php echo $teacher['id']; ?>)" title="Edit">
                                    <i class="bi bi-pencil-square text-primary"></i>
                                </button>
                                <button class="btn btn-sm btn-white border" onclick="viewClasses(<?php echo $teacher['id']; ?>)" title="Workload">
                                    <i class="bi bi-calendar-week text-info"></i>
                                </button>
                                <button class="btn btn-sm btn-white border" onclick="toggleStatus(<?php echo $teacher['id']; ?>, '<?php echo $teacher['status']; ?>')" title="Toggle Status">
                                    <i class="bi bi-<?php echo $teacher['status'] == 'active' ? 'pause-fill text-warning' : 'play-fill text-success'; ?>"></i>
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

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-maroon text-dark py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-person-plus me-2"></i>Register New Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTeacherForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Email Address *</label>
                        <input type="email" class="form-control" name="email" required>
                        <small class="text-muted">This email will serve as the login username.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Login Password *</label>
                        <input type="password" class="form-control" name="password" value="teacher123" required>
                        <small class="text-muted">Default system password: <strong>teacher123</strong></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Residential Address</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="Full address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon btn-sm px-4 fw-bold">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-blue text-white py-3" style="background: var(--blue);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil me-2"></i>Update Instructor Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTeacherForm">
                <input type="hidden" name="teacher_id" id="edit_teacher_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Email Address *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Account Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" style="background: var(--blue);">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// All original logic preserved
document.getElementById('addTeacherForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';

    try {
        const response = await fetch('process/add_teacher.php', { method: 'POST', body: formData });
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
        submitBtn.innerHTML = 'Create Account';
    }
});

document.getElementById('editTeacherForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';

    try {
        const response = await fetch('process/update_teacher.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Update Teacher';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
    }
});

function editTeacher(id) {
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
            } else { showAlert('Failed to load teacher data', 'danger'); }
        });
}

function viewClasses(id) {
    window.location.href = `teachers.php?view_sections=${id}`;
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
            } else { showAlert(data.message, 'danger'); }
        });
    }
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>