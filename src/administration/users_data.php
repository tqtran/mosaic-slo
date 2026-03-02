<?php
declare(strict_types=1);

/**
 * Users DataTables AJAX Handler
 * 
 * Provides server-side processing for users table.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';
require_once __DIR__ . '/../system/includes/datatables_helper.php';

// Get DataTables request parameters
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? '';
$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = $_GET['order'][0]['dir'] ?? 'asc';

// Column mapping
$columns = ['users_pk', 'full_name', 'email', 'is_active'];
$orderColumn = $columns[$orderColumnIndex] ?? 'users_pk';

// Validate order direction
if (!in_array($orderDir, ['asc', 'desc'])) {
    $orderDir = 'asc';
}

// Get individual column search values
$columnSearches = [];
foreach ($columns as $i => $column) {
    $columnSearchValue = $_GET['columns'][$i]['search']['value'] ?? '';
    if ($columnSearchValue !== '') {
        $columnSearches[$column] = $columnSearchValue;
    }
}

// Base query
$baseQuery = "FROM {$dbPrefix}users u WHERE 1=1";
$params = [];
$types = '';

// Apply global search
if ($searchValue !== '') {
    $baseQuery .= " AND (
        u.full_name LIKE ? OR 
        u.email LIKE ?
    )";
    $searchParam = '%' . $searchValue . '%';
    $params = array_merge($params, [$searchParam, $searchParam]);
    $types .= 'ss';
}

// Apply column-specific filters
foreach ($columnSearches as $column => $value) {
    if ($column === 'is_active') {
        // Status filter - exact match
        if (strtolower($value) === 'active') {
            $baseQuery .= " AND u.is_active = 1";
        } elseif (strtolower($value) === 'inactive') {
            $baseQuery .= " AND u.is_active = 0";
        }
    } else {
        // Text filters - partial match
        $baseQuery .= " AND u.$column LIKE ?";
        $params[] = '%' . $value . '%';
        $types .= 's';
    }
}

// Get total count (before filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}users");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered count
$filteredResult = $db->query(
    "SELECT COUNT(*) as total $baseQuery",
    $params,
    $types
);
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

// Get paginated data with ordering
$dataQuery = "
    SELECT 
        u.users_pk,
        u.full_name,
        u.email,
        u.is_active,
        u.created_at,
        u.updated_at
    $baseQuery
    ORDER BY $orderColumn $orderDir
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$params[] = $length;
$params[] = $start;
$types .= 'ii';

$result = $db->query($dataQuery, $params, $types);

$data = [];
while ($row = $result->fetch()) {
    $statusBadge = $row['is_active'] == 1 ? 'Active' : 'Inactive';
    
    $fullName = htmlspecialchars($row['full_name']);
    
    // Never expose password_hash in output
    $userJson = json_encode([
        'users_pk' => $row['users_pk'],
        'full_name' => $row['full_name'],
        'email' => $row['email'],
        'is_active' => $row['is_active']
    ], JSON_HEX_APOS | JSON_HEX_QUOT);
    
   $actions = '
        <button class="btn btn-warning" onclick=\'editUser(' . $userJson . ')\' title="Edit" aria-label="Edit user ' . addslashes($fullName) . '">
            <i class="fas fa-edit" aria-hidden="true"></i>
        </button>
    ';
    
    $data[] = [
        htmlspecialchars((string)$row['users_pk']),
        htmlspecialchars($row['full_name']),
        htmlspecialchars($row['email']),
        $statusBadge,
        $actions
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
]);
