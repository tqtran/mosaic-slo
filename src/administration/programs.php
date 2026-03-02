<?php
declare(strict_types=1);

/**
 * Program Administration
 * 
 * Manage academic programs.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

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
                $code = trim($_POST['program_code'] ?? '');
                $name = trim($_POST['program_name'] ?? '');
                $degreeType = trim($_POST['degree_type'] ?? '');
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if (empty($code)) {
                    $errors[] = 'Program code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Program code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}programs WHERE program_code = ?",
                        [$code],
                        's'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Program code already exists';
                    }
                }
                if (empty($name)) {
                    $errors[] = 'Program name is required';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                } else {
                    // Validate term exists
                    $termCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}terms WHERE terms_pk = ? AND is_active = 1",
                        [$termFk],
                        'i'
                    );
                    $termRow = $termCheck->fetch();
                    if ($termRow['count'] == 0) {
                        $errors[] = 'Invalid term selected';
                    }
                }
                
                if (empty($errors)) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "INSERT INTO {$dbPrefix}programs (program_code, program_name, degree_type, term_fk, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                        [$code, $name, $degreeType, $termFk, $isActive, $userId, $userId],
                        'sssiiii'
                    );
                    $successMessage = 'Program added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['program_id'] ?? 0);
                $code = trim($_POST['program_code'] ?? '');
                $name = trim($_POST['program_name'] ?? '');
                $degreeType = trim($_POST['degree_type'] ?? '');
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid program ID';
                }
                if (empty($code)) {
                    $errors[] = 'Program code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Program code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}programs WHERE program_code = ? AND programs_pk != ?",
                        [$code, $id],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Program code already exists';
                    }
                }
                if (empty($name)) {
                    $errors[] = 'Program name is required';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                } else {
                    // Validate term exists
                    $termCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}terms WHERE terms_pk = ? AND is_active = 1",
                        [$termFk],
                        'i'
                    );
                    $termRow = $termCheck->fetch();
                    if ($termRow['count'] == 0) {
                        $errors[] = 'Invalid term selected';
                    }
                }
                
                if (empty($errors)) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}programs 
                         SET program_code = ?, program_name = ?, degree_type = ?, term_fk = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                         WHERE programs_pk = ?",
                        [$code, $name, $degreeType, $termFk, $isActive, $userId, $id],
                        'sssiiii'
                    );
                    $successMessage = 'Program updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['program_id'] ?? 0);
                if ($id > 0) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}programs 
                         SET is_active = NOT is_active, updated_at = NOW(), updated_by_fk = ?
                         WHERE programs_pk = ?",
                        [$userId, $id],
                        'ii'
                    );
                    $successMessage = 'Program status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['program_id'] ?? 0);
                if ($id > 0) {
                    // Cascade delete: program_outcomes and program_courses will be automatically deleted by database
                    // Note: SLOs linked to program outcomes will have their program_outcomes_fk set to NULL
                    $db->query(
                        "DELETE FROM {$dbPrefix}programs WHERE programs_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Program deleted successfully (including all program outcomes and course links)';
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
        if (DEBUG_MODE) {
            $errorMessage .= '<br><br><strong>Debug Information:</strong><br>';
            $errorMessage .= '<pre style="text-align: left; font-size: 12px;">';
            $errorMessage .= 'File: ' . htmlspecialchars($e->getFile()) . '<br>';
            $errorMessage .= 'Line: ' . htmlspecialchars((string)$e->getLine()) . '<br>';
            $errorMessage .= 'Trace:<br>' . htmlspecialchars($e->getTraceAsString());
            $errorMessage .= '</pre>';
        }
    }
}

// Fetch terms for dropdown (sorted descending with latest first)
$termsResult = $db->query("
    SELECT terms_pk, term_code, term_name, academic_year
    FROM {$dbPrefix}terms
    WHERE is_active = 1
    ORDER BY term_code ASC
");
$terms = $termsResult->fetchAll();

// Get selected term (default to latest/first)
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

// Calculate statistics (filtered by term)
$termFilter = $selectedTermFk ? "WHERE term_fk = {$selectedTermFk}" : '';
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}programs
    {$termFilter}
");
$stats = $statsResult->fetch();
$totalPrograms = $stats['total'];
$activePrograms = $stats['active'];
$inactivePrograms = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Program Management';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_programs',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Programs']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Programs</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
        <?php endif; ?>

        <!-- Programs Table -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-table" aria-hidden="true"></i> Academic Programs</h2>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProgramModal" aria-label="Add new program">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add Program
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="programsTable" class="table table-bordered table-striped" aria-label="Programs data table">
                    <caption class="visually-hidden">List of academic programs with filtering and sorting capabilities</caption>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Term</th>
                            <th scope="col">Code</th>
                            <th scope="col">Program Name</th>
                            <th scope="col">Degree Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col">Created By</th>
                            <th scope="col">Updated</th>
                            <th scope="col">Updated By</th>
                            <th scope="col">Actions</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1" aria-labelledby="addProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <span class="modal-title" id="addProgramModalLabel"><i class="fas fa-plus" aria-hidden="true"></i> Add Program</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="programCode" class="form-label">Program Code <span class="text-danger" aria-label="required">*</span></label>
                            <input type="text" class="form-control" id="programCode" name="program_code" maxlength="50" required aria-required="true" aria-describedby="programCodeHelp">
                            <small id="programCodeHelp" class="form-text text-muted">Unique identifier (letters, numbers, hyphens, underscores)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="degreeType" class="form-label">Degree Type</label>
                            <select class="form-select" id="degreeType" name="degree_type">
                                <option value="">Select Degree Type</option>
                                <?php
                                $degreeTypes = explode(',', $config->get('app.degree_types', 'Associate of Arts (AA),Associate of Science (AS),Associate in Arts for Transfer (AA-T),Associate in Science for Transfer (AS-T),Bachelor of Science (BS),Bachelor of Applied Science (BAS),Certificate of Achievement (16 or more semester units),Certificate of Achievement (8-15.5 semester units),Local Certificate (fewer than 8 semester units),Noncredit Certificate of Completion,Noncredit Certificate of Competency'));
                                foreach ($degreeTypes as $type) {
                                    $type = trim($type);
                                    echo '<option value="' . htmlspecialchars($type) . '">' . htmlspecialchars($type) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="programName" class="form-label">Program Name <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="programName" name="program_name" maxlength="255" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="termFk" class="form-label">Term <span class="text-danger" aria-label="required">*</span></label>
                        <select class="form-select" id="termFk" name="term_fk" required aria-required="true">
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="h6">Status</legend>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1" aria-labelledby="editProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <span class="modal-title" id="editProgramModalLabel"><i class="fas fa-edit" aria-hidden="true"></i> Edit Program</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="program_id" id="editProgramId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editProgramCode" class="form-label">Program Code <span class="text-danger" aria-label="required">*</span></label>
                            <input type="text" class="form-control" id="editProgramCode" name="program_code" maxlength="50" required aria-required="true">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editDegreeType" class="form-label">Degree Type</label>
                            <select class="form-select" id="editDegreeType" name="degree_type">
                                <option value="">Select Degree Type</option>
                                <?php
                                $degreeTypes = explode(',', $config->get('app.degree_types', 'Associate of Arts (AA),Associate of Science (AS),Associate in Arts for Transfer (AA-T),Associate in Science for Transfer (AS-T),Bachelor of Science (BS),Bachelor of Applied Science (BAS),Certificate of Achievement (16 or more semester units),Certificate of Achievement (8-15.5 semester units),Local Certificate (fewer than 8 semester units),Noncredit Certificate of Completion,Noncredit Certificate of Competency'));
                                foreach ($degreeTypes as $type) {
                                    $type = trim($type);
                                    echo '<option value="' . htmlspecialchars($type) . '">' . htmlspecialchars($type) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editProgramName" class="form-label">Program Name <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="editProgramName" name="program_name" maxlength="255" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="editTermFk" class="form-label">Term <span class="text-danger" aria-label="required">*</span></label>
                        <select class="form-select" id="editTermFk" name="term_fk" required aria-required="true">
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>">
                                    <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="h6">Status</legend>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                            <label class="form-check-label" for="editIsActive">Active</label>
                        </div>
                    </fieldset>
                    <hr>
                    <div class="text-muted mb-3"><i class="fas fa-history" aria-hidden="true"></i> Audit Information</div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <small class="text-muted">Created:</small>
                            <p class="mb-0" id="editProgramCreated"></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Created By:</small>
                            <p class="mb-0" id="editProgramCreatedBy"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">Last Updated:</small>
                            <p class="mb-0" id="editProgramUpdated"></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Updated By:</small>
                            <p class="mb-0" id="editProgramUpdatedBy"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <!-- LEFT SIDE: Destructive Actions -->
                    <div>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteProgram()" aria-label="Delete program">
                            <i class="fas fa-trash" aria-hidden="true"></i> Delete
                        </button>
                    </div>
                    <!-- RIGHT SIDE: Primary Actions -->
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="viewProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <span class="modal-title"><i class="fas fa-eye"></i> Program Details</span>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Program Code:</strong>
                        <p id="viewProgramCode"></p>
                    </div>
                    <div class="col-md-4">
                        <strong>Degree Type:</strong>
                        <p id="viewDegreeType"></p>
                    </div>
                    <div class="col-md-4">
                        <strong>ID:</strong>
                        <p id="viewProgramId"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Program Name:</strong>
                        <p id="viewProgramName"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewProgramStatus"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <strong>Term:</strong>
                        <p id="viewProgramTerm"></p>
                    </div>
                </div>
                <hr>
                <div class="text-muted mb-3"><i class="fas fa-history" aria-hidden="true"></i> Audit Information</div>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewProgramCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Created By:</strong>
                        <p id="viewProgramCreatedBy"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewProgramUpdated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Updated By:</strong>
                        <p id="viewProgramUpdatedBy"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Status Form (hidden) -->
<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="program_id" id="toggleProgramId">
</form>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="program_id" id="deleteProgramId">
</form>

<?php $theme->showFooter($context); ?>

<!-- DataTables JS -->
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
$(document).ready(function() {
    // Setup - add a text input or dropdown to each header cell (second row)
    $('#programsTable thead tr:eq(1) td').each(function(i) {
        var title = $('#programsTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title === 'Actions') {
            $(this).html('');
        } else if (title === 'Term') {
            // Create dropdown for Term column
            var select = '<select class="form-select form-select-sm" aria-label="Filter by ' + title + '"><option value="">All</option>';
            <?php foreach ($terms as $term): ?>
            select += '<option value="<?= htmlspecialchars($term['term_code']) ?>"><?= htmlspecialchars($term['term_name']) ?></option>';
            <?php endforeach; ?>
            select += '</select>';
            $(this).html(select);
        } else if (title === 'Status') {
            // Create dropdown for Status column
            var select = '<select class="form-select form-select-sm" aria-label="Filter by ' + title + '">';
            select += '<option value="">All</option>';
            select += '<option value="Active">Active</option>';
            select += '<option value="Inactive">Inactive</option>';
            select += '</select>';
            $(this).html(select);
        } else {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" aria-label="Filter by ' + title + '" />');
        }
    });
    
    var table = $('#programsTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/programs_data.php',
        dom: 'Brtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'programs_pk' },
            { data: 1, name: 'term_code' },
            { data: 2, name: 'program_code' },
            { data: 3, name: 'program_name' },
            { data: 4, name: 'degree_type' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'created_at' },
            { data: 7, name: 'created_by' },
            { data: 8, name: 'updated_at' },
            { data: 9, name: 'updated_by' },
            { data: 10, name: 'actions', orderable: false, searchable: false }
        ],
        order: [[3, 'asc']],
        initComplete: function() {
            // Apply the search - target the second header row where filters are
            var api = this.api();
            api.columns().every(function(colIdx) {
                var column = this;
                // Find input in the second header row (tr:eq(1)) for this column
                $('input, select', $('#programsTable thead tr:eq(1) td').eq(colIdx)).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function viewProgram(prog) {
    $('#viewProgramName').text(prog.program_name);
    $('#viewProgramCode').text(prog.program_code);
    $('#viewDegreeType').text(prog.degree_type || 'N/A');
    $('#viewProgramStatus').html(prog.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewProgramId').text(prog.programs_pk);
    // Display term_code and term_name if available
    var termDisplay = prog.term_code ? (prog.term_code + (prog.term_name ? ' - ' + prog.term_name : '')) : 'N/A';
    $('#viewProgramTerm').text(termDisplay);
    $('#viewProgramCreated').text(prog.created_at || 'N/A');
    $('#viewProgramCreatedBy').text(prog.created_by_name || 'System');
    $('#viewProgramUpdated').text(prog.updated_at || 'N/A');
    $('#viewProgramUpdatedBy').text(prog.updated_by_name || 'System');
    new bootstrap.Modal(document.getElementById('viewProgramModal')).show();
}

function editProgram(prog) {
    $('#editProgramId').val(prog.programs_pk);
    $('#editProgramCode').val(prog.program_code);
    $('#editProgramName').val(prog.program_name);
    $('#editDegreeType').val(prog.degree_type);
    $('#editTermFk').val(prog.term_fk);
    $('#editIsActive').prop('checked', prog.is_active == 1);
    // Populate read-only audit info
    $('#editProgramCreated').text(prog.created_at || 'N/A');
    $('#editProgramCreatedBy').text(prog.created_by_name || 'System');
    $('#editProgramUpdated').text(prog.updated_at || 'N/A');
    $('#editProgramUpdatedBy').text(prog.updated_by_name || 'System');
    new bootstrap.Modal(document.getElementById('editProgramModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Are you sure you want to toggle the status of "' + name + '"?')) {
        $('#toggleProgramId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteProgram(id, name) {
    if (confirm('Are you sure you want to DELETE "' + name + '"?\n\nThis will also delete:\n- All program outcomes (PSLOs)\n- All program-course links\n\nSLOs linked to program outcomes will be preserved with NULL mapping.\n\nThis action cannot be undone.')) {
        $('#deleteProgramId').val(id);
        $('#deleteForm').submit();
    }
}

function confirmDeleteProgram() {
    const programPk = $('#editProgramId').val();
    const programName = $('#editProgramName').val();
    deleteProgram(programPk, programName);
}
</script>
