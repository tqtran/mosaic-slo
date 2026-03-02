<?php
declare(strict_types=1);

/**
 * Sections DataTables Server-Side Processing
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

$params = getDataTablesParams();

$searchableColumns = [
    's.crn',
    'c.course_number',
    'c.course_name',
    's.section_id',
    's.instructor_name'
];

$columns = [
    's.crn',
    'c.course_number',
    's.section_id',
    's.instructor_name',
    's.max_enrollment',
    's.is_active',
    'actions'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'c.course_number';
if ($orderColumn === 'actions') {
    $orderColumn = 'c.course_number';
}

$whereParams = [];
$whereTypes = '';

// Filters
$whereConditions = [];

// Term filter
$termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : null;
if ($termFk) {
    $whereConditions[] = 's.term_fk = ?';
    $whereParams[] = $termFk;
    $whereTypes .= 'i';
}

// Course filter
$courseFk = isset($_GET['course_fk']) ? (int)$_GET['course_fk'] : null;
if ($courseFk) {
    $whereConditions[] = 's.course_fk = ?';
    $whereParams[] = $courseFk;
    $whereTypes .= 'i';
}

// Status filter
$status = isset($_GET['status']) ? $_GET['status'] : null;
if ($status !== null && $status !== '') {
    $whereConditions[] = 's.is_active = ?';
    $whereParams[] = (int)$status;
    $whereTypes .= 'i';
}

$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
if (!empty($whereClause)) {
    $whereConditions[] = $whereClause;
}

$finalWhereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : '';

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}sections");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

$countQuery = "SELECT COUNT(*) as total 
               FROM {$dbPrefix}sections s
               INNER JOIN {$dbPrefix}courses c ON s.course_fk = c.courses_pk
               INNER JOIN {$dbPrefix}terms t ON s.term_fk = t.terms_pk
               {$finalWhereClause}";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

$dataQuery = "
    SELECT s.*, c.course_number, c.course_name, t.term_name, t.term_code
    FROM {$dbPrefix}sections s
    INNER JOIN {$dbPrefix}courses c ON s.course_fk = c.courses_pk
    INNER JOIN {$dbPrefix}terms t ON s.term_fk = t.terms_pk
    {$finalWhereClause}
    ORDER BY {$orderColumn} {$params['orderDir']}
    LIMIT ? OFFSET ?
";

$queryParams = $whereParams;
$queryParams[] = $params['length'];
$queryParams[] = $params['start'];
$queryTypes = $whereTypes . 'ii';

$result = $db->query($dataQuery, $queryParams, $queryTypes);
$sections = $result->fetchAll();

$data = [];
foreach ($sections as $row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusClass = $row['is_active'] ? 'success' : 'secondary';
    $toggleIcon = $row['is_active'] ? 'ban' : 'check';
    $toggleClass = $row['is_active'] ? 'warning' : 'success';
    $sectionLabel = $row['course_number'] . '-' . $row['section_id'];
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        htmlspecialchars($row['crn'] ?? 'N/A'),
        htmlspecialchars($row['course_number']),
        htmlspecialchars($row['section_id']),
        htmlspecialchars($row['instructor_name'] ?? 'N/A'),
        htmlspecialchars($row['max_enrollment'] ?? 'N/A'),
        $status,
        '<button class="btn btn-warning" title="Edit" onclick=\'editSection(' . $rowJson . ')\' aria-label="Edit section ' . htmlspecialchars($sectionLabel, ENT_QUOTES) . '"><i class="fas fa-edit" aria-hidden="true"></i></button>'
    ];
}

outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
