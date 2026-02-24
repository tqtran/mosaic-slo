<?php
declare(strict_types=1);

/**
 * Student Learning Outcomes (S Los) Administration
 * 
 * @package Mosaic
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $programOutcomesFk = !empty($_POST['program_outcomes_fk']) ? (int)$_POST['program_outcomes_fk'] : null;
                $sloCode = trim($_POST['slo_code'] ?? '');
                $sloDescription = trim($_POST['slo_description'] ?? '');
                $assessmentMethod = trim($_POST['assessment_method'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if (empty($sloCode)) {
                    $errors[] = 'SLO code is required';
                }
                if (empty($sloDescription)) {
                    $errors[] = 'SLO description is required';
                }
                
                if (empty($errors)) {
                    if ($programOutcomesFk !== null) {
                        $db->query(
                            "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, program_outcomes_fk, slo_code, slo_description, assessment_method, sequence_num, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$courseFk, $programOutcomesFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive],
                            'iisssii'
                        );
                    } else {
                        $db->query(
                            "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, slo_code, slo_description, assessment_method, sequence_num, is_active, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$courseFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive],
                            'isssii'
                        );
                    }
                    $successMessage = 'SLO added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['slo_id'] ?? 0);
                $courseFk = (int)($_POST['course_fk'] ?? 0);
                $programOutcomesFk = !empty($_POST['program_outcomes_fk']) ? (int)$_POST['program_outcomes_fk'] : null;
                $sloCode = trim($_POST['slo_code'] ?? '');
                $sloDescription = trim($_POST['slo_description'] ?? '');
                $assessmentMethod = trim($_POST['assessment_method'] ?? '');
                $sequenceNum = (int)($_POST['sequence_num'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid SLO ID';
                }
                if ($courseFk <= 0) {
                    $errors[] = 'Course is required';
                }
                if (empty($sloCode)) {
                    $errors[] = 'SLO code is required';
                }
                if (empty($sloDescription)) {
                    $errors[] = 'SLO description is required';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}student_learning_outcomes 
                         SET course_fk = ?, program_outcomes_fk = ?, slo_code = ?, slo_description = ?, assessment_method = ?, sequence_num = ?, is_active = ?, updated_at = NOW()
                         WHERE student_learning_outcomes_pk = ?",
                        [$courseFk, $programOutcomesFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive, $id],
                        'iisssiii'
                    );
                    $successMessage = 'SLO updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['slo_id'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}student_learning_outcomes SET is_active = NOT is_active, updated_at = NOW() WHERE student_learning_outcomes_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'SLO status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['slo_id'] ?? 0);
                if ($id > 0) {
                    $checkResult = $db->query(
                        "SELECT COUNT(*) as count FROM {$dbPrefix}assessments WHERE student_learning_outcome_fk = ?",
                        [$id],
                        'i'
                    );
                    $checkRow = $checkResult->fetch();
                    
                    if ($checkRow['count'] > 0) {
                        $errorMessage = 'Cannot delete SLO: it has associated assessments.';
                    } else {
                        $db->query("DELETE FROM {$dbPrefix}student_learning_outcomes WHERE student_learning_outcomes_pk = ?", [$id], 'i');
                        $successMessage = 'SLO deleted successfully';
                    }
                }
                break;
                
            case 'import':
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Please select a valid CSV file';
                    break;
                }
                
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if ($handle === false) {
                    $errorMessage = 'Failed to open CSV file';
                    break;
                }
                
                // Read header
                $headers = fgetcsv($handle);
                if ($headers === false) {
                    $errorMessage = 'Invalid CSV file format';
                    fclose($handle);
                    break;
                }
                
                // Strip UTF-8 BOM if present
                if (!empty($headers[0])) {
                    $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
                }
                
                // Expected columns: course_number,program_outcome_code,slo_code,slo_description,assessment_method,sequence_num,is_active
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue; // Need at least course_number, slo_code, slo_description
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $courseNumber = trim($data['course_number'] ?? '');
                    $programOutcomeCode = trim($data['program_outcome_code'] ?? '');
                    $sloCode = trim($data['slo_code'] ?? '');
                    $sloDescription = trim($data['slo_description'] ?? '');
                    $assessmentMethod = trim($data['assessment_method'] ?? '');
                    $sequenceNum = (int)($data['sequence_num'] ?? 0);
                    $isActive = isset($data['is_active']) ? ((int)$data['is_active'] === 1 || strtolower($data['is_active']) === 'true') : true;
                    
                    if (empty($courseNumber) || empty($sloCode) || empty($sloDescription)) {
                        $errors[] = "Skipped row: missing required fields (course_number, slo_code, or slo_description)";
                        continue;
                    }
                    
                    // Lookup course_fk by course_number
                    $result = $db->query(
                        "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ?",
                        [$courseNumber],
                        's'
                    );
                    $courseRow = $result->fetch();
                    if (!$courseRow) {
                        $errors[] = "Skipped row: course number '$courseNumber' not found";
                        continue;
                    }
                    $courseFk = (int)$courseRow['courses_pk'];
                    
                    // Lookup program_outcomes_fk by code (optional/nullable)
                    $programOutcomesFk = null;
                    if (!empty($programOutcomeCode)) {
                        $result = $db->query(
                            "SELECT program_outcomes_pk FROM {$dbPrefix}program_outcomes WHERE code = ?",
                            [$programOutcomeCode],
                            's'
                        );
                        $programOutcomeRow = $result->fetch();
                        if (!$programOutcomeRow) {
                            $errors[] = "Warning: program outcome code '$programOutcomeCode' not found, skipping program_outcomes_fk";
                        } else {
                            $programOutcomesFk = (int)$programOutcomeRow['program_outcomes_pk'];
                        }
                    }
                    
                    // Check if SLO exists (based on unique course_fk + slo_code)
                    $result = $db->query(
                        "SELECT student_learning_outcomes_pk FROM {$dbPrefix}student_learning_outcomes WHERE course_fk = ? AND slo_code = ?",
                        [$courseFk, $sloCode],
                        'is'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $db->query(
                            "UPDATE {$dbPrefix}student_learning_outcomes 
                             SET program_outcomes_fk = ?, slo_description = ?, assessment_method = ?, sequence_num = ?, is_active = ?, updated_at = NOW() 
                             WHERE student_learning_outcomes_pk = ?",
                            [$programOutcomesFk, $sloDescription, $assessmentMethod, $sequenceNum, $isActive, $existing['student_learning_outcomes_pk']],
                            'issiii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        if ($programOutcomesFk !== null) {
                            $db->query(
                                "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, program_outcomes_fk, slo_code, slo_description, assessment_method, sequence_num, is_active, created_at, updated_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                [$courseFk, $programOutcomesFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive],
                                'iisssii'
                            );
                        } else {
                            $db->query(
                                "INSERT INTO {$dbPrefix}student_learning_outcomes (course_fk, slo_code, slo_description, assessment_method, sequence_num, is_active, created_at, updated_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                [$courseFk, $sloCode, $sloDescription, $assessmentMethod, $sequenceNum, $isActive],
                                'isssii'
                            );
                        }
                        $imported++;
                    }
                }
                
                fclose($handle);
                
                if ($imported > 0 || $updated > 0) {
                    $successMessage = "Import complete: $imported new, $updated updated";
                    if (!empty($errors)) {
                        $successMessage .= '<br>Warnings: ' . implode('<br>', array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $successMessage .= '<br>... and ' . (count($errors) - 5) . ' more';
                        }
                    }
                } else {
                    $errorMessage = 'No records imported. ' . implode('<br>', $errors);
                }
                break;
        }
    } catch (\Exception $e) {
        $errorMessage = 'Operation failed: ' . htmlspecialchars($e->getMessage());
    }
}

// Get terms for dropdown (sorted descending with latest first)
$termsResult = $db->query("
    SELECT terms_pk, term_code, term_name, academic_year
    FROM {$dbPrefix}terms
    WHERE is_active = 1
    ORDER BY term_name DESC
");
$terms = $termsResult->fetchAll();

// Get selected term (from GET/session, or default to latest)
$selectedTermFk = getSelectedTermFk();
if (!$selectedTermFk && !empty($terms)) {
    $selectedTermFk = $terms[0]['terms_pk'];
}

// Calculate statistics (filtered by term through courses)
if ($selectedTermFk) {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN slo.is_active = 1 THEN 1 ELSE 0 END) as active
        FROM {$dbPrefix}student_learning_outcomes slo
        LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
        WHERE c.term_fk = ?
    ", [$selectedTermFk], 'i');
} else {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN slo.is_active = 1 THEN 1 ELSE 0 END) as active
        FROM {$dbPrefix}student_learning_outcomes slo
        LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
    ");
}
$stats = $statsResult->fetch();
$totalSLOs = $stats['total'];
$activeSLOs = $stats['active'];

// Get courses for dropdown
$coursesResult = $db->query("SELECT courses_pk, course_name, course_number FROM {$dbPrefix}courses WHERE is_active = 1 ORDER BY course_name");
$courses = $coursesResult->fetchAll();

// Get program outcomes for dropdown
$programOutcomesResult = $db->query("SELECT program_outcomes_pk, outcome_code, outcome_description FROM {$dbPrefix}program_outcomes WHERE is_active = 1 ORDER BY outcome_code");
$programOutcomes = $programOutcomesResult->fetchAll();

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Student Learning Outcomes',
    'currentPage' => 'admin_slos',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'SLOs']
    ]
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active">Student Learning Outcomes</li>
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
        
        <!-- Filter and Statistics Row -->
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <label for="termFilter" class="form-label"><i class="fas fa-filter"></i> Filter by Term</label>
                        <select id="termFilter" class="form-select">
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['terms_pk'] ?>" <?= $term['terms_pk'] == $selectedTermFk ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($term['term_name']) ?>
                                    <?= !empty($term['academic_year']) ? ' (' . htmlspecialchars($term['academic_year']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-list-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total SLOs</span>
                        <span class="info-box-number"><?= $totalSLOs ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active SLOs</span>
                        <span class="info-box-number"><?= $activeSLOs ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Student Learning Outcomes</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSLOModal">
                        <i class="fas fa-plus"></i> Add SLO
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="slosTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course</th>
                            <th>Program Outcome</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Sequence</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add SLO Modal -->
<div class="modal fade" id="addSLOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Student Learning Outcome</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="courseFk" class="form-label">Course</label>
                        <select class="form-select" id="courseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="programOutcomesFk" class="form-label">Program Outcome <small class="text-muted">(Optional)</small></label>
                        <select class="form-select" id="programOutcomesFk" name="program_outcomes_fk">
                            <option value="">None</option>
                            <?php foreach ($programOutcomes as $po): ?>
                                <option value="<?= $po['program_outcomes_pk'] ?>">
                                    <?= htmlspecialchars($po['outcome_code']) ?> - <?= htmlspecialchars(substr($po['outcome_description'], 0, 60)) ?><?= strlen($po['outcome_description']) > 60 ? '...' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Links this SLO to a program-level outcome (optional)</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sloCode" class="form-label">SLO Code</label>
                            <input type="text" class="form-control" id="sloCode" name="slo_code" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="sequenceNum" name="sequence_num" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sloDescription" class="form-label">SLO Description</label>
                        <textarea class="form-control" id="sloDescription" name="slo_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assessmentMethod" class="form-label">Assessment Method</label>
                        <textarea class="form-control" id="assessmentMethod" name="assessment_method" rows="2" maxlength="255" placeholder="e.g., Final exam, Portfolio review, Lab practical"></textarea>
                        <div class="form-text">Describe how this SLO is assessed (optional)</div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit SLO Modal -->
<div class="modal fade" id="editSLOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student Learning Outcome</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="slo_id" id="editSLOId">
                    
                    <div class="mb-3">
                        <label for="editCourseFk" class="form-label">Course</label>
                        <select class="form-select" id="editCourseFk" name="course_fk" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courses_pk'] ?>">
                                    <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editProgramOutcomesFk" class="form-label">Program Outcome <small class="text-muted">(Optional)</small></label>
                        <select class="form-select" id="editProgramOutcomesFk" name="program_outcomes_fk">
                            <option value="">None</option>
                            <?php foreach ($programOutcomes as $po): ?>
                                <option value="<?= $po['program_outcomes_pk'] ?>">
                                    <?= htmlspecialchars($po['outcome_code']) ?> - <?= htmlspecialchars(substr($po['outcome_description'], 0, 60)) ?><?= strlen($po['outcome_description']) > 60 ? '...' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Links this SLO to a program-level outcome (optional)</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editSLOCode" class="form-label">SLO Code</label>
                            <input type="text" class="form-control" id="editSLOCode" name="slo_code" maxlength="50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSequenceNum" class="form-label">Sequence Number</label>
                            <input type="number" class="form-control" id="editSequenceNum" name="sequence_num">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editSLODescription" class="form-label">SLO Description</label>
                        <textarea class="form-control" id="editSLODescription" name="slo_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAssessmentMethod" class="form-label">Assessment Method</label>
                        <textarea class="form-control" id="editAssessmentMethod" name="assessment_method" rows="2" maxlength="255" placeholder="e.g., Final exam, Portfolio review, Lab practical"></textarea>
                        <div class="form-text">Describe how this SLO is assessed (optional)</div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Import SLOs from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>CSV Format:</strong><br>
                        <code>course_number,program_outcome_code,slo_code,slo_description,assessment_method,sequence_num,is_active</code><br>
                        <small class="text-muted">program_outcome_code is optional (can be empty). assessment_method is optional. sequence_num defaults to 0. is_active should be 1/0 or true/false (default: true)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="slo_id" id="toggleSLOId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="slo_id" id="deleteSLOId">
</form>

<?php $theme->showFooter($context); ?>

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
// Convert PHP arrays to JavaScript
var courses = <?= json_encode(array_map(function($c) { 
    return ['name' => $c['course_name'], 'number' => $c['course_number']]; 
}, $courses)) ?>;
var programOutcomes = <?= json_encode(array_map(function($po) { 
    return ['code' => $po['outcome_code']]; 
}, $programOutcomes)) ?>;

$(document).ready(function() {
    // Sync local term filter with header selector
    var selectedTerm = '<?= $selectedTermFk ?? '' ?>';
    if (selectedTerm) {
        $('#termFilter').val(selectedTerm);
        $('#headerTermSelector').val(selectedTerm);
    }
    
    // Term filter change handler
    $('#termFilter').on('change', function() {
        var termFk = $(this).val();
        window.location.href = '<?= BASE_URL ?>administration/student_learning_outcomes.php?term_fk=' + termFk;
    });
    
    $('#slosTable thead tr:eq(1) th').each(function(i) {
        var title = $('#slosTable thead tr:eq(0) th:eq(' + i + ')').text();
        
        // Course column (index 1)
        if (title === 'Course') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Courses</option></select>')
                .appendTo($(this).empty());
            courses.forEach(function(course) {
                select.append('<option value="' + course.name + '">' + course.name + '</option>');
            });
        }
        // Program Outcome column (index 2)
        else if (title === 'Program Outcome') {
            var select = $('<select class="form-select form-select-sm"><option value="">All Program Outcomes</option></select>')
                .appendTo($(this).empty());
            programOutcomes.forEach(function(po) {
                if (po.code) {
                    select.append('<option value="' + po.code + '">' + po.code + '</option>');
                }
            });
        }
        else if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#slosTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/student_learning_outcomes_data.php',
            data: function(d) {
                d.term_fk = $('#termFilter').val();
            }
        },
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'student_learning_outcomes_pk' },
            { data: 1, name: 'course_name' },
            { data: 2, name: 'program_outcome_code' },
            { data: 3, name: 'slo_code' },
            { data: 4, name: 'slo_description' },
            { data: 5, name: 'sequence_num' },
            { data: 6, name: 'is_active' },
            { data: 7, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
            this.api().columns().every(function() {
                var column = this;
                $('select', this.header()).on('change', function() {
                    column.search($(this).val()).draw();
                });
                $('input', this.header()).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function editSLO(slo) {
    $('#editSLOId').val(slo.student_learning_outcomes_pk);
    $('#editCourseFk').val(slo.course_fk);
    $('#editProgramOutcomesFk').val(slo.program_outcomes_fk || '');
    $('#editSLOCode').val(slo.slo_code);
    $('#editSLODescription').val(slo.slo_description);
    $('#editAssessmentMethod').val(slo.assessment_method || '');
    $('#editSequenceNum').val(slo.sequence_num);
    $('#editIsActive').prop('checked', slo.is_active == 1);
    new bootstrap.Modal(document.getElementById('editSLOModal')).show();
}

function toggleStatus(id, code) {
    if (confirm('Are you sure you want to toggle the status of SLO "' + code + '"?')) {
        $('#toggleSLOId').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteSLO(id, code) {
    if (confirm('Are you sure you want to DELETE SLO "' + code + '"? This action cannot be undone.')) {
        $('#deleteSLOId').val(id);
        $('#deleteForm').submit();
    }
}
</script>
