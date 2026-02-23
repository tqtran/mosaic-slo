<?php
declare(strict_types=1);

/**
 * Term Years DataTables Server-Side Processing
 * 
 * Handles AJAX requests for term years data with pagination, sorting, and filtering.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

// Get DataTables parameters
$params = getDataTablesParams();

// Define searchable columns
$searchableColumns = [
    'ty.term_name',
    'ty.start_date',
    'ty.end_date'
];

// Column definitions for ordering (must match DataTables column order)
$columns = [
    'ty.term_years_pk',
    'ty.term_name',
    'ty.start_date',
    'ty.end_date',
    'program_count',
    'ty.is_active',
    'ty.is_current',
    'ty.created_at',
    'actions' // Not sortable, placeholder
];

// Get order column name
$orderColumn = $columns[$params['orderColumn']] ?? 'ty.start_date';
if ($orderColumn === 'actions') {
    $orderColumn = 'ty.start_date';
}

// Build WHERE clause
$whereParams = [];
$whereTypes = '';
$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
if (!empty($whereClause)) {
    $whereClause = "WHERE {$whereClause}";
}

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}term_years");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}term_years ty
    {$whereClause}
";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

// Get data
$dataQuery = "
    SELECT 
        ty.term_years_pk,
        ty.term_name,
        ty.start_date,
        ty.end_date,
        ty.is_active,
        ty.is_current,
        ty.created_at,
        ty.updated_at,
        (SELECT COUNT(*) FROM {$dbPrefix}programs WHERE term_year_fk = ty.term_years_pk) as program_count
    FROM {$dbPrefix}term_years ty
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
$terms = $result->fetchAll();

// Format data for DataTables
$data = [];
foreach ($terms as $row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusClass = $row['is_active'] ? 'success' : 'secondary';
    $toggleIcon = $row['is_active'] ? 'ban' : 'check';
    $toggleClass = $row['is_active'] ? 'warning' : 'success';
    
    $current = $row['is_current'] ? 'Yes' : 'No';
    $currentClass = $row['is_current'] ? 'primary' : 'secondary';
    
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        htmlspecialchars($row['term_years_pk']),
        '<strong>' . htmlspecialchars($row['term_name']) . '</strong>',
        htmlspecialchars($row['start_date'] ?? 'N/A'),
        htmlspecialchars($row['end_date'] ?? 'N/A'),
        '<span class="badge bg-info">' . htmlspecialchars($row['program_count']) . '</span>',
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        '<span class="badge bg-' . $currentClass . '">' . $current . '</span>',
        htmlspecialchars($row['created_at'] ?? ''),
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewTerm(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editTerm(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['term_years_pk'] . ', \'' . htmlspecialchars($row['term_name'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteTerm(' . $row['term_years_pk'] . ', \'' . htmlspecialchars($row['term_name'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

// Output JSON response
outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
