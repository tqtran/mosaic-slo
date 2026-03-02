<?php
declare(strict_types=1);

/**
 * Enrollment DataTables Server-Side Processing
 * 
 * Handles AJAX requests for enrollment data with pagination, sorting, and filtering.
 * 
 * @package Mosaic
 */

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database
require_once __DIR__ . '/../system/includes/init.php';

try {
    // DataTables parameters
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';
    $orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = $_GET['order'][0]['dir'] ?? 'asc';

    // Column definitions (must match table columns)
    $columns = [
        'e.enrollment_pk',
        'e.term_code',
        'e.crn',
        's.student_id',
        'e.enrollment_status',
        'e.enrollment_date',
        'e.created_at',
        'u_created.full_name',
        'e.updated_at',
        'u_updated.full_name',
        'actions' // Not sortable, placeholder
    ];

    // Validate order direction
    $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

    // Get order column name (prevent SQL injection)
    $orderColumn = $columns[$orderColumnIndex] ?? 'e.updated_at';
    if ($orderColumn === 'actions') {
        $orderColumn = 'e.updated_at'; // Default for actions column
    }

    // Build WHERE clause for global search
    $whereConditions = [];
    $params = [];
    $types = '';

    // Term filter (join through terms table)
    $termJoin = '';
    $termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : null;
    if ($termFk) {
        $termJoin = "LEFT JOIN {$dbPrefix}terms t ON e.term_code = t.term_code";
        $whereConditions[] = 't.terms_pk = ?';
        $params[] = $termFk;
        $types .= 'i';
    }

    if (!empty($searchValue)) {
        $searchConditions = [
            'e.enrollment_pk LIKE ?',
            'e.term_code LIKE ?',
            'e.crn LIKE ?',
            's.student_id LIKE ?',
            'e.enrollment_status LIKE ?',
            'e.enrollment_date LIKE ?'
        ];
        $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        $searchParam = "%{$searchValue}%";
        for ($i = 0; $i < count($searchConditions); $i++) {
            $params[] = $searchParam;
            $types .= 's';
        }
    }

    // Add column-specific filters
    for ($i = 0; $i < count($columns) - 1; $i++) { // Exclude actions column
        $columnSearch = $_GET['columns'][$i]['search']['value'] ?? '';
        if (!empty($columnSearch) && $columns[$i] !== 'actions') {
            if ($columns[$i] === 'u_created.full_name' || $columns[$i] === 'u_updated.full_name') {
                $whereConditions[] = "{$columns[$i]} LIKE ?";
            } else {
                $whereConditions[] = "{$columns[$i]} LIKE ?";
            }
            $params[] = "%{$columnSearch}%";
            $types .= 's';
        }
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get total records (without filtering)
    $totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}enrollment");
    $totalRow = $totalResult->fetch();
    $recordsTotal = $totalRow['total'];

    // Get filtered records count
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM {$dbPrefix}enrollment e
        LEFT JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
        LEFT JOIN {$dbPrefix}users u_created ON e.created_by_fk = u_created.users_pk
        LEFT JOIN {$dbPrefix}users u_updated ON e.updated_by_fk = u_updated.users_pk
        {$termJoin}
        {$whereClause}
    ";
    if (!empty($params)) {
        $filteredResult = $db->query($countQuery, $params, $types);
    } else {
        $filteredResult = $db->query($countQuery);
    }
    $filteredRow = $filteredResult->fetch();
    $recordsFiltered = $filteredRow['total'];

    // Get data
    $dataQuery = "
        SELECT 
            e.enrollment_pk,
            e.term_code,
            e.crn,
            s.student_id,
            s.first_name,
            s.last_name,
            e.enrollment_status,
            e.enrollment_date,
            e.created_at,
            e.updated_at,
            e.created_by_fk,
            e.updated_by_fk,
            u_created.full_name as created_by_name,
            u_updated.full_name as updated_by_name
        FROM {$dbPrefix}enrollment e
        LEFT JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
        LEFT JOIN {$dbPrefix}users u_created ON e.created_by_fk = u_created.users_pk
        LEFT JOIN {$dbPrefix}users u_updated ON e.updated_by_fk = u_updated.users_pk
        {$termJoin}
        {$whereClause}
        ORDER BY {$orderColumn} {$orderDir}
        LIMIT ? OFFSET ?
    ";

    // Add limit params
    $params[] = $length;
    $params[] = $start;
    $types .= 'ii';

    $result = $db->query($dataQuery, $params, $types);
    $enrollments = $result->fetchAll();

    // Format data for DataTables
    $data = [];
    foreach ($enrollments as $row) {
        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        
        $data[] = [
            htmlspecialchars((string)$row['enrollment_pk']),
            htmlspecialchars($row['term_code']),
            htmlspecialchars($row['crn']),
            htmlspecialchars($row['student_id']),
            htmlspecialchars($row['enrollment_date'] ?? ''),
            htmlspecialchars($row['created_at'] ?? ''),
            htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
            htmlspecialchars($row['updated_at'] ?? ''),
            htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
            '<button class="btn btn-warning" title="Edit" onclick=\'editEnrollment(' . $rowJson . ')\' aria-label="Edit enrollment for ' . htmlspecialchars($row['student_id'], ENT_QUOTES) . '"><i class="fas fa-edit" aria-hidden="true"></i></button>'
        ];
    }

    // Return JSON response
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("Error in enrollment_data.php: " . $e->getMessage());
    echo json_encode([
        'draw' => $_GET['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'An error occurred while fetching data'
    ]);
}
