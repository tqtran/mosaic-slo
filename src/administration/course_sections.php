<?php
declare(strict_types=1);

/**
 * Course Sections (CRN) Administration
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $crn = trim($_POST['crn'] ?? '');
                $sectionNumber = trim($_POST['section_number'] ?? '');
                $instructorFk = !empty($_POST['instructor_fk']) ? (int)$_POST['instructor_fk'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                }
                if (empty($crn)) {
                    $errors[] = 'CRN is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}course_sections (course_fk, term_fk, crn, section_number, instructor_fk, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$courseFk, $termFk, $crn, $sectionNumber, $instructorFk, $isActive],
                        'iissii'
                    );
                    $successMessage = 'Course section added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['section_id'] ?? 0);
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $crn = trim($_POST['crn'] ?? '');
                $sectionNumber = trim($_POST['section_number'] ?? '');
                $instructorFk = !empty($_POST['instructor_fk']) ? (int)$_POST['instructor_fk'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid section ID';
                }
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if ($termFk <= 0) {
                    $errors[] = 'Term is required';
                }
                if (empty($crn)) {
                    $errors[] = 'CRN is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}course_sections 
                         SET course_fk = ?, term_fk = ?, crn = ?, section_number = ?, instructor_fk = ?, is_active = ?, updated_at = NOW()
                         WHERE course_sections_pk = ?",
                        [$courseFk, $termFk, $crn, $sectionNumber, $instructorFk, $isActive, $id],
                        'iissiii'
                    );
                    $successMessage = 'Course section updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['section_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}course_sections SET is_active = NOT is_active, updated_at = NOW() WHERE course_sections_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Section status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['section_id'] ?? 0);
                if ($id > 0) {
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}assessments WHERE course_section_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete section: it has associated assessments.';
                    } else {
                        $db->query("DELETE FROM {$dbPrefix}course_sections WHERE course_sections_pk = ?", [$id], 'i');
                        $successMessage = 'Course section deleted successfully';
                    }
                }
                break;
                
            case 'import':
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Please select a valid CSV file';
                    break;
                }
                
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if ($handle === false) {
                    $errorMessage = 'Failed to open CSV file';
                    break;
                }
                
                // Read header
                $headers = fgetcsv($handle);
                if ($headers === false) {
                    $errorMessage = 'Invalid CSV file format';
                    fclose($handle);
                    break;
                }
                
                // Strip UTF-8 BOM if present
                if (!empty($headers[0])) {
                    $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
                }
                
                // Expected columns: course_number,term_code,crn,section_number,instructor_user_id,is_active (or instructor_email)
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue; // Need at least course_number, term_code, and crn
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $courseNumber = trim($data['course_number'] ?? '');
                    $termCode = trim($data['term_code'] ?? '');
                    $crn = trim($data['crn'] ?? '');
                    $sectionNumber = trim($data['section_number'] ?? '');
                    $instructorUserId = trim($data['instructor_user_id'] ?? '');
                    $instructorEmail = trim($data['instructor_email'] ?? '');
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($courseNumber) || empty($termCode) || empty($crn)) {
                        $errors[] = "Skipped row: missing required fields (course_number, term_code, or crn)";
                        continue;
                    }
                    
                    // Lookup course_fk by course_number
                    $result = $db->query(
                        "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ?",
                        [$courseNumber],
                        's'
                    );
                    $courseRow = $result->fetch();
                    if (!$courseRow) {
                        $errors[] = "Skipped row: course number '$courseNumber' not found";
                        continue;
                    }
                    $courseFk = (int)$courseRow['courses_pk'];
                    
                    // Lookup term_fk by term_code
                    $result = $db->query(
                        "SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ?",
                        [$termCode],
                        's'
                    );
                    $termRow = $result->fetch();
                    if (!$termRow) {
                        $errors[] = "Skipped row: term code '$termCode' not found";
                        continue;
                    }
                    $termFk = (int)$termRow['terms_pk'];
                    
                    // Lookup instructor_fk by user_id or email
                    $instructorFk = null;
                    if (!empty($instructorUserId)) {
                        $result = $db->query(
                            "SELECT users_pk FROM {$dbPrefix}users WHERE user_id = ?",
                            [$instructorUserId],
                            's'
                        );
                        $userRow = $result->fetch();
                        if ($userRow) {
                            $instructorFk = (int)$userRow['users_pk'];
                        } else {
                            $errors[] = "Warning: user_id '$instructorUserId' not found for CRN '$crn' (instructor field will be NULL)";
                        }
                    } elseif (!empty($instructorEmail)) {
                        $result = $db->query(
                            "SELECT users_pk FROM {$dbPrefix}users WHERE email = ?",
                            [$instructorEmail],
                            's'
                        );
                        $userRow = $result->fetch();
                        if ($userRow) {
                            $instructorFk = (int)$userRow['users_pk'];
                        } else {
                            $errors[] = "Warning: email '$instructorEmail' not found for CRN '$crn' (instructor field will be NULL)";
                        }
                    }
                    
                    // Check if section exists (based on unique crn)
                    $result = $db->query(
                        "SELECT course_sections_pk FROM {$dbPrefix}course_sections WHERE crn = ?",
                        [$crn],
                        's'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $db->query(
                            "UPDATE {$dbPrefix}course_sections 
                             SET course_fk = ?, term_fk = ?, section_number = ?, instructor_fk = ?, is_active = ?, updated_at = NOW() 
                             WHERE course_sections_pk = ?",
                            [$courseFk, $termFk, $sectionNumber, $instructorFk, $isActive, $existing['course_sections_pk']],
                            'iisiii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}course_sections (course_fk, term_fk, crn, section_number, instructor_fk, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$courseFk, $termFk, $crn, $sectionNumber, $instructorFk, $isActive],
                            'iissii'
                        );
                        $imported++;
                    }
                }
                
                fclose($handle);
                
                if ($imported > 0 || $updated > 0) {
                    $successMessage = "Import complete: $imported new, $updated updated";
                    if (!empty($errors)) {
                        $successMessage .= '<br>Warnings: ' . implode('<br>', array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $successMessage .= '<br>... and ' . (count($errors) - 5) . ' more';
                        }
                    }
                } else {
                    $errorMessage = 'No records imported. ' . implode('<br>', $errors);
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
    FROM {$dbPrefix}course_sections
");
$stats = $statsResult->fetch();
$totalSections = $stats['total'];
$activeSections = $stats['active'];

// Get courses for dropdown
$coursesResult = $db->query("SELECT courses_pk, course_name, course_number FROM {$dbPrefix}courses WHERE is_active = 1 ORDER BY course_name");
$courses = $coursesResult->fetchAll();

// Get terms for dropdown
$termsResult = $db->query("SELECT terms_pk, term_code, term_name FROM {$dbPrefix}terms WHERE is_active = 1 ORDER BY term_code DESC");
$terms = $termsResult->fetchAll();

// Get users/instructors for dropdown
$usersResult = $db->query("SELECT users_pk, user_id, first_name, last_name, email FROM {$dbPrefix}users WHERE is_active = 1 ORDER BY last_name, first_name");
$users = $usersResult->fetchAll();

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Course Sections (CRN)',
    'currentPage' => 'admin_course_sections',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Course Sections']
    ]
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active">Course Sections</li>
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
        
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-chalkboard"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Sections</span>
                        <span class="info-box-number"><?= $totalSections ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Sections</span>
                        <span class="info-box-number"><?= $activeSections ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Course Sections</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="fas fa-plus"></i> Add Course Section
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="sectionsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>CRN</th>
                            <th>Course</th>
                            <th>Term</th>
                            <th>Section</th>
                            <th>Instructor</th>
                            <th>Status</th>
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
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Course Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="courseFk" class="form-label">Course</label>
                        <select class="form-select" id="courseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="termFk" class="form-label">Term</label>
                        <select class="form-select" id="termFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>">
                                    <?= htmlspecialchars($term['term_name']) ?> (<?= htmlspecialchars($term['term_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="crn" class="form-label">CRN</label>
                            <input type="text" class="form-control" id="crn" name="crn" maxlength="20" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sectionNumber" class="form-label">Section Number</label>
                            <input type="text" class="form-control" id="sectionNumber" name="section_number" maxlength="10">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instructorFk" class="form-label">Instructor</label>
                        <select class="form-select" id="instructorFk" name="instructor_fk">
                            <option value="">Select Instructor (Optional)</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['users_pk'] ?>">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                    <?php if (!empty($user['email'])): ?>
                                        (<?= htmlspecialchars($user['email']) ?>)
                                    <?php endif; ?>
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

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Course Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="section_id" id="editSectionId">
                    
                    <div class="mb-3">
                        <label for="editCourseFk" class="form-label">Course</label>
                        <select class="form-select" id="editCourseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTermFk" class="form-label">Term</label>
                        <select class="form-select" id="editTermFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>">
                                    <?= htmlspecialchars($term['term_name']) ?> (<?= htmlspecialchars($term['term_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editCrn" class="form-label">CRN</label>
                            <input type="text" class="form-control" id="editCrn" name="crn" maxlength="20" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSectionNumber" class="form-label">Section Number</label>
                            <input type="text" class="form-control" id="editSectionNumber" name="section_number" maxlength="10">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editInstructorFk" class="form-label">Instructor</label>
                        <select class="form-select" id="editInstructorFk" name="instructor_fk">
                            <option value="">Select Instructor (Optional)</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['users_pk'] ?>">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                    <?php if (!empty($user['email'])): ?>
                                        (<?= htmlspecialchars($user['email']) ?>)
                                    <?php endif; ?>
                                </option>
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

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Import Course Sections from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="csvFile" name="csv_file" accept=".csv" required>
                        <small class="form-text text-muted">
                            CSV format: course_number, term_code, crn, section_number, instructor_user_id, is_active<br>
                            Alternative: Use instructor_email instead of instructor_user_id
                        </small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Import Notes:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Course and term must already exist in the system</li>
                            <li>Instructor lookup by user_id or email (leave blank if unknown)</li>
                            <li>Existing sections (by CRN) will be updated</li>
                            <li>New sections will be created</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="section_id" id="toggleSectionId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="section_id" id="deleteSectionId">
</form>

<?php $theme->showFooter($context); ?>

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
var courses = <?= json_encode(array_map(function($c) { 
    return ['name' => $c['course_name'], 'number' => $c['course_number']]; 
}, $courses)) ?>;
var terms = <?= json_encode(array_map(function($t) { 
    return ['name' => $t['term_name'], 'code' => $t['term_code']]; 
}, $terms)) ?>;
var instructors = <?= json_encode(array_map(function($u) { 
    return ['name' => $u['first_name'] . ' ' . $u['last_name']]; 
}, $users ?? [])) ?>;

$(document).ready(function() {
    $('#sectionsTable thead tr:eq(1) th').each(function(i) {
        var title = $('#sectionsTable thead tr:eq(0) th:eq(' + i + ')').text();
        
        // Course column (index 2)
        if (title === 'Course') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Courses</option></select>')
                .appendTo($(this).empty());
            courses.forEach(function(course) {
                select.append('<option value="' + course.name + '">' + course.name + '</option>');
            });
        }
        // Term column (index 3)
        else if (title === 'Term') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Terms</option></select>')
                .appendTo($(this).empty());
            terms.forEach(function(term) {
                select.append('<option value="' + term.name + '">' + term.name + '</option>');
            });
        }
        // Instructor column (index 5)
        else if (title === 'Instructor') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Instructors</option></select>')
                .appendTo($(this).empty());
            instructors.forEach(function(instructor) {
                if (instructor.name.trim() !== '') {
                    select.append('<option value="' + instructor.name + '">' + instructor.name + '</option>');
                }
            });
        }
        else if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#sectionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/course_sections_data.php',
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'course_sections_pk' },
            { data: 1, name: 'crn' },
            { data: 2, name: 'course_name' },
            { data: 3, name: 'term_name' },
            { data: 4, name: 'section_number' },
            { data: 5, name: 'instructor_name' },
            { data: 6, name: 'is_active' },
            { data: 7, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
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

function editSection(section) {
    $('#editSectionId').val(section.course_sections_pk);
    $('#editCourseFk').val(section.course_fk);
    $('#editTermFk').val(section.term_fk);
    $('#editCrn').val(section.crn);
    $('#editSectionNumber').val(section.section_number);
    $('#editInstructorFk').val(section.instructor_fk || '');
    $('#editIsActive').prop('checked', section.is_active == 1);
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function toggleStatus(id, crn) {
    if (confirm('Are you sure you want to toggle the status of CRN "' + crn + '"?')) {
        $('#toggleSectionId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteSection(id, crn) {
    if (confirm('Are you sure you want to DELETE CRN "' + crn + '"? This action cannot be undone.')) {
        $('#deleteSectionId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
