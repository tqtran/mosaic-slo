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

// Fetch basic metrics (TODO: optimize with proper queries)
$metrics = [
    'programs' => 0,
    'outcomes' => 0,
    'assessments' => 0
];

try {
    $conn = $db->getConnection();
    
    // Count programs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}programs WHERE is_active = 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $metrics['programs'] = $row['count'] ?? 0;
    
    // Count SLOs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}student_learning_outcomes WHERE is_active = 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $metrics['outcomes'] = $row['count'] ?? 0;
    
    // Count assessments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}assessments");
    $stmt->execute();
    $row = $stmt->fetch();
    $metrics['assessments'] = $row['count'] ?? 0;
    
} catch (Exception $e) {
    // Tables might not exist yet - that's okay for new installations
    error_log('Dashboard metrics error: ' . $e->getMessage());
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

$theme = ThemeLoader::getActiveTheme();
$theme->showHeader($context);
?>

<!-- Quick Actions -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="btn btn-app">
                            <i class="fas fa-flag"></i> Institutional Outcomes
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/programs.php" class="btn btn-app">
                            <i class="fas fa-graduation-cap"></i> Programs
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/program_outcomes.php" class="btn btn-app">
                            <i class="fas fa-bullseye"></i> Program Outcomes
                        </a>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/courses.php" class="btn btn-app">
                            <i class="fas fa-book"></i> Sections
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/student_learning_outcomes.php" class="btn btn-app">
                            <i class="fas fa-tasks"></i> SLOs
                        </a>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/config.php" class="btn btn-app">
                            <i class="fas fa-cog"></i> System Config
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>lti/" class="btn btn-app">
                            <i class="fas fa-plug"></i> LTI Integration
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Status -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-server"></i> System Status
                </h3>
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
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> Getting Started
                </h3>
            </div>
            <div class="card-body">
                <ol>
                    <li><a href="<?= BASE_URL ?>administration/institutional_outcomes.php">Set up institutional outcomes</a></li>
                    <li><a href="<?= BASE_URL ?>administration/programs.php">Create academic programs</a></li>
                    <li><a href="<?= BASE_URL ?>administration/program_outcomes.php">Define program outcomes</a></li>
                    <li><a href="<?= BASE_URL ?>administration/courses.php">Add sections to the catalog</a></li>
                    <li><a href="<?= BASE_URL ?>administration/student_learning_outcomes.php">Define SLOs for sections</a></li>
                    <li>Configure LTI integration with your LMS</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php $theme->showFooter($context); ?>
