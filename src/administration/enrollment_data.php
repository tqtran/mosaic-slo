<?php
declare(strict_types=1);

/**
 * Enrollment DataTables Server-Side Processing
 * 
 * Handles AJAX requests for enrollment data with pagination, sorting, and filtering.
 * 
 * @package Mosaic
 */

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database
require_once __DIR__ . '/../system/includes/init.php';

// DataTables parameters
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? '';
$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = $_GET['order'][0]['dir'] ?? 'asc';

// Column definitions (must match table columns)
$columns = [
    'e.enrollment_pk',
    'e.term_code',
    'e.crn',
    's.c_number',
    'e.enrollment_status',
    'e.enrollment_date',
    'e.updated_at',
    'actions' // Not sortable, placeholder
];

// Validate order direction
$orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

// Get order column name (prevent SQL injection)
$orderColumn = $columns[$orderColumnIndex] ?? 'e.updated_at';
if ($orderColumn === 'actions') {
    $orderColumn = 'e.updated_at'; // Default for actions column
}

// Build WHERE clause for global search
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchValue)) {
    $searchConditions = [
        'e.enrollment_pk LIKE ?',
        'e.term_code LIKE ?',
        'e.crn LIKE ?',
        's.c_number LIKE ?',
        'e.enrollment_status LIKE ?',
        'e.enrollment_date LIKE ?'
    ];
    $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    $searchParam = "%{$searchValue}%";
    for ($i = 0; $i < count($searchConditions); $i++) {
        $params[] = $searchParam;
        $types .= 's';
    }
}

// Add column-specific filters
for ($i = 0; $i < count($columns) - 1; $i++) { // Exclude actions column
    $columnSearch = $_GET['columns'][$i]['search']['value'] ?? '';
    if (!empty($columnSearch) && $columns[$i] !== 'actions') {
        $whereConditions[] = "{$columns[$i]} LIKE ?";
        $params[] = "%{$columnSearch}%";
        $types .= 's';
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}enrollment");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}enrollment e
    JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
    {$whereClause}
";
if (!empty($params)) {
    $filteredResult = $db->query($countQuery, $params, $types);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

// Get data
$dataQuery = "
    SELECT 
        e.*,
        s.c_number,
        s.first_name,
        s.last_name,
        s.email
    FROM {$dbPrefix}enrollment e
    JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
    {$whereClause}
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT ? OFFSET ?
";

// Add limit params
$params[] = $length;
$params[] = $start;
$types .= 'ii';

$result = $db->query($dataQuery, $params, $types);
$enrollments = $result->fetchAll();

// Format data for DataTables
$data = [];
foreach ($enrollments as $row) {
    // Determine status badge color
    $statusLower = strtolower($row['enrollment_status']);
    switch($statusLower) {
        case 'enrolled':
            $statusClass = 'success';
            break;
        case 'completed':
            $statusClass = 'primary';
            break;
        case 'dropped':
            $statusClass = 'warning';
            break;
        case 'withdrawn':
            $statusClass = 'danger';
            break;
        default:
            $statusClass = 'secondary';
    }
    
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        htmlspecialchars($row['enrollment_pk']),
        '<span class="badge bg-info">' . htmlspecialchars($row['term_code']) . '</span>',
        '<span class="badge bg-primary">' . htmlspecialchars($row['crn']) . '</span>',
        htmlspecialchars($row['c_number']),
        '<span class="badge bg-' . $statusClass . '">' . htmlspecialchars($row['enrollment_status']) . '</span>',
        htmlspecialchars($row['enrollment_date'] ?? ''),
        htmlspecialchars($row['updated_at'] ?? ''),
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewEnrollment(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editEnrollment(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteEnrollment(' . $row['enrollment_pk'] . ', \'' . htmlspecialchars($row['c_number'], ENT_QUOTES) . '\', \'' . htmlspecialchars($row['crn'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

// Return JSON response
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
]);
