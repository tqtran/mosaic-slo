<?php
declare(strict_types=1);

/**
 * Institution Administration
 * 
 * Manage institution records (root entity).
 * Uses pragmatic page pattern (logic + template in one file).
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

// Load core classes
require_once __DIR__ . '/../system/Core/Config.php';
require_once __DIR__ . '/../system/Core/Database.php';
require_once __DIR__ . '/../system/Core/Path.php';

// Check if configured
if (!file_exists(__DIR__ . '/../config/config.yaml')) {
    \Mosaic\Core\Path::redirect('setup/');
}

// Load configuration
$config = \Mosaic\Core\Config::getInstance(__DIR__ . '/../config/config.yaml');
$configData = $config->all();

// Define constants
define('BASE_URL', $configData['app']['base_url'] ?? '/');
define('SITE_NAME', $configData['app']['name'] ?? 'MOSAIC');
define('DEBUG_MODE', ($configData['app']['debug_mode'] ?? 'false') === 'true' || ($configData['app']['debug_mode'] ?? false) === true);

// Initialize database
$db = \Mosaic\Core\Database::getInstance($configData['database']);

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
                $name = trim($_POST['institution_name'] ?? '');
                $code = trim($_POST['institution_code'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if (empty($name)) {
                    $errors[] = 'Institution name is required';
                }
                if (empty($code)) {
                    $errors[] = 'Institution code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Institution code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM institution WHERE institution_code = ?",
                        [$code],
                        's'
                    );
                    $row = $result->fetch_assoc();
                    if ($row['count'] > 0) {
                        $errors[] = 'Institution code already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO institution (institution_name, institution_code, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, NOW(), NOW())",
                        [$name, $code, $isActive],
                        'ssi'
                    );
                    $successMessage = 'Institution added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['institution_id'] ?? 0);
                $name = trim($_POST['institution_name'] ?? '');
                $code = trim($_POST['institution_code'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid institution ID';
                }
                if (empty($name)) {
                    $errors[] = 'Institution name is required';
                }
                if (empty($code)) {
                    $errors[] = 'Institution code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Institution code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM institution WHERE institution_code = ? AND institution_pk != ?",
                        [$code, $id],
                        'si'
                    );
                    $row = $result->fetch_assoc();
                    if ($row['count'] > 0) {
                        $errors[] = 'Institution code already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE institution 
                         SET institution_name = ?, institution_code = ?, is_active = ?, updated_at = NOW()
                         WHERE institution_pk = ?",
                        [$name, $code, $isActive, $id],
                        'ssii'
                    );
                    $successMessage = 'Institution updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['institution_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE institution 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE institution_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Institution status updated';
                }
                break;
                
            case 'import':
                if (isset($_FILES['institution_upload']) && $_FILES['institution_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['institution_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $headers = fgetcsv($handle); // Skip header row
                        $imported = 0;
                        $skipped = 0;
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 2) {
                                $name = trim($row[0]);
                                $code = trim($row[1]);
                                $isActive = isset($row[2]) && strtolower(trim($row[2])) === 'active' ? 1 : 0;
                                
                                if (!empty($name) && !empty($code) && preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                                    // Check if exists
                                    $result = $db->query(
                                        "SELECT institution_pk FROM institution WHERE institution_code = ?",
                                        [$code],
                                        's'
                                    );
                                    
                                    if ($result->num_rows > 0) {
                                        // Update existing
                                        $existing = $result->fetch_assoc();
                                        $db->query(
                                            "UPDATE institution 
                                             SET institution_name = ?, is_active = ?, updated_at = NOW()
                                             WHERE institution_pk = ?",
                                            [$name, $isActive, $existing['institution_pk']],
                                            'sii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO institution (institution_name, institution_code, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, NOW(), NOW())",
                                            [$name, $code, $isActive],
                                            'ssi'
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

// Fetch all institutions
$result = $db->query("SELECT * FROM institution ORDER BY institution_name ASC");
$institutions = $result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalInstitutions = count($institutions);
$activeInstitutions = count(array_filter($institutions, fn($i) => $i['is_active']));
$inactiveInstitutions = $totalInstitutions - $activeInstitutions;

$currentPage = 'admin_institution';
$pageTitle = 'Institution Management - ' . SITE_NAME;
$bodyClass = 'hold-transition sidebar-mini layout-fixed';
require_once __DIR__ . '/../system/includes/header.php';
?>

<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= BASE_URL ?>" class="nav-link"><i class="fas fa-home"></i> Home</a>
            </li>
        </ul>
        
        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link">
                    <strong><?= htmlspecialchars(SITE_NAME) ?></strong>
                </span>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

<?php require_once __DIR__ . '/../system/includes/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-university"></i> Institution Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                            <li class="breadcrumb-item active">Institutions</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row">
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?= $totalInstitutions ?></h3>
                                <p>Total Institutions</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-university"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= $activeInstitutions ?></h3>
                                <p>Active Institutions</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= $inactiveInstitutions ?></h3>
                                <p>Inactive Institutions</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-ban"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Institutions Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-table"></i> Institutions</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-file-upload"></i> Import CSV
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInstitutionModal">
                                <i class="fas fa-plus"></i> Add Institution
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                       <table id="institutionsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Institution Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($institutions as $inst): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($inst['institution_pk']) ?></td>
                                        <td><?= htmlspecialchars($inst['institution_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($inst['institution_code']) ?></span></td>
                                        <td>
                                            <?php if ($inst['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($inst['created_at']) ?></td>
                                        <td><?= htmlspecialchars($inst['updated_at']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" title="View" onclick="viewInstitution(<?= htmlspecialchars(json_encode($inst)) ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" title="Edit" onclick="editInstitution(<?= htmlspecialchars(json_encode($inst)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-<?= $inst['is_active'] ? 'warning' : 'success' ?>" title="Toggle Status" onclick="toggleStatus(<?= $inst['institution_pk'] ?>, '<?= htmlspecialchars($inst['institution_name']) ?>')">
                                                <i class="fas fa-<?= $inst['is_active'] ? 'ban' : 'check' ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Add Institution Modal -->
<div class="modal fade" id="addInstitutionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Institution</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="institutionName" class="form-label">Institution Name</label>
                        <input type="text" class="form-control" id="institutionName" name="institution_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="institutionCode" class="form-label">Institution Code</label>
                        <input type="text" class="form-control" id="institutionCode" name="institution_code" maxlength="50" required>
                        <small class="form-text text-muted">Unique identifier (letters, numbers, hyphens, underscores only)</small>
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

<!-- Edit Institution Modal -->
<div class="modal fade" id="editInstitutionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Institution</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="institution_id" id="editInstitutionId">
                    <div class="mb-3">
                        <label for="editInstitutionName" class="form-label">Institution Name</label>
                        <input type="text" class="form-control" id="editInstitutionName" name="institution_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editInstitutionCode" class="form-label">Institution Code</label>
                        <input type="text" class="form-control" id="editInstitutionCode" name="institution_code" maxlength="50" required>
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

<!-- View Institution Modal -->
<div class="modal fade" id="viewInstitutionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Institution Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Institution Name:</strong>
                        <p id="viewInstitutionName"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Code:</strong>
                        <p id="viewInstitutionCode"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewInstitutionStatus"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>ID:</strong>
                        <p id="viewInstitutionId"></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewInstitutionCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewInstitutionUpdated"></p>
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
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Institutions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="institutionUpload" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="institutionUpload" name="institution_upload" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: Institution Name, Code, Status (Active/Inactive)</small>
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

<!-- Toggle Status Form -->
<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="institution_id" id="toggleInstitutionId">
</form>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

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
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function() {
    $('#institutionsTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, "asc"]],
        dom: 'Blfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print', 'colvis'
        ]
    });
});

function viewInstitution(inst) {
    $('#viewInstitutionName').text(inst.institution_name);
    $('#viewInstitutionCode').text(inst.institution_code);
    $('#viewInstitutionStatus').html(inst.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewInstitutionId').text(inst.institution_pk);
    $('#viewInstitutionCreated').text(inst.created_at);
    $('#viewInstitutionUpdated').text(inst.updated_at);
    new bootstrap.Modal(document.getElementById('viewInstitutionModal')).show();
}

function editInstitution(inst) {
    $('#editInstitutionId').val(inst.institution_pk);
    $('#editInstitutionName').val(inst.institution_name);
    $('#editInstitutionCode').val(inst.institution_code);
    $('#editIsActive').prop('checked', inst.is_active == 1);
    new bootstrap.Modal(document.getElementById('editInstitutionModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Are you sure you want to toggle the status of "' + name + '"?')) {
        $('#toggleInstitutionId').val(id);
        $('#toggleStatusForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../system/includes/footer.php'; ?>
