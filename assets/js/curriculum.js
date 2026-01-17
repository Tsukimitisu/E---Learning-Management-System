// Curriculum Management JavaScript
$(document).ready(function() {
    // Initialize form handlers
    initializeFormHandlers();
});

function initializeFormHandlers() {
    // Track Management
    $('#addTrackForm').on('submit', function(e) {
        e.preventDefault();
        addTrack();
    });

    // Strand Management
    $('#addStrandForm').on('submit', function(e) {
        e.preventDefault();
        addStrand();
    });

    // Grade Level Management
    $('#addGradeForm').on('submit', function(e) {
        e.preventDefault();
        addGradeLevel();
    });

    // Subject Management
    $('#addSubjectForm').on('submit', function(e) {
        e.preventDefault();
        addSubject();
    });

    // Program Management
    $('#addProgramForm').on('submit', function(e) {
        e.preventDefault();
        addProgram();
    });

    // Year Level Management
    $('#addCollegeYearForm').on('submit', function(e) {
        e.preventDefault();
        addYearLevel();
    });
}

// Track Management Functions
function addTrack() {
    const formData = new FormData(document.getElementById('addTrackForm'));

    fetch('api/curriculum.php?action=add_track', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Track added successfully!', 'success');
            $('#addTrackModal').modal('hide');
            document.getElementById('addTrackForm').reset();
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error adding track', 'danger');
    });
}

function editTrack(trackId) {
    showAlert('Edit track functionality coming soon', 'info');
}

function deleteTrack(trackId) {
    if (confirm('Are you sure you want to delete this track?')) {
        showAlert('Delete track functionality coming soon', 'info');
    }
}

// Strand Management Functions
function addStrand() {
    const formData = new FormData(document.getElementById('addStrandForm'));

    fetch('api/curriculum.php?action=add_strand', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Strand added successfully!', 'success');
            $('#addStrandModal').modal('hide');
            document.getElementById('addStrandForm').reset();
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error adding strand', 'danger');
    });
}

function editStrand(strandId) {
    showAlert('Edit strand functionality coming soon', 'info');
}

function deleteStrand(strandId) {
    if (confirm('Are you sure you want to delete this strand?')) {
        showAlert('Delete strand functionality coming soon', 'info');
    }
}

// Grade Level Management Functions
function addGradeLevel() {
    const formData = new FormData(document.getElementById('addGradeForm'));

    fetch('api/curriculum.php?action=add_grade_level', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Grade level added successfully!', 'success');
            $('#addGradeModal').modal('hide');
            document.getElementById('addGradeForm').reset();
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error adding grade level', 'danger');
    });
}

function editGradeLevel(gradeId) {
    showAlert('Edit grade level functionality coming soon', 'info');
}

// Subject Management Functions
function addSubject() {
    const formData = new FormData(document.getElementById('addSubjectForm'));

    fetch('modules/school_admin/process/add_subject.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Subject added successfully!', 'success');
            $('#addSubjectModal').modal('hide');
            document.getElementById('addSubjectForm').reset();
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error adding subject', 'danger');
    });
}

function editSubject(subjectId) {
    showAlert('Edit subject functionality coming soon', 'info');
}

function deleteSubject(subjectId) {
    if (confirm('Are you sure you want to delete this subject?')) {
        showAlert('Delete subject functionality coming soon', 'info');
    }
}

// Program Management Functions
function addProgram() {
    const formData = new FormData(document.getElementById('addProgramForm'));

    fetch('modules/school_admin/process/add_program.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Program added successfully!', 'success');
            $('#addProgramModal').modal('hide');
            document.getElementById('addProgramForm').reset();
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error adding program', 'danger');
    });
}

function editProgram(programId) {
    showAlert('Edit program functionality coming soon', 'info');
}

function deleteProgram(programId) {
    if (confirm('Are you sure you want to delete this program?')) {
        showAlert('Delete program functionality coming soon', 'info');
    }
}

// Year Level Management Functions
function addYearLevel() {
    const formData = new FormData(document.getElementById('addCollegeYearForm'));

    fetch('api/curriculum.php?action=add_year_level', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert('Year level added successfully!', 'success');
            $('#addCollegeYearModal').modal('hide');
            document.getElementById('addCollegeYearForm').reset();
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error adding year level', 'danger');
    });
}

function editCollegeYear(yearId) {
    showAlert('Edit year level functionality coming soon', 'info');
}

// Utility Functions
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alertContainer').html(alertHtml);

    // Auto-hide after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}

// Subject Assignment Functions
function assignSubject(subjectId) {
    showAlert('Subject assignment functionality coming soon', 'info');
}

function assignCollegeCourse(courseCode) {
    showAlert('College course assignment functionality coming soon', 'info');
}

function editCollegeCourse(courseCode) {
    showAlert('Edit college course functionality coming soon', 'info');
}