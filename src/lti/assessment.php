<?php
declare(strict_types=1);

/**
 * LTI Assessment Interface
 * 
 * Instructor-facing interface for entering SLO assessment data.
 * Accessed via LTI launch from Learning Management System.
 * Uses pragmatic page pattern (logic + template in one file).
 * 
 * @package Mosaic
 */

// Security headers
header('X-Frame-Options: ALLOWALL'); // LTI launches in iframe
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
ini_set('session.cookie_samesite', 'None'); // Required for iframe embedding
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 7200) { // 2 hour timeout
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize common variables and database
require_once __DIR__ . '/../system/includes/init.php';
require_once __DIR__ . '/../system/includes/message_page.php';

// $logger is now available from init.php

// Check LTI authentication
if (empty($_SESSION['lti_authenticated']) || !$_SESSION['lti_is_instructor']) {
    render_message_page(
        'error',
        'Access Denied',
        'This page is only accessible to instructors via LTI launch.'
    );
    exit;
}

// Get LTI session data
$ltiUserName = $_SESSION['lti_full_name'] ?? 'Instructor';
$ltiUserEmail = $_SESSION['lti_user_email'] ?? '';
$ltiCourseNumber = $_SESSION['lti_course_number'] ?? null;
$ltiCourseName = $_SESSION['lti_course_name'] ?? 'Course';
$ltiCrn = $_SESSION['lti_crn'] ?? null;
$ltiTermId = $_SESSION['lti_term_id'] ?? null;
$ltiAcademicYear = $_SESSION['lti_academic_year'] ?? null;
$ltiTermNumber = $_SESSION['lti_term_number'] ?? null;

// Handle form submission
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
        if ($action === 'save_assessments') {
            $courseSectionId = (int)($_POST['course_section_id'] ?? 0);
            $sloId = (int)($_POST['slo_id'] ?? 0);
            $outcomes = $_POST['outcome'] ?? [];
            $scores = $_POST['score'] ?? [];
            
            if ($courseSectionId <= 0 || $sloId <= 0) {
                throw new \Exception('Invalid course section or SLO selected');
            }
            
            // Get assessor user ID (would be from users table in production)
            // For now, we'll use NULL since we don't have user provisioning yet
            $assessorId = null;
            
            $saved = 0;
            $skipped = 0;
            
            foreach ($outcomes as $enrollmentId => $achievementLevel) {
                $enrollmentId = (int)$enrollmentId;
                
                if ($enrollmentId <= 0) {
                    $skipped++;
                    continue;
                }
                
                // Map outcome text to enum value
                $achievementMap = [
                    'Met' => 'met',
                    'Partially Met' => 'partially_met',
                    'Not Met' => 'not_met',
                    'Not Assessed' => 'pending'
                ];
                $achievementEnum = $achievementMap[$achievementLevel] ?? 'pending';
                
                $scoreValue = isset($scores[$enrollmentId]) && $scores[$enrollmentId] !== '' 
                    ? (float)$scores[$enrollmentId] 
                    : null;
                
                // Check if assessment already exists
                $existing = $db->query(
                    "SELECT assessments_pk FROM {$dbPrefix}assessments 
                     WHERE enrollment_fk = ? AND student_learning_outcome_fk = ?",
                    [$enrollmentId, $sloId],
                    'ii'
                );
                
                if ($existing->num_rows > 0) {
                    // Update existing assessment
                    $row = $existing->fetch_assoc();
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET achievement_level = ?, score_value = ?, assessed_date = CURDATE(), 
                             updated_at = NOW(), assessed_by_fk = ?
                         WHERE assessments_pk = ?",
                        [$achievementEnum, $scoreValue, $assessorId, $row['assessments_pk']],
                        'sdii'
                    );
                } else {
                    // Insert new assessment
                    $db->query(
                        "INSERT INTO {$dbPrefix}assessments 
                         (enrollment_fk, student_learning_outcome_fk, achievement_level, score_value, 
                          assessed_date, is_finalized, assessed_by_fk, created_at, updated_at)
                         VALUES (?, ?, ?, ?, CURDATE(), FALSE, ?, NOW(), NOW())",
                        [$enrollmentId, $sloId, $achievementEnum, $scoreValue, $assessorId],
                        'iisdi'
                    );
                }
                
                $saved++;
            }
            
            $logger->info('LTI Assessment Saved', [
                'course_section_id' => $courseSectionId,
                'slo_id' => $sloId,
                'saved' => $saved,
                'skipped' => $skipped
            ]);
            
            $successMessage = "Assessment data saved successfully! {$saved} student(s) assessed.";
            
            if ($skipped > 0) {
                $successMessage .= " ({$skipped} skipped due to invalid data)";
            }
        }
    } catch (\Exception $e) {
        $errorMessage = 'Failed to save assessments: ' . htmlspecialchars($e->getMessage());
        $logger->error(
            'LTI Assessment Save Failed: ' . $e->getMessage(),
            'LTI_ERROR',
            null,
            $e->getTraceAsString(),
            $e->getFile(),
            $e->getLine()
        );
        
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

// Query available course sections
// Filter by LTI course context if available
$courseSectionsQuery = "
    SELECT cs.course_sections_pk, cs.section_code, c.course_code, c.course_name,
           t.term_name, t.term_year
    FROM {$dbPrefix}course_sections cs
    INNER JOIN {$dbPrefix}courses c ON cs.course_fk = c.courses_pk
    INNER JOIN {$dbPrefix}terms t ON cs.term_fk = t.terms_pk
    WHERE cs.is_active = TRUE
";

$params = [];
$types = '';

// Filter by academic year from LTI launch if available
if ($ltiAcademicYear) {
    $courseSectionsQuery .= " AND t.term_year = ?";
    $params[] = $ltiAcademicYear;
    $types .= 's';
}

$courseSectionsQuery .= " ORDER BY t.term_year DESC, t.term_name DESC, c.course_code ASC";

if (!empty($params)) {
    $courseSectionsResult = $db->query($courseSectionsQuery, $params, $types);
} else {
    $courseSectionsResult = $db->query($courseSectionsQuery);
}
$courseSections = $courseSectionsResult->fetch_all(MYSQLI_ASSOC);

// Get selected course section (first one by default or from GET parameter)
$selectedCourseSectionId = isset($_GET['course_section_id']) 
    ? (int)$_GET['course_section_id'] 
    : ($courseSections[0]['course_sections_pk'] ?? 0);

// Get SLOs for selected course section
$slos = [];
if ($selectedCourseSectionId > 0) {
    $slosResult = $db->query(
        "SELECT slo.student_learning_outcomes_pk, slo.code, slo.description
         FROM {$dbPrefix}student_learning_outcomes slo
         INNER JOIN {$dbPrefix}course_sections cs ON slo.course_fk = cs.course_fk
         WHERE cs.course_sections_pk = ? AND slo.is_active = TRUE
         ORDER BY slo.sequence_num ASC",
        [$selectedCourseSectionId],
        'i'
    );
    $slos = $slosResult->fetch_all(MYSQLI_ASSOC);
}

$selectedSloId = isset($_GET['slo_id']) && !empty($slos)
    ? (int)$_GET['slo_id']
    : ($slos[0]['student_learning_outcomes_pk'] ?? 0);

// Get enrolled students with existing assessments
$students = [];
if ($selectedCourseSectionId > 0 && $selectedSloId > 0) {
    // Build query with optional CRN filter from LTI launch
    $studentsQuery = "
        SELECT e.enrollment_pk, e.crn, s.student_id, s.first_name, s.last_name,
               a.achievement_level, a.score_value
        FROM {$dbPrefix}enrollment e
        INNER JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
        LEFT JOIN {$dbPrefix}assessments a ON e.enrollment_pk = a.enrollment_fk 
                                 AND a.student_learning_outcome_fk = ?
        WHERE e.course_section_fk = ? AND e.enrollment_status = 'enrolled'
    ";
    
    $studentParams = [$selectedSloId, $selectedCourseSectionId];
    $studentTypes = 'ii';
    
    // Filter by CRN from LTI launch if available
    if ($ltiCrn) {
        $studentsQuery .= " AND e.crn = ?";
        $studentParams[] = $ltiCrn;
        $studentTypes .= 's';
    }
    
    $studentsQuery .= " ORDER BY s.last_name ASC, s.first_name ASC";
    
    $studentsResult = $db->query($studentsQuery, $studentParams, $studentTypes);
    $students = $studentsResult->fetch_all(MYSQLI_ASSOC);
}

// Get selected course section details
$courseSection = null;
if ($selectedCourseSectionId > 0) {
    foreach ($courseSections as $cs) {
        if ($cs['course_sections_pk'] == $selectedCourseSectionId) {
            $courseSection = $cs;
            break;
        }
    }
}

// Get selected SLO details
$selectedSlo = null;
if ($selectedSloId > 0) {
    foreach ($slos as $slo) {
        if ($slo['student_learning_outcomes_pk'] == $selectedSloId) {
            $selectedSlo = $slo;
            break;
        }
    }
}

// Capture custom styles
ob_start();
?>
<style>
    :root {
        --primary-dark: #0D47A1;
        --accent-blue: #1976D2;
        --brand-teal: #1565C0;
    }
    body {
        background-color: #f4f6f9;
    }
    .lti-header {
        background: linear-gradient(135deg, var(--accent-blue), var(--brand-teal));
        color: white;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .student-row:hover {
        background-color: #f8f9fa;
    }
    .btn-primary {
        background-color: var(--accent-blue);
        border-color: var(--accent-blue);
    }
    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    .lti-badge {
        background: #17a2b8;
        color: white;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 0.875rem;
    }
</style>
<?php
$customStyles = ob_get_clean();

// Load theme system (auto-loads ThemeContext and Theme)
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'default',
    'pageTitle' => 'SLO Assessment Entry - ' . SITE_NAME,
    'customCss' => $customStyles
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<div class="lti-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-clipboard-check"></i> SLO Assessment Entry
                </h1>
                <p class="mb-0 mt-2">
                    <span class="lti-badge"><i class="fas fa-link"></i> LTI Launch</span>
                    <?php if ($ltiCourseNumber): ?>
                        <strong class="ms-3"><?= htmlspecialchars($ltiCourseNumber) ?></strong>
                    <?php endif; ?>
                </p>
                <?php if ($ltiCourseName): ?>
                    <p class="mb-0 mt-1">
                        <small><?= htmlspecialchars($ltiCourseName) ?></small>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-white">
                    <i class="fas fa-user-circle fa-2x"></i>
                    <div class="mt-2">
                        <strong><?= htmlspecialchars($ltiUserName) ?></strong><br>
                        <?php if ($ltiUserEmail): ?>
                            <small><?= htmlspecialchars($ltiUserEmail) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($ltiCrn || $ltiTermId): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong>LTI Context:</strong>
            <?php if ($ltiCrn): ?>
                CRN: <strong><?= htmlspecialchars($ltiCrn) ?></strong>
            <?php endif; ?>
            <?php if ($ltiTermId): ?>
                <?= $ltiCrn ? ' | ' : '' ?>Term ID: <strong><?= htmlspecialchars($ltiTermId) ?></strong>
            <?php endif; ?>
            <?php if ($ltiAcademicYear): ?>
                (Year: <?= htmlspecialchars($ltiAcademicYear) ?><?= $ltiTermNumber ? ', Term: ' . htmlspecialchars($ltiTermNumber) : '' ?>)
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($courseSections)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>No Course Sections Available</strong><br>
            No active course sections found. Please contact your administrator to set up course sections in MOSAIC.
        </div>
    <?php elseif (empty($slos)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>No SLOs Defined</strong><br>
            No Student Learning Outcomes are defined for this course. Please contact your administrator.
        </div>
    <?php elseif (empty($students)): ?>
        <!-- Course & SLO Selection -->
        <div class="card card-primary card-outline mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-graduation-cap"></i> Select Course Section and SLO</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="selectionForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_section_id" class="form-label">Course Section</label>
                                <select name="course_section_id" id="course_section_id" class="form-select" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($courseSections as $cs): ?>
                                        <option value="<?= $cs['course_sections_pk'] ?>" <?= $cs['course_sections_pk'] == $selectedCourseSectionId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cs['course_code']) ?> - <?= htmlspecialchars($cs['section_code']) ?> 
                                            (<?= htmlspecialchars($cs['term_name']) ?> <?= htmlspecialchars($cs['term_year']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="slo_id" class="form-label">Student Learning Outcome</label>
                                <select name="slo_id" id="slo_id" class="form-select" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($slos as $slo): ?>
                                        <option value="<?= $slo['student_learning_outcomes_pk'] ?>" <?= $slo['student_learning_outcomes_pk'] == $selectedSloId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($slo['code']) ?> - <?= htmlspecialchars(substr($slo['description'], 0, 60)) ?><?= strlen($slo['description']) > 60 ? '...' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>No Students Enrolled</strong><br>
            No students are currently enrolled in this course section. Students must be enrolled before assessments can be entered.
        </div>
    <?php else: ?>
        <!-- Course & SLO Selection -->
        <div class="card card-primary card-outline mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-graduation-cap"></i> Assessment Details</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="selectionForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_section_id" class="form-label">Course Section</label>
                                <select name="course_section_id" id="course_section_id" class="form-select" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($courseSections as $cs): ?>
                                        <option value="<?= $cs['course_sections_pk'] ?>" <?= $cs['course_sections_pk'] == $selectedCourseSectionId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cs['course_code']) ?> - <?= htmlspecialchars($cs['section_code']) ?> 
                                            (<?= htmlspecialchars($cs['term_name']) ?> <?= htmlspecialchars($cs['term_year']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="slo_id" class="form-label">Student Learning Outcome</label>
                                <select name="slo_id" id="slo_id" class="form-select" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($slos as $slo): ?>
                                        <option value="<?= $slo['student_learning_outcomes_pk'] ?>" <?= $slo['student_learning_outcomes_pk'] == $selectedSloId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($slo['code']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php if ($selectedSlo): ?>
                        <div class="alert alert-info mb-0">
                            <strong>SLO Description:</strong> <?= htmlspecialchars($selectedSlo['description']) ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Assessment Entry Form -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="save_assessments">
            <input type="hidden" name="course_section_id" value="<?= $selectedCourseSectionId ?>">
            <input type="hidden" name="slo_id" value="<?= $selectedSloId ?>">

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title">
                        <i class="fas fa-users"></i> Student Assessments (<?= count($students) ?> students)
                    </h3>
                    <div class="card-tools">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-success" onclick="setAllOutcomes('Met')">
                                <i class="fas fa-check"></i> All Met
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="setAllOutcomes('Partially Met')">
                                <i class="fas fa-minus"></i> All Partial
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="setAllOutcomes('Not Met')">
                                <i class="fas fa-times"></i> All Not Met
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 50px">#</th>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>CRN</th>
                                    <th style="width: 200px">Achievement Level</th>
                                    <th style="width: 150px">Score (Optional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $index = 1; 
                                foreach ($students as $student): 
                                    // Map enum to display value
                                    $currentAchievement = 'Not Assessed';
                                    if ($student['achievement_level']) {
                                        $achievementMap = [
                                            'met' => 'Met',
                                            'partially_met' => 'Partially Met',
                                            'not_met' => 'Not Met',
                                            'pending' => 'Not Assessed'
                                        ];
                                        $currentAchievement = $achievementMap[$student['achievement_level']] ?? 'Not Assessed';
                                    }
                                ?>
                                    <tr class="student-row">
                                        <td><?= $index++ ?></td>
                                        <td><code><?= htmlspecialchars($student['student_id']) ?></code></td>
                                        <td>
                                            <strong><?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></strong>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($student['crn']) ?></span></td>
                                        <td>
                                            <select name="outcome[<?= $student['enrollment_pk'] ?>]" class="form-select form-select-sm outcome-select">
                                                <option value="Met" <?= $currentAchievement === 'Met' ? 'selected' : '' ?>>Met</option>
                                                <option value="Partially Met" <?= $currentAchievement === 'Partially Met' ? 'selected' : '' ?>>Partially Met</option>
                                                <option value="Not Met" <?= $currentAchievement === 'Not Met' ? 'selected' : '' ?>>Not Met</option>
                                                <option value="Not Assessed" <?= $currentAchievement === 'Not Assessed' ? 'selected' : '' ?>>Not Assessed</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="score[<?= $student['enrollment_pk'] ?>]" 
                                                   class="form-control form-control-sm" 
                                                   min="0" max="100" step="0.01" 
                                                   value="<?= $student['score_value'] ? htmlspecialchars($student['score_value']) : '' ?>"
                                                   placeholder="0-100">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Assessment Data
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Assessment data is saved to MOSAIC database
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Instructions Card -->
        <div class="card card-info card-outline mt-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-question-circle"></i> Instructions</h3>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li><strong>Select Course Section & SLO:</strong> Choose the course section and specific learning outcome you're assessing.</li>
                    <li><strong>Enter Achievement Levels:</strong> For each student, select whether they Met, Partially Met, or did Not Meet the learning outcome.</li>
                    <li><strong>Optional Scores:</strong> You may enter numeric scores (0-100) if applicable to your assessment method.</li>
                    <li><strong>Quick Actions:</strong> Use the buttons above the table to quickly set all students to the same achievement level.</li>
                    <li><strong>Save:</strong> Click "Save Assessment Data" when complete. You can return later to update assessments.</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function setAllOutcomes(outcome) {
    document.querySelectorAll('.outcome-select').forEach(function(select) {
        select.value = outcome;
    });
}
</script>

<?php $theme->showFooter($context); ?>
