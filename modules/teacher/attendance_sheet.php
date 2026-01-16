<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];
$attendance_date = $_GET['date'] ?? date('Y-m-d');

if ($class_id == 0) {
    header('Location: attendance.php');
    exit();
}

// Verify class
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    header('Location: attendance.php');
    exit();
}

// Get class info
$class_info = $conn->query("
    SELECT cl.*, s.subject_code, s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.id = $class_id
")->fetch_assoc();

// Get students with attendance
$students = $conn->query("
    SELECT 
        s.user_id,
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        a.status,
        a.time_in,
        a.time_out,
        a.remarks
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN attendance a ON s.user_id = a.student_id 
        AND a.class_id = $class_id 
        AND a.attendance_date = '$attendance_date'
    WHERE e.class_id = $class_id AND e.status = 'approved'
    ORDER BY up.last_name, up.first_name
");

$page_title = "Attendance Sheet";
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">
                        <?php echo htmlspecialchars($class_info['subject_code'] ?: 'N/A'); ?> - Attendance
                    </h4>
                    <small class="text-muted"><?php echo htmlspecialchars($class_info['subject_title'] ?: ''); ?></small>
                </div>
                <div>
                    <input type="date" class="form-control" id="attendanceDate" value="<?php echo $attendance_date; ?>" onchange="changeDate(this.value)">
                </div>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Attendance Table -->
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title mb-0">Student List - <?php echo date('F d, Y', strtotime($attendance_date)); ?></h5>
                <div>
                    <button class="btn btn-minimal me-2" onclick="markAll('present')">
                        <i class="bi bi-check-all"></i> All Present
                    </button>
                    <button class="btn btn-primary-minimal" onclick="saveAttendance()">
                        <i class="bi bi-save"></i> Save Attendance
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead style="background-color: var(--navy); color: white;">
                        <tr>
                            <th>No.</th>
                            <th>Student No.</th>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($student = $students->fetch_assoc()): 
                        ?>
                        <tr data-student-id="<?php echo $student['user_id']; ?>">
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($student['student_no']); ?></td>
                            <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                            <td>
                                <select class="form-select form-select-sm status-select">
                                    <option value="present" <?php echo ($student['status'] ?? '') == 'present' ? 'selected' : ''; ?>>Present</option>
                                    <option value="absent" <?php echo ($student['status'] ?? 'absent') == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                    <option value="late" <?php echo ($student['status'] ?? '') == 'late' ? 'selected' : ''; ?>>Late</option>
                                    <option value="excused" <?php echo ($student['status'] ?? '') == 'excused' ? 'selected' : ''; ?>>Excused</option>
                                </select>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm time-in" value="<?php echo $student['time_in'] ?? ''; ?>">
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm time-out" value="<?php echo $student['time_out'] ?? ''; ?>">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm remarks-input" value="<?php echo htmlspecialchars($student['remarks'] ?? ''); ?>" placeholder="Optional">
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CLASS_ID = <?php echo $class_id; ?>;
const ATTENDANCE_DATE = '<?php echo $attendance_date; ?>';

function changeDate(date) {
    window.location.href = `attendance_sheet.php?class_id=${CLASS_ID}&date=${date}`;
}

function markAll(status) {
    document.querySelectorAll('.status-select').forEach(select => {
        select.value = status;
    });
}

async function saveAttendance() {
    const rows = document.querySelectorAll('tbody tr');
    const attendanceData = [];
    
    rows.forEach(row => {
        attendanceData.push({
            student_id: row.dataset.studentId,
            status: row.querySelector('.status-select').value,
            time_in: row.querySelector('.time-in').value,
            time_out: row.querySelector('.time-out').value,
            remarks: row.querySelector('.remarks-input').value
        });
    });
    
    try {
        const response = await fetch('process/save_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                class_id: CLASS_ID,
                attendance_date: ATTENDANCE_DATE,
                attendance: attendanceData
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Failed to save attendance', 'danger');
    }
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-minimal alert-dismissible fade show" role="alert">
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