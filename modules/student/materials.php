<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Learning Materials";
$student_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$section_info = $conn->query("
    SELECT s.* FROM section_students stu
    INNER JOIN sections s ON stu.section_id = s.id
    WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id
    LIMIT 1
")->fetch_assoc();

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

/** 
 * HELPER FUNCTIONS - UNTOUCHED 
 */
function getFileIcon($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'bi-file-earmark-pdf-fill text-danger',
        'doc' => 'bi-file-earmark-word-fill text-primary',
        'docx' => 'bi-file-earmark-word-fill text-primary',
        'xls' => 'bi-file-earmark-excel-fill text-success',
        'xlsx' => 'bi-file-earmark-excel-fill text-success',
        'ppt' => 'bi-file-earmark-ppt-fill text-warning',
        'pptx' => 'bi-file-earmark-ppt-fill text-warning',
        'jpg' => 'bi-file-earmark-image text-info',
        'png' => 'bi-file-earmark-image text-info',
        'mp4' => 'bi-file-earmark-play-fill text-danger',
        'zip' => 'bi-file-earmark-zip-fill text-secondary',
    ];
    return $icons[$ext] ?? 'bi-file-earmark-fill text-secondary';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC MATERIALS UI --- */
    .subject-resource-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden;
    }

    .resource-header {
        background: #fcfcfc; padding: 20px 25px; border-bottom: 1px solid #f1f1f1;
        display: flex; justify-content: space-between; align-items: center;
    }

    .file-row {
        padding: 15px 25px; border-bottom: 1px solid #f9f9f9;
        transition: all 0.3s ease; display: flex; align-items: center;
    }
    .file-row:hover { background-color: #fcfcfc; transform: translateX(5px); }
    .file-row:last-child { border-bottom: none; }

    .file-icon-box {
        width: 45px; height: 45px; border-radius: 12px; background: #f8f9fa;
        display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.4rem;
    }

    .btn-download-pill {
        border-radius: 50px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;
        padding: 6px 15px; transition: 0.3s;
    }

    .tip-banner {
        background: var(--blue); color: white; border-radius: 15px; padding: 20px;
        display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 15px rgba(0,51,102,0.2);
    }

    /* Staggered Animations */
    <?php for($i=1; $i<=10; $i++): ?>
    .delay-<?php echo $i; ?> { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-folder-fill me-2 text-maroon"></i>Learning Materials</h4>
            <p class="text-muted small mb-0">Download modules and lecture references</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-calendar3 me-1"></i> <?php echo htmlspecialchars($current_ay['year_name'] ?? ''); ?>
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <?php if (empty($materials_by_subject)): ?>
    <div class="text-center py-5 animate__animated animate__fadeIn">
        <i class="bi bi-cloud-slash display-1 text-muted opacity-25"></i>
        <h5 class="mt-3 text-muted">No materials have been uploaded yet.</h5>
        <p class="small text-muted">Files shared by your instructors will appear here.</p>
    </div>
    <?php else: ?>
        
        <?php $counter = 1; foreach ($materials_by_subject as $code => $data): ?>
        <div class="subject-resource-card animate__animated animate__fadeInUp delay-<?php echo $counter; ?>">
            <div class="resource-header">
                <div>
                    <span class="badge bg-maroon mb-1 px-3"><?php echo htmlspecialchars($code); ?></span>
                    <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($data['title']); ?></h6>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block small text-uppercase fw-bold" style="font-size: 0.6rem;">Instructor</small>
                    <span class="fw-bold text-blue small">Prof. <?php echo htmlspecialchars($data['teacher']); ?></span>
                </div>
            </div>

            <div class="card-body p-0">
                <?php foreach ($data['materials'] as $material): 
                    $file_path = '../../uploads/' . $material['file_path'];
                    $file_name = basename($material['file_path']);
                    $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                ?>
                <div class="file-row">
                    <div class="file-icon-box shadow-sm">
                        <i class="<?php echo getFileIcon($material['file_path']); ?>"></i>
                    </div>
                    <div class="flex-grow-1 me-3">
                        <div class="fw-bold text-dark text-break" style="font-size: 0.9rem;"><?php echo htmlspecialchars($file_name); ?></div>
                        <div class="d-flex gap-3 small text-muted">
                            <span><i class="bi bi-hdd me-1"></i><?php echo formatFileSize($file_size); ?></span>
                            <span><i class="bi bi-clock me-1"></i><?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo $file_path; ?>" class="btn btn-outline-primary btn-download-pill shadow-sm" target="_blank" download>
                            <i class="bi bi-download me-1"></i> <span class="d-none d-md-inline">Download</span>
                        </a>
                        <a href="<?php echo $file_path; ?>" class="btn btn-light btn-download-pill border shadow-sm" target="_blank">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bg-light p-2 px-4 text-end border-top">
                <small class="text-muted fw-bold" style="font-size: 0.65rem;">
                    TOTAL ASSETS: <?php echo count($data['materials']); ?>
                </small>
            </div>
        </div>
        <?php $counter++; endforeach; ?>

    <?php endif; ?>

    <!-- Tips Section -->
    <div class="tip-banner animate__animated animate__fadeInUp delay-5 mt-5">
        <i class="bi bi-lightbulb-fill fs-2"></i>
        <div>
            <h6 class="fw-bold mb-1">Quick Tip</h6>
            <p class="mb-0 small opacity-75">You can preview most PDF and Image files directly by clicking the eye icon before downloading.</p>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>