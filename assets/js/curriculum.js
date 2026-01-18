// Curriculum Management JavaScript
const BASE_URL = '/elms_system/';

$(document).ready(function() {
    initializeFormHandlers();
});

// Toggle SHS/College fields based on subject type selection
function updateSubjectForm() {
    const subjectType = document.getElementById('subjectTypeSelect')?.value;
    const shsFields = document.getElementById('shsFields');
    const collegeFields = document.getElementById('collegeFields');
    
    if (!shsFields || !collegeFields) return;
    
    if (subjectType && subjectType.startsWith('shs_')) {
        shsFields.style.display = 'block';
        collegeFields.style.display = 'none';
    } else if (subjectType === 'college') {
        shsFields.style.display = 'none';
        collegeFields.style.display = 'block';
    } else {
        shsFields.style.display = 'none';
        collegeFields.style.display = 'none';
    }
}

function initializeFormHandlers() {
    // Track forms
    $('#addTrackForm').on('submit', function(e) { e.preventDefault(); addTrack(); });
    $('#editTrackForm').on('submit', function(e) { e.preventDefault(); updateTrack(); });

    // Strand forms
    $('#addStrandForm').on('submit', function(e) { e.preventDefault(); addStrand(); });
    $('#editStrandForm').on('submit', function(e) { e.preventDefault(); updateStrand(); });

    // Grade Level forms
    $('#addGradeForm').on('submit', function(e) { e.preventDefault(); addGradeLevel(); });
    $('#editGradeForm').on('submit', function(e) { e.preventDefault(); updateGradeLevel(); });

    // Subject forms
    $('#addSubjectForm').on('submit', function(e) { e.preventDefault(); addSubject(); });
    $('#editSubjectForm').on('submit', function(e) { e.preventDefault(); updateSubject(); });
    $('#assignSubjectForm').on('submit', function(e) { e.preventDefault(); submitAssignSubject(); });

    // College subject forms
    $('#addCollegeSubjectForm').on('submit', function(e) { e.preventDefault(); addCollegeSubject(); });
    $('#assignCollegeSubjectForm').on('submit', function(e) { e.preventDefault(); assignCollegeSubjectSubmit(); });

    // Program forms
    $('#addProgramForm').on('submit', function(e) { e.preventDefault(); addProgram(); });
    $('#editProgramForm').on('submit', function(e) { e.preventDefault(); updateProgram(); });

    // College Course forms
    $('#addCollegeCourseForm').on('submit', function(e) { e.preventDefault(); addCollegeCourse(); });
    $('#editCollegeCourseForm').on('submit', function(e) { e.preventDefault(); updateCollegeCourse(); });

    // College Year forms
    $('#addCollegeYearForm').on('submit', function(e) { e.preventDefault(); addYearLevel(); });
    $('#editCollegeYearForm').on('submit', function(e) { e.preventDefault(); updateYearLevel(); });

    // College Course Assignment form
    $('#assignCollegeCourseForm').on('submit', function(e) { e.preventDefault(); assignCollegeCourse(); });
}

// ========== TRACK MANAGEMENT ==========
function addTrack() {
    const formData = new FormData(document.getElementById('addTrackForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_track.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Track added successfully!', 'success');
            $('#addTrackModal').modal('hide');
            document.getElementById('addTrackForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding track', 'danger'));
}

function editTrack(trackId) {
    const tracksData = window.tracksData || [];
    const track = tracksData.find(t => t.id == trackId);
    if (track) {
        document.getElementById('editTrackId').value = track.id;
        document.getElementById('editTrackName').value = track.name || track.track_name;
        document.getElementById('editTrackDescription').value = track.description || '';
        document.getElementById('editTrackStatus').value = track.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editTrackModal')).show();
    }
}

function updateTrack() {
    const formData = new FormData(document.getElementById('editTrackForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_track.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Track updated successfully!', 'success');
            $('#editTrackModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating track', 'danger'));
}

function deleteTrack(trackId) {
    if (confirm('Are you sure you want to delete this track? This will also delete all associated strands.')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ track_id: trackId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Track deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting track', 'danger'));
    }
}

// ========== STRAND MANAGEMENT ==========
function addStrand() {
    const formData = new FormData(document.getElementById('addStrandForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_strand.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Strand added successfully!', 'success');
            $('#addStrandModal').modal('hide');
            document.getElementById('addStrandForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding strand', 'danger'));
}

