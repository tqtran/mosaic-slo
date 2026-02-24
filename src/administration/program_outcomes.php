<?php
declare(strict_types=1);

/**
 * Program Outcomes Administration
 * 
 * Manage program-level learning outcomes.
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
                $programFk = (int)($_POST['program_fk'] ?? 0);
                $institutionalOutcomesFk = !empty($_POST['institutional_outcomes_fk']) ? (int)$_POST['institutional_outcomes_fk'] : null;
                $outcomeCode = trim($_POST['outcome_code'] ?? '');
                $outcomeDescription = trim($_POST['outcome_description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($programFk <= 0) {
                    $errors[] = 'Program is required';
                }
                if (empty($outcomeCode)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    // Check uniqueness for program + code combination
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes WHERE outcome_code = ? AND program_fk = ?",
                        [$outcomeCode, $programFk],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists for this program';
                    }
                }
                if (empty($outcomeDescription)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    if ($institutionalOutcomesFk !== null) {
                        $db->query(
                            "INSERT INTO {$dbPrefix}program_outcomes (program_fk, institutional_outcomes_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$programFk, $institutionalOutcomesFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                            'iissii'
                        );
                    } else {
                        $db->query(
                            "INSERT INTO {$dbPrefix}program_outcomes (program_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                            [$programFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                            'issii'
                        );
                    }
                    $successMessage = 'Program outcome added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['outcome_id'] ?? 0);
                $programFk = (int)($_POST['program_fk'] ?? 0);
                $institutionalOutcomesFk = !empty($_POST['institutional_outcomes_fk']) ? (int)$_POST['institutional_outcomes_fk'] : null;
                $outcomeCode = trim($_POST['outcome_code'] ?? '');
                $outcomeDescription = trim($_POST['outcome_description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid outcome ID';
                }
                if ($programFk <= 0) {
                    $errors[] = 'Program is required';
                }
                if (empty($outcomeCode)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes 
                         WHERE outcome_code = ? AND program_fk = ? AND program_outcomes_pk != ?",
                        [$outcomeCode, $programFk, $id],
                        'sii'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists for this program';
                    }
                }
                if (empty($outcomeDescription)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    if ($institutionalOutcomesFk !== null) {
                        $db->query(
                            "UPDATE {$dbPrefix}program_outcomes 
                             SET program_fk = ?, institutional_outcomes_fk = ?, outcome_code = ?, outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                             WHERE program_outcomes_pk = ?",
                            [$programFk, $institutionalOutcomesFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive, $id],
                            'iissiii'
                        );
                    } else {
                        $db->query(
                            "UPDATE {$dbPrefix}program_outcomes 
                             SET program_fk = ?, institutional_outcomes_fk = NULL, outcome_code = ?, outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                             WHERE program_outcomes_pk = ?",
                            [$programFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive, $id],
                            'issiii'
                        );
                    }
                    $successMessage = 'Program outcome updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['outcome_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}program_outcomes 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE program_outcomes_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Outcome status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['outcome_id'] ?? 0);
                if ($id > 0) {
                    // Check if outcome has associated SLOs
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}student_learning_outcomes WHERE program_outcome_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete outcome: it is mapped to student learning outcomes. Please remove mappings first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}program_outcomes WHERE program_outcomes_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Program outcome deleted successfully';
                    }
                }
                break;
                
            case 'import':
                if (isset($_FILES['outcome_upload']) && $_FILES['outcome_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['outcome_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        // Skip BOM if present
                        $bom = fread($handle, 3);
                        if ($bom !== "\xEF\xBB\xBF") {
                            rewind($handle);
                        }
                        
                        $headers = fgetcsv($handle); // Read header row
                        $imported = 0;
                        $updated = 0;
                        $errors = [];
                        $rowNum = 1;
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNum++;
                            if (count($row) >= 3) {
                                $programCode = trim($row[0]);
                                $outcomeCode = trim($row[1]);
                                $outcomeDescription = trim($row[2]);
                                $institutionalOutcomeCode = isset($row[3]) ? trim($row[3]) : '';
                                $sequenceNum = isset($row[4]) ? (int)trim($row[4]) : 0;
                                $isActive = isset($row[5]) && strtolower(trim($row[5])) === '1' ? 1 : 0;
                                
                                // Find program by code
                                $progResult = $db->query(
                                    "SELECT programs_pk FROM {$dbPrefix}programs WHERE program_code = ?",
                                    [$programCode],
                                    's'
                                );
                                
                                if ($progResult->rowCount() === 0) {
                                    $errors[] = "Row $rowNum: Program code '$programCode' not found";
                                    continue;
                                }
                                
                                $prog = $progResult->fetch();
                                $programFk = $prog['programs_pk'];
                                
                                // Find institutional outcome if specified
                                $institutionalOutcomesFk = null;
                                if (!empty($institutionalOutcomeCode)) {
                                    $ioResult = $db->query(
                                        "SELECT institutional_outcomes_pk FROM {$dbPrefix}institutional_outcomes WHERE outcome_code = ?",
                                        [$institutionalOutcomeCode],
                                        's'
                                    );
                                    if ($ioResult->rowCount() > 0) {
                                        $io = $ioResult->fetch();
                                        $institutionalOutcomesFk = $io['institutional_outcomes_pk'];
                                    } else {
                                        $errors[] = "Row $rowNum: Institutional outcome code '$institutionalOutcomeCode' not found";
                                        continue;
                                    }
                                }
                                
                                if (empty($outcomeCode) || empty($outcomeDescription) || !preg_match('/^[A-Z0-9_.-]+$/i', $outcomeCode)) {
                                    $errors[] = "Row $rowNum: Invalid outcome code or description";
                                    continue;
                                }
                                
                                // Check if exists
                                $result = $db->query(
                                    "SELECT program_outcomes_pk FROM {$dbPrefix}program_outcomes 
                                     WHERE outcome_code = ? AND program_fk = ?",
                                    [$outcomeCode, $programFk],
                                    'si'
                                );
                                
                                if ($result->rowCount() > 0) {
                                    // Update existing
                                    $existing = $result->fetch();
                                    if ($institutionalOutcomesFk !== null) {
                                        $db->query(
                                            "UPDATE {$dbPrefix}program_outcomes 
                                             SET institutional_outcomes_fk = ?, outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                                             WHERE program_outcomes_pk = ?",
                                            [$institutionalOutcomesFk, $outcomeDescription, $sequenceNum, $isActive, $existing['program_outcomes_pk']],
                                            'isiii'
                                        );
                                    } else {
                                        $db->query(
                                            "UPDATE {$dbPrefix}program_outcomes 
                                             SET institutional_outcomes_fk = NULL, outcome_description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                                             WHERE program_outcomes_pk = ?",
                                            [$outcomeDescription, $sequenceNum, $isActive, $existing['program_outcomes_pk']],
                                            'siii'
                                        );
                                    }
                                    $updated++;
                                } else {
                                    // Insert new
                                    if ($institutionalOutcomesFk !== null) {
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}program_outcomes (program_fk, institutional_outcomes_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$programFk, $institutionalOutcomesFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                                            'iissii'
                                        );
                                    } else {
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}program_outcomes (program_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$programFk, $outcomeCode, $outcomeDescription, $sequenceNum, $isActive],
                                            'issii'
                                        );
                                    }
                                    $imported++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        
                        $summary = "$imported new, $updated updated";
                        if (count($errors) > 0) {
                            $errorList = array_slice($errors, 0, 5);
                            $errorMessage = "Import completed with errors: $summary<br><br>" . implode('<br>', $errorList);
                            if (count($errors) > 5) {
                                $errorMessage .= "<br>...and " . (count($errors) - 5) . " more errors";
                            }
                        } else {
                            $successMessage = "Import completed successfully: $summary";
                        }
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
    ORDER BY term_name DESC
");
$terms = $termsResult->fetchAll();

// Get selected term (default to latest/first)
$selectedTermFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : ($terms[0]['terms_pk'] ?? null);

// Fetch programs for dropdown
$programsResult = $db->query("SELECT * FROM {$dbPrefix}programs WHERE is_active = 1 ORDER BY program_name ASC");
$programs = $programsResult->fetchAll();

// Fetch institutional outcomes for dropdown
$institutionalOutcomesResult = $db->query(
    "SELECT io.institutional_outcomes_pk, io.outcome_code, io.outcome_description
     FROM {$dbPrefix}institutional_outcomes io
     WHERE io.is_active = 1 
     ORDER BY io.outcome_code"
);
$institutionalOutcomes = $institutionalOutcomesResult->fetchAll();

// Calculate statistics (filtered by term)
$termFilter = $selectedTermFk ? "WHERE p.term_fk = {$selectedTermFk}" : '';
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN po.is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN po.is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}program_outcomes po
    JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
    {$termFilter}
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
    'pageTitle' => 'Program Outcomes',
    'currentPage' => 'admin_program_outcomes',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Program Outcomes']
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
                    <li class="breadcrumb-item active">Program Outcomes</li>
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
        
        <!-- Filter and Statistics Row -->
        <div class="row mb-3">
            <div class="col-12 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <label for="termFilter" class="form-label"><i class="fas fa-filter"></i> Filter by Term</label>
                        <select id="termFilter" class="form-select">
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($term['term_name']) ?>
                                    <?= !empty($term['academic_year']) ? ' (' . htmlspecialchars($term['academic_year']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-bullseye"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Outcomes</span>
                        <span class="info-box-number"><?= $totalOutcomes ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Outcomes</span>
                        <span class="info-box-number"><?= $activeOutcomes ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive Outcomes</span>
                        <span class="info-box-number"><?= $inactiveOutcomes ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Program Outcomes Table -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bullseye"></i> Program Outcomes
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOutcomeModal">
                        <i class="fas fa-plus"></i> Add Outcome
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="outcomesTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Program</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Institutional Outcome</th>
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
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Outcome Modal -->
<div class="modal fade" id="addOutcomeModal" tabindex="-1" aria-labelledby="addOutcomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addOutcomeModalLabel">
                        <i class="fas fa-plus"></i> Add Program Outcome
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="programFk" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="programFk" name="program_fk" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?= $prog['programs_pk'] ?>">
                                    <?= htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="outcomeCode" class="form-label">Outcome Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="outcomeCode" name="outcome_code" 
                               required pattern="[A-Z0-9_.-]+" 
                               placeholder="e.g., PO1, PO-2, OUTCOME1">
                        <small class="form-text text-muted">Letters, numbers, hyphens, underscores, and periods only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="outcomeDescription" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="outcomeDescription" name="outcome_description" 
                                  rows="3" required placeholder="Enter outcome description"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="institutionalOutcomesFk" class="form-label">Institutional Outcome (Optional)</label>
                        <select class="form-select" id="institutionalOutcomesFk" name="institutional_outcomes_fk">
                            <option value="">-- No Mapping --</option>
                            <?php foreach ($institutionalOutcomes as $io): ?>
                                <option value="<?= $io['institutional_outcomes_pk'] ?>">
                                    <?= htmlspecialchars($io['outcome_code'] . ' - ' . substr($io['outcome_description'], 0, 60)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="sequenceNum" name="sequence_num" 
                                   value="0" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Outcome
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Outcome Modal -->
<div class="modal fade" id="editOutcomeModal" tabindex="-1" aria-labelledby="editOutcomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="outcome_id" id="editOutcomeId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editOutcomeModalLabel">
                        <i class="fas fa-edit"></i> Edit Program Outcome
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editProgramFk" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="editProgramFk" name="program_fk" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?= $prog['programs_pk'] ?>">
                                    <?= htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editOutcomeCode" class="form-label">Outcome Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editOutcomeCode" name="outcome_code" 
                               required pattern="[A-Z0-9_.-]+">
                        <small class="form-text text-muted">Letters, numbers, hyphens, underscores, and periods only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editOutcomeDescription" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="editOutcomeDescription" name="outcome_description" 
                                  rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editInstitutionalOutcomesFk" class="form-label">Institutional Outcome (Optional)</label>
                        <select class="form-select" id="editInstitutionalOutcomesFk" name="institutional_outcomes_fk">
                            <option value="">-- No Mapping --</option>
                            <?php foreach ($institutionalOutcomes as $io): ?>
                                <option value="<?= $io['institutional_outcomes_pk'] ?>">
                                    <?= htmlspecialchars($io['outcome_code'] . ' - ' . substr($io['outcome_description'], 0, 60)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editSequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="editSequenceNum" name="sequence_num" 
                                   value="0" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                                <label class="form-check-label" for="editIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Outcome
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Outcome Modal -->
<div class="modal fade" id="viewOutcomeModal" tabindex="-1" aria-labelledby="viewOutcomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewOutcomeModalLabel">
                    <i class="fas fa-eye"></i> View Program Outcome
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">ID:</dt>
                    <dd class="col-sm-9" id="viewId"></dd>
                    
                    <dt class="col-sm-3">Program:</dt>
                    <dd class="col-sm-9" id="viewProgram"></dd>
                    
                    <dt class="col-sm-3">Code:</dt>
                    <dd class="col-sm-9" id="viewCode"></dd>
                    
                    <dt class="col-sm-3">Description:</dt>
                    <dd class="col-sm-9" id="viewDescription"></dd>
                    
                    <dt class="col-sm-3">Institutional Outcome:</dt>
                    <dd class="col-sm-9" id="viewInstitutionalOutcome"></dd>
                    
                    <dt class="col-sm-3">Sequence:</dt>
                    <dd class="col-sm-9" id="viewSequence"></dd>
                    
                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9" id="viewStatus"></dd>
                    
                    <dt class="col-sm-3">Created:</dt>
                    <dd class="col-sm-9" id="viewCreated"></dd>
                    
                    <dt class="col-sm-3">Updated:</dt>
                    <dd class="col-sm-9" id="viewUpdated"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1" aria-labelledby="uploadCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="import">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadCsvModalLabel">
                        <i class="fas fa-upload"></i> Import Program Outcomes CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="outcomeUpload" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="outcomeUpload" name="outcome_upload" accept=".csv" required>
                    </div>
                    <div class="alert alert-info">
                        <strong>CSV Format:</strong>
                        <ul class="mb-0 small">
                            <li>program_code,outcome_code,outcome_description,institutional_outcome_code,sequence_num,is_active</li>
                            <li>program_code: Existing program code (required)</li>
                            <li>outcome_code: Unique code for this program (required)</li>
                            <li>outcome_description: Full description (required)</li>
                            <li>institutional_outcome_code: Optional mapping to institutional outcome</li>
                            <li>sequence_num: Display order (default 0)</li>
                            <li>is_active: 1 or 0 (default 0)</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden forms for toggle and delete -->
<form id="toggleStatusForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="outcome_id" id="toggleOutcomeId">
</form>

<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
// Convert PHP arrays to JavaScript
var programs = <?= json_encode(array_map(function($p) { 
    return ['name' => $p['program_name'], 'code' => $p['program_code']]; 
}, $programs)) ?>;
var institutionalOutcomes = <?= json_encode(array_map(function($io) { 
    return ['code' => $io['outcome_code']]; 
}, $institutionalOutcomes)) ?>;

$(document).ready(function() {
    // Term filter change handler
    $('#termFilter').on('change', function() {
        var termFk = $(this).val();
        window.location.href = '<?= BASE_URL ?>administration/program_outcomes.php?term_fk=' + termFk;
    });
    
    // Setup - add filters to each header cell (second row)
    $('#outcomesTable thead tr:eq(1) th').each(function(i) {
        var title = $('#outcomesTable thead tr:eq(0) th:eq(' + i + ')').text();
        
        // Program column (index 1)
        if (title === 'Program') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Programs</option></select>')
                .appendTo($(this).empty());
            programs.forEach(function(program) {
                select.append('<option value="' + program.name + '">' + program.name + '</option>');
            });
        }
        // Institutional Outcome column (index 4)
        else if (title === 'Institutional Outcome') {
            var select = $('<select class="form-select form-select-sm"><option value="">All</option></select>')
                .appendTo($(this).empty());
            institutionalOutcomes.forEach(function(io) {
                if (io.code) {
                    select.append('<option value="' + io.code + '">' + io.code + '</option>');
                }
            });
        }
        else if (title !== 'Actions' && title !== 'ID') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    // Initialize DataTable
    var table = $('#outcomesTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: 'program_outcomes_data.php',
            data: function(d) {
                d.term_fk = $('#termFilter').val();
            }
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        order: [[5, 'asc'], [2, 'asc']],
        pageLength: 25,
        columnDefs: [
            { targets: [8], orderable: false },
            { targets: [0], visible: false }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search outcomes..."
        },
        initComplete: function() {
            // Apply the search
            this.api().columns().every(function() {
                var column = this;
                $('select', this.header()).on('change', function() {
                    column.search($(this).val()).draw();
                });
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
    $('#viewId').text(outcome.program_outcomes_pk);
    $('#viewProgram').text(outcome.program_name);
    $('#viewCode').text(outcome.outcome_code);
    $('#viewDescription').text(outcome.outcome_description);
    $('#viewInstitutionalOutcome').text(outcome.institutional_outcome_code || 'N/A');
    $('#viewSequence').text(outcome.sequence_num);
    $('#viewStatus').html('<span class="badge bg-' + (outcome.is_active ? 'success' : 'secondary') + '">' + 
                          (outcome.is_active ? 'Active' : 'Inactive') + '</span>');
    $('#viewCreated').text(outcome.created_at || 'N/A');
    $('#viewUpdated').text(outcome.updated_at || 'N/A');
    new bootstrap.Modal(document.getElementById('viewOutcomeModal')).show();
}

function editOutcome(outcome) {
    $('#editOutcomeId').val(outcome.program_outcomes_pk);
    $('#editProgramFk').val(outcome.program_fk);
    $('#editOutcomeCode').val(outcome.outcome_code);
    $('#editOutcomeDescription').val(outcome.outcome_description);
    $('#editInstitutionalOutcomesFk').val(outcome.institutional_outcomes_fk || '');
    $('#editSequenceNum').val(outcome.sequence_num);
    $('#editIsActive').prop('checked', outcome.is_active == 1);
    new bootstrap.Modal(document.getElementById('editOutcomeModal')).show();
}

function toggleStatus(id, code) {
    if (confirm('Are you sure you want to toggle the status of outcome "' + code + '"?')) {
        $('#toggleOutcomeId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteOutcome(id, code) {
    if (confirm('Are you sure you want to DELETE outcome "' + code + '"? This action cannot be undone.')) {
        $('#deleteOutcomeId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
