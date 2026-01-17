<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Section Management";
$branch_id = 1; // In production, fetch from user's assigned branch

// Fetch all sections in this branch
$sections_query = "
    SELECT
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        s.units,
        cl.max_capacity,
        cl.current_enrolled,
        cl.schedule,
        cl.room,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
        ay.year_name,
        p.program_name,
        COUNT(CASE WHEN e.status = 'approved' THEN 1 END) as approved_enrollments,
        COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_enrollments
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN academic_years ay ON cl.academic_year_id = ay.id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN enrollments e ON cl.id = e.class_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, s.units,
             cl.max_capacity, cl.current_enrolled, cl.schedule, cl.room,
             up.first_name, up.last_name, ay.year_name, p.program_name
    ORDER BY p.program_name, s.subject_code, cl.section_name
";

$sections = $conn->query($sections_query);

// Get unassigned students (enrolled in branch but not in any classes)
$unassigned_students = $conn->query("
    SELECT
        u.id,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        u.email
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN user_roles ur ON u.id = ur.user_id
    INNER JOIN students st ON u.id = st.user_id
    INNER JOIN courses c ON st.course_id = c.id
    WHERE ur.role_id = " . ROLE_STUDENT . "
    AND c.branch_id = $branch_id
    AND u.status = 'active'
    AND u.id NOT IN (
        SELECT DISTINCT e.student_id
        FROM enrollments e
        INNER JOIN classes cl ON e.class_id = cl.id
        WHERE cl.branch_id = $branch_id AND e.status = 'approved'
    )
    ORDER BY up.last_name, up.first_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-diagram-3"></i> Section Management & Enrollment
            </h4>
            <div class="d-flex gap-2">
                <button class="btn btn-sm text-white" style="background-color: #800000;" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="bi bi-plus-circle"></i> Add Section
                </button>
                <button class="btn btn-sm text-white" style="background-color: #003366;" onclick="refreshSections()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Teacher Workload Overview -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #17a2b8; color: white;">
                        <h5 class="mb-0"><i class="bi bi-person-badge"></i> Teacher Workload & Section Assignments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Teacher</th>
                                        <th>Assigned Sections</th>
                                        <th>Subjects Teaching</th>
                                        <th>Total Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $teacher_workload = $conn->query("
                                        SELECT
                                            u.id as teacher_id,
                                            CONCAT(up.first_name, ' ', up.last_name) as teacher_name,
                                            COUNT(DISTINCT cl.id) as section_count,
                                            GROUP_CONCAT(DISTINCT CONCAT(s.subject_code, ' (', cl.section_name, ')') SEPARATOR '<br>') as subjects,
                                            SUM(cl.current_enrolled) as total_students
                                        FROM users u
                                        INNER JOIN user_profiles up ON u.id = up.user_id
                                        INNER JOIN user_roles ur ON u.id = ur.user_id
                                        LEFT JOIN classes cl ON cl.teacher_id = u.id AND cl.branch_id = $branch_id
                                        LEFT JOIN subjects s ON cl.subject_id = s.id
                                        WHERE ur.role_id = " . ROLE_TEACHER . " AND u.status = 'active'
                                        GROUP BY u.id, up.first_name, up.last_name
                                        ORDER BY up.last_name, up.first_name
                                    ");

                                    while ($workload = $teacher_workload->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($workload['teacher_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $workload['section_count']; ?> sections</span>
                                        </td>
                                        <td>
                                            <small><?php echo $workload['subjects'] ?: 'No sections assigned'; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $workload['total_students'] ?? 0; ?> students</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewTeacherSections(<?php echo $workload['teacher_id']; ?>)">
                                                <i class="bi bi-eye"></i> View Sections
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

        <!-- Section Overview -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Class Sections Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="sectionsTable">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Section</th>
                                        <th>Subject & Program</th>
                                        <th>Teacher Assignment</th>
                                        <th>Schedule & Room</th>
                                        <th>Enrollment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sections->data_seek(0);
                                    while ($section = $sections->fetch_assoc()):
                                        $enrolled = $section['approved_enrollments'] ?? 0;
                                        $capacity = $section['max_capacity'];
                                        $percentage = $capacity > 0 ? ($enrolled / $capacity) * 100 : 0;

                                        if ($percentage >= 100) {
                                            $status_class = 'bg-danger';
                                            $status_text = 'FULL';
                                        } elseif ($percentage >= 80) {
                                            $status_class = 'bg-warning';
                                            $status_text = 'ALMOST FULL';
                                        } else {
                                            $status_class = 'bg-success';
                                            $status_text = 'AVAILABLE';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($section['section_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($section['year_name'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($section['subject_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($section['subject_title']); ?> (<?php echo $section['units']; ?> units)</small><br>
                                            <small class="text-info"><?php echo htmlspecialchars($section['program_name'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($section['teacher_name']): ?>
                                                <strong><?php echo htmlspecialchars($section['teacher_name']); ?></strong><br>
                                                <small class="text-success">Assigned</small>
                                            <?php else: ?>
                                                <span class="text-danger">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($section['schedule'] ?? '-'); ?></small><br>
                                            <small class="text-muted">Room: <?php echo htmlspecialchars($section['room'] ?? '-'); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 60px; height: 8px;">
                                                    <div class="progress-bar bg-<?php echo $status_class == 'bg-danger' ? 'danger' : ($status_class == 'bg-warning' ? 'warning' : 'success'); ?>"
                                                         style="width: <?php echo min($percentage, 100); ?>%">
                                                    </div>
                                                </div>
                                                <small><?php echo $enrolled; ?>/<?php echo $capacity; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <button class="btn btn-outline-info mb-1" onclick="viewSectionDetails(<?php echo $section['id']; ?>)">
                                                    <i class="bi bi-eye"></i> Details
                                                </button>
                                                <button class="btn btn-outline-success mb-1" onclick="bulkAssignStudents(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')">
                                                    <i class="bi bi-plus-circle"></i> Enroll
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="manageSection(<?php echo $section['id']; ?>)">
                                                    <i class="bi bi-gear"></i> Manage
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
            </div>
        </div>

        <!-- Unassigned Students -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #6f42c1; color: white;">
                        <h5 class="mb-0"><i class="bi bi-person-dash"></i> Unassigned Students (<?php echo $unassigned_students->num_rows; ?>)</h5>
                        <button class="btn btn-sm btn-light" onclick="bulkAssignModal()">
                            <i class="bi bi-plus-circle"></i> Bulk Assign
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if ($unassigned_students->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th><input type="checkbox" id="selectAllUnassigned"></th>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Quick Assign</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $unassigned_students->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>"></td>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <select class="form-select form-select-sm quick-assign" data-student-id="<?php echo $student['id']; ?>">
                                                <option value="">-- Quick Assign to Section --</option>
                                                <?php
                                                $sections->data_seek(0);
                                                while ($sec = $sections->fetch_assoc()):
                                                    $sec_enrolled = $sec['approved_enrollments'] ?? 0;
                                                    $sec_capacity = $sec['max_capacity'];
                                                    if ($sec_enrolled < $sec_capacity):
                                                ?>
                                                    <option value="<?php echo $sec['id']; ?>">
                                                        <?php echo htmlspecialchars($sec['section_name'] . ' - ' . $sec['subject_code']); ?>
                                                    </option>
                                                <?php endif; endwhile; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-success">
                            <i class="bi bi-check-circle"></i> All students are assigned to sections
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSectionForm">
                <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" name="academic_year_id" required>
                                <option value="">-- Select Academic Year --</option>
                                <?php
                                $academic_years = $conn->query("SELECT id, year_name, is_active FROM academic_years ORDER BY year_name DESC");
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
                                $subjects = $conn->query("
                                    SELECT s.id, s.subject_code, s.subject_title, s.units, p.program_name
                                    FROM subjects s
                                    INNER JOIN programs p ON s.program_id = p.id
                                    WHERE s.is_active = 1
                                    ORDER BY p.program_name, s.subject_code
                                ");
                                while ($subject = $subjects->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_title']); ?>
                                        (<?php echo $subject['units']; ?> units - <?php echo $subject['program_name']; ?>)
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
                            <label class="form-label">Assign Teacher <span class="text-danger">*</span></label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">-- Select Teacher --</option>
                                <?php
                                $teachers = $conn->query("
                                    SELECT u.id, CONCAT(up.first_name, ' ', up.last_name) as name
                                    FROM users u
                                    INNER JOIN user_profiles up ON u.id = up.user_id
                                    INNER JOIN user_roles ur ON u.id = ur.user_id
                                    WHERE ur.role_id = " . ROLE_TEACHER . " AND u.status = 'active'
                                    ORDER BY up.first_name
                                ");
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
                            <small class="text-muted">Maximum number of students for this section</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Schedule <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="schedule" required placeholder="e.g. MWF 10:00-11:30 AM, TTH 2:00-3:30 PM">
                        <small class="text-muted">Format: Days and Time (e.g., Monday/Wednesday/Friday 10:00-11:30 AM)</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will create a new section and assign it to the selected teacher. Students can then be enrolled in this section.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-plus-circle"></i> Create Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign Modal -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #800000; color: white;">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Bulk Assign Students to Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkAssignForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Section</label>
                        <select class="form-select" name="class_id" id="bulk_class_select" required>
                            <option value="">-- Select Section --</option>
                            <?php
                            $sections->data_seek(0);
                            while ($sec = $sections->fetch_assoc()):
                                $enrolled = $sec['approved_enrollments'] ?? 0;
                                $capacity = $sec['max_capacity'];
                                if ($enrolled < $capacity):
                            ?>
                                <option value="<?php echo $sec['id']; ?>" data-available="<?php echo $capacity - $enrolled; ?>">
                                    <?php echo htmlspecialchars($sec['section_name'] . ' - ' . $sec['subject_code'] . ' (' . $enrolled . '/' . $capacity . ' enrolled)'); ?>
                                </option>
                            <?php endif; endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Students</label>
                        <div id="studentSelection" style="max-height: 300px; overflow-y: auto;">
                            <?php
                            $unassigned_students->data_seek(0);
                            while ($student = $unassigned_students->fetch_assoc()):
                            ?>
                            <div class="form-check">
                                <input class="form-check-input bulk-student-checkbox" type="checkbox"
                                       value="<?php echo $student['id']; ?>" id="student_<?php echo $student['id']; ?>">
                                <label class="form-check-label" for="student_<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['student_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Selected students will be enrolled in the chosen section.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background-color: #800000;">
                        <i class="bi bi-plus-circle"></i> Assign Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addSectionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';

    try {
        const response = await fetch('process/add_section.php', {
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
            submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Create Section';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Create Section';
    }
});

document.getElementById('bulkAssignForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    // Get selected students
    const selectedStudents = [];
    document.querySelectorAll('.bulk-student-checkbox:checked').forEach(cb => {
        selectedStudents.push(cb.value);
    });

    if (selectedStudents.length === 0) {
        showAlert('Please select at least one student', 'warning');
        return;
    }

    formData.append('student_ids', JSON.stringify(selectedStudents));

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Assigning...';

    try {
        const response = await fetch('process/bulk_assign_students.php', {
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
            submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Assign Students';
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Assign Students';
    }
});

// Quick assign functionality
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('quick-assign')) {
        const studentId = e.target.dataset.studentId;
        const classId = e.target.value;

        if (classId && confirm('Assign this student to the selected section?')) {
            quickAssignStudent(studentId, classId);
        }
    }
});

async function quickAssignStudent(studentId, classId) {
    try {
        const response = await fetch('process/assign_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `student_id=${studentId}&class_id=${classId}`
        });
        const data = await response.json();

        if (data.status === 'success') {
            showAlert('Student assigned successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('An error occurred', 'danger');
    }
}

function bulkAssignModal() {
    new bootstrap.Modal(document.getElementById('bulkAssignModal')).show();
}

function viewSectionDetails(sectionId) {
    window.location.href = `section_details.php?section_id=${sectionId}`;
}

function bulkAssignStudents(sectionId, sectionName) {
    // Pre-select the section and show modal
    document.getElementById('bulk_class_select').value = sectionId;
    new bootstrap.Modal(document.getElementById('bulkAssignModal')).show();
}

function viewTeacherSections(teacherId) {
    window.location.href = `teachers.php?view_sections=${teacherId}`;
}

function manageSection(sectionId) {
    window.location.href = `scheduling.php?edit=${sectionId}`;
}

function refreshSections() {
    location.reload();
}

// Select all functionality
document.getElementById('selectAllUnassigned')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
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