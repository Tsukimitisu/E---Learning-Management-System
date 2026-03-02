<?php
require_once '../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../index.php');
    exit();
}

$page_title = "Certificates";

$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

include '../../includes/header.php';
?>

<link rel="stylesheet" href="css/certificates.css">

<div class="header-fixed-part animate__animated animate__fadeInDown">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--blue);"><i class="bi bi-award-fill me-2 text-maroon"></i>Document Generation</h4>
            <p class="text-muted small mb-0">Generate and print official student certifications</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm small">
                <i class="bi bi-printer-fill me-1 text-primary"></i> Print Ready Layouts
            </span>
        </div>
    </div>
</div>

<!-- Part 2: Scrollable Body -->
<div class="body-scroll-part">
    
    <div id="alertContainer"></div>

    <div class="cert-card animate__animated animate__fadeInUp">
        <div class="p-4 p-md-5">
            <!-- Modern Pill Navigation -->
            <ul class="nav nav-pills nav-pills-modern mb-5" id="certTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="enrollment-tab" data-bs-toggle="pill" data-bs-target="#enrollment" type="button">
                        <i class="bi bi-file-earmark-check me-2"></i>Enrollment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="grade-tab" data-bs-toggle="pill" data-bs-target="#grade" type="button">
                        <i class="bi bi-graph-up-arrow me-2"></i>Grade Report
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completion-tab" data-bs-toggle="pill" data-bs-target="#completion" type="button">
                        <i class="bi bi-mortarboard me-2"></i>Completion
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="certTabsContent">
                <!-- Enrollment Tab -->
                <div class="tab-pane fade show active" id="enrollment" role="tabpanel">
                    <div class="form-section-title">Certification of Enrollment</div>
                    <div class="animate__animated animate__fadeIn">
                        <?php $tabType = 'enrollment'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>

                <!-- Grade Tab -->
                <div class="tab-pane fade" id="grade" role="tabpanel">
                    <div class="form-section-title">Scholastic Grade Report</div>
                    <div class="animate__animated animate__fadeIn">
                        <?php $tabType = 'grade_report'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>

                <!-- Completion Tab -->
                <div class="tab-pane fade" id="completion" role="tabpanel">
                    <div class="form-section-title">Certificate of Course Completion</div>
                    <div class="animate__animated animate__fadeIn">
                        <?php $tabType = 'completion'; include 'process/_certificate_form.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Section -->
    <div class="row mt-4 g-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
        <div class="col-md-6">
            <div class="p-4 rounded-4 bg-white shadow-sm border-start border-maroon border-4">
                <h6 class="fw-bold text-dark mb-2">Usage Note</h6>
                <p class="small text-muted mb-0">Generated PDF certificates will open in a new browser tab. Please ensure your pop-up blocker is disabled for this domain.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-4 rounded-4 bg-white shadow-sm border-start border-blue border-4">
                <h6 class="fw-bold text-dark mb-2">Data Accuracy</h6>
                <p class="small text-muted mb-0">Certificates are based on verified academic records. If grades are missing, please confirm they have been finalized by the instructor.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<style>
