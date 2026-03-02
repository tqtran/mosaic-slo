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
                         (enrollment_fk, student_learning_outcome_fk, score_value, achievement_level, 
                          assessment_method, notes, assessed_date, is_finalized, created_at, updated_at) 
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
                    $errors[] = 'Invalid assessment ID';
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
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET enrollment_fk = ?, student_learning_outcome_fk = ?, score_value = ?, 
                             achievement_level = ?, assessment_method = ?, notes = ?, 
                             assessed_date = ?, is_finalized = ?, updated_at = NOW(), updated_by_fk = ?
                         WHERE assessments_pk = ?",
                        [$enrollmentFk, $sloFk, $scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized, $userId, $id],
                        'iidssssiii'
                    );
                    $successMessage = 'Assessment updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle':
                $id = (int)($_POST['assessments_pk'] ?? 0);
                if ($id > 0) {
                    $userId = $_SESSION['user_id'] ?? null;
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET is_finalized = NOT is_finalized, updated_at = NOW(), updated_by_fk = ?
                         WHERE assessments_pk = ?",
                        [$userId, $id],
                        'ii'
                    );
                    $successMessage = 'Assessment status toggled successfully';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['assessments_pk'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "DELETE FROM {$dbPrefix}assessments WHERE assessments_pk = ?",
                        [$id],
                        'i'
                    );
                    $successMessage = 'Assessment deleted successfully';
                }
                break;
                
            case 'import':
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Please select a CSV file to upload';
                    break;
                }
                
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                if (!$handle) {
                    $errorMessage = 'Failed to open CSV file';
                    break;
                }
                
                $headers = fgetcsv($handle);
                if ($headers === false || count($headers) < 5) {
                    fclose($handle);
                    $errorMessage = 'Invalid CSV format. Expected headers: term_code,crn,student_id,slo_code,score_value,...';
                    break;
                }
                
                // Strip UTF-8 BOM if present
                if (!empty($headers[0])) {
                    $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
                }
                
                // Expected columns: term_code,crn,student_id,slo_code,score_value,achievement_level,assessment_method,notes,assessed_date,is_finalized
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 4) continue; // Need at least term_code, crn, student_id, slo_code, score_value
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $termCode = trim($data['term_code'] ?? '');
                    $crn = trim($data['crn'] ?? '');
                    $studentId = trim($data['student_id'] ?? '');
                    $sloCode = trim($data['slo_code'] ?? '');
                    $scoreValue = trim($data['score_value'] ?? '');
                    $achievementLevel = trim($data['achievement_level'] ?? 'pending');
                    $assessmentMethod = trim($data['assessment_method'] ?? '');
                    $notes = trim($data['notes'] ?? '');
                    $assessedDate = trim($data['assessed_date'] ?? '');
                    $isFinalized = isset($data['is_finalized']) ? ((int)$data['is_finalized'] === 1 || strtolower($data['is_finalized']) === 'true') : false;
                    
                    if (empty($termCode) || empty($crn) || empty($studentId) || empty($sloCode) || $scoreValue === '') {
                        $errors[] = "Skipped row: missing required fields (term_code, crn, student_id, slo_code, or score_value)";
                        continue;
                    }
                    
                    // Lookup student by student_id
                    $result = $db->query(
                        "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ?",
                        [$studentId],
                        's'
                    );
                    $studentRow = $result->fetch();
                    if (!$studentRow) {
                        $errors[] = "Skipped row: Student ID '$studentId' not found";
                        continue;
                    }
                    $studentsFk = (int)$studentRow['students_pk'];
                    
                    // Lookup enrollment
                    $enrollmentResult = $db->query(
                        "SELECT enrollment_pk FROM {$dbPrefix}enrollment 
                         WHERE term_code = ? AND crn = ? AND student_fk = ?",
                        [$termCode, $crn, $studentsFk],
                        'ssi'
                    );
                    $enrollmentRow = $enrollmentResult->fetch();
                    if (!$enrollmentRow) {
                        $errors[] = "Skipped row: Enrollment not found for student '$studentId' in term '$termCode' CRN '$crn'";
                        continue;
                    }
                    $enrollmentFk = (int)$enrollmentRow['enrollment_pk'];
                    
                    // Lookup SLO by code
                    $sloResult = $db->query(
                        "SELECT student_learning_outcomes_pk FROM {$dbPrefix}student_learning_outcomes 
                         WHERE slo_code = ?",
                        [$sloCode],
                        's'
                    );
                    $sloRow = $sloResult->fetch();
                    if (!$sloRow) {
                        $errors[] = "Skipped row: SLO code '$sloCode' not found";
                        continue;
                    }
                    $sloFk = (int)$sloRow['student_learning_outcomes_pk'];
                    
                    // Check if assessment already exists
                    $checkResult = $db->query(
                        "SELECT assessments_pk FROM {$dbPrefix}assessments 
                         WHERE enrollment_fk = ? AND student_learning_outcome_fk = ?",
                        [$enrollmentFk, $sloFk],
                        'ii'
                    );
                    $existing = $checkResult->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $userId = $_SESSION['user_id'] ?? null;
                        $db->query(
                            "UPDATE {$dbPrefix}assessments 
                             SET score_value = ?, achievement_level = ?, assessment_method = ?, 
                                 notes = ?, assessed_date = ?, is_finalized = ?, updated_at = NOW(), updated_by_fk = ? 
                             WHERE assessments_pk = ?",
                            [$scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized ? 1 : 0, $userId, $existing['assessments_pk']],
                            'dssssiii'
                        );
                        $updated++;
                    } else {
                        // Insert new
                        $db->query(
                            "INSERT INTO {$dbPrefix}assessments 
                             (enrollment_fk, student_learning_outcome_fk, score_value, achievement_level, 
                              assessment_method, notes, assessed_date, is_finalized, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                            [$enrollmentFk, $sloFk, $scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized ? 1 : 0],
                            'iidssssi'
                        );
                        $imported++;
                    }
                }
                
                fclose($handle);
                
                if ($imported > 0 || $updated > 0) {
                    $successMessage = "CSV imported successfully: {$imported} new assessments, {$updated} updated";
                    if (count($errors) > 0) {
                        $successMessage .= '<br><strong>Warnings:</strong><br>' . implode('<br>', array_slice($errors, 0, 10));
                        if (count($errors) > 10) {
                            $successMessage .= '<br>... and ' . (count($errors) - 10) . ' more warnings';
                        }
                    }
                } else {
                    $errorMessage = 'No assessments were imported. Errors:<br>' . implode('<br>', array_slice($errors, 0, 10));
                }
                break;
        }
    } catch (\Exception $e) {
        error_log("Assessments error: " . $e->getMessage());
        $errorMessage = 'Database error: ' . $e->getMessage();
    }
    
    $_SESSION['success_message'] = $successMessage;
    $_SESSION['error_message'] = $errorMessage;
    header('Location: ' . BASE_URL . 'administration/assessments.php');
    exit;
}

