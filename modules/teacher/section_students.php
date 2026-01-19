<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_TEACHER) {
    header('Location: ../../index.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$section_id = (int)($_GET['section_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

// Get current academic year
$current_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_ay_id = $current_ay['id'] ?? 0;

// Verify teacher has access to this subject
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

// Get students in this section
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
?>

<style>
    .info-card {
        background: linear-gradient(135deg, #003366 0%, #004080 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
    }
    
    .student-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .student-table thead {
        background: #f8f9fa;
    }
    
    .student-table th {
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #dee2e6;
        padding: 15px;
    }
    
    .student-table td {
        padding: 12px 15px;
        vertical-align: middle;
    }
    
    .student-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .action-btn {
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.85rem;
    }
    
    .stat-pill {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        margin-right: 10px;
        font-size: 0.9rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }
</style>

<?php include '../../includes/sidebar.php'; ?>

        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="subjects.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="bi bi-arrow-left"></i> Back to My Classes
                </a>
                <h4 class="mb-0" style="color: #003366;">
                    <i class="bi bi-people"></i> Section Students
                </h4>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" onclick="exportStudents()">
                    <i class="bi bi-download"></i> Export List
                </button>
                <a href="attendance.php?section_id=<?php echo $section_id; ?>&subject_id=<?php echo $subject_id; ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar-check"></i> Attendance
                </a>
                <a href="grading.php?section_id=<?php echo $section_id; ?>&subject_id=<?php echo $subject_id; ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-journal-check"></i> Grades
                </a>
            </div>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1"><?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['subject_title']); ?></h5>
                    <p class="mb-2 opacity-75"><?php echo htmlspecialchars($section['program_name']); ?> | <?php echo htmlspecialchars($section['year_level_name']); ?></p>
                    <div class="d-flex flex-wrap">
                        <span class="stat-pill"><i class="bi bi-collection"></i> Section: <?php echo htmlspecialchars($section['section_name']); ?></span>
                        <span class="stat-pill"><i class="bi bi-door-open"></i> Room: <?php echo htmlspecialchars($section['room'] ?? 'N/A'); ?></span>
                        <span class="stat-pill"><i class="bi bi-book"></i> <?php echo $subject['units']; ?> units</span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="display-4 fw-bold"><?php echo $students_result->num_rows; ?></div>
                    <small class="opacity-75">Students Enrolled</small>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-people-fill"></i> Student List</h6>
                <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search students..." style="width: 250px;" onkeyup="filterStudents()">
            </div>
            <div class="card-body p-0">
                <?php if ($students_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table student-table mb-0" id="studentsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 1;
                            while ($student = $students_result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 0.85rem;">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($student['name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info action-btn" onclick="viewStudentProfile(<?php echo $student['id']; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary action-btn" onclick="viewStudentGrades(<?php echo $student['id']; ?>)">
                                        <i class="bi bi-bar-chart"></i> Grades
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h5>No Students Enrolled</h5>
                    <p>There are no students enrolled in this section yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Student Profile Modal -->
<div class="modal fade" id="studentProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #003366; color: white;">
                <h5 class="modal-title"><i class="bi bi-person"></i> Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentProfileContent">
                Loading...
            </div>
        </div>
    </div>
</div>

</div> <!-- Close container-fluid -->

<script>
function filterStudents() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('studentsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    }
}

function viewStudentProfile(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('studentProfileModal'));
    document.getElementById('studentProfileContent').innerHTML = '<div class="text-center p-4"><i class="bi bi-arrow-repeat spin fs-1"></i></div>';
    modal.show();
    
    fetch('api/get_student_profile.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                document.getElementById('studentProfileContent').innerHTML = `
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 1.5rem;">
                            ${student.first_name.charAt(0)}${student.last_name.charAt(0)}
                        </div>
                        <h5 class="mb-1">${student.first_name} ${student.last_name}</h5>
                        <small class="text-muted">${student.student_id || 'No Student ID'}</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="text-muted small">Email</label>
                            <p class="mb-0 fw-bold">${student.email || 'N/A'}</p>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="text-muted small">Phone</label>
                            <p class="mb-0 fw-bold">${student.phone || 'N/A'}</p>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="text-muted small">Gender</label>
                            <p class="mb-0 fw-bold">${student.gender || 'N/A'}</p>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="text-muted small">Birthday</label>
                            <p class="mb-0 fw-bold">${student.birthday || 'N/A'}</p>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('studentProfileContent').innerHTML = '<div class="alert alert-danger">Error loading student profile</div>';
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

<?php include '../../includes/footer.php'; ?>
