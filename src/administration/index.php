<?php
declare(strict_types=1);

/**
 * MOSAIC Administration Dashboard
 * 
 * Main landing page for administrative interface.
 * Shows key metrics and system status.
 * 
 * @package Mosaic\Administration
 */

// Initialize common variables and database
require_once __DIR__ . '/../system/includes/init.php';

// TODO: Check authentication and authorization
// For now, allow access (will implement auth later)

// Get selected term
$selectedTermFk = getSelectedTermFk();
$selectedTermName = 'No Term Selected';
$selectedTermCode = '';

if ($selectedTermFk) {
    $termResult = $db->query(
        "SELECT term_code, term_name FROM {$dbPrefix}terms WHERE terms_pk = ?",
        [$selectedTermFk],
        'i'
    );
    $termRow = $termResult->fetch();
    if ($termRow) {
        $selectedTermName = htmlspecialchars($termRow['term_name']);
        $selectedTermCode = htmlspecialchars($termRow['term_code']);
    }
}

// Fetch term-specific statistics
$stats = [
    'programs' => 0,
    'courses' => 0,
    'students' => 0,
    'enrollments' => 0,
    'institutional_outcomes' => 0,
    'program_outcomes' => 0,
    'student_learning_outcomes' => 0,
    'assessments' => 0
];

try {
    if ($selectedTermFk) {
        // Count programs for selected term
        $result = $db->query(
            "SELECT COUNT(*) as count FROM {$dbPrefix}programs WHERE term_fk = ? AND is_active = 1",
            [$selectedTermFk],
            'i'
        );
        $row = $result->fetch();
        $stats['programs'] = $row['count'] ?? 0;
        
        // Count courses for selected term
        $result = $db->query(
            "SELECT COUNT(*) as count FROM {$dbPrefix}courses WHERE term_fk = ? AND is_active = 1",
            [$selectedTermFk],
            'i'
        );
        $row = $result->fetch();
        $stats['courses'] = $row['count'] ?? 0;
        
        // Count students (all active students, not term-specific)
        $result = $db->query(
            "SELECT COUNT(*) as count FROM {$dbPrefix}students WHERE is_active = 1"
        );
        $row = $result->fetch();
        $stats['students'] = $row['count'] ?? 0;
        
        // Count enrollments for selected term
        // Get banner_term for the selected term (enrollment.term_code actually stores banner_term)
        $bannerTermResult = $db->query(
            "SELECT banner_term FROM {$dbPrefix}terms WHERE terms_pk = ?",
            [$selectedTermFk],
            'i'
        );
        $bannerTermRow = $bannerTermResult->fetch();
        if ($bannerTermRow) {
            $result = $db->query(
                "SELECT COUNT(DISTINCT e.enrollment_pk) as count 
                 FROM {$dbPrefix}enrollment e
                 WHERE e.term_code = ?",
                [$bannerTermRow['banner_term']],
                's'
            );
            $row = $result->fetch();
            $stats['enrollments'] = $row['count'] ?? 0;
        }
        
        // Count institutional outcomes for selected term
        $result = $db->query(
            "SELECT COUNT(*) as count FROM {$dbPrefix}institutional_outcomes WHERE term_fk = ? AND is_active = 1",
            [$selectedTermFk],
            'i'
        );
        $row = $result->fetch();
        $stats['institutional_outcomes'] = $row['count'] ?? 0;
        
        // Count program outcomes for selected term
        $result = $db->query(
            "SELECT COUNT(DISTINCT po.program_outcomes_pk) as count 
             FROM {$dbPrefix}program_outcomes po
             JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
             WHERE p.term_fk = ? AND po.is_active = 1",
            [$selectedTermFk],
            'i'
        );
        $row = $result->fetch();
        $stats['program_outcomes'] = $row['count'] ?? 0;
        
        // Count student learning outcomes (CSLOs) for selected term
        $result = $db->query(
            "SELECT COUNT(DISTINCT slo.student_learning_outcomes_pk) as count 
             FROM {$dbPrefix}student_learning_outcomes slo
             JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
             WHERE c.term_fk = ? AND slo.is_active = 1",
            [$selectedTermFk],
            'i'
        );
        $row = $result->fetch();
        $stats['student_learning_outcomes'] = $row['count'] ?? 0;
        
        // Count assessments for selected term
        if ($bannerTermRow) {
            $result = $db->query(
                "SELECT COUNT(DISTINCT a.assessments_pk) as count 
                 FROM {$dbPrefix}assessments a
                 JOIN {$dbPrefix}enrollment e ON a.enrollment_fk = e.enrollment_pk
                 WHERE e.term_code = ?",
                [$bannerTermRow['banner_term']],
                's'
            );
            $row = $result->fetch();
            $stats['assessments'] = $row['count'] ?? 0;
        }
    }
} catch (Exception $e) {
    // Tables might not exist yet - that's okay for new installations
    error_log('Dashboard statistics error: ' . $e->getMessage());
}

