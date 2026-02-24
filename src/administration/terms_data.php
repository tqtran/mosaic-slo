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
    0 => 't.terms_pk',
    1 => 't.term_code',
    2 => 't.term_name',
    3 => 't.start_date',
    4 => 't.end_date',
    5 => 't.is_active'
];

$orderBy = $columns[$orderColumn] ?? 't.term_code';

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
        t.term_code,
        t.term_name,
        t.start_date,
        t.end_date,
        t.is_active
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
    
    $data[] = [
        htmlspecialchars($row['terms_pk']),
        htmlspecialchars($row['term_code'] ?? ''),
        htmlspecialchars($row['term_name']),
        $startDate,
        $endDate,
        $statusBadge
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
