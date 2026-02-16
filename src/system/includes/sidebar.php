<?php
/**
 * AdminLTE 4 Sidebar Navigation
 * 
 * Usage:
 *   $currentPage = 'admin_institution'; // set before including
 *   require_once __DIR__ . '/../system/includes/sidebar.php';
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
                    <a href="<?= BASE_URL ?>administration/" class="nav-link <?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-header">ADMINISTRATION</li>
                
                <!-- Institution Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institution.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institution' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-university"></i>
                        <p>Institution</p>
                    </a>
                </li>
                
                <!-- Outcome Hierarchy Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-sitemap"></i>
                        <p>Outcome Hierarchy</p>
                    </a>
                </li>
                
                <li class="nav-header">ASSESSMENT</li>
                
                <!-- Coming Soon Items -->
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <p>Course Sections <span class="badge badge-secondary right">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Assessment Entry <span class="badge badge-secondary right">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Reports <span class="badge badge-secondary right">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-header">SYSTEM</li>
                
                <!-- Configuration -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/config.php" class="nav-link <?= ($currentPage ?? '') === 'admin_config' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>Configuration</p>
                    </a>
                </li>
                
                <!-- LTI Integration -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>lti/" class="nav-link <?= ($currentPage ?? '') === 'admin_lti' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-plug"></i>
                        <p>LTI Integration</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
