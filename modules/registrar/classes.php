<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Class Management";

// Fetch all classes with course and teacher info
$classes_query = "
    SELECT 
        cl.id,
        cl.room,
        cl.max_capacity,
        cl.current_enrolled,
        c.course_code,
        c.title as course_title,
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

// Fetch courses for dropdown
$courses_result = $conn->query("
    SELECT c.id, c.course_code, c.title, b.name as branch_name 
    FROM courses c 
    INNER JOIN branches b ON c.branch_id = b.id
    ORDER BY c.course_code
");

// Fetch teachers for dropdown
$teachers_result = $conn->query("
    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = " . ROLE_TEACHER . "
    ORDER BY up.first_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-door-open"></i> Class Management
            </h4>
            <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="bi bi-plus-circle"></i> Add New Class
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
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Branch</th>
                                <th>Teacher</th>
                                <th>Room</th>
                                <th>Capacity</th>
                                <th>Enrolled</th>
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
                                <td><strong><?php echo htmlspecialchars($class['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class['course_title']); ?></td>
                                <td><?php echo htmlspecialchars($class['branch_name']); ?></td>
                                <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo htmlspecialchars($class['room']); ?></td>
                                <td><?php echo $class['max_capacity']; ?></td>
                                <td><strong><?php echo $class['current_enrolled']; ?></strong></td>
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

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Class</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addClassForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php 
                            $courses_result->data_seek(0);
                            while ($course = $courses_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['title'] . ' (' . $course['branch_name'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Teacher <span class="text-danger">*</span></label>
                        <select class="form-select" name="teacher_id" required>
                            <option value="">-- Select Teacher --</option>
                            <?php 
                            $teachers_result->data_seek(0);
                            while ($teacher = $teachers_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="room" required placeholder="e.g. Room 101">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="max_capacity" required min="1" value="30">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-save"></i> Create Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addClassForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process/add_class.php', {
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

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}

function editClass(classId) {
    alert('Edit functionality will be implemented');
}
</script>
</body>
</html>