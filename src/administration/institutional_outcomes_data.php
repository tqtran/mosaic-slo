<?php
declare(strict_types=1);

/**
 * Institutional Outcomes DataTables Server-Side Processing
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
    'io.institutional_outcomes_pk',
    'i.institution_name',
    'io.code',
    'io.description',
    'io.sequence_num',
    'io.is_active',
    'actions'
];

$orderColumn = $columns[$params['orderColumn']] ?? 'io.sequence_num';
if ($orderColumn === 'actions') {
    $orderColumn = 'io.sequence_num';
}

// Build WHERE clause
$whereParams = [];
$whereTypes = '';
$searchWhere = buildSearchWhere(
    $searchValue,
    ['io.institutional_outcomes_pk', 'i.institution_name', 'io.code', 'io.description'],
    $whereParams,
    $whereTypes
);
$whereClause = !empty($searchWhere) ? "WHERE {$searchWhere}" : '';

// Get total records
$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}institutional_outcomes");
$totalRow = $totalResult->fetch();
$totalRecords = (int)$totalRow['total'];

// Get filtered count
if (!empty($whereClause)) {
    $query = "
        SELECT COUNT(*) as total 
        FROM {$dbPrefix}institutional_outcomes io
        JOIN {$dbPrefix}institution i ON io.institution_fk = i.institution_pk
        {$whereClause}
    ";
    $filteredResult = $db->query($query, $whereParams, $whereTypes);
    $filteredRow = $filteredResult->fetch();
    $filteredRecords = (int)$filteredRow['total'];
} else {
    $filteredRecords = $totalRecords;
}

// Get data
$query = "
    SELECT 
        io.institutional_outcomes_pk,
        io.institution_fk,
        i.institution_name,
        io.code,
        io.description,
        io.sequence_num,
        io.is_active
    FROM {$dbPrefix}institutional_outcomes io
    JOIN {$dbPrefix}institution i ON io.institution_fk = i.institution_pk
    {$whereClause}
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT ? OFFSET ?
";

$allParams = array_merge($whereParams, [$length, $start]);
$allTypes = $whereTypes . 'ii';

$result = $db->query($query, $allParams, $allTypes);
$data = $result->fetchAll();

// Output JSON
outputDatatablesJson($draw, $totalRecords, $filteredRecords, $data);
