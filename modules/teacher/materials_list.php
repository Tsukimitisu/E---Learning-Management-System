<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$subject_id = (int)($_GET['subject_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($subject_id == 0) {
    header('Location: materials.php');
    exit();
}

/** 
 * BACKEND LOGIC - Materials are now per subject (not per section)
 */
// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT tsa.id, tsa.branch_id, b.name as branch_name 
    FROM teacher_subject_assignments tsa
    INNER JOIN branches b ON tsa.branch_id = b.id
    WHERE tsa.teacher_id = ? AND tsa.curriculum_subject_id = ? AND tsa.academic_year_id = ? AND tsa.is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
$assignment = $verify->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: materials.php');
    exit();
}

// Get subject info
$subject_query = $conn->prepare("
    SELECT cs.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, sgl.grade_name) as year_level
    FROM curriculum_subjects cs
    LEFT JOIN programs p ON cs.program_id = p.id
    LEFT JOIN shs_strands ss ON cs.shs_strand_id = ss.id
    LEFT JOIN program_year_levels pyl ON cs.year_level_id = pyl.id
    LEFT JOIN shs_grade_levels sgl ON cs.shs_grade_level_id = sgl.id
    WHERE cs.id = ?
");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_info = $subject_query->get_result()->fetch_assoc();

// Get materials for this subject (not per section anymore)
$materials_query = $conn->prepare("
    SELECT id, file_path, uploaded_at
    FROM learning_materials
    WHERE subject_id = ?
    ORDER BY uploaded_at DESC
");
$materials_query->bind_param("i", $subject_id);
$materials_query->execute();
$materials = $materials_query->get_result();

$page_title = "Materials - " . ($subject_info['subject_code'] ?? 'Subject');
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
    .maint-card {
        background: white; border-radius: 15px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden;
    }

    .upload-zone {
        background: #fcfcfc; border: 2px dashed #ddd; border-radius: 12px;
        padding: 25px; transition: 0.3s;
    }
    .upload-zone:hover { border-color: var(--maroon); background: #fff; }

    .table thead th {
        background: #fcfcfc; font-size: 0.75rem; text-transform: uppercase;
        letter-spacing: 1px; color: #888; padding: 15px; border-bottom: 2px solid #eee;
        position: sticky; top: -1px; z-index: 5;
    }

    .btn-maroon-action {
        background-color: var(--maroon); color: white; border: none;
        border-radius: 8px; font-weight: 700; padding: 10px 20px; transition: 0.3s;
    }
    .btn-maroon-action:hover { background-color: #600000; color: white; transform: translateY(-2px); }

    .file-icon-box {
        width: 40px; height: 40px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }

    /* Breadcrumbs */
    .breadcrumb-modern { background: transparent; padding: 0; margin-bottom: 5px; }
    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; vertical-align: middle; }

    @media (max-width: 576px) { .header-fixed-part { padding: 15px; } .body-scroll-part { padding: 15px; } }
</style>

<!-- Part 1: Fixed Header with Breadcrumbs -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="materials.php">Subjects</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($subject_info['subject_code'] ?? 'N/A'); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($subject_info['subject_code'] ?? 'Subject'); ?> <span class="text-muted fw-light mx-2">|</span> <span style="font-size: 0.9rem; color: #666;">Learning Materials</span>
            </h4>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($subject_info['subject_title']); ?> • <?php echo htmlspecialchars($subject_info['program_name']); ?> • <?php echo htmlspecialchars($assignment['branch_name']); ?></p>
        </div>
        <a href="materials.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill shadow-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Modern Upload Card -->
    <div class="maint-card animate__animated animate__fadeInUp">
        <div class="p-4">
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-cloud-arrow-up-fill me-2 text-maroon"></i>Upload New Resources</h6>
            <form id="uploadMaterialForm" enctype="multipart/form-data">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <div class="upload-zone">
                    <div class="row align-items-center g-3">
                        <div class="col-md-9">
                            <input type="file" class="form-control border-light shadow-sm" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xlsx,.xls" required>
                            <div class="mt-2 small text-muted">
                                <i class="bi bi-info-circle me-1"></i> Max 10MB. Supported: PDF, Word, Excel, PowerPoint. Materials will be available to all sections of this subject.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn-maroon-action w-100 shadow-sm">
                                <i class="bi bi-upload me-2"></i> Upload
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Materials Table -->
    <div class="maint-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
        <div class="p-4 border-bottom bg-light">
            <h6 class="fw-bold mb-0 text-blue"><i class="bi bi-files me-2"></i>Subject Files Repository</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Resource Details</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Size</th>
                        <th>Uploaded On</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($materials->num_rows > 0): ?>
                        <?php while ($material = $materials->fetch_assoc()): 
                            $filename = basename($material['file_path']);
                            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $full_path = '../../uploads/materials/' . $filename;
                            $file_size = file_exists($full_path) ? filesize($full_path) : 0;
                            
                            $icon = 'bi-file-earmark'; $color = 'bg-light text-secondary';
                            switch($extension) {
                                case 'pdf': $icon = 'bi-file-earmark-pdf-fill'; $color = 'bg-danger-subtle text-danger'; break;
                                case 'doc': case 'docx': $icon = 'bi-file-earmark-word-fill'; $color = 'bg-primary-subtle text-primary'; break;
                                case 'ppt': case 'pptx': $icon = 'bi-file-earmark-ppt-fill'; $color = 'bg-warning-subtle text-warning'; break;
                                case 'xls': case 'xlsx': $icon = 'bi-file-earmark-excel-fill'; $color = 'bg-success-subtle text-success'; break;
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="file-icon-box <?php echo $color; ?> me-3">
                                        <i class="bi <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="text-dark fw-bold text-break" style="font-size:0.85rem;"><?php echo htmlspecialchars($filename); ?></div>
                                </div>
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark border text-uppercase" style="font-size:0.65rem;"><?php echo $extension; ?></span></td>
                            <td class="text-center small text-muted"><?php echo number_format($file_size / 1024 / 1024, 2); ?> MB</td>
                            <td><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($material['uploaded_at'])); ?></small></td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="../../uploads/materials/<?php echo htmlspecialchars($filename); ?>" target="_blank" class="btn btn-sm btn-white border shadow-sm" title="Download">
                                        <i class="bi bi-download text-primary"></i>
                                    </a>
                                    <button class="btn btn-sm btn-white border shadow-sm" onclick="deleteMaterial(<?php echo $material['id']; ?>, '<?php echo htmlspecialchars($filename); ?>')" title="Delete">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>No materials uploaded for this subject yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED --- -->
<script>
document.getElementById('uploadMaterialForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalContent = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    
    try {
        const response = await fetch('api/upload_material.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        }
    } catch (error) {
        showAlert('Upload failed. Please check file size.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    }
});

async function deleteMaterial(materialId, filename) {
    if (!confirm('Permanently delete "' + filename + '"? This action cannot be undone.')) return;
    try {
        const response = await fetch('api/delete_material.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `material_id=${materialId}`
        });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('System error during deletion.', 'danger'); }
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>