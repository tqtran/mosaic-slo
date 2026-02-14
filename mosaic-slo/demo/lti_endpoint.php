<?php
/**
 * Mosaic-SLO - LTI Endpoint Demo
 * Instructor-facing assessment interface (no sidebar menu)
 */

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
    
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

// Parse CSV to get sample data
$csvFile = __DIR__ . '/sample.csv';
$courses = [];

if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle);
    
    $colIndices = [
        'year' => array_search('AcademicYear', $headers),
        'term' => array_search('Term', $headers),
        'crn' => array_search('CRN', $headers),
        'course' => array_search('Course', $headers),
        'discipline' => array_search('Discipline', $headers),
        'cslo' => array_search('CSLO ', $headers),
        'assessment' => array_search('CrossEnrolled', $headers),
        'studentId' => array_search('Student ID', $headers),
        'outcome' => array_search('ProgramCampCode', $headers),
        'firstName' => array_search('FirstName', $headers),
        'lastName' => array_search('LastName', $headers)
    ];
    
    while (($row = fgetcsv($handle)) !== false) {
        $crn = $row[$colIndices['crn']];
        $cslo = $row[$colIndices['cslo']];
        $studentId = $row[$colIndices['studentId']];
        
        if (!isset($courses[$crn])) {
            $courses[$crn] = [
                'crn' => $crn,
                'course' => $row[$colIndices['course']],
                'discipline' => $row[$colIndices['discipline']],
                'term' => $row[$colIndices['term']],
                'year' => $row[$colIndices['year']],
                'cslos' => [],
                'students' => []
            ];
        }
        
        if (!isset($courses[$crn]['cslos'][$cslo])) {
            $courses[$crn]['cslos'][$cslo] = ['name' => $cslo, 'students' => []];
        }
        
        if (!isset($courses[$crn]['cslos'][$cslo]['students'][$studentId])) {
            $courses[$crn]['cslos'][$cslo]['students'][$studentId] = [
                'id' => $studentId,
                'firstName' => $row[$colIndices['firstName']],
                'lastName' => $row[$colIndices['lastName']],
                'outcome' => $row[$colIndices['outcome']]
            ];
        }
        
        if (!isset($courses[$crn]['students'][$studentId])) {
            $courses[$crn]['students'][$studentId] = [
                'id' => $studentId,
                'firstName' => $row[$colIndices['firstName']],
                'lastName' => $row[$colIndices['lastName']]
            ];
        }
    }
    fclose($handle);
}

// Select first course and SLO
$selectedCRN = $_GET['crn'] ?? array_key_first($courses);
$course = $courses[$selectedCRN] ?? reset($courses);
$cslos = array_keys($course['cslos']);
$selectedSLO = $_GET['slo'] ?? $cslos[0];
$students = $course['cslos'][$selectedSLO]['students'] ?? [];

// Assessment types
$assessmentTypes = ['Exam', 'Quiz', 'Project', 'Lab', 'Assignment', 'Presentation', 'Discussion'];
$selectedAssessmentType = $_GET['assessment_type'] ?? 'Exam';

