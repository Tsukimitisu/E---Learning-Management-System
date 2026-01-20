<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_SCHOOL_ADMIN) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Program Management";

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */
$programs_query = "
    SELECT
        p.id, p.program_code, p.program_name, p.degree_level, p.is_active,
        s.name as school_name,
        COUNT(DISTINCT cs.id) as subject_count,
        COUNT(DISTINCT yl.id) as year_levels_count,
        p.created_at
    FROM programs p
    INNER JOIN schools s ON p.school_id = s.id
    LEFT JOIN curriculum_subjects cs ON p.id = cs.program_id
    LEFT JOIN program_year_levels yl ON p.id = yl.program_id AND yl.is_active = 1
    GROUP BY p.id
    ORDER BY p.program_name
";
$programs_result = $conn->query($programs_query);
$schools_result = $conn->query("SELECT id, name FROM schools ORDER BY name");

include '../../includes/header.php';
include '../../includes/sidebar.php'; // This opens the .wrapper and starts #content
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC PROGRAM UI --- */
    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .btn-maroon-pill { 
        background-color: var(--maroon); color: white !important; border: none; border-radius: 50px; 
        font-weight: 700; padding: 8px 25px; transition: 0.3s; font-size: 0.85rem;
    }
    .btn-maroon-pill:hover { background-color: #600000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(128,0,0,0.2); }

    .action-btn-circle { 
        width: 34px; height: 34px; border-radius: 50%; display: inline-flex; 
        align-items: center; justify-content: center; transition: 0.2s; border: 1px solid #eee; background: white;
    }
    .action-btn-circle:hover { transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-mortarboard-fill me-2 text-maroon"></i>Program Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Programs</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addProgramModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Program
        </button>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part animate__animated animate__fadeInUp">
    
    <div id="alertContainer"></div>

    <div class="main-card-modern">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 80px;">ID</th>
                        <th>Program Details</th>
                        <th>Academic School</th>
                        <th class="text-center">Degree Level</th>
                        <th class="text-center">Subjects</th>
                        <th class="text-center">Status</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($programs_result->num_rows == 0): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted small fst-italic">No academic programs registered.</td></tr>
                    <?php else: while ($program = $programs_result->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-muted small">#<?php echo $program['id']; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($program['program_code']); ?></div>
                            <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?php echo htmlspecialchars($program['program_name']); ?></small>
                        </td>
                        <td>
                            <div class="small fw-bold text-blue"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($program['school_name']); ?></div>
                        </td>
                        <td class="text-center small fw-bold text-muted"><?php echo htmlspecialchars($program['degree_level']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-primary border border-primary px-3 rounded-pill"><?php echo $program['subject_count']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-<?php echo $program['is_active'] ? 'success' : 'secondary'; ?> px-3">
                                <?php echo $program['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="action-btn-circle text-warning" onclick="editProgram(<?php echo $program['id']; ?>)" title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="action-btn-circle text-<?php echo $program['is_active'] ? 'secondary' : 'success'; ?>" 
                                        onclick="toggleStatus(<?php echo $program['id']; ?>, <?php echo $program['is_active']; ?>)" title="Toggle Status">
                                    <i class="bi bi-<?php echo $program['is_active'] ? 'slash-circle' : 'check-circle-fill'; ?>"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Inclusion -->
<?php include 'curriculum_modals.php'; ?>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
/** 
 * Data Preparation for JS logic
 */
const collegePrograms = <?php 
    $programs_result->data_seek(0);
    $programs_data = [];
    while ($prog = $programs_result->fetch_assoc()) { $programs_data[] = $prog; }
    echo json_encode($programs_data); 
?>;

function goBack() {
    if (document.referrer && document.referrer.includes('/elms_system/')) { window.history.back(); } 
    else { window.location.href = 'index.php'; }
}

/** 1. AJAX: ADD PROGRAM */
document.getElementById('addProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/add_program.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            $('#addProgramModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('System communication error', 'danger'); }
});

/** 2. AJAX: TOGGLE STATUS */
function toggleStatus(id, currentStatus) {
    const action = currentStatus ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} this program?`)) return;
    
    fetch('process/toggle_program_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ program_id: id, is_active: currentStatus ? 0 : 1 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') { showAlert(data.message, 'success'); setTimeout(() => location.reload(), 1200); } 
        else { showAlert(data.message, 'danger'); }
    });
}

/** 3. AJAX: EDIT PROGRAM */
function editProgram(id) {
    const program = collegePrograms.find(p => p.id == id);
    if (program) {
        document.getElementById('editProgramId').value = program.id;
        document.getElementById('editProgramCode').value = program.program_code;
        document.getElementById('editProgramName').value = program.program_name;
        document.getElementById('editProgramDegree').value = program.degree_level;
        document.getElementById('editProgramStatus').value = program.is_active;
        
        fetch('process/get_program.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('editProgramSchool').value = data.program.school_id;
                }
            });
        
        new bootstrap.Modal(document.getElementById('editProgramModal')).show();
    }
}

/** 4. AJAX: UPDATE PROGRAM */
document.getElementById('editProgramForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
        const response = await fetch('process/update_program.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            showAlert(data.message, 'success');
            $('#editProgramModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else { showAlert(data.message, 'danger'); }
    } catch (error) { showAlert('Error processing update', 'danger'); }
});

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>