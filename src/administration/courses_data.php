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
    'c.course_name', 
    'c.course_number'
];

$columns = [
    'c.course_number',
    'c.course_name',
    'c.is_active',
    'actions'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'c.course_number';
if ($orderColumn === 'actions') {
    $orderColumn = 'c.course_number';
}

$whereParams = [];
$whereTypes = '';

// Term filter
$whereConditions = [];
$termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : null;
if ($termFk) {
    $whereConditions[] = 'c.term_fk = ?';
    $whereParams[] = $termFk;
    $whereTypes .= 'i';
}

// Status filter
$status = isset($_GET['status']) ? $_GET['status'] : null;
if ($status !== null && $status !== '') {
    $whereConditions[] = 'c.is_active = ?';
    $whereParams[] = (int)$status;
    $whereTypes .= 'i';
}

$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
if (!empty($whereClause)) {
    $whereConditions[] = $whereClause;
}

$finalWhereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : '';

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}courses");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

$countQuery = "SELECT COUNT(*) as total FROM {$dbPrefix}courses c {$finalWhereClause}";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

$dataQuery = "
    SELECT c.*, t.term_name, t.term_code
    FROM {$dbPrefix}courses c
    LEFT JOIN {$dbPrefix}terms t ON c.term_fk = t.terms_pk
    {$finalWhereClause}
    ORDER BY {$orderColumn} {$params['orderDir']}
    LIMIT ? OFFSET ?
";

$queryParams = $whereParams;
$queryParams[] = $params['length'];
$queryParams[] = $params['start'];
$queryTypes = $whereTypes . 'ii';

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
        '<span class="badge bg-primary">' . htmlspecialchars($row['course_number']) . '</span>',
        htmlspecialchars($row['course_name']),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        '<button class="btn btn-sm btn-info" title="View" onclick=\'viewCourse(' . $rowJson . ')\'><i class="fas fa-eye"></i></button> ' .
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editCourse(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['courses_pk'] . ', \'' . htmlspecialchars($row['course_name'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteCourse(' . $row['courses_pk'] . ', \'' . htmlspecialchars($row['course_name'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
