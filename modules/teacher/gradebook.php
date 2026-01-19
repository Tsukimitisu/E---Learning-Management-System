<?php
require_once '../../config/init.php';

$user_role = $_SESSION['role_id'] ?? $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || $user_role != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$selected_term = $_GET['term'] ?? 'prelim'; // Default to prelim
$teacher_id = $_SESSION['user_id'];

// Valid terms
$valid_terms = ['prelim', 'midterm', 'prefinal', 'final'];
if (!in_array($selected_term, $valid_terms)) {
    $selected_term = 'prelim';
}

// Term display names
$term_names = [
    'prelim' => 'Prelim',
    'midterm' => 'Midterm', 
    'prefinal' => 'Pre-Finals',
    'final' => 'Finals'
];

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

// Determine if this is a College or SHS subject
$is_college = !empty($subject_info['program_id']);
$is_shs = !empty($subject_info['shs_strand_id']) || !empty($subject_info['shs_grade_level_id']);

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
    'quarterly_exam_weight' => 20,
    'is_college' => $is_college,
    'is_shs' => $is_shs
];

// Get students from section_students table with notes field
$students = $conn->prepare("
    SELECT 
        u.id as user_id,
        COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
        CONCAT(up.last_name, ', ', up.first_name) as student_name,
        g.id as grade_id,
        g.prelim,
        g.midterm,
        g.prefinal,
        g.final,
        g.final_grade,
        g.remarks,
        g.notes
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

// Generate export password based on subject and section
$export_password = strtoupper(substr(md5($subject_info['subject_code'] . $section_info['section_name'] . $current_ay_id), 0, 8));

$page_title = "Gradebook - " . $class_info['subject_code'];
include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 1050; position: relative; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }
    
    /* Fix dropdown visibility */
    .header-fixed-part .dropdown-menu {
        z-index: 1060 !important;
        position: absolute !important;
    }
    .header-fixed-part .dropdown {
        position: relative;
    }

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
    
    .notes-input {
        width: 150px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        padding: 5px 10px;
        font-size: 0.85rem;
        transition: 0.2s;
    }
    .notes-input:focus {
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(0,64,128,0.1);
        outline: none;
    }

    .computed-grade {
        font-weight: 800;
        color: var(--blue);
        font-size: 1.1rem;
    }

    /* Rating styles */
    .rating-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    /* SHS Ratings */
    .rating-excellent { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
    .rating-very-good { background: linear-gradient(135deg, #17a2b8, #6f42c1); color: white; }
    .rating-good { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
    .rating-satisfactory { background: linear-gradient(135deg, #fd7e14, #dc3545); color: white; }
    .rating-needs-improvement { background: linear-gradient(135deg, #dc3545, #6c757d); color: white; }
    .rating-na { background: #e9ecef; color: #6c757d; }
    
    /* College Ratings (1.0 - 5.0 scale) */
    .rating-college { font-weight: 800; font-size: 1rem; }
    .rating-1-00 { background: linear-gradient(135deg, #198754, #20c997); color: white; }
    .rating-1-25 { background: linear-gradient(135deg, #20c997, #0dcaf0); color: white; }
    .rating-1-50 { background: linear-gradient(135deg, #0dcaf0, #0d6efd); color: white; }
    .rating-1-75 { background: linear-gradient(135deg, #0d6efd, #6610f2); color: white; }
    .rating-2-00 { background: linear-gradient(135deg, #6610f2, #6f42c1); color: white; }
    .rating-2-25 { background: linear-gradient(135deg, #6f42c1, #d63384); color: white; }
    .rating-2-50 { background: linear-gradient(135deg, #d63384, #fd7e14); color: white; }
    .rating-2-75 { background: linear-gradient(135deg, #fd7e14, #ffc107); color: #333; }
    .rating-3-00 { background: linear-gradient(135deg, #ffc107, #ffca2c); color: #333; }
    .rating-5-00 { background: linear-gradient(135deg, #dc3545, #6c757d); color: white; }

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
    
    .btn-export {
        background-color: #198754;
        color: white;
        border: none;
        font-weight: 600;
    }
    .btn-export:hover {
        background-color: #146c43;
        color: white;
    }
    
    .btn-import {
        background-color: #0d6efd;
        color: white;
        border: none;
        font-weight: 600;
    }
    .btn-import:hover {
        background-color: #0a58ca;
        color: white;
    }
    
    .btn-save-row {
        background: linear-gradient(135deg, var(--maroon), #a00000);
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: 0.3s;
    }
    .btn-save-row:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        color: white;
    }

    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; vertical-align: middle; }
    
    .password-info {
        background: linear-gradient(135deg, #fff3cd, #ffeeba);
        border-left: 4px solid #ffc107;
        padding: 12px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    @media (max-width: 576px) {
        .header-fixed-part { padding: 15px; }
        .body-scroll-part { padding: 15px 15px 100px 15px; }
        .notes-input { width: 100px; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="grading.php">Grading</a></li>
                    <li class="breadcrumb-item"><a href="grading_sections.php?subject_id=<?php echo $subject_id; ?>"><?php echo htmlspecialchars($class_info['subject_code']); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($class_info['section_name']); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);">
                <?php echo htmlspecialchars($class_info['section_name'] ?: 'N/A'); ?> <span class="text-muted fw-light mx-2">|</span> <span style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($class_info['subject_title']); ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <!-- Term Filter Dropdown -->
            <select class="form-select form-select-sm rounded-pill shadow-sm term-filter" id="termFilter" style="width: auto; min-width: 150px; font-weight: 600;" onchange="changeTerm(this.value)">
                <option value="prelim" <?php echo $selected_term == 'prelim' ? 'selected' : ''; ?>>📋 Prelim</option>
                <option value="midterm" <?php echo $selected_term == 'midterm' ? 'selected' : ''; ?>>📋 Midterm</option>
                <option value="prefinal" <?php echo $selected_term == 'prefinal' ? 'selected' : ''; ?>>📋 Pre-Finals</option>
                <option value="final" <?php echo $selected_term == 'final' ? 'selected' : ''; ?>>📋 Finals</option>
            </select>
            <!-- Export Dropdown -->
            <div class="dropdown">
                <button class="btn btn-export btn-sm px-4 rounded-pill shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i> EXPORT
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <li><h6 class="dropdown-header"><i class="bi bi-file-earmark-pdf me-2"></i>PDF Format</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToPDF(); return false;"><i class="bi bi-file-pdf text-danger me-2"></i>Export as PDF</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><i class="bi bi-file-earmark-excel me-2"></i>Excel Format</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToExcel(true); return false;"><i class="bi bi-pencil-square text-primary me-2"></i>Excel (Editable)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToExcel(false); return false;"><i class="bi bi-lock text-warning me-2"></i>Excel (Protected)</a></li>
                </ul>
            </div>
            <button class="btn btn-import btn-sm px-4 rounded-pill shadow-sm" onclick="document.getElementById('importFile').click()" title="Import from Excel">
                <i class="bi bi-upload me-1"></i> IMPORT
            </button>
            <input type="file" id="importFile" accept=".xlsx,.xls" style="display:none" onchange="importGrades(this)">
            <button class="btn btn-save-all shadow-sm" onclick="saveAllGrades()">
                <i class="bi bi-cloud-check me-2"></i> SAVE ALL
            </button>
            <a href="grading_sections.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Content -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>
    
    <!-- Export Info Banner -->
    <div class="password-info animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <i class="bi bi-file-earmark-arrow-down-fill fs-4 me-3 text-primary"></i>
            <div>
                <span class="fw-bold text-dark">Export & Import Options</span>
                <div class="small text-muted">
                    <i class="bi bi-download me-1"></i> <strong>Export:</strong> Choose PDF or Excel (Editable / Protected with password: <code class="bg-white px-2 py-1 rounded border"><?php echo $export_password; ?></code>)
                    <br>
                    <i class="bi bi-upload me-1"></i> <strong>Import:</strong> Upload Excel file - format is validated automatically
                </div>
            </div>
        </div>
    </div>

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
                        <th class="ps-4" style="min-width: 250px;">Names</th>
                        <th class="text-center" style="min-width: 120px;">Average <span class="badge bg-primary rounded-pill ms-1 term-label"><?php echo $term_names[$selected_term]; ?></span></th>
                        <th class="text-center" style="min-width: 140px;">Rating</th>
                        <th class="text-center" style="min-width: 120px;">Remarks</th>
                        <th class="text-center" style="min-width: 180px;">Notes</th>
                        <th class="text-center" style="min-width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    while ($student = $students->fetch_assoc()): 
                        $avg = $student['final_grade'] ?? 0;
                        
                        // Determine rating based on average - different for College vs SHS
                        if ($class_info['is_college']) {
                            // Philippine College Grading Scale
                            if ($avg >= 97) {
                                $rating = '1.00';
                                $rating_class = 'rating-college rating-1-00';
                            } elseif ($avg >= 94) {
                                $rating = '1.25';
                                $rating_class = 'rating-college rating-1-25';
                            } elseif ($avg >= 91) {
                                $rating = '1.50';
                                $rating_class = 'rating-college rating-1-50';
                            } elseif ($avg >= 88) {
                                $rating = '1.75';
                                $rating_class = 'rating-college rating-1-75';
                            } elseif ($avg >= 85) {
                                $rating = '2.00';
                                $rating_class = 'rating-college rating-2-00';
                            } elseif ($avg >= 82) {
                                $rating = '2.25';
                                $rating_class = 'rating-college rating-2-25';
                            } elseif ($avg >= 79) {
                                $rating = '2.50';
                                $rating_class = 'rating-college rating-2-50';
                            } elseif ($avg >= 76) {
                                $rating = '2.75';
                                $rating_class = 'rating-college rating-2-75';
                            } elseif ($avg >= 75) {
                                $rating = '3.00';
                                $rating_class = 'rating-college rating-3-00';
                            } elseif ($avg > 0) {
                                $rating = '5.00';
                                $rating_class = 'rating-college rating-5-00';
                            } else {
                                $rating = 'N/A';
                                $rating_class = 'rating-na';
                            }
                        } else {
                            // SHS Descriptive Rating
                            if ($avg >= 95) {
                                $rating = 'Excellent';
                                $rating_class = 'rating-excellent';
                            } elseif ($avg >= 90) {
                                $rating = 'Very Good';
                                $rating_class = 'rating-very-good';
                            } elseif ($avg >= 85) {
                                $rating = 'Good';
                                $rating_class = 'rating-good';
                            } elseif ($avg >= 75) {
                                $rating = 'Satisfactory';
                                $rating_class = 'rating-satisfactory';
                            } elseif ($avg > 0) {
                                $rating = 'Needs Improvement';
                                $rating_class = 'rating-needs-improvement';
                            } else {
                                $rating = 'N/A';
                                $rating_class = 'rating-na';
                            }
                        }
                    ?>
                    <?php 
                        // Determine remarks based on average
                        $remarks_display = 'N/A';
                        $remarks_class = 'bg-secondary';
                        if ($avg >= 75) {
                            $remarks_display = 'PASSED';
                            $remarks_class = 'bg-success';
                        } elseif ($avg > 0) {
                            $remarks_display = 'FAILED';
                            $remarks_class = 'bg-danger';
                        }
                    ?>
                    <tr data-student-id="<?php echo $student['user_id']; ?>" data-grade-id="<?php echo $student['grade_id'] ?? 0; ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="badge bg-light text-dark rounded-pill"><?php echo $counter; ?></span>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                    <small class="text-muted student-no"><?php echo htmlspecialchars($student['student_no']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php 
                                // Get grade value for selected term
                                $term_grade = $student[$selected_term] ?? 0;
                            ?>
                            <input type="number" class="grade-input term-grade-input shadow-sm" 
                                   value="<?php echo $term_grade ? number_format($term_grade, 2) : ''; ?>" 
                                   min="0" max="100" step="0.01" placeholder="0.00"
                                   data-prelim="<?php echo $student['prelim'] ?? 0; ?>"
                                   data-midterm="<?php echo $student['midterm'] ?? 0; ?>"
                                   data-prefinal="<?php echo $student['prefinal'] ?? 0; ?>"
                                   data-final="<?php echo $student['final'] ?? 0; ?>">
                        </td>
                        <td class="text-center">
                            <span class="rating-badge <?php echo $rating_class; ?>"><?php echo $rating; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge remarks-badge rounded-pill px-3 py-2 <?php echo $remarks_class; ?>">
                                <?php echo $remarks_display; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <input type="text" class="notes-input shadow-sm" 
                                   value="<?php echo htmlspecialchars($student['notes'] ?? ''); ?>" 
                                   placeholder="Notes...">
                        </td>
                        <td class="text-center">
                            <button class="btn btn-save-row save-grade-btn" title="Save">
                                <i class="bi bi-check2 me-1"></i> SAVE
                            </button>
                        </td>
                    </tr>
                    <?php 
                        $counter++;
                        endwhile; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SheetJS Library for Excel Import/Export -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js" onerror="console.error('Failed to load XLSX library')"></script>

<!-- --- JAVASCRIPT LOGIC - Updated for term-based grading with dropdown --- -->
<script>
const SECTION_ID = <?php echo $section_id; ?>;
const SUBJECT_ID = <?php echo $subject_id; ?>;
const EXPORT_PASSWORD = '<?php echo $export_password; ?>';
const IS_COLLEGE = <?php echo $class_info['is_college'] ? 'true' : 'false'; ?>;
const SELECTED_TERM = '<?php echo $selected_term; ?>';
const TERM_NAMES = {
    'prelim': 'Prelim',
    'midterm': 'Midterm',
    'prefinal': 'Pre-Finals',
    'final': 'Finals'
};

// Term change function - called from inline onchange
function changeTerm(term) {
    console.log('changeTerm called with:', term);
    const url = new URL(window.location.href);
    url.searchParams.set('term', term);
    console.log('Redirecting to:', url.toString());
    window.location.href = url.toString();
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - Gradebook JS initialized');
    console.log('XLSX library loaded:', typeof XLSX !== 'undefined');
    
    // Update rating and remarks when grade changes
    document.querySelectorAll('.term-grade-input').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const grade = parseFloat(this.value) || 0;
            updateRatingAndRemarks(row, grade);
        });
    });

    // Save individual grade
    document.querySelectorAll('.save-grade-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            saveGrade(row, this);
        });
    });
});

function getRating(average) {
    if (IS_COLLEGE) {
        // Philippine College Grading Scale
        if (average >= 97) return { text: '1.00', class: 'rating-college rating-1-00' };
        if (average >= 94) return { text: '1.25', class: 'rating-college rating-1-25' };
        if (average >= 91) return { text: '1.50', class: 'rating-college rating-1-50' };
        if (average >= 88) return { text: '1.75', class: 'rating-college rating-1-75' };
        if (average >= 85) return { text: '2.00', class: 'rating-college rating-2-00' };
        if (average >= 82) return { text: '2.25', class: 'rating-college rating-2-25' };
        if (average >= 79) return { text: '2.50', class: 'rating-college rating-2-50' };
        if (average >= 76) return { text: '2.75', class: 'rating-college rating-2-75' };
        if (average >= 75) return { text: '3.00', class: 'rating-college rating-3-00' };
        if (average > 0) return { text: '5.00', class: 'rating-college rating-5-00' };
        return { text: 'N/A', class: 'rating-na' };
    } else {
        // SHS Descriptive Rating
        if (average >= 95) return { text: 'Excellent', class: 'rating-excellent' };
        if (average >= 90) return { text: 'Very Good', class: 'rating-very-good' };
        if (average >= 85) return { text: 'Good', class: 'rating-good' };
        if (average >= 75) return { text: 'Satisfactory', class: 'rating-satisfactory' };
        if (average > 0) return { text: 'Needs Improvement', class: 'rating-needs-improvement' };
        return { text: 'N/A', class: 'rating-na' };
    }
}

function updateRatingAndRemarks(row, grade) {
    if (grade === undefined) {
        grade = parseFloat(row.querySelector('.term-grade-input').value) || 0;
    }
    const rating = getRating(grade);
    const remarks = grade >= 75 ? 'PASSED' : (grade > 0 ? 'FAILED' : 'N/A');
    
    // Update rating badge
    const ratingBadge = row.querySelector('.rating-badge');
    ratingBadge.textContent = rating.text;
    ratingBadge.className = 'rating-badge ' + rating.class;
    
    // Update remarks badge
    const remarksBadge = row.querySelector('.remarks-badge');
    if (remarksBadge) {
        remarksBadge.textContent = remarks;
        remarksBadge.className = 'badge remarks-badge rounded-pill px-3 py-2 ' + 
            (remarks === 'PASSED' ? 'bg-success' : (remarks === 'FAILED' ? 'bg-danger' : 'bg-secondary'));
    }
}

async function saveGrade(row, btn) {
    const studentId = row.dataset.studentId;
    const gradeId = row.dataset.gradeId || 0;
    
    // Get the grade input element
    const gradeInput = row.querySelector('.term-grade-input');
    const currentGrade = parseFloat(gradeInput.value) || 0;
    
    // Get stored values for all terms from data attributes
    let prelim = parseFloat(gradeInput.dataset.prelim) || 0;
    let midterm = parseFloat(gradeInput.dataset.midterm) || 0;
    let prefinal = parseFloat(gradeInput.dataset.prefinal) || 0;
    let finalGrade = parseFloat(gradeInput.dataset.final) || 0;
    
    // Update the appropriate term based on selected dropdown
    switch(SELECTED_TERM) {
        case 'prelim': prelim = currentGrade; break;
        case 'midterm': midterm = currentGrade; break;
        case 'prefinal': prefinal = currentGrade; break;
        case 'final': finalGrade = currentGrade; break;
    }
    
    // Update data attributes
    gradeInput.dataset.prelim = prelim;
    gradeInput.dataset.midterm = midterm;
    gradeInput.dataset.prefinal = prefinal;
    gradeInput.dataset.final = finalGrade;
    
    // Calculate overall average from all terms
    let count = 0;
    let total = 0;
    if (prelim > 0) { count++; total += prelim; }
    if (midterm > 0) { count++; total += midterm; }
    if (prefinal > 0) { count++; total += prefinal; }
    if (finalGrade > 0) { count++; total += finalGrade; }
    const overallAverage = count > 0 ? (total / count) : 0;
    
    const notes = row.querySelector('.notes-input').value || '';
    const remarks = overallAverage >= 75 ? 'PASSED' : (overallAverage > 0 ? 'FAILED' : '');
    
    // Show loading state on button
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('section_id', SECTION_ID);
    formData.append('subject_id', SUBJECT_ID);
    formData.append('grade_id', gradeId);
    formData.append('term', SELECTED_TERM);
    formData.append('term_grade', currentGrade.toFixed(2));
    formData.append('prelim', prelim.toFixed(2));
    formData.append('midterm', midterm.toFixed(2));
    formData.append('prefinal', prefinal.toFixed(2));
    formData.append('final', finalGrade.toFixed(2));
    formData.append('final_grade', overallAverage.toFixed(2));
    formData.append('remarks', remarks);
    formData.append('notes', notes);
    
    try {
        const response = await fetch('api/update_grade.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            // Update grade_id for new records
            if (data.grade_id) {
                row.dataset.gradeId = data.grade_id;
            }
            
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-save-row');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-save-row');
                btn.disabled = false;
            }, 1500);
            return true;
        } else {
            throw new Error(data.message || 'Failed to save');
        }
    } catch (error) {
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.classList.add('btn-danger');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-danger');
            btn.disabled = false;
        }, 1500);
        showAlert(error.message || 'Failed to save grade', 'danger');
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
    let failed = 0;
    
    for (const row of rows) {
        const btn = row.querySelector('.save-grade-btn');
        const success = await saveGrade(row, btn);
        if (success) saved++;
        else failed++;
    }
    
    let message = `Successfully saved ${saved} student records for ${TERM_NAMES[SELECTED_TERM]} term.`;
    if (failed > 0) message += ` ${failed} failed.`;
    
    showAlert(message, failed > 0 ? 'warning' : 'success');
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
}

function showAlert(message, type) {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm animate__animated animate__shakeX" role="alert"><i class="bi ${type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-exclamation-circle-fill')} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alertContainer').innerHTML = alertHtml;
    document.querySelector('.body-scroll-part').scrollTo({ top: 0, behavior: 'smooth' });
}

// Common function to gather grade data for current term
function getGradeData() {
    const rows = document.querySelectorAll('tbody tr');
    const subjectCode = '<?php echo addslashes($class_info['subject_code']); ?>';
    const subjectTitle = '<?php echo addslashes($class_info['subject_title']); ?>';
    const sectionName = '<?php echo addslashes($class_info['section_name']); ?>';
    const programName = '<?php echo addslashes($class_info['program_name']); ?>';
    const yearLevel = '<?php echo addslashes($class_info['year_level_name']); ?>';
    const academicYear = '<?php echo ($current_ay['year_start'] ?? date('Y')) . '-' . ($current_ay['year_end'] ?? (date('Y')+1)); ?>';
    
    const students = [];
    rows.forEach((row, index) => {
        const gradeInput = row.querySelector('.term-grade-input');
        const currentGrade = parseFloat(gradeInput?.value) || '';
        
        students.push({
            no: index + 1,
            studentNo: row.querySelector('.student-no')?.textContent?.trim() || '',
            studentName: row.querySelector('.fw-bold.text-dark')?.textContent?.trim() || '',
            grade: currentGrade,
            rating: row.querySelector('.rating-badge')?.textContent?.trim() || '',
            remarks: row.querySelector('.remarks-badge')?.textContent?.trim() || '',
            notes: row.querySelector('.notes-input')?.value?.trim() || ''
        });
    });
    
    return {
        subjectCode,
        subjectTitle,
        sectionName,
        programName,
        yearLevel,
        academicYear,
        term: TERM_NAMES[SELECTED_TERM],
        exportDate: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
        students,
        totalStudents: rows.length
    };
}

// Export to Excel (Editable or Protected)
function exportToExcel(editable = true) {
    // Check if XLSX library is loaded
    if (typeof XLSX === 'undefined') {
        showAlert('<strong>Excel library not loaded.</strong> Please refresh the page and try again.', 'danger');
        console.error('XLSX library not loaded');
        return;
    }
    
    const data = getGradeData();
    console.log('Export data:', data);
    
    // Build formal academic header
    const sheetData = [
        ['STUDENT GRADE SHEET - ' + data.term.toUpperCase() + ' TERM'],
        [''],
        ['Institution:', 'E-LEARNING MANAGEMENT SYSTEM'],
        ['Academic Year:', data.academicYear],
        ['Term:', data.term],
        ['Subject Code:', data.subjectCode],
        ['Subject Title:', data.subjectTitle],
        ['Program:', data.programName],
        ['Year Level:', data.yearLevel],
        ['Section:', data.sectionName],
        ['Export Date:', data.exportDate],
        [''],
        ['═══════════════════════════════════════════════════════════════════════════════════════════════════════'],
        [''],
        ['NO.', 'STUDENT NUMBER', 'STUDENT NAME', 'AVERAGE', 'RATING', 'REMARKS', 'NOTES'],
    ];
    
    // Add student data
    data.students.forEach(student => {
        sheetData.push([student.no, student.studentNo, student.studentName, student.grade, student.rating, student.remarks, student.notes]);
    });
    
    // Add footer
    sheetData.push(['']);
    sheetData.push(['═══════════════════════════════════════════════════════════════════════════════════════════════════════']);
    sheetData.push(['']);
    sheetData.push(['Total Students:', data.totalStudents]);
    sheetData.push(['']);
    sheetData.push(['Prepared by: _______________________________', '', '', 'Date: _______________________']);
    sheetData.push(['']);
    sheetData.push(['Verified by: _______________________________', '', '', 'Date: _______________________']);
    
    if (!editable) {
        sheetData.push(['']);
        sheetData.push(['*** This document is password protected ***']);
        sheetData.push(['*** Password for editing: ' + EXPORT_PASSWORD + ' ***']);
    }
    
    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(sheetData);
    
    // Set column widths for formal look
    ws['!cols'] = [
        { wch: 6 },   // No.
        { wch: 18 },  // Student No
        { wch: 35 },  // Names
        { wch: 12 },  // Average
        { wch: 15 },  // Rating
        { wch: 12 },  // Remarks
        { wch: 30 }   // Notes
    ];
    
    // Merge cells for header title
    ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 6 } },
    ];
    
    // Add sheet protection only if not editable
    if (!editable) {
        ws['!protect'] = {
            password: EXPORT_PASSWORD,
            sheet: true,
            objects: true,
            scenarios: true,
            formatCells: false,
            formatColumns: false,
            formatRows: false,
            insertColumns: false,
            insertRows: false,
            insertHyperlinks: false,
            deleteColumns: false,
            deleteRows: false,
            selectLockedCells: true,
            sort: false,
            autoFilter: false,
            pivotTables: false,
            selectUnlockedCells: true
        };
    }
    
    XLSX.utils.book_append_sheet(wb, ws, 'Grade Sheet');
    
    // Download
    const suffix = editable ? 'Editable' : 'Protected';
    const termSuffix = SELECTED_TERM.charAt(0).toUpperCase() + SELECTED_TERM.slice(1);
    const filename = `GradeSheet_${data.subjectCode}_${data.sectionName}_${termSuffix}_${suffix}_${new Date().toISOString().slice(0,10)}.xlsx`;
    
    XLSX.writeFile(wb, filename);
    
    if (editable) {
        showAlert('<i class="bi bi-file-earmark-excel me-2"></i>Grade sheet exported as <strong>Editable Excel</strong> file for ' + data.term + ' term!', 'success');
    } else {
        showAlert('<i class="bi bi-file-earmark-excel me-2"></i>Grade sheet exported as <strong>Protected Excel</strong>! Password: <strong>' + EXPORT_PASSWORD + '</strong>', 'success');
    }
}

// Export to PDF
function exportToPDF() {
    const data = getGradeData();
    
    // Create a printable HTML content
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Grade Sheet - ${data.subjectCode} - ${data.term}</title>
            <style>
                @page { size: A4 landscape; margin: 15mm; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; line-height: 1.4; color: #000; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { font-size: 16pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
                .header h2 { font-size: 12pt; font-weight: normal; margin-bottom: 15px; }
                .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
                .info-table td { padding: 3px 10px 3px 0; font-size: 10pt; }
                .info-table td:first-child { font-weight: bold; width: 120px; }
                .grade-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .grade-table th, .grade-table td { border: 1px solid #000; padding: 5px 6px; text-align: left; }
                .grade-table th { background-color: #f0f0f0; font-weight: bold; text-align: center; font-size: 8pt; text-transform: uppercase; }
                .grade-table td { font-size: 9pt; }
                .grade-table td:nth-child(1) { text-align: center; width: 30px; }
                .grade-table td:nth-child(4) { text-align: center; width: 80px; }
                .grade-table td:nth-child(5) { text-align: center; width: 100px; }
                .grade-table td:nth-child(6) { text-align: center; width: 80px; }
                .passed { color: #155724; font-weight: bold; }
                .failed { color: #721c24; font-weight: bold; }
                .footer { margin-top: 30px; }
                .signature-row { display: flex; justify-content: space-between; margin-top: 40px; }
                .signature-box { width: 45%; }
                .signature-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; text-align: center; font-size: 10pt; }
                .total-row { font-weight: bold; margin-top: 10px; font-size: 10pt; }
                .print-date { text-align: right; font-size: 9pt; color: #666; margin-top: 20px; }
                @media print {
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Student Grade Sheet - ${data.term} Term</h1>
                <h2>E-Learning Management System</h2>
            </div>
            
            <table class="info-table">
                <tr><td>Academic Year:</td><td>${data.academicYear}</td><td>Program:</td><td>${data.programName}</td></tr>
                <tr><td>Subject Code:</td><td>${data.subjectCode}</td><td>Year Level:</td><td>${data.yearLevel}</td></tr>
                <tr><td>Subject Title:</td><td>${data.subjectTitle}</td><td>Section:</td><td>${data.sectionName}</td></tr>
                <tr><td>Term:</td><td>${data.term}</td><td></td><td></td></tr>
            </table>
            
            <table class="grade-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Student Number</th>
                        <th>Student Name</th>
                        <th>Average</th>
                        <th>Rating</th>
                        <th>Remarks</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.students.map(s => `
                        <tr>
                            <td>${s.no}</td>
                            <td>${s.studentNo}</td>
                            <td>${s.studentName}</td>
                            <td>${s.grade || ''}</td>
                            <td>${s.rating}</td>
                            <td class="${s.remarks === 'PASSED' ? 'passed' : (s.remarks === 'FAILED' ? 'failed' : '')}">${s.remarks}</td>
                            <td>${s.notes}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            <div class="total-row">Total Students: ${data.totalStudents}</div>
            
            <div class="signature-row">
                <div class="signature-box">
                    <div class="signature-line">Prepared by</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Verified by</div>
                </div>
            </div>
            
            <div class="print-date">Generated on: ${data.exportDate}</div>
        </body>
        </html>
    `;
    
    // Open print dialog
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Wait for content to load then print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            // Don't close automatically - let user save as PDF or print
        }, 250);
    };
    
    showAlert('<i class="bi bi-file-pdf me-2"></i>PDF preview opened for ' + data.term + ' term! Use <strong>Save as PDF</strong> in the print dialog or print directly.', 'success');
}

// Import grades from Excel - validates format automatically
function importGrades(input) {
    if (!input.files || !input.files[0]) {
        showAlert('No file selected.', 'warning');
        return;
    }
    
    // Check if XLSX library is loaded
    if (typeof XLSX === 'undefined') {
        showAlert('Excel library not loaded. Please refresh the page and try again.', 'danger');
        return;
    }
    
    const file = input.files[0];
    console.log('Importing file:', file.name);
    
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
            console.log('Parsed rows:', jsonData.length);
            
            // Validate format - look for required headers
            let headerRow = -1;
            
            for (let i = 0; i < Math.min(jsonData.length, 20); i++) {
                const row = jsonData[i];
                if (!row || row.length === 0) continue;
                
                // Convert row to uppercase strings for comparison
                const rowUpper = row.map(cell => String(cell || '').toUpperCase().trim());
                const rowJoined = rowUpper.join(' ');
                
                // Check if this row contains the required headers
                const hasStudentNo = rowJoined.includes('STUDENT NUMBER') || rowJoined.includes('STUDENT NO') || rowUpper.includes('NO.');
                const hasName = rowJoined.includes('STUDENT NAME') || rowJoined.includes('NAME');
                const hasGrade = rowJoined.includes('AVERAGE') || rowJoined.includes('GRADE');
                
                if (hasStudentNo && hasName && hasGrade) {
                    headerRow = i;
                    console.log('Found header row at:', i, rowUpper);
                    break;
                }
            }
            
            if (headerRow === -1) {
                showAlert('<strong>Invalid file format!</strong><br>The Excel file must contain columns: STUDENT NUMBER, STUDENT NAME, AVERAGE.<br>Please use the exported grade sheet as template.', 'danger');
                input.value = '';
                return;
            }
            
            // Find column indices
            const headerRowData = jsonData[headerRow].map(cell => String(cell || '').toUpperCase().trim());
            let studentNoCol = -1, avgCol = -1, notesCol = -1;
            
            headerRowData.forEach((cell, idx) => {
                if (cell.includes('STUDENT NUMBER') || cell.includes('STUDENT NO') || cell === 'NO.') studentNoCol = idx;
                if (cell.includes('AVERAGE') || cell === 'GRADE') avgCol = idx;
                if (cell.includes('NOTES') || cell.includes('NOTE')) notesCol = idx;
            });
            
            // If student number column not found by header, try second column (common pattern)
            if (studentNoCol === -1) {
                studentNoCol = 1; // Assume second column
            }
            if (avgCol === -1) {
                avgCol = 3; // Assume fourth column
            }
            
            console.log('Column indices - StudentNo:', studentNoCol, 'Average:', avgCol, 'Notes:', notesCol);
            
            let updated = 0;
            let notFound = [];
            let errors = [];
            
            // Process data rows (after header)
            for (let i = headerRow + 1; i < jsonData.length; i++) {
                const row = jsonData[i];
                if (!row || row.length < 2) continue;
                
                const studentNo = String(row[studentNoCol] || '').trim();
                const averageRaw = row[avgCol];
                const notes = notesCol >= 0 ? String(row[notesCol] || '').trim() : '';
                
                if (!studentNo || studentNo === '' || studentNo.includes('═') || studentNo.includes('Total')) continue;
                
                // Validate average is a number
                const average = parseFloat(averageRaw);
                if (averageRaw !== '' && averageRaw !== null && averageRaw !== undefined && isNaN(average)) {
                    continue; // Skip invalid rows silently
                }
                
                // Find matching row by student number
                const tableRows = document.querySelectorAll('tbody tr');
                let found = false;
                
                tableRows.forEach(tableRow => {
                    const studentNoElement = tableRow.querySelector('.student-no');
                    if (!studentNoElement) return;
                    
                    const rowStudentNo = studentNoElement.textContent.trim();
                    if (rowStudentNo === studentNo) {
                        const gradeInput = tableRow.querySelector('.term-grade-input');
                        if (gradeInput && !isNaN(average) && average >= 0) {
                            gradeInput.value = average.toFixed(2);
                            console.log('Updated grade for:', studentNo, 'to', average);
                        }
                        const notesInput = tableRow.querySelector('.notes-input');
                        if (notesInput && notes) {
                            notesInput.value = notes;
                        }
                        updateRatingAndRemarks(tableRow);
                        updated++;
                        found = true;
                    }
                });
                
                if (!found && studentNo && !studentNo.includes('Prepared') && !studentNo.includes('Verified')) {
                    notFound.push(studentNo);
                }
            }
            
            // Reset file input
            input.value = '';
            
            // Build result message
            let message = '';
            if (updated > 0) {
                message = `<strong>✓ Successfully imported ${updated} grades for ${TERM_NAMES[SELECTED_TERM]} term.</strong>`;
            } else {
                message = '<strong>No grades were imported.</strong> Make sure the student numbers in the file match those in the gradebook.';
            }
            
            if (notFound.length > 0 && notFound.length <= 5) {
                message += `<br><small class="text-muted">Students not found: ${notFound.join(', ')}</small>`;
            } else if (notFound.length > 5) {
                message += `<br><small class="text-muted">${notFound.length} students not found in this section.</small>`;
            }
            
            if (updated > 0) {
                message += '<br><strong>Click "SAVE ALL" to save changes to database.</strong>';
                showAlert(message, 'success');
            } else {
                showAlert(message, 'warning');
            }
        } catch (error) {
            console.error('Import error:', error);
            showAlert('<strong>Failed to read Excel file.</strong><br>Error: ' + error.message + '<br>Please ensure the file is a valid Excel format (.xlsx or .xls).', 'danger');
            input.value = '';
        }
    };
    
    reader.onerror = function() {
        showAlert('Failed to read the file. Please try again.', 'danger');
        input.value = '';
    };
    
    reader.readAsArrayBuffer(file);
}
</script>

<?php include '../../includes/footer.php'; ?>