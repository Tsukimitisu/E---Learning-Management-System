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

// Fetch all scheduled classes for this branch
$classes_query = "
    SELECT 
        cl.id,
        cl.section_name,
        cl.schedule,
        cl.room,
        cl.max_capacity,
        cl.current_enrolled,
        s.subject_code,
        s.subject_title,
        s.units,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        ay.year_name,
        p.program_name
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

// Fetch academic years
$academic_years = $conn->query("SELECT id, year_name, is_active FROM academic_years ORDER BY year_name DESC");

// Fetch subjects
$subjects = $conn->query("
    SELECT s.id, s.subject_code, s.subject_title, s.units, p.program_name 
    FROM subjects s 
    INNER JOIN programs p ON s.program_id = p.id
    WHERE s.is_active = 1
    ORDER BY p.program_name, s.subject_code
");

// Fetch teachers
$teachers = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . " AND u.status = 'active'
    ORDER BY up.first_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-calendar-plus"></i> Class Scheduling & Sectioning
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#scheduleClassModal">
                <i class="bi bi-plus-circle"></i> Schedule New Class
            </button>
        </div>

        <div id="alertContainer"></div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>ID</th>
                                <th>Academic Year</th>
                                <th>Subject</th>
                                <th>Program</th>
                                <th>Section</th>
                                <th>Teacher</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($class = $classes_result->fetch_assoc()): 
                                $percentage = ($class['max_capacity'] > 0) ? 
                                    ($class['current_enrolled'] / $class['max_capacity']) * 100 : 0;
                                
                                if ($percentage >= 100) {
                                    $badge_class = 'bg-danger';
                                    $status_text = 'FULL';
                                } elseif ($percentage >= 80) {
                                    $badge_class = 'bg-warning';
                                    $status_text = 'ALMOST FULL';
                                } else {
                                    $badge_class = 'bg-success';
                                    $status_text = 'AVAILABLE';
                                }
                            ?>
                            <tr>
                                <td><?php echo $class['id']; ?></td>
                                <td><?php echo htmlspecialchars($class['year_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                                </td>
                                <td><small><?php echo htmlspecialchars($class['program_name'] ?? 'N/A'); ?></small></td>
                                <td><?php echo htmlspecialchars($class['section_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                <td><small><?php echo htmlspecialchars($class['schedule'] ?? '-'); ?></small></td>
                                <td><?php echo htmlspecialchars($class['room'] ?? '-'); ?></td>
                                <td><?php echo $class['current_enrolled']; ?> / <?php echo $class['max_capacity']; ?></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editClass(<?php echo $class['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
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

<!-- Schedule Class Modal -->
<div class="modal fade" id="scheduleClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Schedule New Class</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleClassForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" name="academic_year_id" required>
                                <option value="">-- Select Academic Year --</option>
                                <?php 
                                $academic_years->data_seek(0);
                                while ($ay = $academic_years->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $ay['id']; ?>" <?php echo $ay['is_active'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ay['year_name']); ?>
                                        <?php echo $ay['is_active'] ? ' (Active)' : ''; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject_id" required>
                                <option value="">-- Select Subject --</option>
                                <?php 
                                $subjects->data_seek(0);
                                while ($subject = $subjects->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_title']); ?>
                                        (<?php echo $subject['units']; ?> units)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Section Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="section_name" required placeholder="e.g. Section A, Block 1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teacher <span class="text-danger">*</span></label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">-- Select Teacher --</option>
                                <?php 
                                $teachers->data_seek(0);
                                while ($teacher = $teachers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="room" required placeholder="e.g. Lab 1, Room 301">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="max_capacity" required min="1" max="100" value="35">
                            <small class="text-muted">Critical for enrollment control</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Schedule <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="schedule" required placeholder="e.g. MWF 10:00-11:30 AM, TTH 2:00-3:30 PM">
                        <small class="text-muted">Format: Days and Time (e.g., Monday/Wednesday/Friday 10:00-11:30 AM)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Schedule Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('scheduleClassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scheduling...';
    
    try {
        const response = await fetch('process/schedule_class.php', {
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
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Schedule Class';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Schedule Class';
    }
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

function editClass(id) {
    alert('Edit functionality will be implemented. Class ID: ' + id);
}
</script>
</body>
</html>