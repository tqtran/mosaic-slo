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
                $departmentFk = (int)($_POST['department_fk'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                $creditHours = !empty($_POST['credit_hours']) ? (int)$_POST['credit_hours'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if (empty($courseName)) {
                    $errors[] = 'Course name is required';
                }
                if (empty($courseNumber)) {
                    $errors[] = 'Course number is required';
                }
                if ($departmentFk <= 0) {
                    $errors[] = 'Department is required';
                } else {
                    // Validate department exists
                    $deptCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}departments WHERE departments_pk = ? AND is_active = 1",
                        [$departmentFk],
                        'i'
                    );
                    $deptRow = $deptCheck->fetch();
                    if ($deptRow['count'] == 0) {
                        $errors[] = 'Invalid department selected';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}courses (course_name, course_number, department_fk, description, credit_hours, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$courseName, $courseNumber, $departmentFk, $description, $creditHours, $isActive],
                        'sssiii'
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
                $departmentFk = (int)($_POST['department_fk'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                $creditHours = !empty($_POST['credit_hours']) ? (int)$_POST['credit_hours'] : null;
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
                if ($departmentFk <= 0) {
                    $errors[] = 'Department is required';
                } else {
                    // Validate department exists
                    $deptCheck = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}departments WHERE departments_pk = ? AND is_active = 1",
                        [$departmentFk],
                        'i'
                    );
                    $deptRow = $deptCheck->fetch();
                    if ($deptRow['count'] == 0) {
                        $errors[] = 'Invalid department selected';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}courses 
                         SET course_name = ?, course_number = ?, department_fk = ?, description = ?, credit_hours = ?, is_active = ?, updated_at = NOW()
                         WHERE courses_pk = ?",
                        [$courseName, $courseNumber, $departmentFk, $description, $creditHours, $isActive, $id],
                        'sssiiii'
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
                    $departmentCode = trim($data['department_code'] ?? '');
                    $description = trim($data['description'] ?? '');
                    $creditHours = !empty($data['credit_hours']) ? (int)$data['credit_hours'] : null;
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($courseName) || empty($courseNumber)) {
                        $errors[] = "Skipped row: missing required fields (course_name or course_number)";
                        continue;
                    }
                    
                    // Lookup department by code
                    $departmentFk = null;
                    if (!empty($departmentCode)) {
                        $deptLookup = $db->query(
                            "SELECT departments_pk FROM {$dbPrefix}departments WHERE department_code = ? AND is_active = 1",
                            [$departmentCode],
                            's'
                        );
                        if ($deptLookup->rowCount() > 0) {
                            $deptRow = $deptLookup->fetch();
                            $departmentFk = $deptRow['departments_pk'];
                        }
                    }
                    
                    if ($departmentFk === null) {
                        $errors[] = "Skipped row for {$courseName}: invalid or missing department code";
                        continue;
                    }
                    
                    // Check if course exists (based on unique key: course_name + course_number)
                    $result = $db->query(
                        "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_name = ? AND course_number = ?",
                        [$courseName, $courseNumber],
                        'ss'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $db->query(
                            "UPDATE {$dbPrefix}courses 
                             SET department_fk = ?, description = ?, credit_hours = ?, is_active = ?, updated_at = NOW() 
                             WHERE courses_pk = ?",
                            [$departmentFk, $description, $creditHours, $isActive, $existing['courses_pk']],
                            'isiii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}courses (course_name, course_number, department_fk, description, credit_hours, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$courseName, $courseNumber, $departmentFk, $description, $creditHours, $isActive],
                            'sssiii'
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

// Fetch departments for dropdown
$deptResult = $db->query("
    SELECT departments_pk, department_code, department_name 
    FROM {$dbPrefix}departments 
    WHERE is_active = 1 
    ORDER BY department_name
");
$departments = $deptResult->fetchAll();

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}courses
");
$stats = $statsResult->fetch();
$totalCourses = $stats['total'];
$activeCourses = $stats['active'];
$inactiveCourses = $stats['inactive'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Course Management',
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
        
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-book"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Courses</span>
                        <span class="info-box-number"><?= $totalCourses ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Courses</span>
                        <span class="info-box-number"><?= $activeCourses ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive Courses</span>
                        <span class="info-box-number"><?= $inactiveCourses ?></span>
                    </div>
                </div>
            </div>
        </div>

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
                </div>
            </div>
            <div class="card-body">
                <table id="coursesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course Name</th>
                            <th>Course Number</th>
                            <th>Department</th>
                            <th>Credit Hours</th>
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
                            <th>Course Name</th>
                            <th>Course Number</th>
                            <th>Department</th>
                            <th>Credit Hours</th>
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
                        <label for="departmentFk" class="form-label">Department</label>
                        <select class="form-select" id="departmentFk" name="department_fk" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['departments_pk'] ?>"><?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <small class="text-muted">(Optional)</small></label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="creditHours" class="form-label">Credit Hours <small class="text-muted">(Optional)</small></label>
                        <input type="number" class="form-control" id="creditHours" name="credit_hours" min="0" max="20" step="1">
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
                        <label for="editDepartmentFk" class="form-label">Department</label>
                        <select class="form-select" id="editDepartmentFk" name="department_fk" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['departments_pk'] ?>"><?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description <small class="text-muted">(Optional)</small></label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editCreditHours" class="form-label">Credit Hours <small class="text-muted">(Optional)</small></label>
                        <input type="number" class="form-control" id="editCreditHours" name="credit_hours" min="0" max="20" step="1">
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
                        <code>course_name,course_number,department_code,description,credit_hours,is_active</code><br>
                        <small class="text-muted">description (optional), credit_hours (integer, optional), is_active should be 1/0 or true/false (default: true)</small>
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
var departments = <?= json_encode(array_map(function($d) { 
    return ['name' => $d['department_name'], 'code' => $d['department_code']]; 
}, $departments)) ?>;

$(document).ready(function() {
    $('#coursesTable thead tr:eq(1) th').each(function(i) {
        var title = $('#coursesTable thead tr:eq(0) th:eq(' + i + ')').text();
        
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
    
    var table = $('#coursesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/courses_data.php',
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'courses_pk' },
            { data: 1, name: 'course_name' },
            { data: 2, name: 'course_number' },
            { data: 3, name: 'department_name' },
            { data: 4, name: 'credit_hours' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'created_at' },
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

function editCourse(course) {
    $('#editCourseId').val(course.courses_pk);
    $('#editCourseName').val(course.course_name);
    $('#editCourseNumber').val(course.course_number);
    $('#editDepartmentFk').val(course.department_fk);
    $('#editDescription').val(course.description || '');
    $('#editCreditHours').val(course.credit_hours || '');
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
