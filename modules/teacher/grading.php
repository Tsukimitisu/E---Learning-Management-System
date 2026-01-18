<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Grade Management";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
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
include '../../includes/sidebar.php'; // Opens wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

    .header-fixed-part {
        flex: 0 0 auto;
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #eee;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px;
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC GRADING UI --- */
    .grading-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        border-top: 6px solid var(--maroon);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .grading-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(128, 0, 0, 0.1);
    }

    .weight-badge-container {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 12px;
        display: flex;
        justify-content: space-around;
        text-align: center;
        margin: 15px 0;
    }

    .weight-item small { font-size: 0.65rem; text-transform: uppercase; color: #888; font-weight: 700; display: block; }
    .weight-item span { font-weight: 800; color: var(--blue); font-size: 0.9rem; }

    .btn-action-main {
        background-color: var(--blue);
        color: white;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px;
        transition: 0.3s;
        border: none;
    }
    .btn-action-main:hover { background-color: #002244; color: white; }

    .btn-outline-custom {
        border: 1px solid #dee2e6;
        background: #fff;
        color: #555;
        font-weight: 600;
        padding: 8px;
        border-radius: 10px;
        transition: 0.3s;
        font-size: 0.85rem;
    }
    .btn-outline-custom:hover { background: #f8f9fa; color: var(--maroon); border-color: var(--maroon); }

    /* Staggered Animations */
    <?php for($i=1; $i<=10; $i++): ?>
    .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    /* Mobile Logic */
    @media (max-width: 576px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
    <div>
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-calculator-fill me-2"></i>Grade Management</h4>
        <p class="text-muted small mb-0">Record and synchronize academic marks</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    <div id="alertContainer"></div>

    <div class="row g-4">
        <?php 
        $counter = 1;
        while ($class = $classes->fetch_assoc()): 
        ?>
        <div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp delay-<?php echo $counter; ?>">
            <div class="grading-card p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge bg-light text-maroon border border-maroon mb-2 px-3">
                            <?php echo htmlspecialchars($class['subject_code'] ?: 'N/A'); ?>
                        </span>
                        <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($class['section_name'] ?: 'N/A'); ?></h5>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-blue rounded-pill px-3 py-2 fw-bold">
                            <i class="bi bi-people me-1"></i> <?php echo $class['student_count']; ?>
                        </span>
                    </div>
                </div>
                
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($class['subject_title'] ?: 'N/A'); ?></p>

                <?php if ($class['track_name']): ?>
                <div class="weight-badge-container shadow-sm border border-light">
                    <div class="weight-item">
                        <small>Written</small>
                        <span><?php echo $class['written_work_weight']; ?>%</span>
                    </div>
                    <div class="weight-item border-start border-end px-3">
                        <small>Performance</small>
                        <span><?php echo $class['performance_task_weight']; ?>%</span>
                    </div>
                    <div class="weight-item">
                        <small>Exam</small>
                        <span><?php echo $class['quarterly_exam_weight']; ?>%</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-auto pt-3">
                    <a href="gradebook.php?class_id=<?php echo $class['id']; ?>" class="btn btn-action-main w-100 mb-2 shadow-sm">
                        <i class="bi bi-journal-check me-2"></i> Open Digital Gradebook
                    </a>
                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-outline-custom w-100" onclick="importExcel(<?php echo $class['id']; ?>)">
                                <i class="bi bi-file-earmark-arrow-up me-1"></i> Import
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-custom w-100" onclick="exportExcel(<?php echo $class['id']; ?>)">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            $counter++; 
            endwhile; 
        ?>
    </div>
</div>

<!-- Import Grades Modal (MODERNIZED UI) -->
<div class="modal fade" id="importGradesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel me-2"></i>Import Excel Gradebook</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="importGradesForm" enctype="multipart/form-data">
                <input type="hidden" name="class_id" id="importClassId">
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded-3 mb-4 border-start border-primary border-4">
                        <p class="small mb-1 fw-bold text-dark">REQUIRED EXCEL FORMAT:</p>
                        <p class="small mb-0 text-muted">
                            Col 1: Student No | Col 2: Name | Col 3: Midterm | Col 4: Final
                        </p>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small text-muted">SELECT WORKBOOK (.XLSX, .CSV)</label>
                        <input type="file" class="form-control border-light shadow-sm py-2" name="excel_file" accept=".csv,.xls,.xlsx" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-action-main px-4">Begin Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED --- -->
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
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    
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
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        alert('Internal server error during import');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});
</script>
</body>
</html>