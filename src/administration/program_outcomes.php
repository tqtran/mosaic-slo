<?php
declare(strict_types=1);

/**
 * Program Outcomes Administration
 * 
 * Manage program-level learning outcomes with optional mapping to institutional outcomes.
 * 
 * @package Mosaic
 */

// Common admin session setup (handles security headers, session config, CSRF token)
require_once __DIR__ . '/../system/includes/admin_session.php';

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
                $programFk = (int)($_POST['program_fk'] ?? 0);
                $institutionalOutcomeFk = !empty($_POST['institutional_outcomes_fk']) ? (int)$_POST['institutional_outcomes_fk'] : null;
                $code = trim($_POST['code'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($programFk <= 0) {
                    $errors[] = 'Program is required';
                }
                if (empty($code)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $code)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes WHERE code = ? AND program_fk = ?",
                        [$code, $programFk],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists for this program';
                    }
                }
                if (empty($description)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    if ($institutionalOutcomeFk === null) {
                        $db->query(
                            "INSERT INTO {$dbPrefix}program_outcomes (program_fk, code, description, sequence_num, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                            [$programFk, $code, $description, $sequenceNum, $isActive],
                            'issii'
                        );
                    } else {
                        $db->query(
                            "INSERT INTO {$dbPrefix}program_outcomes (program_fk, institutional_outcomes_fk, code, description, sequence_num, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$programFk, $institutionalOutcomeFk, $code, $description, $sequenceNum, $isActive],
                            'iissii'
                        );
                    }
                    $successMessage = 'Program outcome added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['outcome_id'] ?? 0);
                $programFk = (int)($_POST['program_fk'] ?? 0);
                $institutionalOutcomeFk = !empty($_POST['institutional_outcomes_fk']) ? (int)$_POST['institutional_outcomes_fk'] : null;
                $code = trim($_POST['code'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid outcome ID';
                }
                if ($programFk <= 0) {
                    $errors[] = 'Program is required';
                }
                if (empty($code)) {
                    $errors[] = 'Outcome code is required';
                } elseif (!preg_match('/^[A-Z0-9_.-]+$/i', $code)) {
                    $errors[] = 'Outcome code can only contain letters, numbers, hyphens, underscores, and periods';
                } else {
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes 
                         WHERE code = ? AND program_fk = ? AND program_outcomes_pk != ?",
                        [$code, $programFk, $id],
                        'sii'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Outcome code already exists for this program';
                    }
                }
                if (empty($description)) {
                    $errors[] = 'Description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}program_outcomes 
                         SET program_fk = ?, institutional_outcomes_fk = ?, code = ?, description = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                         WHERE program_outcomes_pk = ?",
                        [$programFk, $institutionalOutcomeFk, $code, $description, $sequenceNum, $isActive, $id],
                        'iissiii'
                    );
                    $successMessage = 'Program outcome updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['outcome_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}program_outcomes 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE program_outcomes_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Outcome status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['outcome_id'] ?? 0);
                if ($id > 0) {
                    // Check if outcome has associated SLOs
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}student_learning_outcomes WHERE program_outcomes_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete outcome: it is mapped to student learning outcomes. Please remove mappings first.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}program_outcomes WHERE program_outcomes_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Program outcome deleted successfully';
                    }
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch programs for dropdown
$progResult = $db->query("SELECT * FROM {$dbPrefix}programs WHERE is_active = 1 ORDER BY program_name ASC");
$programs = $progResult->fetchAll();

// Fetch institutional outcomes for dropdown
$instOutcomeResult = $db->query("
    SELECT io.*, i.institution_name 
    FROM {$dbPrefix}institutional_outcomes io
    JOIN {$dbPrefix}institution i ON io.institution_fk = i.institution_pk
    WHERE io.is_active = 1 
    ORDER BY i.institution_name, io.sequence_num, io.code
");
$institutionalOutcomes = $instOutcomeResult->fetchAll();

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN institutional_outcomes_fk IS NOT NULL THEN 1 ELSE 0 END) as mapped
    FROM {$dbPrefix}program_outcomes
