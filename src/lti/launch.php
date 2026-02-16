<?php
declare(strict_types=1);

/**
 * LTI Launch Handler
 * 
 * Accepts LTI 1.1/1.3 launch requests from Learning Management Systems.
 * Uses pragmatic page pattern (logic + template in one file).
 * 
 * @todo Implement OAuth 1.0 signature validation (LTI 1.1)
 * @todo Implement JWT/JWKS validation (LTI 1.3)
 * @package Mosaic
 */

// Security headers
header('X-Frame-Options: ALLOWALL'); // LTI launches in iframe
header('X-Content-Type-Options: nosniff');

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

// Load core classes
require_once __DIR__ . '/../Core/Config.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Logger.php';
require_once __DIR__ . '/../Core/Path.php';
require_once __DIR__ . '/../includes/message_page.php';

// Check if configured
if (!file_exists(__DIR__ . '/../config/config.yaml')) {
    \Mosaic\Core\Path::redirect('setup/');
}

// Load configuration
$config = \Mosaic\Core\Config::getInstance(__DIR__ . '/../config/config.yaml');
$configData = $config->all();

// Define constants
define('BASE_URL', $configData['app']['base_url'] ?? '/');
define('SITE_NAME', $configData['app']['name'] ?? 'MOSAIC');
define('DEBUG_MODE', ($configData['app']['debug_mode'] ?? 'false') === 'true' || ($configData['app']['debug_mode'] ?? false) === true);

// Initialize database and logger
$db = \Mosaic\Core\Database::getInstance($configData['database']);
$logger = \Mosaic\Core\Logger::getInstance();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_message_page(
        'error',
        'Invalid Request',
        'LTI launches must use POST method.'
    );
    exit;
}

