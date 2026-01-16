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

// Verify this class belongs to this teacher
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    header('Location: my_classes.php');
    exit();
}

// Get class info
$class_query = "
    SELECT 
        cl.id,
        cl.room,
        c.course_code,
        c.title as course_title,
        b.name as branch_name
    FROM classes cl
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN branches b ON c.branch_id = b.id
    WHERE cl.id = ?
";
$stmt = $conn->prepare($class_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class_info = $stmt->get_result()->fetch_assoc();

// Get enrolled students with grades
$students_query = "
    SELECT 
        s.user_id,
        s.student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        g.id as grade_id,
        g.midterm,
        g.final,
        g.final_grade,
        g.remarks
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

// Get learning materials
$materials_query = "
    SELECT id, file_path, uploaded_at
    FROM learning_materials
    WHERE class_id = ?
    ORDER BY uploaded_at DESC
";
$stmt = $conn->prepare($materials_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$materials_result = $stmt->get_result();

$page_title = "Classroom - " . $class_info['course_code'];
include '../../includes/header.php';
?>

<style>
.grade-input {
    width: 80px;
    text-align: center;
}
.grade-input:focus {
    border-color: #800000;
    box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
}
.final-grade-display {
    font-weight: bold;
    color: #003366;
    font-size: 1.1em;
}
.save-indicator {
    display: none;
    color: green;
    font-size: 0.85em;
}
</style>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($class_info['course_code']); ?> - <?php echo htmlspecialchars($class_info['room']); ?>
                </h4>
                <small class="text-muted"><?php echo htmlspecialchars($class_info['course_title']); ?></small>
            </div>
            <a href="my_classes.php" class="btn btn-sm btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Classes
            </a>
        </div>

        <div id="alertContainer"></div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="classroomTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button">
                    <i class="bi bi-file-earmark-pdf"></i> Learning Materials
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="grades-tab" data-bs-toggle="tab" data-bs-target="#grades" type="button">
                    <i class="bi bi-bar-chart"></i> Student Grades
                </button>
            </li>
        </ul>

        <div class="tab-content" id="classroomTabsContent">
            <!-- MATERIALS TAB -->
            <div class="tab-pane fade show active" id="materials" role="tabpanel">
                <div class="card shadow-sm mt-3">
                    <div class="card-header" style="background-color: #800000; color: white;">
                        <i class="bi bi-cloud-upload"></i> Upload Learning Material
                    </div>
                    <div class="card-body">
                        <form id="uploadMaterialForm" enctype="multipart/form-data">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="file" class="form-control" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                                    <small class="text-muted">Accepted: PDF, DOC, DOCX, PPT, PPTX (Max 10MB)</small>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-files"></i> Uploaded Materials
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Uploaded On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($material = $materials_result->fetch_assoc()): 
                                        $filename = basename($material['file_path']);
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                                            <?php echo htmlspecialchars($filename); ?>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($material['uploaded_at'])); ?></td>
                                        <td>
                                            <a href="../../uploads/materials/<?php echo htmlspecialchars($filename); ?>" 
                                               target="_blank" class="btn btn-sm btn-primary">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <button class="btn btn-sm btn-danger" onclick="deleteMaterial(<?php echo $material['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($materials_result->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No materials uploaded yet</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRADES TAB -->
            <div class="tab-pane fade" id="grades" role="tabpanel">
                <div class="card shadow-sm mt-3">
                    <div class="card-header" style="background-color: #003366; color: white;">
                        <i class="bi bi-bar-chart"></i> Student Grades
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead style="background-color: #f8f9fa;">
                                    <tr>
                                        <th>Student No.</th>
                                        <th>Student Name</th>
                                        <th>Midterm</th>
                                        <th>Final</th>
                                        <th>Final Grade</th>
                                        <th>Remarks</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $students_result->data_seek(0);
                                    while ($student = $students_result->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_no']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control grade-input midterm-input" 
                                                   data-student-id="<?php echo $student['user_id']; ?>"
                                                   data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>"
                                                   value="<?php echo $student['midterm'] ?? ''; ?>"
                                                   min="0" 
                                                   max="100" 
                                                   step="0.01"
                                                   placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control grade-input final-input" 
                                                   data-student-id="<?php echo $student['user_id']; ?>"
                                                   data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>"
                                                   value="<?php echo $student['final'] ?? ''; ?>"
                                                   min="0" 
                                                   max="100" 
                                                   step="0.01"
                                                   placeholder="0.00">
                                        </td>
                                        <td>
                                            <span class="final-grade-display" data-student-id="<?php echo $student['user_id']; ?>">
                                                <?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : '-'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="remarks-display" data-student-id="<?php echo $student['user_id']; ?>">
                                                <?php echo htmlspecialchars($student['remarks'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="save-indicator" data-student-id="<?php echo $student['user_id']; ?>">
                                                <i class="bi bi-check-circle"></i> Saved
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i> <strong>Grading System:</strong> 
                            Final Grade = (Midterm × 0.4) + (Final × 0.6) | 
                            Passing: ≥75 | Failed: <75
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CLASS_ID = <?php echo $class_id; ?>;

// ============================================================================
// UPLOAD MATERIAL HANDLER
// ============================================================================
document.getElementById('uploadMaterialForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading...';
    
    try {
        const response = await fetch('api/upload_material.php', {
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
            submitBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
        }
    } catch (error) {
        showAlert('Upload failed. Please try again.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
    }
});

// ============================================================================
// AJAX GRADING SYSTEM
// ============================================================================
document.querySelectorAll('.midterm-input, .final-input').forEach(input => {
    input.addEventListener('blur', async function() {
        const studentId = this.getAttribute('data-student-id');
        const gradeId = this.getAttribute('data-grade-id');
        
        // Get both midterm and final values for this student
        const midtermInput = document.querySelector(`.midterm-input[data-student-id="${studentId}"]`);
        const finalInput = document.querySelector(`.final-input[data-student-id="${studentId}"]`);
        
        const midterm = parseFloat(midtermInput.value) || 0;
        const final = parseFloat(finalInput.value) || 0;
        
        // Calculate final grade (Midterm 40% + Final 60%)
        const finalGrade = (midterm * 0.4) + (final * 0.6);
        
        // Determine remarks
        let remarks = '-';
        if (finalGrade > 0) {
            remarks = finalGrade >= 75 ? 'PASSED' : 'FAILED';
        }
        
        // Update display
        const finalGradeDisplay = document.querySelector(`.final-grade-display[data-student-id="${studentId}"]`);
        const remarksDisplay = document.querySelector(`.remarks-display[data-student-id="${studentId}"]`);
        const saveIndicator = document.querySelector(`.save-indicator[data-student-id="${studentId}"]`);
        
        if (finalGrade > 0) {
            finalGradeDisplay.textContent = finalGrade.toFixed(2);
            remarksDisplay.textContent = remarks;
            remarksDisplay.className = 'remarks-display badge ' + (remarks === 'PASSED' ? 'bg-success' : 'bg-danger');
        }
        
        // Save to database via AJAX
        try {
            const formData = new FormData();
            formData.append('student_id', studentId);
            formData.append('class_id', CLASS_ID);
            formData.append('midterm', midterm);
            formData.append('final', final);
            formData.append('final_grade', finalGrade.toFixed(2));
            formData.append('remarks', remarks);
            formData.append('grade_id', gradeId);
            
            const response = await fetch('api/update_grade.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Show saved indicator
                saveIndicator.style.display = 'inline';
                setTimeout(() => {
                    saveIndicator.style.display = 'none';
                }, 2000);
                
                // Update grade_id for future updates
                midtermInput.setAttribute('data-grade-id', data.grade_id);
                finalInput.setAttribute('data-grade-id', data.grade_id);
            } else {
                showAlert(data.message, 'danger');
            }
        } catch (error) {
            showAlert('Failed to save grade. Please try again.', 'danger');
        }
    });
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

function deleteMaterial(materialId) {
    if (confirm('Are you sure you want to delete this material?')) {
        // Implement delete functionality
        alert('Delete functionality will be implemented');
    }
}
</script>
</body>
</html>