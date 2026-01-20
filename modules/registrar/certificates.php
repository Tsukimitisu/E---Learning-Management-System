<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Certificates";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$students = $conn->query("SELECT s.user_id, s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name FROM students s INNER JOIN user_profiles up ON s.user_id = up.user_id ORDER BY up.last_name, up.first_name");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // This opens the .wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC CERTIFICATE UI --- */
    .cert-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .nav-pills-modern .nav-link {
        color: #666; font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
        padding: 12px 25px; border-radius: 10px; transition: 0.3s; margin-right: 10px;
        background: #f1f3f5;
    }
    .nav-pills-modern .nav-link.active {
        background-color: var(--blue); color: white; box-shadow: 0 4px 12px rgba(0,51,102,0.2);
    }

    .cert-icon-bg {
        width: 60px; height: 60px; border-radius: 15px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; background: rgba(128, 0, 0, 0.05); color: var(--maroon);
        margin-bottom: 20px;
    }

    .form-section-title {
        color: var(--blue); font-weight: 800; text-transform: uppercase;
        font-size: 0.85rem; letter-spacing: 1px; margin-bottom: 20px;
        display: flex; align-items: center;
    }
    .form-section-title::after { content: ""; flex: 1; height: 2px; background: #f1f1f1; margin-left: 15px; }

    @media (max-width: 768px) {
        .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; }
        .nav-pills-modern { flex-direction: column; }
        .nav-pills-modern .nav-link { margin-right: 0; margin-bottom: 5px; width: 100%; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-award-fill me-2 text-maroon"></i>Document Generation</h4>
            <p class="text-muted small mb-0">Generate and print official student certifications</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-printer-fill me-1 text-primary"></i> Print Ready Layouts
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <div class="cert-card animate__animated animate__fadeInUp">
        <div class="p-4 p-md-5">
            <!-- Modern Pill Navigation -->
            <ul class="nav nav-pills nav-pills-modern mb-5" id="certTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="enrollment-tab" data-bs-toggle="pill" data-bs-target="#enrollment" type="button">
                        <i class="bi bi-file-earmark-check me-2"></i>Enrollment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="grade-tab" data-bs-toggle="pill" data-bs-target="#grade" type="button">
                        <i class="bi bi-graph-up-arrow me-2"></i>Grade Report
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completion-tab" data-bs-toggle="pill" data-bs-target="#completion" type="button">
                        <i class="bi bi-mortarboard me-2"></i>Completion
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="certTabsContent">
                <!-- Enrollment Tab -->
                <div class="tab-pane fade show active" id="enrollment" role="tabpanel">
                    <div class="form-section-title">Certification of Enrollment</div>
                    <div class="animate__animated animate__fadeIn">
                        <?php $tabType = 'enrollment'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>

                <!-- Grade Tab -->
                <div class="tab-pane fade" id="grade" role="tabpanel">
                    <div class="form-section-title">Scholastic Grade Report</div>
                    <div class="animate__animated animate__fadeIn">
                        <?php $tabType = 'grade_report'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>

                <!-- Completion Tab -->
                <div class="tab-pane fade" id="completion" role="tabpanel">
                    <div class="form-section-title">Certificate of Course Completion</div>
                    <div class="animate__animated animate__fadeIn">
                        <?php $tabType = 'completion'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Section -->
    <div class="row mt-4 g-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
        <div class="col-md-6">
            <div class="p-4 rounded-4 bg-white shadow-sm border-start border-maroon border-4">
                <h6 class="fw-bold text-dark mb-2">Usage Note</h6>
                <p class="small text-muted mb-0">Generated PDF certificates will open in a new browser tab. Please ensure your pop-up blocker is disabled for this domain.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-4 rounded-4 bg-white shadow-sm border-start border-blue border-4">
                <h6 class="fw-bold text-dark mb-2">Data Accuracy</h6>
                <p class="small text-muted mb-0">Certificates are based on verified academic records. If grades are missing, please confirm they have been finalized by the instructor.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
/** 
 * EVENT LISTENER FOR CERTIFICATE FORMS 
 * Logic remains identical to original, just ensuring modern selector compatibility.
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.certificate-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const type = form.getAttribute('data-type');

            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }
            params.append('certificate_type', type);

            // Opens the backend generation script in a new window
            window.open(`process/generate_certificate.php?${params.toString()}`, '_blank');
        });
    });
});
</script>
</body>
</html>