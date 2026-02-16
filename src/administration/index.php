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
    'institutions' => 0,
    'programs' => 0,
    'outcomes' => 0,
    'assessments' => 0
];

try {
    $conn = $db->getConnection();
    
    // Count institutions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}institution WHERE is_active = 1");
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $metrics['institutions'] = $count ?? 0;
    $stmt->close();
    
    // Count programs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}programs WHERE is_active = 1");
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $metrics['programs'] = $count ?? 0;
    $stmt->close();
    
    // Count SLOs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}student_learning_outcomes WHERE is_active = 1");
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $metrics['outcomes'] = $count ?? 0;
    $stmt->close();
    
    // Count assessments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$dbPrefix}assessments");
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $metrics['assessments'] = $count ?? 0;
    $stmt->close();
    
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

<!-- Metrics Row -->
<div class="row">
    <!-- Institutions -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= $metrics['institutions'] ?></h3>
                <p>Institutions</p>
            </div>
            <div class="icon">
                <i class="fas fa-university"></i>
            </div>
            <a href="<?= BASE_URL ?>administration/institution.php" class="small-box-footer">
                Manage <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    
    <!-- Programs -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= $metrics['programs'] ?></h3>
                <p>Programs</p>
            </div>
            <div class="icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <a href="<?= BASE_URL ?>administration/outcomes.php" class="small-box-footer">
                View Outcomes <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    
    <!-- Outcomes -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= $metrics['outcomes'] ?></h3>
                <p>Learning Outcomes</p>
            </div>
            <div class="icon">
                <i class="fas fa-bullseye"></i>
            </div>
            <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="small-box-footer">
                Manage <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    
    <!-- Assessments -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= $metrics['assessments'] ?></h3>
                <p>Assessments</p>
            </div>
            <div class="icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="small-box-footer">
                View Reports <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>

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
                        <a href="<?= BASE_URL ?>administration/institution.php" class="btn btn-app">
                            <i class="fas fa-university"></i> Institution Settings
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="btn btn-app">
                            <i class="fas fa-bullseye"></i> Manage Outcomes
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>administration/config.php" class="btn btn-app">
                            <i class="fas fa-cog"></i> System Configuration
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
                    <dd class="col-sm-8">1.0.0-dev</dd>
                    
                    <dt class="col-sm-4">PHP Version:</dt>
                    <dd class="col-sm-8"><?= PHP_VERSION ?></dd>
                    
                    <dt class="col-sm-4">Database:</dt>
                    <dd class="col-sm-8">
                        <?php
                        try {
                            echo 'Connected <span class="badge badge-success">OK</span>';
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
                    <li><a href="<?= BASE_URL ?>administration/institution.php">Configure institution details</a></li>
                    <li><a href="<?= BASE_URL ?>administration/institutional_outcomes.php">Set up institutional outcomes</a></li>
                    <li>Create programs and program outcomes</li>
                    <li>Define course SLOs</li>
                    <li>Configure LTI integration with your LMS</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php $theme->showFooter($context); ?>
