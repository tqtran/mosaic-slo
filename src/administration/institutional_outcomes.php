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
                $institutionFk = (int)($_POST['institution_fk'] ?? 0);
                $outcomeCode = trim($_POST['outcome_code'] ?? '');
                $outcomeDescription = trim($_POST['outcome_description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($institutionFk <= 0) {
                    $errors[] = 'Institution is required';
                }
                if (empty($outcomeCode)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    // Check uniqueness
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institutional_outcomes WHERE outcome_code = ? AND institution_fk = ?",
                        [$outcomeCode, $institutionFk],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists for this institution';
                    }
                }
                if (empty($outcomeDescription)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}institutional_outcomes (institution_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$institutionFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                        'issii'
                    );
                    $successMessage = 'Institutional outcome added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['outcome_id'] ?? 0);
                $institutionFk = (int)($_POST['institution_fk'] ?? 0);
                $outcomeCode = trim($_POST['outcome_code'] ?? '');
                $outcomeDescription = trim($_POST['outcome_description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid outcome ID';
                }
                if ($institutionFk <= 0) {
                    $errors[] = 'Institution is required';
                }
                if (empty($outcomeCode)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institutional_outcomes 
                         WHERE outcome_code = ? AND institution_fk = ? AND institutional_outcomes_pk != ?",
                        [$outcomeCode, $institutionFk, $id],
                        'sii'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists for this institution';
                    }
                }
                if (empty($outcomeDescription)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}institutional_outcomes 
                         SET institution_fk = ?, outcome_code = ?, outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                         WHERE institutional_outcomes_pk = ?",
                        [$institutionFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive, $id],
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
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 3) {
                                $institutionCode = trim($row[0]);
                                $outcomeCode = trim($row[1]);
                                $outcomeDescription = trim($row[2]);
                                $sequenceNum = isset($row[3]) ? (int)trim($row[3]) : 0;
                                $isActive = isset($row[4]) && strtolower(trim($row[4])) === 'active' ? 1 : 0;
                                
                                // Find institution by code
                                $instResult = $db->query(
                                    "SELECT institution_pk FROM {$dbPrefix}institution WHERE institution_code = ?",
                                    [$institutionCode],
                                    's'
                                );
                                
                                if ($instResult->rowCount() > 0 && !empty($outcomeCode) && !empty($outcomeDescription) && preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                                    $inst = $instResult->fetch();
                                    $institutionFk = $inst['institution_pk'];
                                    
                                    // Check if exists
                                    $result = $db->query(
                                        "SELECT institutional_outcomes_pk FROM {$dbPrefix}institutional_outcomes 
                                         WHERE outcome_code = ? AND institution_fk = ?",
                                        [$outcomeCode, $institutionFk],
                                        'si'
                                    );
                                    
                                    if ($result->rowCount() > 0) {
                                        // Update existing
                                        $existing = $result->fetch();
                                        $db->query(
                                            "UPDATE {$dbPrefix}institutional_outcomes 
                                             SET outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                                             WHERE institutional_outcomes_pk = ?",
                                            [$outcomeDescription, $sequenceNum, $isActive, $existing['institutional_outcomes_pk']],
                                            'siii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}institutional_outcomes (institution_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$institutionFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                                            'issii'
                                        );
                                    }
                                    $imported++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        $successMessage = "Import completed: {$imported} records imported/updated, {$skipped} skipped";
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

// Fetch institutions for dropdown
$institutionsResult = $db->query("SELECT * FROM {$dbPrefix}institution WHERE is_active = 1 ORDER BY institution_name ASC");
$institutions = $institutionsResult->fetchAll();

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}institutional_outcomes
");
$stats = $statsResult->fetch();
$totalOutcomes = $stats['total'];
$activeOutcomes = $stats['active'];
$inactiveOutcomes = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Institutional Outcomes',
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
        
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-flag"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Outcomes</span>
                        <span class="info-box-number"><?= $totalOutcomes ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Outcomes</span>
                        <span class="info-box-number"><?= $activeOutcomes ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive Outcomes</span>
                        <span class="info-box-number"><?= $inactiveOutcomes ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outcomes Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Institutional Learning Outcomes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOutcomeModal">
                        <i class="fas fa-plus"></i> Add Outcome
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="outcomesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Institution</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Institution</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </tfoot>
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
                    <div class="mb-3">
                        <label for="institutionFk" class="form-label">Institution</label>
                        <select class="form-select" id="institutionFk" name="institution_fk" required>
                            <option value="">Select Institution</option>
                            <?php foreach ($institutions as $inst): ?>
                            <option value="<?= $inst['institution_pk'] ?>"><?= htmlspecialchars($inst['institution_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
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
                    <div class="mb-3">
                        <label for="editInstitutionFk" class="form-label">Institution</label>
                        <select class="form-select" id="editInstitutionFk" name="institution_fk" required>
                            <option value="">Select Institution</option>
                            <?php foreach ($institutions as $inst): ?>
                            <option value="<?= $inst['institution_pk'] ?>"><?= htmlspecialchars($inst['institution_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
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
                        <strong>Institution:</strong>
                        <p id="viewInstitution"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Outcome Code:</strong>
                        <p id="viewOutcomeCode"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <strong>Description:</strong>
                        <p id="viewOutcomeDescription"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Sequence:</strong>
                        <p id="viewSequenceNum"></p>
                    </div>
                    <div class="col-md-4">
                        <strong>Status:</strong>
                        <p id="viewOutcomeStatus"></p>
                    </div>
                    <div class="col-md-4">
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
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Institutional Outcomes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="outcomeUpload" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="outcomeUpload" name="outcome_upload" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: Institution Code, Outcome Code, Description, Sequence, Status (Active/Inactive)</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Existing records with matching codes will be updated.
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
    // Setup - add a text input to each footer cell
    $('#outcomesTable tfoot th').each(function() {
        var title = $(this).text();
        if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html(''); // No filter for Actions column
        }
    });
    
    var table = $('#outcomesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/institutional_outcomes_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'institutional_outcomes_pk' },
            { data: 1, name: 'institution_name' },
            { data: 2, name: 'outcome_code' },
            { data: 3, name: 'outcome_description' },
            { data: 4, name: 'sequence_num' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'created_at' },
            { data: 7, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
            // Apply the search
            this.api().columns().every(function() {
                var column = this;
                $('input', this.footer()).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function viewOutcome(outcome) {
    $('#viewInstitution').text(outcome.institution_name);
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
    $('#editInstitutionFk').val(outcome.institution_fk);
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