// Log the launch attempt
$logger->info('LTI Launch Attempt', [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Extract LTI parameters
$ltiParams = [
    'user_id' => $_POST['user_id'] ?? '',
    'lis_person_name_given' => $_POST['lis_person_name_given'] ?? '',
    'lis_person_name_family' => $_POST['lis_person_name_family'] ?? '',
    'lis_person_name_full' => $_POST['lis_person_name_full'] ?? '',
    'lis_person_contact_email_primary' => $_POST['lis_person_contact_email_primary'] ?? '',
    'lis_person_sourcedid' => $_POST['lis_person_sourcedid'] ?? '',
    'roles' => $_POST['roles'] ?? '',
    'ext_roles' => $_POST['ext_roles'] ?? '',
    'context_id' => $_POST['context_id'] ?? '',
    'context_label' => $_POST['context_label'] ?? '',
    'context_title' => $_POST['context_title'] ?? '',
    'resource_link_id' => $_POST['resource_link_id'] ?? '',
    'resource_link_title' => $_POST['resource_link_title'] ?? '',
    'lis_course_offering_sourcedid' => $_POST['lis_course_offering_sourcedid'] ?? '',
    'custom_canvas_course_id' => $_POST['custom_canvas_course_id'] ?? '',
    'custom_canvas_user_id' => $_POST['custom_canvas_user_id'] ?? '',
    'tool_consumer_instance_name' => $_POST['tool_consumer_instance_name'] ?? '',
    'tool_consumer_instance_guid' => $_POST['tool_consumer_instance_guid'] ?? '',
    'oauth_consumer_key' => $_POST['oauth_consumer_key'] ?? '',
    'lti_version' => $_POST['lti_version'] ?? 'LTI-1p0',
    'lti_message_type' => $_POST['lti_message_type'] ?? 'basic-lti-launch-request'
];

// Validate required parameters
$requiredParams = ['user_id', 'context_id', 'resource_link_id'];
$missingParams = [];

foreach ($requiredParams as $param) {
    if (empty($ltiParams[$param])) {
        $missingParams[] = $param;
    }
}

if (!empty($missingParams)) {
    $logger->warning('LTI Launch Missing Parameters', [
        'missing' => $missingParams,
        'params' => $ltiParams
    ]);
    
    render_message_page(
        'error',
        'Invalid LTI Launch',
        'Missing required parameters: ' . implode(', ', $missingParams)
    );
    exit;
}

// TODO: Implement OAuth signature validation (LTI 1.1) or JWT validation (LTI 1.3)
// For now, we're bypassing authentication for development

// Determine user role
$roleString = strtolower($ltiParams['roles']);
$isInstructor = (
    strpos($roleString, 'instructor') !== false ||
    strpos($roleString, 'faculty') !== false ||
    strpos($roleString, 'teacher') !== false
);
$isStudent = strpos($roleString, 'learner') !== false || strpos($roleString, 'student') !== false;
$isAdmin = strpos($roleString, 'administrator') !== false;

// Map to internal role
if ($isAdmin) {
    $internalRole = 'System Admin';
} elseif ($isInstructor) {
    $internalRole = 'Faculty';
} elseif ($isStudent) {
    $internalRole = 'Student';
} else {
    $internalRole = 'Student'; // Default
}

// Extract name
$firstName = $ltiParams['lis_person_name_given'];
$lastName = $ltiParams['lis_person_name_family'];
if (empty($firstName) && empty($lastName) && !empty($ltiParams['lis_person_name_full'])) {
    $nameParts = explode(' ', $ltiParams['lis_person_name_full'], 2);
    $firstName = $nameParts[0] ?? 'LTI';
    $lastName = $nameParts[1] ?? 'User';
}
if (empty($firstName)) {
    $firstName = 'LTI';
}
if (empty($lastName)) {
    $lastName = 'User';
}

// Parse CRN and term from lis_course_offering_sourcedid (format: CRN.TERMID)
// Example: 23477.202523 -> CRN=23477, TermID=202523
$crn = null;
$termId = null;
$academicYear = null;
$termNumber = null;

if (!empty($ltiParams['lis_course_offering_sourcedid'])) {
    $parts = explode('.', $ltiParams['lis_course_offering_sourcedid']);
    if (count($parts) >= 1) {
        $crn = $parts[0]; // CRN: 23477
    }
    if (count($parts) >= 2) {
        $termId = $parts[1]; // Term ID: 202523
        // Parse YYYYTT format (e.g., 202523 = year 2025, term 23)
        if (strlen($termId) >= 4) {
            $academicYear = substr($termId, 0, 4);
            $termNumber = substr($termId, 4);
        }
    }
}

// Parse course number from context_label (format: COURSENUM-CRN (SECTION))
// Example: CIS157-23477 (ONL) -> CourseNumber=CIS157
$courseNumber = null;
if (!empty($ltiParams['context_label'])) {
    $labelParts = explode('-', $ltiParams['context_label']);
    if (count($labelParts) >= 1) {
        $courseNumber = trim($labelParts[0]); // Course Number: CIS157
    }
}

// Course name is the full context_title
// Example: CIS157-23477 (ONL) Intro to Python Programming
$courseName = $ltiParams['context_title'] ?? '';

// Store LTI session data
$_SESSION['lti_authenticated'] = true;
$_SESSION['lti_user_id'] = $ltiParams['user_id'];
$_SESSION['lti_user_email'] = $ltiParams['lis_person_contact_email_primary'];
$_SESSION['lti_first_name'] = $firstName;
$_SESSION['lti_last_name'] = $lastName;
$_SESSION['lti_full_name'] = trim($firstName . ' ' . $lastName);
$_SESSION['lti_role'] = $internalRole;
$_SESSION['lti_is_instructor'] = $isInstructor;
$_SESSION['lti_is_student'] = $isStudent;
$_SESSION['lti_context_id'] = $ltiParams['context_id'];
$_SESSION['lti_context_label'] = $ltiParams['context_label'];
$_SESSION['lti_course_id'] = $ltiParams['lis_course_offering_sourcedid'];
$_SESSION['lti_crn'] = $crn;
$_SESSION['lti_term_id'] = $termId;
$_SESSION['lti_academic_year'] = $academicYear;
$_SESSION['lti_term_number'] = $termNumber;
$_SESSION['lti_course_number'] = $courseNumber;
$_SESSION['lti_course_name'] = $courseName;
$_SESSION['lti_resource_link_id'] = $ltiParams['resource_link_id'];
$_SESSION['lti_consumer_key'] = $ltiParams['oauth_consumer_key'];
$_SESSION['lti_consumer_name'] = $ltiParams['tool_consumer_instance_name'];

// Log successful launch
$logger->info('LTI Launch Successful', [
    'user_id' => $ltiParams['user_id'],
    'role' => $internalRole,
    'course_number' => $courseNumber,
    'crn' => $crn,
    'term_id' => $termId
]);

// Redirect based on role
if ($isInstructor) {
    // Instructors go to assessment interface
    \Mosaic\Core\Path::redirect('lti/assessment.php');
} elseif ($isStudent) {
    // Students see their results (TODO: implement student view)
    render_message_page(
        'info',
        'Student View',
        'Student assessment results will be displayed here.',
        'fa-info-circle text-info'
    );
    exit;
} else {
    // Unknown role
    render_message_page(
        'error',
        'Access Denied',
        'Your role does not have access to this tool.'
    );
    exit;
}
