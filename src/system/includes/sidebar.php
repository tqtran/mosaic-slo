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
                
                <li class="nav-header">INSTITUTION</li>
                
                <!-- Institution Setup -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institution.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institution' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-building-gear"></i>
                        <p>Institution Setup</p>
                    </a>
                </li>
                
                <!-- Institutional Outcomes -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-flag"></i>
                        <p>Institutional Outcomes</p>
                    </a>
                </li>
                
                <li class="nav-header">ACADEMIC CALENDAR</li>
                
                <!-- Term Years -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/term_years.php" class="nav-link <?= ($currentPage ?? '') === 'admin_term_years' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-calendar-range"></i>
                        <p>Term Years</p>
                    </a>
                </li>
                
                <!-- Terms -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/terms.php" class="nav-link <?= ($currentPage ?? '') === 'admin_terms' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-calendar-week"></i>
                        <p>Terms</p>
                    </a>
                </li>
                
                <li class="nav-header">PROGRAMS</li>
                
                <!-- Program Management -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/programs.php" class="nav-link <?= ($currentPage ?? '') === 'admin_programs' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-mortarboard"></i>
                        <p>Programs</p>
                    </a>
                </li>
                
                <!-- Program Outcomes -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/program_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_program_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-bullseye"></i>
                        <p>Program Outcomes</p>
                    </a>
                </li>
                
                <li class="nav-header">COURSES & SECTIONS</li>
                
                <!-- Courses -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/courses.php" class="nav-link <?= ($currentPage ?? '') === 'admin_courses' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-book"></i>
                        <p>Courses</p>
                    </a>
                </li>
                
                <!-- Course Sections -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/course_sections.php" class="nav-link <?= ($currentPage ?? '') === 'admin_course_sections' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-calendar3"></i>
                        <p>Course Sections (CRN)</p>
                    </a>
                </li>
                
                <!-- Student Learning Outcomes -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/student_learning_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_slos' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-list-check"></i>
                        <p>Student Learning Outcomes</p>
                    </a>
                </li>
                
                <li class="nav-header">STUDENTS & ASSESSMENT</li>
                
                <!-- Students -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/students.php" class="nav-link <?= ($currentPage ?? '') === 'admin_students' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-people"></i>
                        <p>Students</p>
                    </a>
                </li>
                
                <!-- Assessments -->
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/assessments.php" class="nav-link <?= ($currentPage ?? '') === 'admin_assessments' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-clipboard-check"></i>
                        <p>Assessments</p>
                    </a>
                </li>
                
                <li class="nav-header">REPORTS</li>
                
                <!-- Reports (Coming Soon) -->
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