function editStrand(strandId) {
    const strandsData = window.strandsData || [];
    const strand = strandsData.find(s => s.id == strandId);
    if (strand) {
        document.getElementById('editStrandId').value = strand.id;
        document.getElementById('editStrandTrack').value = strand.track_id;
        document.getElementById('editStrandName').value = strand.name || strand.strand_name;
        document.getElementById('editStrandDescription').value = strand.description || '';
        document.getElementById('editStrandStatus').value = strand.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editStrandModal')).show();
    }
}

function updateStrand() {
    const formData = new FormData(document.getElementById('editStrandForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_strand.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Strand updated successfully!', 'success');
            $('#editStrandModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating strand', 'danger'));
}

function deleteStrand(strandId) {
    if (confirm('Are you sure you want to delete this strand? This may affect subject assignments.')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_strand.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ strand_id: strandId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Strand deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting strand', 'danger'));
    }
}

// ========== GRADE LEVEL MANAGEMENT ==========
function addGradeLevel() {
    const formData = new FormData(document.getElementById('addGradeForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_grade_level.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Grade level added successfully!', 'success');
            $('#addGradeModal').modal('hide');
            document.getElementById('addGradeForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding grade level', 'danger'));
}

function editGradeLevel(gradeId) {
    const gradeLevelsData = window.gradeLevelsData || [];
    const grade = gradeLevelsData.find(g => g.id == gradeId);
    if (grade) {
        document.getElementById('editGradeId').value = grade.id;
        document.getElementById('editGradeName').value = grade.name || grade.grade_name;
        document.getElementById('editGradeSemesters').value = grade.semesters || grade.semesters_count;
        document.getElementById('editGradeStatus').value = grade.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editGradeModal')).show();
    }
}

function updateGradeLevel() {
    const formData = new FormData(document.getElementById('editGradeForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_grade_level.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Grade level updated successfully!', 'success');
            $('#editGradeModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating grade level', 'danger'));
}

// ========== SUBJECT MANAGEMENT ==========
function addSubject() {
    const formData = new FormData(document.getElementById('addSubjectForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_subject.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Subject added successfully!', 'success');
            $('#addSubjectModal').modal('hide');
            document.getElementById('addSubjectForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding subject', 'danger'));
}

function editSubject(subjectId) {
    fetch(BASE_URL + `modules/school_admin/process/get_subject.php?id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const subject = data.subject;
                document.getElementById('editSubjectId').value = subject.id;
                document.getElementById('editSubjectCode').value = subject.subject_code;
                document.getElementById('editSubjectTitle').value = subject.subject_title;
                document.getElementById('editSubjectCategory').value = subject.subject_type || 'shs_core';
                document.getElementById('editSubjectUnits').value = subject.units;
                document.getElementById('editSubjectHours').value = subject.lecture_hours || subject.hours || 0;
                document.getElementById('editSubjectPrerequisites').value = subject.prerequisites || '';
                document.getElementById('editSubjectStatus').value = subject.is_active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
            } else showAlert('Failed to load subject data', 'danger');
        })
        .catch(error => showAlert('Error loading subject', 'danger'));
}

function updateSubject() {
    const formData = new FormData(document.getElementById('editSubjectForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_subject.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Subject updated successfully!', 'success');
            $('#editSubjectModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating subject', 'danger'));
}

function deleteSubject(subjectId) {
    if (confirm('Are you sure you want to delete this subject?')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_subject.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subject_id: subjectId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Subject deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting subject', 'danger'));
    }
}

function assignSubject(subjectId) {
    const input = document.querySelector('#assignSubjectForm input[name="subject_id"]');
    if (input) input.value = subjectId;
    new bootstrap.Modal(document.getElementById('assignSubjectModal')).show();
}

function submitAssignSubject() {
    const formData = new FormData(document.getElementById('assignSubjectForm'));
    fetch(BASE_URL + 'modules/school_admin/process/assign_subject.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Subject assigned successfully!', 'success');
            $('#assignSubjectModal').modal('hide');
            document.getElementById('assignSubjectForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error assigning subject', 'danger'));
}

// ========== PROGRAM MANAGEMENT ==========
function addProgram() {
    const formData = new FormData(document.getElementById('addProgramForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_college_program.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Program added successfully!', 'success');
            $('#addProgramModal').modal('hide');
            document.getElementById('addProgramForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding program', 'danger'));
}

function editProgram(programId) {
    const programs = window.collegePrograms || [];
    const program = programs.find(p => p.id == programId);
    if (program) {
        document.getElementById('editProgramId').value = program.id;
        document.getElementById('editProgramCode').value = program.program_code;
        document.getElementById('editProgramName').value = program.program_name;
        document.getElementById('editProgramLevel').value = program.degree_level;
        document.getElementById('editProgramDuration').value = program.duration_years || 4;
        document.getElementById('editProgramUnits').value = program.total_units || '';
        document.getElementById('editProgramDescription').value = program.description || '';
        document.getElementById('editProgramStatus').value = program.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editProgramModal')).show();
    }
}

function updateProgram() {
    const formData = new FormData(document.getElementById('editProgramForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_college_program.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Program updated successfully!', 'success');
            $('#editProgramModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating program', 'danger'));
}

function deleteProgram(programId) {
    if (confirm('Are you sure you want to delete this program? This will also delete all associated year levels.')) {
        fetch('modules/school_admin/process/delete_college_program.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ program_id: programId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Program deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting program', 'danger'));
    }
}

// ========== COLLEGE COURSE MANAGEMENT ==========
function addCollegeCourse() {
    const formData = new FormData(document.getElementById('addCollegeCourseForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_college_course.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('College course added successfully!', 'success');
            $('#addCollegeCourseModal').modal('hide');
            document.getElementById('addCollegeCourseForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding college course', 'danger'));
}

function editCollegeCourse(courseCode) {
    fetch(BASE_URL + `modules/school_admin/process/get_college_course.php?code=${courseCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const course = data.course;
                document.getElementById('editCourseCode').value = course.course_code;
                document.getElementById('editCourseCodeInput').value = course.course_code;
                document.getElementById('editCourseTitle').value = course.course_title;
                document.getElementById('editCourseCategory').value = course.category;
                document.getElementById('editCourseUnits').value = course.units;
                document.getElementById('editCourseHours').value = course.hours || 3;
                document.getElementById('editCourseLectureHours').value = course.lecture_hours || '';
                document.getElementById('editCourseLabHours').value = course.lab_hours || '';
                document.getElementById('editCoursePrerequisites').value = course.prerequisites || '';
                document.getElementById('editCourseDescription').value = course.description || '';
                document.getElementById('editCourseStatus').value = course.is_active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editCollegeCourseModal')).show();
            } else showAlert('Failed to load course data', 'danger');
        })
        .catch(error => showAlert('Error loading course', 'danger'));
}

