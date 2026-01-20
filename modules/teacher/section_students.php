<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

/** 
 * ==========================================
 * BACKEND LOGIC - ABSOLUTELY UNTOUCHED
 * ==========================================
 */

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Verify teacher access
$verify = $conn->prepare("
    SELECT tsa.id FROM teacher_subject_assignments tsa
    WHERE tsa.teacher_id = ? AND tsa.curriculum_subject_id = ? AND tsa.is_active = 1 AND tsa.academic_year_id = ?
");
$verify->bind_param("iii", $teacher_id, $subject_id, $current_ay_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    header('Location: subjects.php');
    exit();
}

// Get section info
$section_query = $conn->prepare("
    SELECT s.*, 
           COALESCE(p.program_code, ss.strand_code) as program_code,
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
$section = $section_query->get_result()->fetch_assoc();

if (!$section) {
    header('Location: subjects.php');
    exit();
}

// Get subject info
$subject_query = $conn->prepare("SELECT * FROM curriculum_subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject = $subject_query->get_result()->fetch_assoc();

// Get students
$students_query = $conn->prepare("
    SELECT u.id, up.first_name, up.last_name, u.email,
           COALESCE(st.student_no, CONCAT('STU-', u.id)) as student_no,
           up.contact_no as phone,
           CONCAT(up.first_name, ' ', up.last_name) as name,
           ss.enrolled_at
    FROM section_students ss
    INNER JOIN users u ON ss.student_id = u.id
    INNER JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN students st ON u.id = st.user_id
    WHERE ss.section_id = ? AND ss.status = 'active'
    ORDER BY up.last_name, up.first_name
");
$students_query->bind_param("i", $section_id);
$students_query->execute();
$students_result = $students_query->get_result();

include '../../includes/header.php';
include '../../includes/sidebar.php'; 
?>

<style>
    /* --- SCROLL & LAYOUT ENGINE --- */
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #content { height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
    .header-fixed-part { flex: 0 0 auto; background: white; padding: 15px 30px; border-bottom: 1px solid #eee; z-index: 10; }
    .body-scroll-part { flex: 1 1 auto; overflow-y: auto; padding: 25px 30px 100px 30px; background-color: #f8f9fa; }

    /* --- FANTASTIC SECTION UI --- */
    .section-banner {
        background: linear-gradient(135deg, var(--blue) 0%, #001a33 100%);
        border-radius: 20px; padding: 30px; color: white; margin-bottom: 30px;
        box-shadow: 0 10px 25px rgba(0, 51, 102, 0.1);
        position: relative; overflow: hidden;
    }
    .section-banner i.bg-icon { position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.1; }

    .stat-pill-modern {
        background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
        padding: 6px 15px; border-radius: 50px; margin-right: 10px; font-size: 0.8rem;
        font-weight: 600; display: inline-flex; align-items: center; gap: 8px;
    }

    .main-card-modern {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden;
    }

    .table-modern thead th { 
        background: var(--blue); color: white; font-size: 0.7rem; text-transform: uppercase; 
        letter-spacing: 1px; padding: 15px 20px; position: sticky; top: -1px; z-index: 5;
    }
    .table-modern tbody td { padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #f1f1f1; font-size: 0.85rem; }

    .search-box-pill { border-radius: 50px; border: 1px solid #ddd; padding-left: 15px; font-size: 0.85rem; max-width: 300px; }

    .student-avatar {
        width: 35px; height: 35px; border-radius: 50%; background: var(--maroon);
        color: white; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.75rem; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .btn-action-pill {
        border-radius: 50px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;
        padding: 6px 15px; transition: 0.3s;
    }

    .breadcrumb-item { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    .breadcrumb-item a { color: var(--maroon); text-decoration: none; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; color: #ccc; font-size: 1.2rem; vertical-align: middle; }

    @media (max-width: 768px) { .header-fixed-part { flex-direction: column; gap: 10px; text-align: center; } .section-banner { text-align: center; } .section-banner .text-end { text-align: center !important; margin-top: 20px; } }
</style>

<!-- Part 1: Fixed Header -->
<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="subjects.php">My Classes</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($section['section_name']); ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-people-fill me-2 text-maroon"></i>Section Students</h4>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-action-pill shadow-sm" onclick="exportStudents()">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <a href="attendance.php?section_id=<?php echo $section_id; ?>&subject_id=<?php echo $subject_id; ?>" class="btn btn-primary btn-action-pill shadow-sm">
                <i class="bi bi-calendar-check me-1"></i> Attendance
            </a>
            <a href="grading.php?section_id=<?php echo $section_id; ?>&subject_id=<?php echo $subject_id; ?>" class="btn btn-warning btn-action-pill shadow-sm">
                <i class="bi bi-journal-check me-1"></i> Grades
            </a>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <!-- Section Banner Card -->
    <div class="section-banner animate__animated animate__fadeIn">
        <i class="bi bi-people bg-icon"></i>
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="badge bg-dark text-maroon mb-2 px-3 py-2 fw-bold"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($subject['subject_title']); ?></h3>
                <p class="mb-3 opacity-75 fw-semibold"><?php echo htmlspecialchars($section['program_name']); ?> | <?php echo htmlspecialchars($section['year_level_name']); ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="stat-pill-modern"><i class="bi bi-collection"></i> Section: <?php echo htmlspecialchars($section['section_name']); ?></span>
                    <span class="stat-pill-modern"><i class="bi bi-door-open"></i> Room: <?php echo htmlspecialchars($section['room'] ?? 'TBD'); ?></span>
                    <span class="stat-pill-modern"><i class="bi bi-book"></i> <?php echo $subject['units']; ?> Units</span>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="display-3 fw-extrabold mb-0"><?php echo $students_result->num_rows; ?></div>
                <small class="text-uppercase fw-bold opacity-50" style="letter-spacing: 2px;">Students Enrolled</small>
            </div>
        </div>
    </div>

    <!-- Student Table Card -->
    <div class="main-card-modern animate__animated animate__fadeInUp">
        <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-blue">Class Roster</h6>
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-light border-end-0 rounded-start-pill ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 rounded-end-pill modern-input" id="searchInput" placeholder="Filter names..." onkeyup="filterStudents()">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-modern align-middle mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 60px;">#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Contact</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_result->num_rows > 0): 
                        $count = 1;
                        while ($student = $students_result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td class="ps-4 text-muted fw-bold"><?php echo $count++; ?></td>
                        <td><span class="badge bg-dark text-maroon border border-maroon px-3"><?php echo htmlspecialchars($student['student_no']); ?></span></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="student-avatar me-3">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                </div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['name']); ?></div>
                            </div>
                        </td>
                        <td><small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small></td>
                        <td><small class="text-muted fw-bold"><?php echo htmlspecialchars($student['phone'] ?: 'N/A'); ?></small></td>
                        <td class="text-center pe-4">
                            <div class="btn-group shadow-xs">
                                <button class="btn btn-sm btn-white border px-3" onclick="viewStudentProfile(<?php echo $student['id']; ?>)" title="View Profile">
                                    <i class="bi bi-eye-fill text-info"></i>
                                </button>
                                <button class="btn btn-sm btn-white border px-3" onclick="viewStudentGrades(<?php echo $student['id']; ?>)" title="View Grades">
                                    <i class="bi bi-bar-chart-fill text-primary"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-people display-4 text-muted opacity-25"></i>
                            <h5 class="mt-3 text-muted">No Students Enrolled</h5>
                            <p class="small text-muted">There are no active student records found for this section.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Student Profile Modal (Modernized) -->
<div class="modal fade" id="studentProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header p-4 text-white" style="background: var(--blue); border: none;">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-badge me-2"></i>Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="studentProfileContent">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- --- JAVASCRIPT LOGIC - UNTOUCHED & RE-WIRED --- -->
<script>
function filterStudents() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
    });
}

function viewStudentProfile(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('studentProfileModal'));
    document.getElementById('studentProfileContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-maroon"></div><p class="mt-2 small text-muted text-uppercase fw-bold">Syncing Profile...</p></div>';
    modal.show();
    
    fetch('api/get_student_profile.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const s = data.student;
                document.getElementById('studentProfileContent').innerHTML = `
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto border-4 border-white shadow mb-3" style="width: 90px; height: 90px; background: var(--maroon); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
                            ${s.first_name.charAt(0)}${s.last_name.charAt(0)}
                        </div>
                        <h4 class="fw-bold text-dark mb-0">${s.first_name} ${s.last_name}</h4>
                        <span class="badge bg-blue rounded-pill px-3 mt-2">${s.student_id || 'ID UNASSIGNED'}</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-6"><div class="p-3 bg-white rounded-3 border shadow-xs"><label class="small fw-bold text-muted text-uppercase d-block mb-1" style="font-size:0.6rem;">Email</label><div class="small fw-bold text-dark text-truncate">${s.email || 'N/A'}</div></div></div>
                        <div class="col-6"><div class="p-3 bg-white rounded-3 border shadow-xs"><label class="small fw-bold text-muted text-uppercase d-block mb-1" style="font-size:0.6rem;">Phone</label><div class="small fw-bold text-dark">${s.phone || 'N/A'}</div></div></div>
                        <div class="col-6"><div class="p-3 bg-white rounded-3 border shadow-xs"><label class="small fw-bold text-muted text-uppercase d-block mb-1" style="font-size:0.6rem;">Gender</label><div class="small fw-bold text-dark">${s.gender || 'N/A'}</div></div></div>
                        <div class="col-6"><div class="p-3 bg-white rounded-3 border shadow-xs"><label class="small fw-bold text-muted text-uppercase d-block mb-1" style="font-size:0.6rem;">Birthday</label><div class="small fw-bold text-dark">${s.birthday || 'N/A'}</div></div></div>
                    </div>
                `;
            } else {
                document.getElementById('studentProfileContent').innerHTML = '<div class="alert alert-danger border-0">Data retrieval failed.</div>';
            }
        });
}

function viewStudentGrades(studentId) {
    window.location.href = 'grading.php?section_id=<?php echo $section_id; ?>&subject_id=<?php echo $subject_id; ?>&student_id=' + studentId;
}

function exportStudents() {
    window.open('api/export_students.php?section_id=<?php echo $section_id; ?>&subject_id=<?php echo $subject_id; ?>', '_blank');
}
</script>
</body>
</html>