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
                $crn = trim($_POST['crn'] ?? '');
                $sectionNumber = trim($_POST['section_number'] ?? '');
                $instructorName = trim($_POST['instructor_name'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if (empty($crn)) {
                    $errors[] = 'CRN is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}course_sections (course_fk, crn, section_number, instructor_name, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$courseFk, $crn, $sectionNumber, $instructorName, $isActive],
                        'isssi'
                    );
                    $successMessage = 'Course section added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['section_id'] ?? 0);
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $crn = trim($_POST['crn'] ?? '');
                $sectionNumber = trim($_POST['section_number'] ?? '');
                $instructorName = trim($_POST['instructor_name'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid section ID';
                }
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if (empty($crn)) {
                    $errors[] = 'CRN is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}course_sections 
                         SET course_fk = ?, crn = ?, section_number = ?, instructor_name = ?, is_active = ?, updated_at = NOW()
                         WHERE course_sections_pk = ?",
                        [$courseFk, $crn, $sectionNumber, $instructorName, $isActive, $id],
                        'isssii'
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
                
                // Expected columns: course_number,crn,section_number,instructor_name,is_active
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 2) continue; // Need at least course_number and crn
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $courseNumber = trim($data['course_number'] ?? '');
                    $crn = trim($data['crn'] ?? '');
                    $sectionNumber = trim($data['section_number'] ?? '');
                    $instructorName = trim($data['instructor_name'] ?? '');
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($courseNumber) || empty($crn)) {
                        $errors[] = "Skipped row: missing required fields (course_number or crn)";
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
                             SET course_fk = ?, section_number = ?, instructor_name = ?, is_active = ?, updated_at = NOW() 
                             WHERE course_sections_pk = ?",
                            [$courseFk, $sectionNumber, $instructorName, $isActive, $existing['course_sections_pk']],
                            'issii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}course_sections (course_fk, crn, section_number, instructor_name, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                            [$courseFk, $crn, $sectionNumber, $instructorName, $isActive],
                            'isssi'
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
                            <th>Section</th>
                            <th>Instructor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>ID</th>
                            <th>CRN</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Instructor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </tfoot>
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
                        <label for="instructorName" class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="instructorName" name="instructor_name" maxlength="255">
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
                        <label for="editInstructorName" class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="editInstructorName" name="instructor_name" maxlength="255">
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
$(document).ready(function() {
    $('#sectionsTable tfoot th').each(function() {
        var title = $(this).text();
        if (title !== 'Actions') {
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
            { data: 3, name: 'section_number' },
            { data: 4, name: 'instructor_name' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
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

function editSection(section) {
    $('#editSectionId').val(section.course_sections_pk);
    $('#editCourseFk').val(section.course_fk);
    $('#editCrn').val(section.crn);
    $('#editSectionNumber').val(section.section_number);
    $('#editInstructorName').val(section.instructor_name);
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
