<?php
declare(strict_types=1);

/**
 * Program Outcomes DataTables Server-Side Processing
 * 
 * @package Mosaic
 */

header('X-Content-Type-Options: nosniff');

// Session configuration (minimal for AJAX endpoints)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database
require_once __DIR__ . '/../system/includes/init.php';
require_once __DIR__ . '/../system/includes/datatables_helper.php';

// Get DataTables parameters
$params = getDatatTablesParams();
$draw = $params['draw'];
$start = $params['start'];
$length = $params['length'];
$searchValue = $params['search'];
$orderDir = $params['orderDir'];

// Column definitions
$columns = [
    'po.program_outcomes_pk',
    'p.program_name',
    'po.code',
    'po.description',
    'io.code',
    'po.sequence_num',
    'po.is_active',
    'actions'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'po.sequence_num';
if ($orderColumn === 'actions') {
    $orderColumn = 'po.sequence_num';
}

// Build WHERE clause
$whereParams = [];
$whereTypes = '';
$searchWhere = buildSearchWhere(
    $searchValue,
    ['po.program_outcomes_pk', 'p.program_name', 'po.code', 'po.description', 'io.code'],
    $whereParams,
    $whereTypes
);
$whereClause = !empty($searchWhere) ? "WHERE {$searchWhere}" : '';

// Get total records
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}program_outcomes");
$totalRow = $totalResult->fetch_assoc();
$totalRecords = (int)$totalRow['total'];

// Get filtered count
if (!empty($whereClause)) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM {$dbPrefix}program_outcomes po
        JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
        LEFT JOIN {$dbPrefix}institutional_outcomes io ON po.institutional_outcomes_fk = io.institutional_outcomes_pk
        {$whereClause}
    ");
    if (!empty($whereTypes)) {
        $stmt->bind_param($whereTypes, ...$whereParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $filteredRow = $result->fetch_assoc();
    $filteredRecords = (int)$filteredRow['total'];
} else {
    $filteredRecords = $totalRecords;
}

// Get data
$query = "
    SELECT 
        po.program_outcomes_pk,
        po.program_fk,
        p.program_name,
        po.institutional_outcomes_fk,
        io.code as institutional_outcome_code,
        po.code,
        po.description,
        po.sequence_num,
        po.is_active
    FROM {$dbPrefix}program_outcomes po
    JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
    LEFT JOIN {$dbPrefix}institutional_outcomes io ON po.institutional_outcomes_fk = io.institutional_outcomes_pk
    {$whereClause}
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT ? OFFSET ?
";

$allParams = array_merge($whereParams, [$length, $start]);
$allTypes = $whereTypes . 'ii';

$stmt = $db->prepare($query);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

// Output JSON
outputDatatablesJson($draw, $totalRecords, $filteredRecords, $data);