/* Student list items */
.cert-student-list .student-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.15s;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cert-student-list .student-item:hover {
    background: #e8f4fd;
}
.cert-student-list .student-item:last-child {
    border-bottom: none;
}
.cert-student-list .student-item .student-main {
    display: flex;
    align-items: center;
    gap: 10px;
}
.cert-student-list .student-item .student-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--blue);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem;
    flex-shrink: 0;
}
.cert-student-list .student-item .student-info .name {
    font-weight: 600; font-size: 0.9rem; color: #333;
}
.cert-student-list .student-item .student-info .meta {
    font-size: 0.75rem; color: #888;
}
.cert-student-list .no-results {
    padding: 20px; text-align: center; color: #999; font-size: 0.85rem;
}
.cert-student-selected {
    border-color: #28a745 !important;
    background: #f0fff4 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- State for each form ----
    const formStates = {};
    let filterOptions = null; // shared across all forms

    // Load filter options once
    fetch('process/get_filter_options.php')
        .then(r => r.json())
        .then(data => {
            filterOptions = data;
            // Populate filter dropdowns on all forms
            document.querySelectorAll('.cert-filter-program').forEach(sel => {
                const progGroup = sel.querySelector('.filter-programs-group');
                const strandGroup = sel.querySelector('.filter-strands-group');
                if (progGroup) {
                    (data.programs || []).forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = 'p_' + p.id;
                        opt.textContent = p.program_code + ' - ' + p.program_name;
                        opt.dataset.type = 'college';
                        progGroup.appendChild(opt);
                    });
                }
                if (strandGroup) {
                    (data.strands || []).forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = 's_' + s.id;
                        opt.textContent = s.strand_code + ' - ' + s.strand_name;
                        opt.dataset.type = 'shs';
                        strandGroup.appendChild(opt);
                    });
                }
            });

            // Initial load for each form
            document.querySelectorAll('.certificate-form').forEach(form => {
                const formId = form.id;
                formStates[formId] = { selectedStudent: null, debounceTimer: null };
                updateYearLevelOptions(formId);
                loadStudents(formId);
            });
        })
        .catch(err => console.error('Failed to load filter options:', err));

    // Update year level dropdown based on education type
    function updateYearLevelOptions(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        const typeFilter = form.querySelector('.cert-filter-type');
        const yearFilter = form.querySelector('.cert-filter-year');
        const programFilter = form.querySelector('.cert-filter-program');
        if (!yearFilter || !filterOptions) return;

        const eduType = typeFilter ? typeFilter.value : '';
        const programVal = programFilter ? programFilter.value : '';

        yearFilter.innerHTML = '<option value="">All Year/Grade</option>';

        if (eduType === 'shs' || programVal.startsWith('s_')) {
            (filterOptions.shs_grade_levels || []).forEach(yl => {
                const opt = document.createElement('option');
                opt.value = yl.id;
                opt.textContent = yl.level_name;
                yearFilter.appendChild(opt);
            });
        } else if (eduType === 'college' || programVal.startsWith('p_')) {
            // If specific program selected, show that program's year levels
            const progId = programVal.startsWith('p_') ? parseInt(programVal.substring(2)) : 0;
            if (progId > 0) {
                (filterOptions.program_year_levels || []).filter(yl => yl.program_id == progId).forEach(yl => {
                    const opt = document.createElement('option');
                    opt.value = yl.id;
                    opt.textContent = yl.year_name;
                    yearFilter.appendChild(opt);
                });
            } else {
                (filterOptions.college_year_levels || []).forEach(yl => {
                    const opt = document.createElement('option');
                    opt.value = yl.id;
                    opt.textContent = yl.level_name;
                    yearFilter.appendChild(opt);
                });
            }
        } else {
            // Show all
            (filterOptions.college_year_levels || []).forEach(yl => {
                const opt = document.createElement('option');
                opt.value = yl.id;
                opt.textContent = yl.level_name;
                yearFilter.appendChild(opt);
            });
            (filterOptions.shs_grade_levels || []).forEach(yl => {
                const opt = document.createElement('option');
                opt.value = yl.id;
                opt.textContent = yl.level_name;
                yearFilter.appendChild(opt);
            });
        }
    }

    // ---- Search Students ----
    function loadStudents(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        const searchInput = form.querySelector('.cert-search-input');
        const programFilter = form.querySelector('.cert-filter-program');
        const yearFilter = form.querySelector('.cert-filter-year');
        const typeFilter = form.querySelector('.cert-filter-type');
        const semesterFilter = form.querySelector('.cert-filter-semester');
        const listContainer = form.querySelector('.cert-student-list');

        const q = searchInput ? searchInput.value.trim() : '';
        const programVal = programFilter ? programFilter.value : '';
        const yearVal = yearFilter ? yearFilter.value : '';
        const eduType = typeFilter ? typeFilter.value : '';
        const semesterVal = semesterFilter ? semesterFilter.value : '';

        // Build query string
        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (yearVal) params.append('year_level', yearVal);
        if (programVal.startsWith('p_')) params.append('program_id', programVal.substring(2));
        if (programVal.startsWith('s_')) params.append('strand_id', programVal.substring(2));
        if (eduType) params.append('education_type', eduType);
        if (semesterVal) params.append('semester', semesterVal);
        params.append('limit', '30');

        listContainer.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Searching...</div>';

        fetch('process/search_students.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                const students = data.students || [];
                if (students.length === 0) {
                    listContainer.innerHTML = '<div class="no-results"><i class="bi bi-search me-1"></i>No students found matching your criteria</div>';
                    return;
                }

                let html = '';
                students.forEach(s => {
                    const initials = (s.full_name || '').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
                    const typeBadge = s.program_type === 'shs' 
                        ? '<span class="badge bg-success" style="font-size:0.65rem">SHS</span>' 
                        : '<span class="badge bg-primary" style="font-size:0.65rem">College</span>';
                    const ylBadge = s.year_level_name 
                        ? `<span class="badge bg-secondary" style="font-size:0.65rem">${escHtml(s.year_level_name)}</span>` 
                        : '';

                    html += `
                        <div class="student-item" data-student='${JSON.stringify(s).replace(/'/g, "&#39;")}'>
                            <div class="student-main">
                                <div class="student-avatar">${initials}</div>
                                <div class="student-info">
                                    <div class="name">${escHtml(s.full_name)}</div>
                                    <div class="meta">${escHtml(s.student_no)} &middot; ${escHtml(s.program_code || 'N/A')}</div>
                                </div>
                            </div>
                            <div class="d-flex gap-1 align-items-center">
                                ${ylBadge} ${typeBadge}
                            </div>
                        </div>`;
                });
                listContainer.innerHTML = html;

                // Click handlers
                listContainer.querySelectorAll('.student-item').forEach(item => {
                    item.addEventListener('click', () => selectStudent(formId, JSON.parse(item.dataset.student)));
                });
            })
            .catch(() => {
                listContainer.innerHTML = '<div class="no-results text-danger">Error loading students</div>';
            });
    }

    function selectStudent(formId, student) {
        const form = document.getElementById(formId);
        if (!form) return;

        formStates[formId].selectedStudent = student;
        form.querySelector('.cert-student-id').value = student.user_id;

        // Show selected card
        const selectedCard = form.querySelector('.cert-student-selected');
        selectedCard.classList.remove('d-none');
        selectedCard.querySelector('.selected-student-name').textContent = student.full_name;
        selectedCard.querySelector('.selected-student-no').textContent = student.student_no;
        selectedCard.querySelector('.selected-student-program').textContent = student.program_code || student.program_type.toUpperCase();

        // Hide list
        form.querySelector('.cert-student-list').classList.add('d-none');

        // Enable submit
        form.querySelector('button[type="submit"]').disabled = false;
    }

    function clearStudent(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        formStates[formId].selectedStudent = null;
        form.querySelector('.cert-student-id').value = '';

        form.querySelector('.cert-student-selected').classList.add('d-none');
        form.querySelector('.cert-student-list').classList.remove('d-none');
        form.querySelector('button[type="submit"]').disabled = true;

        loadStudents(formId);
    }

    // ---- Event Bindings ----
    // Search inputs with debounce
    document.querySelectorAll('.cert-search-input').forEach(input => {
        input.addEventListener('input', function() {
            const formId = this.dataset.form;
            clearTimeout(formStates[formId]?.debounceTimer);
            formStates[formId].debounceTimer = setTimeout(() => loadStudents(formId), 300);
        });
    });

    // Filter changes
    document.querySelectorAll('.cert-filter-program, .cert-filter-year, .cert-filter-semester').forEach(sel => {
        sel.addEventListener('change', function() {
            loadStudents(this.dataset.form);
        });
    });

    // Education type filter - also updates program/year options
    document.querySelectorAll('.cert-filter-type').forEach(sel => {
        sel.addEventListener('change', function() {
            const formId = this.dataset.form;
            const form = document.getElementById(formId);
            if (!form) return;
            const eduType = this.value;
            const programFilter = form.querySelector('.cert-filter-program');

            // Show/hide program groups based on education type
            if (programFilter) {
                programFilter.value = '';
                const progGroup = programFilter.querySelector('.filter-programs-group');
                const strandGroup = programFilter.querySelector('.filter-strands-group');
                if (progGroup) progGroup.style.display = (eduType === 'shs') ? 'none' : '';
                if (strandGroup) strandGroup.style.display = (eduType === 'college') ? 'none' : '';
            }

            updateYearLevelOptions(formId);
            loadStudents(formId);
        });
    });

    // Program filter also updates year levels
    document.querySelectorAll('.cert-filter-program').forEach(sel => {
        sel.addEventListener('change', function() {
            const formId = this.dataset.form;
            updateYearLevelOptions(formId);
            loadStudents(formId);
        });
    });

    // Clear student
    document.querySelectorAll('.cert-clear-student').forEach(btn => {
        btn.addEventListener('click', function() {
            clearStudent(this.dataset.form);
        });
    });

    // Form submissions
    document.querySelectorAll('.certificate-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const studentId = form.querySelector('.cert-student-id').value;
            if (!studentId) {
                Swal.fire({ icon: 'warning', title: 'Oops', text: 'Please select a student first.' });
                return;
            }

            const formData = new FormData(form);
            const type = form.getAttribute('data-type');

            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }
            params.append('certificate_type', type);

            window.open('process/generate_certificate.php?' + params.toString(), '_blank');
        });
    });

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
});
</script>
</body>
</html>