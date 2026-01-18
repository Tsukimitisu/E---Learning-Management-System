<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Class Records";

$class_list = $conn->query("SELECT cl.id, cl.section_name, cs.subject_code, cs.subject_title
    FROM classes cl
    LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
    ORDER BY cs.subject_code, cl.section_name
");

include '../../includes/header.php';
?>

<div class="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="content">
        <div class="navbar-custom d-flex justify-content-between align-items-center">
            <h4 class="mb-0" style="color: #003366;">
                <i class="bi bi-clipboard-data"></i> Class Records
            </h4>
            <a href="process/export_report.php?type=class" class="btn btn-sm btn-outline-primary" id="classExportBtn"><i class="bi bi-download"></i> Export CSV</a>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <select class="form-select" id="classSelect">
                    <option value="">-- Select Class --</option>
                    <?php while ($cl = $class_list->fetch_assoc()): ?>
                        <option value="<?php echo $cl['id']; ?>">
                            <?php echo htmlspecialchars(($cl['subject_code'] ?? 'N/A') . ' - ' . $cl['section_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="classRecordsTable">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th>Student No</th>
                                <th>Name</th>
                                <th>Midterm</th>
                                <th>Final</th>
                                <th>Final Grade</th>
                                <th>Remarks</th>
                                <th>Attendance %</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" class="text-center text-muted">Select a class to view records</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('classSelect').addEventListener('change', async function() {
    const classId = this.value;
    const tbody = document.querySelector('#classRecordsTable tbody');
    const exportBtn = document.getElementById('classExportBtn');
    if (classId) {
        exportBtn.href = `process/export_report.php?type=class&class_id=${classId}`;
    } else {
        exportBtn.href = 'process/export_report.php?type=class';
    }
    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Select a class to view records</td></tr>';
        return;
    }
    const response = await fetch(`process/get_class_records.php?class_id=${classId}`);
    const data = await response.json();
    if (data.status !== 'success') {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load records</td></tr>';
        return;
    }
    tbody.innerHTML = data.records.map(r => `
        <tr>
            <td>${r.student_no}</td>
            <td>${r.full_name}</td>
            <td>${r.midterm ?? '-'}</td>
            <td>${r.final ?? '-'}</td>
            <td>${r.final_grade ?? '-'}</td>
            <td>${r.remarks ?? '-'}</td>
            <td>${r.attendance_percentage ?? 0}%</td>
            <td>${r.payment_status}</td>
        </tr>
    `).join('');
});
</script>
</body>
</html>
