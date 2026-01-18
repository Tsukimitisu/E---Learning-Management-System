<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$teacher_id = $_SESSION['user_id'];

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

if ($section_id == 0 || $subject_id == 0) {
    header('Location: grading.php');
    exit();
}

/** 
 * BACKEND LOGIC - Using new section/subject structure
 */
// Verify teacher is assigned to this subject
$verify = $conn->prepare("SELECT id FROM teacher_subject_assignments WHERE teacher_id = ? AND curriculum_subject_id = ? AND academic_year_id = ? AND is_active = 1");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
$result = $verify->get_result();

if ($result->num_rows == 0) {
    header('Location: grading.php');
    exit();
}

// Get section info
$section_query = $conn->prepare("
    SELECT s.*, 
           COALESCE(p.program_name, ss.strand_name) as program_name,
           COALESCE(pyl.year_name, CONCAT('Grade ', sgl.grade_level)) as year_level_name
    FROM sections s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN program_year_levels pyl ON s.year_level_id = pyl.id
    LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id
    LEFT JOIN shs_grade_levels sgl ON s.shs_grade_level_id = sgl.id
    WHERE s.id = ?
");
$section_query->bind_param("i", $section_id);
$section_query->execute();
$section_info = $section_query->get_result()->fetch_assoc();

// Get subject info
$subject_query = $conn->prepare("SELECT * FROM curriculum_subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_info = $subject_query->get_result()->fetch_assoc();

// Combine for compatibility with old template
$class_info = [
    'section_name' => $section_info['section_name'],
    'subject_code' => $subject_info['subject_code'],
    'subject_title' => $subject_info['subject_title'],
    'units' => $subject_info['units'],
    'program_name' => $section_info['program_name'],
    'year_level_name' => $section_info['year_level_name'],
    'track_name' => null,
    'written_work_weight' => 30,
    'performance_task_weight' => 50,
    'quarterly_exam_weight' => 20
];

// Get students from section_students table
$students = $conn->prepare("
    SELECT 
        u.id as user_id,
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        CONCAT(up.first_name, ' ', up.last_name) as student_name,
        g.midterm,
        g.final,
        g.final_grade,
        g.remarks
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN students st ON u.id = st.user_id
    LEFT JOIN grades g ON u.id = g.student_id AND g.section_id = ? AND g.subject_id = ?
    WHERE ss.section_id = ? AND ss.status = 'active'
    ORDER BY up.last_name, up.first_name
");
$students->bind_param("iii", $section_id, $subject_id, $section_id);
$students->execute();
$students = $students->get_result();

$page_title = "Gradebook - " . $class_info['subject_code'];
include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC GRADEBOOK UI --- */
    .ledger-card {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .track-info-banner {
        background: #e7f5ff;
        border-left: 5px solid var(--blue);
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
    }

    /* Input Styling */
    .grade-input {
        width: 85px;
        text-align: center;
        font-weight: 700;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        padding: 5px;
        transition: 0.2s;
    }
    .grade-input:focus {
        border-color: var(--maroon);
        box-shadow: 0 0 0 3px rgba(128,0,0,0.1);
        outline: none;
    }

    .computed-grade {
        font-weight: 800;
        color: var(--blue);
        font-size: 1.1rem;
    }

    /* Sticky Table Header */
    .table thead th {
        background: #fcfcfc;
        position: sticky;
        top: -1px;
        z-index: 5;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #888;
        padding: 15px;
        border-bottom: 2px solid #eee;
    }

    .table tbody td { padding: 12px 15px; vertical-align: middle; }

    .btn-save-all {
        background-color: var(--maroon);
        color: white;
        border: none;
        font-weight: 700;
        padding: 8px 25px;
        border-radius: 50px;
        transition: 0.3s;
    }
    .btn-save-all:hover {
        background-color: #600000;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
    }

    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; vertical-align: middle; }

    @media (max-width: 576px) {
        .header-fixed-part { padding: 15px; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="grading.php">Grading</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($class_info['subject_code']); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($class_info['section_name'] ?: 'N/A'); ?> <span class="text-muted fw-light mx-2">|</span> <span style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($class_info['subject_title']); ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm px-3 rounded-pill" onclick="exportGrades()" title="Export to Excel">
                <i class="bi bi-file-earmark-excel me-1"></i> Export
            </button>
            <button class="btn btn-outline-primary btn-sm px-3 rounded-pill" onclick="document.getElementById('importFile').click()" title="Import from Excel">
                <i class="bi bi-file-earmark-excel me-1"></i> Import
            </button>
            <input type="file" id="importFile" accept=".xlsx,.xls" style="display:none" onchange="importGrades(this)">
            <button class="btn btn-save-all shadow-sm" onclick="saveAllGrades()">
                <i class="bi bi-cloud-check me-2"></i> Save All
            </button>
            <a href="grading.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <?php if ($class_info['track_name']): ?>
    <div class="track-info-banner animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill fs-4 me-3 text-blue"></i>
            <div>
                <span class="fw-bold text-blue">SHS TRACK: <?php echo htmlspecialchars($class_info['track_name']); ?></span>
                <div class="small text-muted">
                    Weights: Written (<?php echo $class_info['written_work_weight']; ?>%) • 
                    Performance (<?php echo $class_info['performance_task_weight']; ?>%) • 
                    Exam (<?php echo $class_info['quarterly_exam_weight']; ?>%)
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gradebook Ledger -->
    <div class="ledger-card animate__animated animate__fadeInUp">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Student Name</th>
                        <th class="text-center">Midterm</th>
                        <th class="text-center">Final</th>
                        <th class="text-center">Average</th>
                        <th class="text-center">Remarks</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                    <tr data-student-id="<?php echo $student['user_id']; ?>">
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($student['student_no']); ?></small>
                        </td>
                        <td class="text-center">
                            <input type="number" class="grade-input midterm-input shadow-sm" 
                                   value="<?php echo $student['midterm'] ?? ''; ?>" min="0" max="100" step="0.01">
                        </td>
                        <td class="text-center">
                            <input type="number" class="grade-input final-input shadow-sm" 
                                   value="<?php echo $student['final'] ?? ''; ?>" min="0" max="100" step="0.01">
                        </td>
                        <td class="text-center computed-grade">
                            <?php echo $student['final_grade'] ? number_format($student['final_grade'], 2) : '---'; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill px-3 py-2 <?php echo ($student['remarks'] ?? '') == 'PASSED' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo htmlspecialchars($student['remarks'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-light border rounded-circle save-grade-btn shadow-sm" title="Save Row">
                                <i class="bi bi-save text-blue"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- SheetJS Library for Excel Import/Export -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>

<!-- --- JAVASCRIPT LOGIC - Updated for new structure --- -->
<script>
<script>
const SECTION_ID = <?php echo $section_id; ?>;
const SUBJECT_ID = <?php echo $subject_id; ?>;

// Auto-calculate on input change
document.querySelectorAll('.midterm-input, .final-input').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        calculateFinalGrade(row);
    });
});

// Save individual grade
document.querySelectorAll('.save-grade-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('tr');
        saveGrade(row);
    });
});

