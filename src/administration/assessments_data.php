<?php
declare(strict_types=1);

/**
 * Assessments DataTables Server-Side Processing
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

header('Content-Type: application/json');

$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$searchValue = $_GET['search']['value'] ?? '';

$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 0);
$orderDirection = strtoupper($_GET['order'][0]['dir'] ?? 'DESC');
if (!in_array($orderDirection, ['ASC', 'DESC'])) {
    $orderDirection = 'DESC';
}

$columns = [
    'a.assessments_pk', 
    'cs.crn', 
    'CONCAT(s.student_last_name, ", ", s.student_first_name)', 
    'slo.slo_code', 
    'a.score_value', 
    'a.achievement_level', 
    'a.assessed_date',
    'a.is_finalized'
];
$orderColumn = $columns[$orderColumnIndex] ?? 'a.assessed_date';

$columnSearch = [];
$searchColumns = ['crn', 'student_name', 'slo_code', 'score_value', 'achievement_level', 'assessed_date', 'is_finalized'];
foreach ($searchColumns as $index => $column) {
    $searchVal = $_GET['columns'][$index + 1]['search']['value'] ?? '';
    if ($searchVal !== '') {
        $columnSearch[$column] = $searchVal;
    }
}

$where = [];
$params = [];
$types = '';

if ($searchValue !== '') {
    $searchConditions = [];
    $searchConditions[] = "cs.crn LIKE ?";
    $searchConditions[] = "s.student_id LIKE ?";
    $searchConditions[] = "s.student_first_name LIKE ?";
    $searchConditions[] = "s.student_last_name LIKE ?";
    $searchConditions[] = "slo.slo_code LIKE ?";
    $searchConditions[] = "a.achievement_level LIKE ?";
    foreach ($searchConditions as $condition) {
        $params[] = "%{$searchValue}%";
        $types .= 's';
    }
    $where[] = '(' . implode(' OR ', $searchConditions) . ')';
}

foreach ($columnSearch as $column => $value) {
    if ($column === 'is_finalized') {
        $activeValue = stripos($value, 'finalized') !== false ? 1 : 0;
        $where[] = "a.is_finalized = ?";
        $params[] = $activeValue;
        $types .= 'i';
    } else if ($column === 'crn') {
        $where[] = "cs.crn LIKE ?";
        $params[] = "%{$value}%";
        $types .= 's';
    } else if ($column === 'student_name') {
        $where[] = "(s.student_first_name LIKE ? OR s.student_last_name LIKE ?)";
        $params[] = "%{$value}%";
        $params[] = "%{$value}%";
        $types .= 'ss';
    } else if ($column === 'slo_code') {
        $where[] = "slo.slo_code LIKE ?";
        $params[] = "%{$value}%";
        $types .= 's';
    } else {
        $where[] = "a." . $column . " LIKE ?";
        $params[] = "%{$value}%";
        $types .= 's';
    }
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}assessments");
$totalRow = $totalResult->fetch();
$totalRecords = $totalRow['total'];

$fromClause = "FROM {$dbPrefix}assessments a
    LEFT JOIN {$dbPrefix}course_sections cs ON a.course_section_fk = cs.course_sections_pk
    LEFT JOIN {$dbPrefix}courses c ON cs.course_fk = c.courses_pk
    LEFT JOIN {$dbPrefix}students s ON a.students_fk = s.students_pk
    LEFT JOIN {$dbPrefix}student_learning_outcomes slo ON a.student_learning_outcome_fk = slo.student_learning_outcomes_pk
    LEFT JOIN {$dbPrefix}courses c2 ON slo.course_fk = c2.courses_pk";

if ($whereClause) {
    $filteredResult = $db->query(
        "SELECT COUNT(*) as total {$fromClause} {$whereClause}",
        $params,
        $types
    );
    $filteredRow = $filteredResult->fetch();
    $totalFiltered = $filteredRow['total'];
} else {
    $totalFiltered = $totalRecords;
}

$sql = "SELECT a.assessments_pk, a.course_section_fk, a.students_fk, a.student_learning_outcome_fk,
               a.score_value, a.achievement_level, a.assessment_method, a.notes, a.assessed_date, a.is_finalized,
               cs.crn,
               c.course_name, c.course_number,
               s.student_id, s.student_first_name, s.student_last_name,
               slo.slo_code, slo.slo_description,
               c2.course_name as slo_course_name, c2.course_number as slo_course_number
        {$fromClause}
        {$whereClause}
        ORDER BY {$orderColumn} {$orderDirection}
        LIMIT ? OFFSET ?";

$params[] = $length;
$params[] = $start;
$types .= 'ii';

$result = $db->query($sql, $params, $types);

$data = [];
while ($row = $result->fetch()) {
    $statusBadge = $row['is_finalized'] ? 
        '<span class="badge bg-success">Finalized</span>' : 
        '<span class="badge bg-secondary">Draft</span>';
    
    $studentName = htmlspecialchars($row['student_last_name']) . ', ' . htmlspecialchars($row['student_first_name']);
    $sloDisplay = htmlspecialchars($row['slo_code']);
    $dateDisplay = $row['assessed_date'] ? date('Y-m-d', strtotime($row['assessed_date'])) : '';
    
    $rowData = json_encode([
        'assessments_pk' => $row['assessments_pk'],
        'course_section_fk' => $row['course_section_fk'],
        'students_fk' => $row['students_fk'],
        'student_learning_outcome_fk' => $row['student_learning_outcome_fk'],
        'score_value' => $row['score_value'],
        'achievement_level' => $row['achievement_level'],
        'assessment_method' => $row['assessment_method'],
        'notes' => $row['notes'],
        'assessed_date' => $row['assessed_date'],
        'is_finalized' => $row['is_finalized']
    ]);
    
    $actions = '
        <button type="button" class="btn btn-sm btn-primary" onclick=\'editAssessment(' . $rowData . ')\' title="Edit">
            <i class="fas fa-edit"></i>
        </button>
        <button type="button" class="btn btn-sm btn-warning" onclick="toggleStatus(' . $row['assessments_pk'] . ', \'' . $row['assessments_pk'] . '\')" title="Toggle Status">
            <i class="fas fa-toggle-on"></i>
        </button>
        <button type="button" class="btn btn-sm btn-danger" onclick="deleteAssessment(' . $row['assessments_pk'] . ', \'' . $row['assessments_pk'] . '\')" title="Delete">
            <i class="fas fa-trash"></i>
        </button>
    ';
    
    $data[] = [
        $row['assessments_pk'],
        htmlspecialchars($row['crn']),
        $studentName,
        $sloDisplay,
        number_format($row['score_value'], 2),
        htmlspecialchars($row['achievement_level']),
        $dateDisplay,
        $statusBadge,
        $actions
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);
