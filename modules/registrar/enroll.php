<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Student Enrollment";
$registrar_id = $_SESSION['user_id'];

// Get registrar's branch
$registrar_profile = $conn->query("SELECT branch_id FROM user_profiles WHERE user_id = $registrar_id")->fetch_assoc();
$branch_id = $registrar_profile['branch_id'] ?? 0;

// Get branch info
$branch = $conn->query("SELECT * FROM branches WHERE id = $branch_id")->fetch_assoc();

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch students from this branch with balance info
$students_query = "
    SELECT 
        s.user_id,
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as full_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        COALESCE((SELECT SUM(amount) FROM student_fees WHERE student_id = s.user_id), 0) as total_fees,
        COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.user_id AND status = 'verified'), 0) as total_paid
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN programs p ON s.course_id = p.id
    LEFT JOIN shs_strands ss ON s.course_id = ss.id
    WHERE up.branch_id = $branch_id
    ORDER BY up.last_name, up.first_name
";
$students_result = $conn->query($students_query);

// Fetch sections (classes) for this branch
$sections_query = "
    SELECT 
        cl.id,
        cl.section_name,
        cs.subject_code,
        cs.subject_title,
        cs.units,
        cl.max_capacity,
        cl.current_enrolled,
        cl.schedule,
        cl.room,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        COALESCE(p.program_code, ss.strand_code) as program_code,
        COALESCE(p.program_name, ss.strand_name) as program_name,
        COALESCE(pyl.year_name, sgl.grade_name) as year_level,
        (cl.max_capacity - cl.current_enrolled) as available_slots
    FROM classes cl
    LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.branch_id = $branch_id
    AND cl.academic_year_id = $current_ay_id
    AND cl.current_enrolled < cl.max_capacity
    ORDER BY COALESCE(p.program_name, ss.strand_name), cs.subject_code, cl.section_name
";
$sections_result = $conn->query($sections_query);

