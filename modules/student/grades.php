<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_STUDENT) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "My Grades";
$student_id = $_SESSION['user_id'];

/** 
 * --- AJAX HANDLER ---
 * We return JSON for the table and GPA when filtered, without reloading.
 */
if (isset($_GET['ajax'])) {
    $selected_term = $_GET['term'] ?? 'all';
    // Logic needs to be repeated inside the AJAX block to get fresh data
    $current_ay = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $current_ay_id = $current_ay['id'] ?? 0;
    
    $sec_info = $conn->query("SELECT s.id FROM section_students stu INNER JOIN sections s ON stu.section_id = s.id WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id LIMIT 1")->fetch_assoc();
    $sid = $sec_info['id'] ?? 0;

    $g_query = $conn->query("SELECT g.*, cs.subject_code, cs.subject_title FROM grades g INNER JOIN curriculum_subjects cs ON g.subject_id = cs.id WHERE g.student_id = $student_id AND g.section_id = $sid ORDER BY cs.subject_code");
    $list = [];
    while ($r = $g_query->fetch_assoc()) { $list[] = $r; }
    if (empty($list)) {
        $o_grades = $conn->query("SELECT g.*, c.course_code as subject_code, c.title as subject_title FROM grades g INNER JOIN classes cl ON g.class_id = cl.id INNER JOIN courses c ON cl.course_id = c.id WHERE g.student_id = $student_id ORDER BY c.course_code");
        while ($r = $o_grades->fetch_assoc()) { $list[] = $r; }
    }

    $total = 0; $count = 0;
    $html = '';
    foreach ($list as $grade) {
        $val = ($selected_term == 'all') ? ($grade['final_grade'] ?? 0) : ($grade[$selected_term] ?? 0);
        if ($val > 0) { $total += $val; $count++; }

        $rating = 'N/A';
        if ($val >= 97) $rating = '1.00'; elseif ($val >= 94) $rating = '1.25'; elseif ($val >= 91) $rating = '1.50'; elseif ($val >= 88) $rating = '1.75'; elseif ($val >= 85) $rating = '2.00'; elseif ($val >= 82) $rating = '2.25'; elseif ($val >= 79) $rating = '2.50'; elseif ($val >= 76) $rating = '2.75'; elseif ($val >= 75) $rating = '3.00'; elseif ($val > 0) $rating = '5.00';

        $rem = $val >= 75 ? 'PASSED' : ($val > 0 ? 'FAILED' : 'PENDING');
        $clr = ($rem == 'PASSED') ? 'success' : (($rem == 'FAILED') ? 'danger' : 'secondary');

        $html .= '<tr><td class="ps-4"><div class="fw-bold">'.$grade['subject_code'].'</div><small class="text-muted">'.$grade['subject_title'].'</small></td>';
        if ($selected_term == 'all') {
            $html .= '<td class="text-center small">'.(($grade['prelim'] > 0) ? number_format($grade['prelim'], 2) : '-').'</td>';
            $html .= '<td class="text-center small">'.(($grade['midterm'] > 0) ? number_format($grade['midterm'], 2) : '-').'</td>';
            $html .= '<td class="text-center small">'.(($grade['prefinal'] > 0) ? number_format($grade['prefinal'], 2) : '-').'</td>';
            $html .= '<td class="text-center small">'.(($grade['final'] > 0) ? number_format($grade['final'], 2) : '-').'</td>';
            $html .= '<td class="text-center fw-bold text-maroon">'.(($grade['final_grade'] > 0) ? number_format($grade['final_grade'], 2) : '-').'</td>';
        } else {
            $html .= '<td class="text-center fw-bold text-maroon">'.(($val > 0) ? number_format($val, 2) : '-').'</td>';
        }
        $html .= '<td class="text-center"><span class="badge bg-light text-dark border">'.$rating.'</span></td><td class="text-center pe-4"><span class="badge rounded-pill bg-'.$clr.'">'.$rem.'</span></td></tr>';
    }

    echo json_encode(['table' => $html, 'gpa' => ($count > 0 ? round($total / $count, 2) : "0.00")]);
    exit();
}

