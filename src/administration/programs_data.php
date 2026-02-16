<?php
declare(strict_types=1);

/**
 * Programs DataTables Server-Side Processing
 * 
 * Handles AJAX requests for program data with pagination, sorting, and filtering.
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
    'p.programs_pk',
    'p.program_code',
    'p.program_name',
    'd.department_name',
    'p.degree_type',
    'p.is_active',
    'p.created_at',
    'actions' // Not sortable, placeholder
];

// Validate order direction
$orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

// Get order column name (prevent SQL injection)
$orderColumn = $columns[$orderColumnIndex] ?? 'p.program_name';
if ($orderColumn === 'actions') {
    $orderColumn = 'p.program_name'; // Default for actions column
}

// Build WHERE clause for global search
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchValue)) {
    $searchConditions = [
        'p.programs_pk LIKE ?',
        'p.program_code LIKE ?',
        'p.program_name LIKE ?',
        'd.department_name LIKE ?',
        'p.degree_type LIKE ?'
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
        if ($columns[$i] === 'p.is_active') {
            // Handle Status column specially
            if (stripos($columnSearch, 'active') !== false && stripos($columnSearch, 'inactive') === false) {
                $whereConditions[] = 'p.is_active = 1';
            } elseif (stripos($columnSearch, 'inactive') !== false) {
                $whereConditions[] = 'p.is_active = 0';
            }
        } else {
            $whereConditions[] = "{$columns[$i]} LIKE ?";
            $params[] = "%{$columnSearch}%";
            $types .= 's';
        }
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}programs");
$totalRow = $totalResult->fetch_assoc();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}programs p
    LEFT JOIN {$dbPrefix}departments d ON p.department_fk = d.departments_pk
    {$whereClause}
";
if (!empty($params)) {
    $filteredResult = $db->query($countQuery, $params, $types);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch_assoc();
$recordsFiltered = $filteredRow['total'];

// Get data
$dataQuery = "
    SELECT 
        p.*,
        d.department_name,
        d.department_code
    FROM {$dbPrefix}programs p
    LEFT JOIN {$dbPrefix}departments d ON p.department_fk = d.departments_pk
    {$whereClause}
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT ? OFFSET ?
";

// Add limit params
$params[] = $length;
$params[] = $start;
$types .= 'ii';

$result = $db->query($dataQuery, $params, $types);
$programs = $result->fetch_all(MYSQLI_ASSOC);

// Format data for DataTables
$data = [];
foreach ($programs as $row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusClass = $row['is_active'] ? 'success' : 'secondary';
    $toggleIcon = $row['is_active'] ? 'ban' : 'check';
    $toggleClass = $row['is_active'] ? 'warning' : 'success';
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        htmlspecialchars($row['programs_pk']),
        '<span class="badge bg-primary">' . htmlspecialchars($row['program_code']) . '</span>',
        htmlspecialchars($row['program_name']),
        htmlspecialchars($row['department_name'] ?? 'N/A'),
        htmlspecialchars($row['degree_type'] ?? ''),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        htmlspecialchars($row['created_at'] ?? ''),
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewProgram(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editProgram(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['programs_pk'] . ', \'' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteProgram(' . $row['programs_pk'] . ', \'' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

// Return JSON response
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
]);
