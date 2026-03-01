<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Tuition Fee Management";

// Fetch College programs
$programs_result = $conn->query("SELECT id, program_code, program_name FROM programs WHERE is_active = 1 ORDER BY program_code");

// Fetch program year levels
$year_levels_result = $conn->query("SELECT id, program_id, year_name, year_level FROM program_year_levels WHERE is_active = 1 ORDER BY program_id, year_level");
$year_levels_by_program = [];
while ($row = $year_levels_result->fetch_assoc()) {
    $year_levels_by_program[$row['program_id']][] = $row;
}

// Fetch SHS strands
$shs_strands_result = $conn->query("SELECT id, strand_code, strand_name FROM shs_strands WHERE is_active = 1 ORDER BY strand_code");

// Fetch SHS grade levels
$shs_grade_levels_result = $conn->query("SELECT id, strand_id, grade_name, grade_level FROM shs_grade_levels WHERE is_active = 1 ORDER BY strand_id, grade_level");
$grade_levels_by_strand = [];
while ($row = $shs_grade_levels_result->fetch_assoc()) {
    $grade_levels_by_strand[$row['strand_id']][] = $row;
}

// Fetch current academic year
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Fetch existing tuition fees
$tuition_fees_query = "
    SELECT ptf.*,
           COALESCE(ptf.program_type, 'college') as program_type,
           COALESCE(p.program_code, ss.strand_code) as program_code,
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, sgl.grade_name) as year_name
    FROM program_tuition_fees ptf
    LEFT JOIN programs p ON ptf.program_id = p.id AND COALESCE(ptf.program_type, 'college') = 'college'
    LEFT JOIN shs_strands ss ON ptf.program_id = ss.id AND ptf.program_type = 'shs'
    LEFT JOIN program_year_levels pyl ON ptf.year_level_id = pyl.id AND COALESCE(ptf.program_type, 'college') = 'college'
    LEFT JOIN shs_grade_levels sgl ON ptf.year_level_id = sgl.id AND ptf.program_type = 'shs'
    WHERE ptf.is_active = 1
    ORDER BY ptf.program_type, COALESCE(p.program_code, ss.strand_code), COALESCE(pyl.year_level, sgl.grade_level), ptf.semester
";
$tuition_fees_result = $conn->query($tuition_fees_query);

include '../../includes/header.php';
?>

