<?php
declare(strict_types=1);

/**
 * Institutional Outcomes Administration
 * 
 * Manage institutional-level learning outcomes.
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
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $outcomeCode = trim($_POST['outcome_code'] ?? '');
                $outcomeDescription = trim($_POST['outcome_description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                }
                if (empty($outcomeCode)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    // Check uniqueness within the term
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institutional_outcomes WHERE term_fk = ? AND outcome_code = ?",
                        [$termFk, $outcomeCode],
                        'is'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists in this term';
                    }
                }
                if (empty($outcomeDescription)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}institutional_outcomes (term_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$termFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                        'issii'
                    );
                    $successMessage = 'Institutional outcome added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['outcome_id'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $outcomeCode = trim($_POST['outcome_code'] ?? '');
                $outcomeDescription = trim($_POST['outcome_description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid outcome ID';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                }
                if (empty($outcomeCode)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    // Check uniqueness within the term (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institutional_outcomes 
                         WHERE term_fk = ? AND outcome_code = ? AND institutional_outcomes_pk != ?",
                        [$termFk, $outcomeCode, $id],
                        'isi'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists in this term';
                    }
                }
                if (empty($outcomeDescription)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}institutional_outcomes 
                         SET term_fk = ?, outcome_code = ?, outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                         WHERE institutional_outcomes_pk = ?",
                        [$termFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive, $id],
                        'issiii'
                    );
                    $successMessage = 'Institutional outcome updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['outcome_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}institutional_outcomes 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE institutional_outcomes_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Outcome status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['outcome_id'] ?? 0);
                if ($id > 0) {
                    // Check if outcome has associated program outcomes
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes WHERE institutional_outcomes_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete outcome: it is mapped to program outcomes. Please remove mappings first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}institutional_outcomes WHERE institutional_outcomes_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Institutional outcome deleted successfully';
                    }
                }
                break;
                
            case 'import':
                if (isset($_FILES['outcome_upload']) && $_FILES['outcome_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['outcome_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $headers = fgetcsv($handle); // Skip header row
                        $imported = 0;
                        $skipped = 0;
                        
                        // Get selected term for import
                        $selectedTermFk = getSelectedTermFk();
                        if (!$selectedTermFk) {
                            $errorMessage = 'No term selected. Please select a term first.';
                            fclose($handle);
                            break;
                        }
                        
                        $sequenceNum = 1;
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 1 && !empty(trim($row[0]))) {
                                $outcomeDescription = trim($row[0]);
                                $outcomeCode = 'ISLO-' . $sequenceNum; // Auto-generate code
                                
                                if (!empty($outcomeDescription)) {
                                    // Check if exists for this term
                                    $result = $db->query(
                                        "SELECT institutional_outcomes_pk FROM {$dbPrefix}institutional_outcomes 
                                         WHERE outcome_code = ? AND term_fk = ?",
                                        [$outcomeCode, $selectedTermFk],
                                        'si'
                                    );
                                    
                                    if ($result->rowCount() > 0) {
                                        // Update existing
                                        $existing = $result->fetch();
                                        $db->query(
                                            "UPDATE {$dbPrefix}institutional_outcomes 
                                             SET outcome_description = ?, sequence_num = ?, updated_at = NOW()
                                             WHERE institutional_outcomes_pk = ?",
                                            [$outcomeDescription, $sequenceNum, $existing['institutional_outcomes_pk']],
                                            'sii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}institutional_outcomes (term_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
                                            [$selectedTermFk, $outcomeCode, $outcomeDescription, $sequenceNum],
                                            'issi'
                                        );
                                    }
                                    $imported++;
                                    $sequenceNum++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        $successMessage = "Import completed: {$imported} ISLOs imported/updated, {$skipped} skipped";
                    } else {
                        $errorMessage = 'Failed to read CSV file';
                    }
                } else {
                    $errorMessage = 'No file uploaded or upload error occurred';
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
        COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
        COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as inactive
    FROM {$dbPrefix}institutional_outcomes
    {$termFilter}
");
$stats = $statsResult->fetch();
$totalOutcomes = $stats['total'] ?? 0;
$activeOutcomes = $stats['active'] ?? 0;
$inactiveOutcomes = $stats['inactive'] ?? 0;

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Institutional Outcomes';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_institutional_outcomes',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Institutional Outcomes']
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
                    <li class="breadcrumb-item active">Institutional Outcomes</li>
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

        <!-- Outcomes Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Institutional Learning Outcomes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOutcomeModal">
                        <i class="fas fa-plus"></i> Add Outcome
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import ISLOs
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="outcomesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Created</th>
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

<!-- Add Outcome Modal -->
<div class="modal fade" id="addOutcomeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Institutional Outcome</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="addTermFk" class="form-label">Term</label>
                            <select class="form-select" id="addTermFk" name="term_fk" required>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="outcomeCode" class="form-label">Outcome Code</label>
                            <input type="text" class="form-control" id="outcomeCode" name="outcome_code" maxlength="50" required>
                            <small class="form-text text-muted">e.g., ILO-1, INST-A</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="sequenceNum" name="sequence_num" min="0" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="outcomeDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="outcomeDescription" name="outcome_description" rows="4" required></textarea>
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

<!-- Edit Outcome Modal -->
<div class="modal fade" id="editOutcomeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Institutional Outcome</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="outcome_id" id="editOutcomeId">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="editTermFk" class="form-label">Term</label>
                            <select class="form-select" id="editTermFk" name="term_fk" required>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['terms_pk'] ?>">
                                        <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editOutcomeCode" class="form-label">Outcome Code</label>
                            <input type="text" class="form-control" id="editOutcomeCode" name="outcome_code" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="editSequenceNum" name="sequence_num" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editOutcomeDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editOutcomeDescription" name="outcome_description" rows="4" required></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
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

<!-- View Outcome Modal -->
<div class="modal fade" id="viewOutcomeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Outcome Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Outcome Code:</strong>
                        <p id="viewOutcomeCode"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Sequence:</strong>
                        <p id="viewSequenceNum"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <strong>Description:</strong>
                        <p id="viewOutcomeDescription"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewOutcomeStatus"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>ID:</strong>
                        <p id="viewOutcomeId"></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewOutcomeCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewOutcomeUpdated"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import ISLOs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="outcomeUpload" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="outcomeUpload" name="outcome_upload" accept=".csv" required>
                        <small class="form-text text-muted">
                            CSV format: One ISLO description per line (header row optional)<br>
                            Example file: <code>data/ISLOs.csv</code>
                        </small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <ul class="mb-0 small">
                            <li>ISLOs will be imported into the currently selected term</li>
                            <li>Codes will be auto-generated as ISLO-1, ISLO-2, etc.</li>
                            <li>Existing ISLOs with same codes will be updated</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Form (hidden) -->
<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="outcome_id" id="toggleOutcomeId">
</form>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="outcome_id" id="deleteOutcomeId">
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
    // Setup - add a text input to each header cell (second row)
    $('#outcomesTable thead tr:eq(1) th').each(function(i) {
        var title = $('#outcomesTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html(''); // No filter for Actions column
        }
    });
    
    var table = $('#outcomesTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/institutional_outcomes_data.php',
            data: function(d) {
                d.term_fk = $('#termFilter').val();
            }
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'institutional_outcomes_pk' },
            { data: 1, name: 'outcome_code' },
            { data: 2, name: 'outcome_description' },
            { data: 3, name: 'sequence_num' },
            { data: 4, name: 'is_active' },
            { data: 5, name: 'created_at' },
            { data: 6, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
            // Apply the search
            this.api().columns().every(function() {
                var column = this;
                $('input', this.header()).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function viewOutcome(outcome) {
    $('#viewOutcomeCode').text(outcome.outcome_code);
    $('#viewOutcomeDescription').text(outcome.outcome_description);
    $('#viewSequenceNum').text(outcome.sequence_num);
    $('#viewOutcomeStatus').html(outcome.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewOutcomeId').text(outcome.institutional_outcomes_pk);
    $('#viewOutcomeCreated').text(outcome.created_at);
    $('#viewOutcomeUpdated').text(outcome.updated_at);
    new bootstrap.Modal(document.getElementById('viewOutcomeModal')).show();
}

function editOutcome(outcome) {
    $('#editOutcomeId').val(outcome.institutional_outcomes_pk);
    $('#editTermFk').val(outcome.term_fk);
    $('#editOutcomeCode').val(outcome.outcome_code);
    $('#editOutcomeDescription').val(outcome.outcome_description);
    $('#editSequenceNum').val(outcome.sequence_num);
    $('#editIsActive').prop('checked', outcome.is_active == 1);
    new bootstrap.Modal(document.getElementById('editOutcomeModal')).show();
}

function toggleStatus(id, code) {
    if (confirm('Are you sure you want to toggle the status of "' + code + '"?')) {
        $('#toggleOutcomeId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteOutcome(id, code) {
    if (confirm('Are you sure you want to DELETE "' + code + '"? This action cannot be undone.')) {
        $('#deleteOutcomeId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
