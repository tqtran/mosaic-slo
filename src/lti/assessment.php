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
        if ($action === 'save_method') {
            // AJAX handler for saving just the assessment method
            $crn = $_POST['crn'] ?? '';
            $termCode = $_POST['term_code'] ?? '';
            $sloId = (int)($_POST['slo_id'] ?? 0);
            $assessmentMethod = trim($_POST['assessment_method'] ?? '');
            
            if (empty($crn) || empty($termCode) || $sloId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid course section or SLO']);
                exit;
            }
            
            // Allow empty assessment method (means user is clearing/deselecting it)
            $userId = $_SESSION['user_id'] ?? null;
            $methodExists = $db->query(
                "SELECT section_slo_methods_pk FROM {$dbPrefix}section_slo_methods 
                 WHERE crn = ? AND term_code = ? AND student_learning_outcome_fk = ?",
                [$crn, $termCode, $sloId],
                'ssi'
            );
            
            if ($methodExists->rowCount() > 0) {
                // Update existing method (can be set to empty/NULL to clear)
                $db->query(
                    "UPDATE {$dbPrefix}section_slo_methods 
                     SET assessment_method = ?, assessed_date = CURDATE(), updated_at = NOW(), updated_by_fk = ?
                     WHERE crn = ? AND term_code = ? AND student_learning_outcome_fk = ?",
                    [$assessmentMethod, $userId, $crn, $termCode, $sloId],
                    'sissi'
                );
            } else {
                // Only insert if assessment method is provided (not empty)
                if (!empty($assessmentMethod)) {
                    $db->query(
                        "INSERT INTO {$dbPrefix}section_slo_methods 
                         (crn, term_code, student_learning_outcome_fk, assessment_method, assessed_date, created_at, updated_at, created_by_fk, updated_by_fk)
                         VALUES (?, ?, ?, ?, CURDATE(), NOW(), NOW(), ?, ?)",
                        [$crn, $termCode, $sloId, $assessmentMethod, $userId, $userId],
                        'ssisii'
                    );
                }
            }
            
            $message = empty($assessmentMethod) ? 'Assessment method cleared' : 'Assessment method saved';
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
            
        } elseif ($action === 'save_strategies') {
            // AJAX handler for saving improvement strategies
            $crn = $_POST['crn'] ?? '';
            $termCode = $_POST['term_code'] ?? '';
            $sloId = (int)($_POST['slo_id'] ?? 0);
            $improvementStrategies = $_POST['improvement_strategies'] ?? [];
            
            if (empty($crn) || empty($termCode) || $sloId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid course section or SLO']);
                exit;
            }
            
            // Convert strategies array to pipe-separated string (using || to avoid conflicts with commas in strategies)
            $strategiesString = '';
            if (is_array($improvementStrategies) && !empty($improvementStrategies)) {
                $strategiesString = implode('||', array_map('trim', $improvementStrategies));
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            $methodExists = $db->query(
                "SELECT section_slo_methods_pk FROM {$dbPrefix}section_slo_methods 
                 WHERE crn = ? AND term_code = ? AND student_learning_outcome_fk = ?",
                [$crn, $termCode, $sloId],
                'ssi'
            );
            
            if ($methodExists->rowCount() > 0) {
                // Update existing record
                $db->query(
                    "UPDATE {$dbPrefix}section_slo_methods 
                     SET improvement_strategies = ?, updated_at = NOW(), updated_by_fk = ?
                     WHERE crn = ? AND term_code = ? AND student_learning_outcome_fk = ?",
                    [$strategiesString, $userId, $crn, $termCode, $sloId],
                    'sissi'
                );
            } else {
                // Insert new record with just strategies (no assessment method yet)
                $db->query(
                    "INSERT INTO {$dbPrefix}section_slo_methods 
                     (crn, term_code, student_learning_outcome_fk, improvement_strategies, created_at, updated_at, created_by_fk, updated_by_fk)
                     VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                    [$crn, $termCode, $sloId, $strategiesString, $userId, $userId],
                    'ssisii'
                );
            }
            
            $count = is_array($improvementStrategies) ? count($improvementStrategies) : 0;
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Saved {$count} improvement strategies"]);
            exit;
            
        } elseif ($action === 'save_assessments') {
            $crn = $_POST['crn'] ?? '';
            $termCode = $_POST['term_code'] ?? '';
            $sloId = (int)($_POST['slo_id'] ?? 0);
            $outcomes = $_POST['outcome'] ?? [];
            
            if (empty($crn) || empty($termCode) || $sloId <= 0) {
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
                         SET achievement_level = ?, assessed_date = CURDATE(), 
                             updated_at = NOW(), assessed_by_fk = ?
                         WHERE assessments_pk = ?",
                        [$achievementLevel, $assessorId, $row['assessments_pk']],
                        'sii'
                    );
                } else {
                    // Insert new assessment
                    $db->query(
                        "INSERT INTO {$dbPrefix}assessments 
                         (enrollment_fk, student_learning_outcome_fk, achievement_level,
                          assessed_date, is_finalized, assessed_by_fk, created_at, updated_at)
                         VALUES (?, ?, ?, CURDATE(), FALSE, ?, NOW(), NOW())",
                        [$enrollmentId, $sloId, $achievementLevel, $assessorId],
                        'iisi'
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
            
            $message = "{$saved} student(s) assessed";
            if ($skipped > 0) {
                $message .= " ({$skipped} skipped)";
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message, 'saved' => $saved, 'skipped' => $skipped]);
            exit;
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
        
        // Return JSON for AJAX requests
        if ($action === 'save_assessments' || $action === 'save_method') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        
        $errorMessage = 'Failed to save assessments: ' . htmlspecialchars($e->getMessage());
        
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

// Get current assessment method for this section/SLO if it exists
$currentAssessmentMethod = '';
$currentImprovementStrategies = [];
if ($selectedCrn && $selectedTermCode && $selectedSloId > 0) {
    $methodQuery = "
        SELECT assessment_method, improvement_strategies
        FROM {$dbPrefix}section_slo_methods
        WHERE crn = ? AND term_code = ? AND student_learning_outcome_fk = ?
    ";
    $methodResult = $db->query($methodQuery, [$selectedCrn, $selectedTermCode, $selectedSloId], 'ssi');
    $methodRow = $methodResult->fetch();
    if ($methodRow) {
        $currentAssessmentMethod = $methodRow['assessment_method'];
        if (!empty($methodRow['improvement_strategies'])) {
            $currentImprovementStrategies = explode('||', $methodRow['improvement_strategies']);
            $currentImprovementStrategies = array_map('trim', $currentImprovementStrategies);
        }
    }
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
        margin: -1rem -1rem 30px -1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .lti-header h2 {
        color: white;
        margin: 0;
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
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }
    .toast {
        min-width: 250px;
        background: white;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        margin-bottom: 10px;
        overflow: hidden;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    }
    .toast.show {
        opacity: 1;
        transform: translateX(0);
    }
    .toast-header {
        padding: 10px 15px;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .toast-body {
        padding: 10px 15px;
    }
    .toast.success .toast-header {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
    }
    .toast.error .toast-header {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
    }
    .toast.info .toast-header {
        background-color: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
    }
    .slo-description {
        line-height: 1.4;
    }
    .btn.slo-button {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: flex-start;
        padding: 10px 15px;
        white-space: normal;
        height: auto;
        text-align: left;
    }
    .card-header {
        padding: 0.75rem 1.25rem;
    }
    .card-header .card-title {
        margin-bottom: 0;
    }
    .card-tools .btn-tool {
        color: inherit;
        font-size: 1.2rem;
    }
    .slo-content {
        opacity: 0;
        transition: opacity 0.5s ease-in;
    }
    .slo-content.fade-in {
        opacity: 1;
    }
    
    /* ==================== WCAG 2.2 AA Accessibility Styles ==================== */
    
    /* Skip link - visible on focus for keyboard navigation */
    .skip-link {
        position: absolute;
        top: 0;
        left: 0;
        z-index: 10000;
        padding: 0.75rem 1.5rem;
        transform: translateY(-100%);
    }
    .skip-link:focus {
        transform: translateY(0);
    }
    
    /* Enhanced focus indicators (WCAG 2.4.7, 2.4.11) */
    a:focus,
    button:focus,
    input:focus,
    select:focus,
    textarea:focus,
    .btn:focus,
    .btn-check:focus + label,
    [tabindex]:focus {
        outline: 3px solid #0D47A1;
        outline-offset: 2px;
        box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.2);
    }
    
    /* Remove default Bootstrap focus styles to avoid double outlines */
    .btn-check:focus + .btn {
        box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.2);
    }
    
    /* Focus visible for :focus-visible support */
    *:focus:not(:focus-visible) {
        outline: none;
        box-shadow: none;
    }
    *:focus-visible {
        outline: 3px solid #0D47A1;
        outline-offset: 2px;
        box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.2);
    }
    
    /* Improved color contrast for muted text (WCAG 1.4.3) */
    .text-muted {
        color: #5a6268 !important; /* 4.5:1 contrast ratio on white */
    }
    
    /* Visually hidden but accessible to screen readers */
    .visually-hidden-focusable:not(:focus):not(:focus-within) {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }
    
    /* Touch target sizes (WCAG 2.5.8) - minimum 24x24px */
    .btn-sm {
        min-height: 32px;
        min-width: 32px;
        padding: 0.25rem 0.75rem;
    }
    .btn-tool {
        min-height: 32px;
        min-width: 32px;
    }
    
    /* Remove default fieldset styling to prevent spacing issues */
    fieldset.outcome-buttons {
        border: 0;
        margin: 0;
        padding: 0;
        min-width: 0;
    }
    
    /* Table caption for screen readers */
    caption {
        caption-side: top;
        text-align: left;
        padding: 0.75rem;
    }
    
    /* Ensure save indicators are announced but not visually intrusive */
    .save-indicator[role="status"] {
        min-width: 20px;
        min-height: 20px;
        display: inline-block;
    }
    
    /* ==================== End Accessibility Styles ==================== */
</style>
<?php
$customStyles = ob_get_clean();

// Load theme system (auto-loads ThemeContext and Theme)
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'embedded',
    'pageTitle' => 'SLO Assessment Entry - ' . SITE_NAME,
    'customCss' => $customStyles
]);

