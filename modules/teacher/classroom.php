<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0) {
    header('Location: my_classes.php');
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
    header('Location: my_classes.php');
    exit();
}

$class_query = "
    SELECT cl.id, cl.room, c.course_code, c.title as course_title, b.name as branch_name
    FROM classes cl
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN branches b ON c.branch_id = b.id
    WHERE cl.id = ?
";
$stmt = $conn->prepare($class_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class_info = $stmt->get_result()->fetch_assoc();

$students_query = "
    SELECT s.user_id, s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name,
           g.id as grade_id, g.midterm, g.final, g.final_grade, g.remarks
    FROM enrollments e
    INNER JOIN students s ON e.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN grades g ON s.user_id = g.student_id AND g.class_id = ?
    WHERE e.class_id = ? AND e.status = 'approved'
    ORDER BY up.last_name, up.first_name
";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("ii", $class_id, $class_id);
$stmt->execute();
$students_result = $stmt->get_result();

$materials_query = "
    SELECT id, file_path, uploaded_at FROM learning_materials WHERE class_id = ? ORDER BY uploaded_at DESC
";
$stmt = $conn->prepare($materials_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$materials_result = $stmt->get_result();

$page_title = "Classroom - " . $class_info['course_code'];
include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .classroom-banner {
        background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);
        border-radius: 15px; padding: 25px; color: white; margin-bottom: 25px;
        box-shadow: 0 10px 20px rgba(0, 51, 102, 0.1);
    }
    .nav-pills-modern .nav-link {
        color: #666; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
        padding: 12px 25px; border-radius: 10px; transition: 0.3s;
    }
    .nav-pills-modern .nav-link.active {
        background-color: var(--maroon); color: white; box-shadow: 0 4px 12px rgba(128,0,0,0.2);
    }
    .grade-input {
        width: 85px; text-align: center; font-weight: 700; border-radius: 6px;
        border: 1px solid #dee2e6; padding: 5px; transition: 0.2s;
    }
    .grade-input:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(128,0,0,0.1); outline: none; }
    .final-grade-display { font-weight: 800; color: var(--blue); font-size: 1.1rem; }
    .save-indicator { display: none; color: #28a745; font-size: 0.75rem; font-weight: 700; }
    .material-card-item {
        background: white; border: 1px solid #eee; border-radius: 12px;
        padding: 15px; margin-bottom: 10px; transition: 0.3s;
    }
    .material-card-item:hover { transform: translateX(8px); border-left: 5px solid var(--maroon); }

    /* Breadcrumbs */
    .breadcrumb-modern { background: transparent; padding: 0; margin-bottom: 5px; }
    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; line-height: 1; vertical-align: middle; }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="my_classes.php">My Classes</a></li>
                    <li class="breadcrumb-item"><a href="class_sections.php?subject=<?php echo urlencode($class_info['course_code']); ?>"><?php echo htmlspecialchars($class_info['course_code']); ?></a></li>
                    <li class="breadcrumb-item active">Classroom</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">Digital Classroom</h4>
        </div>

        <a href="class_sections.php?subject=<?php echo urlencode($class_info['course_code']); ?>" class="btn btn-outline-secondary btn-sm px-4 rounded-pill shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Sections
        </a>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <div class="classroom-banner animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="badge bg-white text-maroon mb-2 px-3 py-2 fw-bold">ROOM: <?php echo htmlspecialchars($class_info['room']); ?></span>
                <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($class_info['course_title']); ?></h2>
                <p class="mb-0 opacity-75 small"><i class="bi bi-building me-2"></i>Campus: <?php echo htmlspecialchars($class_info['branch_name']); ?></p>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="bi bi-door-open display-2 opacity-25"></i>
            </div>
        </div>
    </div>

    <div id="alertContainer"></div>

    <ul class="nav nav-pills nav-pills-modern mb-4 animate__animated animate__fadeInUp" id="classroomTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="materials-tab" data-bs-toggle="pill" data-bs-target="#materials" type="button">
                <i class="bi bi-folder-fill me-2"></i> Learning Materials
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="grades-tab" data-bs-toggle="pill" data-bs-target="#grades" type="button">
                <i class="bi bi-journal-check me-2"></i> Gradebook
            </button>
        </li>
    </ul>

    <div class="tab-content" id="classroomTabsContent">
        <!-- MATERIALS TAB -->
        <div class="tab-pane fade show active" id="materials" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-bottom p-3">
                            <h6 class="fw-bold mb-0 text-maroon"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Upload File</h6>
                        </div>
                        <div class="card-body">
                            <form id="uploadMaterialForm">
                                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">SELECT DOCUMENT</label>
                                    <input type="file" class="form-control border-light shadow-sm" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                                    <i class="bi bi-upload me-2"></i> Publish Material
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-bottom p-3">
                            <h6 class="fw-bold mb-0 text-blue"><i class="bi bi-files me-2"></i>Class Library</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($materials_result->num_rows > 0): ?>
                                <?php while ($material = $materials_result->fetch_assoc()): 
                                    $filename = basename($material['file_path']);
                                ?>
                                <div class="material-card-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-3 me-3"></i>
                                        <div>
                                            <div class="fw-bold text-dark text-break" style="font-size:0.9rem;"><?php echo htmlspecialchars($filename); ?></div>
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($material['uploaded_at'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="../../uploads/materials/<?php echo htmlspecialchars($filename); ?>" target="_blank" class="btn btn-light btn-sm border shadow-sm"><i class="bi bi-download"></i></a>
                                        <button class="btn btn-light btn-sm border shadow-sm text-danger" onclick="deleteMaterial(<?php echo $material['id']; ?>)"><i class="bi bi-trash3"></i></button>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted"><p>No documents shared yet.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRADES TAB -->
        <div class="tab-pane fade" id="grades" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Student Info</th>
                                <th class="text-center">Midterm (40%)</th>
                                <th class="text-center">Final (60%)</th>
                                <th class="text-center">Final Grade</th>
                                <th class="text-center">Remarks</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $students_result->data_seek(0);
                            while ($student = $students_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                </td>
                                <td class="text-center">
                                    <input type="number" class="grade-input midterm-input" data-student-id="<?php echo $student['user_id']; ?>" data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>" value="<?php echo $student['midterm'] ?? ''; ?>">
                                </td>
                                <td class="text-center">
                                    <input type="number" class="grade-input final-input" data-student-id="<?php echo $student['user_id']; ?>" data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>" value="<?php echo $student['final'] ?? ''; ?>">
                                </td>
                                <td class="text-center">
                                    <span class="final-grade-display" data-student-id="<?php echo $student['user_id']; ?>"><?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : '---'; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="remarks-display badge <?php echo ($student['remarks'] == 'PASSED') ? 'bg-success' : (($student['remarks'] == 'FAILED') ? 'bg-danger' : 'bg-light text-dark'); ?>" data-student-id="<?php echo $student['user_id']; ?>"><?php echo htmlspecialchars($student['remarks'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="save-indicator" data-student-id="<?php echo $student['user_id']; ?>"><i class="bi bi-check-circle-fill"></i> Saved</span>
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

<?php include '../../includes/footer.php'; ?>

<script>
const CLASS_ID = <?php echo $class_id; ?>;

// AJAX Logic 
document.getElementById('uploadMaterialForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Syncing...';
    try {
        const response = await fetch('api/upload_material.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
        else { showAlert(data.message, 'danger'); submitBtn.disabled = false; }
    } catch (error) { showAlert('Network error occurred.', 'danger'); submitBtn.disabled = false; }
});

document.querySelectorAll('.midterm-input, .final-input').forEach(input => {
    input.addEventListener('blur', async function() {
        const studentId = this.getAttribute('data-student-id');
        const gradeId = this.getAttribute('data-grade-id');
        const midterm = parseFloat(document.querySelector(`.midterm-input[data-student-id="${studentId}"]`).value) || 0;
        const final = parseFloat(document.querySelector(`.final-input[data-student-id="${studentId}"]`).value) || 0;
        const finalGrade = (midterm * 0.4) + (final * 0.6);
        let remarks = (finalGrade >= 75) ? 'PASSED' : 'FAILED';
        
        const finalGradeDisplay = document.querySelector(`.final-grade-display[data-student-id="${studentId}"]`);
        const remarksDisplay = document.querySelector(`.remarks-display[data-student-id="${studentId}"]`);
        const saveIndicator = document.querySelector(`.save-indicator[data-student-id="${studentId}"]`);
        
        finalGradeDisplay.textContent = finalGrade.toFixed(2);
        remarksDisplay.textContent = remarks;
        remarksDisplay.className = `remarks-display badge ${remarks === 'PASSED' ? 'bg-success' : 'bg-danger'}`;

        try {
            const formData = new FormData();
            formData.append('student_id', studentId);
            formData.append('class_id', CLASS_ID);
            formData.append('midterm', midterm);
            formData.append('final', final);
            formData.append('final_grade', finalGrade.toFixed(2));
            formData.append('remarks', remarks);
            formData.append('grade_id', gradeId);
            const response = await fetch('api/update_grade.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.status === 'success') {
                saveIndicator.style.display = 'inline';
                setTimeout(() => { saveIndicator.style.display = 'none'; }, 2000);
            }
        } catch (error) { console.error('Save failed'); }
    });
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
}
</script>
</body>
</html>