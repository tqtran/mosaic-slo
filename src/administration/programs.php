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
                    $db->query(
                        "INSERT INTO {$dbPrefix}programs (program_code, program_name, degree_type, term_fk, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$code, $name, $degreeType, $termFk, $isActive],
                        'sssii'
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
                    $db->query(
                        "UPDATE {$dbPrefix}programs 
                         SET program_code = ?, program_name = ?, degree_type = ?, term_fk = ?, is_active = ?, updated_at = NOW()
                         WHERE programs_pk = ?",
                        [$code, $name, $degreeType, $termFk, $isActive, $id],
                        'sssiii'
                    );
                    $successMessage = 'Program updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['program_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}programs 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE programs_pk = ?",
                        [$id],
                        'i'
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
                
            case 'import':
                if (isset($_FILES['program_upload']) && $_FILES['program_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['program_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $headers = fgetcsv($handle); // Skip header row
                        $imported = 0;
                        $skipped = 0;
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 2) {
                                $code = trim($row[0]);
                                $name = trim($row[1]);
                                $degreeType = isset($row[2]) ? trim($row[2]) : '';
                                $termCode = isset($row[3]) ? trim($row[3]) : '';
                                $isActive = isset($row[4]) && strtolower(trim($row[4])) === 'active' ? 1 : 0;
                                
                                // Lookup term by code
                                $termFk = null;
                                if (!empty($termCode)) {
                                    $termLookup = $db->query(
                                        "SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ? AND is_active = 1",
                                        [$termCode],
                                        's'
                                    );
                                    if ($termLookup->rowCount() > 0) {
                                        $termRow = $termLookup->fetch();
                                        $termFk = $termRow['terms_pk'];
                                    }
                                }
                                
                                if (!empty($code) && !empty($name) && preg_match('/^[A-Z0-9_-]+$/i', $code) && $termFk !== null) {
                                    // Check if exists
                                    $result = $db->query(
                                        "SELECT programs_pk FROM {$dbPrefix}programs WHERE program_code = ?",
                                        [$code],
                                        's'
                                    );
                                    
                                    if ($result->rowCount() > 0) {
                                        // Update existing
                                        $existing = $result->fetch();
                                        $db->query(
                                            "UPDATE {$dbPrefix}programs 
                                             SET program_name = ?, degree_type = ?, term_fk = ?, is_active = ?, updated_at = NOW()
                                             WHERE programs_pk = ?",
                                            [$name, $degreeType, $termFk, $isActive, $existing['programs_pk']],
                                            'ssiii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}programs (program_code, program_name, degree_type, term_fk, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$code, $name, $degreeType, $termFk, $isActive],
                                            'sssii'
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

// Fetch terms for dropdown
$termResult = $db->query("
    SELECT terms_pk, term_code, term_name 
    FROM {$dbPrefix}terms 
    WHERE is_active = 1 
    ORDER BY term_name DESC
");
$terms = $termResult->fetchAll();

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}programs
");
$stats = $statsResult->fetch();
$totalPrograms = $stats['total'];
$activePrograms = $stats['active'];
$inactivePrograms = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Program Management',
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
        
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-graduation-cap"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Programs</span>
                        <span class="info-box-number"><?= $totalPrograms ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Programs</span>
                        <span class="info-box-number"><?= $activePrograms ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive Programs</span>
                        <span class="info-box-number"><?= $inactivePrograms ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Programs Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Academic Programs</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
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
                            <th>Program Code</th>
                            <th>Program Name</th>
                            <th>Department</th>
                            <th>Degree Type</th>
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
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Program Code</th>
                            <th>Program Name</th>
                            <th>Department</th>
                            <th>Degree Type</th>
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
                            <input type="text" class="form-control" id="degreeType" name="degree_type" maxlength="50" placeholder="e.g., AS, BS, BA, MS">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="programName" class="form-label">Program Name</label>
                        <input type="text" class="form-control" id="programName" name="program_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="termFk" class="form-label">Term</label>
                        <select class="form-select" id="termFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?= $term['terms_pk'] ?>"><?= htmlspecialchars($term['term_name']) ?> (<?= htmlspecialchars($term['term_code']) ?>)</option>
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
                            <input type="text" class="form-control" id="editDegreeType" name="degree_type" maxlength="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editProgramName" class="form-label">Program Name</label>
                        <input type="text" class="form-control" id="editProgramName" name="program_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTermFk" class="form-label">Term</label>
                        <select class="form-select" id="editTermFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?= $term['terms_pk'] ?>"><?= htmlspecialchars($term['term_name']) ?> (<?= htmlspecialchars($term['term_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
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
                    <div class="col-md-6">
                        <strong>Program Name:</strong>
                        <p id="viewProgramName"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Program Code:</strong>
                        <p id="viewProgramCode"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Degree Type:</strong>
                        <p id="viewDegreeType"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Department:</strong>
                        <p id="viewDepartment"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewProgramStatus"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>ID:</strong>
                        <p id="viewProgramId"></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewProgramCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewProgramUpdated"></p>
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
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Programs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="programUpload" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="programUpload" name="program_upload" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: Program Code, Program Name, Degree Type, Department Code, Status (Active/Inactive)</small>
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
// Convert PHP arrays to JavaScript
var departments = <?= json_encode(array_map(function($d) { 
    return ['name' => $d['department_name'], 'code' => $d['department_code']]; 
}, $departments)) ?>;

$(document).ready(function() {
    // Setup - add filters to each header cell (second row)
    $('#programsTable thead tr:eq(1) th').each(function(i) {
        var title = $('#programsTable thead tr:eq(0) th:eq(' + i + ')').text();
        
        // Department column (index 3) gets dropdown
        if (title === 'Department') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Departments</option></select>')
                .appendTo($(this).empty());
            
            // Populate from PHP data
            departments.forEach(function(dept) {
                select.append('<option value="' + dept.name + '">' + dept.name + '</option>');
            });
        } else if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#programsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/programs_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'programs_pk' },
            { data: 1, name: 'program_code' },
            { data: 2, name: 'program_name' },
            { data: 3, name: 'department_name' },
            { data: 4, name: 'degree_type' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'created_at' },
            { data: 7, name: 'actions', orderable: false, searchable: false }
        ],
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

function viewProgram(prog) {
    $('#viewProgramName').text(prog.program_name);
    $('#viewProgramCode').text(prog.program_code);
    $('#viewDegreeType').text(prog.degree_type || 'N/A');
    $('#viewDepartment').text(prog.department_name ? prog.department_name + ' (' + prog.department_code + ')' : 'N/A');
    $('#viewProgramStatus').html(prog.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewProgramId').text(prog.programs_pk);
    $('#viewProgramCreated').text(prog.created_at);
    $('#viewProgramUpdated').text(prog.updated_at);
    new bootstrap.Modal(document.getElementById('viewProgramModal')).show();
}

function editProgram(prog) {
    $('#editProgramId').val(prog.programs_pk);
    $('#editProgramCode').val(prog.program_code);
    $('#editProgramName').val(prog.program_name);
    $('#editDegreeType').val(prog.degree_type);
    $('#editTermFk').val(prog.term_fk);
    $('#editIsActive').prop('checked', prog.is_active == 1);
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