// Use embedded layout for iframe embedding with AdminLTE support
$theme = ThemeLoader::getActiveTheme(null, 'embedded');
$theme->showHeader($context);
?>

<!-- Skip Navigation Link for Keyboard Users -->
<a href="#main-content" class="skip-link visually-hidden-focusable btn btn-primary">Skip to main content</a>

<header role="banner">
<h1 class="mb-4">SLO Assessment Entry</h1>

<div class="lti-header" aria-label="Course and instructor information">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <?php if ($ltiCourseName): ?>
                    <h2 class="mb-0">
                        <?= htmlspecialchars($ltiCourseName) ?>
                    </h2>
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
</header>

<main id="main-content" role="main">
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
        <!-- Instructions Card -->
        <div class="card card-info card-outline mb-3" id="instructions-card">
            <div class="card-header">
                <h2 class="card-title" id="instructions-title"><i class="fas fa-question-circle" aria-hidden="true"></i> Instructions</h2>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" 
                            data-lte-toggle="card-collapse" 
                            aria-expanded="true" 
                            aria-controls="instructions-body"
                            aria-label="Collapse instructions section">
                        <i class="fas fa-minus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" id="instructions-body" role="region" aria-labelledby="instructions-title">
                <ol class="mb-2">
                    <li><strong>Select Course SLO:</strong> Choose the specific learning outcome you're assessing.</li>
                    <li><strong>Choose Assessment Type:</strong> Select the type of assessment used (Quiz, Exam, Project, etc.). This is saved per SLO and will be remembered for this course section.</li>
                    <li><strong>Enter Achievement Levels:</strong> Click the button to indicate whether each student Met, did Not Meet, or have Not Assessed the learning outcome.</li>
                    <li><strong>Quick Actions:</strong> Use the buttons above the table to quickly set all students to the same achievement level.</li>
                    <li><strong>Continuous Improvement Strategies (Optional):</strong> Check any strategies you've implemented or plan to implement.</li>
                </ol>
                <p class="mb-0 text-muted"><i class="fas fa-info-circle" aria-hidden="true"></i> <em>Note: Looking for the save button? All changes are saved automatically.</em></p>
            </div>
        </div>

        <!-- SLO Selection Buttons -->
        <div class="card card-primary card-outline mb-3">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-tasks" aria-hidden="true"></i> Select Student Learning Outcome</h2>
            </div>
            <div class="card-body">
                <nav aria-label="Student Learning Outcomes">
                <div class="row g-2" role="group">
                    <?php foreach ($slos as $slo): 
                        $isSelected = $slo['student_learning_outcomes_pk'] == $selectedSloId;
                    ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex">
                            <a href="?crn=<?= urlencode($selectedCrn) ?>&term_code=<?= urlencode($selectedTermCode) ?>&slo_id=<?= $slo['student_learning_outcomes_pk'] ?>" 
                               class="btn slo-button <?= $isSelected ? 'btn-primary' : 'btn-outline-primary' ?> w-100 h-100"
                               <?= $isSelected ? 'aria-current="true"' : '' ?>
                               aria-label="<?= htmlspecialchars($slo['slo_code'] . ': ' . $slo['slo_description']) ?>">
                                <strong><?= htmlspecialchars($slo['slo_code']) ?></strong>
                                <small class="slo-description mt-1"><?= htmlspecialchars($slo['slo_description']) ?></small>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                </nav>
            </div>
        </div>

        <?php if ($selectedSloId): ?>
        <!-- Assessment Entry Form -->
        <div class="slo-content" id="sloContent">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="save_assessments">
            <input type="hidden" name="crn" value="<?= htmlspecialchars($selectedCrn) ?>">
            <input type="hidden" name="term_code" value="<?= htmlspecialchars($selectedTermCode) ?>">
            <input type="hidden" name="slo_id" value="<?= $selectedSloId ?>">

            <!-- Assessment Method Selection -->
            <div class="card card-info card-outline mb-3">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Assessment Method</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="assessment_method" class="form-label">What type assessment are you recording? <span class="text-danger" aria-label="required">*</span></label>
                            <select class="form-select" id="assessment_method" name="assessment_method" required aria-required="true">
                                <option value="">-- Select Assessment Type --</option>
                                <?php
                                $assessmentTypes = explode(',', $config->get('app.assessment_types', 'Quiz,Exam,Project,Assignment'));
                                foreach ($assessmentTypes as $type):
                                    $type = trim($type);
                                    $selected = ($type === $currentAssessmentMethod) ? ' selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($type) ?>"<?= $selected ?>><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                <?php if ($currentAssessmentMethod): ?>
                                    Currently: <strong><?= htmlspecialchars($currentAssessmentMethod) ?></strong>
                                <?php else: ?>
                                    This will be saved with all assessments below.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="card-title">
                        <i class="fas fa-users" aria-hidden="true"></i> Student Assessments (<?= count($students) ?> students)
                    </h2>
                    <div class="card-tools">
                        <div class="btn-group" role="group" aria-label="Quick assessment actions">
                            <button type="button" class="btn btn-sm btn-success" onclick="setAllOutcomes('met')" 
                                    aria-label="Mark all students as met">
                                <i class="fas fa-check" aria-hidden="true"></i> All Met
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="setAllOutcomes('not_met')"
                                    aria-label="Mark all students as not met">
                                <i class="fas fa-times" aria-hidden="true"></i> All Not Met
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="setAllOutcomes('pending')"
                                    aria-label="Mark all students as not assessed">
                                <i class="fas fa-minus" aria-hidden="true"></i> All Unassessed
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0" aria-label="Student assessment results">
                            <caption class="visually-hidden">Student assessment results for selected SLO</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Student ID</th>
                                    <th scope="col">Student Name</th>
                                    <th scope="col">Achievement Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($students as $student): 
                                    // Get current achievement level (enum from database)
                                    $currentLevel = $student['achievement_level'] ?: 'pending';
                                ?>
                                    <tr class="student-row">
                                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></strong>
                                        </td>
                                        <td>
                                            <fieldset class="btn-group btn-group-sm outcome-buttons" role="group" aria-label="Achievement level for <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>" data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <input type="radio" class="btn-check outcome-radio" name="outcome[<?= $student['enrollment_pk'] ?>]" id="met_<?= $student['enrollment_pk'] ?>" value="met" <?= $currentLevel === 'met' ? 'checked' : '' ?> data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <label class="btn btn-outline-success" for="met_<?= $student['enrollment_pk'] ?>">
                                                    <i class="fas fa-check" aria-hidden="true"></i> Met
                                                </label>

                                                <input type="radio" class="btn-check outcome-radio" name="outcome[<?= $student['enrollment_pk'] ?>]" id="not_met_<?= $student['enrollment_pk'] ?>" value="not_met" <?= $currentLevel === 'not_met' ? 'checked' : '' ?> data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <label class="btn btn-outline-danger" for="not_met_<?= $student['enrollment_pk'] ?>">
                                                    <i class="fas fa-times" aria-hidden="true"></i> Not Met
                                                </label>

                                                <input type="radio" class="btn-check outcome-radio" name="outcome[<?= $student['enrollment_pk'] ?>]" id="pending_<?= $student['enrollment_pk'] ?>" value="pending" <?= $currentLevel === 'pending' ? 'checked' : '' ?> data-enrollment="<?= $student['enrollment_pk'] ?>">
                                                <label class="btn btn-outline-secondary" for="pending_<?= $student['enrollment_pk'] ?>">
                                                    <i class="fas fa-minus" aria-hidden="true"></i> Not Assessed
                                                </label>
                                            </fieldset>
                                            <span class="save-indicator ms-2" id="indicator_<?= $student['enrollment_pk'] ?>" role="status" aria-live="polite" aria-atomic="true"></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>

        <!-- Continuous Improvement Strategies -->
        <div class="card card-warning card-outline mt-3">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-lightbulb" aria-hidden="true"></i> Continuous Improvement Strategies (Optional)</h2>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <p class="text-muted mb-0">
                        Select any strategies you've implemented or plan to implement to improve student learning for this outcome.
                    </p>
                    <div class="btn-group" role="group" aria-label="Strategy selection actions">
                        <button type="button" id="selectAllStrategies" class="btn btn-sm btn-outline-primary" aria-label="Select all strategies">
                            <i class="fas fa-check-square" aria-hidden="true"></i> Select All
                        </button>
                        <button type="button" id="selectNoneStrategies" class="btn btn-sm btn-outline-secondary" aria-label="Clear all strategies">
                            <i class="fas fa-square" aria-hidden="true"></i> Select None
                        </button>
                    </div>
                </div>
                <div class="row">
                    <?php
                    $improvementStrategies = explode(',', $config->get('app.improvement_strategies', ''));
                    foreach ($improvementStrategies as $index => $strategy):
                        $strategy = trim($strategy);
                        if (empty($strategy)) continue;
                        $checked = in_array($strategy, $currentImprovementStrategies) ? ' checked' : '';
                        $strategyId = 'strategy_' . $index;
                    ?>
                        <div class="col-md-6 col-lg-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input improvement-strategy-checkbox" type="checkbox" 
                                       id="<?= $strategyId ?>" 
                                       name="improvement_strategies[]" 
                                       value="<?= htmlspecialchars($strategy) ?>"<?= $checked ?>>
                                <label class="form-check-label" for="<?= $strategyId ?>">
                                    <?= htmlspecialchars($strategy) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        </div><!-- /.slo-content -->
        <?php endif; ?>
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
</main>

