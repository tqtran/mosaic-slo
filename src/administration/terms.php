<?php
declare(strict_types=1);

/**
 * Terms Administration (Import Only)
 * 
 * Manage academic terms via CSV import.
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
        if ($action === 'import') {
            if (isset($_FILES['terms_upload']) && $_FILES['terms_upload']['error'] === UPLOAD_ERR_OK) {
                $csvFile = $_FILES['terms_upload']['tmp_name'];
                $handle = fopen($csvFile, 'r');
                
                if ($handle !== false) {
                    $headers = fgetcsv($handle); // Read header
                    
                    // Strip UTF-8 BOM if present  
                    if (!empty($headers[0])) {
                        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
                    }
                    
                    $imported = 0;
                    $updated = 0;
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) >= 3) {
                            $data = array_combine($headers, $row);
                            
                            $termCode = trim($data['term_code'] ?? '');
                            $termName = trim($data['term_name'] ?? '');
                            $startDate = trim($data['start_date'] ?? '');
                            $endDate = trim($data['end_date'] ?? '');
                            $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                            
                            if (!empty($termCode) && !empty($termName)) {
                                // Check if term exists
                                $checkResult = $db->query(
                                    "SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ?",
                                    [$termCode],
                                    's'
                                );
                                $termRow = $checkResult->fetch();
                                
                                if ($termRow) {
                                    // Update existing
                                    $db->query(
                                        "UPDATE {$dbPrefix}terms SET term_name = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE terms_pk = ?",
                                        [$termName, $startDate, $endDate, $isActive, $termRow['terms_pk']],
                                        'sssii'
                                    );
                                    $updated++;
                                } else {
                                    // Insert new
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}terms (term_code, term_name, start_date, end_date, is_active, created_at, updated_at)
                                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                        [$termCode, $termName, $startDate, $endDate, $isActive],
                                        'ssssi'
                                    );
                                    $imported++;
                                }
                            }
                        }
                    }
                    
                    fclose($handle);
                    $successMessage = "Import completed: {$imported} new, {$updated} updated";
                } else {
                    $errorMessage = 'Failed to read CSV file';
                }
            } else {
                $errorMessage = 'No file uploaded or upload error';
            }
        }
    } catch (\Exception $e) {
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// Calculate statistics
$statsResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}terms");
$statsRow = $statsResult->fetch();
$totalTerms = $statsRow['total'] ?? 0;

$activeResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}terms WHERE is_active = 1");
$activeRow = $activeResult->fetch();
$activeTerms = $activeRow['total'] ?? 0;

$coursesResult = $db->query("SELECT COUNT(DISTINCT term_fk) as total FROM {$dbPrefix}courses WHERE is_active = 1");
$coursesRow = $coursesResult->fetch();
$termsWithCourses = $coursesRow['total'] ?? 0;

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
                        <span class="info-box-text">Terms with Courses</span>
                        <span class="info-box-number"><?= $termsWithCourses ?></span>
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
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="termsTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Term Code</th>
                            <th>Term Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Import Terms</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="terms_upload" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="terms_upload" name="terms_upload" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>CSV Format:</strong><br>
                        <code>term_code,term_name,start_date,end_date,is_active</code><br>
                        <small class="text-muted">Example: 202630,Spring 2026,2026-01-15,2026-05-15,1</small>
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
    $('#termsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: 'terms_data.php',
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        order: [[2, 'asc']],
        pageLength: 25,
        columnDefs: [
            { targets: [0], visible: false }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search terms..."
        }
    });
});
</script>