");
$stats = $statsResult->fetch();

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Program Outcomes',
    'currentPage' => 'admin_program_outcomes',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Program Outcomes']
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
                    <li class="breadcrumb-item active">Program Outcomes</li>
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
                    <span class="info-box-icon bg-info"><i class="fas fa-list"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Outcomes</span>
                        <span class="info-box-number"><?= $stats['total'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Outcomes</span>
                        <span class="info-box-number"><?= $stats['active'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-times-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Inactive Outcomes</span>
                        <span class="info-box-number"><?= $stats['inactive'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-link"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Mapped to Institutional</span>
                        <span class="info-box-number"><?= $stats['mapped'] ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Table Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Program Outcomes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus"></i> Add Outcome
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="outcomesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Program</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Inst. Outcome</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Program Outcome</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="addProgramFk" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="addProgramFk" name="program_fk" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['programs_pk'] ?>"><?= htmlspecialchars($prog['program_name']) ?> (<?= htmlspecialchars($prog['program_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="addInstitutionalOutcomeFk" class="form-label">Institutional Outcome (Optional)</label>
                        <select class="form-select" id="addInstitutionalOutcomeFk" name="institutional_outcomes_fk">
                            <option value="">None / Not Mapped</option>
                            <?php foreach ($institutionalOutcomes as $io): ?>
                            <option value="<?= $io['institutional_outcomes_pk'] ?>">
                                <?= htmlspecialchars($io['institution_name']) ?> - <?= htmlspecialchars($io['code']) ?>: <?= htmlspecialchars(substr($io['description'], 0, 50)) ?>...
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="addCode" class="form-label">Outcome Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="addCode" name="code" required 
                               pattern="[A-Za-z0-9_.-]+" 
                               title="Letters, numbers, hyphens, underscores, and periods only"
                               placeholder="e.g., PLO-1">
                    </div>
                    
                    <div class="mb-3">
                        <label for="addDescription" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="addDescription" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="addSequenceNum" class="form-label">Sequence Number</label>
                        <input type="number" class="form-control" id="addSequenceNum" name="sequence_num" value="0" min="0">
                        <small class="form-text text-muted">Controls display order (0 = default)</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="addIsActive" name="is_active" checked>
                        <label class="form-check-label" for="addIsActive">Active</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Outcome</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="outcome_id" id="editOutcomeId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Program Outcome</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editProgramFk" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="editProgramFk" name="program_fk" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['programs_pk'] ?>"><?= htmlspecialchars($prog['program_name']) ?> (<?= htmlspecialchars($prog['program_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editInstitutionalOutcomeFk" class="form-label">Institutional Outcome (Optional)</label>
                        <select class="form-select" id="editInstitutionalOutcomeFk" name="institutional_outcomes_fk">
                            <option value="">None / Not Mapped</option>
                            <?php foreach ($institutionalOutcomes as $io): ?>
                            <option value="<?= $io['institutional_outcomes_pk'] ?>">
                                <?= htmlspecialchars($io['institution_name']) ?> - <?= htmlspecialchars($io['code']) ?>: <?= htmlspecialchars(substr($io['description'], 0, 50)) ?>...
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCode" class="form-label">Outcome Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editCode" name="code" required 
                               pattern="[A-Za-z0-9_.-]+" 
                               title="Letters, numbers, hyphens, underscores, and periods only">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editSequenceNum" class="form-label">Sequence Number</label>
                        <input type="number" class="form-control" id="editSequenceNum" name="sequence_num" min="0">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    const table = $('#outcomesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/program_outcomes_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        columns: [
            { data: 'program_outcomes_pk' },
            { data: 'program_name' },
            { data: 'code' },
            { data: 'description' },
            { 
                data: 'institutional_outcome_code',
                render: function(data, type, row) {
                    return data ? '<span class="badge bg-info">' + data + '</span>' : '<span class="text-muted">-</span>';
                }
            },
            { data: 'sequence_num' },
            { 
                data: 'is_active',
                render: function(data) {
                    return data == 1 
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-info edit-btn" data-id="${data.program_outcomes_pk}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="${data.program_outcomes_pk}">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[1, 'asc'], [5, 'asc'], [2, 'asc']]
    });
    
    // Edit button handler
    $('#outcomesTable').on('click', '.edit-btn', function() {
        const id = $(this).data('id');
        const row = table.row($(this).parents('tr')).data();
        
        $('#editOutcomeId').val(row.program_outcomes_pk);
        $('#editProgramFk').val(row.program_fk);
        $('#editInstitutionalOutcomeFk').val(row.institutional_outcomes_fk || '');
        $('#editCode').val(row.code);
        $('#editDescription').val(row.description);
        $('#editSequenceNum').val(row.sequence_num);
        $('#editIsActive').prop('checked', row.is_active == 1);
        
        $('#editModal').modal('show');
    });
    
    // Delete button handler
    $('#outcomesTable').on('click', '.delete-btn', function() {
        if (confirm('Are you sure you want to delete this outcome?')) {
            const id = $(this).data('id');
            const form = $('<form method="POST"></form>');
            form.append('<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">');
            form.append('<input type="hidden" name="action" value="delete">');
            form.append('<input type="hidden" name="outcome_id" value="' + id + '">');
            $('body').append(form);
            form.submit();
        }
    });
});
</script>

<?php
$theme->showFooter($context);
