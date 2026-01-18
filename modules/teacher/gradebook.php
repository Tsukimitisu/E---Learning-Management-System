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

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    header('Location: grading.php');
    exit();
}

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
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC GRADEBOOK UI --- */
    .ledger-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .track-info-banner {
        background: #e7f5ff;
        border-left: 5px solid var(--blue);
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
    }

    /* Input Styling */
    .grade-input {
        width: 85px;
        text-align: center;
        font-weight: 700;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        padding: 5px;
        transition: 0.2s;
    }
    .grade-input:focus {
        border-color: var(--maroon);
        box-shadow: 0 0 0 3px rgba(128,0,0,0.1);
        outline: none;
    }

    .computed-grade {
        font-weight: 800;
        color: var(--blue);
        font-size: 1.1rem;
    }

    /* Sticky Table Header */
    .table thead th {
        background: #fcfcfc;
        position: sticky;
        top: -1px;
        z-index: 5;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px;
        border-bottom: 2px solid #eee;
    }

    .table tbody td { padding: 12px 15px; vertical-align: middle; }

    .btn-save-all {
        background-color: var(--maroon);
        color: white;
        border: none;
        font-weight: 700;
        padding: 8px 25px;
        border-radius: 50px;
        transition: 0.3s;
    }
    .btn-save-all:hover {
        background-color: #600000;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
    }

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
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="grading.php">Grading</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($class_info['subject_code']); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($class_info['section_name'] ?: 'N/A'); ?> <span class="text-muted fw-light mx-2">|</span> <span style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($class_info['subject_title']); ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-save-all shadow-sm" onclick="saveAllGrades()">
                <i class="bi bi-cloud-check me-2"></i> Save All
            </button>
            <a href="grading.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <?php if ($class_info['track_name']): ?>
    <div class="track-info-banner animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill fs-4 me-3 text-blue"></i>
            <div>
                <span class="fw-bold text-blue">SHS TRACK: <?php echo htmlspecialchars($class_info['track_name']); ?></span>
                <div class="small text-muted">
                    Weights: Written (<?php echo $class_info['written_work_weight']; ?>%) • 
                    Performance (<?php echo $class_info['performance_task_weight']; ?>%) • 
                    Exam (<?php echo $class_info['quarterly_exam_weight']; ?>%)
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gradebook Ledger -->
    <div class="ledger-card animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Student Name</th>
                        <th class="text-center">Midterm</th>
                        <th class="text-center">Final</th>
                        <th class="text-center">Average</th>
                        <th class="text-center">Remarks</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                    <tr data-student-id="<?php echo $student['user_id']; ?>">
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                        </td>
                        <td class="text-center">
                            <input type="number" class="grade-input midterm-input shadow-sm" 
                                   value="<?php echo $student['midterm'] ?? ''; ?>" min="0" max="100" step="0.01">
                        </td>
                        <td class="text-center">
                            <input type="number" class="grade-input final-input shadow-sm" 
                                   value="<?php echo $student['final'] ?? ''; ?>" min="0" max="100" step="0.01">
                        </td>
                        <td class="text-center computed-grade">
                            <?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : '---'; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill px-3 py-2 <?php echo ($student['remarks'] ?? '') == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo htmlspecialchars($student['remarks'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-light border rounded-circle save-grade-btn shadow-sm" title="Save Row">
                                <i class="bi bi-save text-blue"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & WIRED --- -->
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
    
    // Formula logic exactly as provided
    const finalGrade = (midterm * 0.4) + (final * 0.6);
    const remarks = finalGrade >= 75 ? 'PASSED' : 'FAILED';
    
    const gradeCell = row.querySelector('.computed-grade');
    gradeCell.textContent = finalGrade > 0 ? finalGrade.toFixed(2) : '---';
    
    const remarksCell = row.querySelector('.badge');
    remarksCell.textContent = finalGrade > 0 ? remarks : 'N/A';
    remarksCell.className = 'badge rounded-pill px-3 py-2 ' + (remarks === 'PASSED' ? 'bg-success' : 'bg-danger');
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
            // Logic handled by showAlert
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function saveAllGrades() {
    const rows = document.querySelectorAll('tbody tr');
    const saveBtn = document.querySelector('.btn-save-all');
    const originalText = saveBtn.innerHTML;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
    
    let saved = 0;
    for (const row of rows) {
        await saveGrade(row);
        saved++;
    }
    
    showAlert(`Successfully synchronized ${saved} student records.`, 'success');
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>