// Get student's current enrollments for display
function getStudentEnrollments($conn, $student_id, $branch_id) {
    $result = $conn->query("
        SELECT cl.section_name, cs.subject_code, cs.subject_title, e.status
        FROM enrollments e
        INNER JOIN classes cl ON e.class_id = cl.id
        LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
        WHERE e.student_id = $student_id AND cl.branch_id = $branch_id
        ORDER BY cs.subject_code
    ");
    $enrollments = [];
    while ($row = $result->fetch_assoc()) {
        $enrollments[] = $row;
    }
    return $enrollments;
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-pencil-square"></i> Student Enrollment
            </h4>
            <div>
                <span class="badge bg-info me-2"><?php echo htmlspecialchars($branch['name'] ?? 'Unknown Branch'); ?></span>
                <span class="badge bg-secondary">A.Y. <?php echo htmlspecialchars($current_ay['year_name'] ?? 'N/A'); ?></span>
            </div>
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
                                <?php while ($student = $students_result->fetch_assoc()): 
                                    $balance = $student['total_fees'] - $student['total_paid'];
                                    $has_payment = $student['total_paid'] > 0;
                                ?>
                                <a href="#" class="list-group-item list-group-item-action student-item" 
                                   data-student-id="<?php echo $student['user_id']; ?>"
                                   data-student-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                   data-student-no="<?php echo htmlspecialchars($student['student_no']); ?>"
                                   data-total-fees="<?php echo $student['total_fees']; ?>"
                                   data-total-paid="<?php echo $student['total_paid']; ?>"
                                   data-has-payment="<?php echo $has_payment ? '1' : '0'; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($student['program_code'] ?? 'No Program'); ?>
                                    </small>
                                    <div class="mt-1 d-flex justify-content-between">
                                        <?php if ($has_payment): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> No Payment</span>
                                        <?php endif; ?>
                                        <?php if ($balance > 0): ?>
                                            <small class="text-danger">Bal: ₱<?php echo number_format($balance, 2); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Student Enrollments -->
                <div class="card shadow-sm mt-3" id="currentEnrollmentsCard" style="display:none;">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-list-check"></i> Current Enrollments
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="currentEnrollmentsList">
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Available Sections Card -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-grid-3x3-gap"></i> Available Sections
                        <span id="selectedStudentBadge" class="badge bg-light text-dark ms-2" style="display:none;"></span>
                    </div>
                    <div class="card-body">
                        <div id="paymentWarning" class="alert alert-warning" style="display:none;">
                            <i class="bi bi-exclamation-triangle"></i> Student has no payment. Please record a payment first before enrolling.
                        </div>
                        
                        <!-- Filter by Program -->
                        <div class="mb-3">
                            <select class="form-select" id="programFilter">
                                <option value="">All Programs/Strands</option>
                                <?php 
                                $programs_list = [];
                                $sections_result->data_seek(0);
                                while ($sec = $sections_result->fetch_assoc()) {
                                    $key = $sec['program_code'] ?? 'Other';
                                    if (!isset($programs_list[$key])) {
                                        $programs_list[$key] = $sec['program_name'] ?? 'Other';
                                    }
                                }
                                foreach ($programs_list as $code => $name): 
                                ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($code . ' - ' . $name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-sm" id="sectionsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Section</th>
                                        <th>Subject</th>
                                        <th>Program</th>
                                        <th>Year Level</th>
                                        <th>Schedule</th>
                                        <th>Slots</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sections_result->data_seek(0);
                                    while ($section = $sections_result->fetch_assoc()): 
                                        $percentage = $section['max_capacity'] > 0 ? ($section['current_enrolled'] / $section['max_capacity']) * 100 : 0;
                                        $badge_class = $percentage >= 90 ? 'bg-warning' : 'bg-success';
                                    ?>
                                    <tr data-program="<?php echo htmlspecialchars($section['program_code'] ?? 'Other'); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($section['section_name'] ?? 'TBA'); ?></strong>
                                            <?php if ($section['room']): ?>
                                            <br><small class="text-muted">Room: <?php echo htmlspecialchars($section['room']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($section['subject_code'] ?? '-'); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($section['subject_title'] ?? '-'); ?></small>
                                            <?php if ($section['units']): ?>
                                            <br><small class="badge bg-secondary"><?php echo $section['units']; ?> units</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($section['program_code'] ?? '-'); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($section['year_level'] ?? '-'); ?></small></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($section['schedule'] ?? 'TBA'); ?></small>
                                            <?php if ($section['teacher_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($section['teacher_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $section['current_enrolled']; ?>/<?php echo $section['max_capacity']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary enroll-btn" 
                                                    data-class-id="<?php echo $section['id']; ?>"
                                                    data-class-name="<?php echo htmlspecialchars(($section['section_name'] ?? '') . ' - ' . ($section['subject_code'] ?? '')); ?>"
                                                    disabled>
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($sections_result->num_rows == 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-grid-3x3-gap display-4"></i>
                            <p class="mt-2">No sections available. Please contact the Branch Admin to create sections.</p>
                        </div>
                        <?php endif; ?>
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
let selectedHasPayment = false;

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

// Program filter functionality
document.getElementById('programFilter').addEventListener('change', function(e) {
    const selectedProgram = e.target.value;
    const rows = document.querySelectorAll('#sectionsTable tbody tr');
    
    rows.forEach(row => {
        if (!selectedProgram || row.getAttribute('data-program') === selectedProgram) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
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
        selectedHasPayment = this.getAttribute('data-has-payment') === '1';
        
        // Update badge
        const badge = document.getElementById('selectedStudentBadge');
        badge.textContent = 'Selected: ' + selectedStudentName;
        badge.style.display = 'inline-block';

        const warning = document.getElementById('paymentWarning');
        if (!selectedHasPayment) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
        
        // Enable enroll buttons if has payment
        document.querySelectorAll('.enroll-btn').forEach(btn => btn.disabled = !selectedHasPayment);
        
        // Load current enrollments
        loadCurrentEnrollments(selectedStudentId);
    });
});

// Load current enrollments for selected student
async function loadCurrentEnrollments(studentId) {
    const card = document.getElementById('currentEnrollmentsCard');
    const list = document.getElementById('currentEnrollmentsList');
    
    try {
        const response = await fetch(`process/get_student_enrollments.php?student_id=${studentId}`);
        const data = await response.json();
        
        if (data.status === 'success' && data.enrollments.length > 0) {
            list.innerHTML = data.enrollments.map(e => `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${e.subject_code || 'N/A'}</strong><br>
                        <small class="text-muted">${e.section_name || 'TBA'}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger unenroll-btn" 
                            data-enrollment-id="${e.enrollment_id}"
                            data-class-name="${e.subject_code}">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </li>
            `).join('');
            card.style.display = 'block';
            
            // Attach unenroll handlers
            document.querySelectorAll('.unenroll-btn').forEach(btn => {
                btn.addEventListener('click', handleUnenroll);
            });
        } else {
            list.innerHTML = '<li class="list-group-item text-muted">No current enrollments</li>';
            card.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading enrollments:', error);
    }
}

// Handle unenroll
async function handleUnenroll() {
    const enrollmentId = this.getAttribute('data-enrollment-id');
    const className = this.getAttribute('data-class-name');
    
    if (!confirm(`Remove ${selectedStudentName} from ${className}?`)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('enrollment_id', enrollmentId);
        
        const response = await fetch('process/unenroll_student.php', {
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
        showAlert('An error occurred during unenrollment', 'danger');
    }
}

// Enrollment process
document.querySelectorAll('.enroll-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!selectedStudentId) {
            showAlert('Please select a student first', 'warning');
            return;
        }

        if (!selectedHasPayment) {
            showAlert('Student has no payment recorded. Please record a payment first.', 'danger');
            return;
        }
        
        const classId = this.getAttribute('data-class-id');
        const className = this.getAttribute('data-class-name');
        
        if (!confirm(`Enroll ${selectedStudentName} in ${className}?`)) {
            return;
        }
        
        // Disable button and show loading
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
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
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message, 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-plus-circle"></i>';
            }
        } catch (error) {
            showAlert('An error occurred during enrollment', 'danger');
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-plus-circle"></i>';
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