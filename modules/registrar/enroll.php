<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Enrollment";

// Fetch all students
$students_query = "
    SELECT 
        s.user_id,
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        c.course_code,
        c.title as course_title
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    ORDER BY up.first_name, up.last_name
";
$students_result = $conn->query($students_query);

// Fetch available classes with detailed info
$classes_query = "
    SELECT 
        cl.id,
        cl.room,
        cl.max_capacity,
        cl.current_enrolled,
        c.course_code,
        c.title as course_title,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        b.name as branch_name,
        (cl.max_capacity - cl.current_enrolled) as available_slots
    FROM classes cl
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN branches b ON c.branch_id = b.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.current_enrolled < cl.max_capacity
    ORDER BY c.course_code, cl.room
";
$classes_result = $conn->query($classes_query);

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-pencil-square"></i> Student Enrollment
            </h4>
        </div>

        <div id="alertContainer"></div>

        <div class="row">
            <!-- Student Selection Card -->
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-person-check"></i> Select Student
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Search Student</label>
                            <input type="text" class="form-control" id="studentSearch" placeholder="Type student name or number...">
                        </div>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <div class="list-group" id="studentList">
                                <?php while ($student = $students_result->fetch_assoc()): ?>
                                <a href="#" class="list-group-item list-group-item-action student-item" 
                                   data-student-id="<?php echo $student['user_id']; ?>"
                                   data-student-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                   data-student-no="<?php echo htmlspecialchars($student['student_no']); ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($student['course_code'] ?? 'No Course'); ?> - 
                                        <?php echo htmlspecialchars($student['course_title'] ?? 'N/A'); ?>
                                    </small>
                                </a>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Classes Card -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-door-open"></i> Available Classes
                        <span id="selectedStudentBadge" class="badge bg-light text-dark ms-2" style="display:none;"></span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Teacher</th>
                                        <th>Room</th>
                                        <th>Branch</th>
                                        <th>Capacity</th>
                                        <th>Available</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($class = $classes_result->fetch_assoc()): 
                                        $percentage = ($class['current_enrolled'] / $class['max_capacity']) * 100;
                                        
                                        if ($percentage >= 90) {
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $badge_class = 'bg-success';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['course_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($class['course_title']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['teacher_name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['room']); ?></td>
                                        <td><?php echo htmlspecialchars($class['branch_name']); ?></td>
                                        <td>
                                            <?php echo $class['current_enrolled']; ?> / <?php echo $class['max_capacity']; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $class['available_slots']; ?> slots
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary enroll-btn" 
                                                    data-class-id="<?php echo $class['id']; ?>"
                                                    data-class-name="<?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['room']); ?>"
                                                    disabled>
                                                <i class="bi bi-plus-circle"></i> Enroll
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedStudentId = null;
let selectedStudentName = '';

// Student search functionality
document.getElementById('studentSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const studentItems = document.querySelectorAll('.student-item');
    
    studentItems.forEach(item => {
        const name = item.getAttribute('data-student-name').toLowerCase();
        const studentNo = item.getAttribute('data-student-no').toLowerCase();
        
        if (name.includes(searchTerm) || studentNo.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Student selection
document.querySelectorAll('.student-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all items
        document.querySelectorAll('.student-item').forEach(i => i.classList.remove('active'));
        
        // Add active class to clicked item
        this.classList.add('active');
        
        // Store selected student
        selectedStudentId = this.getAttribute('data-student-id');
        selectedStudentName = this.getAttribute('data-student-name');
        
        // Update badge
        const badge = document.getElementById('selectedStudentBadge');
        badge.textContent = 'Selected: ' + selectedStudentName;
        badge.style.display = 'inline-block';
        
        // Enable enroll buttons
        document.querySelectorAll('.enroll-btn').forEach(btn => btn.disabled = false);
    });
});

// Enrollment process
document.querySelectorAll('.enroll-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!selectedStudentId) {
            showAlert('Please select a student first', 'warning');
            return;
        }
        
        const classId = this.getAttribute('data-class-id');
        const className = this.getAttribute('data-class-name');
        
        if (!confirm(`Enroll ${selectedStudentName} in ${className}?`)) {
            return;
        }
        
        // Disable button and show loading
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        
        try {
            const formData = new FormData();
            formData.append('student_id', selectedStudentId);
            formData.append('class_id', classId);
            
            const response = await fetch('process/process_enroll.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(data.message, 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-plus-circle"></i> Enroll';
            }
        } catch (error) {
            showAlert('An error occurred during enrollment', 'danger');
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-plus-circle"></i> Enroll';
        }
    });
});

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>