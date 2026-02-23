<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

$params = getDataTablesParams();

$searchableColumns = ['crn', 'section_number', 'c.course_name', 't.term_name', 'u.first_name', 'u.last_name'];

$columns = [
    'course_sections_pk',
    'crn',
    'course_name',
    'term_name',
    'section_number',
    'instructor_name',
    'is_active',
    'actions'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'crn';
if ($orderColumn === 'actions') {
    $orderColumn = 'crn';
}
if ($orderColumn === 'course_name') {
    $orderColumn = 'c.course_name';
}
if ($orderColumn === 'term_name') {
    $orderColumn = 't.term_name';
}

$whereParams = [];
$whereTypes = '';
$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
if (!empty($whereClause)) {
    $whereClause = "WHERE {$whereClause}";
}

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}course_sections");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

$countQuery = "SELECT COUNT(*) as total 
               FROM {$dbPrefix}course_sections cs 
               LEFT JOIN {$dbPrefix}courses c ON cs.course_fk = c.courses_pk 
               LEFT JOIN {$dbPrefix}terms t ON cs.term_fk = t.terms_pk 
               LEFT JOIN {$dbPrefix}users u ON cs.instructor_fk = u.users_pk 
               {$whereClause}";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

$dataQuery = "
    SELECT cs.*, c.course_name, c.course_number, t.term_name, t.term_code,
           CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
           u.email as instructor_email
    FROM {$dbPrefix}course_sections cs
    LEFT JOIN {$dbPrefix}courses c ON cs.course_fk = c.courses_pk
    LEFT JOIN {$dbPrefix}terms t ON cs.term_fk = t.terms_pk
    LEFT JOIN {$dbPrefix}users u ON cs.instructor_fk = u.users_pk
    {$whereClause}
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
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $data[] = [
        htmlspecialchars($row['course_sections_pk']),
        '<span class="badge bg-primary">' . htmlspecialchars($row['crn']) . '</span>',
        htmlspecialchars($row['course_name'] ?? '') . ' (' . htmlspecialchars($row['course_number'] ?? '') . ')',
        htmlspecialchars($row['term_name'] ?? '') . ' <small class="text-muted">(' . htmlspecialchars($row['term_code'] ?? '') . ')</small>',
        htmlspecialchars($row['section_number'] ?? ''),
        !empty($row['instructor_name']) ? htmlspecialchars($row['instructor_name']) : '<span class="text-muted">Not assigned</span>',
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editSection(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['course_sections_pk'] . ', \'' . htmlspecialchars($row['crn'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteSection(' . $row['course_sections_pk'] . ', \'' . htmlspecialchars($row['crn'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
