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

// Security headers for LTI embedding
// Allow embedding in iframes from any origin (required for LMS integration)
header("Content-Security-Policy: frame-ancestors *");
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
            $crn = $_POST['crn'] ?? '';
            $termCode = $_POST['term_code'] ?? '';
            $sloId = (int)($_POST['slo_id'] ?? 0);
            $assessmentMethod = trim($_POST['assessment_method'] ?? '');
            $outcomes = $_POST['outcome'] ?? [];
            
            if (empty($crn) || empty($termCode) || $sloId <= 0) {
                throw new \Exception('Invalid course section or SLO selected');
            }
            
            if (empty($assessmentMethod)) {
                throw new \Exception('Assessment method is required');
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
                
                // Validate achievement level (already in enum format from radio buttons)
                $validLevels = ['met', 'not_met', 'pending'];
                if (!in_array($achievementLevel, $validLevels)) {
                    $achievementLevel = 'pending';
                }
                
                // Check if assessment already exists
                $existing = $db->query(
                    "SELECT assessments_pk FROM {$dbPrefix}assessments 
                     WHERE enrollment_fk = ? AND student_learning_outcome_fk = ?",
                    [$enrollmentId, $sloId],
                    'ii'
                );
                
                if ($existing->rowCount() > 0) {
                    // Update existing assessment
                    $row = $existing->fetch();
                    $db->query(
                        "UPDATE {$dbPrefix}assessments 
                         SET achievement_level = ?, assessment_method = ?, assessed_date = CURDATE(), 
                             updated_at = NOW(), assessed_by_fk = ?
                         WHERE assessments_pk = ?",
                        [$achievementLevel, $assessmentMethod, $assessorId, $row['assessments_pk']],
                        'ssii'
                    );
                } else {
                    // Insert new assessment
                    $db->query(
                        "INSERT INTO {$dbPrefix}assessments 
                         (enrollment_fk, student_learning_outcome_fk, achievement_level, assessment_method,
                          assessed_date, is_finalized, assessed_by_fk, created_at, updated_at)
                         VALUES (?, ?, ?, ?, CURDATE(), FALSE, ?, NOW(), NOW())",
                        [$enrollmentId, $sloId, $achievementLevel, $assessmentMethod, $assessorId],
                        'iissi'
                    );
                }
                
                $saved++;
            }
            
            $logger->info('LTI Assessment Saved', [
                'crn' => $crn,
                'term_code' => $termCode,
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

// Query available course sections from enrollment table (has all the data)
// enrollment.term_code stores Banner Term ID (e.g., 202533)
$courseSectionsQuery = "
    SELECT DISTINCT e.crn, e.term_code, t.term_name
    FROM {$dbPrefix}enrollment e
    LEFT JOIN {$dbPrefix}terms t ON e.term_code = t.banner_term
    WHERE e.enrollment_status = '1'
";

$params = [];
$types = '';

// Filter by Banner Term from LTI launch if available
if ($ltiTermId) {
    $courseSectionsQuery .= " AND e.term_code = ?";
    $params[] = $ltiTermId;
    $types .= 's';
}

// Filter by CRN from LTI launch if available  
if ($ltiCrn) {
    $courseSectionsQuery .= " AND e.crn = ?";
    $params[] = $ltiCrn;
    $types .= 's';
}

$courseSectionsQuery .= " ORDER BY e.term_code DESC, e.crn ASC";

if (!empty($params)) {
    $courseSectionsResult = $db->query($courseSectionsQuery, $params, $types);
} else {
    $courseSectionsResult = $db->query($courseSectionsQuery);
}
$courseSections = $courseSectionsResult->fetchAll();

// Get selected course section (first one by default or from GET parameter)
$selectedCrn = isset($_GET['crn']) && !empty($_GET['crn'])
    ? $_GET['crn']
    : ($courseSections[0]['crn'] ?? '');
    
$selectedTermCode = isset($_GET['term_code']) && !empty($_GET['term_code'])
    ? $_GET['term_code']
    : ($courseSections[0]['term_code'] ?? '');

// Get course_fk from enrollment table (populated during enrollment import)
$courseFkForSlo = null;
$courseNumber = null;
if ($ltiCrn && $ltiTermId) {
    $enrollmentResult = $db->query(
        "SELECT DISTINCT e.course_fk, c.course_number
         FROM {$dbPrefix}enrollment e
         INNER JOIN {$dbPrefix}courses c ON e.course_fk = c.courses_pk
         WHERE e.crn = ? AND e.term_code = ? AND e.enrollment_status = '1'
         LIMIT 1",
        [$ltiCrn, $ltiTermId],
        'ss'
    );
    if ($enrollmentResult->rowCount() > 0) {
        $enrollmentRow = $enrollmentResult->fetch();
        $courseFkForSlo = $enrollmentRow['course_fk'];
        $courseNumber = $enrollmentRow['course_number'];
    }
}

// Query SLOs for the course
$slos = [];
if ($courseFkForSlo) {
    $slosResult = $db->query(
        "SELECT student_learning_outcomes_pk, slo_code, slo_description
         FROM {$dbPrefix}student_learning_outcomes
         WHERE course_fk = ? AND is_active = TRUE
         ORDER BY sequence_num ASC",
        [$courseFkForSlo],
        'i'
    );
    $slos = $slosResult->fetchAll();
}

$selectedSloId = isset($_GET['slo_id']) && !empty($slos)
    ? (int)$_GET['slo_id']
    : ($slos[0]['student_learning_outcomes_pk'] ?? 0);

// Get enrolled students with existing assessments
// Get enrolled students for the selected CRN and Banner Term
// SLO selection is optional - will filter assessment data if selected
$students = [];
if ($selectedCrn && $selectedTermCode) {
    // Query all students from enrollment by CRN and Banner Term
    // Use course_fk directly from enrollment table (populated during import)
    if ($selectedSloId > 0) {
        // Include existing assessments for the selected SLO
        $studentsQuery = "
            SELECT e.enrollment_pk, e.crn, e.term_code, e.course_fk,
                   s.student_id, s.first_name, s.last_name,
                   a.achievement_level
            FROM {$dbPrefix}enrollment e
            INNER JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
            LEFT JOIN {$dbPrefix}assessments a ON e.enrollment_pk = a.enrollment_fk 
                                     AND a.student_learning_outcome_fk = ?
            WHERE e.crn = ? AND e.term_code = ? AND e.enrollment_status = '1'
            ORDER BY s.last_name ASC, s.first_name ASC
        ";
        $studentsResult = $db->query($studentsQuery, [$selectedSloId, $selectedCrn, $selectedTermCode], 'iss');
    } else {
        // Just get enrolled students without assessment data
        $studentsQuery = "
            SELECT e.enrollment_pk, e.crn, e.term_code, e.course_fk,
                   s.student_id, s.first_name, s.last_name,
                   NULL as achievement_level
            FROM {$dbPrefix}enrollment e
            INNER JOIN {$dbPrefix}students s ON e.student_fk = s.students_pk
            WHERE e.crn = ? AND e.term_code = ? AND e.enrollment_status = '1'
            ORDER BY s.last_name ASC, s.first_name ASC
        ";
        $studentsResult = $db->query($studentsQuery, [$selectedCrn, $selectedTermCode], 'ss');
    }
    
    $students = $studentsResult->fetchAll();
}

// Get selected course section details
$courseSection = null;
if ($selectedCrn && $selectedTermCode) {
    foreach ($courseSections as $cs) {
        if ($cs['crn'] === $selectedCrn && $cs['term_code'] === $selectedTermCode) {
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

// Use LTI-specific theme (lightweight for iframe embedding)
$theme = ThemeLoader::getActiveTheme(null, 'lti');
$theme->showHeader($context);
?>

<div class="lti-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
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

    <?php if (empty($courseSections)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>No Course Sections Available</strong><br>
            No active course sections found. Please contact your administrator to set up course sections in MOSAIC.
        </div>
    <?php elseif (empty($students)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No students are currently enrolled in this course section.
        </div>
    <?php else: ?>
        <!-- SLO Selection Buttons -->
        <div class="card card-primary card-outline mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tasks"></i> Select Student Learning Outcome</h3>
            </div>
            <div class="card-body">
                <div class="btn-group d-flex flex-wrap gap-2" role="group">
                    <?php foreach ($slos as $slo): ?>
                        <a href="?crn=<?= urlencode($selectedCrn) ?>&term_code=<?= urlencode($selectedTermCode) ?>&slo_id=<?= $slo['student_learning_outcomes_pk'] ?>" 
                           class="btn <?= $slo['student_learning_outcomes_pk'] == $selectedSloId ? 'btn-primary' : 'btn-outline-primary' ?> flex-fill"
                           style="min-width: 120px;">
                            <strong><?= htmlspecialchars($slo['slo_code']) ?></strong><br>
                            <small><?= htmlspecialchars(substr($slo['slo_description'], 0, 50)) ?><?= strlen($slo['slo_description']) > 50 ? '...' : '' ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($selectedSlo): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong><?= htmlspecialchars($selectedSlo['slo_code']) ?>:</strong> <?= htmlspecialchars($selectedSlo['slo_description']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assessment Entry Form -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="save_assessments">
            <input type="hidden" name="crn" value="<?= htmlspecialchars($selectedCrn) ?>">
            <input type="hidden" name="term_code" value="<?= htmlspecialchars($selectedTermCode) ?>">
            <input type="hidden" name="slo_id" value="<?= $selectedSloId ?>">

            <!-- Assessment Method Selection -->
            <div class="card card-info card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Assessment Method</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="assessment_method" class="form-label">What type of assessment are you recording?</label>
                            <select class="form-select" id="assessment_method" name="assessment_method" required>
                                <option value="">-- Select Assessment Type --</option>
                                <?php
                                $assessmentTypes = explode(',', $config->get('app.assessment_types', 'Quiz,Exam,Project,Assignment'));
                                foreach ($assessmentTypes as $type):
                                    $type = trim($type);
                                ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">This will be saved with all assessments below.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title">
                        <i class="fas fa-users"></i> Student Assessments (<?= count($students) ?> students)
                    </h3>
                    <div class="card-tools">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-success" onclick="setAllOutcomes('met')">
                                <i class="fas fa-check"></i> All Met
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="setAllOutcomes('not_met')">
                                <i class="fas fa-times"></i> All Not Met
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="setAllOutcomes('pending')">
                                <i class="fas fa-circle"></i> All Unassessed
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
                                    <th>Achievement Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $index = 1; 
                                foreach ($students as $student): 
                                    // Get current achievement level (enum from database)
                                    $currentLevel = $student['achievement_level'] ?: 'pending';
                                ?>
                                    <tr class="student-row">
                                        <td><?= $index++ ?></td>
                                        <td><code><?= htmlspecialchars($student['student_id']) ?></code></td>
                                        <td>
                                            <strong><?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></strong>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($student['crn']) ?></span></td>
                                        <td>
                                            <div class="btn-group btn-group-sm outcome-buttons" role="group" data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <input type="radio" class="btn-check outcome-radio" name="outcome[<?= $student['enrollment_pk'] ?>]" id="met_<?= $student['enrollment_pk'] ?>" value="met" <?= $currentLevel === 'met' ? 'checked' : '' ?> data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <label class="btn btn-outline-success" for="met_<?= $student['enrollment_pk'] ?>">
                                                    <i class="fas fa-check"></i> Met
                                                </label>

                                                <input type="radio" class="btn-check outcome-radio" name="outcome[<?= $student['enrollment_pk'] ?>]" id="not_met_<?= $student['enrollment_pk'] ?>" value="not_met" <?= $currentLevel === 'not_met' ? 'checked' : '' ?> data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <label class="btn btn-outline-danger" for="not_met_<?= $student['enrollment_pk'] ?>">
                                                    <i class="fas fa-times"></i> Not Met
                                                </label>

                                                <input type="radio" class="btn-check outcome-radio" name="outcome[<?= $student['enrollment_pk'] ?>]" id="pending_<?= $student['enrollment_pk'] ?>" value="pending" <?= $currentLevel === 'pending' ? 'checked' : '' ?> data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <label class="btn btn-outline-secondary" for="pending_<?= $student['enrollment_pk'] ?>">
                                                    <i class="fas fa-minus"></i> Not Assessed
                                                </label>
                                            </div>
                                            <span class="save-indicator ms-2" id="indicator_<?= $student['enrollment_pk'] ?>"></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Assessment data is automatically saved when you select an achievement level.
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <span id="save-status" class="badge bg-secondary">
                                <i class="fas fa-circle-notch fa-spin d-none" id="save-spinner"></i>
                                <span id="save-text">Ready</span>
                            </span>
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
                    <li><strong>Enter Achievement Levels:</strong> Click the button to indicate whether each student Met, did Not Meet, or have Not Assessed the learning outcome. <strong>Assessments are saved automatically</strong> when you click.</li>
                    <li><strong>Quick Actions:</strong> Use the buttons above the table to quickly set all students to the same achievement level.</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (defined('DEBUG_MODE') && DEBUG_MODE === true): ?>
    <!-- Debug Information -->
    <div class="card card-warning card-outline mt-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bug"></i> Debug Information</h3>
        </div>
        <div class="card-body">
            <h5>LTI Session Data</h5>
            <pre><?php print_r([
                'lti_authenticated' => $_SESSION['lti_authenticated'] ?? false,
                'lti_is_instructor' => $_SESSION['lti_is_instructor'] ?? false,
                'lti_full_name' => $ltiUserName,
                'lti_user_email' => $ltiUserEmail,
                'lti_course_number' => $ltiCourseNumber,
                'lti_course_name' => $ltiCourseName,
                'lti_crn' => $ltiCrn,
                'lti_term_id' => $ltiTermId,
                'lti_academic_year' => $ltiAcademicYear,
                'lti_term_number' => $ltiTermNumber,
            ]); ?></pre>
            
            <h5>Course Sections Available</h5>
            <pre><?php print_r($courseSections); ?></pre>
            
            <h5>Selected Course Data</h5>
            <pre><?php print_r([
                'selectedCrn' => $selectedCrn,
                'selectedTermCode' => $selectedTermCode,
                'courseFkForSlo' => $courseFkForSlo,
                'courseNumber' => $courseNumber,
            ]); ?></pre>
            
            <h5>Student Learning Outcomes (SLOs)</h5>
            <pre><?php print_r($slos); ?></pre>
            
            <h5>Selected SLO</h5>
            <pre><?php print_r(['selectedSloId' => $selectedSloId]); ?></pre>
            
            <h5>Enrolled Students</h5>
            <pre><?php print_r($students); ?></pre>
            
            <h5>Database Prefix</h5>
            <pre><?php echo htmlspecialchars($dbPrefix); ?></pre>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// CSRF token for AJAX requests
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const selectedCrn = '<?= htmlspecialchars($selectedCrn) ?>';
const selectedTermCode = '<?= htmlspecialchars($selectedTermCode) ?>';
const selectedSloId = <?= $selectedSloId ?>;

// Save individual assessment via AJAX
function saveAssessment(enrollmentId, achievementLevel) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'save_assessments');
    formData.append('crn', selectedCrn);
    formData.append('term_code', selectedTermCode);
    formData.append('slo_id', selectedSloId);
    formData.append('outcome[' + enrollmentId + ']', achievementLevel);
    
    // Send AJAX request
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Check if response indicates success (simple check)
        if (html.includes('Assessment data saved successfully')) {
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-check text-success"></i>';
                setTimeout(() => {
                    indicator.innerHTML = '';
                }, 2000);
            }
            statusBadge.className = 'badge bg-success';
            statusText.textContent = 'Saved';
            spinner.classList.add('d-none');
            
            setTimeout(() => {
                statusBadge.className = 'badge bg-secondary';
                statusText.textContent = 'Ready';
            }, 2000);
        } else {
            throw new Error('Save failed');
        }
    })
    .catch(error => {
        console.error('Error saving assessment:', error);
        if (indicator) {
            indicator.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
        }
        statusBadge.className = 'badge bg-danger';
        statusText.textContent = 'Error';
        spinner.classList.add('d-none');
        
        setTimeout(() => {
            if (indicator) indicator.innerHTML = '';
            statusBadge.className = 'badge bg-secondary';
            statusText.textContent = 'Ready';
        }, 3000);
    });
}

// Attach event listeners to all radio buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.outcome-radio').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const enrollmentId = this.getAttribute('data-enrollment');
                const achievementLevel = this.value;
                saveAssessment(enrollmentId, achievementLevel);
            }
        });
    });
});

// Set all outcomes and save each
function setAllOutcomes(outcome) {
    document.querySelectorAll('.outcome-buttons').forEach(function(btnGroup) {
        const enrollmentId = btnGroup.getAttribute('data-enrollment');
        const radio = document.getElementById(outcome + '_' + enrollmentId);
        if (radio && !radio.checked) {
            radio.checked = true;
            saveAssessment(enrollmentId, outcome);
        }
    });
}
</script>

<?php $theme->showFooter($context); ?>
