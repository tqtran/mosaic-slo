<?php
/**
 * Mosaic-SLO - Student Management
 * AdminLTE-based student enrollment management
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

// Read CSV data
$csvFile = __DIR__ . '/sample.csv';
$csvData = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $csvData[] = array_combine($headers, $row);
    }
    fclose($handle);
}

// Get unique student enrollments
$enrollments = [];
$seenEnrollments = [];
foreach ($csvData as $row) {
    $key = $row['Student ID'] . '|' . $row['CRN'];
    if (!isset($seenEnrollments[$key])) {
        $enrollments[] = [
            'student_id' => $row['Student ID'],
            'name' => $row['FirstName'] . ' ' . $row['LastName'],
            'first_name' => $row['FirstName'],
            'last_name' => $row['LastName'],
            'crn' => $row['CRN'],
            'term' => $row['Term'],
            'course' => $row['Course'],
            'discipline' => $row['Discipline'],
            'academic_year' => $row['AcademicYear']
        ];
        $seenEnrollments[$key] = true;
    }
}

// Get unique terms
$terms = array_unique(array_column($enrollments, 'term'));
sort($terms);

// Get selected term with validation
$selectedTerm = isset($_GET['term']) ? $_GET['term'] : $terms[0];
if (!in_array($selectedTerm, $terms, true)) {
    $selectedTerm = $terms[0];
}

// Filter enrollments by selected term
$filteredEnrollments = array_filter($enrollments, function($enrollment) use ($selectedTerm) {
    return $enrollment['term'] === $selectedTerm;
});

// Sort by student ID, then by CRN
usort($filteredEnrollments, function($a, $b) {
    $idCompare = strcmp($a['student_id'], $b['student_id']);
    if ($idCompare !== 0) {
        return $idCompare;
    }
    return strcmp($a['crn'], $b['crn']);
});

// Calculate statistics
$totalEnrollments = count($filteredEnrollments);
$uniqueStudents = count(array_unique(array_column($filteredEnrollments, 'student_id')));
$uniqueCourses = count(array_unique(array_column($filteredEnrollments, 'crn')));
$disciplines = array_unique(array_column($filteredEnrollments, 'discipline'));
$disciplineCount = count($disciplines);

$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successMessage = 'Student data saved successfully! In production, this would update the database.';
}

$pageTitle = 'Student Management - Mosaic-SLO';
$currentPage = 'admin_users';
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
                    <h1 class="m-0">Student Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Student Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Term Filter -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> Select Term</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#uploadModal">
                            <i class="fas fa-file-upload"></i> Bulk Import
                        </button>
                        <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#addStudentModal">
                            <i class="fas fa-user-plus"></i> Add Student
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <label for="term" class="mr-2">Term:</label>
                        <select name="term" id="term" class="form-control mr-2" onchange="this.form.submit()">
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= htmlspecialchars($term) ?>" <?= $term === $selectedTerm ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($term) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-primary">Load Term</button></noscript>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($totalEnrollments) ?></h3>
                            <p>Total Enrollments</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($uniqueStudents) ?></h3>
                            <p>Unique Students</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($uniqueCourses) ?></h3>
                            <p>Active Courses</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= number_format($disciplineCount) ?></h3>
                            <p>Disciplines</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollments Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table"></i> Student Enrollments</h3>
                </div>
                <div class="card-body">
                    <table id="enrollmentsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>CRN</th>
                                <th>Course</th>
                                <th>Discipline</th>
                                <th>Academic Year</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredEnrollments as $enrollment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($enrollment['student_id']) ?></td>
                                    <td><?= htmlspecialchars($enrollment['name']) ?></td>
                                    <td><?= htmlspecialchars($enrollment['crn']) ?></td>
                                    <td><?= htmlspecialchars($enrollment['course']) ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($enrollment['discipline']) ?></span></td>
                                    <td><?= htmlspecialchars($enrollment['academic_year']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" title="Remove">
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
            <div class="modal-header bg-success">
                <h5 class="modal-title text-white" id="uploadModalLabel">
                    <i class="fas fa-file-upload"></i> Bulk Student Import
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="form-group">
                        <label for="studentUpload">Upload CSV File</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="studentUpload" name="student_upload" accept=".csv">
                            <label class="custom-file-label" for="studentUpload">Choose file</label>
                        </div>
                        <small class="form-text text-muted">
                            CSV format: Student ID, First Name, Last Name, CRN, Course
                        </small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This will import student enrollment data from a CSV file. Existing records will be updated.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" role="dialog" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="addStudentModalLabel">
                    <i class="fas fa-user-plus"></i> Add Student Enrollment
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="studentId">Student ID</label>
                                <input type="text" class="form-control" id="studentId" name="student_id" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="crn">CRN</label>
                                <input type="text" class="form-control" id="crn" name="crn" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="last_name" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#enrollmentsTable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "pageLength": 25,
        "order": [[0, "asc"], [2, "asc"]],
        "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#enrollmentsTable_wrapper .col-md-6:eq(0)');

    // Update file input label
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
