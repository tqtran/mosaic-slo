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

try {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';

    $orderColumnIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDirection = strtoupper($_GET['order'][0]['dir'] ?? 'ASC');
    if (!in_array($orderDirection, ['ASC', 'DESC'])) {
        $orderDirection = 'ASC';
    }

    $columns = ['students_pk', 'student_id', 'first_name', 'last_name', 'email', 'is_active', 'created_at', 'created_by_name', 'updated_at', 'updated_by_name'];
    $orderColumn = $columns[$orderColumnIndex] ?? 'students_pk';
    
    // Map friendly column names to database columns
    if ($orderColumn === 'created_by_name') {
        $orderColumn = 'u_created.full_name';
    } elseif ($orderColumn === 'updated_by_name') {
        $orderColumn = 'u_updated.full_name';
    } else {
        $orderColumn = 's.' . $orderColumn;
    }

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
            $searchConditions[] = "s." . $col . " LIKE ?";
            $params[] = "%{$searchValue}%";
            $types .= 's';
        }
        $where[] = '(' . implode(' OR ', $searchConditions) . ')';
    }

    foreach ($columnSearch as $column => $value) {
        if ($column === 'is_active') {
            $activeValue = stripos($value, 'active') !== false ? 1 : 0;
            $where[] = "s.is_active = ?";
            $params[] = $activeValue;
            $types .= 'i';
        } elseif ($column === 'created_by_name') {
            $where[] = "u_created.full_name LIKE ?";
            $params[] = "%{$value}%";
            $types .= 's';
        } elseif ($column === 'updated_by_name') {
            $where[] = "u_updated.full_name LIKE ?";
            $params[] = "%{$value}%";
            $types .= 's';
        } elseif (in_array($column, ['student_id', 'first_name', 'last_name', 'email', 'students_pk', 'created_at', 'updated_at'])) {
            $where[] = "s." . $column . " LIKE ?";
            $params[] = "%{$value}%";
            $types .= 's';
        }
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}students");
    $totalRow = $totalResult->fetch();
    $totalRecords = $totalRow['total'];

    $countQuery = "SELECT COUNT(*) as total 
                   FROM {$dbPrefix}students s
                   LEFT JOIN {$dbPrefix}users u_created ON s.created_by_fk = u_created.users_pk
                   LEFT JOIN {$dbPrefix}users u_updated ON s.updated_by_fk = u_updated.users_pk
                   {$whereClause}";
    
    if ($whereClause) {
        $filteredResult = $db->query($countQuery, $params, $types);
        $filteredRow = $filteredResult->fetch();
        $totalFiltered = $filteredRow['total'];
    } else {
        $totalFiltered = $totalRecords;
    }

    $sql = "SELECT s.students_pk, s.student_id, s.first_name, s.last_name, s.email, s.is_active,
                   s.created_at, s.updated_at,
                   s.created_by_fk, s.updated_by_fk,
                   u_created.full_name as created_by_name,
                   u_updated.full_name as updated_by_name
            FROM {$dbPrefix}students s
            LEFT JOIN {$dbPrefix}users u_created ON s.created_by_fk = u_created.users_pk
            LEFT JOIN {$dbPrefix}users u_updated ON s.updated_by_fk = u_updated.users_pk
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
            '<span class="badge bg-secondary">' . htmlspecialchars((string)$row['students_pk']) . '</span>',
            htmlspecialchars($row['student_id'] ?? ''),
            htmlspecialchars($row['first_name'] ?? ''),
            htmlspecialchars($row['last_name'] ?? ''),
            htmlspecialchars($row['email'] ?? ''),
            $statusBadge,
            htmlspecialchars($row['created_at'] ?? ''),
            htmlspecialchars(trim($row['created_by_name'] ?? '') ?: 'System'),
            htmlspecialchars($row['updated_at'] ?? ''),
            htmlspecialchars(trim($row['updated_by_name'] ?? '') ?: 'System'),
            $actions
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("Error in students_data.php: " . $e->getMessage());
    echo json_encode([
        'draw' => $_GET['draw'] ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'An error occurred while fetching data'
    ]);
}
