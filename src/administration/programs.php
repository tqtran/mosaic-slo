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
                    // Check if program has associated program outcomes
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes WHERE program_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete program: it has associated program outcomes. Please remove outcomes first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}programs WHERE programs_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Program deleted successfully';
                    }
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

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active">Programs</li>
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

        <!-- Programs Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Academic Programs</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        <i class="fas fa-plus"></i> Add Program
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="programsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Term</th>
                            <th>Code</th>
                            <th>Program Name</th>
                            <th>Degree Type</th>
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
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="programCode" class="form-label">Program Code</label>
                            <input type="text" class="form-control" id="programCode" name="program_code" maxlength="50" required>
                            <small class="form-text text-muted">Unique identifier (letters, numbers, hyphens, underscores)</small>
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
                        <label for="programName" class="form-label">Program Name</label>
                        <input type="text" class="form-control" id="programName" name="program_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="termFk" class="form-label">Term</label>
                        <select class="form-select" id="termFk" name="term_fk" required>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="program_id" id="editProgramId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editProgramCode" class="form-label">Program Code</label>
                            <input type="text" class="form-control" id="editProgramCode" name="program_code" maxlength="50" required>
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
                        <label for="editProgramName" class="form-label">Program Name</label>
                        <input type="text" class="form-control" id="editProgramName" name="program_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTermFk" class="form-label">Term</label>
                        <select class="form-select" id="editTermFk" name="term_fk" required>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>">
                                    <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-history"></i> Audit Information</h6>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Program Modal -->
<div class="modal fade" id="viewProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Program Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                <h6 class="text-muted mb-3"><i class="fas fa-history"></i> Audit Information</h6>
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
    $('#programsTable thead tr:eq(1) th').each(function(i) {
        var title = $('#programsTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title === 'Actions') {
            $(this).html('');
        } else if (title === 'Term') {
            // Create dropdown for Term column
            var select = '<select class="form-select form-select-sm"><option value="">All</option>';
            <?php foreach ($terms as $term): ?>
            select += '<option value="<?= htmlspecialchars($term['term_code']) ?>"><?= htmlspecialchars($term['term_name']) ?></option>';
            <?php endforeach; ?>
            select += '</select>';
            $(this).html(select);
        } else if (title === 'Status') {
            // Create dropdown for Status column
            var select = '<select class="form-select form-select-sm">';
            select += '<option value="">All</option>';
            select += '<option value="Active">Active</option>';
            select += '<option value="Inactive">Inactive</option>';
            select += '</select>';
            $(this).html(select);
        } else {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        }
    });
    
    var table = $('#programsTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/programs_data.php',
        dom: 'Bfrtip',
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
            // Apply the search
            this.api().columns().every(function() {
                var column = this;
                $('input, select', this.header()).on('keyup change clear', function() {
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
    if (confirm('Are you sure you want to DELETE "' + name + '"? This action cannot be undone.')) {
        $('#deleteProgramId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
