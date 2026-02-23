<?php
/**
 * Terms Management
 * 
 * Manages academic terms (Fall 2025, Spring 2026, etc.) within term years.
 * Terms are used to organize course sections (CRNs).
 */

declare(strict_types=1);

require_once __DIR__ . '/../system/includes/init.php';

use System\Core\Database;
use System\Core\Logger;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$message = '';
$messageType = 'info';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// POST REQUEST HANDLING
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                // ========================================================
                // ADD TERM
                // ========================================================
                case 'add':
                    $termYearFk = (int)($_POST['term_year_fk'] ?? 0);
                    $termName = trim($_POST['term_name'] ?? '');
                    $startDate = trim($_POST['start_date'] ?? '');
                    $endDate = trim($_POST['end_date'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($termYearFk) || empty($termName)) {
                        $message = 'Term Year and Term Name are required.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    // Check for duplicate term name within term year
                    $checkStmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM tbl_terms 
                        WHERE term_year_fk = ? AND term_name = ?
                    ");
                    $checkStmt->bind_param('is', $termYearFk, $termName);
                    $checkStmt->execute();
                    $exists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
                    $checkStmt->close();
                    
                    if ($exists) {
                        $message = 'A term with this name already exists in this term year.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO tbl_terms 
                        (term_year_fk, term_name, start_date, end_date, is_active, created_by_fk, updated_by_fk) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param(
                        'issssii',
                        $termYearFk,
                        $termName,
                        $startDate ?: null,
                        $endDate ?: null,
                        $isActive,
                        $userId,
                        $userId
                    );
                    
                    if ($stmt->execute()) {
                        $message = 'Term added successfully.';
                        $messageType = 'success';
                        Logger::getInstance()->info('Term added', [
                            'term_id' => $db->insert_id,
                            'term_name' => $termName,
                            'user_id' => $userId
                        ]);
                    } else {
                        throw new Exception('Failed to add term: ' . $stmt->error);
                    }
                    $stmt->close();
                    break;
                
                // ========================================================
                // EDIT TERM
                // ========================================================
                case 'edit':
                    $termPk = (int)($_POST['term_pk'] ?? 0);
                    $termYearFk = (int)($_POST['term_year_fk'] ?? 0);
                    $termName = trim($_POST['term_name'] ?? '');
                    $startDate = trim($_POST['start_date'] ?? '');
                    $endDate = trim($_POST['end_date'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($termPk) || empty($termYearFk) || empty($termName)) {
                        $message = 'All required fields must be provided.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    // Check for duplicate term name within term year (excluding current)
                    $checkStmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM tbl_terms 
                        WHERE term_year_fk = ? AND term_name = ? AND terms_pk != ?
                    ");
                    $checkStmt->bind_param('isi', $termYearFk, $termName, $termPk);
                    $checkStmt->execute();
                    $exists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
                    $checkStmt->close();
                    
                    if ($exists) {
                        $message = 'A term with this name already exists in this term year.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE tbl_terms 
                        SET term_year_fk = ?, term_name = ?, start_date = ?, end_date = ?, 
                            is_active = ?, updated_by_fk = ?, updated_at = NOW()
                        WHERE terms_pk = ?
                    ");
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param(
                        'issssii',
                        $termYearFk,
                        $termName,
                        $startDate ?: null,
                        $endDate ?: null,
                        $isActive,
                        $userId,
                        $termPk
                    );
                    
                    if ($stmt->execute()) {
                        $message = 'Term updated successfully.';
                        $messageType = 'success';
                        Logger::getInstance()->info('Term updated', [
                            'term_id' => $termPk,
                            'term_name' => $termName,
                            'user_id' => $userId
                        ]);
                    } else {
                        throw new Exception('Failed to update term: ' . $stmt->error);
                    }
                    $stmt->close();
                    break;
                
                // ========================================================
                // TOGGLE ACTIVE STATUS
                // ========================================================
                case 'toggle_status':
                    $termPk = (int)($_POST['term_pk'] ?? 0);
                    
                    if (empty($termPk)) {
                        $message = 'Invalid term ID.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE tbl_terms 
                        SET is_active = NOT is_active, updated_by_fk = ?, updated_at = NOW()
                        WHERE terms_pk = ?
                    ");
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param('ii', $userId, $termPk);
                    
                    if ($stmt->execute()) {
                        $message = 'Term status updated successfully.';
                        $messageType = 'success';
                    } else {
                        throw new Exception('Failed to update term status: ' . $stmt->error);
                    }
                    $stmt->close();
                    break;
                
                // ========================================================
                // DELETE TERM
                // ========================================================
                case 'delete':
                    $termPk = (int)($_POST['term_pk'] ?? 0);
                    
                    if (empty($termPk)) {
                        $message = 'Invalid term ID.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    // Check if term has course sections
                    $checkStmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM tbl_course_sections 
                        WHERE term_fk = ?
                    ");
                    $checkStmt->bind_param('i', $termPk);
                    $checkStmt->execute();
                    $sectionsCount = $checkStmt->get_result()->fetch_assoc()['count'];
                    $checkStmt->close();
                    
                    if ($sectionsCount > 0) {
                        $message = "Cannot delete term: {$sectionsCount} course section(s) are assigned to this term.";
                        $messageType = 'warning';
                        break;
                    }
                    
                    $stmt = $db->prepare("DELETE FROM tbl_terms WHERE terms_pk = ?");
                    $stmt->bind_param('i', $termPk);
                    
                    if ($stmt->execute()) {
                        $message = 'Term deleted successfully.';
                        $messageType = 'success';
                        Logger::getInstance()->info('Term deleted', [
                            'term_id' => $termPk,
                            'user_id' => $_SESSION['user_id']
                        ]);
                    } else {
                        throw new Exception('Failed to delete term: ' . $stmt->error);
                    }
                    $stmt->close();
                    break;
                    
                default:
                    $message = 'Invalid action.';
                    $messageType = 'danger';
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
            Logger::getInstance()->error('Terms management error', [
                'error' => $e->getMessage(),
                'action' => $action,
                'user_id' => $_SESSION['user_id']
            ]);
        }
    }
    
    // Redirect to prevent form resubmission
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $messageType;
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Get flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// ============================================================================
// GET TERM YEARS FOR DROPDOWN
// ============================================================================

$termYearsResult = $db->query("
    SELECT term_years_pk, term_name 
    FROM tbl_term_years 
    WHERE is_active = 1 
    ORDER BY term_name DESC
");
$termYears = [];
while ($row = $termYearsResult->fetch_assoc()) {
    $termYears[] = $row;
}

// ============================================================================
// PAGE DISPLAY
// ============================================================================

$pageTitle = 'Terms Management';
$currentPage = 'admin_terms';
require_once __DIR__ . '/../system/includes/header.php';
?>

<!-- Include AdminLTE Sidebar -->
<?php require_once __DIR__ . '/../system/includes/sidebar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="bi bi-calendar-week me-2"></i>Terms Management</h1>
                </div>
                <div class="col-sm-6">
                    <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addTermModal">
                        <i class="bi bi-plus-circle me-1"></i> Add Term
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Academic Terms</h3>
                    <div class="card-tools">
                        <span class="badge bg-info">Organize Course Sections (CRNs)</span>
                    </div>
                </div>
                <div class="card-body">
                    <table id="termsTable" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Term Year</th>
                                <th>Term Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Sections</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </section>
</div>

<!-- ADD TERM MODAL -->
<div class="modal fade" id="addTermModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_term_year_fk" class="form-label">Term Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_term_year_fk" name="term_year_fk" required>
                            <option value="">-- Select Term Year --</option>
                            <?php foreach ($termYears as $ty): ?>
                                <option value="<?= $ty['term_years_pk'] ?>"><?= htmlspecialchars($ty['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_term_name" class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_term_name" name="term_name" 
                               placeholder="e.g., Fall 2025, Spring 2026, Summer 2026" required>
                        <div class="form-text">Examples: Fall 2025, Spring 2026, Summer 2026</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="add_start_date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="add_end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="add_is_active" name="is_active" checked>
                        <label class="form-check-label" for="add_is_active">Active</label>
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

<!-- EDIT TERM MODAL -->
<div class="modal fade" id="editTermModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="term_pk" id="edit_term_pk">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_term_year_fk" class="form-label">Term Year <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_term_year_fk" name="term_year_fk" required>
                            <option value="">-- Select Term Year --</option>
                            <?php foreach ($termYears as $ty): ?>
                                <option value="<?= $ty['term_years_pk'] ?>"><?= htmlspecialchars($ty['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_term_name" class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_term_name" name="term_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
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

<?php require_once __DIR__ . '/../system/includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#termsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/terms_data.php',
        columns: [
            { data: 'term_year_name' },
            { data: 'term_name' },
            { data: 'start_date' },
            { data: 'end_date' },
            { data: 'sections_count' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc'], [1, 'asc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
    
    // Edit term
    $('#termsTable').on('click', '.btn-edit', function() {
        const termId = $(this).data('id');
        
        $.ajax({
            url: '<?= BASE_URL ?>administration/terms_data.php',
            type: 'GET',
            data: { action: 'get_term', id: termId },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#edit_term_pk').val(data.term.terms_pk);
                    $('#edit_term_year_fk').val(data.term.term_year_fk);
                    $('#edit_term_name').val(data.term.term_name);
                    $('#edit_start_date').val(data.term.start_date || '');
                    $('#edit_end_date').val(data.term.end_date || '');
                    $('#edit_is_active').prop('checked', data.term.is_active == 1);
                    $('#editTermModal').modal('show');
                } else {
                    alert('Error loading term data: ' + (data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error loading term data.');
            }
        });
    });
    
    // Toggle status
    $('#termsTable').on('click', '.btn-toggle', function() {
        if (!confirm('Toggle this term status?')) return;
        
        const termId = $(this).data('id');
        const form = $('<form method="POST"></form>');
        form.append('<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">');
        form.append('<input type="hidden" name="action" value="toggle_status">');
        form.append('<input type="hidden" name="term_pk" value="' + termId + '">');
        $('body').append(form);
        form.submit();
    });
    
    // Delete term
    $('#termsTable').on('click', '.btn-delete', function() {
        if (!confirm('Are you sure you want to delete this term? This cannot be undone.')) return;
        
        const termId = $(this).data('id');
        const form = $('<form method="POST"></form>');
        form.append('<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">');
        form.append('<input type="hidden" name="action" value="delete">');
        form.append('<input type="hidden" name="term_pk" value="' + termId + '">');
        $('body').append(form);
        form.submit();
    });
});
</script>
