<?php
declare(strict_types=1);

/**
 * MOSAIC Web Setup Interface
 * 
 * Browser-based installation wizard for configuring MOSAIC.
 * Creates database and saves configuration to config.yaml.
 * 
 * @package Mosaic\Setup
 */

// Load path helper for proper redirects
require_once __DIR__ . '/../system/Core/Path.php';

// Define base URL for easy access in this script
define('BASE_URL', \Mosaic\Core\Path::getBaseUrl());

// Prevent setup from running if already configured
$configFile = __DIR__ . '/../config/config.yaml';
if (file_exists($configFile)) {
    \Mosaic\Core\Path::redirect('administration/');
}

// Initialize session for form data persistence
session_start();

// Process form submission
$error = null;
$success = false;
$step = $_GET['step'] ?? 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_submit'])) {
    // Validate and sanitize input
    $site_name = trim($_POST['site_name'] ?? 'MOSAIC');
    $base_url = trim($_POST['base_url'] ?? BASE_URL);
    $db_driver = trim($_POST['db_driver'] ?? 'mysql');
    $db_host = trim($_POST['db_host'] ?? '');
    $db_port = filter_var($_POST['db_port'] ?? 3306, FILTER_VALIDATE_INT);
    $db_name = trim($_POST['db_name'] ?? '');
    $db_prefix = trim($_POST['db_prefix'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? ''; // Don't trim password
    
    // Email Configuration (optional)
    $mail_method = trim($_POST['mail_method'] ?? 'disabled');
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = filter_var($_POST['smtp_port'] ?? 587, FILTER_VALIDATE_INT);
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = $_POST['smtp_pass'] ?? ''; // Don't trim password
    $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? $site_name);
    $smtp_encryption = trim($_POST['smtp_encryption'] ?? 'tls');
    
    // Validation
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Database host, name, and username are required.';
    } elseif (!in_array($db_driver, ['mysql', 'mssql'])) {
        $error = 'Invalid database driver. Must be mysql or mssql.';
    } elseif (!empty($db_prefix) && !preg_match('/^[a-zA-Z0-9_]+$/', $db_prefix)) {
        $error = 'Table prefix can only contain letters, numbers, and underscores.';
    } elseif (empty($base_url) || !str_starts_with($base_url, '/')) {
        $error = 'Base URL is required and must start with /';
    } elseif ($db_port === false || $db_port < 1 || $db_port > 65535) {
        $error = 'Invalid port number. Must be between 1 and 65535.';
    } else {
        // Attempt connection with PDO (supports both MySQL and MSSQL)
        try {
            // Build DSN based on driver
            if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                $dsn = sprintf('sqlsrv:Server=%s,%d', $db_host, $db_port);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db_host, $db_port);
            }
            
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
        } catch (PDOException $e) {
            $error = 'Connection failed: ' . $e->getMessage();
            $pdo = null;
        }
        
        if ($pdo !== null) {
            // Connection successful - try to create database (may fail on shared hosting)
            $createSuccess = false;
            try {
                if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                    // MSSQL database creation
                    $pdo->exec("CREATE DATABASE [$db_name]");
                    $createSuccess = true;
                } else {
                    // MySQL database creation
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $createSuccess = true;
                }
            } catch (PDOException $e) {
                // User doesn't have CREATE DATABASE privilege - that's okay on shared hosting
                // We'll try to connect to existing database below
                $createSuccess = false;
            }
            
            // Reconnect with database selected
            try {
                if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                    $dsn = sprintf('sqlsrv:Server=%s,%d;Database=%s', $db_host, $db_port, $db_name);
                } else {
                    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db_host, $db_port, $db_name);
                }
                
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Database connected successfully - proceed with schema installation
                // Use appropriate schema file based on driver
                $schemaFile = ($db_driver === 'mssql' || $db_driver === 'sqlsrv') 
                    ? __DIR__ . '/../system/database/schema_mssql.sql'
                    : __DIR__ . '/../system/database/schema.sql';
                
                if (!file_exists($schemaFile)) {
                    $error = 'Schema file not found at: ' . $schemaFile;
                } else {
                    $schema = file_get_contents($schemaFile);
                    
                    // Drop existing tables if they exist (for clean reinstall)
                    $tables = [
                        'lti_nonces', 'security_log', 'error_log', 'audit_log',
                        'assessments', 'enrollment', 'terms',
                        'student_learning_outcomes', 'slo_sets',
                        'program_outcomes', 'programs',
                        'institutional_outcomes', 'institution',
                        'user_roles', 'roles', 'users'
                    ];
                        
                    try {
                        if ($db_driver === 'mysql') {
                            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                        }
                        foreach ($tables as $table) {
                            $tableName = $db_prefix . $table;
                            if ($db_driver === 'mssql' || $db_driver === 'sqlsrv') {
                                @$pdo->exec("DROP TABLE IF EXISTS [$tableName]");
                            } else {
                                @$pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                            }
                        }
                        if ($db_driver === 'mysql') {
                            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                        }
                    } catch (PDOException $e) {
                        // Ignore errors from dropping non-existent tables
                    }
                        
                    // Apply table prefix if configured
                    if (!empty($db_prefix)) {
                        // Replace table names with prefixed versions
                        foreach ($tables as $table) {
                            $schema = preg_replace(
                                '/\b' . preg_quote($table, '/') . '\b/',
                                $db_prefix . $table,
                                $schema
                            );
                        }
                    }
                    
                    // Execute schema - split into individual statements for sequential execution
                    try {
                            // Split schema by semicolons (simple approach for our controlled schema files)
                            $statements = array_filter(
                                array_map('trim', explode(';', $schema)),
                                fn($stmt) => !empty($stmt) && !preg_match('/^\s*(--|#)/', $stmt)
                            );
                            
                            foreach ($statements as $statement) {
                                if (!empty(trim($statement))) {
                                    $pdo->exec($statement);
                                }
                            }
                            
                            // Schema executed successfully - now import seed data if available
                            $seedFile = __DIR__ . '/seed_data.csv';
                            $seedStats = ['programs' => 0, 'slo_sets' => 0, 'terms' => 0, 'slos' => 0, 'enrollment' => 0, 'assessments' => 0];
                            
                            if (file_exists($seedFile)) {
                                try {
                                    $handle = fopen($seedFile, 'r');
                                    if ($handle !== false) {
                                        // Read header row
                                        $headers = fgetcsv($handle);
                                        
                                        // Collections for unique values (to avoid duplicates)
                                        $programs = [];
                                        $slo_sets = [];
                                        $terms_data = [];
                                        $slos = [];
                                        $enrollments = [];
                                        $assessments = [];
                                        
                                        // Parse CSV and collect unique entities
                                        while (($row = fgetcsv($handle)) !== false) {
                                            if (count($row) < 16) continue; // Skip invalid rows
                                            
                                            $data = array_combine($headers, $row);
                                            
                                            // Extract program (unique by Program column)
                                            if (!empty($data['Program'])) {
                                                $programCode = strtoupper(str_replace(' ', '_', $data['Program']));
                                                if (!isset($programs[$programCode])) {
                                                    $programs[$programCode] = [
                                                        'code' => $programCode,
                                                        'name' => $data['Program'],
                                                        'degree_type' => 'AS' // Default
                                                    ];
                                                }
                                            }
                                            
                                            // Student data will be denormalized into enrollment table
                                            
                                            // Extract SLO Set (academic year)
                                            if (!empty($data['Academic Year'])) {
                                                $setCode = str_replace('-', '_', $data['Academic Year']);
                                                if (!isset($slo_sets[$setCode])) {
                                                    $years = explode('-', $data['Academic Year']);
                                                    $startYear = '20' . $years[0];
                                                    $endYear = '20' . $years[1];
                                                    $slo_sets[$setCode] = [
                                                        'set_code' => $setCode,
                                                        'set_name' => 'Academic Year ' . $data['Academic Year'],
                                                        'set_type' => 'year',
                                                        'start_date' => $startYear . '-08-01',
                                                        'end_date' => $endYear . '-07-31'
                                                    ];
                                                }
                                            }
                                            
                                            // Extract term
                                            if (!empty($data['Term']) && !empty($data['Academic Year'])) {
                                                $setCode = str_replace('-', '_', $data['Academic Year']);
                                                $termCode = str_replace(' ', '_', strtoupper($data['Term']));
                                                $termKey = $setCode . '_' . $termCode;
                                                
                                                if (!isset($terms_data[$termKey])) {
                                                    $year = '20' . explode('-', $data['Academic Year'])[0];
                                                    if (stripos($data['Term'], 'Spring') !== false) {
                                                        $year = '20' . explode('-', $data['Academic Year'])[1];
                                                        $start = $year . '-01-15';
                                                        $end = $year . '-05-31';
                                                    } else {
                                                        $start = $year . '-08-15';
                                                        $end = $year . '-12-31';
                                                    }
                                                    
                                                    $terms_data[$termKey] = [
                                                        'slo_set_code' => $setCode,
                                                        'term_code' => $termCode,
                                                        'term_name' => $data['Term'],
                                                        'term_year' => (int)$year,
                                                        'start_date' => $start,
                                                        'end_date' => $end
                                                    ];
                                                }
                                            }
                                            
                                            // Extract SLO (course + CSLO within academic year set)
                                            if (!empty($data['CSLO']) && !empty($data['SLO Language']) && !empty($data['Course'])) {
                                                $setCode = str_replace('-', '_', $data['Academic Year']);
                                                $courseCode = str_replace(' ', '_', $data['Course']);
                                                $csloCode = str_replace(' ', '_', strtoupper($data['CSLO']));
                                                $sloCode = $courseCode . '_' . $csloCode;  // e.g., JAPN_C185_CSLO_1
                                                $sloKey = $setCode . '_' . $sloCode;
                                                
                                                if (!isset($slos[$sloKey])) {
                                                    $slos[$sloKey] = [
                                                        'slo_set_code' => $setCode,
                                                        'slo_code' => $sloCode,
                                                        'description' => substr($data['SLO Language'], 0, 500),
                                                        'assessment_method' => $data['Assessment'] ?? 'Assignment',
                                                        'sequence_num' => count($slos) + 1
                                                    ];
                                                }
                                            }
                                            
                                            // Extract enrollment with all denormalized context data
                                            if (!empty($data['CRN']) && !empty($data['StudentID']) && !empty($data['Term'])) {
                                                $termCode = str_replace(' ', '_', strtoupper($data['Term']));
                                                $enrollKey = $termCode . '_' . $data['CRN'] . '_' . $data['StudentID'];
                                                
                                                if (!isset($enrollments[$enrollKey])) {
                                                    $enrollments[$enrollKey] = [
                                                        'term_code' => $termCode,
                                                        'crn' => $data['CRN'],
                                                        'student_id' => $data['StudentID'],
                                                        'first_name' => 'Student',
                                                        'last_name' => substr($data['StudentID'], 1), // C00123456 -> 00123456
                                                        'academic_year' => $data['Academic Year'] ?? null,
                                                        'semester' => $data['Semester'] ?? null,
                                                        'course_code' => $data['Course'] ?? null,
                                                        'course_title' => $data['Title'] ?? null,
                                                        'course_modality' => $data['Modality'] ?? null,
                                                        'program_name' => $data['Program'] ?? null,
                                                        'subject_code' => $data['Sub Code'] ?? null,
                                                        'subject_name' => $data['Subject'] ?? null,
                                                        'enrollment_status' => ($data['Course Status'] === 'Active') ? 'enrolled' : 'dropped',
                                                        'enrollment_date' => $terms_data[$setCode . '_' . $termCode]['start_date'] ?? date('Y-m-d')
                                                    ];
                                                }
                                            }
                                            
                                            // Store assessment for later (after enrollment is inserted)
                                            if (!empty($data['CSLO']) && !empty($data['Met/Not Met']) && !empty($data['Course'])) {
                                                $termCode = str_replace(' ', '_', strtoupper($data['Term']));
                                                $courseCode = str_replace(' ', '_', $data['Course']);
                                                $csloCode = str_replace(' ', '_', strtoupper($data['CSLO']));
                                                $sloCode = $courseCode . '_' . $csloCode;
                                                
                                                $assessments[] = [
                                                    'term_code' => $termCode,
                                                    'crn' => $data['CRN'],
                                                    'student_id' => $data['StudentID'],
                                                    'slo_code' => $sloCode,
                                                    'score' => ($data['Met/Not Met'] === 'Met') ? 1 : 0,
                                                    'assessment_type' => $data['Assessment'] ?? 'Assignment',
                                                    'assessment_date' => date('Y-m-d')
                                                ];
                                            }
                                        }
                                        
                                        fclose($handle);
                                        
                                        // Insert data in foreign key dependency order
                                        $pdo->beginTransaction();
                                        
                                        // 1. Programs
                                        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}programs (program_code, program_name, degree_type, is_active) VALUES (?, ?, ?, 1)");
                                        foreach ($programs as $program) {
                                            $stmt->execute([$program['code'], $program['name'], $program['degree_type']]);
                                            $seedStats['programs']++;
                                        }
                                        
                                        // 2. SLO Sets
                                        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}slo_sets (set_code, set_name, set_type, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                                        foreach ($slo_sets as $set) {
                                            $stmt->execute([$set['set_code'], $set['set_name'], $set['set_type'], $set['start_date'], $set['end_date']]);
                                            $seedStats['slo_sets']++;
                                        }
                                        
                                        // 4. Terms
                                        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}terms (slo_set_code, term_code, term_name, term_year, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                                        foreach ($terms_data as $term) {
                                            $stmt->execute([$term['slo_set_code'], $term['term_code'], $term['term_name'], $term['term_year'], $term['start_date'], $term['end_date']]);
                                            $seedStats['terms']++;
                                        }
                                        
                                        // 5. Student Learning Outcomes
                                        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}student_learning_outcomes (slo_set_code, slo_code, description, assessment_method, sequence_num, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                                        foreach ($slos as $slo) {
                                            $stmt->execute([$slo['slo_set_code'], $slo['slo_code'], $slo['description'], $slo['assessment_method'], $slo['sequence_num']]);
                                            $seedStats['slos']++;
                                        }
                                        
                                        // 6. Enrollment
                                        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}enrollment 
                                            (term_code, crn, student_id, student_first_name, student_last_name, 
                                             academic_year, semester, course_code, course_title, course_modality, 
                                             program_name, subject_code, subject_name, enrollment_status, enrollment_date) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                        foreach ($enrollments as $enrollment) {
                                            $stmt->execute([
                                                $enrollment['term_code'], 
                                                $enrollment['crn'], 
                                                $enrollment['student_id'], 
                                                $enrollment['first_name'], 
                                                $enrollment['last_name'],
                                                $enrollment['academic_year'],
                                                $enrollment['semester'],
                                                $enrollment['course_code'],
                                                $enrollment['course_title'],
                                                $enrollment['course_modality'],
                                                $enrollment['program_name'],
                                                $enrollment['subject_code'],
                                                $enrollment['subject_name'],
                                                $enrollment['enrollment_status'], 
                                                $enrollment['enrollment_date']
                                            ]);
                                            $seedStats['enrollment']++;
                                        }
                                        
                                        // 7. Assessments
                                        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}assessments (enrollment_fk, student_learning_outcome_fk, score_value, achievement_level, assessed_date, notes)
                                                               SELECT e.enrollment_pk, s.student_learning_outcomes_pk, ?, ?, ?, ?
                                                               FROM {$db_prefix}enrollment e
                                                               JOIN {$db_prefix}student_learning_outcomes s ON s.slo_code = ?
                                                               WHERE e.term_code = ? AND e.crn = ? AND e.student_id = ?");
                                        foreach ($assessments as $assessment) {
                                            $achievementLevel = $assessment['score'] == 1 ? 'met' : 'not_met';
                                            $stmt->execute([
                                                $assessment['score'],
                                                $achievementLevel,
                                                $assessment['assessment_date'],
                                                $assessment['assessment_type'] ?? null,
                                                $assessment['slo_code'],
                                                $assessment['term_code'],
                                                $assessment['crn'],
                                                $assessment['student_id']
                                            ]);
                                            $seedStats['assessments']++;
                                        }
                                        
                                        $pdo->commit();
                                    }
                                } catch (Exception $e) {
                                    if (isset($pdo) && $pdo->inTransaction()) {
                                        $pdo->rollBack();
                                    }
                                    // Don't fail setup if seed data import fails - log it but continue
                                    error_log('Seed data import failed: ' . $e->getMessage());
                                }
                            }
                            
                            // Save configuration
                                    $configDir = __DIR__ . '/../config';
                                    if (!is_dir($configDir)) {
                                        mkdir($configDir, 0755, true);
                                    }
                                    
                                    $configContent = "# MOSAIC Configuration\n";
                                    $configContent .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
                                    $configContent .= "database:\n";
                                    $configContent .= "  driver: " . $db_driver . "\n";
                                    $configContent .= "  host: " . $db_host . "\n";
                                    $configContent .= "  port: " . $db_port . "\n";
                                    $configContent .= "  name: " . $db_name . "\n";
                                    $configContent .= "  prefix: " . $db_prefix . "\n";
                                    $configContent .= "  username: " . $db_user . "\n";
                                    $configContent .= "  password: " . $db_pass . "\n";
                                    $configContent .= "  charset: utf8mb4\n\n";
                                    $configContent .= "app:\n";
                                    $configContent .= "  name: " . $site_name . "\n";
                                    $configContent .= "  timezone: America/Los_Angeles\n";
                                    $configContent .= "  base_url: " . $base_url . "\n";
                                    $configContent .= "  debug_mode: true\n\n";
                                    $configContent .= "# Theme Configuration\n";
                                    $configContent .= "# Available themes: theme-default, theme-adminlte, theme-metis\n";
                                    $configContent .= "theme:\n";
                                    $configContent .= "  active_theme: theme-default\n\n";
                                    $configContent .= "# Email configuration for notifications\n";
                                    $configContent .= "email:\n";
                                    $configContent .= "  method: " . $mail_method . "\n";
                                    $configContent .= "  from_email: " . $smtp_from_email . "\n";
                                    $configContent .= "  from_name: " . $smtp_from_name . "\n";
                                    $configContent .= "  smtp_host: " . $smtp_host . "\n";
                                    $configContent .= "  smtp_port: " . ($smtp_port ?: 587) . "\n";
                                    $configContent .= "  smtp_username: " . $smtp_user . "\n";
                                    $configContent .= "  smtp_password: " . $smtp_pass . "\n";
                                    $configContent .= "  smtp_encryption: " . $smtp_encryption . "\n";
                                    
                                    if (file_put_contents($configFile, $configContent)) {
                                        // Create .htaccess to protect config directory
                                        $htaccessFile = $configDir . '/.htaccess';
                                        file_put_contents($htaccessFile, "Deny from all\n");
                                        
                                        // Create index.php fallback
                                        $indexFile = $configDir . '/index.php';
                                        file_put_contents($indexFile, "<?php\nhttp_response_code(403);\nexit('Forbidden');\n");
                                        
                                        $success = true;
                                        $step = 'complete';
                                    } else {
                                        $error = 'Failed to write configuration file. Check directory permissions.';
                                    }
                                
                        } catch (PDOException $e) {
                            $error = 'Schema execution failed: ' . $e->getMessage();
                        }
                } // End else (schema file exists / complete installation)
            } catch (PDOException $e) {
                // Database doesn't exist and we couldn't create or access it
                $error = 'Cannot access database "' . htmlspecialchars($db_name) . '". ';
                $error .= 'Error: ' . htmlspecialchars($e->getMessage()) . '. ';
                if (!isset($createSuccess) || !$createSuccess) {
                    $error .= 'You may need to create this database first through your hosting control panel (cPanel, Plesk, etc.) and ensure your user has access to it.';
                }
            }
            
            // PDO connection will be closed automatically when $pdo goes out of scope
        }
    }
}

