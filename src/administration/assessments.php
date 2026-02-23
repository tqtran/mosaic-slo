<?php
declare(strict_types=1);

/**
 * Assessments Administration
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
                $enrollmentFk = (int)($_POST['enrollment_fk'] ?? 0);
                $sloFk = (int)($_POST['student_learning_outcome_fk'] ?? 0);
                $scoreValue = trim($_POST['score_value'] ?? '');
                $achievementLevel = trim($_POST['achievement_level'] ?? '');
                $assessmentMethod = trim($_POST['assessment_method'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                $assessedDate = trim($_POST['assessed_date'] ?? '');
                $isFinalized = isset($_POST['is_finalized']) ? 1 : 0;
                
                $errors = [];
                if ($enrollmentFk <= 0) {
                    $errors[] = 'Enrollment is required';
                }
                if ($sloFk <= 0) {
                    $errors[] = 'Student Learning Outcome is required';
                }
                if ($scoreValue === '') {
                    $errors[] = 'Score is required';
                }
                if (!is_numeric($scoreValue)) {
                    $errors[] = 'Score must be numeric';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}assessments 
                         (enrollment_fk, student_learning_outcome_fk, score_value, achievement_level, assessment_method, notes, assessed_date, is_finalized, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$enrollmentFk, $sloFk, $scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized],
                        'iidssssi'
                    );
                    $successMessage = 'Assessment added successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['assessments_pk'] ?? 0);
                $enrollmentFk = (int)($_POST['enrollment_fk'] ?? 0);
                $sloFk = (int)($_POST['student_learning_outcome_fk'] ?? 0);
                $scoreValue = trim($_POST['score_value'] ?? '');
                $achievementLevel = trim($_POST['achievement_level'] ?? '');
                $assessmentMethod = trim($_POST['assessment_method'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                $assessedDate = trim($_POST['assessed_date'] ?? '');
                $isFinalized = isset($_POST['is_finalized']) ? 1 : 0;
                
                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'Invalid assessment PK';
                }
                if ($enrollmentFk <= 0) {
                    $errors[] = 'Enrollment is required';
                }
                if ($sloFk <= 0) {
                    $errors[] = 'Student Learning Outcome is required';
                }
                if ($scoreValue === '') {
                    $errors[] = 'Score is required';
                }
                if (!is_numeric($scoreValue)) {
                    $errors[] = 'Score must be numeric';
                }
                
                if (empty($errors)) {
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET enrollment_fk = ?, student_learning_outcome_fk = ?, 
                             score_value = ?, achievement_level = ?, assessment_method = ?, notes = ?, assessed_date = ?, is_finalized = ?, updated_at = NOW()
                         WHERE assessments_pk = ?",
                        [$enrollmentFk, $sloFk, $scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized, $id],
                        'iidssssi i'
                    );
                    $successMessage = 'Assessment updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['assessments_pk'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}assessments SET is_finalized = NOT is_finalized, updated_at = NOW() WHERE assessments_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Assessment status updated';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['assessments_pk'] ?? 0);
                if ($id > 0) {
                    $db->query("DELETE FROM {$dbPrefix}assessments WHERE assessments_pk = ?", [$id], 'i');
                    $successMessage = 'Assessment deleted successfully';
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
                
                // Expected columns: term_code,crn,c_number,slo_code,score_value,achievement_level,assessment_method,notes,assessed_date,is_finalized
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 4) continue; // Need at least term_code, crn, c_number, slo_code, score_value
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $termCode = trim($data['term_code'] ?? '');
                    $crn = trim($data['crn'] ?? '');
                    $cNumber = trim($data['c_number'] ?? '');
                    $sloCode = trim($data['slo_code'] ?? '');
                    $scoreValue = trim($data['score_value'] ?? '');
                    $achievementLevel = trim($data['achievement_level'] ?? 'pending');
                    $assessmentMethod = trim($data['assessment_method'] ?? '');
                    $notes = trim($data['notes'] ?? '');
                    $assessedDate = trim($data['assessed_date'] ?? '');
                    $isFinalized = isset($data['is_finalized']) ? ((int)$data['is_finalized'] === 1 || strtolower($data['is_finalized']) === 'true') : false;
                    
                    if (empty($termCode) || empty($crn) || empty($cNumber) || empty($sloCode) || $scoreValue === '') {
                        $errors[] = "Skipped row: missing required fields (term_code, crn, c_number, slo_code, or score_value)";
                        continue;
                    }
                    
                    // Lookup student by c_number
                    $result = $db->query(
                        "SELECT students_pk FROM {$dbPrefix}students WHERE c_number = ?",
                        [$cNumber],
                        's'
                    );
                    $studentRow = $result->fetch();
                    if (!$studentRow) {
                        $errors[] = "Skipped row: C-Number '$cNumber' not found";
                        continue;
                    }
                    $studentsFk = (int)$studentRow['students_pk'];
                    
                    // Lookup enrollment by term_code + crn + student_fk
                    $result = $db->query(
                        "SELECT e.enrollment_pk, cs.course_fk 
                         FROM {$dbPrefix}enrollment e
                         LEFT JOIN {$dbPrefix}course_sections cs ON e.course_section_fk = cs.course_sections_pk
                         WHERE e.term_code = ? AND e.crn = ? AND e.student_fk = ?",
                        [$termCode, $crn, $studentsFk],
                        'ssi'
                    );
                    $enrollmentRow = $result->fetch();
                    if (!$enrollmentRow) {
                        $errors[] = "Skipped row: Enrollment not found for term '$termCode', CRN '$crn', C-Number '$cNumber'";
                        continue;
                    }
                    $enrollmentFk = (int)$enrollmentRow['enrollment_pk'];
                    $courseFk = (int)$enrollmentRow['course_fk'];
                    
                    // Lookup student_learning_outcome_fk by course_fk + slo_code (if course_fk available)
                    if ($courseFk > 0) {
                        $result = $db->query(
                            "SELECT student_learning_outcomes_pk FROM {$dbPrefix}student_learning_outcomes WHERE course_fk = ? AND slo_code = ?",
                            [$courseFk, $sloCode],
                            'is'
                        );
                    } else {
                        // Fallback: lookup by slo_code only
                        $result = $db->query(
                            "SELECT student_learning_outcomes_pk FROM {$dbPrefix}student_learning_outcomes WHERE slo_code = ?",
                            [$sloCode],
                            's'
                        );
                    }
                    $sloRow = $result->fetch();
                    if (!$sloRow) {
                        $errors[] = "Skipped row: SLO code '$sloCode' not found";
                        continue;
                    }
                    $sloFk = (int)$sloRow['student_learning_outcomes_pk'];
                    
                    // Check if assessment exists (based on unique enrollment_fk + slo_fk)
                    $result = $db->query(
                        "SELECT assessments_pk FROM {$dbPrefix}assessments WHERE enrollment_fk = ? AND student_learning_outcome_fk = ?",
                        [$enrollmentFk, $sloFk],
                        'ii'
                    );
                    $existing = $result->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $db->query(
                            "UPDATE {$dbPrefix}assessments 
                             SET score_value = ?, achievement_level = ?, assessment_method = ?, notes = ?, assessed_date = ?, is_finalized = ?, updated_at = NOW() 
                             WHERE assessments_pk = ?",
                            [$scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized, $existing['assessments_pk']],
                            'dssssii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}assessments (enrollment_fk, student_learning_outcome_fk, score_value, achievement_level, assessment_method, notes, assessed_date, is_finalized, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$enrollmentFk, $sloFk, $scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized],
                            'iidssss i'
                        );
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

$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_finalized = 1 THEN 1 ELSE 0 END) as finalized
    FROM {$dbPrefix}assessments
");
$stats = $statsResult->fetch();
$totalAssessments = $stats['total'];
$activeAssessments = $stats['finalized'];

$enrollmentsResult = $db->query("
    SELECT e.enrollment_pk, e.term_code, e.crn, 
           s.c_number, s.student_first_name, s.student_last_name,
           cs.course_sections_pk, c.course_name
    FROM {$dbPrefix}enrollment e
    LEFT JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
    LEFT JOIN {$dbPrefix}course_sections cs ON e.course_section_fk = cs.course_sections_pk
    LEFT JOIN {$dbPrefix}courses c ON cs.course_fk = c.courses_pk
    WHERE e.enrollment_status IN ('enrolled', 'completed')
    ORDER BY e.term_code DESC, e.crn, s.student_last_name, s.student_first_name
");
$enrollments = $enrollmentsResult->fetchAll();

$slosResult = $db->query("
    SELECT slo.student_learning_outcomes_pk, slo.slo_code, slo.slo_description, c.course_name, c.course_number
    FROM {$dbPrefix}student_learning_outcomes slo
    LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
    WHERE slo.is_active = 1
    ORDER BY c.course_name, slo.slo_code
");
$slos = $slosResult->fetchAll();

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Assessment Management',
    'currentPage' => 'admin_assessments',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Assessments']
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
                    <li class="breadcrumb-item active">Assessments</li>
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
        
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-clipboard-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Assessments</span>
                        <span class="info-box-number"><?= $totalAssessments ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-circle-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Finalized Assessments</span>
                        <span class="info-box-number"><?= $activeAssessments ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table"></i> Assessments</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAssessmentModal">
                        <i class="fas fa-plus"></i> Add Assessment
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="assessmentsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>PK</th>
                            <th>Term</th>
                            <th>CRN</th>
                            <th>Student</th>
                            <th>SLO</th>
                            <th>Score</th>
                            <th>Achievement</th>
                            <th>Date</th>
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

<!-- Add Assessment Modal -->
<div class="modal fade" id="addAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="enrollmentFk" class="form-label">Enrollment (Term CRN - Student) <span class="text-danger">*</span></label>
                            <select class="form-select" id="enrollmentFk" name="enrollment_fk" required>
                                <option value="">Select Enrollment</option>
                                <?php foreach ($enrollments as $enrollment): 
                                    $studentName = htmlspecialchars(($enrollment['student_last_name'] ?? '') . ', ' . ($enrollment['student_first_name'] ?? ''));
                                    $courseName = htmlspecialchars($enrollment['course_name'] ?? 'Course');
                                    $displayText = htmlspecialchars($enrollment['term_code']) . ' ' . htmlspecialchars($enrollment['crn']) . ' - ' . htmlspecialchars($enrollment['c_number'] ?? '') . ' (' . trim($studentName, ', ') . ') - ' . $courseName;
                                ?>
                                    <option value="<?= $enrollment['enrollment_pk'] ?>">
                                        <?= $displayText ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="sloFk" class="form-label">Student Learning Outcome <span class="text-danger">*</span></label>
                            <select class="form-select" id="sloFk" name="student_learning_outcome_fk" required>
                                <option value="">Select SLO</option>
                                <?php foreach ($slos as $slo): ?>
                                    <option value="<?= $slo['student_learning_outcomes_pk'] ?>">
                                        <?= htmlspecialchars($slo['course_name']) ?> - <?= htmlspecialchars($slo['slo_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="scoreValue" class="form-label">Score <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="scoreValue" name="score_value" required>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="achievementLevel" class="form-label">Achievement Level</label>
                            <select class="form-select" id="achievementLevel" name="achievement_level">
                                <option value="">Select Level</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Satisfactory">Satisfactory</option>
                                <option value="Needs Improvement">Needs Improvement</option>
                                <option value="Unsatisfactory">Unsatisfactory</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="assessmentMethod" class="form-label">Assessment Method</label>
                            <input type="text" class="form-control" id="assessmentMethod" name="assessment_method" maxlength="100">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="assessedDate" class="form-label">Assessed Date</label>
                            <input type="date" class="form-control" id="assessedDate" name="assessed_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isFinalized" name="is_finalized" checked>
                        <label class="form-check-label" for="isFinalized">Finalized</label>
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

<!-- Edit Assessment Modal -->
<div class="modal fade" id="editAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="assessments_pk" id="editAssessmentPk">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="editEnrollmentFk" class="form-label">Enrollment (Term CRN - Student) <span class="text-danger">*</span></label>
                            <select class="form-select" id="editEnrollmentFk" name="enrollment_fk" required>
                                <option value="">Select Enrollment</option>
                                <?php foreach ($enrollments as $enrollment): 
                                    $studentName = htmlspecialchars(($enrollment['student_last_name'] ?? '') . ', ' . ($enrollment['student_first_name'] ?? ''));
                                    $courseName = htmlspecialchars($enrollment['course_name'] ?? 'Course');
                                    $displayText = htmlspecialchars($enrollment['term_code']) . ' ' . htmlspecialchars($enrollment['crn']) . ' - ' . htmlspecialchars($enrollment['c_number'] ?? '') . ' (' . trim($studentName, ', ') . ') - ' . $courseName;
                                ?>
                                    <option value="<?= $enrollment['enrollment_pk'] ?>">
                                        <?= $displayText ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="editSloFk" class="form-label">Student Learning Outcome <span class="text-danger">*</span></label>
                            <select class="form-select" id="editSloFk" name="student_learning_outcome_fk" required>
                                <option value="">Select SLO</option>
                                <?php foreach ($slos as $slo): ?>
                                    <option value="<?= $slo['student_learning_outcomes_pk'] ?>">
                                        <?= htmlspecialchars($slo['course_name']) ?> - <?= htmlspecialchars($slo['slo_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="editScoreValue" class="form-label">Score <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="editScoreValue" name="score_value" required>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="editAchievementLevel" class="form-label">Achievement Level</label>
                            <select class="form-select" id="editAchievementLevel" name="achievement_level">
                                <option value="">Select Level</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Satisfactory">Satisfactory</option>
                                <option value="Needs Improvement">Needs Improvement</option>
                                <option value="Unsatisfactory">Unsatisfactory</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="editAssessmentMethod" class="form-label">Assessment Method</label>
                            <input type="text" class="form-control" id="editAssessmentMethod" name="assessment_method" maxlength="100">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="editAssessedDate" class="form-label">Assessed Date</label>
                            <input type="date" class="form-control" id="editAssessedDate" name="assessed_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsFinalized" name="is_finalized">
                        <label class="form-check-label" for="editIsFinalized">Finalized</label>
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

<form id="toggleStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="assessments_pk" id="toggleAssessmentPk">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="assessments_pk" id="deleteAssessmentPk">
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
$(document).ready(function() {
    $('#assessmentsTable thead tr:eq(1) th').each(function(i) {
        var title = $('#assessmentsTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title !== 'Actions') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#assessmentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/assessments_data.php',
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        columns: [
            { data: 0, name: 'assessments_pk' },
            { data: 1, name: 'term_code' },
            { data: 2, name: 'crn' },
            { data: 3, name: 'student_name' },
            { data: 4, name: 'slo_code' },
            { data: 5, name: 'score_value' },
            { data: 6, name: 'achievement_level' },
            { data: 7, name: 'assessed_date' },
            { data: 8, name: 'is_active' },
            { data: 9, name: 'actions', orderable: false, searchable: false }
        ],
        order: [[7, 'desc']],
        initComplete: function() {
            this.api().columns().every(function() {
                var column = this;
                $('input', this.header()).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function editAssessment(assessment) {
    $('#editAssessmentPk').val(assessment.assessments_pk);
    $('#editEnrollmentFk').val(assessment.enrollment_fk);
    $('#editSloFk').val(assessment.student_learning_outcome_fk);
    $('#editScoreValue').val(assessment.score_value);
    $('#editAchievementLevel').val(assessment.achievement_level);
    $('#editAssessmentMethod').val(assessment.assessment_method);
    $('#editNotes').val(assessment.notes);
    $('#editAssessedDate').val(assessment.assessed_date);
    $('#editIsFinalized').prop('checked', assessment.is_finalized == 1);
    new bootstrap.Modal(document.getElementById('editAssessmentModal')).show();
}

function toggleStatus(id, identifier) {
    if (confirm('Are you sure you want to toggle the status of assessment #' + id + '?')) {
        $('#toggleAssessmentPk').val(id);
        $('#toggleStatusForm').submit();
    }
}

function deleteAssessment(id, identifier) {
    if (confirm('Are you sure you want to DELETE assessment #' + id + '? This action cannot be undone.')) {
        $('#deleteAssessmentPk').val(id);
        $('#deleteForm').submit();
    }
}
</script>
