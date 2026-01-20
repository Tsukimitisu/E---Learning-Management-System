<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Class Management";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$classes_query = "
    SELECT 
        cl.id, cl.room, cl.max_capacity, cl.current_enrolled,
        c.course_code, c.title as course_title,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        b.name as branch_name
    FROM classes cl
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN branches b ON c.branch_id = b.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    ORDER BY c.course_code, cl.room
";
$classes_result = $conn->query($classes_query);

$courses_result = $conn->query("
    SELECT c.id, c.course_code, c.title, b.name as branch_name 
    FROM courses c 
    INNER JOIN branches b ON c.branch_id = b.id
    ORDER BY c.course_code
");

$teachers_result = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . "
    ORDER BY up.first_name
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC CLASS UI --- */
    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; font-size: 0.85rem; border-bottom: 1px solid #f1f1f1; }

    .progress-tiny { height: 6px; border-radius: 10px; background: #eee; overflow: hidden; margin-top: 5px; }

    .btn-maroon-add {
        background-color: var(--maroon); color: white; border: none; border-radius: 50px;
        font-weight: 700; padding: 8px 20px; transition: 0.3s; font-size: 0.85rem;
    }
    .btn-maroon-add:hover { background-color: #600000; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(128,0,0,0.2); }

    .action-btn-edit {
        width: 32px; height: 32px; border-radius: 8px; display: inline-flex;
        align-items: center; justify-content: center; transition: 0.2s;
        background: #f8f9fa; border: 1px solid #eee; color: #f39c12;
    }
    .action-btn-edit:hover { background: #f39c12; color: white; border-color: #f39c12; }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-door-open-fill me-2 text-maroon"></i>Class Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-maroon text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Classes</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-maroon-add shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Class
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <div class="main-card-modern">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Class Details</th>
                        <th>Academic Course</th>
                        <th>Assigned Faculty</th>
                        <th class="text-center">Branch</th>
                        <th class="text-center">Room</th>
                        <th class="text-center" style="width: 150px;">Occupancy</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($classes_result->num_rows == 0): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No classes created yet.</td></tr>
                    <?php else: while ($class = $classes_result->fetch_assoc()): 
                        // Logic for percentage (UNTOUCHED)
                        $pct = ($class['max_capacity'] > 0) ? ($class['current_enrolled'] / $class['max_capacity']) * 100 : 0;
                        
                        if ($pct >= 100) {
                            $badge = 'bg-danger'; $status = 'FULL'; $bar = 'bg-danger';
                        } elseif ($pct >= 80) {
                            $badge = 'bg-warning text-dark'; $status = 'NEAR LIMIT'; $bar = 'bg-warning';
                        } else {
                            $badge = 'bg-success'; $status = 'AVAILABLE'; $bar = 'bg-success';
                        }
                    ?>
                    <tr>
                        <td class="ps-4">
                            <span class="text-muted fw-bold small">#<?php echo $class['id']; ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($class['course_code']); ?></div>
                            <small class="text-muted text-truncate d-block" style="max-width: 200px;"><?php echo htmlspecialchars($class['course_title']); ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle-sm bg-light text-blue fw-bold me-2 d-flex align-items-center justify-content-center border" style="width:30px; height:30px; border-radius:50%; font-size:0.7rem;">
                                    <?php echo strtoupper(substr($class['teacher_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <span class="small fw-bold"><?php echo htmlspecialchars($class['teacher_name'] ?? 'Unassigned'); ?></span>
                            </div>
                        </td>
                        <td class="text-center small"><?php echo htmlspecialchars($class['branch_name']); ?></td>
                        <td class="text-center fw-bold text-maroon"><?php echo htmlspecialchars($class['room']); ?></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                <span><?php echo $class['current_enrolled']; ?></span>
                                <span class="text-muted">/ <?php echo $class['max_capacity']; ?></span>
                            </div>
                            <div class="progress-tiny">
                                <div class="progress-bar <?php echo $bar; ?>" style="width: <?php echo min($pct, 100); ?>%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge <?php echo $badge; ?> rounded-pill px-3" style="font-size: 0.65rem;">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <button class="action-btn-edit" onclick="editClass(<?php echo $class['id']; ?>)" title="Edit Settings">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Create New Class Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addClassForm">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">SELECT COURSE/SUBJECT *</label>
                            <select class="form-select border-light shadow-sm" name="course_id" required>
                                <option value="">-- Search & Select Course --</option>
                                <?php $courses_result->data_seek(0); while ($course = $courses_result->fetch_assoc()): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title'] . ' (' . $course['branch_name'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted">ASSIGN INSTRUCTOR *</label>
                            <select class="form-select border-light shadow-sm" name="teacher_id" required>
                                <option value="">-- Search & Select Teacher --</option>
                                <?php $teachers_result->data_seek(0); while ($teacher = $teachers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">ROOM ASSIGNMENT *</label>
                            <input type="text" class="form-control border-light shadow-sm" name="room" required placeholder="e.g. Room 302 - IT Lab">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">MAXIMUM CAPACITY *</label>
                            <input type="number" class="form-control border-light shadow-sm" name="max_capacity" required min="1" value="30">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon-add px-4">Create Class Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED --- -->
<script>
document.getElementById('addClassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    
    try {
        const response = await fetch('process/add_class.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Class Record';
        }
    } catch (error) {
        showAlert('An error occurred during class creation', 'danger');
        submitBtn.disabled = false;
    }
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

function editClass(classId) {
    alert('Edit functionality call for ID: ' + classId);
}
</script>
</body>
</html>