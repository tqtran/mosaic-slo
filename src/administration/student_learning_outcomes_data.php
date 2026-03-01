<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/includes/datatables_helper.php';
require_once __DIR__ . '/../system/includes/init.php';

try {
    $params = getDataTablesParams();

    $searchableColumns = ['slo_code', 'slo_description', 'slo.assessment_method', 'c.course_name', 'po.outcome_code', 'slo.student_learning_outcomes_pk'];

    $columns = [
        'student_learning_outcomes_pk',
        'course_name',
        'program_outcome_code',
        'slo_code',
        'slo_description',
        'assessment_method',
        'sequence_num',
        'is_active',
        'created_at',
        'created_by_name',
        'updated_at',
        'updated_by_name',
        'actions'
    ];

    // Map column indices to database column names for individual column search
    $columnDbNames = [
        0 => 'slo.student_learning_outcomes_pk',
        1 => 'c.course_name',
        2 => 'po.outcome_code',
        3 => 'slo.slo_code',
        4 => 'slo.slo_description',
        5 => 'slo.assessment_method',
        6 => 'slo.sequence_num',
        7 => 'slo.is_active',
        8 => 'slo.created_at',
        9 => 'u_created.full_name',
        10 => 'slo.updated_at',
        11 => 'u_updated.full_name'
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
    if ($orderColumn === 'created_by_name') {
        $orderColumn = 'u_created.full_name';
    }
    if ($orderColumn === 'updated_by_name') {
        $orderColumn = 'u_updated.full_name';
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
                   LEFT JOIN {$dbPrefix}users u_created ON slo.created_by_fk = u_created.users_pk
                   LEFT JOIN {$dbPrefix}users u_updated ON slo.updated_by_fk = u_updated.users_pk
                   {$finalWhereClause}";
    if (!empty($whereParams)) {
        $filteredResult = $db->query($countQuery, $whereParams, $whereTypes);
    } else {
        $filteredResult = $db->query($countQuery);
    }
    $filteredRow = $filteredResult->fetch();
    $recordsFiltered = $filteredRow['total'];

    $dataQuery = "
        SELECT slo.*, 
               c.course_name, c.course_number, 
               po.outcome_code as program_outcome_code,
               slo.created_by_fk,
               slo.updated_by_fk,
               u_created.full_name as created_by_name,
               u_updated.full_name as updated_by_name
        FROM {$dbPrefix}student_learning_outcomes slo
        LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
        LEFT JOIN {$dbPrefix}program_outcomes po ON slo.program_outcomes_fk = po.program_outcomes_pk
        LEFT JOIN {$dbPrefix}users u_created ON slo.created_by_fk = u_created.users_pk
        LEFT JOIN {$dbPrefix}users u_updated ON slo.updated_by_fk = u_updated.users_pk
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
        
        $assessmentMethodDisplay = !empty($row['assessment_method'])
            ? htmlspecialchars($row['assessment_method'])
            : '<span class="text-muted">-</span>';
        
        $data[] = [
            '<span class="badge bg-secondary">' . htmlspecialchars((string)$row['student_learning_outcomes_pk']) . '</span>',
            htmlspecialchars($row['course_name'] ?? '') . ' (' . htmlspecialchars($row['course_number'] ?? '') . ')',
            $programOutcomeDisplay,
            '<span class="badge bg-primary">' . htmlspecialchars($row['slo_code']) . '</span>',
            htmlspecialchars($descriptionPreview),
            $assessmentMethodDisplay,
            htmlspecialchars((string)$row['sequence_num']),
            '<span class="badge bg-' . $statusClass . '">' . $status . '</span>',
            htmlspecialchars($row['created_at'] ?? ''),
            htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
            htmlspecialchars($row['updated_at'] ?? ''),
            htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
            '<button class="btn btn-warning" title="Edit" onclick=\'editSLO(' . $rowJson . ')\' aria-label="Edit SLO ' . htmlspecialchars($row['slo_code'], ENT_QUOTES) . '"><i class="fas fa-edit" aria-hidden="true"></i></button>'
        ];
    }

    outputDataTablesJson($params['draw'], $recordsTotal, $recordsFiltered, $data);

} catch (Exception $e) {
    error_log("Error in student_learning_outcomes_data.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'draw' => $_GET['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'An error occurred while fetching data'
    ]);
}