// Handle form submission
$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessments'])) {
    $successMessage = 'Assessment data saved successfully! In production, this would update the database via LTI.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SLO Assessment Entry - Mosaic-SLO</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
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
        .demo-badge {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            background: #ffc107;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            font-weight: bold;
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
    </style>
</head>
<body>
    <div class="demo-badge">
        <i class="fas fa-flask"></i> DEMO MODE
    </div>

    <div class="lti-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-clipboard-check"></i> SLO Assessment Entry
                    </h1>
                    <p class="mb-0 mt-2">
                        <strong><?= htmlspecialchars($course['course']) ?></strong> - 
                        <?= htmlspecialchars($course['discipline']) ?> | 
                        <?= htmlspecialchars($course['term']) ?> <?= htmlspecialchars($course['year']) ?>
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="text-white">
                        <i class="fas fa-user-circle fa-2x"></i>
                        <div class="mt-2">
                            <strong>Prof. Demo Instructor</strong><br>
                            <small>demo@institution.edu</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Course & SLO Selection -->
        <div class="card card-primary card-outline mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-graduation-cap"></i> Select Course and SLO</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="selectionForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="crn">Course</label>
                                <select name="crn" id="crn" class="form-control" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= htmlspecialchars($c['crn']) ?>" <?= $c['crn'] === $selectedCRN ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['course']) ?> - <?= htmlspecialchars($c['term']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="slo">Student Learning Outcome</label>
                                <select name="slo" id="slo" class="form-control" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($cslos as $cslo): ?>
                                        <option value="<?= htmlspecialchars($cslo) ?>" <?= $cslo === $selectedSLO ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cslo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="assessment_type">Assessment Type</label>
                                <select name="assessment_type" id="assessment_type" class="form-control" onchange="document.getElementById('selectionForm').submit()">
                                    <?php foreach ($assessmentTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>" <?= $type === $selectedAssessmentType ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assessment Entry Form -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="crn" value="<?= htmlspecialchars($selectedCRN) ?>">
            <input type="hidden" name="slo" value="<?= htmlspecialchars($selectedSLO) ?>">
            <input type="hidden" name="assessment_type" value="<?= htmlspecialchars($selectedAssessmentType) ?>">

            <div class="card">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">
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
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px">#</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th style="width: 200px">Outcome</th>
                                <th style="width: 150px">Score (Optional)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $index = 1; foreach ($students as $student): ?>
                                <tr class="student-row">
                                    <td><?= $index++ ?></td>
                                    <td><code><?= htmlspecialchars($student['id']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($student['firstName']) ?> <?= htmlspecialchars($student['lastName']) ?></strong>
                                    </td>
                                    <td>
                                        <select name="outcome[<?= htmlspecialchars($student['id']) ?>]" class="form-control form-control-sm outcome-select">
                                            <option value="Met" <?= $student['outcome'] === 'Met' ? 'selected' : '' ?>>Met</option>
                                            <option value="Partially Met" <?= $student['outcome'] === 'Partially Met' ? 'selected' : '' ?>>Partially Met</option>
                                            <option value="Not Met" <?= $student['outcome'] === 'Not Met' ? 'selected' : '' ?>>Not Met</option>
                                            <option value="Not Assessed" <?= $student['outcome'] === 'Not Assessed' ? 'selected' : '' ?>>Not Assessed</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="score[<?= htmlspecialchars($student['id']) ?>]" 
                                               class="form-control form-control-sm" 
                                               min="0" max="100" step="0.01" placeholder="0-100">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" name="submit_assessments" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Assessment Data
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='dashboard.php'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                        <div class="col-md-6 text-right">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Changes are saved back to your LMS gradebook
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Instructions Card -->
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-question-circle"></i> Instructions</h3>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li><strong>Select Course & SLO:</strong> Choose the course section and specific learning outcome you're assessing.</li>
                    <li><strong>Choose Assessment Type:</strong> Indicate what type of assessment this data represents (Exam, Quiz, Project, etc.).</li>
                    <li><strong>Enter Outcomes:</strong> For each student, select whether they Met, Partially Met, or did Not Meet the learning outcome.</li>
                    <li><strong>Optional Scores:</strong> You may enter numeric scores (0-100) if applicable to your assessment.</li>
                    <li><strong>Quick Actions:</strong> Use the buttons above the table to quickly set all students to the same outcome level.</li>
                    <li><strong>Save:</strong> Click "Save Assessment Data" when complete. Data is securely transmitted back to the SLO Cloud system.</li>
                </ol>
            </div>
        </div>

        <div class="text-center mb-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

    <script>
        function setAllOutcomes(outcome) {
            $('.outcome-select').val(outcome);
        }
    </script>
</body>
</html>
