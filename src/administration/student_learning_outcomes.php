<?php
declare(strict_types=1);

/**
 * Student Learning Outcomes (S Los) Administration
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $programOutcomesFk = !empty($_POST['program_outcomes_fk']) ? (int)$_POST['program_outcomes_fk'] : null;
                $sloCode = trim($_POST['slo_code'] ?? '');
                $sloDescription = trim($_POST['slo_description'] ?? '');
                $assessmentMethod = trim($_POST['assessment_method'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if (empty($sloCode)) {
                    $errors[] = 'SLO code is required';
                }
                if (empty($sloDescription)) {
                    $errors[] = 'SLO description is required';
                }
                
                if (empty($errors)) {
                    $userId = $_SESSION['user_id'] ?? null;
                    if ($programOutcomesFk !== null) {
                        $db->query(
                            "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, program_outcomes_fk, slo_code, slo_description, assessment_method, sequence_num, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                            [$courseFk, $programOutcomesFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive, $userId, $userId],
                            'iisssiii'
                        );
                    } else {
                        $db->query(
                            "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, slo_code, slo_description, assessment_method, sequence_num, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                            [$courseFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive, $userId, $userId],
                            'isssiiii'
                        );
                    }
                    $successMessage = 'SLO added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['slo_id'] ?? 0);
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $programOutcomesFk = !empty($_POST['program_outcomes_fk']) ? (int)$_POST['program_outcomes_fk'] : null;
                $sloCode = trim($_POST['slo_code'] ?? '');
                $sloDescription = trim($_POST['slo_description'] ?? '');
                $assessmentMethod = trim($_POST['assessment_method'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid SLO ID';
                }
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if (empty($sloCode)) {
                    $errors[] = 'SLO code is required';
                }
                if (empty($sloDescription)) {
                    $errors[] = 'SLO description is required';
                }
                
                if (empty($errors)) {
                    $userId = $_SESSION['user_id'] ?? null;
                    if ($programOutcomesFk !== null) {
                        $db->query(
                            "UPDATE {$dbPrefix}student_learning_outcomes 
                             SET course_fk = ?, program_outcomes_fk = ?, slo_code = ?, slo_description = ?, assessment_method = ?, sequence_num = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                             WHERE student_learning_outcomes_pk = ?",
                            [$courseFk, $programOutcomesFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive, $userId, $id],
                            'iisssiiii'
                        );
                    } else {
                        $db->query(
                            "UPDATE {$dbPrefix}student_learning_outcomes 
                             SET course_fk = ?, program_outcomes_fk = NULL, slo_code = ?, slo_description = ?, assessment_method = ?, sequence_num = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                             WHERE student_learning_outcomes_pk = ?",
                            [$courseFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive, $userId, $id],
                            'isssiii'
                        );
                    }
                    $successMessage = 'SLO updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['slo_id'] ?? 0);
                if ($id > 0) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}student_learning_outcomes SET is_active = NOT is_active, updated_at = NOW(), updated_by_fk = ? WHERE student_learning_outcomes_pk = ?",
                        [$userId, $id],
                        'ii'
                    );
                    $successMessage = 'SLO status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['slo_id'] ?? 0);
                if ($id > 0) {
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}assessments WHERE student_learning_outcome_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete SLO: it has associated assessments.';
                    } else {
                        $db->query("DELETE FROM {$dbPrefix}student_learning_outcomes WHERE student_learning_outcomes_pk = ?", [$id], 'i');
                        $successMessage = 'SLO deleted successfully';
                    }
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Get terms for dropdown (sorted descending with latest first)
$termsResult = $db->query("
    SELECT terms_pk, term_code, term_name, academic_year
    FROM {$dbPrefix}terms
    WHERE is_active = 1
    ORDER BY term_code ASC
");
$terms = $termsResult->fetchAll();

// Get selected term (from GET/session, or default to latest)
$selectedTermFk = getSelectedTermFk();
if (!$selectedTermFk && !empty($terms)) {
    $selectedTermFk = $terms[0]['terms_pk'];
    // Save to session for header dropdown sync
    $_SESSION['selected_term_fk'] = $selectedTermFk;
}

// Get selected term name
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

// Calculate statistics (filtered by term through courses)
if ($selectedTermFk) {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN slo.is_active = 1 THEN 1 ELSE 0 END) as active
        FROM {$dbPrefix}student_learning_outcomes slo
        LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
        WHERE c.term_fk = ?
    ", [$selectedTermFk], 'i');
} else {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN slo.is_active = 1 THEN 1 ELSE 0 END) as active
        FROM {$dbPrefix}student_learning_outcomes slo
        LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
    ");
}
$stats = $statsResult->fetch();
$totalSLOs = $stats['total'];
$activeSLOs = $stats['active'];

// Get courses for dropdown
$coursesResult = $db->query("SELECT courses_pk, course_name, course_number FROM {$dbPrefix}courses WHERE is_active = 1 ORDER BY course_name");
$courses = $coursesResult->fetchAll();

// Get program outcomes for dropdown
$programOutcomesResult = $db->query("SELECT program_outcomes_pk, outcome_code, outcome_description FROM {$dbPrefix}program_outcomes WHERE is_active = 1 ORDER BY outcome_code");
$programOutcomes = $programOutcomesResult->fetchAll();

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Student Learning Outcomes';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_slos',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'SLOs']
    ]
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active">Student Learning Outcomes</li>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Student Learning Outcomes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSLOModal">
                        <i class="fas fa-plus"></i> Add SLO
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="slosTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course</th>
                            <th>Program Outcome</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Assessment Method</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Created By</th>
                            <th>Updated</th>
                            <th>Updated By</th>
                            <th>Actions</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add SLO Modal -->
<div class="modal fade" id="addSLOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Student Learning Outcome</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="courseFk" class="form-label">Course</label>
                        <select class="form-select" id="courseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="programOutcomesFk" class="form-label">Program Outcome <small class="text-muted">(Optional)</small></label>
                        <select class="form-select" id="programOutcomesFk" name="program_outcomes_fk">
                            <option value="">None</option>
                            <?php foreach ($programOutcomes as $po): ?>
                                <option value="<?= $po['program_outcomes_pk'] ?>">
                                    <?= htmlspecialchars($po['outcome_code']) ?> - <?= htmlspecialchars(substr($po['outcome_description'], 0, 60)) ?><?= strlen($po['outcome_description']) > 60 ? '...' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Links this SLO to a program-level outcome (optional)</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sloCode" class="form-label">SLO Code</label>
                            <input type="text" class="form-control" id="sloCode" name="slo_code" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="sequenceNum" name="sequence_num" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sloDescription" class="form-label">SLO Description</label>
                        <textarea class="form-control" id="sloDescription" name="slo_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assessmentMethod" class="form-label">Assessment Method</label>
                        <textarea class="form-control" id="assessmentMethod" name="assessment_method" rows="2" maxlength="255" placeholder="e.g., Final exam, Portfolio review, Lab practical"></textarea>
                        <div class="form-text">Describe how this SLO is assessed (optional)</div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit SLO Modal -->
<div class="modal fade" id="editSLOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student Learning Outcome</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="slo_id" id="editSLOId">
                    
                    <div class="mb-3">
                        <label for="editCourseFk" class="form-label">Course</label>
                        <select class="form-select" id="editCourseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editProgramOutcomesFk" class="form-label">Program Outcome <small class="text-muted">(Optional)</small></label>
                        <select class="form-select" id="editProgramOutcomesFk" name="program_outcomes_fk">
                            <option value="">None</option>
                            <?php foreach ($programOutcomes as $po): ?>
                                <option value="<?= $po['program_outcomes_pk'] ?>">
                                    <?= htmlspecialchars($po['outcome_code']) ?> - <?= htmlspecialchars(substr($po['outcome_description'], 0, 60)) ?><?= strlen($po['outcome_description']) > 60 ? '...' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Links this SLO to a program-level outcome (optional)</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editSLOCode" class="form-label">SLO Code</label>
                            <input type="text" class="form-control" id="editSLOCode" name="slo_code" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="editSequenceNum" name="sequence_num">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editSLODescription" class="form-label">SLO Description</label>
                        <textarea class="form-control" id="editSLODescription" name="slo_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAssessmentMethod" class="form-label">Assessment Method</label>
                        <textarea class="form-control" id="editAssessmentMethod" name="assessment_method" rows="2" maxlength="255" placeholder="e.g., Final exam, Portfolio review, Lab practical"></textarea>
                        <div class="form-text">Describe how this SLO is assessed (optional)</div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                    
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-info-circle"></i> Audit Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted"><strong>Created:</strong></small>
                            <p id="editSLOCreated"></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted"><strong>Created By:</strong></small>
                            <p id="editSLOCreatedBy"></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted"><strong>Last Updated:</strong></small>
                            <p id="editSLOUpdated"></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted"><strong>Updated By:</strong></small>
                            <p id="editSLOUpdatedBy"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Import CSLO</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>CSV Format:</strong><br>
                        <code>CRS ID,CRS TITLE,CSLO</code><br>
                        <small class="text-muted">
                            <ul class="mb-0">
                                <li>CRS ID: Course identifier (e.g., BCI C100, COMM C110)</li>
                                <li>CRS TITLE: Course name (e.g., "Introduction to Building Code")</li>
                                <li>CSLO: Learning outcome text (can contain multiple sentences)</li>
                                <li>Courses will be auto-created if they don't exist</li>
                                <li>CSLO codes will be auto-generated as {COURSE_ID}_clso1, {COURSE_ID}_clso2, etc.</li>
                                <li>Multiple sentences will be automatically split and numbered</li>
                                <li>Courses will be assigned to the currently selected term</li>
                            </ul>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="slo_id" id="toggleSLOId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="slo_id" id="deleteSLOId">
</form>

<?php $theme->showFooter($context); ?>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
// Convert PHP arrays to JavaScript
var courses = <?= json_encode(array_map(function($c) { 
    return ['name' => $c['course_name'], 'number' => $c['course_number']]; 
}, $courses)) ?>;
var programOutcomes = <?= json_encode(array_map(function($po) { 
    return ['code' => $po['outcome_code']]; 
}, $programOutcomes)) ?>;

$(document).ready(function() {
    $('#slosTable thead tr:eq(1) th').each(function(i) {
        var title = $('#slosTable thead tr:eq(0) th:eq(' + i + ')').text();
        
        // Course column (index 1)
        if (title === 'Course') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Courses</option></select>')
                .appendTo($(this).empty());
            courses.forEach(function(course) {
                select.append('<option value="' + course.name + '">' + course.name + '</option>');
            });
        }
        // Program Outcome column (index 2)
        else if (title === 'Program Outcome') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Program Outcomes</option></select>')
                .appendTo($(this).empty());
            programOutcomes.forEach(function(po) {
                if (po.code) {
                    select.append('<option value="' + po.code + '">' + po.code + '</option>');
                }
            });
        }
        // Status column (index 7)
        else if (title === 'Status') {
            var select = $('<select class="form-select form-select-sm"><option value="">All</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select>')
                .appendTo($(this).empty());
        }
        else if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#slosTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/student_learning_outcomes_data.php',
            data: function(d) {
                d.term_fk = $('#termFilter').val();
            }
        },
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'student_learning_outcomes_pk' },
            { data: 1, name: 'course_name' },
            { data: 2, name: 'program_outcome_code' },
            { data: 3, name: 'slo_code' },
            { data: 4, name: 'slo_description' },
            { data: 5, name: 'assessment_method' },
            { data: 6, name: 'sequence_num' },
            { data: 7, name: 'is_active' },
            { data: 8, name: 'created_at' },
            { data: 9, name: 'created_by_name' },
            { data: 10, name: 'updated_at' },
            { data: 11, name: 'updated_by_name' },
            { data: 12, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
            var api = this.api();
            
            // Apply the search
            api.columns().every(function(colIdx) {
                var column = this;
                
                // Find the input/select in the second header row for this column
                var filterCell = $('#slosTable thead tr:eq(1) th:eq(' + colIdx + ')');
                
                $('select', filterCell).on('change', function() {
                    var val = $(this).val();
                    column.search(val ? val : '', true, false).draw();
                });
                
                $('input', filterCell).on('keyup change clear', function() {
                    var val = $(this).val();
                    if (column.search() !== val) {
                        column.search(val).draw();
                    }
                });
            });
        }
    });
});

function editSLO(slo) {
    $('#editSLOId').val(slo.student_learning_outcomes_pk);
    $('#editCourseFk').val(slo.course_fk);
    $('#editProgramOutcomesFk').val(slo.program_outcomes_fk || '');
    $('#editSLOCode').val(slo.slo_code);
    $('#editSLODescription').val(slo.slo_description);
    $('#editAssessmentMethod').val(slo.assessment_method || '');
    $('#editSequenceNum').val(slo.sequence_num);
    $('#editIsActive').prop('checked', slo.is_active == 1);
    
    // Populate audit information
    $('#editSLOCreated').text(slo.created_at || 'N/A');
    $('#editSLOCreatedBy').text(slo.created_by_name || 'System');
    $('#editSLOUpdated').text(slo.updated_at || 'N/A');
    $('#editSLOUpdatedBy').text(slo.updated_by_name || 'System');
    
    new bootstrap.Modal(document.getElementById('editSLOModal')).show();
}

function toggleStatus(id, code) {
    if (confirm('Are you sure you want to toggle the status of SLO "' + code + '"?')) {
        $('#toggleSLOId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteSLO(id, code) {
    if (confirm('Are you sure you want to DELETE SLO "' + code + '"? This action cannot be undone.')) {
        $('#deleteSLOId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
