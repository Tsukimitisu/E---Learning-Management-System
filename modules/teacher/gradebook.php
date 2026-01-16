<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0) {
    header('Location: grading.php');
    exit();
}

// Verify class belongs to teacher
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    header('Location: grading.php');
    exit();
}

// Get class info with track
$class_info = $conn->query("
    SELECT 
        cl.*,
        s.subject_code,
        s.subject_title,
        st.track_name,
        st.written_work_weight,
        st.performance_task_weight,
        st.quarterly_exam_weight
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN shs_tracks st ON cl.shs_track_id = st.id
    WHERE cl.id = $class_id
")->fetch_assoc();

// Get enrolled students with grades
$students = $conn->query("
    SELECT 
        s.user_id,
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        g.midterm,
        g.final,
        g.final_grade,
        g.remarks
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN grades g ON s.user_id = g.student_id AND g.class_id = $class_id
    WHERE e.class_id = $class_id AND e.status = 'approved'
    ORDER BY up.last_name, up.first_name
");

$page_title = "Gradebook - " . $class_info['subject_code'];
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">
<style>
.grade-input {
    width: 80px;
    text-align: center;
    padding: 5px;
}
.computed-grade {
    font-weight: bold;
    color: var(--navy);
}
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
               <div>
    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">
        <?php echo htmlspecialchars($class_info['subject_code'] ?: 'N/A'); ?> - <?php echo htmlspecialchars($class_info['section_name'] ?: 'N/A'); ?>
    </h4>
    <small class="text-muted"><?php echo htmlspecialchars($class_info['subject_title'] ?: ''); ?></small>
</div>
                <div>
                    <button class="btn btn-minimal me-2" onclick="location.href='grading.php'">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary-minimal" onclick="saveAllGrades()">
                        <i class="bi bi-save"></i> Save All
                    </button>
                </div>
            </div>
        </div>

        <?php if ($class_info['track_name']): ?>
        <div class="alert alert-info alert-minimal" style="border-left-color: #17a2b8;">
            <strong>SHS Track:</strong> <?php echo htmlspecialchars($class_info['track_name']); ?><br>
            <small>
                Written Work: <?php echo $class_info['written_work_weight']; ?>% | 
                Performance Task: <?php echo $class_info['performance_task_weight']; ?>% | 
                Quarterly Exam: <?php echo $class_info['quarterly_exam_weight']; ?>%
            </small>
        </div>
        <?php endif; ?>

        <div id="alertContainer"></div>

        <!-- Gradebook Table -->
        <div class="minimal-card">
            <h5 class="section-title">Student Grades</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead style="background-color: var(--navy); color: white;">
                        <tr>
                            <th>Student No.</th>
                            <th>Student Name</th>
                            <th>Midterm</th>
                            <th>Final</th>
                            <th>Final Grade</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students->fetch_assoc()): ?>
                        <tr data-student-id="<?php echo $student['user_id']; ?>">
                            <td><?php echo htmlspecialchars($student['student_no']); ?></td>
                            <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                            <td>
                                <input type="number" 
                                       class="form-control form-control-sm grade-input midterm-input" 
                                       value="<?php echo $student['midterm'] ?? ''; ?>"
                                       min="0" 
                                       max="100" 
                                       step="0.01">
                            </td>
                            <td>
                                <input type="number" 
                                       class="form-control form-control-sm grade-input final-input" 
                                       value="<?php echo $student['final'] ?? ''; ?>"
                                       min="0" 
                                       max="100" 
                                       step="0.01">
                            </td>
                            <td class="computed-grade">
                                <?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : '-'; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo ($student['remarks'] ?? '') == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo htmlspecialchars($student['remarks'] ?? '-'); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary-minimal save-grade-btn">
                                    <i class="bi bi-save"></i>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CLASS_ID = <?php echo $class_id; ?>;

// Auto-calculate on input change
document.querySelectorAll('.midterm-input, .final-input').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        calculateFinalGrade(row);
    });
});

// Save individual grade
document.querySelectorAll('.save-grade-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        saveGrade(row);
    });
});

function calculateFinalGrade(row) {
    const midterm = parseFloat(row.querySelector('.midterm-input').value) || 0;
    const final = parseFloat(row.querySelector('.final-input').value) || 0;
    
    // Formula: Midterm 40% + Final 60%
    const finalGrade = (midterm * 0.4) + (final * 0.6);
    const remarks = finalGrade >= 75 ? 'PASSED' : 'FAILED';
    
    const gradeCell = row.querySelector('.computed-grade');
    gradeCell.textContent = finalGrade > 0 ? finalGrade.toFixed(2) : '-';
    
    const remarksCell = row.querySelector('.badge');
    remarksCell.textContent = finalGrade > 0 ? remarks : '-';
    remarksCell.className = 'badge ' + (remarks === 'PASSED' ? 'bg-success' : 'bg-danger');
}

async function saveGrade(row) {
    const studentId = row.dataset.studentId;
    const midterm = parseFloat(row.querySelector('.midterm-input').value) || 0;
    const final = parseFloat(row.querySelector('.final-input').value) || 0;
    const finalGrade = (midterm * 0.4) + (final * 0.6);
    const remarks = finalGrade >= 75 ? 'PASSED' : 'FAILED';
    
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('class_id', CLASS_ID);
    formData.append('midterm', midterm);
    formData.append('final', final);
    formData.append('final_grade', finalGrade.toFixed(2));
    formData.append('remarks', remarks);
    
    try {
        const response = await fetch('../teacher/api/update_grade.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showAlert('Grade saved successfully', 'success');
} else {
showAlert(data.message, 'danger');
}
} catch (error) {
showAlert('Failed to save grade', 'danger');
}
}async function saveAllGrades() {
const rows = document.querySelectorAll('tbody tr');
let saved = 0;for (const row of rows) {
    await saveGrade(row);
    saved++;
}showAlert(`All ${saved} grades saved successfully!`, 'success');
}function showAlert(message, type) {
const alertHtml =         <div class="alert alert-${type} alert-minimal alert-dismissible fade show" role="alert">             ${message}             <button type="button" class="btn-close" data-bs-dismiss="alert"></button>         </div>    ;
document.getElementById('alertContainer').innerHTML = alertHtml;
window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>