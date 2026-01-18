<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Learning Materials";
$teacher_id = $_SESSION['user_id'];

/** 
 * BACKEND LOGIC  
 */
$classes = $conn->query("
    SELECT 
        cl.id,
        cl.section_name,
        s.subject_code,
        s.subject_title
    FROM classes cl
    LEFT JOIN subjects s ON cl.subject_id = s.id
    WHERE cl.teacher_id = $teacher_id
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
        z-index: 10;
    }

    .body-scroll-part {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 25px 30px 100px 30px; 
        background-color: #f8f9fa;
    }

    /* --- FANTASTIC FOLDER UI --- */
    .folder-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        cursor: pointer;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    /* Folder Tab Accent */
    .folder-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 6px;
        background: var(--maroon);
    }

    .folder-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(128, 0, 0, 0.1);
    }

    .folder-icon-box {
        width: 60px;
        height: 60px;
        background: rgba(128, 0, 0, 0.05);
        color: var(--maroon);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 20px;
        transition: 0.3s;
    }

    .folder-card:hover .folder-icon-box {
        background: var(--maroon);
        color: white;
        transform: rotate(-10deg);
    }

    .btn-manage {
        background-color: var(--blue);
        color: white;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px;
        transition: 0.3s;
        border: none;
        width: 100%;
        text-align: center;
        text-decoration: none;
        display: inline-block;
    }
    .btn-manage:hover {
        background-color: #002244;
        color: white;
    }

    /* Animation delays */
    <?php for($i=1; $i<=12; $i++): ?>
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
        <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-file-earmark-pdf-fill me-2 text-maroon"></i>Learning Materials</h4>
        <p class="text-muted small mb-0">Distribute modules, handouts, and resources to your classes</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4 shadow-sm rounded-pill">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    <div id="alertContainer"></div>

    <div class="row g-4">
        <?php 
        $counter = 1;
        if ($classes->num_rows > 0):
            while ($class = $classes->fetch_assoc()): 
                $subject_code = $class['subject_code'] ?? 'N/A';
                $section_name = $class['section_name'] ?? 'N/A';
                $subject_title = $class['subject_title'] ?? 'N/A';
        ?>
        <div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp delay-<?php echo $counter; ?>">
            <div class="folder-card p-4" onclick="location.href='materials_list.php?class_id=<?php echo $class['id']; ?>'">
                <div class="folder-icon-box shadow-sm">
                    <i class="bi bi-folder-fill"></i>
                </div>

                <div class="mb-4">
                    <span class="badge bg-light text-maroon border border-maroon px-3 mb-2 small fw-bold">
                        <?php echo htmlspecialchars($subject_code); ?>
                    </span>
                    <h5 class="fw-bold mb-1 text-dark">
                        <?php echo htmlspecialchars($section_name); ?>
                    </h5>
                    <p class="text-muted small mb-0 line-clamp-2" style="min-height: 40px;">
                        <?php echo htmlspecialchars($subject_title); ?>
                    </p>
                </div>

                <div class="mt-auto">
                    <a href="materials_list.php?class_id=<?php echo $class['id']; ?>" class="btn-manage shadow-sm">
                        <i class="bi bi-cloud-arrow-up me-2"></i> Manage Resources
                    </a>
                </div>
            </div>
        </div>
        <?php 
            $counter++; 
            endwhile; 
        else: ?>
        <div class="col-12 text-center py-5 animate__animated animate__fadeIn">
            <i class="bi bi-folder-x display-1 text-muted opacity-25"></i>
            <h5 class="mt-3 text-muted">No classes found to manage materials.</h5>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>