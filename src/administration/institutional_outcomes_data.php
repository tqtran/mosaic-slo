<?php
declare(strict_types=1);

/**
 * Institutional Outcomes DataTables Server-Side Processing
 * 
 * Handles AJAX requests for institutional outcomes data with pagination, sorting, and filtering.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

header('Content-Type: application/json');

try {
    // Get DataTables parameters
    $params = getDataTablesParams();

// Define searchable columns
$searchableColumns = [
    'io.outcome_code',
    'io.outcome_description'
];

// Column definitions for ordering (must match DataTables column order)
$columns = [
    'io.institutional_outcomes_pk',
    'io.outcome_code',
    'io.outcome_description',
    'io.sequence_num',
    'io.is_active',
    'io.created_at',
    'actions' // Not sortable, placeholder
];

// Get order column name
$orderColumn = $columns[$params['orderColumn']] ?? 'io.sequence_num';
if ($orderColumn === 'actions') {
    $orderColumn = 'io.sequence_num';
}

// Build WHERE clause
$whereParams = [];
$whereTypes = '';
$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
if (!empty($whereClause)) {
    $whereClause = "WHERE {$whereClause}";
}

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}institutional_outcomes");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}institutional_outcomes io
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
        io.institutional_outcomes_pk,
        io.outcome_code,
        io.outcome_description,
        io.sequence_num,
        io.is_active,
        io.created_at,
        io.updated_at
    FROM {$dbPrefix}institutional_outcomes io
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
$outcomes = $result->fetchAll();

// Format data for DataTables
$data = [];
foreach ($outcomes as $row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusClass = $row['is_active'] ? 'success' : 'secondary';
    $toggleIcon = $row['is_active'] ? 'ban' : 'check';
    $toggleClass = $row['is_active'] ? 'warning' : 'success';
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    // Truncate description for display
    $descriptionPreview = strlen($row['outcome_description']) > 100 
        ? substr($row['outcome_description'], 0, 100) . '...' 
        : $row['outcome_description'];
    
    $data[] = [
        htmlspecialchars($row['institutional_outcomes_pk']),
        '<span class="badge bg-primary">' . htmlspecialchars($row['outcome_code']) . '</span>',
        htmlspecialchars($descriptionPreview),
        htmlspecialchars($row['sequence_num']),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        htmlspecialchars($row['created_at'] ?? ''),
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewOutcome(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editOutcome(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['institutional_outcomes_pk'] . ', \'' . htmlspecialchars($row['outcome_code'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteOutcome(' . $row['institutional_outcomes_pk'] . ', \'' . htmlspecialchars($row['outcome_code'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

    // Output JSON response
    outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
} catch (\Exception $e) {
    error_log("Institutional Outcomes DataTables error: " . $e->getMessage());
    echo json_encode([
        'draw' => $params['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
