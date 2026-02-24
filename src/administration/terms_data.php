<?php
/**
 * Terms Data Endpoint
 * 
 * DataTables server-side processing endpoint for terms management.
 * Returns terms data with term year information and course section counts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system/includes/init.php';

use Mosaic\Core\Database;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();

// ============================================================================
// HANDLE GET TERM FOR EDITING
// ============================================================================

if (isset($_GET['action']) && $_GET['action'] === 'get_term') {
    $termId = (int)($_GET['id'] ?? 0);
    
    if (empty($termId)) {
        echo json_encode(['success' => false, 'message' => 'Invalid term ID']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT terms_pk, term_name, start_date, end_date, is_active
        FROM tbl_terms
        WHERE terms_pk = ?
    ");
    $stmt->execute([$termId]);
    
    if ($row = $stmt->fetch()) {
        echo json_encode(['success' => true, 'term' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Term not found']);
    }
    exit;
}

// ============================================================================
// DATATABLES SERVER-SIDE PROCESSING
// ============================================================================

// DataTables parameters
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? '';
$orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtoupper($_GET['order'][0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

// Column mapping
$columns = [
    0 => 't.term_name',
    1 => 't.start_date',
    2 => 't.end_date',
    3 => 't.is_active'
];

$orderBy = $columns[$orderColumn] ?? 't.term_name';

// Base query
$baseQuery = "
    FROM tbl_terms t
";

// Search filter
$whereClause = '';
$searchParam = '';
if (!empty($searchValue)) {
    $whereClause = " WHERE t.term_name LIKE ?";
    $searchParam = '%' . $searchValue . '%';
}

// Total records
$totalQuery = "SELECT COUNT(*) as total FROM tbl_terms t";
$totalRow = $db->query($totalQuery)->fetch();
$totalRecords = $totalRow['total'];

// Filtered records
$filteredQuery = "SELECT COUNT(*) as total " . $baseQuery . $whereClause;
if (!empty($searchValue)) {
    $stmt = $db->prepare($filteredQuery);
    $stmt->execute([$searchParam]);
    $filteredRow = $stmt->fetch();
    $filteredRecords = $filteredRow['total'];
} else {
    $filteredRecords = $totalRecords;
}

// Main query
$query = "
    SELECT 
        t.terms_pk,
        t.term_name,
        t.start_date,
        t.end_date,
        t.is_active,
        t.created_at,
        t.updated_at
    " . $baseQuery . $whereClause . "
    ORDER BY " . $orderBy . " " . $orderDir . "
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($query);
if (!empty($searchValue)) {
    $stmt->execute([$searchParam, $length, $start]);
} else {
    $stmt->execute([$length, $start]);
}

// Format data
$data = [];
while ($row = $stmt->fetch()) {
    $statusBadge = $row['is_active'] 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';
    
    $startDate = $row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : '-';
    $endDate = $row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : '-';
    
    $actions = '
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-info btn-edit" data-id="' . $row['terms_pk'] . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="btn btn-warning btn-toggle" data-id="' . $row['terms_pk'] . '" title="Toggle Status">
                <i class="bi bi-toggle-on"></i>
            </button>
            <button type="button" class="btn btn-danger btn-delete" data-id="' . $row['terms_pk'] . '" title="Delete">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    ';
    
    $data[] = [
        htmlspecialchars($row['terms_pk']),
        htmlspecialchars($row['term_name']),
        $startDate,
        $endDate,
        $statusBadge,
        $actions
    ];
}

// Output DataTables JSON
header('Content-Type: application/json');
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
]);
