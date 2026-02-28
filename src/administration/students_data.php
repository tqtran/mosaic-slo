<?php
declare(strict_types=1);

/**
 * Students DataTables Server-Side Processing
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
$orderDirection = strtoupper($_GET['order'][0]['dir'] ?? 'ASC');
if (!in_array($orderDirection, ['ASC', 'DESC'])) {
    $orderDirection = 'ASC';
}

$columns = ['students_pk', 'student_id', 'first_name', 'last_name', 'email', 'is_active'];
$orderColumn = $columns[$orderColumnIndex] ?? 'students_pk';

$columnSearch = [];
foreach ($columns as $index => $column) {
    $searchVal = $_GET['columns'][$index]['search']['value'] ?? '';
    if ($searchVal !== '') {
        $columnSearch[$column] = $searchVal;
    }
}

$where = [];
$params = [];
$types = '';

if ($searchValue !== '') {
    $searchConditions = [];
    foreach (['student_id', 'first_name', 'last_name', 'email'] as $col) {
        $searchConditions[] = "{$dbPrefix}students." . $col . " LIKE ?";
        $params[] = "%{$searchValue}%";
        $types .= 's';
    }
    $where[] = '(' . implode(' OR ', $searchConditions) . ')';
}

foreach ($columnSearch as $column => $value) {
    if ($column === 'is_active') {
        $activeValue = stripos($value, 'active') !== false ? 1 : 0;
        $where[] = "{$dbPrefix}students.is_active = ?";
        $params[] = $activeValue;
        $types .= 'i';
    } else {
        $where[] = "{$dbPrefix}students." . $column . " LIKE ?";
        $params[] = "%{$value}%";
        $types .= 's';
    }
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}students");
$totalRow = $totalResult->fetch();
$totalRecords = $totalRow['total'];

if ($whereClause) {
    $filteredResult = $db->query(
        "SELECT COUNT(*) as total FROM {$dbPrefix}students {$whereClause}",
        $params,
        $types
    );
    $filteredRow = $filteredResult->fetch();
    $totalFiltered = $filteredRow['total'];
} else {
    $totalFiltered = $totalRecords;
}

$sql = "SELECT students_pk, student_id, first_name, last_name, email, is_active
        FROM {$dbPrefix}students 
        {$whereClause}
        ORDER BY {$orderColumn} {$orderDirection}
        LIMIT ? OFFSET ?";

$params[] = $length;
$params[] = $start;
$types .= 'ii';

$result = $db->query($sql, $params, $types);

$data = [];
while ($row = $result->fetch()) {
    $statusBadge = $row['is_active'] ? 
        '<span class="badge bg-success">Active</span>' : 
        '<span class="badge bg-secondary">Inactive</span>';
    
    $rowData = json_encode($row);
    $escapedStudentId = htmlspecialchars($row['student_id'] ?? '', ENT_QUOTES);
    
    $actions = '
        <button type="button" class="btn btn-sm btn-primary" onclick=\'editStudent(' . $rowData . ')\' title="Edit">
            <i class="fas fa-edit"></i>
        </button>
        <button type="button" class="btn btn-sm btn-warning" onclick="toggleStatus(' . $row['students_pk'] . ', \'' . $escapedStudentId . '\')" title="Toggle Status">
            <i class="fas fa-toggle-on"></i>
        </button>
        <button type="button" class="btn btn-sm btn-danger" onclick="deleteStudent(' . $row['students_pk'] . ', \'' . $escapedStudentId . '\')" title="Delete">
            <i class="fas fa-trash"></i>
        </button>
    ';
    
    $data[] = [
        $row['students_pk'],
        htmlspecialchars($row['student_id'] ?? ''),
        htmlspecialchars($row['first_name'] ?? ''),
        htmlspecialchars($row['last_name'] ?? ''),
        htmlspecialchars($row['email'] ?? ''),
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
