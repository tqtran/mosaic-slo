<?php
declare(strict_types=1);

/**
 * Program Administration
 * 
 * Manage academic programs by department.
 * 
 * @package Mosaic
 */

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize common variables and database
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
                $departmentFk = (int)($_POST['department_fk'] ?? 0);
                $code = trim($_POST['program_code'] ?? '');
                $name = trim($_POST['program_name'] ?? '');
                $degreeType = trim($_POST['degree_type'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($departmentFk <= 0) {
                    $errors[] = 'Department is required';
                }
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
                    $row = $result->fetch_assoc();
                    if ($row['count'] > 0) {
                        $errors[] = 'Program code already exists';
                    }
                }
                if (empty($name)) {
                    $errors[] = 'Program name is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}programs (department_fk, program_code, program_name, degree_type, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$departmentFk, $code, $name, $degreeType, $isActive],
                        'isssi'
                    );
                    $successMessage = 'Program added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['program_id'] ?? 0);
                $departmentFk = (int)($_POST['department_fk'] ?? 0);
                $code = trim($_POST['program_code'] ?? '');
                $name = trim($_POST['program_name'] ?? '');
                $degreeType = trim($_POST['degree_type'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid program ID';
                }
                if ($departmentFk <= 0) {
                    $errors[] = 'Department is required';
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
                    $row = $result->fetch_assoc();
                    if ($row['count'] > 0) {
                        $errors[] = 'Program code already exists';
                    }
                }
                if (empty($name)) {
                    $errors[] = 'Program name is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}programs 
                         SET department_fk = ?, program_code = ?, program_name = ?, degree_type = ?, is_active = ?, updated_at = NOW()
                         WHERE programs_pk = ?",
                        [$departmentFk, $code, $name, $degreeType, $isActive, $id],
                        'isssii'
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
                
            case 'import':
                if (isset($_FILES['program_upload']) && $_FILES['program_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['program_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $headers = fgetcsv($handle); // Skip header row
                        $imported = 0;
                        $skipped = 0;
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 3) {
                                $departmentCode = trim($row[0]);
                                $code = trim($row[1]);
                                $name = trim($row[2]);
                                $degreeType = isset($row[3]) ? trim($row[3]) : '';
                                $isActive = isset($row[4]) && strtolower(trim($row[4])) === 'active' ? 1 : 0;
                                
                                // Look up department
                                $deptResult = $db->query(
                                    "SELECT departments_pk FROM {$dbPrefix}departments WHERE department_code = ?",
                                    [$departmentCode],
                                    's'
                                );
                                
                                if ($deptResult->num_rows > 0 && !empty($code) && !empty($name) && preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                                    $dept = $deptResult->fetch_assoc();
                                    $departmentFk = $dept['departments_pk'];
                                    
                                    // Check if exists
                                    $result = $db->query(
                                        "SELECT programs_pk FROM {$dbPrefix}programs WHERE program_code = ?",
                                        [$code],
                                        's'
                                    );
                                    
                                    if ($result->num_rows > 0) {
                                        // Update existing
                                        $existing = $result->fetch_assoc();
                                        $db->query(
                                            "UPDATE {$dbPrefix}programs 
                                             SET department_fk = ?, program_name = ?, degree_type = ?, is_active = ?, updated_at = NOW()
                                             WHERE programs_pk = ?",
                                            [$departmentFk, $name, $degreeType, $isActive, $existing['programs_pk']],
                                            'issii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}programs (department_fk, program_code, program_name, degree_type, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$departmentFk, $code, $name, $degreeType, $isActive],
                                            'isssi'
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

// Fetch all programs with department JOIN
$result = $db->query("
    SELECT 
        p.*,
        d.department_name,
        d.department_code
    FROM {$dbPrefix}programs p
    LEFT JOIN {$dbPrefix}departments d ON p.department_fk = d.departments_pk
    ORDER BY p.program_name ASC
");
$programs = $result->fetch_all(MYSQLI_ASSOC);

// Fetch all departments for dropdown
$deptResult = $db->query("SELECT * FROM {$dbPrefix}departments WHERE is_active = 1 ORDER BY department_name ASC");
$departments = $deptResult->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalPrograms = count($programs);
$activePrograms = count(array_filter($programs, fn($p) => $p['is_active']));
$inactivePrograms = $totalPrograms - $activePrograms;

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
            <div class="col-sm-6">
                <h3 class="mb-0">Program Management</h3>
            </div>
            <div class="col-sm-6">
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
            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= $totalPrograms ?></h3>
                        <p>Total Programs</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= $activePrograms ?></h3>
                        <p>Active Programs</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?= $inactivePrograms ?></h3>
                        <p>Inactive Programs</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-ban"></i>
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
                    </thead>
                    <tbody>
                        <?php foreach ($programs as $row):
                            $status = $row['is_active'] ? 'Active' : 'Inactive';
                            $statusClass = $row['is_active'] ? 'success' : 'secondary';
                            $toggleIcon = $row['is_active'] ? 'ban' : 'check';
                            $toggleClass = $row['is_active'] ? 'warning' : 'success';
                            $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['programs_pk']) ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($row['program_code']) ?></span></td>
                            <td><?= htmlspecialchars($row['program_name']) ?></td>
                            <td><?= htmlspecialchars($row['department_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['degree_type'] ?? '') ?></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= $status ?></span></td>
                            <td><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" title="View" onclick='viewProgram(<?= $rowJson ?>)'>
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary" title="Edit" onclick='editProgram(<?= $rowJson ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-<?= $toggleClass ?>" title="Toggle Status" 
                                        onclick="toggleStatus(<?= $row['programs_pk'] ?>, '<?= htmlspecialchars($row['program_name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-<?= $toggleIcon ?>"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                    <div class="mb-3">
                        <label for="departmentFk" class="form-label">Department</label>
                        <select class="form-select" id="departmentFk" name="department_fk" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['departments_pk'] ?>">
                                <?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                    <div class="mb-3">
                        <label for="editDepartmentFk" class="form-label">Department</label>
                        <select class="form-select" id="editDepartmentFk" name="department_fk" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['departments_pk'] ?>">
                                <?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                        <strong>Department:</strong>
                        <p id="viewDepartment"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Degree Type:</strong>
                        <p id="viewDegreeType"></p>
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
                        <small class="form-text text-muted">CSV format: Department Code, Program Code, Program Name, Degree Type, Status (Active/Inactive)</small>
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
    $('#programsTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});

function viewProgram(prog) {
    $('#viewProgramName').text(prog.program_name);
    $('#viewProgramCode').text(prog.program_code);
    $('#viewDepartment').text(prog.department_name || 'N/A');
    $('#viewDegreeType').text(prog.degree_type || 'N/A');
    $('#viewProgramStatus').html(prog.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewProgramId').text(prog.programs_pk);
    $('#viewProgramCreated').text(prog.created_at);
    $('#viewProgramUpdated').text(prog.updated_at);
    new bootstrap.Modal(document.getElementById('viewProgramModal')).show();
}

function editProgram(prog) {
    $('#editProgramId').val(prog.programs_pk);
    $('#editDepartmentFk').val(prog.department_fk);
    $('#editProgramCode').val(prog.program_code);
    $('#editProgramName').val(prog.program_name);
    $('#editDegreeType').val(prog.degree_type);
    $('#editIsActive').prop('checked', prog.is_active == 1);
    new bootstrap.Modal(document.getElementById('editProgramModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Are you sure you want to toggle the status of "' + name + '"?')) {
        $('#toggleProgramId').val(id);
        $('#toggleStatusForm').submit();
    }
}
</script>
