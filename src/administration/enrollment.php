<?php
declare(strict_types=1);

/**
 * Enrollment Administration
 * 
 * Manage student enrollments by term and CRN.
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
                $termCode = trim($_POST['term_code'] ?? '');
                $crn = trim($_POST['crn'] ?? '');
                $studentCNum = trim($_POST['student_c_number'] ?? '');
                $enrollmentStatus = trim($_POST['enrollment_status'] ?? 'enrolled');
                $enrollmentDate = trim($_POST['enrollment_date'] ?? date('Y-m-d'));
                $dropDate = !empty($_POST['drop_date']) ? trim($_POST['drop_date']) : null;
                
                // Validation
                $errors = [];
                if (empty($termCode)) {
                    $errors[] = 'Term code is required';
                }
                if (empty($crn)) {
                    $errors[] = 'CRN is required';
                }
                if (empty($studentCNum)) {
                    $errors[] = 'Student C-number is required';
                }
                
                // Find or create student
                if (empty($errors)) {
                    $studentResult = $db->query(
                        "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ?",
                        [$studentCNum],
                        's'
                    );
                    
                    if ($studentResult->rowCount() === 0) {
                        // Auto-create student with minimal data
                        $db->query(
                            "INSERT INTO {$dbPrefix}students (student_id, created_at, updated_at) 
                             VALUES (?, NOW(), NOW())",
                            [$studentCNum],
                            's'
                        );
                        $studentFk = $db->lastInsertId();
                    } else {
                        $student = $studentResult->fetch();
                        $studentFk = $student['students_pk'];
                    }
                    
                    // Check if enrollment already exists
                    $checkResult = $db->query(
                        "SELECT e.enrollment_pk 
                         FROM {$dbPrefix}enrollment e
                         WHERE e.term_code = ? AND e.crn = ? AND e.student_fk = ?",
                        [$termCode, $crn, $studentFk],
                        'ssi'
                    );
                    
                    if ($checkResult->rowCount() > 0) {
                        $errors[] = 'Enrollment already exists for this student in this term/CRN';
                    } else {
                        // Insert enrollment
                        $db->query(
                            "INSERT INTO {$dbPrefix}enrollment (term_code, crn, student_fk, enrollment_status, enrollment_date, drop_date, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$termCode, $crn, $studentFk, $enrollmentStatus, $enrollmentDate, $dropDate],
                            'ssisss'
                        );
                        $successMessage = 'Enrollment added successfully';
                    }
                }
                
                if (!empty($errors)) {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['enrollment_id'] ?? 0);
                $enrollmentStatus = trim($_POST['enrollment_status'] ?? 'enrolled');
                $enrollmentDate = trim($_POST['enrollment_date'] ?? date('Y-m-d'));
                $dropDate = !empty($_POST['drop_date']) ? trim($_POST['drop_date']) : null;
                
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}enrollment 
                         SET enrollment_status = ?, enrollment_date = ?, drop_date = ?, updated_at = NOW()
                         WHERE enrollment_pk = ?",
                        [$enrollmentStatus, $enrollmentDate, $dropDate, $id],
                        'sssi'
                    );
                    $successMessage = 'Enrollment updated successfully';
                } else {
                    $errorMessage = 'Invalid enrollment ID';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['enrollment_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "DELETE FROM {$dbPrefix}enrollment WHERE enrollment_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Enrollment deleted successfully';
                }
                break;
                
            case 'import':
                if (isset($_FILES['enrollment_upload']) && $_FILES['enrollment_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['enrollment_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $headers = fgetcsv($handle); // Skip header row
                        $imported = 0;
                        $skipped = 0;
                        $errors = [];
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 3) {
                                $termCode = trim($row[0]);  // term
                                $crn = trim($row[1]);  // crn
                                $studentCNum = trim($row[2]);  // cnum
                                $firstName = isset($row[3]) ? trim($row[3]) : '';  // FN
                                $lastName = isset($row[4]) ? trim($row[4]) : '';  // LN
                                $enrollmentStatus = isset($row[5]) ? trim($row[5]) : '1';  // status (1,2,7)
                                $enrollmentDate = isset($row[6]) ? trim($row[6]) : date('Y-m-d');  // regdate
                                $dropDate = isset($row[7]) ? trim($row[7]) : null;  // drop_date
                                $lastUpdated = isset($row[8]) ? trim($row[8]) : null;  // updated
                                
                                if (empty($termCode) || empty($crn) || empty($studentCNum)) {
                                    $skipped++;
                                    continue;
                                }
                                
                                // Find or create student by student_id
                                $studentResult = $db->query(
                                    "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ?",
                                    [$studentCNum],
                                    's'
                                );
                                
                                $studentFk = null;
                                if ($studentResult->rowCount() === 0) {
                                    // Create student with name data from Banner
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}students (student_id, first_name, last_name, created_at, updated_at) 
                                         VALUES (?, ?, ?, NOW(), NOW())",
                                        [$studentCNum, $firstName, $lastName],
                                        'sss'
                                    );
                                    $studentFk = $db->lastInsertId();
                                } else {
                                    // Update student name if provided and currently null
                                    $student = $studentResult->fetch();
                                    $studentFk = $student['students_pk'];
                                    if (!empty($firstName) || !empty($lastName)) {
                                        $db->query(
                                            "UPDATE {$dbPrefix}students 
                                             SET first_name = COALESCE(NULLIF(first_name, ''), ?), 
                                                 last_name = COALESCE(NULLIF(last_name, ''), ?), 
                                                 updated_at = NOW() 
                                             WHERE students_pk = ?",
                                            [$firstName, $lastName, $studentFk],
                                            'ssi'
                                        );
                                    }
                                }
                                
                                // Check if enrollment exists
                                $enrollResult = $db->query(
                                    "SELECT enrollment_pk FROM {$dbPrefix}enrollment 
                                     WHERE term_code = ? AND crn = ? AND student_fk = ?",
                                    [$termCode, $crn, $studentFk],
                                    'ssi'
                                );
                                
                                if ($enrollResult->rowCount() > 0) {
                                    // Update existing
                                    $enroll = $enrollResult->fetch();
                                    $updateSql = "UPDATE {$dbPrefix}enrollment 
                                                  SET enrollment_status = ?, enrollment_date = ?, drop_date = ?, updated_at = ";
                                    $updateSql .= $lastUpdated ? "?" : "NOW()";
                                    $updateSql .= " WHERE enrollment_pk = ?";
                                    
                                    if ($lastUpdated) {
                                        $db->query($updateSql, [$enrollmentStatus, $enrollmentDate, $dropDate, $lastUpdated, $enroll['enrollment_pk']], 'ssssi');
                                    } else {
                                        $db->query($updateSql, [$enrollmentStatus, $enrollmentDate, $dropDate, $enroll['enrollment_pk']], 'sssi');
                                    }
                                } else {
                                    // Insert new
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}enrollment (term_code, crn, student_fk, enrollment_status, enrollment_date, drop_date, created_at, updated_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                        [$termCode, $crn, $studentFk, $enrollmentStatus, $enrollmentDate, $dropDate],
                                        'ssisss'
                                    );
                                }
                                $imported++;
                            }
                        }
                        
                        fclose($handle);
                        $message = "Import completed: {$imported} records imported/updated, {$skipped} skipped";
                        if (!empty($errors)) {
                            $message .= "<br><br><strong>Warnings:</strong><br>" . implode('<br>', array_unique($errors));
                        }
                        $successMessage = $message;
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

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN enrollment_status = 'enrolled' THEN 1 ELSE 0 END) as enrolled,
        SUM(CASE WHEN enrollment_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN enrollment_status = 'dropped' THEN 1 ELSE 0 END) as dropped
    FROM {$dbPrefix}enrollment
");
$stats = $statsResult->fetch();
$totalEnrollments = $stats['total'];
$enrolledCount = $stats['enrolled'];
$completedCount = $stats['completed'];
$droppedCount = $stats['dropped'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Enrollment Management',
    'currentPage' => 'admin_enrollment',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Enrollment']
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
                    <li class="breadcrumb-item active">Enrollment</li>
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
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Enrollments</span>
                        <span class="info-box-number"><?= $totalEnrollments ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Currently Enrolled</span>
                        <span class="info-box-number"><?= $enrolledCount ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-graduation-cap"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Completed</span>
                        <span class="info-box-number"><?= $completedCount ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-user-xmark"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Dropped</span>
                        <span class="info-box-number"><?= $droppedCount ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollment Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Student Enrollments</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
                        <i class="fas fa-plus"></i> Add Enrollment
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="enrollmentTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Term Code</th>
                            <th>CRN</th>
                            <th>Student C-Number</th>
                            <th>Status</th>
                            <th>Enrollment Date</th>
                            <th>Drop Date</th>
                            <th>Last Updated</th>
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
                    <tbody>
                        <!-- Data loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Enrollment Modal -->
<div class="modal fade" id="addEnrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Enrollment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="termCode" class="form-label">Term Code</label>
                            <input type="text" class="form-control" id="termCode" name="term_code" maxlength="20" required placeholder="e.g., FA2024">
                            <small class="form-text text-muted">Term identifier</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="crn" class="form-label">CRN</label>
                            <input type="text" class="form-control" id="crn" name="crn" maxlength="20" required placeholder="e.g., 10001">
                            <small class="form-text text-muted">Course Reference Number</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="studentCNumber" class="form-label">Student C-Number</label>
                        <input type="text" class="form-control" id="studentCNumber" name="student_c_number" maxlength="20" required placeholder="e.g., C00001001">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="enrollmentStatus" class="form-label">Status</label>
                            <select class="form-select" id="enrollmentStatus" name="enrollment_status" required>
                                <option value="enrolled">Enrolled</option>
                                <option value="completed">Completed</option>
                                <option value="dropped">Dropped</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="enrollmentDate" class="form-label">Enrollment Date</label>
                            <input type="date" class="form-control" id="enrollmentDate" name="enrollment_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="dropDate" class="form-label">Drop Date <small class="text-muted">(Optional)</small></label>
                            <input type="date" class="form-control" id="dropDate" name="drop_date">
                        </div>
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

<!-- Edit Enrollment Modal -->
<div class="modal fade" id="editEnrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Enrollment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="enrollment_id" id="editEnrollmentId">
                    <div class="alert alert-info">
                        <strong>Note:</strong> Term Code, CRN, and Student C-Number cannot be changed. Delete and recreate enrollment if needed.
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Term Code:</strong>
                            <p id="editTermCode"></p>
                        </div>
                        <div class="col-md-4">
                            <strong>CRN:</strong>
                            <p id="editCrn"></p>
                        </div>
                        <div class="col-md-4">
                            <strong>Student C-Number:</strong>
                            <p id="editStudentCNumber"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editEnrollmentStatus" class="form-label">Status</label>
                            <select class="form-select" id="editEnrollmentStatus" name="enrollment_status" required>
                                <option value="enrolled">Enrolled</option>
                                <option value="completed">Completed</option>
                                <option value="dropped">Dropped</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEnrollmentDate" class="form-label">Enrollment Date</label>
                            <input type="date" class="form-control" id="editEnrollmentDate" name="enrollment_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editDropDate" class="form-label">Drop Date <small class="text-muted">(Optional)</small></label>
                        <input type="date" class="form-control" id="editDropDate" name="drop_date">
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

<!-- View Enrollment Modal -->
<div class="modal fade" id="viewEnrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Enrollment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Term Code:</strong>
                        <p id="viewTermCode"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>CRN:</strong>
                        <p id="viewCrn"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Student C-Number:</strong>
                        <p id="viewStudentCNumber"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewEnrollmentStatus"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Enrollment Date:</strong>
                        <p id="viewEnrollmentDate"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Drop Date:</strong>
                        <p id="viewDropDate"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>ID:</strong>
                        <p id="viewEnrollmentId"></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewEnrollmentCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewEnrollmentUpdated"></p>
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
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Enrollments</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="enrollmentUpload" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="enrollmentUpload" name="enrollment_upload" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: Term_Code, CRN, Student_C_Number, Enrollment_Status, Enrollment_Date, Drop_Date, Last_Updated</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Auto-Creation:</strong> 
                        <ul class="mb-0 mt-2">
                            <li>Students will be auto-created if C-number doesn't exist</li>
                            <li>Course sections must already exist in the system</li>
                            <li>Existing enrollments will be updated</li>
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

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="enrollment_id" id="deleteEnrollmentId">
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
    $('#enrollmentTable thead tr:eq(1) th').each(function(i) {
        var title = $('#enrollmentTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html(''); // No filter for Actions column
        }
    });
    
    var table = $('#enrollmentTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/enrollment_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'enrollment_pk' },
            { data: 1, name: 'term_code' },
            { data: 2, name: 'crn' },
            { data: 3, name: 'student_id' },
            { data: 4, name: 'enrollment_status' },
            { data: 5, name: 'enrollment_date' },
            { data: 6, name: 'drop_date' },
            { data: 7, name: 'updated_at' },
            { data: 8, name: 'actions', orderable: false, searchable: false }
        ],
        order: [[7, 'desc']], // Sort by last updated
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

function viewEnrollment(enroll) {
    $('#viewTermCode').text(enroll.term_code);
    $('#viewCrn').text(enroll.crn);
    $('#viewStudentCNumber').text(enroll.student_id);
    $('#viewEnrollmentStatus').html('<span class="badge bg-' + getStatusClass(enroll.enrollment_status) + '">' + enroll.enrollment_status + '</span>');
    $('#viewEnrollmentDate').text(enroll.enrollment_date);
    $('#viewDropDate').text(enroll.drop_date || '-');
    $('#viewEnrollmentId').text(enroll.enrollment_pk);
    $('#viewEnrollmentCreated').text(enroll.created_at);
    $('#viewEnrollmentUpdated').text(enroll.updated_at);
    new bootstrap.Modal(document.getElementById('viewEnrollmentModal')).show();
}

function editEnrollment(enroll) {
    $('#editEnrollmentId').val(enroll.enrollment_pk);
    $('#editTermCode').text(enroll.term_code);
    $('#editCrn').text(enroll.crn);
    $('#editStudentCNumber').text(enroll.student_id);
    $('#editEnrollmentStatus').val(enroll.enrollment_status);
    $('#editEnrollmentDate').val(enroll.enrollment_date);
    $('#editDropDate').val(enroll.drop_date || '');
    new bootstrap.Modal(document.getElementById('editEnrollmentModal')).show();
}

function deleteEnrollment(id, cnum, crn) {
    if (confirm('Are you sure you want to DELETE enrollment for student ' + cnum + ' in CRN ' + crn + '? This action cannot be undone.')) {
        $('#deleteEnrollmentId').val(id);
        $('#deleteForm').submit();
    }
}

function getStatusClass(status) {
    switch(status.toLowerCase()) {
        case 'enrolled': return 'success';
        case 'completed': return 'primary';
        case 'dropped': return 'warning';
        case 'withdrawn': return 'danger';
        default: return 'secondary';
    }
}
</script>
