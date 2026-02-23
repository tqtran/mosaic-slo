<?php
/**
 * Terms Data Endpoint
 * 
 * DataTables server-side processing endpoint for terms management.
 * Returns terms data with term year information and course section counts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system/includes/init.php';

use System\Core\Database;

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
        SELECT terms_pk, term_year_fk, term_name, start_date, end_date, is_active
        FROM tbl_terms
        WHERE terms_pk = ?
    ");
    $stmt->bind_param('i', $termId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'term' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Term not found']);
    }
    $stmt->close();
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
    0 => 'ty.term_name',
    1 => 't.term_name',
    2 => 't.start_date',
    3 => 't.end_date',
    4 => 'sections_count',
    5 => 't.is_active'
];

$orderBy = $columns[$orderColumn] ?? 'ty.term_name';

// Base query with term year join and sections count
$baseQuery = "
    FROM tbl_terms t
    LEFT JOIN tbl_term_years ty ON t.term_year_fk = ty.term_years_pk
    LEFT JOIN (
        SELECT term_fk, COUNT(*) as sections_count
        FROM tbl_course_sections
        GROUP BY term_fk
    ) cs ON t.terms_pk = cs.term_fk
";

// Search filter
$whereClause = '';
$searchParam = '';
if (!empty($searchValue)) {
    $whereClause = " WHERE (ty.term_name LIKE ? OR t.term_name LIKE ?)";
    $searchParam = '%' . $searchValue . '%';
}

// Total records
$totalQuery = "SELECT COUNT(*) as total FROM tbl_terms t";
$totalResult = $db->query($totalQuery);
$totalRecords = $totalResult->fetch_assoc()['total'];

// Filtered records
$filteredQuery = "SELECT COUNT(*) as total " . $baseQuery . $whereClause;
if (!empty($searchValue)) {
    $stmt = $db->prepare($filteredQuery);
    $stmt->bind_param('ss', $searchParam, $searchParam);
    $stmt->execute();
    $filteredRecords = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
} else {
    $filteredRecords = $totalRecords;
}

// Main query
$query = "
    SELECT 
        t.terms_pk,
        t.term_year_fk,
        ty.term_name as term_year_name,
        t.term_name,
        t.start_date,
        t.end_date,
        t.is_active,
        COALESCE(cs.sections_count, 0) as sections_count,
        t.created_at,
        t.updated_at
    " . $baseQuery . $whereClause . "
    ORDER BY " . $orderBy . " " . $orderDir . "
    LIMIT ? OFFSET ?
";

if (!empty($searchValue)) {
    $stmt = $db->prepare($query);
    $stmt->bind_param('ssii', $searchParam, $searchParam, $length, $start);
} else {
    $stmt = $db->prepare($query);
    $stmt->bind_param('ii', $length, $start);
}

$stmt->execute();
$result = $stmt->get_result();

// Format data
$data = [];
while ($row = $result->fetch_assoc()) {
    $statusBadge = $row['is_active'] 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';
    
    $sectionsCount = (int)$row['sections_count'];
    $sectionsBadge = $sectionsCount > 0 
        ? '<span class="badge bg-info">' . $sectionsCount . '</span>'
        : '<span class="badge bg-secondary">0</span>';
    
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
        'term_year_name' => htmlspecialchars($row['term_year_name'] ?? 'Unknown'),
        'term_name' => htmlspecialchars($row['term_name']),
        'start_date' => $startDate,
        'end_date' => $endDate,
        'sections_count' => $sectionsBadge,
        'status' => $statusBadge,
        'actions' => $actions
    ];
}

$stmt->close();

// Output DataTables JSON
header('Content-Type: application/json');
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
]);
