<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_BRANCH_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Monitoring & Compliance";
$branch_id = 1; // In production, fetch from user's assigned branch

// Get date range for monitoring (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Low Attendance Classes (attendance rate < 70%)
$low_attendance_classes = $conn->query("
    SELECT
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        COUNT(DISTINCT e.student_id) as total_enrolled,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) as total_present,
        ROUND(
            (COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) * 100.0) /
            NULLIF(COUNT(DISTINCT e.student_id), 0), 2
        ) as attendance_rate,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN attendance a ON cl.id = a.class_id AND a.attendance_date BETWEEN '$start_date' AND '$end_date'
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, up.first_name, up.last_name
    HAVING attendance_rate < 70 OR attendance_rate IS NULL
    ORDER BY attendance_rate ASC
");

// Students with Poor Attendance (< 70% in any class)
$poor_attendance_students = $conn->query("
    SELECT
        u.id as student_id,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        COUNT(a.attendance_date) as total_sessions,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        ROUND(
            (COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0) /
            NULLIF(COUNT(a.attendance_date), 0), 2
        ) as attendance_rate
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN enrollments e ON u.id = e.student_id AND e.status = 'approved'
    INNER JOIN classes cl ON e.class_id = cl.id AND cl.branch_id = $branch_id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN attendance a ON cl.id = a.class_id AND a.attendance_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY u.id, up.first_name, up.last_name, cl.id, cl.section_name, s.subject_code, s.subject_title
    HAVING attendance_rate < 70
    ORDER BY attendance_rate ASC
");

// Failing Students (final grade < 75 or no grade submitted)
$failing_students = $conn->query("
    SELECT
        u.id as student_id,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        g.final_grade,
        g.remarks,
        CONCAT(tup.first_name, ' ', tup.last_name) as teacher_name
    FROM users u
    INNER JOIN user_profiles up ON u.id = up.user_id
    INNER JOIN enrollments e ON u.id = e.student_id AND e.status = 'approved'
    INNER JOIN classes cl ON e.class_id = cl.id AND cl.branch_id = $branch_id
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN grades g ON u.id = g.student_id AND cl.id = g.class_id
    LEFT JOIN users tu ON cl.teacher_id = tu.id
    LEFT JOIN user_profiles tup ON tu.id = tup.user_id
    WHERE (g.final_grade < 75 AND g.final_grade IS NOT NULL) OR g.final_grade IS NULL
    ORDER BY g.final_grade ASC, up.last_name, up.first_name
");

// Classes without grades submitted
$classes_without_grades = $conn->query("
    SELECT
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        COUNT(DISTINCT e.student_id) as enrolled_count,
        COUNT(g.student_id) as graded_count,
        CONCAT(up.first_name, ' ', up.last_name) as teacher_name
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    LEFT JOIN grades g ON cl.id = g.class_id
    LEFT JOIN users u ON cl.teacher_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE cl.branch_id = $branch_id
    GROUP BY cl.id, cl.section_name, s.subject_code, s.subject_title, up.first_name, up.last_name
    HAVING graded_count = 0 AND enrolled_count > 0
    ORDER BY enrolled_count DESC
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-eye"></i> Monitoring & Compliance Dashboard
            </h4>
            <div class="d-flex gap-2">
                <input type="date" id="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                <input type="date" id="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                <button class="btn btn-sm text-white" style="background-color: #800000;" onclick="updateMonitoring()">
                    <i class="bi bi-arrow-clockwise"></i> Update
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Alert Summary -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Issues Requiring Attention</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Low Attendance Classes:</strong> <?php echo $low_attendance_classes->num_rows; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Students with Poor Attendance:</strong> <?php echo $poor_attendance_students->num_rows; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Failing/At-Risk Students:</strong> <?php echo $failing_students->num_rows; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Classes Without Grades:</strong> <?php echo $classes_without_grades->num_rows; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Attendance Classes -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #dc3545; color: white;">
                        <h5 class="mb-0"><i class="bi bi-calendar-x"></i> Classes with Low Attendance (< 70%)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Enrolled</th>
                                        <th>Present</th>
                                        <th>Attendance Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($class = $low_attendance_classes->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['section_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo number_format($class['total_enrolled'] ?? 0); ?></td>
                                        <td><?php echo number_format($class['total_present'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo number_format($class['attendance_rate'] ?? 0, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="viewAttendanceDetails(<?php echo $class['id']; ?>)">
                                                <i class="bi bi-eye"></i> Details
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="sendReminder(<?php echo $class['id']; ?>, 'attendance')">
                                                <i class="bi bi-envelope"></i> Remind
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($low_attendance_classes->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-success">
                                            <i class="bi bi-check-circle"></i> All classes have good attendance rates
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Failing Students -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #fd7e14; color: white;">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Students Requiring Academic Attention</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $failing_students->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['subject_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['teacher_name'] ?? 'Not Assigned'); ?></td>
                                        <td>
                                            <?php if ($student['final_grade']): ?>
                                                <span class="badge bg-danger"><?php echo number_format($student['final_grade'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Graded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['remarks'] ?? 'N/A'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewStudentDetails(<?php echo $student['student_id']; ?>)">
                                                <i class="bi bi-eye"></i> Details
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="sendReminder(<?php echo $student['student_id']; ?>, 'academic')">
                                                <i class="bi bi-envelope"></i> Notify
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($failing_students->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-success">
                                            <i class="bi bi-check-circle"></i> No students currently failing
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes Without Grades -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #6f42c1; color: white;">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-x"></i> Classes Without Submitted Grades</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Enrolled Students</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($class = $classes_without_grades->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['section_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo number_format($class['enrolled_count']); ?></td>
                                        <td>
                                            <span class="badge bg-danger">Grades Not Submitted</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="sendReminder(<?php echo $class['id']; ?>, 'grades')">
                                                <i class="bi bi-envelope"></i> Remind Teacher
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="escalateIssue(<?php echo $class['id']; ?>, 'missing_grades')">
                                                <i class="bi bi-arrow-up-circle"></i> Escalate
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($classes_without_grades->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-success">
                                            <i class="bi bi-check-circle"></i> All classes have submitted grades
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Poor Attendance Students -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header" style="background-color: #20c997; color: white;">
                        <h5 class="mb-0"><i class="bi bi-person-x"></i> Students with Poor Attendance (< 70%)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Student</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Sessions</th>
                                        <th>Present</th>
                                        <th>Attendance Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $poor_attendance_students->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['subject_code'] ?? 'N/A'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['subject_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo number_format($student['total_sessions'] ?? 0); ?></td>
                                        <td><?php echo number_format($student['present_count'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo number_format($student['attendance_rate'] ?? 0, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewStudentAttendance(<?php echo $student['student_id']; ?>)">
                                                <i class="bi bi-eye"></i> Details
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="sendReminder(<?php echo $student['student_id']; ?>, 'attendance')">
                                                <i class="bi bi-envelope"></i> Notify
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($poor_attendance_students->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-success">
                                            <i class="bi bi-check-circle"></i> All students have good attendance
                                        </td>
                                    </tr>
                                    <?php endif; ?>
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
function updateMonitoring() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    if (startDate && endDate) {
        window.location.href = `monitoring.php?start_date=${startDate}&end_date=${endDate}`;
    }
}

function viewAttendanceDetails(classId) {
    // Redirect to attendance details or open modal
    window.location.href = `attendance_details.php?class_id=${classId}`;
}

function viewStudentDetails(studentId) {
    // Redirect to student details
    window.location.href = `students.php?view=${studentId}`;
}

function viewStudentAttendance(studentId) {
    // Redirect to student attendance details
    window.location.href = `student_attendance.php?student_id=${studentId}`;
}

function sendReminder(id, type) {
    if (confirm('Send reminder notification?')) {
        fetch('process/send_reminder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, type: type })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => showAlert('An error occurred', 'danger'));
    }
}

function escalateIssue(id, issueType) {
    if (confirm('Escalate this issue to School Administration?')) {
        fetch('process/escalate_issue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, issue_type: issueType })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message, 'success');
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