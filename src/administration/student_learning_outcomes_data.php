<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

$params = getDataTablesParams();

$searchableColumns = ['slo_code', 'slo_description', 'c.course_name', 'po.outcome_code'];

$columns = [
    'student_learning_outcomes_pk',
    'course_name',
    'program_outcome_code',
    'slo_code',
    'slo_description',
    'sequence_num',
    'is_active',
    'actions'
];

// Map column indices to database column names for individual column search
$columnDbNames = [
    0 => 'slo.student_learning_outcomes_pk',
    1 => 'c.course_name',
    2 => 'po.outcome_code',
    3 => 'slo.slo_code',
    4 => 'slo.slo_description',
    5 => 'slo.sequence_num',
    6 => 'slo.is_active'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'slo_code';
if ($orderColumn === 'actions') {
    $orderColumn = 'slo_code';
}
if ($orderColumn === 'course_name') {
    $orderColumn = 'c.course_name';
}
if ($orderColumn === 'program_outcome_code') {
    $orderColumn = 'po.outcome_code';
}

$whereParams = [];
$whereTypes = '';

// Term filter (join through courses)
$whereConditions = [];
$termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : null;
if ($termFk) {
    $whereConditions[] = 'c.term_fk = ?';
    $whereParams[] = $termFk;
    $whereTypes .= 'i';
}

// Global search
$whereClause = buildSearchWhere($params['search'], $searchableColumns, $whereParams, $whereTypes);
if (!empty($whereClause)) {
    $whereConditions[] = $whereClause;
}

// Individual column searches
$columnSearchConditions = buildColumnSearchWhere($params['columnSearches'], $columnDbNames, $whereParams, $whereTypes);
foreach ($columnSearchConditions as $condition) {
    $whereConditions[] = $condition;
}

$finalWhereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : '';

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}student_learning_outcomes");
$totalRow = $totalResult->fetch();
$recordsTotal = $totalRow['total'];

$countQuery = "SELECT COUNT(*) as total 
               FROM {$dbPrefix}student_learning_outcomes slo 
               LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
               LEFT JOIN {$dbPrefix}program_outcomes po ON slo.program_outcomes_fk = po.program_outcomes_pk 
               {$finalWhereClause}";
if (!empty($whereParams)) {
    $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
} else {
    $filteredResult = $db->query($countQuery);
}
$filteredRow = $filteredResult->fetch();
$recordsFiltered = $filteredRow['total'];

$dataQuery = "
    SELECT slo.*, c.course_name, c.course_number, po.outcome_code as program_outcome_code
    FROM {$dbPrefix}student_learning_outcomes slo
    LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
    LEFT JOIN {$dbPrefix}program_outcomes po ON slo.program_outcomes_fk = po.program_outcomes_pk
    {$finalWhereClause}
    ORDER BY {$orderColumn} {$params['orderDir']}
    LIMIT ? OFFSET ?
";

$queryParams = $whereParams;
$queryParams[] = $params['length'];
$queryParams[] = $params['start'];
$queryTypes = $whereTypes . 'ii';

$result = $db->query($dataQuery, $queryParams, $queryTypes);
$slos = $result->fetchAll();

$data = [];
foreach ($slos as $row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusClass = $row['is_active'] ? 'success' : 'secondary';
    $toggleIcon = $row['is_active'] ? 'ban' : 'check';
    $toggleClass = $row['is_active'] ? 'warning' : 'success';
    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
    
    $descriptionPreview = strlen($row['slo_description']) > 60 
        ? substr($row['slo_description'], 0, 60) . '...' 
        : $row['slo_description'];
    
    $programOutcomeDisplay = !empty($row['program_outcome_code']) 
        ? '<span class="badge bg-info">' . htmlspecialchars($row['program_outcome_code']) . '</span>' 
        : '<span class="text-muted">-</span>';
    
    $data[] = [
        htmlspecialchars((string)$row['student_learning_outcomes_pk']),
        htmlspecialchars($row['course_name'] ?? '') . ' (' . htmlspecialchars($row['course_number'] ?? '') . ')',
        $programOutcomeDisplay,
        '<span class="badge bg-primary">' . htmlspecialchars($row['slo_code']) . '</span>',
        htmlspecialchars($descriptionPreview),
        htmlspecialchars((string)$row['sequence_num']),
        '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
        '<button class="btn btn-sm btn-primary" title="Edit" onclick=\'editSLO(' . $rowJson . ')\'><i class="fas fa-edit"></i></button> ' .
        '<button class="btn btn-sm btn-' . $toggleClass . '" title="Toggle Status" onclick="toggleStatus(' . $row['student_learning_outcomes_pk'] . ', \'' . htmlspecialchars($row['slo_code'], ENT_QUOTES) . '\')"><i class="fas fa-' . $toggleIcon . '"></i></button> ' .
        '<button class="btn btn-sm btn-danger" title="Delete" onclick="deleteSLO(' . $row['student_learning_outcomes_pk'] . ', \'' . htmlspecialchars($row['slo_code'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i></button>'
    ];
}

outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);
