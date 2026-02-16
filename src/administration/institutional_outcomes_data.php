<?php
declare(strict_types=1);

/**
 * Institutional Outcomes Data - AJAX Endpoint for DataTables Server-Side Processing
 * 
 * Returns institutional outcome records in DataTables JSON format with real-time filtering.
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
        0 => 'sequence_num',
        1 => 'code',
        2 => 'description',
        3 => 'institution_name',
        4 => 'program_outcome_count',
        5 => 'is_active'
    ];
    
    // Get ordering
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
    $orderDir = isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
    $orderColumn = $columns[$orderColumnIndex] ?? 'sequence_num';
    
    // Map display column to actual column for sorting
    $orderColumnMapping = [
        'institution_name' => 'i.institution_name',
        'program_outcome_count' => 'program_outcome_count',
        'is_active' => 'io.is_active',
        'sequence_num' => 'io.sequence_num',
        'code' => 'io.code',
        'description' => 'io.description'
    ];
    $orderColumnActual = $orderColumnMapping[$orderColumn] ?? 'io.sequence_num';
    
    // Build WHERE clause for global search
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($searchValue)) {
        $whereConditions[] = "(io.code LIKE ? OR io.description LIKE ? OR i.institution_name LIKE ? OR i.institution_code LIKE ?)";
        $searchParam = "%{$searchValue}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ssss';
    }
    
    // Column-specific filters
    foreach ($_GET['columns'] ?? [] as $index => $column) {
        if (!empty($column['search']['value']) && isset($columns[$index])) {
            $columnName = $columns[$index];
            $columnValue = trim($column['search']['value']);
            
            if ($columnName === 'is_active') {
                // Handle status filter
                if (stripos($columnValue, 'active') !== false && stripos($columnValue, 'inactive') === false) {
                    $whereConditions[] = "io.is_active = 1";
                } elseif (stripos($columnValue, 'inactive') !== false) {
                    $whereConditions[] = "io.is_active = 0";
                }
            } elseif ($columnName === 'institution_name') {
                $whereConditions[] = "(i.institution_name LIKE ? OR i.institution_code LIKE ?)";
                $params[] = "%{$columnValue}%";
                $params[] = "%{$columnValue}%";
                $types .= 'ss';
            } elseif ($columnName === 'code') {
                $whereConditions[] = "io.code LIKE ?";
                $params[] = "%{$columnValue}%";
                $types .= 's';
            } elseif ($columnName === 'description') {
                $whereConditions[] = "io.description LIKE ?";
                $params[] = "%{$columnValue}%";
                $types .= 's';
            }
        }
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Base query with joins
    $baseQuery = "FROM {$dbPrefix}institutional_outcomes io
                  INNER JOIN {$dbPrefix}institution i ON io.institution_fk = i.institution_pk
                  LEFT JOIN (
                      SELECT institutional_outcome_fk, COUNT(*) as count
                      FROM {$dbPrefix}program_outcomes
                      GROUP BY institutional_outcome_fk
                  ) po ON io.institutional_outcomes_pk = po.institutional_outcome_fk";
    
    // Get total records (without filtering)
    $totalQuery = "SELECT COUNT(*) as count {$baseQuery}";
    $totalResult = $db->query($totalQuery);
    $totalRow = $totalResult->fetch_assoc();
    $recordsTotal = (int)($totalRow['count'] ?? 0);
    
    // Get filtered records count
    $filteredQuery = "SELECT COUNT(*) as count {$baseQuery} {$whereClause}";
    if (!empty($params)) {
        $filteredResult = $db->query($filteredQuery, $params, $types);
    } else {
        $filteredResult = $db->query($filteredQuery);
    }
    $filteredRow = $filteredResult->fetch_assoc();
    $recordsFiltered = (int)($filteredRow['count'] ?? 0);
    
    // Get data
    $dataQuery = "SELECT io.*, 
                         i.institution_name, 
                         i.institution_code,
                         COALESCE(po.count, 0) as program_outcome_count
                  {$baseQuery}
                  {$whereClause} 
                  ORDER BY {$orderColumnActual} {$orderDir} 
                  LIMIT ? OFFSET ?";
    
    $dataParams = array_merge($params, [$length, $start]);
    $dataTypes = $types . 'ii';
    
    $result = $db->query($dataQuery, $dataParams, $dataTypes);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'institutional_outcomes_pk' => $row['institutional_outcomes_pk'],
            'institution_fk' => $row['institution_fk'],
            'code' => $row['code'],
            'description' => $row['description'],
            'sequence_num' => $row['sequence_num'],
            'is_active' => (int)$row['is_active'],
            'institution_name' => $row['institution_name'],
            'institution_code' => $row['institution_code'],
            'program_outcome_count' => (int)$row['program_outcome_count'],
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