function updateCollegeCourse() {
    const formData = new FormData(document.getElementById('editCollegeCourseForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_college_course.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('College course updated successfully!', 'success');
            $('#editCollegeCourseModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating college course', 'danger'));
}

function deleteCollegeCourse(courseCode) {
    if (confirm('Are you sure you want to delete this course?')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_college_course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ course_code: courseCode })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('College course deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting course', 'danger'));
    }
}

// ========== YEAR LEVEL MANAGEMENT ==========
function addYearLevel() {
    const formData = new FormData(document.getElementById('addCollegeYearForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_college_year_level.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Year level added successfully!', 'success');
            $('#addCollegeYearModal').modal('hide');
            document.getElementById('addCollegeYearForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error adding year level', 'danger'));
}

function editCollegeYear(yearId) {
    const yearLevels = window.collegeYearLevels || [];
    const year = yearLevels.find(y => y.id == yearId);
    if (year) {
        document.getElementById('editYearId').value = year.id;
        document.getElementById('editYearName').value = year.year_name;
        document.getElementById('editYearNumber').value = year.year_level;
        document.getElementById('editYearSemesters').value = year.semesters_count;
        document.getElementById('editYearStatus').value = year.is_active ? '1' : '0';
        new bootstrap.Modal(document.getElementById('editCollegeYearModal')).show();
    }
}

function updateYearLevel() {
    const formData = new FormData(document.getElementById('editCollegeYearForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_college_year_level.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Year level updated successfully!', 'success');
            $('#editCollegeYearModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating year level', 'danger'));
}

function deleteYearLevel(yearId) {
    if (confirm('Are you sure you want to delete this year level? This may affect subject assignments.')) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_college_year_level.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ year_id: yearId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Year level deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting year level', 'danger'));
    }
}

// ========== COLLEGE COURSE ASSIGNMENT ==========
function assignCollegeCourse() {
    const formData = new FormData(document.getElementById('assignCollegeCourseForm'));
    fetch(BASE_URL + 'modules/school_admin/process/assign_college_course.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Course assigned successfully!', 'success');
            $('#assignCollegeCourseModal').modal('hide');
            document.getElementById('assignCollegeCourseForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error assigning course', 'danger'));
}

