<?php
declare(strict_types=1);

/**
 * Institution Data - AJAX Endpoint for DataTables Server-Side Processing
 * 
 * Returns institution records in DataTables JSON format with real-time filtering.
 * 
 * @package Mosaic\Administration
 */

// Security headers
header('Content-Type: application/json');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// Initialize common variables and database
require_once __DIR__ . '/../system/includes/init.php';

// TODO: Check authentication and authorization
// For now, allow access (will implement auth later)

try {
    // DataTables parameters
    $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 25;
    $searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    
    // Column definitions (must match table order)
    $columns = [
        0 => 'institution_pk',
        1 => 'institution_name',
        2 => 'institution_code',
        3 => 'is_active',
        4 => 'created_at',
        5 => 'updated_at'
    ];
    
    // Get ordering
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 1;
    $orderDir = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
    $orderColumn = $columns[$orderColumnIndex] ?? 'institution_name';
    
    // Build WHERE clause for global search
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($searchValue)) {
        $whereConditions[] = "(institution_name LIKE ? OR institution_code LIKE ?)";
        $searchParam = "%{$searchValue}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    // Column-specific filters
    foreach ($_GET['columns'] ?? [] as $index => $column) {
        if (!empty($column['search']['value']) && isset($columns[$index])) {
            $columnName = $columns[$index];
            $columnValue = trim($column['search']['value']);
            
            if ($columnName === 'is_active') {
                // Handle status filter
                if (stripos($columnValue, 'active') !== false && stripos($columnValue, 'inactive') === false) {
                    $whereConditions[] = "is_active = 1";
                } elseif (stripos($columnValue, 'inactive') !== false) {
                    $whereConditions[] = "is_active = 0";
                }
            } else {
                $whereConditions[] = "{$columnName} LIKE ?";
                $params[] = "%{$columnValue}%";
                $types .= 's';
            }
        }
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total records (without filtering)
    $totalResult = $db->query("SELECT COUNT(*) as count FROM {$dbPrefix}institution");
    $totalRow = $totalResult->fetch_assoc();
    $recordsTotal = (int)($totalRow['count'] ?? 0);
    
    // Get filtered records count
    $filteredQuery = "SELECT COUNT(*) as count FROM {$dbPrefix}institution {$whereClause}";
    if (!empty($params)) {
        $filteredResult = $db->query($filteredQuery, $params, $types);
    } else {
        $filteredResult = $db->query($filteredQuery);
    }
    $filteredRow = $filteredResult->fetch_assoc();
    $recordsFiltered = (int)($filteredRow['count'] ?? 0);
    
    // Get data
    $dataQuery = "SELECT * FROM {$dbPrefix}institution 
                  {$whereClause} 
                  ORDER BY {$orderColumn} {$orderDir} 
                  LIMIT ? OFFSET ?";
    
    $dataParams = array_merge($params, [$length, $start]);
    $dataTypes = $types . 'ii';
    
    $result = $db->query($dataQuery, $dataParams, $dataTypes);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'institution_pk' => $row['institution_pk'],
            'institution_name' => $row['institution_name'],
            'institution_code' => $row['institution_code'],
            'is_active' => (int)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    // Return DataTables JSON response
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ], JSON_THROW_ON_ERROR);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'message' => $debugMode ? $e->getMessage() : 'An error occurred while fetching data'
    ], JSON_THROW_ON_ERROR);
}
