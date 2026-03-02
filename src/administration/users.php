<?php
declare(strict_types=1);

/**
 * Users Administration
 * 
 * Manage system users and their roles.
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
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if (empty($fullName)) {
                    $errors[] = 'Full name is required';
                }
                if (empty($email)) {
                    $errors[] = 'Email is required';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format';
                }
                if (empty($password)) {
                    $errors[] = 'Password is required';
                } elseif (strlen($password) < 8) {
                    $errors[] = 'Password must be at least 8 characters';
                }
                
                // Check email uniqueness
                if (!empty($email)) {
                    $checkResult = $db->query(
                        "SELECT users_pk FROM {$dbPrefix}users WHERE email = ?",
                        [$email],
                        's'
                    );
                    if ($checkResult->fetch()) {
                        $errors[] = 'Email already exists';
                    }
                }
                
                if (empty($errors)) {
                    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "INSERT INTO {$dbPrefix}users (full_name, email, password_hash, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                         VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                        [$fullName, $email, $passwordHash, $isActive, $userId, $userId],
                        'sssiii'
                    );
                    $successMessage = 'User added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['users_pk'] ?? 0);
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid user ID';
                }
                if (empty($fullName)) {
                    $errors[] = 'Full name is required';
                }
                if (empty($email)) {
                    $errors[] = 'Email is required';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format';
                }
                
                // Check email uniqueness (exclude current record)
                if (!empty($email)) {
                    $checkResult = $db->query(
                        "SELECT users_pk FROM {$dbPrefix}users WHERE email = ? AND users_pk != ?",
                        [$email, $id],
                        'si'
                    );
                    if ($checkResult->fetch()) {
                        $errors[] = 'Email already exists';
                    }
                }
                
                if (empty($errors)) {
                    // Update password only if provided
                    if (!empty($password)) {
                        if (strlen($password) < 8) {
                            $errorMessage = 'Password must be at least 8 characters';
                            break;
                        }
                        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
                        $userId = $_SESSION['user_id'] ?? null;
                        $db->query(
                            "UPDATE {$dbPrefix}users 
                             SET full_name = ?, email = ?, password_hash = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                             WHERE users_pk = ?",
                            [$fullName, $email, $passwordHash, $isActive, $userId, $id],
                            'sssiii'
                        );
                    } else {
                        $userId = $_SESSION['user_id'] ?? null;
                        $db->query(
                            "UPDATE {$dbPrefix}users 
                             SET full_name = ?, email = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                             WHERE users_pk = ?",
                            [$fullName, $email, $isActive, $userId, $id],
                            'ssiii'
                        );
                    }
                    $successMessage = 'User updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['users_pk'] ?? 0);
                if ($id > 0) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}users SET is_active = NOT is_active, updated_at = NOW(), updated_by_fk = ? WHERE users_pk = ?",
                        [$userId, $id],
                        'ii'
                    );
                    $successMessage = 'User status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['users_pk'] ?? 0);
                if ($id > 0) {
                    // Check if user has associated records
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}user_roles WHERE user_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete user: they have associated roles. Please remove roles first or deactivate the user instead.';
                    } else {
                        $db->query("DELETE FROM {$dbPrefix}users WHERE users_pk = ?", [$id], 'i');
                        $successMessage = 'User deleted successfully';
                    }
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM {$dbPrefix}users
");
$stats = $statsResult->fetch();
$totalUsers = $stats['total'];
$activeUsers = $stats['active'];
$inactiveUsers = $stats['inactive'];

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'User Management',
    'currentPage' => 'admin_users',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Users']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Users</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-table" aria-hidden="true"></i> System Users</h2>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal" aria-label="Add new user">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add User
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="usersTable" class="table table-bordered table-striped table-hover" aria-label="System users data table">
                    <caption class="visually-hidden">List of system users with filtering and sorting capabilities</caption>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <span class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus" aria-hidden="true"></i> Add User</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger" aria-label="required">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Full Name <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="fullName" name="full_name" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger" aria-label="required">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required aria-required="true" minlength="8" aria-describedby="passwordHelp">
                        <small id="passwordHelp" class="form-text text-muted">Minimum 8 characters</small>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="h6">Status</legend>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="users_pk" id="editUserPk">
                <div class="modal-header bg-warning text-dark">
                    <span class="modal-title" id="editUserModalLabel"><i class="fas fa-edit" aria-hidden="true"></i> Edit User</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close dialog"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email <span class="text-danger" aria-label="required">*</span></label>
                        <input type="email" class="form-control" id="editEmail" name="email" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="editFullName" class="form-label">Full Name <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="editFullName" name="full_name" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="editPassword" name="password" minlength="8" aria-describedby="editPasswordHelp">
                        <small id="editPasswordHelp" class="form-text text-muted">Leave blank to keep existing password. Minimum 8 characters if changing.</small>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="h6">Status</legend>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                            <label class="form-check-label" for="editIsActive">Active</label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <!-- LEFT SIDE: Destructive Actions -->
                    <div>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteUser()" aria-label="Delete user">
                            <i class="fas fa-trash" aria-hidden="true"></i> Delete
                        </button>
                    </div>
                    <!-- RIGHT SIDE: Primary Actions -->
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Form (hidden) -->
<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="users_pk" id="toggleUserPk">
</form>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="users_pk" id="deleteUserPk">
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
    // Setup - add a text input to each header cell (second row)
    $('#usersTable thead tr:eq(1) th').each(function(i) {
        var title = $('#usersTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title !== 'Actions' && title !== 'ID') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" aria-label="Filter by ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#usersTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/users_data.php',
        dom: 'Brtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'users_pk' },
            { data: 1, name: 'full_name' },
            { data: 2, name: 'email' },
            { data: 3, name: 'is_active' },
            { data: 4, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
            // Apply the search - target the second header row where filters are
            var api = this.api();
            api.columns().every(function(colIdx) {
                var column = this;
                // Find input in the second header row (tr:eq(1)) for this column
                $('input', $('#usersTable thead tr:eq(1) td').eq(colIdx)).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function editUser(user) {
    $('#editUserPk').val(user.users_pk);
    $('#editFullName').val(user.full_name);
    $('#editEmail').val(user.email);
    $('#editIsActive').prop('checked', user.is_active == 1);
    $('#editPassword').val(''); // Clear password field
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function toggleStatus(id, name) {
    if (confirm('Toggle status for user "' + name + '"?')) {
        $('#toggleUserPk').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteUser(id, name) {
    if (confirm('Are you sure you want to DELETE user "' + name + '"? This action cannot be undone.')) {
        $('#deleteUserPk').val(id);
        $('#deleteForm').submit();
    }
}

function confirmDeleteUser() {
    const userPk = $('#editUserPk').val();
    const fullName = $('#editFullName').val();
    deleteUser(userPk, fullName);
}
</script>
