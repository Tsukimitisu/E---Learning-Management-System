<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Certificates";

$students = $conn->query("SELECT s.user_id, s.student_no, CONCAT(up.first_name, ' ', up.last_name) as full_name FROM students s INNER JOIN user_profiles up ON s.user_id = up.user_id ORDER BY up.last_name, up.first_name");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-award"></i> Certificate Generation
            </h4>
        </div>

        <div id="alertContainer"></div>

        <div class="card shadow-sm">
            <div class="card-body">
                <ul class="nav nav-tabs" id="certTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="enrollment-tab" data-bs-toggle="tab" data-bs-target="#enrollment" type="button" role="tab">Enrollment Certificate</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="grade-tab" data-bs-toggle="tab" data-bs-target="#grade" type="button" role="tab">Grade Report</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completion-tab" data-bs-toggle="tab" data-bs-target="#completion" type="button" role="tab">Completion Certificate</button>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="enrollment" role="tabpanel">
                        <?php $tabType = 'enrollment'; include 'process/_certificate_form.php'; ?>
                    </div>
                    <div class="tab-pane fade" id="grade" role="tabpanel">
                        <?php $tabType = 'grade_report'; include 'process/_certificate_form.php'; ?>
                    </div>
                    <div class="tab-pane fade" id="completion" role="tabpanel">
                        <?php $tabType = 'completion'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

        window.open(`process/generate_certificate.php?${params.toString()}`, '_blank');
    });
});
</script>
</body>
</html>
