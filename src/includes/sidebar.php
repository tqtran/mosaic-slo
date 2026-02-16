<?php
/**
 * AdminLTE 4 Sidebar Navigation
 * 
 * Usage:
 *   $currentPage = 'admin_institution'; // set before including
 *   require_once __DIR__ . '/includes/sidebar.php';
 */
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= BASE_URL ?>" class="brand-link">
        <i class="fas fa-chart-line brand-image ml-3"></i>
        <span class="brand-text font-weight-light">MOSAIC</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-user-circle fa-2x text-white"></i>
            </div>
            <div class="info">
                <a href="#" class="d-block">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>dashboard.php" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-header">ADMINISTRATION</li>
                
                <!-- Institution Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_institution.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institution' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-university"></i>
                        <p>Institution</p>
                    </a>
                </li>
                
                <!-- Institutional Outcomes -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_institutional_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-bullseye"></i>
                        <p>Institutional Outcomes</p>
                    </a>
                </li>
                
                <!-- Programs -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_programs.php" class="nav-link <?= ($currentPage ?? '') === 'admin_programs' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-book"></i>
                        <p>Programs</p>
                    </a>
                </li>
                
                <!-- Courses -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_courses.php" class="nav-link <?= ($currentPage ?? '') === 'admin_courses' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-book-open"></i>
                        <p>Courses</p>
                    </a>
                </li>
                
                <!-- SLO Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_slo.php" class="nav-link <?= ($currentPage ?? '') === 'admin_slo' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-graduation-cap"></i>
                        <p>Student Learning Outcomes</p>
                    </a>
                </li>
                
                <li class="nav-header">ASSESSMENT</li>
                
                <!-- Course Sections -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_sections.php" class="nav-link <?= ($currentPage ?? '') === 'admin_sections' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <p>Course Sections</p>
                    </a>
                </li>
                
                <!-- Assessment Entry -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_assessments.php" class="nav-link <?= ($currentPage ?? '') === 'admin_assessments' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Assessment Entry</p>
                    </a>
                </li>
                
                <!-- Reports -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_reports.php" class="nav-link <?= ($currentPage ?? '') === 'admin_reports' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Reports</p>
                    </a>
                </li>
                
                <li class="nav-header">SYSTEM</li>
                
                <!-- Configuration -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_config.php" class="nav-link <?= ($currentPage ?? '') === 'admin_config' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>Configuration</p>
                    </a>
                </li>
                
                <!-- LTI Consumers -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_lti.php" class="nav-link <?= ($currentPage ?? '') === 'admin_lti' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-plug"></i>
                        <p>LTI Consumers</p>
                    </a>
                </li>
                
                <!-- Users -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>admin_users.php" class="nav-link <?= ($currentPage ?? '') === 'admin_users' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
