<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Class Records";

/** 
 * BACKEND LOGIC - UNTOUCHED 
 */
$class_list = $conn->query("SELECT cl.id, cl.section_name, cs.subject_code, cs.subject_title
    FROM classes cl
    LEFT JOIN curriculum_subjects cs ON cl.curriculum_subject_id = cs.id
    ORDER BY cs.subject_code, cl.section_name
");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // This opens the .wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC UI COMPONENTS --- */
    .control-card {
        background: white; border-radius: 15px; padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
        border-left: 5px solid var(--maroon);
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .modern-select { border-radius: 50px; border: 1px solid #ddd; font-weight: 600; color: #555; padding-left: 20px; }
    .modern-select:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(128,0,0,0.1); }

    .btn-export-pill {
        background-color: var(--blue); color: white; border-radius: 50px;
        font-weight: 700; padding: 8px 20px; transition: 0.3s; font-size: 0.8rem; border: none;
    }
    .btn-export-pill:hover { background-color: #002244; transform: translateY(-2px); color: white; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-clipboard-data-fill me-2 text-maroon"></i>Class Master Records</h4>
            <p class="text-muted small mb-0">View comprehensive student data per class section</p>
        </div>
        <a href="process/export_report.php?type=class" class="btn btn-export-pill shadow-sm" id="classExportBtn">
            <i class="bi bi-download me-1"></i> Export Section CSV
        </a>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <!-- Selection Hub -->
    <div class="control-card">
        <div class="row align-items-center">
            <div class="col-md-2">
                <label class="small fw-bold text-muted text-uppercase mb-md-0 mb-2">Select Section:</label>
            </div>
            <div class="col-md-10">
                <select class="form-select modern-select shadow-sm" id="classSelect">
                    <option value="">-- Search and Select Subject / Section --</option>
                    <?php while ($cl = $class_list->fetch_assoc()): ?>
                        <option value="<?php echo $cl['id']; ?>">
                            <?php echo htmlspecialchars(($cl['subject_code'] ?? 'N/A') . ' - ' . $cl['section_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="main-card-modern">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0" id="classRecordsTable">
                <thead>
                    <tr>
                        <th class="ps-4">Student Identity</th>
                        <th class="text-center">Midterm</th>
                        <th class="text-center">Final</th>
                        <th class="text-center">GWA</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Attendance</th>
                        <th class="text-center pe-4">Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="text-center py-5 text-muted fst-italic small">Please select a class section from the dropdown above to load records.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
document.getElementById('classSelect').addEventListener('change', async function() {
    const classId = this.value;
    const tbody = document.querySelector('#classRecordsTable tbody');
    const exportBtn = document.getElementById('classExportBtn');
    
    // Update Export URL
    if (classId) {
        exportBtn.href = `process/export_report.php?type=class&class_id=${classId}`;
    } else {
        exportBtn.href = 'process/export_report.php?type=class';
    }

    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted small">Select a class section above to view records</td></tr>';
        return;
    }

    // Show Loading
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border spinner-border-sm text-maroon"></div><p class="mt-2 small text-muted">Retrieving student data...</p></td></tr>';

    try {
        const response = await fetch(`process/get_class_records.php?class_id=${classId}`);
        const data = await response.json();
        
        if (data.status !== 'success') {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Failed to load records. Please try again.</td></tr>';
            return;
        }

        if (data.records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No students enrolled in this section.</td></tr>';
            return;
        }

        // Map Data to Table (Logic exactly as provided)
        tbody.innerHTML = data.records.map(r => `
            <tr>
                <td class="ps-4">
                    <div class="fw-bold text-dark">${r.full_name}</div>
                    <small class="text-muted fw-bold">${r.student_no}</small>
                </td>
                <td class="text-center small">${r.midterm ?? '-'}</td>
                <td class="text-center small">${r.final ?? '-'}</td>
                <td class="text-center fw-bold text-blue">${r.final_grade ?? '-'}</td>
                <td class="text-center">
                    <span class="badge rounded-pill bg-light text-dark border px-3">${r.remarks ?? 'N/A'}</span>
                </td>
                <td class="text-center">
                    <div class="small fw-bold">${r.attendance_percentage ?? 0}%</div>
                    <div class="progress mt-1" style="height: 4px; width: 60px; margin: 0 auto;">
                        <div class="progress-bar bg-success" style="width: ${r.attendance_percentage ?? 0}%"></div>
                    </div>
                </td>
                <td class="text-center pe-4">
                    <span class="badge rounded-pill bg-opacity-10 bg-info text-info border border-info border-opacity-25 px-3">
                        ${r.payment_status}
                    </span>
                </td>
            </tr>
        `).join('');

    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Server communication error.</td></tr>';
    }
});
</script>
</body>
</html>