if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get terms for dropdown
$termsResult = $db->query("
    SELECT terms_pk, term_code, term_name, academic_year
    FROM {$dbPrefix}terms
    WHERE is_active = 1
    ORDER BY term_code ASC
");
$terms = $termsResult->fetchAll();

// Get selected term (from GET/session, or default to latest)
$selectedTermFk = getSelectedTermFk();
if (!$selectedTermFk && !empty($terms)) {
    $selectedTermFk = $terms[0]['terms_pk'];
    // Save to session for header dropdown sync
    $_SESSION['selected_term_fk'] = $selectedTermFk;
}

// Get selected term name
$selectedTermName = '';
$selectedTermCode = '';
if ($selectedTermFk && !empty($terms)) {
    foreach ($terms as $term) {
        if ($term['terms_pk'] == $selectedTermFk) {
            $selectedTermName = $term['term_name'];
            $selectedTermCode = $term['term_code'];
            break;
        }
    }
}

// Get statistics (filtered by term through enrollment->terms join)
if ($selectedTermFk) {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN a.is_finalized = 1 THEN 1 ELSE 0 END), 0) as finalized
        FROM {$dbPrefix}assessments a
        LEFT JOIN {$dbPrefix}enrollment e ON a.enrollment_fk = e.enrollment_pk
        LEFT JOIN {$dbPrefix}terms t ON e.term_code = t.term_code
        WHERE t.terms_pk = ?
    ", [$selectedTermFk], 'i');
} else {
    $statsResult = $db->query("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN is_finalized = 1 THEN 1 ELSE 0 END), 0) as finalized
        FROM {$dbPrefix}assessments
    ");
}
$stats = $statsResult->fetch();
$totalAssessments = (int)($stats['total'] ?? 0);
$finalizedAssessments = (int)($stats['finalized'] ?? 0);

// Get enrollments for dropdowns
$enrollmentsResult = $db->query("
    SELECT e.enrollment_pk, e.term_code, e.crn, 
           s.student_id, s.first_name, s.last_name
    FROM {$dbPrefix}enrollment e
    LEFT JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
    WHERE e.enrollment_status IN ('enrolled', 'completed', '1', '2')
    ORDER BY e.term_code DESC, e.crn, s.last_name, s.first_name
");
$enrollments = $enrollmentsResult->fetchAll();

// Get SLOs for dropdowns
$slosResult = $db->query("
    SELECT slo.student_learning_outcomes_pk, slo.slo_code, slo.slo_description, c.course_name, c.course_number
    FROM {$dbPrefix}student_learning_outcomes slo
    LEFT JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
    WHERE slo.is_active = 1
    ORDER BY c.course_number, slo.sequence_num
");
$slos = $slosResult->fetchAll();

require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$pageTitle = 'Assessment Management';
if ($selectedTermName) {
    $pageTitle .= ' - ' . $selectedTermName;
    if ($selectedTermCode) {
        $pageTitle .= ' (' . $selectedTermCode . ')';
    }
}

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => $pageTitle,
    'currentPage' => 'admin_assessments',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Assessments']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>

<!-- Success/Error Messages -->
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle" aria-hidden="true"></i> <?= $successMessage ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <?= $errorMessage ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
    </div>
<?php endif; ?>

<!-- Assessments Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-table" aria-hidden="true"></i> Assessments</h2>
    </div>
    <div class="card-body">
        <table id="assessmentsTable" class="table table-bordered table-striped table-hover" aria-label="Assessments data table">
            <caption class="visually-hidden">List of assessments with filtering and sorting capabilities</caption>
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Term</th>
                    <th scope="col">CRN</th>
                    <th scope="col">Student</th>
                    <th scope="col">SLO</th>
                    <th scope="col">Score</th>
                    <th scope="col">Level</th>
                    <th scope="col">Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
                <tr>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search ID" aria-label="Filter by ID"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search Term" aria-label="Filter by Term"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search CRN" aria-label="Filter by CRN"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search Student" aria-label="Filter by Student"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search SLO" aria-label="Filter by SLO"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search Score" aria-label="Filter by Score"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search Level" aria-label="Filter by Level"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search Date" aria-label="Filter by Date"></td>
                    <td><input type="text" class="form-control form-control-sm" placeholder="Search Status" aria-label="Filter by Status"></td>
                    <td>&nbsp;</td>
                </tr>
            </thead>
        </table>
    </div>

</div>

<!-- Add Assessment Modal -->
<div class="modal fade" id="addAssessmentModal" tabindex="-1" aria-labelledby="addAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <span class="modal-title" id="addAssessmentModalLabel"><i class="fas fa-plus" aria-hidden="true"></i> Add Assessment</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <fieldset>
                        <legend class="h6 mb-3">Assessment Selection</legend>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="enrollmentFk" class="form-label">Enrollment (Term CRN - Student) <span class="text-danger" aria-label="required">*</span></label>
                            <select class="form-select" id="enrollmentFk" name="enrollment_fk" required aria-required="true">
                                <option value="">Select Enrollment</option>
                                <?php foreach ($enrollments as $enrollment): 
                                    $studentName = htmlspecialchars(($enrollment['last_name'] ?? '') . ', ' . ($enrollment['first_name'] ?? ''));
                                    $displayText = htmlspecialchars($enrollment['term_code']) . ' CRN:' . htmlspecialchars($enrollment['crn']) . ' - ' . htmlspecialchars($enrollment['student_id'] ?? '') . ' (' . trim($studentName, ', ') . ')';
                                ?>
                                    <option value="<?= $enrollment['enrollment_pk'] ?>">
                                        <?= $displayText ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="sloFk" class="form-label">Student Learning Outcome <span class="text-danger" aria-label="required">*</span></label>
                            <select class="form-select" id="sloFk" name="student_learning_outcome_fk" required aria-required="true">
                                <option value="">Select SLO</option>
                                <?php foreach ($slos as $slo): ?>
                                    <option value="<?= $slo['student_learning_outcomes_pk'] ?>">
                                        <?= htmlspecialchars($slo['course_number'] ?? '') ?> - <?= htmlspecialchars($slo['slo_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend class="h6 mb-3">Assessment Details</legend>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="scoreValue" class="form-label">Score <span class="text-danger" aria-label="required">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="scoreValue" name="score_value" required aria-required="true">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="achievementLevel" class="form-label">Achievement Level</label>
                                <select class="form-select" id="achievementLevel" name="achievement_level">
                                    <option value="exceeds">Exceeds Expectations</option>
                                    <option value="meets">Meets Expectations</option>
                                    <option value="developing">Developing</option>
                                    <option value="below">Below Expectations</option>
                                    <option value="pending" selected>Pending</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="assessedDate" class="form-label">Assessment Date</label>
                                <input type="date" class="form-control" id="assessedDate" name="assessed_date" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assessmentMethod" class="form-label">Assessment Method</label>
                            <input type="text" class="form-control" id="assessmentMethod" name="assessment_method" maxlength="255">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isFinalized" name="is_finalized">
                            <label class="form-check-label" for="isFinalized">Finalized</label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div class="modal fade" id="editAssessmentModal" tabindex="-1" aria-labelledby="editAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <span class="modal-title" id="editAssessmentModalLabel"><i class="fas fa-edit" aria-hidden="true"></i> Edit Assessment</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="assessments_pk" id="editAssessmentPk">
                    
                    <fieldset>
                        <legend class="h6 mb-3">Assessment Selection</legend>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="editEnrollmentFk" class="form-label">Enrollment (Term CRN - Student) <span class="text-danger" aria-label="required">*</span></label>
                            <select class="form-select" id="editEnrollmentFk" name="enrollment_fk" required aria-required="true">
                                <option value="">Select Enrollment</option>
                                <?php foreach ($enrollments as $enrollment): 
                                    $studentName = htmlspecialchars(($enrollment['last_name'] ?? '') . ', ' . ($enrollment['first_name'] ?? ''));
                                    $displayText = htmlspecialchars($enrollment['term_code']) . ' CRN:' . htmlspecialchars($enrollment['crn']) . ' - ' . htmlspecialchars($enrollment['student_id'] ?? '') . ' (' . trim($studentName, ', ') . ')';
                                ?>
                                    <option value="<?= $enrollment['enrollment_pk'] ?>">
                                        <?= $displayText ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="editSloFk" class="form-label">Student Learning Outcome <span class="text-danger" aria-label="required">*</span></label>
                            <select class="form-select" id="editSloFk" name="student_learning_outcome_fk" required aria-required="true">
                                <option value="">Select SLO</option>
                                <?php foreach ($slos as $slo): ?>
                                    <option value="<?= $slo['student_learning_outcomes_pk'] ?>">
                                        <?= htmlspecialchars($slo['course_number'] ?? '') ?> - <?= htmlspecialchars($slo['slo_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    </fieldset>
                    
                    <fieldset>
                        <legend class="h6 mb-3">Assessment Details</legend>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editScoreValue" class="form-label">Score <span class="text-danger" aria-label="required">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="editScoreValue" name="score_value" required aria-required="true">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editAchievementLevel" class="form-label">Achievement Level</label>
                            <select class="form-select" id="editAchievementLevel" name="achievement_level">
                                <option value="exceeds">Exceeds Expectations</option>
                                <option value="meets">Meets Expectations</option>
                                <option value="developing">Developing</option>
                                <option value="below">Below Expectations</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editAssessedDate" class="form-label">Assessment Date</label>
                            <input type="date" class="form-control" id="editAssessedDate" name="assessed_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAssessmentMethod" class="form-label">Assessment Method</label>
                        <input type="text" class="form-control" id="editAssessmentMethod" name="assessment_method" maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="editIsFinalized" name="is_finalized">
                        <label class="form-check-label" for="editIsFinalized">Finalized</label>
                    </div>
                    </fieldset>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <!-- LEFT SIDE: Destructive Actions -->
                    <div>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteAssessment()" aria-label="Delete assessment">
                            <i class="fas fa-trash" aria-hidden="true"></i> Delete
                        </button>
                    </div>
                    <!-- RIGHT SIDE: Primary Actions -->
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <span class="modal-title"><i class="fas fa-upload"></i> Import Assessments from CSV</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="import">
                    
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csvFile" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>CSV Format:</strong><br>
                        <code>term_code,crn,student_id,slo_code,score_value,achievement_level,assessment_method,notes,assessed_date,is_finalized</code><br>
                        <small>Required: term_code, crn, student_id, slo_code, score_value<br>
                        Optional: achievement_level (default: pending), assessment_method, notes, assessed_date, is_finalized (0/1)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="assessments_pk" id="deleteAssessmentId">
</form>

<!-- Toggle Status Form (hidden) -->
<form method="POST" id="toggleForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle">
    <input type="hidden" name="assessments_pk" id="toggleAssessmentId">
</form>

<script>
$(document).ready(function() {
    var table = $('#assessmentsTable').DataTable({
        orderCellsTop: true,
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>administration/assessments_data.php',
            data: function(d) {
                d.term_fk = $('#termFilter').val();
            }
        },
        dom: 'Brtip',
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
            { data: 8, name: 'is_finalized' },
            { data: 9, name: 'actions', orderable: false, searchable: false }
        ],
        initComplete: function() {
            // Apply the search - target the second header row where filters are
            var api = this.api();
            api.columns().every(function(colIdx) {
                var column = this;
                // Find input in the second header row (tr:eq(1)) for this column
                $('input', $('#assessmentsTable thead tr:eq(1) td').eq(colIdx)).on('keyup change clear', function() {
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

function toggleStatus(id, displayId) {
    if (confirm('Toggle finalized status for assessment #' + displayId + '?')) {
        $('#toggleAssessmentId').val(id);
        $('#toggleForm').submit();
    }
}

function deleteAssessment(id, displayId) {
    if (confirm('Are you sure you want to DELETE assessment #' + displayId + '? This action cannot be undone.')) {
        $('#deleteAssessmentId').val(id);
        $('#deleteForm').submit();
    }
}

function confirmDeleteAssessment() {
    const assessmentPk = $('#editAssessmentPk').val();
    deleteAssessment(assessmentPk, assessmentPk);
}
</script>

    </div>
</div>

<?php $theme->showFooter($context); ?>