<!-- Toast Container -->
<div id="toast-container" class="toast-container" role="region" aria-live="polite" aria-atomic="true"></div>

<script>
// CSRF token for AJAX requests
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const selectedCrn = '<?= htmlspecialchars($selectedCrn) ?>';
const selectedTermCode = '<?= htmlspecialchars($selectedTermCode) ?>';
const selectedSloId = <?= $selectedSloId ?>;

// Toast notification system
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-exclamation-circle' : 
                 'fa-info-circle';
    
    toast.innerHTML = `
        <div class="toast-header">
            <i class="fas ${icon} me-2"></i>
            <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
        </div>
        <div class="toast-body">${message}</div>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto-remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Save individual assessment via AJAX
function saveAssessment(enrollmentId, achievementLevel) {
    // Get UI elements for this specific enrollment
    const indicator = document.getElementById('indicator_' + enrollmentId);
    
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
    .then(response => {
        // Parse JSON regardless of status code
        return response.json().then(data => ({
            ok: response.ok,
            status: response.status,
            data: data
        })).catch(jsonError => {
            // If JSON parsing fails, return error info
            return {
                ok: false,
                status: response.status,
                data: { success: false, message: 'Invalid server response' }
            };
        });
    })
    .then(({ok, status, data}) => {
        if (ok && data.success) {
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-check text-success"></i>';
                setTimeout(() => {
                    indicator.innerHTML = '';
                }, 2000);
            }
            showToast('Assessment saved successfully', 'success', 2000);
        } else {
            throw new Error(data.message || 'Save failed');
        }
    })
    .catch(error => {
        console.error('Error saving assessment:', error);
        if (indicator) {
            indicator.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
            setTimeout(() => {
                indicator.innerHTML = '';
            }, 3000);
        }
        showToast(error.message || 'Error saving assessment', 'error', 3000);
    });
}

// Save assessment method when changed
function saveAssessmentMethod(assessmentMethod) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'save_method');
    formData.append('crn', selectedCrn);
    formData.append('term_code', selectedTermCode);
    formData.append('slo_id', selectedSloId);
    formData.append('assessment_method', assessmentMethod);

    showToast('Saving assessment method...', 'info', 2000);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Parse JSON regardless of status code
        return response.json().then(data => ({
            ok: response.ok,
            status: response.status,
            data: data
        })).catch(jsonError => {
            // If JSON parsing fails, return error info
            return {
                ok: false,
                status: response.status,
                data: { success: false, message: 'Invalid server response' }
            };
        });
    })
    .then(({ok, status, data}) => {
        if (ok && data.success) {
            const message = assessmentMethod ? `Assessment method saved: ${assessmentMethod}` : 'Assessment method cleared';
            showToast(message, 'success', 3000);
        } else {
            showToast('Error: ' + (data.message || 'Failed to save'), 'error');
        }
    })
    .catch(error => {
        console.error('Error saving method:', error);
        showToast('Error saving assessment method', 'error');
    });
}

// Save improvement strategies separately
function saveImprovementStrategies() {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'save_strategies');
    formData.append('crn', selectedCrn);
    formData.append('term_code', selectedTermCode);
    formData.append('slo_id', selectedSloId);
    
    // Add improvement strategies from checkboxes
    const checkboxes = document.querySelectorAll('.improvement-strategy-checkbox:checked');
    checkboxes.forEach(checkbox => {
        formData.append('improvement_strategies[]', checkbox.value);
    });

    showToast('Saving improvement strategies...', 'info', 2000);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Parse JSON regardless of status code
        return response.json().then(data => ({
            ok: response.ok,
            status: response.status,
            data: data
        })).catch(jsonError => {
            // If JSON parsing fails, return error info
            return {
                ok: false,
                status: response.status,
                data: { success: false, message: 'Invalid server response' }
            };
        });
    })
    .then(({ok, status, data}) => {
        if (ok && data.success) {
            showToast(data.message || 'Improvement strategies saved', 'success', 3000);
        } else {
            showToast('Error: ' + (data.message || 'Failed to save'), 'error');
        }
    })
    .catch(error => {
        console.error('Error saving strategies:', error);
        showToast('Error saving improvement strategies', 'error');
    });
}

// Attach event listeners to all radio buttons
document.addEventListener('DOMContentLoaded', function() {
    // Fade in SLO content on page load
    const sloContent = document.getElementById('sloContent');
    if (sloContent) {
        setTimeout(() => {
            sloContent.classList.add('fade-in');
        }, 100);
    }
    
    // Collapsible card accessibility - sync aria-expanded with card state
    const collapseButton = document.querySelector('[data-lte-toggle="card-collapse"]');
    const instructionsCard = document.getElementById('instructions-card');
    if (collapseButton && instructionsCard) {
        // Listen for AdminLTE card events
        instructionsCard.addEventListener('collapsed.lte.cardwidget', function() {
            collapseButton.setAttribute('aria-expanded', 'false');
        });
        instructionsCard.addEventListener('expanded.lte.cardwidget', function() {
            collapseButton.setAttribute('aria-expanded', 'true');
        });
        
        // Fallback: manual click handler if AdminLTE doesn't fire events
        collapseButton.addEventListener('click', function() {
            setTimeout(() => {
                const isCollapsed = instructionsCard.classList.contains('collapsed-card');
                collapseButton.setAttribute('aria-expanded', !isCollapsed);
            }, 100);
        });
    }
    
    document.querySelectorAll('.outcome-radio').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const enrollmentId = this.getAttribute('data-enrollment');
                const achievementLevel = this.value;
                saveAssessment(enrollmentId, achievementLevel);
            }
        });
    });
    
    // Assessment method dropdown change handler
    const methodSelect = document.getElementById('assessment_method');
    if (methodSelect) {
        methodSelect.addEventListener('change', function() {
            // Always save, even if empty (allows deselecting)
            saveAssessmentMethod(this.value);
        });
    }
    
    // Improvement strategies checkbox change handler
    document.querySelectorAll('.improvement-strategy-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            saveImprovementStrategies();
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

// Select all improvement strategies
document.getElementById('selectAllStrategies')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.querySelectorAll('.improvement-strategy-checkbox').forEach(function(checkbox) {
        checkbox.checked = true;
    });
    saveImprovementStrategies();
});

// Select none improvement strategies
document.getElementById('selectNoneStrategies')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.querySelectorAll('.improvement-strategy-checkbox').forEach(function(checkbox) {
        checkbox.checked = false;
    });
    saveImprovementStrategies();
});
</script>

<footer role="contentinfo" class="mt-5 py-3 border-top">
    <div class="container-fluid">
        <div class="text-center text-muted">
            <small>v<?= htmlspecialchars(trim(file_get_contents(__DIR__ . '/../VERSION'))) ?></small>
        </div>
    </div>
</footer>

<?php $theme->showFooter($context); ?>
