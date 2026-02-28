<?php
declare(strict_types=1);

/**
 * Courses Administration
 * 
 * Manage courses.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

// Get selected term (from GET/session, or default to latest)
$selectedTermFk = getSelectedTermFk();

// We'll set default term after fetching terms list

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
                $courseName = trim($_POST['course_name'] ?? '');
                $courseNumber = trim($_POST['course_number'] ?? '');
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if (empty($courseName)) {
                    $errors[] = 'Course name is required';
                }
                if (empty($courseNumber)) {
                    $errors[] = 'Course number is required';
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
                        "INSERT INTO {$dbPrefix}courses (course_name, course_number, term_fk, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, NOW(), NOW())",
                        [$courseName, $courseNumber, $termFk, $isActive],
                        'ssii'
                    );
                    $successMessage = 'Course added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['course_id'] ?? 0);
                $courseName = trim($_POST['course_name'] ?? '');
                $courseNumber = trim($_POST['course_number'] ?? '');
                $termFk = (int)($_POST['term_fk'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid course ID';
                }
                if (empty($courseName)) {
                    $errors[] = 'Course name is required';
                }
                if (empty($courseNumber)) {
                    $errors[] = 'Course number is required';
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
                        "UPDATE {$dbPrefix}courses 
                         SET course_name = ?, course_number = ?, term_fk = ?, is_active = ?, updated_at = NOW()
                         WHERE courses_pk = ?",
                        [$courseName, $courseNumber, $termFk, $isActive, $id],
                        'ssiii'
                    );
                    $successMessage = 'Course updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['course_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}courses 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE courses_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Course status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['course_id'] ?? 0);
                if ($id > 0) {
                    // Check if course has associated SLOs
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}student_learning_outcomes WHERE course_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete course: it has associated SLOs. Please remove SLOs first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}courses WHERE courses_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Course deleted successfully';
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
                
                // Expected columns: course_name, course_number, is_active
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 2) continue; // Need at least course_name and course_number
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $courseName = trim($data['course_name'] ?? '');
                    $courseNumber = trim($data['course_number'] ?? '');
                    $termCode = trim($data['term_code'] ?? '');
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($courseName) || empty($courseNumber)) {
                        $errors[] = "Skipped row: missing required fields (course_name or course_number)";
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
                        $errors[] = "Skipped row for {$courseName}: invalid or missing term code";
                        continue;
                    }
                    
                    // Check if course exists (based on unique key: course_number)
                    $result = $db->query(
                        "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ?",
                        [$courseNumber],
                        's'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $db->query(
                            "UPDATE {$dbPrefix}courses 
                             SET course_name = ?, term_fk = ?, is_active = ?, updated_at = NOW() 
                             WHERE courses_pk = ?",
                            [$courseName, $termFk, $isActive, $existing['courses_pk']],
                            'siii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}courses (course_name, course_number, term_fk, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, NOW(), NOW())",
                            [$courseName, $courseNumber, $termFk, $isActive],
                            'ssii'
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
                
            case 'import_map':
                if (!isset($_FILES['map_upload']) || $_FILES['map_upload']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Please select a valid CSV file';
                    break;
                }
                
                $file = $_FILES['map_upload']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if ($handle === false) {
                    $errorMessage = 'Failed to open CSV file';
                    break;
                }
                
                // Skip BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                
                $headers = fgetcsv($handle); // Read header row
                $imported = 0;
                $skipped = 0;
                $incomplete = 0;
                $programsCreated = 0;
                $coursesCreated = 0;
                $errors = [];
                $rowNum = 1;
                
                // Build maps for lookups
                $programMap = [];
                $programsResult = $db->query("
                    SELECT programs_pk, program_code 
                    FROM {$dbPrefix}programs 
                    WHERE is_active = 1
                ");
                while ($prog = $programsResult->fetch()) {
                    $programMap[trim($prog['program_code'])] = $prog['programs_pk'];
                }
                
                $courseMap = [];
                $coursesResult = $db->query("
                    SELECT courses_pk, course_number 
                    FROM {$dbPrefix}courses 
                    WHERE is_active = 1
                ");
                while ($course = $coursesResult->fetch()) {
                    $courseMap[trim($course['course_number'])] = $course['courses_pk'];
                }
                
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    if (count($row) >= 3) {
                        // CSV format: ProgramID,Program,Course
                        $programCode = trim($row[0]);
                        $programName = trim($row[1]);
                        $courseNumber = trim($row[2]);
                        
                        $programFk = null;
                        $courseFk = null;
                        
                        // Create/lookup program if program code is provided
                        if (!empty($programCode)) {
                            if (!isset($programMap[$programCode])) {
                                // Double-check database with TRIM in case of whitespace differences
                                $existingProgram = $db->query(
                                    "SELECT programs_pk FROM {$dbPrefix}programs WHERE TRIM(program_code) = ? LIMIT 1",
                                    [$programCode],
                                    's'
                                );
                                $existingRow = $existingProgram->fetch();
                                
                                if ($existingRow) {
                                    // Program exists with whitespace differences - use it
                                    $programFk = $existingRow['programs_pk'];
                                    $programMap[$programCode] = $programFk;
                                } else {
                                    // Auto-create program
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}programs (program_code, program_name, term_fk, is_active, created_at, updated_at) 
                                         VALUES (?, ?, ?, 1, NOW(), NOW())",
                                        [$programCode, $programName ?: $programCode, $selectedTermFk],
                                        'ssi'
                                    );
                                    $programFk = $db->getInsertId();
                                    $programMap[$programCode] = $programFk;
                                    $programsCreated++;
                                }
                            } else {
                                $programFk = $programMap[$programCode];
                            }
                        }
                        
                        // Create/lookup course if course number is provided
                        if (!empty($courseNumber)) {
                            if (!isset($courseMap[$courseNumber])) {
                                // Double-check database with TRIM in case of whitespace differences
                                $existingCourse = $db->query(
                                    "SELECT courses_pk FROM {$dbPrefix}courses WHERE TRIM(course_number) = ? LIMIT 1",
                                    [$courseNumber],
                                    's'
                                );
                                $existingRow = $existingCourse->fetch();
                                
                                if ($existingRow) {
                                    // Course exists with whitespace differences - use it
                                    $courseFk = $existingRow['courses_pk'];
                                    $courseMap[$courseNumber] = $courseFk;
                                } else {
                                    // Auto-create course (use course_number as course_name placeholder)
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}courses (course_name, course_number, term_fk, is_active, created_at, updated_at) 
                                         VALUES (?, ?, ?, 1, NOW(), NOW())",
                                        [$courseNumber, $courseNumber, $selectedTermFk],
                                        'ssi'
                                    );
                                    $courseFk = $db->getInsertId();
                                    $courseMap[$courseNumber] = $courseFk;
                                    $coursesCreated++;
                                }
                            } else {
                                $courseFk = $courseMap[$courseNumber];
                            }
                        }
                        
                        // Only create mapping if both program and course are present
                        if ($programFk && $courseFk) {
                            // Check if mapping already exists
                            $result = $db->query(
                                "SELECT program_courses_pk FROM {$dbPrefix}program_courses 
                                 WHERE program_fk = ? AND course_fk = ?",
                                [$programFk, $courseFk],
                                'ii'
                            );
                            
                            if ($result->rowCount() > 0) {
                                $skipped++;
                            } else {
                                // Insert new mapping
                                $db->query(
                                    "INSERT INTO {$dbPrefix}program_courses (program_fk, course_fk, created_at) 
                                     VALUES (?, ?, NOW())",
                                    [$programFk, $courseFk],
                                    'ii'
                                );
                                $imported++;
                            }
                        } else {
                            // Incomplete row - parent records created but mapping skipped
                            $incomplete++;
                        }
                    }
                }
                
                fclose($handle);
                
                $summary = "$imported program-course mappings imported";
                if ($programsCreated > 0) {
                    $summary .= ", $programsCreated programs created";
                }
                if ($coursesCreated > 0) {
                    $summary .= ", $coursesCreated courses created";
                }
                if ($skipped > 0) {
                    $summary .= ", $skipped duplicates skipped";
                }
                if ($incomplete > 0) {
                    $summary .= ", $incomplete incomplete rows processed (parent records created, mappings skipped)";
                }
                
                if (count($errors) > 0) {
                    $errorList = array_slice($errors, 0, 5);
                    $errorMessage = "Import completed with errors: $summary<br><br>" . implode('<br>', $errorList);
                    if (count($errors) > 5) {
                        $errorMessage .= "<br>...and " . (count($errors) - 5) . " more errors";
                    }
                } else {
                    $successMessage = "Import completed successfully: $summary";
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
    ORDER BY term_code ASC
");
$terms = $termResult->fetchAll();

// Default to first term if none selected
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

// Calculate statistics (filtered by selected term if applicable)
if ($selectedTermFk) {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
        FROM {$dbPrefix}courses
        WHERE term_fk = ?
    ", [$selectedTermFk], 'i');
} else {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
        FROM {$dbPrefix}courses
    ");
}
$stats = $statsResult->fetch();
$totalCourses = $stats['total'];
$activeCourses = $stats['active'];
$inactiveCourses = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Course Management';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_courses',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Courses']
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
                    <li class="breadcrumb-item active">Courses</li>
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

        <!-- Courses Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Courses</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus"></i> Add Course
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#uploadMapModal">
                        <i class="fas fa-project-diagram"></i> Import PSLO Map
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="coursesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course Name</th>
                            <th>Course Number</th>
                            <th>Term</th>
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
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>Course Name</th>
                            <th>Course Number</th>
                            <th>Term</th>
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

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="courseName" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="courseName" name="course_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="courseNumber" class="form-label">Course Number</label>
                        <input type="text" class="form-control" id="courseNumber" name="course_number" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="termFk" class="form-label">Term</label>
                        <select class="form-select" id="termFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?= $term['terms_pk'] ?>"><?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?></option>
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

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="course_id" id="editCourseId">
                    <div class="mb-3">
                        <label for="editCourseName" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="editCourseName" name="course_name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCourseNumber" class="form-label">Course Number</label>
                        <input type="text" class="form-control" id="editCourseNumber" name="course_number" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTermFk" class="form-label">Term</label>
                        <select class="form-select" id="editTermFk" name="term_fk" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                            <option value="<?= $term['terms_pk'] ?>"><?= htmlspecialchars($term['term_code'] . ' - ' . $term['term_name']) ?></option>
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
                <h5 class="modal-title"><i class="fas fa-upload"></i> Import Courses from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>CSV Format:</strong><br>
                        <code>course_name,course_number,term_code,is_active</code><br>
                        <small class="text-muted">term_code (required), is_active should be 1/0 or true/false (default: true)<br>
                        <strong>Note:</strong> Courses are no longer assigned to programs during import. Use "Import PSLO Map" to associate courses with programs after importing courses.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PSLO Map Upload Modal -->
<div class="modal fade" id="uploadMapModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-project-diagram"></i> Import PSLO Map</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import_map">
                    
                    <div class="mb-3">
                        <label for="map_upload" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="map_upload" name="map_upload" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>CSV Format:</strong><br>
                        <code>ProgramID,Program,Course</code><br>
                        <small class="text-muted">
                            <ul class="mb-0 mt-2">
                                <li>Maps courses to programs (many-to-many relationship)</li>
                                <li>ProgramID: Program code (e.g., AS_ACC, CA_ETC)</li>
                                <li>Program: Program name (used if program doesn't exist)</li>
                                <li>Course: Course number (e.g., ACCT C100, EDUC C202)</li>
                                <li><strong>Programs and courses will be auto-created if they don't exist</strong></li>
                                <li><strong>Rows with missing program or course data will create valid parent records but skip the mapping</strong></li>
                                <li>Duplicate mappings will be skipped</li>
                            </ul>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Form (hidden) -->
<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="course_id" id="toggleCourseId">
</form>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="course_id" id="deleteCourseId">
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
var terms = <?= json_encode(array_map(function($t) { 
    return ['name' => $t['term_name'], 'code' => $t['term_code']]; 
}, $terms)) ?>;

$(document).ready(function() {
    $('#coursesTable thead tr:eq(1) th').each(function(i) {
        var title = $('#coursesTable thead tr:eq(0) th:eq(' + i + ')').text();
        
        // Program column (index 3) gets dropdown
        if (title === 'Program') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Programs</option></select>')
                .appendTo($(this).empty());
            
            // Populate from PHP data
            programs.forEach(function(prog) {
                select.append('<option value="' + prog.name + '">' + prog.name + '</option>');
            });
        }
        // Term column (index 4) gets dropdown
        else if (title === 'Term') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Terms</option></select>')
                .appendTo($(this).empty());
            
            // Populate from PHP data
            terms.forEach(function(term) {
                select.append('<option value="' + term.name + '">' + term.name + '</option>');
            });
        }
        else if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#coursesTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/courses_data.php',
            data: function(d) {
                d.term_fk = $('#termFilter').val();
            }
        },
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'courses_pk' },
            { data: 1, name: 'course_name' },
            { data: 2, name: 'course_number' },
            { data: 3, name: 'term_name' },
            { data: 4, name: 'is_active' },
            { data: 5, name: 'created_at' },
            { data: 6, name: 'actions', orderable: false, searchable: false }
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

function editCourse(course) {
    $('#editCourseId').val(course.courses_pk);
    $('#editCourseName').val(course.course_name);
    $('#editCourseNumber').val(course.course_number);
    $('#editTermFk').val(course.term_fk);
    $('#editIsActive').prop('checked', course.is_active == 1);
    new bootstrap.Modal(document.getElementById('editCourseModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Are you sure you want to toggle the status of "' + name + '"?')) {
        $('#toggleCourseId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteCourse(id, name) {
    if (confirm('Are you sure you want to DELETE "' + name + '"? This action cannot be undone.')) {
        $('#deleteCourseId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
