<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

if ($class_id == 0) {
    header('Location: materials.php');
    exit();
}

// Verify class
$verify = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
$verify->bind_param("i", $class_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['teacher_id'] != $teacher_id) {
    header('Location: materials.php');
    exit();
}

// Get class info
$class_info = $conn->query("
    SELECT cl.*, s.subject_code, s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.id = $class_id
")->fetch_assoc();

// Get materials
$materials = $conn->query("
    SELECT id, file_path, uploaded_at
    FROM learning_materials
    WHERE class_id = $class_id
    ORDER BY uploaded_at DESC
");

$page_title = "Materials - " . ($class_info['subject_code'] ?? 'Class');
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
                        <?php echo htmlspecialchars($class_info['subject_code'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($class_info['section_name'] ?? 'N/A'); ?>
                    </h4>
                    <small class="text-muted">Learning Materials</small>
                </div>
                <a href="materials.php" class="btn btn-minimal">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Upload Form -->
        <div class="minimal-card">
            <h5 class="section-title">Upload Material</h5>
            <form id="uploadMaterialForm" enctype="multipart/form-data">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <div class="row">
                    <div class="col-md-8">
                        <input type="file" class="form-control" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xlsx,.xls" required>
                        <small class="text-muted">Accepted: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX (Max 10MB)</small>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary-minimal w-100">
                            <i class="bi bi-upload"></i> Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Materials List -->
        <div class="minimal-card">
            <h5 class="section-title">Uploaded Materials</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead style="background-color: var(--light-gray);">
                        <tr>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($material = $materials->fetch_assoc()): 
                            $filename = basename($material['file_path']);
                            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $full_path = '../../uploads/materials/' . $filename;
                            $file_size = file_exists($full_path) ? filesize($full_path) : 0;
                            
                            $icon_class = 'bi-file-earmark';
                            $icon_color = 'text-secondary';
                            
                            switch($extension) {
                                case 'pdf':
                                    $icon_class = 'bi-file-earmark-pdf';
                                    $icon_color = 'text-danger';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $icon_class = 'bi-file-earmark-word';
                                    $icon_color = 'text-primary';
                                    break;
                                case 'ppt':
                                case 'pptx':
                                    $icon_class = 'bi-file-earmark-ppt';
                                    $icon_color = 'text-warning';
                                    break;
                                case 'xls':
                                case 'xlsx':
                                    $icon_class = 'bi-file-earmark-excel';
                                    $icon_color = 'text-success';
                                    break;
                            }
                        ?>
                        <tr>
                            <td>
                                <i class="bi <?php echo $icon_class; ?> <?php echo $icon_color; ?> fs-5 me-2"></i>
                                <?php echo htmlspecialchars($filename); ?>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo strtoupper($extension); ?></span></td>
                            <td><?php echo number_format($file_size / 1024 / 1024, 2); ?> MB</td>
                            <td><?php echo date('M d, Y h:i A', strtotime($material['uploaded_at'])); ?></td>
                            <td>
                                <a href="../../uploads/materials/<?php echo htmlspecialchars($filename); ?>" 
                                   target="_blank" class="btn btn-sm btn-minimal">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteMaterial(<?php echo $material['id']; ?>, '<?php echo htmlspecialchars($filename); ?>')">
                                    <i class="bi bi-trash"></i> Delete
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
document.getElementById('uploadMaterialForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
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
        showAlert('Upload failed', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
    }
});

async function deleteMaterial(materialId, filename) {
    if (!confirm('Are you sure you want to delete "' + filename + '"?')) {
        return;
    }
    
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
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        showAlert('Delete failed', 'danger');
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