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
        <i class="bi bi-graph-up ms-3"></i>
        <span class="brand-text fw-light">MOSAIC</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/" class="nav-link <?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-speedometer2"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-header">ADMINISTRATION</li>
                
                <!-- Institution Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institution.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institution' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-building"></i>
                        <p>Institution</p>
                    </a>
                </li>
                
                <!-- Outcome Hierarchy Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-diagram-3"></i>
                        <p>Institutional Outcomes</p>
                    </a>
                </li>
                
                <!-- Program Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/programs.php" class="nav-link <?= ($currentPage ?? '') === 'admin_programs' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-mortarboard"></i>
                        <p>Programs</p>
                    </a>
                </li>
                
                <li class="nav-header">ASSESSMENT</li>
                
                <!-- Coming Soon Items -->
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon bi bi-book"></i>
                        <p>Course Sections <span class="badge text-bg-secondary">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon bi bi-clipboard-check"></i>
                        <p>Assessment Entry <span class="badge text-bg-secondary">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon bi bi-bar-chart"></i>
                        <p>Reports <span class="badge text-bg-secondary">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-header">SYSTEM</li>
                
                <!-- Configuration -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/config.php" class="nav-link <?= ($currentPage ?? '') === 'admin_config' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-gear"></i>
                        <p>Configuration</p>
                    </a>
                </li>
                
                <!-- LTI Integration -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>lti/" class="nav-link <?= ($currentPage ?? '') === 'admin_lti' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-plug"></i>
                        <p>LTI Integration</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
