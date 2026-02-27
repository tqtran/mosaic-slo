<?php
declare(strict_types=1);

/**
 * Sections DataTables Data Source
 * 
 * Provides JSON data for sections DataTable with server-side processing.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

header('Content-Type: application/json');

try {
    // Parse DataTables parameters
    $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
    $searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 1;
    $orderDirection = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
    
    // Get term filter
    $termFk = isset($_GET['term_fk']) ? (int)$_GET['term_fk'] : 0;
    
    // Column mapping for ordering
    $columns = [
        0 => 's.sections_pk',
        1 => 'c.course_number',
        2 => 's.section_id',
        3 => 'c.course_name',
        4 => 's.crn',
        5 => 's.instructor_name',
        6 => 't.term_name',
        7 => 's.is_active'
    ];
    
    $orderColumn = $columns[$orderColumnIndex] ?? 'c.course_number';
    
    // Base query
    $baseQuery = "FROM {$dbPrefix}sections s
                  INNER JOIN {$dbPrefix}courses c ON s.course_fk = c.courses_pk
                  INNER JOIN {$dbPrefix}terms t ON s.term_fk = t.terms_pk";
    
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Term filter
    if ($termFk > 0) {
        $whereConditions[] = "s.term_fk = ?";
        $params[] = $termFk;
        $types .= 'i';
    }
    
    // Search filter
    if (!empty($searchValue)) {
        $searchConditions = [
            "c.course_number LIKE ?",
            "s.section_id LIKE ?",
            "c.course_name LIKE ?",
            "s.crn LIKE ?",
            "s.instructor_name LIKE ?",
            "t.term_name LIKE ?"
        ];
        $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        $searchParam = "%{$searchValue}%";
        for ($i = 0; $i < 6; $i++) {
            $params[] = $searchParam;
            $types .= 's';
        }
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Count total records
    $totalQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $totalResult = $db->query($totalQuery);
    $totalRow = $totalResult->fetch();
    $recordsTotal = $totalRow['total'];
    
    // Count filtered records
    $filteredQuery = "SELECT COUNT(*) as total " . $baseQuery . $whereClause;
    if (!empty($params)) {
        $filteredResult = $db->query($filteredQuery, $params, $types);
    } else {
        $filteredResult = $db->query($filteredQuery);
    }
    $filteredRow = $filteredResult->fetch();
    $recordsFiltered = $filteredRow['total'];
    
    // Fetch data
    $dataQuery = "SELECT s.sections_pk, c.course_number, s.section_id, c.course_name, s.crn, 
                         s.instructor_name, t.term_name, s.is_active, s.course_fk, s.term_fk, s.max_enrollment
                  " . $baseQuery . $whereClause . "
                  ORDER BY {$orderColumn} {$orderDirection}
                  LIMIT {$start}, {$length}";
    
    if (!empty($params)) {
        $dataResult = $db->query($dataQuery, $params, $types);
    } else {
        $dataResult = $db->query($dataQuery);
    }
    
    $data = [];
    while ($row = $dataResult->fetch()) {
        $sectionLabel = htmlspecialchars($row['course_number'] . '-' . $row['section_id']);
        $statusBadge = $row['is_active'] 
            ? '<span class="badge bg-success">Active</span>' 
            : '<span class="badge bg-danger">Inactive</span>';
        
        $editBtn = '<button type="button" class="btn btn-sm btn-warning" onclick="editSection(' . htmlspecialchars(json_encode($row)) . ')" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>';
        $toggleBtn = '<button type="button" class="btn btn-sm btn-' . ($row['is_active'] ? 'secondary' : 'success') . '" 
                             onclick="toggleStatus(' . $row['sections_pk'] . ', \'' . $sectionLabel . '\')" title="Toggle Status">
                          <i class="fas fa-toggle-' . ($row['is_active'] ? 'on' : 'off') . '"></i>
                      </button>';
        $deleteBtn = '<button type="button" class="btn btn-sm btn-danger" 
                             onclick="deleteSection(' . $row['sections_pk'] . ', \'' . $sectionLabel . '\')" title="Delete">
                          <i class="fas fa-trash"></i>
                      </button>';
        
        $actions = $editBtn . ' ' . $toggleBtn . ' ' . $deleteBtn;
        
        $data[] = [
            $row['sections_pk'],
            htmlspecialchars($row['course_number']),
            '<span class="badge bg-primary">' . htmlspecialchars($row['section_id']) . '</span>',
            htmlspecialchars($row['course_name']),
            htmlspecialchars($row['crn'] ?? ''),
            htmlspecialchars($row['instructor_name'] ?? ''),
            htmlspecialchars($row['term_name']),
            $statusBadge,
            $actions
        ];
    }
    
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error loading sections data',
        'message' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}
