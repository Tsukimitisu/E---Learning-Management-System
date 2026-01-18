<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];
$attendance_date = $_GET['date'] ?? date('Y-m-d');

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0) {
    header('Location: attendance.php');
    exit();
}

/** 
 * BACKEND LOGIC - Using new section/subject structure
 */
// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0) {
    header('Location: attendance.php');
    exit();
}

// Get section info
$section_query = $conn->prepare("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, CONCAT('Grade ', sgl.grade_level)) as year_level_name
    FROM sections s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    WHERE s.id = ?
");
$section_query->bind_param("i", $section_id);
$section_query->execute();
$section_info = $section_query->get_result()->fetch_assoc();

// Get subject info
$subject_query = $conn->prepare("SELECT * FROM curriculum_subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_info = $subject_query->get_result()->fetch_assoc();

// Combine for compatibility with old template
$class_info = [
    'section_name' => $section_info['section_name'],
    'subject_code' => $subject_info['subject_code'],
    'subject_title' => $subject_info['subject_title']
];

// Get students from section_students table with attendance
$students_query = $conn->prepare("
    SELECT 
        u.id as user_id, 
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no, 
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        a.status, a.time_in, a.time_out, a.remarks
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN attendance a ON u.id = a.student_id 
        AND a.section_id = ? AND a.subject_id = ?
        AND a.attendance_date = ?
    WHERE ss.section_id = ? AND ss.status = 'active'
    ORDER BY up.last_name, up.first_name
");
$students_query->bind_param("iisi", $section_id, $subject_id, $attendance_date, $section_id);
$students_query->execute();
$students = $students_query->get_result();

$page_title = "Attendance Sheet";
include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC ATTENDANCE UI --- */
    .attendance-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    /* Sticky Table Header */
    .table thead th {
        background: var(--blue);
        color: white;
        position: sticky;
        top: -1px;
        z-index: 5;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 15px;
        border: none;
    }

    .table tbody td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; }

    /* Custom Inputs */
    .status-select {
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.85rem;
        border: 1px solid #dee2e6;
        padding: 5px 10px;
    }
    .time-input {
        border-radius: 6px;
        border: 1px solid #eee;
        font-size: 0.85rem;
        padding: 4px 8px;
        width: 120px;
    }

    .btn-maroon-save {
        background-color: var(--maroon);
        color: white;
        border: none;
        font-weight: 700;
        padding: 8px 25px;
        border-radius: 50px;
        transition: 0.3s;
    }
    .btn-maroon-save:hover {
        background-color: #600000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
    }

    .date-picker-custom {
        max-width: 200px;
        border-radius: 8px;
        border: 1px solid #ddd;
        font-weight: 600;
        color: var(--blue);
    }

    /* Breadcrumbs */
    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; vertical-align: middle; }

    @media (max-width: 576px) {
        .header-fixed-part { padding: 15px; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="attendance.php">Attendance</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($class_info['subject_code'] ?: 'Class'); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($class_info['section_name'] ?: 'N/A'); ?> <span class="text-muted fw-light mx-2">|</span> <span style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($class_info['subject_title']); ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <!-- BACK BUTTON ADDED -->
            <a href="attendance.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <button class="btn btn-maroon-save shadow-sm" onclick="saveAttendance()">
                <i class="bi bi-cloud-check me-2"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Controls Row -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center bg-white p-2 rounded-3 shadow-sm border">
            <i class="bi bi-calendar-event me-2 ms-2 text-maroon"></i>
            <input type="date" class="form-control border-0 date-picker-custom" id="attendanceDate" value="<?php echo $attendance_date; ?>" onchange="changeDate(this.value)">
        </div>
        
        <div class="btn-group shadow-sm">
            <button class="btn btn-white border btn-sm fw-bold px-3" onclick="markAll('present')">
                <i class="bi bi-check-circle text-success me-1"></i> All Present
            </button>
            <button class="btn btn-white border btn-sm fw-bold px-3" onclick="markAll('absent')">
                <i class="bi bi-x-circle text-danger me-1"></i> All Absent
            </button>
        </div>
    </div>

    <!-- Attendance Ledger -->
    <div class="attendance-card animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 80px;">No.</th>
                        <th>Student Information</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Time In</th>
                        <th class="text-center">Time Out</th>
                        <th class="pe-4">Remarks / Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    while ($student = $students->fetch_assoc()): 
                    ?>
                    <tr data-student-id="<?php echo $student['user_id']; ?>">
                        <td class="ps-4 text-muted fw-bold"><?php echo $no++; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                        </td>
                        <td class="text-center">
                            <select class="form-select form-select-sm status-select shadow-sm mx-auto" style="width: 130px;">
                                <option value="present" <?php echo ($student['status'] ?? '') == 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="absent" <?php echo ($student['status'] ?? 'absent') == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="late" <?php echo ($student['status'] ?? '') == 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="excused" <?php echo ($student['status'] ?? '') == 'excused' ? 'selected' : ''; ?>>Excused</option>
                            </select>
                        </td>
                        <td class="text-center">
                            <input type="time" class="form-control form-control-sm time-input shadow-sm mx-auto time-in" value="<?php echo $student['time_in'] ?? ''; ?>">
                        </td>
                        <td class="text-center">
                            <input type="time" class="form-control form-control-sm time-input shadow-sm mx-auto time-out" value="<?php echo $student['time_out'] ?? ''; ?>">
                        </td>
                        <td class="pe-4">
                            <input type="text" class="form-control form-control-sm shadow-sm remarks-input" value="<?php echo htmlspecialchars($student['remarks'] ?? ''); ?>" placeholder="Optional note...">
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 text-center text-muted small">
        <i class="bi bi-info-circle me-1"></i> Attendance is recorded for <strong><?php echo date('l, F d, Y', strtotime($attendance_date)); ?></strong>.
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - Updated for new structure --- -->
<script>
const SECTION_ID = <?php echo $section_id; ?>;
const SUBJECT_ID = <?php echo $subject_id; ?>;
const ATTENDANCE_DATE = '<?php echo $attendance_date; ?>';

function changeDate(date) {
    window.location.href = `attendance_sheet.php?section_id=${SECTION_ID}&subject_id=${SUBJECT_ID}&date=${date}`;
}

function markAll(status) {
    document.querySelectorAll('.status-select').forEach(select => {
        select.value = status;
    });
}

async function saveAttendance() {
    const saveBtn = document.querySelector('.btn-maroon-save');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Syncing...';

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
                section_id: SECTION_ID,
                subject_id: SUBJECT_ID,
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
        showAlert('Failed to synchronize attendance data', 'danger');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>