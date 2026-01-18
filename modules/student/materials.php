<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Learning Materials";
$student_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Get enrolled section
$section_info = $conn->query("
    SELECT s.* 
    FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

// Get materials from enrolled classes
$materials = $conn->query("
    SELECT lm.*, c.course_code as subject_code, c.title as subject_title,
           CONCAT(up.first_name, ' ', up.last_name) as uploaded_by_name
    FROM learning_materials lm
    INNER JOIN classes cl ON lm.class_id = cl.id
    INNER JOIN courses c ON cl.course_id = c.id
    INNER JOIN enrollments e ON e.class_id = lm.class_id
    LEFT JOIN user_profiles up ON cl.teacher_id = up.user_id
    WHERE e.student_id = $student_id AND e.status = 'approved'
    ORDER BY lm.uploaded_at DESC
");

// Group materials by subject
$materials_by_subject = [];
while ($row = $materials->fetch_assoc()) {
    $code = $row['subject_code'];
    if (!isset($materials_by_subject[$code])) {
        $materials_by_subject[$code] = [
            'title' => $row['subject_title'],
            'teacher' => $row['uploaded_by_name'],
            'materials' => []
        ];
    }
    $materials_by_subject[$code]['materials'][] = $row;
}

// Helper function to get file icon
function getFileIcon($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'bi-file-earmark-pdf text-danger',
        'doc' => 'bi-file-earmark-word text-primary',
        'docx' => 'bi-file-earmark-word text-primary',
        'xls' => 'bi-file-earmark-excel text-success',
        'xlsx' => 'bi-file-earmark-excel text-success',
        'ppt' => 'bi-file-earmark-ppt text-warning',
        'pptx' => 'bi-file-earmark-ppt text-warning',
        'jpg' => 'bi-file-earmark-image text-info',
        'jpeg' => 'bi-file-earmark-image text-info',
        'png' => 'bi-file-earmark-image text-info',
        'gif' => 'bi-file-earmark-image text-info',
        'mp4' => 'bi-file-earmark-play text-danger',
        'mp3' => 'bi-file-earmark-music text-purple',
        'zip' => 'bi-file-earmark-zip text-secondary',
        'rar' => 'bi-file-earmark-zip text-secondary',
    ];
    return $icons[$ext] ?? 'bi-file-earmark text-secondary';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-pdf text-info me-2"></i>Learning Materials</h4>
                <small class="text-muted">Access course materials shared by your teachers</small>
            </div>
        </div>

        <?php if (empty($materials_by_subject)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-folder2-open display-3 text-muted"></i>
                <p class="mt-3 text-muted">No learning materials available yet</p>
                <small class="text-muted">Materials uploaded by your teachers will appear here</small>
            </div>
        </div>
        <?php else: ?>
        
        <div class="row">
            <?php foreach ($materials_by_subject as $code => $data): ?>
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($code); ?></span>
                                <strong><?php echo htmlspecialchars($data['title']); ?></strong>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($data['teacher']); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['materials'] as $material): 
                                        $file_path = '../../uploads/' . $material['file_path'];
                                        $file_name = basename($material['file_path']);
                                        $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <i class="<?php echo getFileIcon($material['file_path']); ?> fs-4"></i>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($file_name); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo formatFileSize($file_size); ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($material['uploaded_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="<?php echo $file_path; ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               target="_blank" download>
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                            <a href="<?php echo $file_path; ?>" 
                                               class="btn btn-sm btn-outline-info" 
                                               target="_blank">
                                                <i class="bi bi-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-muted small">
                        <i class="bi bi-folder2 me-1"></i>
                        <?php echo count($data['materials']); ?> file(s)
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tips Card -->
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-lightbulb text-warning me-2"></i>Tips</h6>
                <ul class="mb-0 small text-muted">
                    <li>Click "Download" to save materials to your device</li>
                    <li>Click "View" to preview the file in a new tab</li>
                    <li>Check back regularly for new materials uploaded by your teachers</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
