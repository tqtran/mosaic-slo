<?php
declare(strict_types=1);

/**
 * Term Years Administration
 * 
 * Manage academic term years and copy course data between terms.
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

// Handle POST requests
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $termName = trim($_POST['term_name'] ?? '');
                $startDate = trim($_POST['start_date'] ?? '');
                $endDate = trim($_POST['end_date'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $isCurrent = isset($_POST['is_current']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if (empty($termName)) {
                    $errors[] = 'Term name is required';
                } else {
                    // Check uniqueness
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}term_years WHERE term_name = ?",
                        [$termName],
                        's'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Term name already exists';
                    }
                }
                
                if (empty($errors)) {
                    // If setting as current, unset other current terms
                    if ($isCurrent) {
                        $db->query("UPDATE {$dbPrefix}term_years SET is_current = 0");
                    }
                    
                    $db->query(
                        "INSERT INTO {$dbPrefix}term_years (term_name, start_date, end_date, is_active, is_current, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$termName, $startDate ?: null, $endDate ?: null, $isActive, $isCurrent],
                        'sssii'
                    );
                    $successMessage = 'Term year added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['term_id'] ?? 0);
                $termName = trim($_POST['term_name'] ?? '');
                $startDate = trim($_POST['start_date'] ?? '');
                $endDate = trim($_POST['end_date'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $isCurrent = isset($_POST['is_current']) ? 1 : 0;
                
                // Validation
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid term ID';
                }
                if (empty($termName)) {
                    $errors[] = 'Term name is required';
                } else {
                    // Check uniqueness (excluding current record)
                    $result = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}term_years WHERE term_name = ? AND term_years_pk != ?",
                        [$termName, $id],
                        'si'
                    );
                    $row = $result->fetch();
                    if ($row['count'] > 0) {
                        $errors[] = 'Term name already exists';
                    }
                }
                
                if (empty($errors)) {
                    // If setting as current, unset other current terms
                    if ($isCurrent) {
                        $db->query("UPDATE {$dbPrefix}term_years SET is_current = 0");
                    }
                    
                    $db->query(
                        "UPDATE {$dbPrefix}term_years 
                         SET term_name = ?, start_date = ?, end_date = ?, is_active = ?, is_current = ?, updated_at = NOW()
                         WHERE term_years_pk = ?",
                        [$termName, $startDate ?: null, $endDate ?: null, $isActive, $isCurrent, $id],
                        'sssiii'
                    );
                    $successMessage = 'Term year updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['term_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}term_years 
                         SET is_active = NOT is_active, updated_at = NOW()
                         WHERE term_years_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Term status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['term_id'] ?? 0);
                if ($id > 0) {
                    // Check if term has associated programs
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}programs WHERE term_year_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete term: it has associated programs. Please use "Clear Term Data" option first or delete programs manually.';
                    } else {
                        $db->query(
                            "DELETE FROM {$dbPrefix}term_years WHERE term_years_pk = ?",
                            [$id],
                            'i'
                        );
                        $successMessage = 'Term year deleted successfully';
                    }
                }
                break;
                
            case 'copy_term':
                $sourceTermId = (int)($_POST['source_term_id'] ?? 0);
                $targetTermId = (int)($_POST['target_term_id'] ?? 0);
                
                if ($sourceTermId <= 0 || $targetTermId <= 0) {
                    $errorMessage = 'Invalid term selection';
                } elseif ($sourceTermId === $targetTermId) {
                    $errorMessage = 'Source and target terms must be different';
                } else {
                    // Begin transaction
                    $db->query("START TRANSACTION");
                    
                    try {
                        // Get source term name
                        $sourceTermResult = $db->query(
                            "SELECT term_name FROM {$dbPrefix}term_years WHERE term_years_pk = ?",
                            [$sourceTermId],
                            'i'
                        );
                        $sourceTerm = $sourceTermResult->fetch();
                        
                        $programsProcessed = 0;
                        $outcomesProcessed = 0;
                        $coursesProcessed = 0;
                        $sectionsProcessed = 0;
                        $slosProcessed = 0;
                        
                        // Get programs from source term
                        $programsResult = $db->query(
                            "SELECT program_code, program_name, degree_type, is_active FROM {$dbPrefix}programs WHERE term_year_fk = ?",
                            [$sourceTermId],
                            'i'
                        );
                        
                        while ($program = $programsResult->fetch()) {
                            // Insert or update program in target term
                            $db->query(
                                "INSERT INTO {$dbPrefix}programs (term_year_fk, program_code, program_name, degree_type, is_active, created_at, updated_at)
                                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                                 ON DUPLICATE KEY UPDATE 
                                 program_name = VALUES(program_name),
                                 degree_type = VALUES(degree_type),
                                 is_active = VALUES(is_active),
                                 updated_at = NOW()",
                                [$targetTermId, $program['program_code'], $program['program_name'], $program['degree_type'], $program['is_active']],
                                'isssi'
                            );
                            $programsProcessed++;
                            
                            // Get the program_pk in source and target terms
                            $sourceProgramResult = $db->query(
                                "SELECT programs_pk FROM {$dbPrefix}programs WHERE term_year_fk = ? AND program_code = ?",
                                [$sourceTermId, $program['program_code']],
                                'is'
                            );
                            $sourceProgram = $sourceProgramResult->fetch();
                            
                            $targetProgramResult = $db->query(
                                "SELECT programs_pk FROM {$dbPrefix}programs WHERE term_year_fk = ? AND program_code = ?",
                                [$targetTermId, $program['program_code']],
                                'is'
                            );
                            $targetProgram = $targetProgramResult->fetch();
                            
                            // Copy program outcomes
                            $outcomesResult = $db->query(
                                "SELECT outcome_code, outcome_description, institutional_outcomes_fk, sequence_num, is_active 
                                 FROM {$dbPrefix}program_outcomes WHERE program_fk = ?",
                                [$sourceProgram['programs_pk']],
                                'i'
                            );
                            
                            while ($outcome = $outcomesResult->fetch()) {
                                $db->query(
                                    "INSERT INTO {$dbPrefix}program_outcomes (program_fk, institutional_outcomes_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at)
                                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                                     ON DUPLICATE KEY UPDATE 
                                     outcome_description = VALUES(outcome_description),
                                     institutional_outcomes_fk = VALUES(institutional_outcomes_fk),
                                     sequence_num = VALUES(sequence_num),
                                     is_active = VALUES(is_active),
                                     updated_at = NOW()",
                                    [$targetProgram['programs_pk'], $outcome['institutional_outcomes_fk'], $outcome['outcome_code'], $outcome['outcome_description'], $outcome['sequence_num'], $outcome['is_active']],
                                    'iissii'
                                );
                                $outcomesProcessed++;
                            }
                            
                            // Copy courses for this program
                            $coursesResult = $db->query(
                                "SELECT course_name, course_number, is_active FROM {$dbPrefix}courses WHERE program_fk = ?",
                                [$sourceProgram['programs_pk']],
                                'i'
                            );
                            
                            while ($course = $coursesResult->fetch()) {
                                // Insert or update course in target program
                                $db->query(
                                    "INSERT INTO {$dbPrefix}courses (program_fk, course_name, course_number, is_active, created_at, updated_at)
                                     VALUES (?, ?, ?, ?, NOW(), NOW())
                                     ON DUPLICATE KEY UPDATE 
                                     course_name = VALUES(course_name),
                                     is_active = VALUES(is_active),
                                     updated_at = NOW()",
                                    [$targetProgram['programs_pk'], $course['course_name'], $course['course_number'], $course['is_active']],
                                    'issi'
                                );
                                $coursesProcessed++;
                                
                                // Get the course_pk in source and target programs
                                $sourceCourseResult = $db->query(
                                    "SELECT courses_pk FROM {$dbPrefix}courses WHERE program_fk = ? AND course_number = ?",
                                    [$sourceProgram['programs_pk'], $course['course_number']],
                                    'is'
                                );
                                $sourceCourse = $sourceCourseResult->fetch();
                                
                                $targetCourseResult = $db->query(
                                    "SELECT courses_pk FROM {$dbPrefix}courses WHERE program_fk = ? AND course_number = ?",
                                    [$targetProgram['programs_pk'], $course['course_number']],
                                    'is'
                                );
                                $targetCourse = $targetCourseResult->fetch();
                                
                                // Copy course sections
                                $sectionsResult = $db->query(
                                    "SELECT section_number, instructor_name, is_active FROM {$dbPrefix}course_sections WHERE course_fk = ?",
                                    [$sourceCourse['courses_pk']],
                                    'i'
                                );
                                
                                while ($section = $sectionsResult->fetch()) {
                                    // Generate new CRN for target term (course_number + section_number + target term pk)
                                    $newCrn = $course['course_number'] . '-' . $section['section_number'] . '-T' . $targetTermId;
                                    
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}course_sections (course_fk, crn, section_number, instructor_name, is_active, created_at, updated_at)
                                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                                         ON DUPLICATE KEY UPDATE 
                                         section_number = VALUES(section_number),
                                         instructor_name = VALUES(instructor_name),
                                         is_active = VALUES(is_active),
                                         updated_at = NOW()",
                                        [$targetCourse['courses_pk'], $newCrn, $section['section_number'], $section['instructor_name'], $section['is_active']],
                                        'isssi'
                                    );
                                    $sectionsProcessed++;
                                }
                                
                                // Copy SLOs
                                $slosResult = $db->query(
                                    "SELECT slo_code, slo_description, sequence_num, is_active FROM {$dbPrefix}student_learning_outcomes WHERE course_fk = ?",
                                    [$sourceCourse['courses_pk']],
                                    'i'
                                );
                                
                                while ($slo = $slosResult->fetch()) {
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, slo_code, slo_description, sequence_num, is_active, created_at, updated_at)
                                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                                         ON DUPLICATE KEY UPDATE 
                                         slo_description = VALUES(slo_description),
                                         sequence_num = VALUES(sequence_num),
                                         is_active = VALUES(is_active),
                                         updated_at = NOW()",
                                        [$targetCourse['courses_pk'], $slo['slo_code'], $slo['slo_description'], $slo['sequence_num'], $slo['is_active']],
                                        'issii'
                                    );
                                    $slosProcessed++;
                                }
                            }
                        }
                        
                        $db->query("COMMIT");
                        
                        $successMessage = "Term data copied successfully from '{$sourceTerm['term_name']}': {$programsProcessed} programs, {$outcomesProcessed} program outcomes, {$coursesProcessed} courses, {$sectionsProcessed} sections, {$slosProcessed} SLOs";
                    } catch (\Exception $e) {
                        $db->query("ROLLBACK");
                        throw $e;
                    }
                }
                break;
                
            case 'clear_term':
                $termId = (int)($_POST['term_id'] ?? 0);
                
                if ($termId <= 0) {
                    $errorMessage = 'Invalid term selection';
                } else {
                    // Begin transaction
                    $db->query("START TRANSACTION");
                    
                    try {
                        // Get term name for message
                        $termResult = $db->query(
                            "SELECT term_name FROM {$dbPrefix}term_years WHERE term_years_pk = ?",
                            [$termId],
                            'i'
                        );
                        $term = $termResult->fetch();
                        
                        // Get programs for this term
                        $programsResult = $db->query(
                            "SELECT programs_pk FROM {$dbPrefix}programs WHERE term_year_fk = ?",
                            [$termId],
                            'i'
                        );
                        
                        $programIds = [];
                        while ($program = $programsResult->fetch()) {
                            $programIds[] = $program['programs_pk'];
                        }
                        
                        $programsDeleted = count($programIds);
                        $outcomesDeleted = 0;
                        $coursesDeleted = 0;
                        
                        if (!empty($programIds)) {
                            $placeholders = implode(',', array_fill(0, count($programIds), '?'));
                            $types = str_repeat('i', count($programIds));
                            
                            // Get courses for these programs to delete sections/SLOs
                            $coursesResult = $db->query(
                                "SELECT courses_pk FROM {$dbPrefix}courses WHERE program_fk IN ({$placeholders})",
                                $programIds,
                                $types
                            );
                            
                            $courseIds = [];
                            while ($course = $coursesResult->fetch()) {
                                $courseIds[] = $course['courses_pk'];
                            }
                            $coursesDeleted = count($courseIds);
                            
                            if (!empty($courseIds)) {
                                $coursePlaceholders = implode(',', array_fill(0, count($courseIds), '?'));
                                $courseTypes = str_repeat('i', count($courseIds));
                                
                                // Delete SLOs for these courses
                                $db->query(
                                    "DELETE FROM {$dbPrefix}student_learning_outcomes WHERE course_fk IN ({$coursePlaceholders})",
                                    $courseIds,
                                    $courseTypes
                                );
                                
                                // Delete sections for these courses
                                $db->query(
                                    "DELETE FROM {$dbPrefix}course_sections WHERE course_fk IN ({$coursePlaceholders})",
                                    $courseIds,
                                    $courseTypes
                                );
                            }
                            
                            // Delete courses for these programs
                            $db->query(
                                "DELETE FROM {$dbPrefix}courses WHERE program_fk IN ({$placeholders})",
                                $programIds,
                                $types
                            );
                            
                            // Delete program outcomes for these programs
                            $outcomesResult = $db->query(
                                "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes WHERE program_fk IN ({$placeholders})",
                                $programIds,
                                $types
                            );
                            $outcomesRow = $outcomesResult->fetch();
                            $outcomesDeleted = $outcomesRow['count'];
                            
                            $db->query(
                                "DELETE FROM {$dbPrefix}program_outcomes WHERE program_fk IN ({$placeholders})",
                                $programIds,
                                $types
                            );
                            
                            // Delete programs
                            $db->query(
                                "DELETE FROM {$dbPrefix}programs WHERE term_year_fk = ?",
                                [$termId],
                                'i'
                            );
                        }
                        
                        $db->query("COMMIT");
                        
                        $successMessage = "All data cleared for term '{$term['term_name']}': {$programsDeleted} programs, {$outcomesDeleted} program outcomes, {$coursesDeleted} courses (and associated sections/SLOs) deleted";
                    } catch (\Exception $e) {
                        $db->query("ROLLBACK");
                        throw $e;
                    }
                }
                break;
                
            case 'import':
                if (isset($_FILES['term_years_upload']) && $_FILES['term_years_upload']['error'] === UPLOAD_ERR_OK) {
                    $csvFile = $_FILES['term_years_upload']['tmp_name'];
                    $handle = fopen($csvFile, 'r');
                    
                    if ($handle !== false) {
                        // Skip header row
                        fgetcsv($handle);
                        
                        $imported = 0;
                        $skipped = 0;
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 2) {
                                $termName = trim($row[0]);
                                $startDate = isset($row[1]) && !empty(trim($row[1])) ? trim($row[1]) : null;
                                $endDate = isset($row[2]) && !empty(trim($row[2])) ? trim($row[2]) : null;
                                $isActive = isset($row[3]) && strtolower(trim($row[3])) === 'active' ? 1 : 0;
                                $isCurrent = isset($row[4]) && strtolower(trim($row[4])) === 'yes' ? 1 : 0;
                                
                                if (!empty($termName)) {
                                    // Check if term exists
                                    $checkResult = $db->query(
                                        "SELECT term_years_pk FROM {$dbPrefix}term_years WHERE term_name = ?",
                                        [$termName],
                                        's'
                                    );
                                    
                                    if ($checkRow = $checkResult->fetch()) {
                                        // Update existing
                                        $db->query(
                                            "UPDATE {$dbPrefix}term_years 
                                             SET start_date = ?, end_date = ?, is_active = ?, is_current = ?, updated_at = NOW()
                                             WHERE term_years_pk = ?",
                                            [$startDate, $endDate, $isActive, $isCurrent, $checkRow['term_years_pk']],
                                            'ssiii'
                                        );
                                    } else {
                                        // Insert new
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}term_years (term_name, start_date, end_date, is_active, is_current, created_at, updated_at)
                                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                            [$termName, $startDate, $endDate, $isActive, $isCurrent],
                                            'ssiii'
                                        );
                                    }
                                    $imported++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        $successMessage = "Import completed: {$imported} records imported/updated, {$skipped} skipped";
                    } else {
                        $errorMessage = 'Failed to read CSV file';
                    }
                } else {
                    $errorMessage = 'No file uploaded or upload error occurred';
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
        if (DEBUG_MODE) {
            $errorMessage .= '<br><br><strong>Debug Information:</strong><br>';
            $errorMessage .= '<pre style="text-align: left; font-size: 12px;">';
            $errorMessage .= 'File: ' . htmlspecialchars($e->getFile()) . '<br>';
            $errorMessage .= 'Line: ' . htmlspecialchars((string)$e->getLine()) . '<br>';
            $errorMessage .= 'Trace:<br>' . htmlspecialchars($e->getTraceAsString());
            $errorMessage .= '</pre>';
        }
    }
}

// Fetch term years for operations
$allTermsResult = $db->query("SELECT * FROM {$dbPrefix}term_years ORDER BY start_date DESC");
$allTerms = $allTermsResult->fetchAll();

// Calculate statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_current = 1 THEN 1 ELSE 0 END) as current
    FROM {$dbPrefix}term_years
");
$stats = $statsResult->fetch();
$totalTerms = $stats['total'];
$activeTerms = $stats['active'];
$currentTerm = $stats['current'];

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Term Years',
    'currentPage' => 'admin_term_years',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Term Years']
    ]
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active">Term Years</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-calendar"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Terms</span>
                        <span class="info-box-number"><?= $totalTerms ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Terms</span>
                        <span class="info-box-number"><?= $activeTerms ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-star"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Current Term</span>
                        <span class="info-box-number"><?= $currentTerm > 0 ? 'Set' : 'None' ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Term Years Table -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar"></i> Term Years
                </h3>
                <div class="card-tools">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#copyTermModal">
                                <i class="fas fa-copy"></i> Copy Term Data
                            </a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#clearTermModal">
                                <i class="fas fa-trash"></i> Clear Term Data
                            </a></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-upload"></i> Import CSV
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTermModal">
                        <i class="fas fa-plus"></i> Add Term
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="termsTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Term Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Programs</th>
                            <th>Status</th>
                            <th>Current</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search ID"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Term"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Start"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search End"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Programs"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Status"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Current"></th>
                            <th><input type="text" class="form-control form-control-sm" placeholder="Search Created"></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Term Modal -->
<div class="modal fade" id="addTermModal" tabindex="-1" aria-labelledby="addTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTermModalLabel">
                        <i class="fas fa-plus"></i> Add Term Year
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="termName" class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="termName" name="term_name" 
                               required placeholder="e.g., 2025-2026">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isCurrent" name="is_current">
                            <label class="form-check-label" for="isCurrent">Set as Current Term</label>
                        </div>
                        <small class="form-text text-muted">Only one term can be current at a time</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Term
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Term Modal -->
<div class="modal fade" id="editTermModal" tabindex="-1" aria-labelledby="editTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="term_id" id="editTermId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTermModalLabel">
                        <i class="fas fa-edit"></i> Edit Term Year
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTermName" class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTermName" name="term_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editStartDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="editStartDate" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEndDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="editEndDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editIsActive" name="is_active">
                            <label class="form-check-label" for="editIsActive">Active</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editIsCurrent" name="is_current">
                            <label class="form-check-label" for="editIsCurrent">Set as Current Term</label>
                        </div>
                        <small class="form-text text-muted">Only one term can be current at a time</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Term
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Term Modal -->
<div class="modal fade" id="viewTermModal" tabindex="-1" aria-labelledby="viewTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTermModalLabel">
                    <i class="fas fa-eye"></i> View Term Year
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">ID:</dt>
                    <dd class="col-sm-8" id="viewId"></dd>
                    
                    <dt class="col-sm-4">Term Name:</dt>
                    <dd class="col-sm-8" id="viewTermName"></dd>
                    
                    <dt class="col-sm-4">Start Date:</dt>
                    <dd class="col-sm-8" id="viewStartDate"></dd>
                    
                    <dt class="col-sm-4">End Date:</dt>
                    <dd class="col-sm-8" id="viewEndDate"></dd>
                    
                    <dt class="col-sm-4">Programs:</dt>
                    <dd class="col-sm-8" id="viewPrograms"></dd>
                    
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8" id="viewStatus"></dd>
                    
                    <dt class="col-sm-4">Current:</dt>
                    <dd class="col-sm-8" id="viewCurrent"></dd>
                    
                    <dt class="col-sm-4">Created:</dt>
                    <dd class="col-sm-8" id="viewCreated"></dd>
                    
                    <dt class="col-sm-4">Updated:</dt>
                    <dd class="col-sm-8" id="viewUpdated"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Copy Term Data Modal -->
<div class="modal fade" id="copyTermModal" tabindex="-1" aria-labelledby="copyTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="copyTermForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="copy_term">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="copyTermModalLabel">
                        <i class="fas fa-copy"></i> Copy Term Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sourceTermId" class="form-label">Copy From (Source Term)</label>
                        <select class="form-select" id="sourceTermId" name="source_term_id" required>
                            <option value="">-- Select Source Term --</option>
                            <?php foreach ($allTerms as $term): ?>
                                <option value="<?= $term['term_years_pk'] ?>"><?= htmlspecialchars($term['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="targetTermId" class="form-label">Copy To (Target Term)</label>
                        <select class="form-select" id="targetTermId" name="target_term_id" required>
                            <option value="">-- Select Target Term --</option>
                            <?php foreach ($allTerms as $term): ?>
                                <option value="<?= $term['term_years_pk'] ?>"><?= htmlspecialchars($term['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will copy all programs, program outcomes, courses, course sections, and SLOs from the source term to the target term. Existing programs/courses in the target term will be updated.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmCopyTerm()">
                        <i class="fas fa-copy"></i> Copy Term Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Term Data Modal -->
<div class="modal fade" id="clearTermModal" tabindex="-1" aria-labelledby="clearTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="clearTermForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="clear_term">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="clearTermModalLabel">
                        <i class="fas fa-trash"></i> Clear Term Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="clearTermId" class="form-label">Select Term to Clear</label>
                        <select class="form-select" id="clearTermId" name="term_id" required>
                            <option value="">-- Select Term --</option>
                            <?php foreach ($allTerms as $term): ?>
                                <option value="<?= $term['term_years_pk'] ?>"><?= htmlspecialchars($term['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Danger:</strong> This will permanently delete ALL programs, program outcomes, courses, sections, and SLOs for the selected term. This action CANNOT be undone!
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmClearTerm()">
                        <i class="fas fa-trash"></i> Clear All Data for Selected Term
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="import">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">
                        <i class="fas fa-file-upload"></i> Import Term Years from CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="term_years_upload" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="term_years_upload" 
                               name="term_years_upload" accept=".csv" required>
                        <div class="form-text">
                            CSV format: Term Name, Start Date (YYYY-MM-DD), End Date (YYYY-MM-DD), Status (Active/Inactive), Is Current (Yes/No)
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Existing term years with matching names will be updated. New term years will be created.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden forms for toggle and delete -->
<form id="toggleStatusForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="term_id" id="toggleTermId">
</form>

<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="term_id" id="deleteTermId">
</form>

<?php $theme->showFooter($context); ?>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#termsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: 'term_years_data.php',
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        order: [[2, 'desc']],
        pageLength: 25,
        columnDefs: [
            { targets: [8], orderable: false },
            { targets: [0], visible: false }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search terms..."
        },
        initComplete: function() {
            this.api().columns([1, 2, 3, 4, 5, 6, 7]).every(function() {
                var column = this;
                var footer = $('input', this.footer());
                
                footer.on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function viewTerm(term) {
    $('#viewId').text(term.term_years_pk);
    $('#viewTermName').text(term.term_name);
    $('#viewStartDate').text(term.start_date || 'N/A');
    $('#viewEndDate').text(term.end_date || 'N/A');
    $('#viewPrograms').text(term.program_count || '0');
    $('#viewStatus').html('<span class="badge bg-' + (term.is_active ? 'success' : 'secondary') + '">' + 
                          (term.is_active ? 'Active' : 'Inactive') + '</span>');
    $('#viewCurrent').html('<span class="badge bg-' + (term.is_current ? 'primary' : 'secondary') + '">' + 
                          (term.is_current ? 'Yes' : 'No') + '</span>');
    $('#viewCreated').text(term.created_at || 'N/A');
    $('#viewUpdated').text(term.updated_at || 'N/A');
    new bootstrap.Modal(document.getElementById('viewTermModal')).show();
}

function editTerm(term) {
    $('#editTermId').val(term.term_years_pk);
    $('#editTermName').val(term.term_name);
    $('#editStartDate').val(term.start_date || '');
    $('#editEndDate').val(term.end_date || '');
    $('#editIsActive').prop('checked', term.is_active == 1);
    $('#editIsCurrent').prop('checked', term.is_current == 1);
    new bootstrap.Modal(document.getElementById('editTermModal')).show();
}

function toggleStatus(id, termName) {
    if (confirm('Are you sure you want to toggle the status of term "' + termName + '"?')) {
        $('#toggleTermId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteTerm(id, termName) {
    if (confirm('Are you sure you want to DELETE term "' + termName + '"?\n\nThis will fail if the term has associated programs. Use "Clear Term Data" first if needed.\n\nThis action cannot be undone.')) {
        $('#deleteTermId').val(id);
        $('#deleteForm').submit();
    }
}

function confirmCopyTerm() {
    var sourceSelect = document.getElementById('sourceTermId');
    var targetSelect = document.getElementById('targetTermId');
    
    if (!sourceSelect.value || !targetSelect.value) {
        alert('Please select both source and target terms');
        return;
    }
    
    if (sourceSelect.value === targetSelect.value) {
        alert('Source and target terms must be different');
        return;
    }
    
    var sourceTerm = sourceSelect.options[sourceSelect.selectedIndex].text;
    var targetTerm = targetSelect.options[targetSelect.selectedIndex].text;
    
    if (confirm('Are you sure you want to copy all programs, program outcomes, courses, sections, and SLOs from "' + sourceTerm + '" to "' + targetTerm + '"?\n\nExisting programs/courses in the target term will be updated.\n\nThis operation may take a while for large datasets.')) {
        document.getElementById('copyTermForm').submit();
    }
}

function confirmClearTerm() {
    var clearSelect = document.getElementById('clearTermId');
    
    if (!clearSelect.value) {
        alert('Please select a term to clear');
        return;
    }
    
    var termName = clearSelect.options[clearSelect.selectedIndex].text;
    
    if (confirm('WARNING: This will PERMANENTLY DELETE all programs, program outcomes, courses, sections, and SLOs for term "' + termName + '".\n\nThis action CANNOT be undone!\n\nAre you absolutely sure you want to continue?')) {
        if (confirm('FINAL CONFIRMATION: Delete ALL data for "' + termName + '"?\n\nType OK in the next prompt to continue.')) {
            var confirmation = prompt('Type "DELETE" to confirm:');
            if (confirmation === 'DELETE') {
                document.getElementById('clearTermForm').submit();
            } else {
                alert('Deletion cancelled - confirmation text did not match');
            }
        }
    }
}
</script>