/** 
 * --- INITIAL LOAD LOGIC --- 
 */
$term_names = ['all' => 'All Terms', 'prelim' => 'Prelim', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Finals', 'final' => 'Finals'];
$current_ay = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

$section_info = $conn->query("SELECT s.*, COALESCE(p.program_code, ss.strand_code) as program_code FROM section_students stu INNER JOIN sections s ON stu.section_id = s.id LEFT JOIN programs p ON s.program_id = p.id LEFT JOIN shs_strands ss ON s.shs_strand_id = ss.id WHERE stu.student_id = $student_id AND stu.status = 'active' AND s.academic_year_id = $current_ay_id LIMIT 1")->fetch_assoc();
$section_id = $section_info['id'] ?? 0;

// Grade list logic (for initial total calculation)
$grades_query = $conn->query("SELECT g.*, cs.subject_code, cs.subject_title FROM grades g INNER JOIN curriculum_subjects cs ON g.subject_id = cs.id WHERE g.student_id = $student_id AND g.section_id = $section_id ORDER BY cs.subject_code");
$grades_list = [];
while ($row = $grades_query->fetch_assoc()) { $grades_list[] = $row; }
if (empty($grades_list)) {
    $old_grades = $conn->query("SELECT g.*, c.course_code as subject_code, c.title as subject_title FROM grades g INNER JOIN classes cl ON g.class_id = cl.id INNER JOIN courses c ON cl.course_id = c.id WHERE g.student_id = $student_id ORDER BY c.course_code");
    while ($row = $old_grades->fetch_assoc()) { $grades_list[] = $row; }
}

$total_grade = 0; $grade_count = 0;
foreach ($grades_list as $g) { if (($g['final_grade'] ?? 0) > 0) { $total_grade += $g['final_grade']; $grade_count++; } }
$gpa = $grade_count > 0 ? round($total_grade / $grade_count, 2) : 0;

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL FIX & LAYOUT --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- UI CARDS --- */
    .summary-card { background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px; }
    .gpa-hero { background: linear-gradient(135deg, var(--maroon) 0%, #4a0000 100%); border-radius: 15px; padding: 25px; color: white; margin-bottom: 30px; box-shadow: 0 10px 25px rgba(128,0,0,0.1); }
    .table-modern thead th { background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; padding: 15px 20px; position: sticky; top: -1px; z-index: 5; }

    /* --- PRINT STYLES --- */
    @media print {
        #sidebar, .navbar-custom, .header-fixed-part, .accordion, .legend-card, .btn-print-hide { display: none !important; }
        #content, .body-scroll-part { overflow: visible !important; height: auto !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .summary-card { box-shadow: none !important; border: 1px solid #000; width: 100% !important; }
        .table thead th { background-color: #eee !important; color: #000 !important; border: 1px solid #000 !important; }
        .table td { border: 1px solid #000 !important; }
    }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0 text-blue"><i class="bi bi-bar-chart-fill me-2 text-maroon"></i>My Academic Grades</h4>
            <small class="text-muted">Academic Year: <?php echo htmlspecialchars($current_ay['year_name']); ?></small>
        </div>
        <div class="d-flex gap-2 btn-print-hide">
            <select class="form-select form-select-sm rounded-pill shadow-sm" id="termAjaxFilter" style="min-width: 150px;">
                <?php foreach ($term_names as $key => $name): ?>
                    <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-light btn-sm border rounded-pill px-3" onclick="window.print()"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- GPA Hero -->
    <div class="gpa-hero animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="fw-bold mb-1 text-white"><?php echo htmlspecialchars($_SESSION['name']); ?></h4>
                <p class="mb-0 opacity-75 small fw-bold text-uppercase">
                    <?php echo htmlspecialchars($section_info['program_code'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($section_info['section_name'] ?? 'N/A'); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white bg-opacity-10 p-3 rounded-4 border border-white border-opacity-25 d-inline-block text-center">
                    <h1 class="mb-0 fw-bold" id="gpaDisplay"><?php echo number_format($gpa, 2); ?></h1>
                    <small class="text-uppercase fw-bold opacity-75" style="font-size: 0.6rem;">General Average</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Table Card -->
    <div class="summary-card animate__animated animate__fadeInUp" id="gradeTableCard">
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0">
                <thead id="dynamicHeader">
                    <!-- Loaded via AJAX -->
                </thead>
                <tbody id="dynamicBody">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- DETAILED COMPONENTS (BROUGHT BACK) -->
    <?php if (!empty($detailed_grades)): ?>
    <div class="accordion mb-4 animate__animated animate__fadeInUp" id="gradeDetails">
        <h6 class="fw-bold mb-3 text-blue text-uppercase small">Detailed Components Breakdown</h6>
        <?php $i = 0; foreach ($detailed_grades as $code => $data): $i++; ?>
        <div class="accordion-item border-0 shadow-sm mb-2 rounded-3 overflow-hidden">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed fw-bold py-3" type="button" data-bs-toggle="collapse" data-bs-target="#acc<?php echo $i; ?>">
                    <span class="badge bg-maroon me-3"><?php echo $code; ?></span> <?php echo $data['subject_title']; ?>
                </button>
            </h2>
            <div id="acc<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#gradeDetails">
                <div class="accordion-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Component</th><th class="text-center">Score</th><th class="text-center">Max</th><th class="text-center">Weight</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['components'] as $c): ?>
                            <tr>
                                <td class="ps-3"><?php echo $c['component_name']; ?></td>
                                <td class="text-center fw-bold"><?php echo $c['score']; ?></td>
                                <td class="text-center"><?php echo $c['max_score']; ?></td>
                                <td class="text-center small"><?php echo $c['weight']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- GRADING SCALE LEGEND (BROUGHT BACK) -->
    <div class="card border-0 shadow-sm rounded-4 legend-card animate__animated animate__fadeInUp">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-muted small text-uppercase">Institutional Grading Scale</h6>
            <div class="row g-4 small">
                <div class="col-md-6 border-end">
                    <div class="d-flex justify-content-between mb-1"><span>96 - 100</span><strong>1.00 Excellent</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>91 - 95</span><strong>1.25 Very Good</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>86 - 90</span><strong>1.50 Good</strong></div>
                </div>
                <div class="col-md-6 ps-md-4">
                    <div class="d-flex justify-content-between mb-1"><span>75</span><strong>3.00 Passing</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>Below 75</span><strong class="text-danger">5.00 Failed</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>Incomplete</span><strong>INC</strong></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- REAL AJAX SCRIPT --- -->
<script>
$(document).ready(function() {
    // Initial Load
    fetchGrades('all');

    // On Term Filter Change
    $('#termAjaxFilter').on('change', function() {
        fetchGrades($(this).val());
    });
});

async function fetchGrades(term) {
    const card = $('#gradeTableCard');
    card.css('opacity', '0.4'); // Visual loading cue

    try {
        const response = await fetch(`?ajax=1&term=${term}`);
        const data = await response.json();

        // Update Headers
        let head = `<tr><th class="ps-4">Subject & Code</th>`;
        if (term === 'all') {
            head += `<th class="text-center">Prelim</th><th class="text-center">Midterm</th><th class="text-center">Pre-Final</th><th class="text-center">Final</th><th class="text-center">GWA</th>`;
        } else {
            head += `<th class="text-center">Term Grade</th>`;
        }
        head += `<th class="text-center">Rating</th><th class="text-center pe-4">Remarks</th></tr>`;

        // Inject Content
        $('#dynamicHeader').html(head);
        $('#dynamicBody').html(data.table);
        $('#gpaDisplay').text(data.gpa);
        
        card.css('opacity', '1');
    } catch (e) {
        console.error("AJAX Failed:", e);
        card.css('opacity', '1');
    }
}
</script>
</body>
</html>