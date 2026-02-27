<?php
/**
 * Terms Data Endpoint
 * 
 * DataTables server-side processing endpoint for terms management.
 * Returns terms data with term year information and course section counts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

header('Content-Type: application/json');

try {
    // DataTables parameters
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';
    $orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = strtoupper($_GET['order'][0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    // Column mapping
    $columns = [
        0 => 't.term_code',
        1 => 't.term_name',
        2 => ''  // Actions column
    ];

    $orderBy = $columns[$orderColumn] ?? 't.term_code';

    // Total records
    $totalResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}terms");
    $totalRow = $totalResult->fetch();
    $totalRecords = $totalRow['total'];

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    if (!empty($searchValue)) {
        $where[] = "(t.term_code LIKE ? OR t.term_name LIKE ? OR t.academic_year LIKE ?)";
        $params[] = "%{$searchValue}%";
        $params[] = "%{$searchValue}%";
        $params[] = "%{$searchValue}%";
        $types .= 'sss';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Filtered records
    if (!empty($where)) {
        $filteredResult = $db->query(
            "SELECT COUNT(*) as total FROM {$dbPrefix}terms t {$whereClause}",
            $params,
            $types
        );
        $filteredRow = $filteredResult->fetch();
        $filteredRecords = $filteredRow['total'];
    } else {
        $filteredRecords = $totalRecords;
    }

    // Main query
    $query = "
        SELECT 
            t.terms_pk,
            t.term_code,
            t.term_name,
            t.academic_year,
            t.start_date,
            t.end_date,
            t.is_active
        FROM {$dbPrefix}terms t
        {$whereClause}
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ? OFFSET ?
    ";

    $params[] = $length;
    $params[] = $start;
    $types .= 'ii';

    $result = $db->query($query, $params, $types);

    // Format data
    $data = [];
    while ($row = $result->fetch()) {
        // Prepare data for JavaScript functions (keeping all data for edit functionality)
        $termJson = htmlspecialchars(json_encode([
            'terms_pk' => $row['terms_pk'],
            'term_code' => $row['term_code'],
            'term_name' => $row['term_name'],
            'academic_year' => $row['academic_year'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'is_active' => $row['is_active']
        ]), ENT_QUOTES, 'UTF-8');
        
        // Action buttons
        $actions = '
            <button type="button" class="btn btn-sm btn-primary" onclick=\'editTerm(' . $termJson . ')\' title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn btn-sm btn-warning" onclick="toggleStatus(' . $row['terms_pk'] . ', \'' . htmlspecialchars($row['term_code'], ENT_QUOTES) . '\')" title="Toggle Status">
                <i class="fas fa-toggle-on"></i>
            </button>
            <button type="button" class="btn btn-sm btn-danger" onclick="deleteTerm(' . $row['terms_pk'] . ', \'' . htmlspecialchars($row['term_code'], ENT_QUOTES) . '\')" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        ';
        
        $data[] = [
            htmlspecialchars($row['term_code'] ?? ''),
            htmlspecialchars($row['term_name']),
            $actions
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
} catch (\Exception $e) {
    error_log("Terms DataTables error: " . $e->getMessage());
    echo json_encode([
        'draw' => $draw ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
