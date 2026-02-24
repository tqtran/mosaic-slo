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
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET enrollment_fk = ?, student_learning_outcome_fk = ?, score_value = ?, 
                             achievement_level = ?, assessment_method = ?, notes = ?, 
                             assessed_date = ?, is_finalized = ?, updated_at = NOW()
                         WHERE assessments_pk = ?",
                        [$enrollmentFk, $sloFk, $scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized, $id],
                        'iidssssii'
                    );
                    $successMessage = 'Assessment updated successfully';
                } else {
                    $errorMessage = implode('<br>', $errors);
                }
                break;
                
            case 'toggle':
                $id = (int)($_POST['assessments_pk'] ?? 0);
                if ($id > 0) {
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET is_finalized = NOT is_finalized, updated_at = NOW()
                         WHERE assessments_pk = ?",
                        [$id],
                        'i'
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
                        $db->query(
                            "UPDATE {$dbPrefix}assessments 
                             SET score_value = ?, achievement_level = ?, assessment_method = ?, 
                                 notes = ?, assessed_date = ?, is_finalized = ?, updated_at = NOW() 
                             WHERE assessments_pk = ?",
                            [$scoreValue, $achievementLevel, $assessmentMethod, $notes, $assessedDate, $isFinalized ? 1 : 0, $existing['assessments_pk']],
                            'dssssii'
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

// Get statistics
$statsResult = $db->query("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN is_finalized = 1 THEN 1 ELSE 0 END), 0) as finalized
    FROM {$dbPrefix}assessments
");
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

<!-- Success/Error Messages -->
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?= $successMessage ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?= $errorMessage ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="row mb-3">
    <div class="col-md-8">
        <h1 class="h3"><i class="fas fa-chart-line"></i> Assessment Management</h1>
        <p class="text-muted">Manage student learning outcome assessments</p>
    </div>
    <div class="col-md-4 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssessmentModal">
            <i class="fas fa-plus"></i> Add Assessment
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-upload"></i> Import CSV
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-bar fa-3x opacity-50"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fs-5 fw-bold"><?= number_format($totalAssessments) ?></div>
                        <div>Total Assessments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fs-5 fw-bold"><?= number_format($finalizedAssessments) ?></div>
                        <div>Finalized Assessments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assessments Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-table"></i> Assessments</h3>
    </div>
    <div class="card-body">
        <table id="assessmentsTable" class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Term</th>
                    <th>CRN</th>
                    <th>Student</th>
                    <th>SLO</th>
                    <th>Score</th>
                    <th>Level</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <tr>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search ID"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search Term"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search CRN"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search Student"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search SLO"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search Score"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search Level"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search Date"></th>
                    <th><input type="text" class="form-control form-control-sm" placeholder="Search Status"></th>
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Add Assessment Modal -->
<div class="modal fade" id="addAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
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
                            <label for="sloFk" class="form-label">Student Learning Outcome <span class="text-danger">*</span></label>
                            <select class="form-select" id="sloFk" name="student_learning_outcome_fk" required>
                                <option value="">Select SLO</option>
                                <?php foreach ($slos as $slo): ?>
                                    <option value="<?= $slo['student_learning_outcomes_pk'] ?>">
                                        <?= htmlspecialchars($slo['course_number'] ?? '') ?> - <?= htmlspecialchars($slo['slo_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="scoreValue" class="form-label">Score <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="scoreValue" name="score_value" required>
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
    <div class="modal-dialog modal-lg">
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
                            <label for="editSloFk" class="form-label">Student Learning Outcome <span class="text-danger">*</span></label>
                            <select class="form-select" id="editSloFk" name="student_learning_outcome_fk" required>
                                <option value="">Select SLO</option>
                                <?php foreach ($slos as $slo): ?>
                                    <option value="<?= $slo['student_learning_outcomes_pk'] ?>">
                                        <?= htmlspecialchars($slo['course_number'] ?? '') ?> - <?= htmlspecialchars($slo['slo_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="editScoreValue" class="form-label">Score <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="editScoreValue" name="score_value" required>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Import Assessments from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
    // Initialize DataTable with individual column search
    $('#assessmentsTable thead tr:eq(1) th').each(function(i) {
        var title = $('#assessmentsTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (i < 9) { // Skip last column (actions)
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '">');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#assessmentsTable').DataTable({
        orderCellsTop: true,
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
            { data: 8, name: 'is_finalized' },
            { data: 9, name: 'actions', orderable: false, searchable: false }
        ],
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
</script>

    </div>
</div>

<?php $theme->showFooter($context); ?>
