<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Class Scheduling";
$branch_id = get_user_branch_id();
if ($branch_id === null) {
    echo "Error: Your account is not assigned to any branch. Please contact the School Administrator.";
    exit();
}
require_branch_assignment();

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$classes_query = "
    SELECT 
        cl.id, cl.section_name, cl.schedule, cl.room, cl.max_capacity, cl.current_enrolled,
        s.subject_code, s.subject_title, s.units,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        ay.year_name, p.program_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN academic_years ay ON cl.academic_year_id = ay.id
    WHERE cl.branch_id = ?
    ORDER BY ay.year_name DESC, s.subject_code, cl.section_name
";

$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$classes_result = $stmt->get_result();

$academic_years = $conn->query("SELECT id, year_name, is_active FROM academic_years ORDER BY year_name DESC");

$subjects = $conn->query("
    SELECT s.id, s.subject_code, s.subject_title, s.units, p.program_name 
    FROM subjects s 
    INNER JOIN programs p ON s.program_id = p.id
    WHERE s.is_active = 1
    ORDER BY p.program_name, s.subject_code
");

$teachers = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . " AND u.status = 'active'
    ORDER BY up.first_name
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
        padding: 4px 12px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    }
    .status-available { background: #e6f4ea; color: #1e7e34; }
    .status-warning { background: #fff4e5; color: #664d03; }
    .status-danger { background: #fceaea; color: #dc3545; }

    .btn-maroon { background-color: var(--maroon); color: white; font-weight: 700; border: none; }
    .btn-maroon:hover { background-color: #600000; color: white; transform: translateY(-1px); }

    .schedule-text { font-size: 0.75rem; font-weight: 600; color: #555; }
    .room-badge { background: #f1f3f5; color: var(--blue); border-radius: 5px; padding: 2px 8px; font-weight: 700; font-size: 0.7rem; }
</style>

<div class="main-content-body animate__animated animate__fadeIn">
    
    <!-- 1. PAGE HEADER -->
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="mb-2 mb-md-0">
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <i class="bi bi-calendar-plus-fill me-2 text-maroon"></i>Class Scheduling & Sectioning
            </h4>
            <p class="text-muted small mb-0">Create new class sections, assign instructors, and manage room schedules.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-maroon btn-sm px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#scheduleClassModal">
                <i class="bi bi-plus-circle me-1"></i> New Schedule
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- 2. CLASSES DIRECTORY -->
    <div class="content-card">
        <div class="card-header-modern bg-white">
            <i class="bi bi-collection me-2"></i> Branch Class Masterlist
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern mb-0">
                <thead>
                    <tr>
                        <th>Subject Info</th>
                        <th>Program / Year</th>
                        <th>Section</th>
                        <th>Instructor</th>
                        <th>Schedule & Room</th>
                        <th class="text-center">Capacity</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($class = $classes_result->fetch_assoc()): 
                        $percentage = ($class['max_capacity'] > 0) ? ($class['current_enrolled'] / $class['max_capacity']) * 100 : 0;
                        
                        if ($percentage >= 100) { $pill = 'status-danger'; $text = 'Full'; }
                        elseif ($percentage >= 80) { $pill = 'status-warning'; $text = 'Almost Full'; }
                        else { $pill = 'status-available'; $text = 'Open'; }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></div>
                            <small class="text-muted line-clamp-1" style="max-width: 200px;"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <div class="small fw-bold"><?php echo htmlspecialchars($class['program_name'] ?? 'N/A'); ?></div>
                            <span class="badge bg-light text-dark border" style="font-size: 0.6rem;"><?php echo htmlspecialchars($class['year_name'] ?? 'N/A'); ?></span>
                        </td>
                        <td><span class="badge bg-blue bg-opacity-10 text-blue fw-bold px-3"><?php echo htmlspecialchars($class['section_name'] ?? '-'); ?></span></td>
                        <td><small class="fw-bold"><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></small></td>
                        <td>
                            <div class="schedule-text mb-1"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($class['schedule'] ?? '-'); ?></div>
                            <span class="room-badge"><i class="bi bi-geo-alt-fill me-1"></i><?php echo htmlspecialchars($class['room'] ?? '-'); ?></span>
                        </td>
                        <td class="text-center">
                            <div class="fw-bold"><?php echo $class['current_enrolled']; ?> / <?php echo $class['max_capacity']; ?></div>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar <?php echo str_replace('status-', 'bg-', $pill); ?>" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="status-pill <?php echo $pill; ?>"><?php echo $text; ?></span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-white border shadow-sm rounded-circle" onclick="editClass(<?php echo $class['id']; ?>)">
                                <i class="bi bi-pencil-square text-primary"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Schedule Class Modal -->
<div class="modal fade" id="scheduleClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-maroon text-dark py-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-calendar-plus me-2"></i>Schedule New Class Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleClassForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Academic Period *</label>
                            <select class="form-select shadow-sm" name="academic_year_id" required>
                                <option value="">-- Choose Period --</option>
                                <?php $academic_years->data_seek(0); while ($ay = $academic_years->fetch_assoc()): ?>
                                    <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_active'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ay['year_name']); ?> <?php echo $ay['is_active'] ? '(Live)' : ''; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Subject Curriculum *</label>
                            <select class="form-select shadow-sm" name="subject_id" required>
                                <option value="">-- Choose Subject --</option>
                                <?php $subjects->data_seek(0); while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Section Name *</label>
                            <input type="text" class="form-control shadow-sm" name="section_name" required placeholder="e.g., Section A">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Assigned Teacher *</label>
                            <select class="form-select shadow-sm" name="teacher_id" required>
                                <option value="">-- Choose Instructor --</option>
                                <?php $teachers->data_seek(0); while ($teacher = $teachers->fetch_assoc()): ?>
                                    <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Facility / Room *</label>
                            <input type="text" class="form-control shadow-sm" name="room" required placeholder="e.g., CL 1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase opacity-75">Max Student Count *</label>
                            <input type="number" class="form-control shadow-sm" name="max_capacity" required min="1" max="100" value="35">
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase opacity-75">Weekly Schedule *</label>
                        <input type="text" class="form-control shadow-sm" name="schedule" required placeholder="e.g., MWF 10:00-11:30 AM">
                        <small class="text-muted mt-1 d-block" style="font-size: 0.65rem;"><i class="bi bi-info-circle me-1"></i>Format: Day Abbreviations + Start Time - End Time</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-light btn-sm px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon btn-sm px-4 fw-bold">CREATE CLASS SECTION</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Logic preserved exactly as requested
document.getElementById('scheduleClassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    
    try {
        const response = await fetch('process/schedule_class.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'CREATE CLASS SECTION';
        }
    } catch (error) {
        showAlert('An error occurred while scheduling.', 'danger');
        submitBtn.disabled = false;
    }
});

function editClass(id) { Swal.fire('Management', 'The update/edit module for Class ID #' + id + ' is being initialized.', 'info'); }

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert"><i class="bi bi-info-circle-fill me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>