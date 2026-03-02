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

// Define searchable columns
$searchableColumns = [
    'po.program_outcomes_pk',
    'p.program_name',
    'po.outcome_code',
    'po.outcome_description',
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
    'u_created.full_name',
    'po.updated_at',
    'u_updated.full_name',
    'actions' // Not sortable
];

// Get order column name
$orderColumn = $columns[$params['orderColumn']] ?? 'po.sequence_num';
if ($orderColumn === 'actions') {
    $orderColumn = 'po.sequence_num';
}

// Build WHERE clause
$whereParams = [];
$whereTypes = '';
$whereConditions = [];

// Global search
if (!empty($params['search'])) {
    $searchClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
    if (!empty($searchClause)) {
        $whereConditions[] = $searchClause;
    }
}

// Add column-specific searches with special handling for is_active
foreach ($params['columnSearches'] as $columnIndex => $searchValue) {
    if (!isset($columns[$columnIndex]) || empty($searchValue)) {
        continue;
    }
    
    $columnName = $columns[$columnIndex];
    
    // Special handling for is_active column
    if ($columnName === 'po.is_active') {
        // Check if searching for inactive (starts with 'in' or equals 'inactive')
        if (stripos($searchValue, 'inact') === 0 || strtolower($searchValue) === 'inactive') {
            $activeValue = 0;
        } else {
            $activeValue = 1;
        }
        $whereConditions[] = "po.is_active = ?";
        $whereParams[] = $activeValue;
        $whereTypes .= 'i';
    } elseif ($columnName !== 'actions') {
        $whereConditions[] = "{$columnName} LIKE ?";
        $whereParams[] = "%{$searchValue}%";
        $whereTypes .= 's';
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

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
        po.updated_at,
        po.created_by_fk,
        po.updated_by_fk,
        u_created.full_name as created_by_name,
        u_updated.full_name as updated_by_name
    FROM {$dbPrefix}program_outcomes po
    JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
    LEFT JOIN {$dbPrefix}institutional_outcomes io ON po.institutional_outcomes_fk = io.institutional_outcomes_pk
    LEFT JOIN {$dbPrefix}users u_created ON po.created_by_fk = u_created.users_pk
    LEFT JOIN {$dbPrefix}users u_updated ON po.updated_by_fk = u_updated.users_pk
    {$whereClause}
    ORDER BY {$orderColumn} {$params['orderDir']}
    LIMIT ? OFFSET ?
";

// Add limit params
$queryParams = $whereParams;
$queryParams[] = $params['length'];
$queryParams[] = $params['start'];
$queryTypes = $whereTypes . 'ii';

try {
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
            ? htmlspecialchars($row['institutional_outcome_code'])
            : '<span class="text-muted">N/A</span>';
        
        $data[] = [
            htmlspecialchars((string)$row['program_outcomes_pk']),
            htmlspecialchars($row['program_name']),
            htmlspecialchars($row['outcome_code']),
            htmlspecialchars($descriptionPreview),
            $institutionalOutcome,
            htmlspecialchars((string)$row['sequence_num']),
            $status,
            htmlspecialchars($row['created_at'] ?? ''),
            htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
            htmlspecialchars($row['updated_at'] ?? ''),
            htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
            '<button class="btn btn-warning" title="Edit" onclick=\'editOutcome(' . $rowJson . ')\' aria-label="Edit outcome ' . htmlspecialchars($row['outcome_code'], ENT_QUOTES) . '"><i class="fas fa-edit" aria-hidden="true"></i></button>'
    ];
    }

    // Output JSON response
    outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
} catch (\Exception $e) {
    error_log("Program Outcomes DataTables error: " . $e->getMessage());
    echo json_encode([
        'draw' => $params['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
