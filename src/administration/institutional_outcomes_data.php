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
    'io.outcome_description',
    't.term_code',
    't.term_name'
];

// Column definitions for ordering (must match DataTables column order)
$columns = [
    'io.institutional_outcomes_pk',
    't.term_code',
    'io.outcome_code',
    'io.outcome_description',
    'io.sequence_num',
    'io.is_active',
    'io.created_at',
    'created_by',
    'io.updated_at',
    'updated_by',
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

// Add column-specific searches
$columnConditions = buildColumnSearchWhere($params['columnSearches'], $columns, $whereParams, $whereTypes);
if (!empty($columnConditions)) {
    if (!empty($whereClause)) {
        $whereClause .= ' AND ' . implode(' AND ', $columnConditions);
    } else {
        $whereClause = implode(' AND ', $columnConditions);
    }
}

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
    LEFT JOIN {$dbPrefix}terms t ON io.term_fk = t.terms_pk
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
        io.term_fk,
        io.outcome_code,
        io.outcome_description,
        io.sequence_num,
        io.is_active,
        io.created_at,
        io.updated_at,
        io.created_by_fk,
        io.updated_by_fk,
        t.term_code,
        t.term_name,
        u_created.full_name as created_by_name,
        u_updated.full_name as updated_by_name
    FROM {$dbPrefix}institutional_outcomes io
    LEFT JOIN {$dbPrefix}terms t ON io.term_fk = t.terms_pk
    LEFT JOIN {$dbPrefix}users u_created ON io.created_by_fk = u_created.users_pk
    LEFT JOIN {$dbPrefix}users u_updated ON io.updated_by_fk = u_updated.users_pk
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
        htmlspecialchars((string)$row['institutional_outcomes_pk']),
        htmlspecialchars($row['term_name'] ?? 'N/A'),
        htmlspecialchars($row['outcome_code']),
        htmlspecialchars($descriptionPreview),
        htmlspecialchars((string)$row['sequence_num']),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        htmlspecialchars($row['created_at'] ?? ''),
        htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
        htmlspecialchars($row['updated_at'] ?? ''),
        htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
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
