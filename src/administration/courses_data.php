<?php
declare(strict_types=1);

/**
 * Courses DataTables Server-Side Processing
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

$params = getDataTablesParams();

$searchableColumns = [
    'c.courses_pk',
    't.term_code',
    'c.course_number', 
    'c.course_name'
];

$columns = [
    'c.courses_pk',
    't.term_code',
    'c.course_number',
    'c.course_name',
    'c.is_active',
    'c.created_at',
    'u_created.full_name',
    'c.updated_at',
    'u_updated.full_name',
    'actions'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'c.course_number';
if ($orderColumn === 'actions') {
    $orderColumn = 'c.course_number';
}

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

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}courses");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

$countQuery = "
    SELECT COUNT(*) as total 
    FROM {$dbPrefix}courses c
    LEFT JOIN {$dbPrefix}terms t ON c.term_fk = t.terms_pk
    {$whereClause}
";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

$dataQuery = "
    SELECT 
        c.courses_pk,
        c.term_fk,
        c.course_number,
        c.course_name,
        c.is_active,
        c.created_at,
        c.updated_at,
        c.created_by_fk,
        c.updated_by_fk,
        t.term_name,
        t.term_code,
        u_created.full_name as created_by_name,
        u_updated.full_name as updated_by_name
    FROM {$dbPrefix}courses c
    LEFT JOIN {$dbPrefix}terms t ON c.term_fk = t.terms_pk
    LEFT JOIN {$dbPrefix}users u_created ON c.created_by_fk = u_created.users_pk
    LEFT JOIN {$dbPrefix}users u_updated ON c.updated_by_fk = u_updated.users_pk
    {$whereClause}
    ORDER BY {$orderColumn} {$params['orderDir']}
    LIMIT ? OFFSET ?
";

$queryParams = $whereParams;
$queryParams[] = $params['length'];
$queryParams[] = $params['start'];
$queryTypes = $whereTypes . 'ii';

try {
    $result = $db->query($dataQuery, $queryParams, $queryTypes);
    $courses = $result->fetchAll();

    $data = [];
    foreach ($courses as $row) {
        $status = $row['is_active'] ? 'Active' : 'Inactive';
        $statusClass = $row['is_active'] ? 'success' : 'secondary';
        $toggleIcon = $row['is_active'] ? 'ban' : 'check';
        $toggleClass = $row['is_active'] ? 'warning' : 'success';
        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        
        $data[] = [
            htmlspecialchars((string)$row['courses_pk']),
            '<span class="badge bg-info">' . htmlspecialchars($row['term_code'] ?? 'N/A') . '</span>',
            '<span class="badge bg-primary">' . htmlspecialchars($row['course_number']) . '</span>',
            htmlspecialchars($row['course_name']),
            '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
            htmlspecialchars($row['created_at'] ?? ''),
            htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
            htmlspecialchars($row['updated_at'] ?? ''),
            htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
            '<button class="btn btn-sm btn-info" title="View" onclick=\'viewCourse(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
            '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editCourse(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
            '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['courses_pk'] . ', \'' . htmlspecialchars($row['course_name'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
            '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteCourse(' . $row['courses_pk'] . ', \'' . htmlspecialchars($row['course_name'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
        ];
    }

    outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
} catch (\Exception $e) {
    error_log("Courses DataTables error: " . $e->getMessage());
    echo json_encode([
        'draw' => $params['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
