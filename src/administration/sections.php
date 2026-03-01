<?php
declare(strict_types=1);

/**
 * Section Administration
 * 
 * Manage course sections.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

// Get selected term (from GET/session, or default to latest)
$selectedTermFk = getSelectedTermFk();

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
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $sectionId = trim($_POST['section_id'] ?? '');
                $crn = trim($_POST['crn'] ?? '');
                $instructorName = trim($_POST['instructor_name'] ?? '');
                $maxEnrollment = !empty($_POST['max_enrollment']) ? (int)$_POST['max_enrollment'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                } else {
                    // Validate course exists
                    $courseCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}courses WHERE courses_pk = ? AND is_active = 1",
                        [$courseFk],
                        'i'
                    );
                    $courseRow = $courseCheck->fetch();
                    if ($courseRow['count'] == 0) {
                        $errors[] = 'Invalid course selected';
                    }
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
                if (empty($sectionId)) {
                    $errors[] = 'Section ID is required';
                } else {
                    // Check uniqueness (course + term + section_id must be unique)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}sections WHERE course_fk = ? AND term_fk = ? AND section_id = ?",
                        [$courseFk, $termFk, $sectionId],
                        'iis'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Section ID already exists for this course and term';
                    }
                }
                
                if (empty($errors)) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "INSERT INTO {$dbPrefix}sections (course_fk, term_fk, section_id, crn, instructor_name, max_enrollment, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                        [$courseFk, $termFk, $sectionId, $crn, $instructorName, $maxEnrollment, $isActive, $userId, $userId],
                        'iisssiiii'
                    );
                    $successMessage = 'Section added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['section_id'] ?? 0);
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $sectionId = trim($_POST['section_id_value'] ?? '');
                $crn = trim($_POST['crn'] ?? '');
                $instructorName = trim($_POST['instructor_name'] ?? '');
                $maxEnrollment = !empty($_POST['max_enrollment']) ? (int)$_POST['max_enrollment'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid section ID';
                }
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                } else {
                    // Validate course exists
                    $courseCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}courses WHERE courses_pk = ? AND is_active = 1",
                        [$courseFk],
                        'i'
                    );
                    $courseRow = $courseCheck->fetch();
                    if ($courseRow['count'] == 0) {
                        $errors[] = 'Invalid course selected';
                    }
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
                if (empty($sectionId)) {
                    $errors[] = 'Section ID is required';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}sections WHERE course_fk = ? AND term_fk = ? AND section_id = ? AND sections_pk != ?",
                        [$courseFk, $termFk, $sectionId, $id],
                        'iisi'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Section ID already exists for this course and term';
                    }
                }
                
                if (empty($errors)) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}sections 
                         SET course_fk = ?, term_fk = ?, section_id = ?, crn = ?, instructor_name = ?, max_enrollment = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                         WHERE sections_pk = ?",
                        [$courseFk, $termFk, $sectionId, $crn, $instructorName, $maxEnrollment, $isActive, $userId, $id],
                        'iisssiiii'
                    );
                    $successMessage = 'Section updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['section_id'] ?? 0);
                if ($id > 0) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}sections 
                         SET is_active = NOT is_active, updated_at = NOW(), updated_by_fk = ?
                         WHERE sections_pk = ?",
                        [$userId, $id],
                        'ii'
                    );
                    $successMessage = 'Section status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['section_id'] ?? 0);
                if ($id > 0) {
                    // Check if section has associated enrollments
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}enrollment WHERE crn IN (SELECT crn FROM {$dbPrefix}sections WHERE sections_pk = ?)",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete section: it has associated enrollments. Please remove enrollments first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}sections WHERE sections_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Section deleted successfully';
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
                
                // Expected columns: crn, course_number, section_id, term_code, instructor_name, max_enrollment, is_active
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue; // Need at least crn, course_number, section_id
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $crn = trim($data['crn'] ?? '');
                    $courseNumber = trim($data['course_number'] ?? '');
                    $sectionId = trim($data['section_id'] ?? '');
                    $termCode = trim($data['term_code'] ?? '');
                    $instructorName = trim($data['instructor_name'] ?? '');
                    $maxEnrollment = !empty($data['max_enrollment']) ? (int)$data['max_enrollment'] : null;
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($sectionId) || empty($courseNumber)) {
                        $errors[] = "Skipped row: missing required fields (section_id or course_number)";
                        continue;
                    }
                    
                    // Lookup course by number
                    $courseFk = null;
                    if (!empty($courseNumber)) {
                        $courseLookup = $db->query(
                            "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ? AND is_active = 1",
                            [$courseNumber],
                            's'
                        );
                        if ($courseLookup->rowCount() > 0) {
                            $courseRow = $courseLookup->fetch();
                            $courseFk = $courseRow['courses_pk'];
                        }
                    }
                    
                    if ($courseFk === null) {
                        $errors[] = "Skipped row for {$courseNumber}: course not found";
                        continue;
                    }
                    
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
                    
                    if ($termFk === null) {
                        $errors[] = "Skipped row for {$courseNumber}/{$sectionId}: invalid or missing term code";
                        continue;
                    }
                    
                    // Check if section exists (based on unique key: course_fk + term_fk + section_id)
                    $result = $db->query(
                        "SELECT sections_pk FROM {$dbPrefix}sections WHERE course_fk = ? AND term_fk = ? AND section_id = ?",
                        [$courseFk, $termFk, $sectionId],
                        'iis'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $userId = $_SESSION['user_id'] ?? null;
                        $db->query(
                            "UPDATE {$dbPrefix}sections 
                             SET crn = ?, instructor_name = ?, max_enrollment = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ? 
                             WHERE sections_pk = ?",
                            [$crn, $instructorName, $maxEnrollment, $isActive, $userId, $existing['sections_pk']],
                            'ssiiii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $userId = $_SESSION['user_id'] ?? null;
                        $db->query(
                            "INSERT INTO {$dbPrefix}sections (course_fk, term_fk, section_id, crn, instructor_name, max_enrollment, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                            [$courseFk, $termFk, $sectionId, $crn, $instructorName, $maxEnrollment, $isActive, $userId, $userId],
                            'iisssiiii'
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

// Fetch terms for dropdown (sorted ascending)
$termsResult = $db->query("
    SELECT terms_pk, term_code, term_name, academic_year
    FROM {$dbPrefix}terms
    WHERE is_active = 1
    ORDER BY term_code ASC
");
$terms = $termsResult->fetchAll();

// Fetch courses for dropdown
$coursesResult = $db->query("
    SELECT courses_pk, course_number, course_name
    FROM {$dbPrefix}courses
    WHERE is_active = 1
    ORDER BY course_number ASC
");
$courses = $coursesResult->fetchAll();

// Get selected term (default to latest/first)
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
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}sections
    {$termFilter}
");
$stats = $statsResult->fetch();
$totalSections = $stats['total'];
$activeSections = $stats['active'];
$inactiveSections = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Section Management';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_sections',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Sections']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
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
                    <li class="breadcrumb-item active">Sections</li>
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

        <!-- Sections Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chalkboard-teacher"></i> Course Sections</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="fas fa-plus"></i> Add Section
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="courseFilter" class="form-label">Course:</label>
                        <select id="courseFilter" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_number'] . ' - ' . $course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="statusFilter" class="form-label">Status:</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <table id="sectionsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>CRN</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Instructor</th>
                            <th>Max Enrollment</th>
                            <th>Status</th>
                            <th>Actions</th>
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

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="courseFk" class="form-label">Course</label>
                            <select class="form-select" id="courseFk" name="course_fk" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['courses_pk'] ?>">
                                        <?= htmlspecialchars($course['course_number'] . ' - ' . $course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="termFk" class="form-label">Term</label>
                            <select class="form-select" id="termFk" name="term_fk" required>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sectionId" class="form-label">Section ID</label>
                            <input type="text" class="form-control" id="sectionId" name="section_id" maxlength="20" required>
                            <small class="form-text text-muted">e.g., 01, 02, A, B</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="crn" class="form-label">CRN</label>
                            <input type="text" class="form-control" id="crn" name="crn" maxlength="20">
                            <small class="form-text text-muted">Course Reference Number</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="instructorName" class="form-label">Instructor Name</label>
                            <input type="text" class="form-control" id="instructorName" name="instructor_name" maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="maxEnrollment" class="form-label">Max Enrollment</label>
                            <input type="number" class="form-control" id="maxEnrollment" name="max_enrollment" min="1">
                        </div>
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
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="section_id" id="editSectionId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editCourseFk" class="form-label">Course</label>
                            <select class="form-select" id="editCourseFk" name="course_fk" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['courses_pk'] ?>">
                                        <?= htmlspecialchars($course['course_number'] . ' - ' . $course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editTermFk" class="form-label">Term</label>
                            <select class="form-select" id="editTermFk" name="term_fk" required>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= $term['terms_pk'] ?>">
                                        <?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editSectionIdValue" class="form-label">Section ID</label>
                            <input type="text" class="form-control" id="editSectionIdValue" name="section_id_value" maxlength="20" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editCrn" class="form-label">CRN</label>
                            <input type="text" class="form-control" id="editCrn" name="crn" maxlength="20">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editInstructorName" class="form-label">Instructor Name</label>
                            <input type="text" class="form-control" id="editInstructorName" name="instructor_name" maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editMaxEnrollment" class="form-label">Max Enrollment</label>
                            <input type="number" class="form-control" id="editMaxEnrollment" name="max_enrollment" min="1">
                        </div>
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

<!-- View Section Modal -->
<div class="modal fade" id="viewSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Section Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>CRN:</strong>
                        <p id="viewCrn"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Section ID:</strong>
                        <p id="viewSectionId"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Course:</strong>
                        <p id="viewCourse"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Instructor:</strong>
                        <p id="viewInstructor"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Max Enrollment:</strong>
                        <p id="viewMaxEnrollment"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewSectionStatus"></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Created:</strong>
                        <p id="viewSectionCreated"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p id="viewSectionUpdated"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Sections</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">Upload CSV File</label>
                        <input type="file" class="form-control" id="csvFile" name="csv_file" accept=".csv" required>
                        <small class="form-text text-muted">CSV format: crn, course_number, section_id, term_code, instructor_name, max_enrollment, is_active</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Existing sections will be updated based on course + term + section_id combination.
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
    <input type="hidden" name="section_id" id="toggleSectionId">
</form>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="section_id" id="deleteSectionId">
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
    var table = $('#sectionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/sections_data.php',
            data: function(d) {
                d.term_fk = <?= $selectedTermFk ?>;
                d.course_fk = $('#courseFilter').val();
                d.status = $('#statusFilter').val();
            }
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 0, name: 'crn' },
            { data: 1, name: 'course_number' },
            { data: 2, name: 'section_id' },
            { data: 3, name: 'instructor_name' },
            { data: 4, name: 'max_enrollment' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'asc'], [2, 'asc']]
    });

    // Reload table when filters change
    $('#courseFilter, #statusFilter').on('change', function() {
        table.ajax.reload();
    });
});

function viewSection(section) {
    $('#viewCrn').text(section.crn || 'N/A');
    $('#viewSectionId').text(section.section_id);
    $('#viewCourse').text(section.course_number + ' - ' + section.course_name);
    $('#viewInstructor').text(section.instructor_name || 'N/A');
    $('#viewMaxEnrollment').text(section.max_enrollment || 'N/A');
    $('#viewSectionStatus').html(section.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>');
    $('#viewSectionCreated').text(section.created_at);
    $('#viewSectionUpdated').text(section.updated_at);
    new bootstrap.Modal(document.getElementById('viewSectionModal')).show();
}

function editSection(section) {
    $('#editSectionId').val(section.sections_pk);
    $('#editCourseFk').val(section.course_fk);
    $('#editTermFk').val(section.term_fk);
    $('#editSectionIdValue').val(section.section_id);
    $('#editCrn').val(section.crn);
    $('#editInstructorName').val(section.instructor_name);
    $('#editMaxEnrollment').val(section.max_enrollment);
    $('#editIsActive').prop('checked', section.is_active == 1);
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Are you sure you want to toggle the status of section "' + name + '"?')) {
        $('#toggleSectionId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteSection(id, name) {
    if (confirm('Are you sure you want to DELETE section "' + name + '"? This action cannot be undone.')) {
        $('#deleteSectionId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