<style>
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 15px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .btn-maroon-pill { background-color: var(--maroon); color: white !important; border: none; border-radius: 50px; font-weight: 700; padding: 8px 25px; transition: 0.3s; font-size: 0.8rem; }
    .btn-maroon-pill:hover { background-color: #600000; transform: translateY(-2px); }

    .fee-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #eee;
        padding: 20px;
        margin-bottom: 15px;
        transition: 0.3s;
    }
    .fee-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .fee-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--maroon);
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-currency-dollar me-2 text-maroon"></i>Tuition Fee Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Tuition Fees</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-maroon-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addTuitionModal">
                <i class="bi bi-plus-circle me-1"></i> Add Tuition Fee
            </button>
            <a href="students.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Students
            </a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <!-- Academic Year Info -->
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Current Academic Year:</strong> <?php echo htmlspecialchars($current_ay['year_name'] ?? 'Not Set'); ?>
        <span class="ms-3 text-muted small">| Tuition fees configured below will apply to this academic year</span>
    </div>

    <!-- Tuition Fees Table -->
    <div class="main-card-modern animate__animated animate__fadeInUp">
        <div class="p-3 bg-light border-bottom">
            <h6 class="mb-0 fw-bold text-uppercase small text-muted"><i class="bi bi-list-ul me-2"></i>Configured Tuition Fees</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0" id="tuitionTable">
                <thead>
                    <tr>
                        <th class="ps-4">Program</th>
                        <th>Type</th>
                        <th>Year Level</th>
                        <th>Semester</th>
                        <th class="text-end">Tuition Fee</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tuition_fees_result && $tuition_fees_result->num_rows > 0): ?>
                        <?php while ($fee = $tuition_fees_result->fetch_assoc()): ?>
                        <tr data-id="<?php echo $fee['id']; ?>">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($fee['program_code']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($fee['program_name']); ?></small>
                            </td>
                            <td><span class="badge bg-<?php echo ($fee['program_type'] ?? 'college') === 'shs' ? 'success' : 'primary'; ?> bg-opacity-75"><?php echo strtoupper($fee['program_type'] ?? 'college'); ?></span></td>
                            <td><?php echo htmlspecialchars($fee['year_name'] ?? 'All'); ?></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo $fee['semester']; ?> Sem</span></td>
                            <td class="text-end fw-bold text-maroon">₱<?php echo number_format($fee['tuition_fee'], 2); ?></td>
                            <td class="text-center pe-4">
                                <button class="btn btn-sm btn-warning" onclick="editTuition(<?php echo $fee['id']; ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteTuition(<?php echo $fee['id']; ?>)" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                No tuition fees configured yet. Click "Add Tuition Fee" to get started.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Terms Info -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="fee-card">
                <h6 class="fw-bold text-blue mb-3"><i class="bi bi-calculator me-2"></i>Payment Options</h6>
                <p class="small text-muted mb-2">When a student enrolls, they can choose:</p>
                <ul class="small">
                    <li><strong>Full Payment:</strong> Pay the entire tuition upfront</li>
                    <li><strong>Down Payment:</strong> Pay an initial amount, then the balance is divided into 4 terms</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="fee-card">
                <h6 class="fw-bold text-blue mb-3"><i class="bi bi-calendar-week me-2"></i>Payment Terms</h6>
                <p class="small text-muted mb-2">For installment payments, balance is divided into:</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-primary">Prelim</span>
                    <span class="badge bg-info">Midterm</span>
                    <span class="badge bg-warning text-dark">Pre-Finals</span>
                    <span class="badge bg-success">Finals</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Tuition Fee Modal -->
<div class="modal fade" id="addTuitionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: var(--maroon);">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add Tuition Fee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTuitionForm">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Level Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="program_type" id="add_program_type" required onchange="switchProgramType('add')">
                                <option value="college">College</option>
                                <option value="shs">SHS</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Program/Strand <span class="text-danger">*</span></label>
                            <select class="form-select" name="program_id" id="add_program_id" required onchange="loadYearLevelsForType('add')">
                                <option value="">-- Select Program --</option>
                                <?php $programs_result->data_seek(0); while ($p = $programs_result->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" data-type="college"><?php echo htmlspecialchars($p['program_code'].' - '.$p['program_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Year Level</label>
                            <select class="form-select" name="year_level_id" id="add_year_level_id">
                                <option value="">-- All Year Levels --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" name="semester" required>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tuition Fee (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-lg" name="tuition_fee" step="0.01" required placeholder="Enter tuition fee amount">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon-pill px-4">Save Tuition Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Tuition Fee Modal -->
<div class="modal fade" id="editTuitionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background-color: #c29200;">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Edit Tuition Fee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTuitionForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Level Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="program_type" id="edit_program_type" required onchange="switchProgramType('edit')">
                                <option value="college">College</option>
                                <option value="shs">SHS</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Program/Strand <span class="text-danger">*</span></label>
                            <select class="form-select" name="program_id" id="edit_program_id" required onchange="loadYearLevelsForType('edit')">
                                <option value="">-- Select Program --</option>
                                <?php $programs_result->data_seek(0); while ($p = $programs_result->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" data-type="college"><?php echo htmlspecialchars($p['program_code'].' - '.$p['program_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Year Level</label>
                            <select class="form-select" name="year_level_id" id="edit_year_level_id">
                                <option value="">-- All Year Levels --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" name="semester" id="edit_semester" required>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tuition Fee (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-lg" name="tuition_fee" id="edit_tuition_fee" step="0.01" required placeholder="Enter tuition fee amount">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold">Update Tuition Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Data from PHP
const yearLevelsByProgram = <?php echo json_encode($year_levels_by_program); ?>;
const gradeLevelsByStrand = <?php echo json_encode($grade_levels_by_strand); ?>;
const allPrograms = <?php $programs_result->data_seek(0); $prg_arr = []; while ($p = $programs_result->fetch_assoc()) { $prg_arr[] = $p; } echo json_encode($prg_arr); ?>;
const allStrands = <?php $shs_strands_result->data_seek(0); $str_arr = []; while ($s = $shs_strands_result->fetch_assoc()) { $str_arr[] = $s; } echo json_encode($str_arr); ?>;

function switchProgramType(prefix) {
    const type = document.getElementById(prefix + '_program_type').value;
    const programSelect = document.getElementById(prefix + '_program_id');
    const yearSelect = document.getElementById(prefix + '_year_level_id');
    programSelect.innerHTML = '<option value="">-- Select --</option>';
    yearSelect.innerHTML = '<option value="">-- All Levels --</option>';
    
    if (type === 'college') {
        allPrograms.forEach(p => {
            programSelect.innerHTML += `<option value="${p.id}" data-type="college">${p.program_code} - ${p.program_name}</option>`;
        });
    } else {
        allStrands.forEach(s => {
            programSelect.innerHTML += `<option value="${s.id}" data-type="shs">${s.strand_code} - ${s.strand_name}</option>`;
        });
    }
}

function loadYearLevelsForType(prefix) {
    const type = document.getElementById(prefix + '_program_type').value;
    const programId = document.getElementById(prefix + '_program_id').value;
    const select = document.getElementById(prefix + '_year_level_id');
    select.innerHTML = '<option value="">-- All Levels --</option>';
    
    if (type === 'college' && programId && yearLevelsByProgram[programId]) {
        yearLevelsByProgram[programId].forEach(level => {
            select.innerHTML += `<option value="${level.id}">${level.year_name}</option>`;
        });
    } else if (type === 'shs' && programId && gradeLevelsByStrand[programId]) {
        gradeLevelsByStrand[programId].forEach(level => {
            select.innerHTML += `<option value="${level.id}">${level.grade_name}</option>`;
        });
    }
}

// Legacy compatibility
function loadYearLevels(programId, selectId) {
    const select = document.getElementById(selectId);
    select.innerHTML = '<option value="">-- All Year Levels --</option>';
    if (programId && yearLevelsByProgram[programId]) {
        yearLevelsByProgram[programId].forEach(level => {
            select.innerHTML += `<option value="${level.id}">${level.year_name}</option>`;
        });
    }
}

// Add Tuition Form
document.getElementById('addTuitionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    
    try {
        const formData = new FormData(this);
        formData.append('action', 'add');
        
        const res = await fetch('process/tuition_api.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('addTuitionModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (err) {
        showAlert('Error: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Save Tuition Fee';
    }
});

// Edit Tuition
async function editTuition(id) {
    try {
        const res = await fetch(`process/tuition_api.php?action=get&id=${id}`);
        const data = await res.json();
        
        if (data.success) {
            const fee = data.tuition;
            document.getElementById('edit_id').value = fee.id;
            
            // Set program type first, then switch programs
            const pType = fee.program_type || 'college';
            document.getElementById('edit_program_type').value = pType;
            switchProgramType('edit');
            
            setTimeout(() => {
                document.getElementById('edit_program_id').value = fee.program_id;
                loadYearLevelsForType('edit');
                setTimeout(() => {
                    document.getElementById('edit_year_level_id').value = fee.year_level_id || '';
                }, 100);
            }, 50);
            
            document.getElementById('edit_semester').value = fee.semester;
            document.getElementById('edit_tuition_fee').value = fee.tuition_fee;
            
            new bootstrap.Modal(document.getElementById('editTuitionModal')).show();
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (err) {
        showAlert('Error loading tuition data', 'danger');
    }
}

// Edit Tuition Form
document.getElementById('editTuitionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    
    try {
        const formData = new FormData(this);
        formData.append('action', 'update');
        
        const res = await fetch('process/tuition_api.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('editTuitionModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (err) {
        showAlert('Error: ' + err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Update Tuition Fee';
    }
});

// Delete Tuition
async function deleteTuition(id) {
    if (!confirm('Are you sure you want to delete this tuition fee configuration?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const res = await fetch('process/tuition_api.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (err) {
        showAlert('Error: ' + err.message, 'danger');
    }
}

function showAlert(msg, type) {
    document.getElementById('alertContainer').innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}
</script>
</body>
</html>
