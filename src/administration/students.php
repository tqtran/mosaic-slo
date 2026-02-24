<?php
declare(strict_types=1);

/**
 * Students Administration
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
                $studentId = trim($_POST['student_id'] ?? '');
                $firstName = trim($_POST['student_first_name'] ?? '');
                $lastName = trim($_POST['student_last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if (empty($studentId)) {
                    $errors[] = 'Student ID is required';
                }
                if (empty($firstName)) {
                    $errors[] = 'First name is required';
                }
                if (empty($lastName)) {
                    $errors[] = 'Last name is required';
                }
                
                // Check student_id uniqueness
                if (!empty($studentId)) {
                    $checkResult = $db->query(
                        "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ?",
                        [$studentId],
                        's'
                    );
                    if ($checkResult->fetch()) {
                        $errors[] = 'Student ID already exists';
                    }
                }
                
                if (empty($errors)) {
                    // Note: In production, these fields should be encrypted
                    $db->query(
                        "INSERT INTO {$dbPrefix}students (student_id, first_name, last_name, email, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$studentId, $firstName, $lastName, $email, $isActive],
                        'ssssi'
                    );
                    $successMessage = 'Student added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['student_pk'] ?? 0);
                $studentId = trim($_POST['student_id'] ?? '');
                $firstName = trim($_POST['student_first_name'] ?? '');
                $lastName = trim($_POST['student_last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid student PK';
                }
                if (empty($studentId)) {
                    $errors[] = 'Student ID is required';
                }
                if (empty($firstName)) {
                    $errors[] = 'First name is required';
                }
                if (empty($lastName)) {
                    $errors[] = 'Last name is required';
                }
                
                // Check student_id uniqueness (exclude current record)
                if (!empty($studentId)) {
                    $checkResult = $db->query(
                        "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ? AND students_pk != ?",
                        [$studentId, $id],
                        'si'
                    );
                    if ($checkResult->fetch()) {
                        $errors[] = 'Student ID already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}students 
                         SET student_id = ?, first_name = ?, last_name = ?, email = ?, is_active = ?, updated_at = NOW()
                         WHERE students_pk = ?",
                        [$studentId, $firstName, $lastName, $email, $isActive, $id],
                        'ssssii'
                    );
                    $successMessage = 'Student updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['student_pk'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}students SET is_active = NOT is_active, updated_at = NOW() WHERE students_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Student status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['student_pk'] ?? 0);
                if ($id > 0) {
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}assessments WHERE students_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete student: they have associated assessments.';
                    } else {
                        $db->query("DELETE FROM {$dbPrefix}students WHERE students_pk = ?", [$id], 'i');
                        $successMessage = 'Student deleted successfully';
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
                
                // Expected columns: student_id, student_first_name, student_last_name, email, is_active
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue; // Need at least student_id, first_name, last_name
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $studentId = trim($data['student_id'] ?? '');
                    $firstName = trim($data['student_first_name'] ?? '');
                    $lastName = trim($data['student_last_name'] ?? '');
                    $email = trim($data['email'] ?? '');
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($studentId) || empty($firstName) || empty($lastName)) {
                        $errors[] = "Skipped row: missing required fields (student_id, first_name, or last_name)";
                        continue;
                    }
                    
                    // Check if student exists (based on unique student_id)
                    $result = $db->query(
                        "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ?",
                        [$studentId],
                        's'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $db->query(
                            "UPDATE {$dbPrefix}students 
                             SET first_name = ?, last_name = ?, email = ?, is_active = ?, updated_at = NOW() 
                             WHERE students_pk = ?",
                            [$firstName, $lastName, $email, $isActive, $existing['students_pk']],
                            'sssii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}students (student_id, first_name, last_name, email, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                            [$studentId, $firstName, $lastName, $email, $isActive],
                            'ssssi'
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
    FROM {$dbPrefix}students
");
$stats = $statsResult->fetch();
$totalStudents = $stats['total'];
$activeStudents = $stats['active'];

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Student Management',
    'currentPage' => 'admin_students',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Students']
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
                    <li class="breadcrumb-item active">Students</li>
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
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong>FERPA Notice:</strong> Student data fields (ID, name, email) should be encrypted in production. Currently stored as plain text for development.
        </div>
        
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-user-graduate"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Students</span>
                        <span class="info-box-number"><?= $totalStudents ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Students</span>
                        <span class="info-box-number"><?= $activeStudents ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Students</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="studentsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>PK</th>
                            <th>Student ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
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
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="studentId" class="form-label">Student ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="studentId" name="student_id" maxlength="255" required>
                        <small class="form-text text-muted">Unique student identifier</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="student_first_name" maxlength="255" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="student_last_name" maxlength="255" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="255">
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

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="student_pk" id="editStudentPk">
                    
                    <div class="mb-3">
                        <label for="editStudentId" class="form-label">Student ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editStudentId" name="student_id" maxlength="255" required>
                        <small class="form-text text-muted">Unique student identifier</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editFirstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="editFirstName" name="student_first_name" maxlength="255" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editLastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="editLastName" name="student_last_name" maxlength="255" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" maxlength="255">
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
                <h5 class="modal-title"><i class="fas fa-upload"></i> Import Students from CSV</h5>
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
                        <code>student_id,student_first_name,student_last_name,email,is_active</code><br>
                        <small class="text-muted">student_id is required (unique identifier). is_active should be 1/0 or true/false (default: true)</small>
                    </div>
                    
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle"></i> <strong>FERPA Notice:</strong> Ensure CSV files are transmitted and stored securely. Data will be stored unencrypted in development.
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

<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="student_pk" id="toggleStudentPk">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="student_pk" id="deleteStudentPk">
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
    $('#studentsTable thead tr:eq(1) th').each(function(i) {
        var title = $('#studentsTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#studentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/students_data.php',
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'students_pk' },
            { data: 1, name: 'student_id' },
            { data: 2, name: 'student_first_name' },
            { data: 3, name: 'student_last_name' },
            { data: 4, name: 'email' },
            { data: 5, name: 'is_active' },
            { data: 6, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
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

function editStudent(student) {
    $('#editStudentPk').val(student.students_pk);
    $('#editStudentId').val(student.student_id);
    $('#editFirstName').val(student.first_name);
    $('#editLastName').val(student.last_name);
    $('#editEmail').val(student.email);
    $('#editIsActive').prop('checked', student.is_active == 1);
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

function toggleStatus(id, studentId) {
    if (confirm('Are you sure you want to toggle the status of student "' + studentId + '"?')) {
        $('#toggleStudentPk').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteStudent(id, studentId) {
    if (confirm('Are you sure you want to DELETE student "' + studentId + '"? This action cannot be undone.')) {
        $('#deleteStudentPk').val(id);
        $('#deleteForm').submit();
    }
}
</script>
