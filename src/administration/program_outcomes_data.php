<?php
declare(strict_types=1);

/**
 * Program Outcomes DataTables Server-Side Processing
 * 
 * Handles AJAX requests for program outcomes data with pagination, sorting, and filtering.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

// Get DataTables parameters
$params = getDataTablesParams();

// Get term filter
$termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : null;

// Define searchable columns
$searchableColumns = [
    'po.outcome_code',
    'po.outcome_description',
    'p.program_name',
    'p.program_code',
    'io.outcome_code'
];

// Column definitions for ordering (must match DataTables column order)
$columns = [
    'po.program_outcomes_pk',
    'p.program_name',
    'po.outcome_code',
    'po.outcome_description',
    'io.outcome_code',
    'po.sequence_num',
    'po.is_active',
    'po.created_at',
    'actions' // Not sortable, placeholder
];

// Get order column name
$orderColumn = $columns[$params['orderColumn']] ?? 'po.sequence_num';
if ($orderColumn === 'actions') {
    $orderColumn = 'po.sequence_num';
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

if (!empty($whereClause)) {
    $whereClause = "WHERE {$whereClause}";
}

// Get total records (without filtering)
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}program_outcomes");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}program_outcomes po
    JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
    LEFT JOIN {$dbPrefix}institutional_outcomes io ON po.institutional_outcomes_fk = io.institutional_outcomes_pk
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
        po.program_outcomes_pk,
        po.program_fk,
        p.program_name,
        p.program_code,
        po.outcome_code,
        po.outcome_description,
        po.institutional_outcomes_fk,
        io.outcome_code as institutional_outcome_code,
        io.outcome_description as institutional_outcome_description,
        po.sequence_num,
        po.is_active,
        po.created_at,
        po.updated_at
    FROM {$dbPrefix}program_outcomes po
    JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
    LEFT JOIN {$dbPrefix}institutional_outcomes io ON po.institutional_outcomes_fk = io.institutional_outcomes_pk
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
    
    // Format institutional outcome display
    $institutionalOutcome = $row['institutional_outcome_code'] 
        ? '<span class="badge bg-secondary">' . htmlspecialchars($row['institutional_outcome_code']) . '</span>'
        : '<span class="text-muted">N/A</span>';
    
    $data[] = [
        htmlspecialchars($row['program_outcomes_pk']),
        htmlspecialchars($row['program_name']),
        '<span class="badge bg-primary">' . htmlspecialchars($row['outcome_code']) . '</span>',
        htmlspecialchars($descriptionPreview),
        $institutionalOutcome,
        htmlspecialchars($row['sequence_num']),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        htmlspecialchars($row['created_at'] ?? ''),
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewOutcome(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editOutcome(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['program_outcomes_pk'] . ', \'' . htmlspecialchars($row['outcome_code'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteOutcome(' . $row['program_outcomes_pk'] . ', \'' . htmlspecialchars($row['outcome_code'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

// Output JSON response
outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
