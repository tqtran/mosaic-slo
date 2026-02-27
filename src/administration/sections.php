<?php
declare(strict_types=1);

/**
 * Sections Administration
 * 
 * Manage course sections.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

// Get selected term
$selectedTermFk = getSelectedTermFk();

// Handle POST requests
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $sectionId = trim($_POST['section_id'] ?? '');
                $crn = trim($_POST['crn'] ?? '');
                $instructorName = trim($_POST['instructor_name'] ?? '');
                $maxEnrollment = !empty($_POST['max_enrollment']) ? (int)$_POST['max_enrollment'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                }
                if (empty($sectionId)) {
                    $errors[] = 'Section ID is required';
                }
                
                // Check for duplicate
                if (empty($errors)) {
                    $dupCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}sections 
                         WHERE course_fk = ? AND term_fk = ? AND section_id = ?",
                        [$courseFk, $termFk, $sectionId],
                        'iis'
                    );
                    $dupRow = $dupCheck->fetch();
                    if ($dupRow['count'] > 0) {
                        $errors[] = 'This section already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}sections 
                         (course_fk, term_fk, section_id, crn, instructor_name, max_enrollment, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$courseFk, $termFk, $sectionId, $crn, $instructorName, $maxEnrollment, $isActive],
                        'iisssii'
                    );
                    $successMessage = 'Section added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['section_id'] ?? 0);
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $sectionId = trim($_POST['section_id_value'] ?? '');
                $crn = trim($_POST['crn'] ?? '');
                $instructorName = trim($_POST['instructor_name'] ?? '');
                $maxEnrollment = !empty($_POST['max_enrollment']) ? (int)$_POST['max_enrollment'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid section ID';
                }
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                }
                if (empty($sectionId)) {
                    $errors[] = 'Section ID is required';
                }
                
                // Check for duplicate (excluding current record)
                if (empty($errors)) {
                    $dupCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}sections 
                         WHERE course_fk = ? AND term_fk = ? AND section_id = ? AND sections_pk != ?",
                        [$courseFk, $termFk, $sectionId, $id],
                        'iisi'
                    );
                    $dupRow = $dupCheck->fetch();
                    if ($dupRow['count'] > 0) {
                        $errors[] = 'This section already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}sections 
                         SET course_fk = ?, term_fk = ?, section_id = ?, crn = ?, instructor_name = ?, 
                             max_enrollment = ?, is_active = ?, updated_at = NOW() 
                         WHERE sections_pk = ?",
                        [$courseFk, $termFk, $sectionId, $crn, $instructorName, $maxEnrollment, $isActive, $id],
                        'iisssiii'
                    );
                    $successMessage = 'Section updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['section_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "DELETE FROM {$dbPrefix}sections WHERE sections_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Section deleted successfully';
                } else {
                    $errorMessage = 'Invalid section ID';
                }
                break;
                
            case 'toggle':
                $id = (int)($_POST['section_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}sections SET is_active = NOT is_active, updated_at = NOW() WHERE sections_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Section status updated successfully';
                } else {
                    $errorMessage = 'Invalid section ID';
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch courses for dropdown
$coursesResult = $db->query("
    SELECT courses_pk, course_number, course_name
    FROM {$dbPrefix}courses 
    WHERE is_active = 1 
    ORDER BY course_number
");
$courses = $coursesResult->fetchAll();

// Fetch terms for dropdown
$termResult = $db->query("
    SELECT terms_pk, term_code, term_name 
    FROM {$dbPrefix}terms 
    WHERE is_active = 1 
    ORDER BY term_code ASC
");
$terms = $termResult->fetchAll();

// Default to first term if none selected
if (!$selectedTermFk && !empty($terms)) {
    $selectedTermFk = $terms[0]['terms_pk'];
    $_SESSION['selected_term_fk'] = $selectedTermFk;
}

// Get selected term name and code
$selectedTermName = '';
$selectedTermCode = '';
if ($selectedTermFk && !empty($terms)) {
    foreach ($terms as $term) {
        if ($term['terms_pk'] == $selectedTermFk) {
            $selectedTermName = $term['term_name'];
            $selectedTermCode = $term['term_code'];
            break;
        }
    }
}

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Section Management';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_sections',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Sections']
    ]
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active">Sections</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Sections Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Sections</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="fas fa-plus"></i> Add Section
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="sectionsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course Number</th>
                            <th>Section ID</th>
                            <th>Course Name</th>
                            <th>CRN</th>
                            <th>Instructor</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSectionModalLabel">
                        <i class="fas fa-plus"></i> Add Section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="courseFk" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="courseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['courses_pk'] ?>">
                                <?= htmlspecialchars($course['course_number'] . ' - ' . $course['course_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="termFk" class="form-label">Term <span class="text-danger">*</span></label>
                        <select class="form-select" id="termFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sectionId" class="form-label">Section ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sectionId" name="section_id" 
                               maxlength="20" placeholder="01, 02, A, B, etc." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="crn" class="form-label">CRN</label>
                        <input type="text" class="form-control" id="crn" name="crn" maxlength="20">
                    </div>
                    
                    <div class="mb-3">
                        <label for="instructorName" class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="instructorName" name="instructor_name" maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="maxEnrollment" class="form-label">Max Enrollment</label>
                        <input type="number" class="form-control" id="maxEnrollment" name="max_enrollment" min="1">
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="section_id" id="editSectionPk">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalLabel">
                        <i class="fas fa-edit"></i> Edit Section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCourseFk" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="editCourseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['courses_pk'] ?>">
                                <?= htmlspecialchars($course['course_number'] . ' - ' . $course['course_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTermFk" class="form-label">Term <span class="text-danger">*</span></label>
                        <select class="form-select" id="editTermFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?= $term['terms_pk'] ?>">
                                <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editSectionId" class="form-label">Section ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editSectionId" name="section_id_value" 
                               maxlength="20" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCrn" class="form-label">CRN</label>
                        <input type="text" class="form-control" id="editCrn" name="crn" maxlength="20">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editInstructorName" class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="editInstructorName" name="instructor_name" maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editMaxEnrollment" class="form-label">Max Enrollment</label>
                        <input type="number" class="form-control" id="editMaxEnrollment" name="max_enrollment" min="1">
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $theme->showFooter($context); ?>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    const table = $('#sectionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/sections_data.php',
            type: 'GET',
            data: function(d) {
                d.term_fk = <?= $selectedTermFk ?? 'null' ?>;
            }
        },
        columns: [
            { data: 0, visible: false },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 },
            { data: 7 },
            { data: 8, orderable: false }
        ],
        order: [[1, 'asc'], [2, 'asc']],
        pageLength: 25,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search sections..."
        }
    });
});

function editSection(section) {
    $('#editSectionPk').val(section.sections_pk);
    $('#editCourseFk').val(section.course_fk);
    $('#editTermFk').val(section.term_fk);
    $('#editSectionId').val(section.section_id);
    $('#editCrn').val(section.crn || '');
    $('#editInstructorName').val(section.instructor_name || '');
    $('#editMaxEnrollment').val(section.max_enrollment || '');
    $('#editIsActive').prop('checked', section.is_active == 1);
    $('#editSectionModal').modal('show');
}

function toggleStatus(id, label) {
    if (confirm('Toggle status for section ' + label + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="section_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteSection(id, label) {
    if (confirm('Are you sure you want to delete section ' + label + '? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="section_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
