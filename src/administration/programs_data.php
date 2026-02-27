<?php
declare(strict_types=1);

/**
 * Programs DataTables Server-Side Processing
 * 
 * Handles AJAX requests for program data with pagination, sorting, and filtering.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

// Get DataTables parameters
$params = getDataTablesParams();

// Get filters
$termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : null;
$degreeTypeFilter = isset($_GET['degree_type']) ? trim($_GET['degree_type']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Define searchable columns
$searchableColumns = [
    'p.program_name',
    'p.degree_type'
];

// Column definitions for ordering (must match DataTables column order)
$columns = [
    'p.program_name',
    'p.degree_type',
    'p.is_active',
    'actions' // Not sortable, placeholder
];

// Get order column name
$orderColumn = $columns[$params['orderColumn']] ?? 'p.program_name';
if ($orderColumn === 'actions') {
    $orderColumn = 'p.program_name';
}

// Build WHERE clause
$whereParams = [];
$whereTypes = '';
$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);

// Add term filter if specified
if ($termFk !== null && $termFk > 0) {
    if (!empty($whereClause)) {
        $whereClause .= ' AND p.term_fk = ?';
    } else {
        $whereClause = 'p.term_fk = ?';
    }
    $whereParams[] = $termFk;
    $whereTypes .= 'i';
}

// Add degree type filter
if (!empty($degreeTypeFilter)) {
    if (!empty($whereClause)) {
        $whereClause .= ' AND p.degree_type = ?';
    } else {
        $whereClause = 'p.degree_type = ?';
    }
    $whereParams[] = $degreeTypeFilter;
    $whereTypes .= 's';
}

// Add status filter
if ($statusFilter !== '') {
    if (!empty($whereClause)) {
        $whereClause .= ' AND p.is_active = ?';
    } else {
        $whereClause = 'p.is_active = ?';
    }
    $whereParams[] = (int)$statusFilter;
    $whereTypes .= 'i';
}

if (!empty($whereClause)) {
    $whereClause = "WHERE {$whereClause}";
}

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}programs");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "SELECT COUNT(*) as total FROM {$dbPrefix}programs p {$whereClause}";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

// Get data
$dataQuery = "
    SELECT p.*
    FROM {$dbPrefix}programs p
    {$whereClause}
    ORDER BY {$orderColumn} {$params['orderDir']}
    LIMIT ? OFFSET ?
";

// Add limit params
$queryParams = $whereParams;
$queryParams[] = $params['length'];
$queryParams[] = $params['start'];
$queryTypes = $whereTypes . 'ii';

$result = $db->query($dataQuery, $queryParams, $queryTypes);
$programs = $result->fetchAll();

// Format data for DataTables
$data = [];
foreach ($programs as $row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusClass = $row['is_active'] ? 'success' : 'secondary';
    $toggleIcon = $row['is_active'] ? 'ban' : 'check';
    $toggleClass = $row['is_active'] ? 'warning' : 'success';
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        htmlspecialchars($row['program_name']),
        htmlspecialchars($row['degree_type'] ?? ''),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewProgram(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editProgram(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['programs_pk'] . ', \'' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteProgram(' . $row['programs_pk'] . ', \'' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

// Output JSON response
outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