// Load theme system (auto-loads ThemeContext and Theme)
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Administration Dashboard',
    'currentPage' => 'admin_dashboard',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Dashboard']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<!-- Term Statistics -->
<section aria-labelledby="term-stats-heading">
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h2 id="term-stats-heading" class="card-title">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i> Term Statistics
                        <?php if ($selectedTermFk): ?>
                            - <?= $selectedTermName ?> (<?= $selectedTermCode ?>)
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (!$selectedTermFk): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                            No term selected. Please select a term from the dropdown in the header to view statistics.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-info" role="region" aria-label="Programs statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['programs']) ?></h3>
                                        <p>Programs</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/programs.php" class="small-box-footer" aria-label="View programs page">
                                        View Programs <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-warning" role="region" aria-label="Students statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['students']) ?></h3>
                                        <p>Students</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/students.php" class="small-box-footer" aria-label="View students page">
                                        View Students <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-success" role="region" aria-label="Courses statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['courses']) ?></h3>
                                        <p>Courses</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/courses.php" class="small-box-footer" aria-label="View courses page">
                                        View Courses <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-danger" role="region" aria-label="Enrollments statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['enrollments']) ?></h3>
                                        <p>Enrollments</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/enrollment.php" class="small-box-footer" aria-label="View enrollments page">
                                        View Enrollments <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-primary" role="region" aria-label="Institutional outcomes statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['institutional_outcomes']) ?></h3>
                                        <p>Institutional Outcomes</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-flag"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="small-box-footer" aria-label="View institutional outcomes page">
                                        View ISLOs <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-secondary" role="region" aria-label="Program outcomes statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['program_outcomes']) ?></h3>
                                        <p>Program Outcomes</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-bullseye"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/program_outcomes.php" class="small-box-footer" aria-label="View program outcomes page">
                                        View PSLOs <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-teal" role="region" aria-label="Student learning outcomes statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['student_learning_outcomes']) ?></h3>
                                        <p>Student Learning Outcomes</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/student_learning_outcomes.php" class="small-box-footer" aria-label="View student learning outcomes page">
                                        View CSLOs <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-indigo" role="region" aria-label="Assessments statistic">
                                    <div class="inner">
                                        <h3><?= number_format($stats['assessments']) ?></h3>
                                        <p>Assessments</p>
                                    </div>
                                    <div class="icon" aria-hidden="true">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <a href="<?= BASE_URL ?>administration/assessments.php" class="small-box-footer" aria-label="View assessments page">
                                        View Assessments <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- System Status -->
<section aria-labelledby="system-status-heading">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2 id="system-status-heading" class="card-title">
                        <i class="fas fa-server" aria-hidden="true"></i> System Status
                    </h2>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Application Version:</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($appVersion) ?></dd>
                        
                        <dt class="col-sm-4">PHP Version:</dt>
                        <dd class="col-sm-8"><?= PHP_VERSION ?></dd>
                        
                        <dt class="col-sm-4">Database:</dt>
                        <dd class="col-sm-8">
                            <?php
                            try {
                                $dbVersion = 'Unknown';
                                $driver = $db->getDriver();
                                
                                if ($driver === 'mssql' || $driver === 'sqlsrv') {
                                    $stmt = $db->query("SELECT @@VERSION as version");
                                    $row = $stmt->fetch();
                                    if ($row) {
                                        // MSSQL version string is very long, extract just the main version
                                        preg_match('/Microsoft SQL Server (\d{4}|[\d\.]+)/', $row['version'], $matches);
                                        $dbVersion = $matches[0] ?? 'MS SQL Server';
                                    }
                                } else {
                                    $stmt = $db->query("SELECT VERSION() as version");
                                    $row = $stmt->fetch();
                                    if ($row) {
                                        // MySQL version, extract just version number
                                        preg_match('/^([\d\.]+)/', $row['version'], $matches);
                                        $dbVersion = 'MySQL ' . ($matches[1] ?? $row['version']);
                                    }
                                }
                                
                                echo htmlspecialchars($dbVersion) . ' <span class="badge badge-success">Connected</span>';
                            } catch (Exception $e) {
                                echo 'Error <span class="badge badge-danger">FAIL</span>';
                            }
                            ?>
                        </dd>
                        
                        <dt class="col-sm-4">Email Method:</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($config->get('email.method', 'disabled')) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</section>

<?php $theme->showFooter($context); ?>
