<?php
declare(strict_types=1);

/**
 * Terms Administration
 * 
 * Manage academic terms (Fall 2025, Spring 2026, etc.).
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
                $termName = trim($_POST['term_name'] ?? '');
                $startDate = trim($_POST['start_date'] ?? '');
                $endDate = trim($_POST['end_date'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if (empty($termName)) $errors[] = 'Term name is required';
                
                // Check for duplicate
                if (!empty($termName)) {
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}terms WHERE term_name = ?",
                        [$termName],
                        's'
                    );
                    $checkRow = $checkResult->fetch();
                    if ($checkRow['count'] > 0) {
                        $errors[] = 'Term name already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}terms (term_name, start_date, end_date, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, NOW(), NOW())",
                        [$termName, $startDate ?: null, $endDate ?: null, $isActive],
                        'sssi'
                    );
                    $successMessage = 'Term added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['term_id'] ?? 0);
                $termName = trim($_POST['term_name'] ?? '');
                $startDate = trim($_POST['start_date'] ?? '');
                $endDate = trim($_POST['end_date'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) $errors[] = 'Invalid term ID';
                if (empty($termName)) $errors[] = 'Term name is required';
                
                // Check for duplicate (excluding current)
                if (!empty($termName) && $id > 0) {
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}terms WHERE term_name = ? AND terms_pk != ?",
                        [$termName, $id],
                        'si'
                    );
                    $checkRow = $checkResult->fetch();
                    if ($checkRow['count'] > 0) {
                        $errors[] = 'Term name already exists';
                    }
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}terms 
                         SET term_name = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW()
                         WHERE terms_pk = ?",
                        [$termName, $startDate ?: null, $endDate ?: null, $isActive, $id],
                        'sssii'
                    );
                    $successMessage = 'Term updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['term_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}terms SET is_active = NOT is_active, updated_at = NOW() WHERE terms_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Term status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['term_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "DELETE FROM {$dbPrefix}terms WHERE terms_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Term deleted successfully';
                }
                break;
                
            case 'import':
                if (isset($_FILES['terms_upload']) && $_FILES['terms_upload']['error'] === UPLOAD_ERR_OK) {
                    $csvFile = $_FILES['terms_upload']['tmp_name'];
                    $handle = fopen($csvFile, 'r');
                    
                    if ($handle !== false) {
                        fgetcsv($handle); // Skip header
                        
                        $imported = 0;
                        $skipped = 0;
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 1) {
                                $termName = trim($row[0]);
                                $startDate = isset($row[1]) && !empty(trim($row[1])) ? trim($row[1]) : null;
                                $endDate = isset($row[2]) && !empty(trim($row[2])) ? trim($row[2]) : null;
                                $isActive = isset($row[3]) && strtolower(trim($row[3])) === 'active' ? 1 : 0;
                                
                                if (!empty($termName)) {
                                    // Check if term exists
                                    $checkResult = $db->query(
                                        "SELECT terms_pk FROM {$dbPrefix}terms WHERE term_name = ?",
                                        [$termName],
                                        's'
                                    );
                                    $termRow = $checkResult->fetch();
                                    
                                    if ($termRow) {
                                        // Update existing
                                        $db->query(
                                            "UPDATE {$dbPrefix}terms SET start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE terms_pk = ?",
                                            [$startDate, $endDate, $isActive, $termRow['terms_pk']],
                                            'ssii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}terms (term_name, start_date, end_date, is_active, created_at, updated_at)
                                             VALUES (?, ?, ?, ?, NOW(), NOW())",
                                            [$termName, $startDate, $endDate, $isActive],
                                            'sssi'
                                        );
                                    }
                                    $imported++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        $successMessage = "Import completed: {$imported} imported/updated, {$skipped} skipped";
                    } else {
                        $errorMessage = 'Failed to read CSV file';
                    }
                } else {
                    $errorMessage = 'No file uploaded or upload error';
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Terms Management',
    'currentPage' => 'admin_terms',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Terms']
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
                    <li class="breadcrumb-item active">Terms</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-calendar-week"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Terms</span>
                        <span class="info-box-number"><?= $totalTerms ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Terms</span>
                        <span class="info-box-number"><?= $activeTerms ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-bookmark"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Terms with Sections</span>
                        <span class="info-box-number"><?= $termsWithSections ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Terms Table -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-week"></i> Academic Terms
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTermModal">
                        <i class="fas fa-plus"></i> Add Term
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="termsTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Term Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search ID"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Term"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Start"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search End"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Status"></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Term Modal -->
<div class="modal fade" id="addTermModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="termName" class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="termName" name="term_name" 
                               required placeholder="e.g., Fall 2025, Spring 2026">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Term</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Term Modal -->
<div class="modal fade" id="editTermModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="term_id" id="editTermId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTermName" class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTermName" name="term_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editStartDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="editStartDate" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEndDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="editEndDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Term</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="import">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Terms from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="terms_upload" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="terms_upload" name="terms_upload" accept=".csv" required>
                        <div class="form-text">
                            CSV format: Term Year Name, Term Name, Start Date (YYYY-MM-DD), End Date (YYYY-MM-DD), Status (Active/Inactive)
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Existing terms will be updated. New terms will be created.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    // Initialize DataTable
    var table = $('#termsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: 'terms_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        columnDefs: [
            { targets: [5], orderable: false },
            { targets: [0], visible: false }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search terms..."
        },
        initComplete: function() {
            this.api().columns([1, 2, 3, 4]).every(function() {
                var column = this;
                var footer = $('input', this.footer());
                
                footer.on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function editTerm(term) {
    $('#editTermId').val(term.terms_pk);
    $('#editTermName').val(term.term_name);
    $('#editStartDate').val(term.start_date || '');
    $('#editEndDate').val(term.end_date || '');
    $('#editIsActive').prop('checked', term.is_active == 1);
    new bootstrap.Modal(document.getElementById('editTermModal')).show();
}

function toggleStatus(id) {
    if (confirm('Toggle this term status?')) {
        const form = $('<form method="POST"></form>');
        form.append('<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">');
        form.append('<input type="hidden" name="action" value="toggle_status">');
        form.append('<input type="hidden" name="term_id" value="' + id + '">');
        $('body').append(form);
        form.submit();
    }
}

function deleteTerm(id) {
    if (confirm('Are you sure you want to delete this term? This cannot be undone.')) {
        const form = $('<form method="POST"></form>');
        form.append('<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">');
        form.append('<input type="hidden" name="action" value="delete">');
        form.append('<input type="hidden" name="term_id" value="' + id + '">');
        $('body').append(form);
        form.submit();
    }
}
</script>
