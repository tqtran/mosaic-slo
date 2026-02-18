<?php
declare(strict_types=1);

/**
 * Institution Administration
 * 
 * Manage institution settings and LTI consumer configuration.
 * Typically only one institution record exists.
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
                $code = trim($_POST['institution_code'] ?? '');
                $name = trim($_POST['institution_name'] ?? '');
                $ltiKey = trim($_POST['lti_consumer_key'] ?? '');
                $ltiSecret = trim($_POST['lti_consumer_secret'] ?? '');
                $ltiName = trim($_POST['lti_consumer_name'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if (empty($code)) {
                    $errors[] = 'Institution code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Institution code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institution WHERE institution_code = ?",
                        [$code],
                        's'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Institution code already exists';
                    }
                }
                if (empty($name)) {
                    $errors[] = 'Institution name is required';
                }
                
                // Validate LTI consumer key uniqueness if provided
                if (!empty($ltiKey)) {
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institution WHERE lti_consumer_key = ?",
                        [$ltiKey],
                        's'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'LTI consumer key already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}institution (institution_code, institution_name, lti_consumer_key, lti_consumer_secret, lti_consumer_name, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$code, $name, $ltiKey, $ltiSecret, $ltiName, $isActive],
                        'sssssi'
                    );
                    $successMessage = 'Institution added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['institution_id'] ?? 0);
                $code = trim($_POST['institution_code'] ?? '');
                $name = trim($_POST['institution_name'] ?? '');
                $ltiKey = trim($_POST['lti_consumer_key'] ?? '');
                $ltiSecret = trim($_POST['lti_consumer_secret'] ?? '');
                $ltiName = trim($_POST['lti_consumer_name'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid institution ID';
                }
                if (empty($code)) {
                    $errors[] = 'Institution code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Institution code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institution WHERE institution_code = ? AND institution_pk != ?",
                        [$code, $id],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Institution code already exists';
                    }
                }
                if (empty($name)) {
                    $errors[] = 'Institution name is required';
                }
                
                // Validate LTI consumer key uniqueness if provided
                if (!empty($ltiKey)) {
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institution WHERE lti_consumer_key = ? AND institution_pk != ?",
                        [$ltiKey, $id],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'LTI consumer key already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}institution 
                         SET institution_code = ?, institution_name = ?, lti_consumer_key = ?, lti_consumer_secret = ?, lti_consumer_name = ?, is_active = ?, updated_at = NOW()
                         WHERE institution_pk = ?",
                        [$code, $name, $ltiKey, $ltiSecret, $ltiName, $isActive, $id],
                        'sssssii'
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
                        "UPDATE {$dbPrefix}institution 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE institution_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Institution status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['institution_id'] ?? 0);
                if ($id > 0) {
                    // Check if institution has associated data
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}institutional_outcomes WHERE institution_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete institution: it has associated institutional outcomes. Please remove outcomes first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}institution WHERE institution_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Institution deleted successfully';
                    }
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
                                $code = trim($row[0]);
                                $name = trim($row[1]);
                                $ltiKey = isset($row[2]) ? trim($row[2]) : '';
                                $ltiSecret = isset($row[3]) ? trim($row[3]) : '';
                                $ltiName = isset($row[4]) ? trim($row[4]) : '';
                                $isActive = isset($row[5]) && strtolower(trim($row[5])) === 'active' ? 1 : 0;
                                
                                if (!empty($code) && !empty($name) && preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                                    // Check if exists
                                    $result = $db->query(
                                        "SELECT institution_pk FROM {$dbPrefix}institution WHERE institution_code = ?",
                                        [$code],
                                        's'
                                    );
                                    
                                    if ($result->rowCount() > 0) {
                                        // Update existing
                                        $existing = $result->fetch();
                                        $db->query(
                                            "UPDATE {$dbPrefix}institution 
                                             SET institution_name = ?, lti_consumer_key = ?, lti_consumer_secret = ?, lti_consumer_name = ?, is_active = ?, updated_at = NOW()
                                             WHERE institution_pk = ?",
                                            [$name, $ltiKey, $ltiSecret, $ltiName, $isActive, $existing['institution_pk']],
                                            'ssssii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}institution (institution_code, institution_name, lti_consumer_key, lti_consumer_secret, lti_consumer_name, is_active, created_at, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$code, $name, $ltiKey, $ltiSecret, $ltiName, $isActive],
                                            'sssssi'
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

// Calculate statistics (lightweight query for dashboard boxes)
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}institution
");
$stats = $statsResult->fetch();
$totalInstitutions = $stats['total'];
$activeInstitutions = $stats['active'];
$inactiveInstitutions = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Institution Management',
    'currentPage' => 'admin_institution',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Institution']
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
                    <li class="breadcrumb-item active">Institution</li>
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
                    <span class="info-box-icon bg-info"><i class="fas fa-university"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Institutions</span>
                        <span class="info-box-number"><?= $totalInstitutions ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Institutions</span>
                        <span class="info-box-number"><?= $activeInstitutions ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive Institutions</span>
                        <span class="info-box-number"><?= $inactiveInstitutions ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Institutions Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Institution Settings</h3>
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
                            <th>Institution Code</th>
                            <th>Institution Name</th>
                            <th>LTI Consumer Name</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Institution Code</th>
                            <th>Institution Name</th>
                            <th>LTI Consumer Name</th>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="institutionCode" class="form-label">Institution Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="institutionCode" name="institution_code" maxlength="50" required>
                            <small class="form-text text-muted">Unique identifier (letters, numbers, hyphens, underscores)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="institutionName" class="form-label">Institution Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="institutionName" name="institution_name" maxlength="255" required>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>LTI Consumer Configuration (Optional)</h6>
                    
                    <div class="mb-3">
                        <label for="ltiConsumerName" class="form-label">LTI Consumer Name</label>
                        <input type="text" class="form-control" id="ltiConsumerName" name="lti_consumer_name" maxlength="100" placeholder="e.g., Canvas LMS, Moodle">
                    </div>
                    <div class="mb-3">
                        <label for="ltiConsumerKey" class="form-label">LTI Consumer Key</label>
                        <input type="text" class="form-control" id="ltiConsumerKey" name="lti_consumer_key" maxlength="255">
                        <small class="form-text text-muted">OAuth consumer key for LTI 1.1 integration</small>
                    </div>
                    <div class="mb-3">
                        <label for="ltiConsumerSecret" class="form-label">LTI Consumer Secret</label>
                        <input type="password" class="form-control" id="ltiConsumerSecret" name="lti_consumer_secret" maxlength="255">
                        <small class="form-text text-muted">OAuth shared secret for LTI 1.1 integration</small>
                    </div>
                    
                    <hr>
                    
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editInstitutionCode" class="form-label">Institution Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editInstitutionCode" name="institution_code" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editInstitutionName" class="form-label">Institution Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editInstitutionName" name="institution_name" maxlength="255" required>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>LTI Consumer Configuration (Optional)</h6>
                    
                    <div class="mb-3">
                        <label for="editLtiConsumerName" class="form-label">LTI Consumer Name</label>
                        <input type="text" class="form-control" id="editLtiConsumerName" name="lti_consumer_name" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="editLtiConsumerKey" class="form-label">LTI Consumer Key</label>
                        <input type="text" class="form-control" id="editLtiConsumerKey" name="lti_consumer_key" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="editLtiConsumerSecret" class="form-label">LTI Consumer Secret</label>
                        <input type="password" class="form-control" id="editLtiConsumerSecret" name="lti_consumer_secret" maxlength="255" placeholder="Leave blank to keep current value">
                        <small class="form-text text-muted">Leave blank to keep existing secret</small>
                    </div>
                    
                    <hr>
                    
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
                        <strong>Institution Code:</strong>
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
                <h6>LTI Consumer Configuration</h6>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>LTI Consumer Name:</strong>
                        <p id="viewLtiConsumerName"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>LTI Consumer Key:</strong>
                        <p id="viewLtiConsumerKey"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>LTI Consumer Secret:</strong>
                        <p id="viewLtiConsumerSecret"></p>
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
                        <small class="form-text text-muted">CSV format: Institution Code, Institution Name, LTI Key, LTI Secret, LTI Consumer Name, Status (Active/Inactive)</small>
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
    <input type="hidden" name="institution_id" id="toggleInstitutionId">
</form>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="institution_id" id="deleteInstitutionId">
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
    $('#institutionsTable tfoot th').each(function() {
        var title = $(this).text();
        if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html(''); // No filter for Actions column
        }
    });
    
    var table = $('#institutionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/institution_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'institution_pk' },
            { data: 1, name: 'institution_code' },
            { data: 2, name: 'institution_name' },
            { data: 3, name: 'lti_consumer_name' },
            { data: 4, name: 'is_active' },
            { data: 5, name: 'created_at' },
            { data: 6, name: 'actions', orderable: false, searchable: false }
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

function viewInstitution(inst) {
    $('#viewInstitutionName').text(inst.institution_name);
    $('#viewInstitutionCode').text(inst.institution_code);
    $('#viewInstitutionStatus').html(inst.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewInstitutionId').text(inst.institution_pk);
    $('#viewLtiConsumerName').text(inst.lti_consumer_name || 'N/A');
    $('#viewLtiConsumerKey').text(inst.lti_consumer_key || 'N/A');
    $('#viewLtiConsumerSecret').text(inst.lti_consumer_secret ? '***hidden***' : 'N/A');
    $('#viewInstitutionCreated').text(inst.created_at);
    $('#viewInstitutionUpdated').text(inst.updated_at);
    new bootstrap.Modal(document.getElementById('viewInstitutionModal')).show();
}

function editInstitution(inst) {
    $('#editInstitutionId').val(inst.institution_pk);
    $('#editInstitutionCode').val(inst.institution_code);
    $('#editInstitutionName').val(inst.institution_name);
    $('#editLtiConsumerName').val(inst.lti_consumer_name);
    $('#editLtiConsumerKey').val(inst.lti_consumer_key);
    $('#editLtiConsumerSecret').val(''); // Don't show existing secret
    $('#editIsActive').prop('checked', inst.is_active == 1);
    new bootstrap.Modal(document.getElementById('editInstitutionModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Are you sure you want to toggle the status of "' + name + '"?')) {
        $('#toggleInstitutionId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteInstitution(id, name) {
    if (confirm('Are you sure you want to DELETE "' + name + '"? This action cannot be undone.')) {
        $('#deleteInstitutionId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
