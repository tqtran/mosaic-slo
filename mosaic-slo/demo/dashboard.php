<?php
/**
 * MOSAIC Demo - Dashboard
 * AdminLTE-based analytics dashboard
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

// Parse CSV and get all data
$csvFile = __DIR__ . '/sample.csv';
$allData = [];
$disciplines = [];
$terms = [];
$academicYears = [];
$courses = [];

if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        $crn = $row[2];
        $courseName = $row[3];
        $discipline = $row[4];
        $term = $row[1];
        $year = $row[0];
        
        if (!isset($courses[$crn])) {
            $courses[$crn] = [
                'crn' => $crn,
                'course' => $courseName,
                'discipline' => $discipline,
                'term' => $term,
                'year' => $year
            ];
        }
        
        $allData[] = [
            'year' => $year,
            'term' => $term,
            'crn' => $crn,
            'course' => $courseName,
            'discipline' => $discipline,
            'cslo' => $row[5],
            'assessmentType' => $row[6],
            'studentID' => $row[7],
            'outcome' => $row[8]
        ];
        
        if (!in_array($discipline, $disciplines)) $disciplines[] = $discipline;
        if (!in_array($term, $terms)) $terms[] = $term;
        if (!in_array($year, $academicYears)) $academicYears[] = $year;
    }
    fclose($handle);
}

// Handle filters with validation
$selectedYear = $_GET['year'] ?? $academicYears[0];
$selectedTerm = $_GET['term'] ?? $terms[0];
$selectedDiscipline = $_GET['discipline'] ?? $disciplines[0];

if (!in_array($selectedYear, $academicYears, true)) {
    $selectedYear = $academicYears[0];
}
if (!in_array($selectedTerm, $terms, true)) {
    $selectedTerm = $terms[0];
}
if (!in_array($selectedDiscipline, $disciplines, true)) {
    $selectedDiscipline = $disciplines[0];
}

// Filter data
$filteredData = array_filter($allData, function($row) use ($selectedYear, $selectedTerm, $selectedDiscipline) {
    return $row['year'] == $selectedYear && 
           $row['term'] == $selectedTerm && 
           $row['discipline'] == $selectedDiscipline;
});

// Calculate metrics
$totalAssessments = count($filteredData);
$assessmentTypes = [];
$outcomes = ['Met' => 0, 'Partially Met' => 0, 'Not Met' => 0, 'Not Assessed' => 0];
$courseBreakdown = [];

foreach ($filteredData as $row) {
    $type = $row['assessmentType'];
    if (!isset($assessmentTypes[$type])) {
        $assessmentTypes[$type] = 0;
    }
    $assessmentTypes[$type]++;
    
    $outcome = $row['outcome'];
    if (isset($outcomes[$outcome])) {
        $outcomes[$outcome]++;
    }
    
    $course = $row['course'];
    if (!isset($courseBreakdown[$course])) {
        $courseBreakdown[$course] = 0;
    }
    $courseBreakdown[$course]++;
}

$pageTitle = 'Dashboard - MOSAIC Demo';
$currentPage = 'dashboard';
include 'includes/header.php';
?>

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="dashboard.php" class="nav-link">Home</a>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <span class="nav-link">
                <i class="fas fa-flask text-warning"></i> <strong>Demo Mode</strong>
            </span>
        </li>
    </ul>
</nav>
<!-- /.navbar -->

<?php include 'includes/sidebar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Analytics Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Filter Card -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <div class="form-group mr-3">
                            <label for="year" class="mr-2">Academic Year:</label>
                            <select name="year" id="year" class="form-control">
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= htmlspecialchars($year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group mr-3">
                            <label for="term" class="mr-2">Term:</label>
                            <select name="term" id="term" class="form-control">
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= htmlspecialchars($term) ?>" <?= $term === $selectedTerm ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($term) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group mr-3">
                            <label for="discipline" class="mr-2">Discipline:</label>
                            <select name="discipline" id="discipline" class="form-control">
                                <?php foreach ($disciplines as $disc): ?>
                                    <option value="<?= htmlspecialchars($disc) ?>" <?= $disc === $selectedDiscipline ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($disc) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info boxes -->
            <div class="row">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-clipboard-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Assessments</span>
                            <span class="info-box-number"><?= number_format($totalAssessments) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Met</span>
                            <span class="info-box-number"><?= number_format($outcomes['Met']) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Partially Met</span>
                            <span class="info-box-number"><?= number_format($outcomes['Partially Met']) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Not Met</span>
                            <span class="info-box-number"><?= number_format($outcomes['Not Met']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header border-0">
                            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Assessment Types</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="assessmentTypeChart" style="height: 250px;"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header border-0">
                            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Outcomes Distribution</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="outcomesChart" style="height: 250px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Breakdown Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table"></i> Course Breakdown</h3>
                </div>
                <div class="card-body">
                    <table id="courseTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Assessments</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseBreakdown as $course => $count): ?>
                                <tr>
                                    <td><?= htmlspecialchars($course) ?></td>
                                    <td><?= number_format($count) ?></td>
                                    <td>
                                        <div class="progress progress-xs">
                                            <div class="progress-bar bg-primary" style="width: <?= ($count / $totalAssessments * 100) ?>%"></div>
                                        </div>
                                        <?= number_format(($count / $totalAssessments * 100), 1) ?>%
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#courseTable').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "pageLength": 10
    });

    // Assessment Type Chart
    const assessmentTypeCtx = document.getElementById('assessmentTypeChart').getContext('2d');
    new Chart(assessmentTypeCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($assessmentTypes)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($assessmentTypes)) ?>,
                backgroundColor: [
                    '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Outcomes Chart
    const outcomesCtx = document.getElementById('outcomesChart').getContext('2d');
    new Chart(outcomesCtx, {
        type: 'bar',
        data: {
            labels: ['Met', 'Partially Met', 'Not Met', 'Not Assessed'],
            datasets: [{
                label: 'Count',
                data: [
                    <?= $outcomes['Met'] ?>,
                    <?= $outcomes['Partially Met'] ?>,
                    <?= $outcomes['Not Met'] ?>,
                    <?= $outcomes['Not Assessed'] ?>
                ],
                backgroundColor: [
                    '#28a745', '#ffc107', '#dc3545', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