// Setup page variables
$pageTitle = 'Setup';
$bodyClass = '';

// Define inline CSS for setup page
ob_start();
?>
<style>
    body {
        background: linear-gradient(135deg, var(--brand-teal) 0%, var(--primary-dark) 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        font-family: 'Source Sans Pro', sans-serif;
    }
    
    .setup-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 600px;
        width: 100%;
        overflow: hidden;
    }
    
    .setup-header {
        background: var(--brand-teal);
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .setup-header h1 {
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .setup-header p {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 0;
    }
    
    .setup-body {
        padding: 40px;
    }
    
    .btn-primary {
        background: var(--brand-teal);
        border-color: var(--brand-teal);
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .success-icon {
        text-align: center;
        font-size: 64px;
        color: #28a745;
        margin-bottom: 20px;
    }
    
    .requirements {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 25px;
    }
    
    .requirements ul {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }
    
    .requirements li {
        padding: 5px 0 5px 24px;
        position: relative;
    }
    
    .requirements li:before {
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 0;
    }
    
    .requirements li.pass:before {
        content: "\f00c";
        color: #28a745;
    }
    
    .requirements li.warning:before {
        content: "\f06a";
        color: #ffc107;
    }
    
    .requirements li.fail:before {
        content: "\f00d";
        color: #dc3545;
    }
    
    .form-section-title {
        font-size: 16px;
        font-weight: 600;
        color: #495057;
        margin-top: 25px;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e9ecef;
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
    'pageTitle' => 'MOSAIC Setup',
    'customCss' => $customStyles
]);

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<div class="setup-container">
    <div class="setup-header">
        <h1>Welcome to MOSAIC!</h1>
        <p>Let's get your student learning outcomes system up and running</p>
    </div>
    
    <div class="setup-body">
        <?php if ($step === 'complete'): ?>
                <!-- Success Page -->
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="text-center">
                    <h2 class="mb-3">You're All Set!</h2>
                    <p class="text-muted mb-2">Your installation was successful.</p>
                    <p class="text-muted mb-4">The database is ready and you can start using your new system.</p>
                    <a href="<?php echo htmlspecialchars(BASE_URL); ?>administration/" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-arrow-right mr-2"></i>Get Started
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Preflight Checks -->
                <?php
                // Perform real preflight checks
                $php_version = PHP_VERSION;
                $php_ok = version_compare($php_version, '8.1.0', '>=');
                $pdo_ok = extension_loaded('pdo');
                $pdo_mysql_ok = extension_loaded('pdo_mysql');
                $pdo_sqlsrv_ok = extension_loaded('pdo_sqlsrv');
                $db_driver_ok = $pdo_mysql_ok || $pdo_sqlsrv_ok;
                $all_checks_pass = $php_ok && $pdo_ok && $db_driver_ok;
                ?>
                
                <div class="requirements">
                    <h5 class="mb-3"><i class="fas fa-clipboard-check mr-2"></i>System Requirements</h5>
                    <ul>
                        <li class="<?php echo $php_ok ? 'pass' : 'fail'; ?>">
                            PHP 8.1 or higher <span class="text-muted">(found <?php echo $php_version; ?>)</span>
                        </li>
                        <li class="<?php echo $pdo_ok ? 'pass' : 'fail'; ?>">
                            PDO extension enabled
                        </li>
                        <li class="<?php echo $pdo_mysql_ok ? 'pass' : 'warning'; ?>">
                            PDO MySQL driver (pdo_mysql) <?php echo $pdo_mysql_ok ? '<span class="text-success">Available</span>' : '<span class="text-muted">Not installed</span>'; ?>
                        </li>
                        <li class="<?php echo $pdo_sqlsrv_ok ? 'pass' : 'warning'; ?>">
                            PDO MS SQL Server driver (pdo_sqlsrv) <?php echo $pdo_sqlsrv_ok ? '<span class="text-success">Available</span>' : '<span class="text-muted">Not installed</span>'; ?>
                        </li>
                        <li class="<?php echo $db_driver_ok ? 'pass' : 'fail'; ?>">
                            At least one database driver required
                        </li>
                    </ul>
                </div>
                
                <?php if (!$all_checks_pass): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Requirements Not Met:</strong> Please fix the issues above before continuing.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'setup/'); ?>">
                    <!-- Site Configuration -->
                    <div class="form-group">
                        <label for="site_name"><i class="fas fa-graduation-cap mr-2"></i>What would you like to call your site?</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" 
                               value="<?php echo htmlspecialchars($_POST['site_name'] ?? 'MOSAIC'); ?>" 
                               placeholder="e.g., Springfield University Assessment" 
                               required>
                        <small class="form-text text-muted">This name will appear throughout your installation</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="base_url"><i class="fas fa-link mr-2"></i>Installation Path</label>
                        <input type="text" class="form-control" id="base_url" name="base_url" 
                               value="<?php echo htmlspecialchars($_POST['base_url'] ?? BASE_URL); ?>" 
                               required>
                        <small class="form-text text-muted">We've detected your installation path. Most users can leave this as-is.</small>
                    </div>
                    
                    <!-- Database Configuration -->
                    <h6 class="form-section-title"><i class="fas fa-database mr-2"></i>Database Connection</h6>
                    <p class="text-muted mb-3" style="font-size: 14px;">
                        We support MySQL (for development or smaller deployments) and MS SQL Server (for enterprise production). If you're not sure about these settings, contact your hosting provider or system administrator.
                        <br><strong>Shared Hosting Note:</strong> If using cPanel, Plesk, or similar, create your database first through their interface and use those credentials here.
                    </p>
                    
                    <div class="form-group">
                        <label for="db_driver">Database Type</label>
                        <select class="form-control" id="db_driver" name="db_driver" onchange="updatePortDefault()" required>
                            <option value="mysql" <?php echo ($_POST['db_driver'] ?? 'mysql') === 'mysql' ? 'selected' : ''; ?> <?php echo !$pdo_mysql_ok ? 'disabled' : ''; ?>>MySQL / MariaDB<?php echo !$pdo_mysql_ok ? ' (PDO driver missing: pdo_mysql)' : ''; ?></option>
                            <option value="mssql" <?php echo ($_POST['db_driver'] ?? '') === 'mssql' ? 'selected' : ''; ?> <?php echo !$pdo_sqlsrv_ok ? 'disabled' : ''; ?>>Microsoft SQL Server<?php echo !$pdo_sqlsrv_ok ? ' (PDO driver missing: pdo_sqlsrv)' : ''; ?></option>
                        </select>
                        <small class="form-text text-muted">Choose MySQL for easy entry and development, or MS SQL Server for production enterprise environments. <strong>This choice is permanent</strong> - the database type cannot be changed after installation.
                        <?php if (!$pdo_mysql_ok || !$pdo_sqlsrv_ok): ?>
                            <br><span class="text-danger"><i class="fas fa-exclamation-triangle"></i> 
                            <?php if (!$pdo_mysql_ok): ?>
                                pdo_mysql PDO driver must be installed on your server before you can use MySQL.
                            <?php endif; ?>
                            <?php if (!$pdo_mysql_ok && !$pdo_sqlsrv_ok): ?>
                                <br>
                            <?php endif; ?>
                            <?php if (!$pdo_sqlsrv_ok): ?>
                                pdo_sqlsrv PDO driver must be installed on your server before you can use MSSQL.
                            <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_host">Server Address</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" 
                               value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" 
                               required>
                        <small class="form-text text-muted">Usually "localhost" if MySQL is on the same server</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_port">Port</label>
                        <input type="number" class="form-control" id="db_port" name="db_port" 
                               value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" 
                               min="1" max="65535" required>
                        <small class="form-text text-muted">MySQL default: 3306, MSSQL default: 1433</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" 
                               value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'mosaic'); ?>" 
                               required>
                        <small class="form-text text-muted">On shared hosting, use the exact database name created in your control panel. On dedicated servers, we'll create it if needed.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_prefix">Table Prefix</label>
                        <input type="text" class="form-control" id="db_prefix" name="db_prefix" 
                               value="<?php echo htmlspecialchars($_POST['db_prefix'] ?? 'tbl_'); ?>" 
                               placeholder="e.g., tbl_ or mosaic_">
                        <small class="form-text text-muted">Prefix for all database tables. Include underscore if desired (e.g., tbl_). Leave blank for no prefix.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Username</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" 
                               value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" 
                               required>
                        <small class="form-text text-muted">Your MySQL username</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Password</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass" 
                               value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                        <small class="form-text text-muted">Leave blank if no password is set</small>
                    </div>
                    
                    <!-- Email Configuration (Optional) -->
                    <h6 class="form-section-title"><i class="fas fa-envelope mr-2"></i>Email Notifications <span class="badge badge-secondary" style="font-size: 11px;">Optional</span></h6>
                    <p class="text-muted mb-3" style="font-size: 14px;">Configure email settings to enable notifications for assessments, reminders, and reports. You can skip this and configure it later if needed.</p>
                    
                    <div class="form-group">
                        <label>Email Method</label>
                        <select class="form-control" id="mail_method" name="mail_method" onchange="toggleSmtpFields()">
                            <option value="disabled" <?php echo ($_POST['mail_method'] ?? 'disabled') === 'disabled' ? 'selected' : ''; ?>>Disabled (configure later)</option>
                            <option value="server" <?php echo ($_POST['mail_method'] ?? '') === 'server' ? 'selected' : ''; ?>>Server Mail (PHP mail function)</option>
                            <option value="smtp" <?php echo ($_POST['mail_method'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP (recommended)</option>
                        </select>
                        <small class="form-text text-muted">Server Mail uses your server's built-in mail. SMTP is more reliable for delivery.</small>
                    </div>
                    
                    <div id="smtp-fields" style="display: <?php echo ($_POST['mail_method'] ?? 'disabled') === 'smtp' ? 'block' : 'none'; ?>;">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Server</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? ''); ?>" 
                                   placeholder="e.g., smtp.gmail.com">
                            <small class="form-text text-muted">Your email server address</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="smtp_port">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="<?php echo htmlspecialchars($_POST['smtp_port'] ?? '587'); ?>" 
                                           min="1" max="65535">
                                    <small class="form-text text-muted">Usually 587 (TLS) or 465 (SSL)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="smtp_encryption">Encryption</label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo ($_POST['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($_POST['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo ($_POST['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                    <small class="form-text text-muted">Recommended: TLS</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_user">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_user" name="smtp_user" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? ''); ?>" 
                                   placeholder="Your email or username">
                            <small class="form-text text-muted">Usually your full email address</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_pass">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_pass'] ?? ''); ?>" 
                                   placeholder="Your email password or app password">
                            <small class="form-text text-muted">For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a></small>
                        </div>
                    </div>
                    
                    <div id="email-common-fields" style="display: <?php echo ($_POST['mail_method'] ?? 'disabled') !== 'disabled' ? 'block' : 'none'; ?>;">
                        <div class="form-group">
                            <label for="smtp_from_email">From Email Address</label>
                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_from_email'] ?? ''); ?>" 
                                   placeholder="noreply@yourdomain.edu">
                            <small class="form-text text-muted">Email address that notifications will be sent from</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_from_name">From Name</label>
                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" 
                                   value="<?php echo htmlspecialchars($_POST['smtp_from_name'] ?? $_POST['site_name'] ?? 'MOSAIC'); ?>" 
                                   placeholder="Your site name">
                            <small class="form-text text-muted">Display name for outgoing emails</small>
                        </div>
                    </div>
                    
                    <script>
                    function toggleSmtpFields() {
                        var method = document.getElementById('mail_method').value;
                        var smtpFields = document.getElementById('smtp-fields');
                        var commonFields = document.getElementById('email-common-fields');
                        
                        if (method === 'smtp') {
                            smtpFields.style.display = 'block';
                            commonFields.style.display = 'block';
                        } else if (method === 'server') {
                            smtpFields.style.display = 'none';
                            commonFields.style.display = 'block';
                        } else {
                            smtpFields.style.display = 'none';
                            commonFields.style.display = 'none';
                        }
                    }
                    
                    function updatePortDefault() {
                        var driver = document.getElementById('db_driver').value;
                        var portField = document.getElementById('db_port');
                        
                        // Only update if port is still at default value
                        if (portField.value === '3306' || portField.value === '1433') {
                            portField.value = (driver === 'mssql') ? '1433' : '3306';
                        }
                    }
                    </script>
                    
                    <button type="submit" name="setup_submit" class="btn btn-primary btn-lg btn-block" <?php echo !$all_checks_pass ? 'disabled' : ''; ?>>
                        <i class="fas fa-rocket mr-2"></i>Install Now
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php $theme->showFooter($context); ?>
