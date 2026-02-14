<?php
/**
 * MOSAIC Demo - SLO Administration
 * AdminLTE-based SLO management interface
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

// Hardcoded Institutional Outcomes
$institutionalOutcomes = [
    1 => ['id' => 1, 'code' => 'IO1', 'description' => 'Critical Thinking and Problem Solving'],
    2 => ['id' => 2, 'code' => 'IO2', 'description' => 'Communication and Collaboration'],
    3 => ['id' => 3, 'code' => 'IO3', 'description' => 'Information and Digital Literacy'],
    4 => ['id' => 4, 'code' => 'IO4', 'description' => 'Personal and Professional Development']
];

// Hardcoded Program Outcomes
$programOutcomes = [
    1 => ['id' => 1, 'code' => 'PO1', 'description' => 'Apply mathematical concepts to solve real-world problems', 'institutional_outcome_id' => 1, 'program' => 'Mathematics'],
    2 => ['id' => 2, 'code' => 'PO2', 'description' => 'Demonstrate effective written and oral communication', 'institutional_outcome_id' => 2, 'program' => 'Mathematics'],
    3 => ['id' => 3, 'code' => 'PO3', 'description' => 'Analyze and interpret biological systems', 'institutional_outcome_id' => 1, 'program' => 'Biology'],
    4 => ['id' => 4, 'code' => 'PO4', 'description' => 'Conduct scientific research following ethical guidelines', 'institutional_outcome_id' => 4, 'program' => 'Biology'],
    5 => ['id' => 5, 'code' => 'PO5', 'description' => 'Apply programming principles to create software solutions', 'institutional_outcome_id' => 3, 'program' => 'Computer Science'],
    6 => ['id' => 6, 'code' => 'PO6', 'description' => 'Collaborate effectively in team-based projects', 'institutional_outcome_id' => 2, 'program' => 'Computer Science'],
    7 => ['id' => 7, 'code' => 'PO7', 'description' => 'Apply chemical principles to laboratory experiments', 'institutional_outcome_id' => 1, 'program' => 'Chemistry']
];

// Parse CSV to get courses and SLOs
$csvFile = __DIR__ . '/sample.csv';
$courses = [];
$slos = [];

if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        $crn = $row[2];
        $courseName = $row[3];
        $discipline = $row[4];
        $cslo = $row[5];
        
        if (!isset($courses[$crn])) {
            $courses[$crn] = [
                'crn' => $crn,
                'course' => $courseName,
                'discipline' => $discipline,
                'term' => $row[1],
                'year' => $row[0]
            ];
        }
        
        if (!isset($slos[$crn])) {
            $slos[$crn] = [];
        }
        
        if (!in_array($cslo, $slos[$crn])) {
            $slos[$crn][] = $cslo;
        }
    }
    fclose($handle);
}

// Select first course by default
$selectedCRN = $_GET['crn'] ?? array_key_first($courses);
if (!isset($courses[$selectedCRN])) {
    $selectedCRN = array_key_first($courses);
}

$selectedCourse = $courses[$selectedCRN];
$courseSLOs = $slos[$selectedCRN] ?? [];

// Generate demo SLO data with alignments
$sloData = [];
foreach ($courseSLOs as $index => $cslo) {
    $poId = ($index % count($programOutcomes)) + 1;
    $po = $programOutcomes[$poId];
    $io = $institutionalOutcomes[$po['institutional_outcome_id']];
    
    $sloData[] = [
        'slo' => $cslo,
        'description' => 'Students will demonstrate proficiency in ' . strtolower($cslo),
        'assessment_method' => ['Exam', 'Quiz', 'Project', 'Lab'][rand(0, 3)],
        'program_outcome' => $po['code'] . ': ' . $po['description'],
        'institutional_outcome' => $io['code'] . ': ' . $io['description'],
        'students_assessed' => rand(20, 50)
    ];
}

$pageTitle = 'SLO Management - MOSAIC Demo';
$currentPage = 'admin_slo';
include 'includes/header.php';
?>

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="dashboard.php" class="nav-link">Home</a>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <span class="nav-link">
                <i class="fas fa-flask text-warning"></i> <strong>Demo Mode</strong>
            </span>
        </li>
    </ul>
</nav>

<?php include 'includes/sidebar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">SLO Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">SLO Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Course Selection -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-book"></i> Select Course</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#uploadModal">
                            <i class="fas fa-upload"></i> Bulk Upload
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <label for="crn" class="mr-2">Course:</label>
                        <select name="crn" id="crn" class="form-control mr-2" style="min-width: 400px;" onchange="this.form.submit()">
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= htmlspecialchars($course['crn']) ?>" <?= $course['crn'] === $selectedCRN ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course']) ?> - <?= htmlspecialchars($course['discipline']) ?> (<?= htmlspecialchars($course['term']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-primary">Load Course</button></noscript>
                    </form>
                </div>
            </div>

            <!-- Course Info -->
            <div class="row">
                <div class="col-md-3">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-book-open"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Course Code</span>
                            <span class="info-box-number"><?= htmlspecialchars($selectedCourse['course']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-primary">
                        <span class="info-box-icon"><i class="fas fa-graduation-cap"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total SLOs</span>
                            <span class="info-box-number"><?= count($courseSLOs) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Discipline</span>
                            <span class="info-box-number" style="font-size: 1.2rem;"><?= htmlspecialchars($selectedCourse['discipline']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Term</span>
                            <span class="info-box-number" style="font-size: 1.2rem;"><?= htmlspecialchars($selectedCourse['term']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outcomes Hierarchy -->
            <div class="card card-success card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sitemap"></i> Outcomes Hierarchy</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5 class="text-primary"><i class="fas fa-university"></i> Institutional Outcomes</h5>
                            <ul class="list-group">
                                <?php foreach ($institutionalOutcomes as $io): ?>
                                    <li class="list-group-item">
                                        <strong><?= htmlspecialchars($io['code']) ?></strong>: <?= htmlspecialchars($io['description']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-success"><i class="fas fa-certificate"></i> Program Outcomes</h5>
                            <ul class="list-group">
                                <?php foreach ($programOutcomes as $po): ?>
                                    <li class="list-group-item">
                                        <strong><?= htmlspecialchars($po['code']) ?></strong>: <?= htmlspecialchars($po['description']) ?>
                                        <br><small class="text-muted">→ <?= htmlspecialchars($institutionalOutcomes[$po['institutional_outcome_id']]['code']) ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-info"><i class="fas fa-tasks"></i> Course SLOs</h5>
                            <ul class="list-group">
                                <?php foreach ($courseSLOs as $cslo): ?>
                                    <li class="list-group-item">
                                        <?= htmlspecialchars($cslo) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SLO Details Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> SLO Details & Alignment</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add SLO
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="sloTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SLO</th>
                                <th>Description</th>
                                <th>Assessment Method</th>
                                <th>Program Outcome</th>
                                <th>Institutional Outcome</th>
                                <th>Students Assessed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sloData as $slo): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($slo['slo']) ?></strong></td>
                                    <td><?= htmlspecialchars($slo['description']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($slo['assessment_method']) ?></span></td>
                                    <td><small><?= htmlspecialchars($slo['program_outcome']) ?></small></td>
                                    <td><small><?= htmlspecialchars($slo['institutional_outcome']) ?></small></td>
                                    <td><?= $slo['students_assessed'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Bulk SLO Upload</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="form-group">
                        <label for="sloFile">Upload CSV File</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="sloFile" name="slo_file" accept=".csv">
                            <label class="custom-file-label" for="sloFile">Choose file</label>
                        </div>
                        <small class="form-text text-muted">
                            CSV format: SLO, Description, Assessment Method, Program Outcome ID
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#sloTable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "buttons": ["copy", "csv", "excel", "pdf", "print"]
    }).buttons().container().appendTo('#sloTable_wrapper .col-md-6:eq(0)');

    // Update file input label
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
