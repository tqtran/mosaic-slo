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

// Column definitions (must match table columns)
$columns = [
    'programs_pk',
    'program_code',
    'program_name',
    'degree_type',
    'is_active',
    'created_at',
    'actions' // Not sortable, placeholder
];

// Get order column name
$orderColumn = $columns[$params['orderColumnIndex']] ?? 'program_name';
if ($orderColumn === 'actions') {
    $orderColumn = 'program_name';
}

// Build WHERE clause
list($whereClause, $whereParams, $whereTypes) = buildSearchWhere($params, $columns, 'programs');

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}programs");
$totalRow = $totalResult->fetch_assoc();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "SELECT COUNT(*) as total FROM {$dbPrefix}programs {$whereClause}";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch_assoc();
$recordsFiltered = $filteredRow['total'];

// Get data
$dataQuery = "
    SELECT *
    FROM {$dbPrefix}programs
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
        htmlspecialchars($row['degree_type'] ?? ''),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        htmlspecialchars($row['created_at'] ?? ''),
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewProgram(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editProgram(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['programs_pk'] . ', \'' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteProgram(' . $row['programs_pk'] . ', \'' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

// Output JSON response
outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