function calculateFinalGrade(row) {
    const midterm = parseFloat(row.querySelector('.midterm-input').value) || 0;
    const final = parseFloat(row.querySelector('.final-input').value) || 0;
    
    // Formula logic exactly as provided
    const finalGrade = (midterm * 0.4) + (final * 0.6);
    const remarks = finalGrade >= 75 ? 'PASSED' : 'FAILED';
    
    const gradeCell = row.querySelector('.computed-grade');
    gradeCell.textContent = finalGrade > 0 ? finalGrade.toFixed(2) : '---';
    
    const remarksCell = row.querySelector('.badge');
    remarksCell.textContent = finalGrade > 0 ? remarks : 'N/A';
    remarksCell.className = 'badge rounded-pill px-3 py-2 ' + (remarks === 'PASSED' ? 'bg-success' : 'bg-danger');
}

async function saveGrade(row) {
    const studentId = row.dataset.studentId;
    const midterm = parseFloat(row.querySelector('.midterm-input').value) || 0;
    const final = parseFloat(row.querySelector('.final-input').value) || 0;
    const finalGrade = (midterm * 0.4) + (final * 0.6);
    const remarks = finalGrade >= 75 ? 'PASSED' : 'FAILED';
    
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('section_id', SECTION_ID);
    formData.append('subject_id', SUBJECT_ID);
    formData.append('midterm', midterm);
    formData.append('final', final);
    formData.append('final_grade', finalGrade.toFixed(2));
    formData.append('remarks', remarks);
    
    try {
        const response = await fetch('../teacher/api/update_grade.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            // Logic handled by showAlert
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function saveAllGrades() {
    const rows = document.querySelectorAll('tbody tr');
    const saveBtn = document.querySelector('.btn-save-all');
    const originalText = saveBtn.innerHTML;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
    
    let saved = 0;
    for (const row of rows) {
        await saveGrade(row);
        saved++;
    }
    
    showAlert(`Successfully synchronized ${saved} student records.`, 'success');
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

// Export grades to Excel
function exportGrades() {
    const rows = document.querySelectorAll('tbody tr');
    const data = [['Student No', 'Student Name', 'Midterm', 'Final', 'Average', 'Remarks']];
    
    rows.forEach(row => {
        const studentNo = row.querySelector('small.text-muted')?.textContent?.trim() || '';
        const studentName = row.querySelector('.fw-bold.text-dark')?.textContent?.trim() || '';
        const midterm = parseFloat(row.querySelector('.midterm-input')?.value) || '';
        const final = parseFloat(row.querySelector('.final-input')?.value) || '';
        const average = row.querySelector('.computed-grade')?.textContent?.trim() || '';
        const remarks = row.querySelector('.badge')?.textContent?.trim() || '';
        
        data.push([studentNo, studentName, midterm, final, average, remarks]);
    });
    
    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Set column widths
    ws['!cols'] = [
        { wch: 15 },  // Student No
        { wch: 30 },  // Student Name
        { wch: 10 },  // Midterm
        { wch: 10 },  // Final
        { wch: 10 },  // Average
        { wch: 12 }   // Remarks
    ];
    
    XLSX.utils.book_append_sheet(wb, ws, 'Grades');
    
    // Download
    const subjectCode = '<?php echo addslashes($class_info['subject_code']); ?>';
    const sectionName = '<?php echo addslashes($class_info['section_name']); ?>';
    const filename = `Grades_${subjectCode}_${sectionName}_${new Date().toISOString().slice(0,10)}.xlsx`;
    
    XLSX.writeFile(wb, filename);
    showAlert('Grades exported to Excel successfully!', 'success');
}

// Import grades from Excel
function importGrades(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // Get first sheet
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            
            // Convert to array of arrays
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            
            let updated = 0;
            let notFound = [];
            
            // Skip header row (index 0)
            for (let i = 1; i < jsonData.length; i++) {
                const row = jsonData[i];
                if (!row || row.length < 4) continue;
                
                const studentNo = String(row[0] || '').trim();
                const midterm = parseFloat(row[2]) || 0;
                const final = parseFloat(row[3]) || 0;
                
                if (!studentNo) continue;
                
                // Find matching row by student number
                const tableRows = document.querySelectorAll('tbody tr');
                let found = false;
                
                tableRows.forEach(tableRow => {
                    const rowStudentNo = tableRow.querySelector('small.text-muted')?.textContent?.trim();
                    if (rowStudentNo === studentNo) {
                        tableRow.querySelector('.midterm-input').value = midterm;
                        tableRow.querySelector('.final-input').value = final;
                        calculateFinalGrade(tableRow);
                        updated++;
                        found = true;
                    }
                });
                
                if (!found) {
                    notFound.push(studentNo);
                }
            }
            
            // Reset file input
            input.value = '';
            
            let message = `Imported ${updated} grades from Excel.`;
            if (notFound.length > 0) {
                message += ` ${notFound.length} students not found.`;
            }
            
            if (updated > 0) {
                showAlert(message + ' Click "Save All" to persist changes.', 'success');
            } else {
                showAlert(message, 'warning');
            }
        } catch (error) {
            console.error('Import error:', error);
            showAlert('Failed to read Excel file. Please check the file format.', 'danger');
            input.value = '';
        }
    };
    
    reader.readAsArrayBuffer(file);
}
</script>
</body>
</html>