// ========== UTILITY FUNCTIONS ==========
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    const container = document.getElementById('alertContainer');
    if (container) {
        container.innerHTML = alertHtml;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function loadStrands(trackId) {
    const strandSelect = document.getElementById('strandSelect');
    if (!strandSelect) return;
    
    strandSelect.innerHTML = '<option value="">-- Select Strand --</option>';
    const strandsData = window.strandsData || [];
    const trackStrands = strandsData.filter(strand => strand.track_id == trackId);
    trackStrands.forEach(strand => {
        const option = document.createElement('option');
        option.value = strand.id;
        option.textContent = strand.strand_name;
        strandSelect.appendChild(option);
    });
}

// ========== COLLEGE SUBJECT MANAGEMENT ==========
function addCollegeSubject() {
    const formData = new FormData(document.getElementById('addCollegeSubjectForm'));
    fetch(BASE_URL + 'modules/school_admin/process/add_subject.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('College subject added successfully!', 'success');
            $('#addCollegeSubjectModal').modal('hide');
            document.getElementById('addCollegeSubjectForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error adding college subject', 'danger'));
}

function assignCollegeSubject(subjectId) {
    const input = document.querySelector('#assignCollegeSubjectForm input[name="subject_id"]');
    if (input) input.value = subjectId;
    const select = document.querySelector('#assignCollegeSubjectForm select[name="subject_id"]');
    if (select && subjectId) select.value = subjectId;
    new bootstrap.Modal(document.getElementById('assignCollegeSubjectModal')).show();
}

function assignCollegeSubjectSubmit() {
    const formData = new FormData(document.getElementById('assignCollegeSubjectForm'));
    fetch(BASE_URL + 'modules/school_admin/process/assign_subject.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('College subject assigned successfully!', 'success');
            $('#assignCollegeSubjectModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error assigning college subject', 'danger'));
}

// ========== COLLEGE SUBJECT MANAGEMENT ==========
function editCollegeSubject(subjectId) {
    fetch(BASE_URL + `modules/school_admin/process/get_subject.php?id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const subject = data.subject;
                document.getElementById('editCollegeSubjectId').value = subject.id;
                document.getElementById('editCollegeSubjectCode').value = subject.subject_code;
                document.getElementById('editCollegeSubjectTitle').value = subject.subject_title;
                document.getElementById('editCollegeSubjectUnits').value = subject.units;
                document.getElementById('editCollegeSubjectLectureHours').value = subject.lecture_hours || 0;
                document.getElementById('editCollegeSubjectLabHours').value = subject.lab_hours || 0;
                document.getElementById('editCollegeSubjectSemester').value = subject.semester || 1;
                document.getElementById('editCollegeSubjectProgram').value = subject.program_id || '';
                document.getElementById('editCollegeSubjectYearLevel').value = subject.year_level_id || '';
                document.getElementById('editCollegeSubjectPrerequisites').value = subject.prerequisites || '';
                document.getElementById('editCollegeSubjectStatus').value = subject.is_active ? '1' : '0';
                new bootstrap.Modal(document.getElementById('editCollegeSubjectModal')).show();
            } else showAlert('Failed to load subject data', 'danger');
        })
        .catch(error => showAlert('Error loading subject', 'danger'));
}

function updateCollegeSubject() {
    const formData = new FormData(document.getElementById('editCollegeSubjectForm'));
    fetch(BASE_URL + 'modules/school_admin/process/update_subject.php', {
        method: 'POST', body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Subject updated successfully!', 'success');
            $('#editCollegeSubjectModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else showAlert(data.message, 'danger');
    })
    .catch(error => showAlert('Error updating subject', 'danger'));
}

function deleteCollegeSubject(subjectId, subjectCode) {
    if (confirm(`Are you sure you want to delete subject "${subjectCode}"? This cannot be undone.`)) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_subject.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subject_id: subjectId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Subject deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting subject', 'danger'));
    }
}

function deleteCollegeProgram(programId, programCode) {
    if (confirm(`Are you sure you want to delete program "${programCode}"? This will also delete all associated year levels and subjects.`)) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_college_program.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ program_id: programId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Program deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting program', 'danger'));
    }
}

function deleteCollegeYear(yearLevelId, yearLevelName) {
    if (confirm(`Are you sure you want to delete year level "${yearLevelName}"?`)) {
        fetch(BASE_URL + 'modules/school_admin/process/delete_college_year_level.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ year_level_id: yearLevelId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('Year level deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else showAlert(data.message, 'danger');
        })
        .catch(error => showAlert('Error deleting year level', 'danger'));
    }
}