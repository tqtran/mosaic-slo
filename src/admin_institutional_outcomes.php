<?php
declare(strict_types=1);

/**
 * Institutional Outcomes Administration
 * 
 * Manage top-level institutional learning outcomes.
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
require_once __DIR__ . '/Core/Config.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Path.php';

// Check if configured
if (!file_exists(__DIR__ . '/config/config.yaml')) {
    \Mosaic\Core\Path::redirect('setup/');
}

// Load configuration
$config = \Mosaic\Core\Config::getInstance(__DIR__ . '/config/config.yaml');
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
                $institutionFk = (int)($_POST['institution_fk'] ?? 0);
                $code = trim($_POST['code'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($institutionFk <= 0) {
                    $errors[] = 'Institution is required';
                }
                if (empty($code)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM institutional_outcomes WHERE code = ?",
                        [$code],
                        's'
                    );
                    $row = $result->fetch_assoc();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists';
                    }
                }
                if (empty($description)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    // Auto-assign sequence if not provided
                    if ($sequenceNum <= 0) {
                        $result = $db->query(
                            "SELECT MAX(sequence_num) as max_seq FROM institutional_outcomes WHERE institution_fk = ?",
                            [$institutionFk],
                            'i'
                        );
                        $row = $result->fetch_assoc();
                        $sequenceNum = ((int)($row['max_seq'] ?? 0)) + 1;
                    }
                    
                    $db->query(
                        "INSERT INTO institutional_outcomes 
                         (institution_fk, code, description, sequence_num, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$institutionFk, $code, $description, $sequenceNum, $isActive],
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
                $code = trim($_POST['code'] ?? '');
                $description = trim($_POST['description'] ?? '');
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
                if (empty($code)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, and underscores';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM institutional_outcomes 
                         WHERE code = ? AND institutional_outcomes_pk != ?",
                        [$code, $id],
                        'si'
                    );
                    $row = $result->fetch_assoc();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists';
                    }
                }
                if (empty($description)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE institutional_outcomes 
                         SET institution_fk = ?, code = ?, description = ?, 
                             sequence_num = ?, is_active = ?, updated_at = NOW()
                         WHERE institutional_outcomes_pk = ?",
                        [$institutionFk, $code, $description, $sequenceNum, $isActive, $id],
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
                        "UPDATE institutional_outcomes 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE institutional_outcomes_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Outcome status updated';
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
                                $code = trim($row[0]);
                                $description = trim($row[1]);
                                $institutionCode = trim($row[2]);
                                $sequenceNum = isset($row[3]) ? (int)trim($row[3]) : 0;
                                $isActive = isset($row[4]) && strtolower(trim($row[4])) === 'active' ? 1 : 0;
                                
                                if (!empty($code) && !empty($description) && !empty($institutionCode) && preg_match('/^[A-Z0-9_-]+$/i', $code)) {
                                    // Find institution
                                    $result = $db->query(
                                        "SELECT institution_pk FROM institution WHERE institution_code = ?",
                                        [$institutionCode],
                                        's'
                                    );
                                    
                                    if ($result->num_rows > 0) {
                                        $inst = $result->fetch_assoc();
                                        $institutionFk = $inst['institution_pk'];
                                        
                                        // Check if outcome exists
                                        $result = $db->query(
                                            "SELECT institutional_outcomes_pk FROM institutional_outcomes WHERE code = ?",
                                            [$code],
                                            's'
                                        );
                                        
                                        if ($result->num_rows > 0) {
                                            // Update existing
                                            $existing = $result->fetch_assoc();
                                            $db->query(
                                                "UPDATE institutional_outcomes 
                                                 SET institution_fk = ?, description = ?, sequence_num = ?, 
                                                     is_active = ?, updated_at = NOW()
                                                 WHERE institutional_outcomes_pk = ?",
                                                [$institutionFk, $description, $sequenceNum, $isActive, $existing['institutional_outcomes_pk']],
                                                'isiii'
                                            );
                                        } else {
                                            // Auto-assign sequence if not provided
                                            if ($sequenceNum <= 0) {
                                                $result = $db->query(
                                                    "SELECT MAX(sequence_num) as max_seq FROM institutional_outcomes WHERE institution_fk = ?",
                                                    [$institutionFk],
                                                    'i'
                                                );
                                                $row = $result->fetch_assoc();
                                                $sequenceNum = ((int)($row['max_seq'] ?? 0)) + 1;
                                            }
                                            
                                            // Insert new
                                            $db->query(
                                                "INSERT INTO institutional_outcomes 
                                                 (institution_fk, code, description, sequence_num, is_active, created_at, updated_at) 
                                                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                                [$institutionFk, $code, $description, $sequenceNum, $isActive],
                                                'issii'
                                            );
                                        }
                                        $imported++;
                                    } else {
                                        $skipped++;
                                    }
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

// Fetch institutions for dropdowns
$result = $db->query("SELECT * FROM institution WHERE is_active = 1 ORDER BY institution_name ASC");
$institutions = $result->fetch_all(MYSQLI_ASSOC);

// Fetch all institutional outcomes with institution details
$result = $db->query(
    "SELECT io.*, i.institution_name, i.institution_code
     FROM institutional_outcomes io
     INNER JOIN institution i ON io.institution_fk = i.institution_pk
     ORDER BY io.sequence_num ASC, io.code ASC"
);
$outcomes = $result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalOutcomes = count($outcomes);
$activeOutcomes = count(array_filter($outcomes, fn($o) => $o['is_active']));
$inactiveOutcomes = $totalOutcomes - $activeOutcomes;

// Count program outcomes mapped
foreach ($outcomes as &$outcome) {
    $result = $db->query(
        "SELECT COUNT(*) as count FROM program_outcomes 
         WHERE institutional_outcomes_fk = ? AND is_active = 1",
        [$outcome['institutional_outcomes_pk']],
        'i'
    );
    $row = $result->fetch_assoc();
    $outcome['program_outcome_count'] = $row['count'];
}
unset($outcome);

$currentPage = 'admin_institutional_outcomes';
$pageTitle = 'Institutional Outcomes - ' . SITE_NAME;
$bodyClass = 'hold-transition sidebar-mini layout-fixed';
require_once __DIR__ . '/includes/header.php';
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
            <li class="nav-item d-none d-sm-inline-block">
                <a class="nav-link" href="<?= BASE_URL ?>admin_institution.php"><i class="fas fa-university"></i> Institutions</a>
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

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-graduation-cap"></i> Institutional Outcomes</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin_institution.php">Institutions</a></li>
                            <li class="breadcrumb-item active">Institutional Outcomes</li>
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
                                <h3><?= $totalOutcomes ?></h3>
                                <p>Total Outcomes</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= $activeOutcomes ?></h3>
                                <p>Active Outcomes</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= $inactiveOutcomes ?></h3>
                                <p>Inactive Outcomes</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-ban"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outcomes Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-table"></i> Institutional Outcomes</h3>
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
                                    <th>Sequence</th>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Institution</th>
                                    <th>Program Outcomes</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outcomes as $outcome): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($outcome['sequence_num']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($outcome['code']) ?></span></td>
                                        <td><?= htmlspecialchars($outcome['description']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($outcome['institution_code']) ?></span>
                                            <?= htmlspecialchars($outcome['institution_name']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $outcome['program_outcome_count'] ?> mapped</span>
                                        </td>
                                        <td>
                                            <?php if ($outcome['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" title="View" onclick="viewOutcome(<?= htmlspecialchars(json_encode($outcome)) ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" title="Edit" onclick="editOutcome(<?= htmlspecialchars(json_encode($outcome)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-<?= $outcome['is_active'] ? 'warning' : 'success' ?>" title="Toggle Status" onclick="toggleStatus(<?= $outcome['institutional_outcomes_pk'] ?>, '<?= htmlspecialchars($outcome['code']) ?>')">
                                                <i class="fas fa-<?= $outcome['is_active'] ? 'ban' : 'check' ?>"></i>
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
                            <option value="">Select Institution...</option>
                            <?php foreach ($institutions as $inst): ?>
                                <option value="<?= $inst['institution_pk'] ?>">
                                    <?= htmlspecialchars($inst['institution_name']) ?> (<?= htmlspecialchars($inst['institution_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label">Outcome Code</label>
                        <input type="text" class="form-control" id="code" name="code" maxlength="50" required>
                        <small class="form-text text-muted">Unique identifier (e.g., IO1, IO2)</small>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="sequenceNum" class="form-label">Sequence Number</label>
                        <input type="number" class="form-control" id="sequenceNum" name="sequence_num" min="0" value="0">
                        <small class="form-text text-muted">Display order (0 = auto-assign next)</small>
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
                            <?php foreach ($institutions as $inst): ?>
                                <option value="<?= $inst['institution_pk'] ?>">
                                    <?= htmlspecialchars($inst['institution_name']) ?> (<?= htmlspecialchars($inst['institution_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editCode" class="form-label">Outcome Code</label>
                        <input type="text" class="form-control" id="editCode" name="code" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editSequenceNum" class="form-label">Sequence Number</label>
                        <input type="number" class="form-control" id="editSequenceNum" name="sequence_num" min="0" required>
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
                <h5 class="modal-title"><i class="fas fa-eye"></i> Institutional Outcome Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Code:</strong>
                        <p id="viewCode"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Sequence:</strong>
                        <p id="viewSequence"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <strong>Description:</strong>
                        <p id="viewDescription"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Institution:</strong>
                        <p id="viewInstitution"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewStatus"></p>
                    </div>
                </div>
                <hr>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Program Outcomes Mapped:</strong>
                        <p id="viewMappedCount"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>ID:</strong>
                        <p id="viewId"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewUpdated"></p>
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
                        <small class="form-text text-muted">
                            CSV format: Code, Description, Institution Code, Sequence (optional), Status (optional)
                        </small>
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
    <input type="hidden" name="outcome_id" id="toggleOutcomeId">
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
    $('#outcomesTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 25,
        order: [[0, "asc"]],
        dom: 'Blfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print', 'colvis'
        ]
    });
});

function viewOutcome(outcome) {
    $('#viewCode').text(outcome.code);
    $('#viewDescription').text(outcome.description);
    $('#viewSequence').text(outcome.sequence_num);
    $('#viewInstitution').html('<span class="badge bg-info">' + outcome.institution_code + '</span> ' + outcome.institution_name);
    $('#viewStatus').html(outcome.is_active == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewMappedCount').text(outcome.program_outcome_count + ' program outcomes');
    $('#viewId').text(outcome.institutional_outcomes_pk);
    $('#viewCreated').text(outcome.created_at);
    $('#viewUpdated').text(outcome.updated_at);
    new bootstrap.Modal(document.getElementById('viewOutcomeModal')).show();
}

function editOutcome(outcome) {
    $('#editOutcomeId').val(outcome.institutional_outcomes_pk);
    $('#editInstitutionFk').val(outcome.institution_fk);
    $('#editCode').val(outcome.code);
    $('#editDescription').val(outcome.description);
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
