<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Grade Management";
$teacher_id = $_SESSION['user_id'];

// Fetch teacher's classes
$classes = $conn->query("
    SELECT 
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title,
        st.track_name,
        st.written_work_weight,
        st.performance_task_weight,
        st.quarterly_exam_weight,
        COUNT(DISTINCT e.student_id) as student_count
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    LEFT JOIN shs_tracks st ON cl.shs_track_id = st.id
    LEFT JOIN enrollments e ON cl.id = e.class_id AND e.status = 'approved'
    WHERE cl.teacher_id = $teacher_id
    GROUP BY cl.id
    ORDER BY s.subject_code
");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/minimal.css">

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="minimal-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1" style="color: var(--navy); font-weight: 600;">Grade Management</h4>
                    <small class="text-muted">Digital Gradebook with Excel Integration</small>
                </div>
                <a href="dashboard.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Class Selection -->
        <div class="minimal-card">
            <h5 class="section-title">Select Class</h5>
            <div class="row">
                <?php while ($class = $classes->fetch_assoc()): ?>
                <div class="col-md-6 mb-3">
                   <div class="card h-100" style="border-left: 4px solid var(--maroon);">
    <div class="card-body">
        <h6 class="card-title" style="color: var(--navy);">
            <?php echo htmlspecialchars($class['subject_code'] ?: 'N/A'); ?> - <?php echo htmlspecialchars($class['section_name'] ?: 'N/A'); ?>
        </h6>
        <p class="card-text mb-2">
            <small class="text-muted"><?php echo htmlspecialchars($class['subject_title'] ?: 'N/A'); ?></small>
        </p>
                            
                            <?php if ($class['track_name']): ?>
                            <div class="alert alert-info py-2 mb-2">
                                <strong>Track:</strong> <?php echo htmlspecialchars($class['track_name']); ?><br>
                                <small>
                                    WW: <?php echo $class['written_work_weight']; ?>% | 
                                    PT: <?php echo $class['performance_task_weight']; ?>% | 
                                    QE: <?php echo $class['quarterly_exam_weight']; ?>%
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <span class="badge bg-info"><?php echo $class['student_count']; ?> Students</span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="gradebook.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary-minimal">
                                    <i class="bi bi-journal-text"></i> Open Gradebook
                                </a>
                                <div class="btn-group">
                                    <button class="btn btn-minimal" onclick="importExcel(<?php echo $class['id']; ?>)">
                                        <i class="bi bi-upload"></i> Import Excel
                                    </button>
                                    <button class="btn btn-minimal" onclick="exportExcel(<?php echo $class['id']; ?>)">
                                        <i class="bi bi-download"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Import Grades Modal -->
<div class="modal fade" id="importGradesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--maroon); color: white;">
                <h5 class="modal-title">Import Grades from Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="importGradesForm" enctype="multipart/form-data">
                <input type="hidden" name="class_id" id="importClassId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Excel Format:</strong><br>
                        Columns: Student No | Student Name | Midterm | Final | Final Grade | Remarks<br>
                        <small>First row should be headers. Only Student No, Midterm, and Final are required.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Excel/CSV File</label>
                        <input type="file" class="form-control" name="excel_file" accept=".csv,.xls,.xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-minimal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-minimal">
                        <i class="bi bi-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function importExcel(classId) {
    document.getElementById('importClassId').value = classId;
    const modal = new bootstrap.Modal(document.getElementById('importGradesModal'));
    modal.show();
}

function exportExcel(classId) {
    window.location.href = 'process/export_grades.php?class_id=' + classId;
}

document.getElementById('importGradesForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
    
    try {
        const response = await fetch('process/import_grades.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-upload"></i> Import';
        }
    } catch (error) {
        alert('Import failed');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-upload"></i> Import';
    }
});
</script>
</body>
</html>