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

// Define searchable columns
$searchableColumns = [
    'p.programs_pk',
    't.term_code',
    'p.program_code',
    'p.program_name',
    'p.degree_type'
];

// Column definitions for ordering (must match DataTables column order)
$columns = [
    'p.programs_pk',
    't.term_code',
    'p.program_code',
    'p.program_name',
    'p.degree_type',
    'p.is_active',
    'p.created_at',
    'u_created.full_name',
    'p.updated_at',
    'u_updated.full_name',
    'actions' // Not sortable
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
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}programs");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

// Get filtered records count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}programs p
    LEFT JOIN {$dbPrefix}terms t ON p.term_fk = t.terms_pk
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
        p.programs_pk,
        p.term_fk,
        p.program_code,
        p.program_name,
        p.degree_type,
        p.is_active,
        p.created_at,
        p.updated_at,
        p.created_by_fk,
        p.updated_by_fk,
        t.term_code,
        t.term_name,
        u_created.full_name as created_by_name,
        u_updated.full_name as updated_by_name
    FROM {$dbPrefix}programs p
    LEFT JOIN {$dbPrefix}terms t ON p.term_fk = t.terms_pk
    LEFT JOIN {$dbPrefix}users u_created ON p.created_by_fk = u_created.users_pk
    LEFT JOIN {$dbPrefix}users u_updated ON p.updated_by_fk = u_updated.users_pk
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
            htmlspecialchars((string)$row['programs_pk']),
            '<span class="badge bg-info">' . htmlspecialchars($row['term_code'] ?? 'N/A') . '</span>',
            '<span class="badge bg-primary">' . htmlspecialchars($row['program_code']) . '</span>',
            htmlspecialchars($row['program_name']),
            htmlspecialchars($row['degree_type'] ?? ''),
            '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
            htmlspecialchars($row['created_at'] ?? ''),
            htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
            htmlspecialchars($row['updated_at'] ?? ''),
            htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
            '<button class="btn btn-warning" title="Edit" onclick=\'editProgram(' . $rowJson . ')\' aria-label="Edit program ' . htmlspecialchars($row['program_name'], ENT_QUOTES) . '"><i class="fas fa-edit" aria-hidden="true"></i></button>'
        ];
    }

    // Output JSON response
    outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
} catch (\Exception $e) {
    error_log("Programs DataTables error: " . $e->getMessage());
    echo json_encode([
        'draw' => $params['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
