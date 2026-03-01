<?php
/**
 * AdminLTE 4 Theme - Admin Layout Header
 * Full sidebar navigation for administration
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MOSAIC') ?></title>
    
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- AdminLTE 4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/adminlte4@4.0.0-rc.6.20260104/dist/css/adminlte.min.css">
    
    <style>
        :root {
            --primary-dark: #0D47A1;
            --accent-blue: #1976D2;
            --brand-teal: #1565C0;
        }
        
        /* ==================== WCAG 2.2 AA/AAA Accessibility Styles ==================== */
        
        /* Skip link - visible on focus for keyboard navigation */
        .skip-link {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10000;
            padding: 0.75rem 1.5rem;
            transform: translateY(-100%);
            background: var(--accent-blue);
            color: white;
        }
        .skip-link:focus {
            transform: translateY(0);
        }
        
        /* Enhanced focus indicators (WCAG 2.4.7, 2.4.11, 2.4.13 Level AAA) */
        a:focus,
        button:focus,
        input:focus,
        select:focus,
        textarea:focus,
        .nav-link:focus,
        [tabindex]:focus {
            outline: 3px solid #0D47A1;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.2);
        }
        
        /* Focus visible for :focus-visible support */
        *:focus:not(:focus-visible) {
            outline: none;
            box-shadow: none;
        }
        *:focus-visible {
            outline: 3px solid #0D47A1;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.2);
        }
        
        /*Enhanced line spacing for readability (WCAG 1.4.8 Level AAA) */
        body {
            line-height: 1.5;
        }
        p {
            margin-bottom: 1.5em;
        }
        
        /* Respect user preference for reduced motion (WCAG 2.3.3 Level AAA) */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
        
        /* Enhanced contrast for muted text (WCAG 1.4.6 Level AAA - 7:1) */
        .text-muted {
            color: #495057 !important;
        }
        
        /* Touch target sizes (WCAG 2.5.5 Level AAA - 44x44px) */
        .btn, .nav-link, .dropdown-item, .form-select, .form-control {
            min-height: 44px;
            padding: 0.5rem 1rem;
        }
        .btn-sm {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* ==================== End Accessibility Styles ==================== */
    </style>
    
    <?php if (isset($customCss)): ?>
    <?= $customCss ?>
    <?php endif; ?>
</head>
<body class="layout-fixed sidebar-mini">

<!-- Skip Navigation Link -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<div class="app-wrapper">

<!-- Navbar -->
<nav class="app-header navbar navbar-expand bg-body" role="navigation" aria-label="Main navigation">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar navigation" aria-expanded="true" aria-controls="sidebar-nav"><i class="fas fa-bars" aria-hidden="true"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= BASE_URL ?>" class="nav-link"><i class="fas fa-home" aria-hidden="true"></i> Home</a>
        </li>
    </ul>
    <ul class="navbar-nav ms-auto"  role="menubar" aria-label="User menu">
        <?php
        // Get database and config from global scope
        $db = $GLOBALS['db'] ?? null;
        $dbPrefix = $GLOBALS['dbPrefix'] ?? '';
        $headerTerms = [];
        $headerSelectedTerm = null;
        
        // Fetch active terms for header dropdown
        if ($db && $dbPrefix) {
            try {
                $headerTermsResult = $db->query("
                    SELECT terms_pk, term_code, term_name, academic_year
                    FROM {$dbPrefix}terms 
                    WHERE is_active = 1 
                    ORDER BY term_code ASC
                ");
                $headerTerms = $headerTermsResult->fetchAll();
                
                // Get selected term from session/GET
                $headerSelectedTerm = function_exists('getSelectedTermFk') ? getSelectedTermFk() : null;
            } catch (\Exception $e) {
                // Silently fail if database not available
                error_log("Header term selector error: " . $e->getMessage());
            }
        }
        ?>
        <li class="nav-item me-3 d-flex align-items-center" role="none">
            <label for="headerTermSelector" class="me-2 mb-0"><strong>Term:</strong></label>
            <select id="headerTermSelector" class="form-select form-select-sm" style="min-width: 200px;" aria-label="Select term filter">
                <option value="">All Terms</option>
                <?php if (empty($headerTerms)): ?>
                    <option disabled>No terms available</option>
                <?php else: ?>
                    <?php foreach ($headerTerms as $headerTerm): ?>
                        <option value="<?= $headerTerm['terms_pk'] ?>" <?= $headerTerm['terms_pk'] == $headerSelectedTerm ? 'selected' : '' ?>>
                            <?= htmlspecialchars($headerTerm['term_code'] . ' - ' . $headerTerm['term_name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </li>
        <li class="nav-item dropdown" role="menuitem">
            <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false" aria-label="User menu">
                <i class="bi bi-person-circle" aria-hidden="true"></i>
                <span class="d-none d-md-inline ms-1">
                    <?php
                    // Get logged in user info from session
                    $loggedInUser = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Administrator';
                    echo htmlspecialchars($loggedInUser);
                    ?>
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-end" role="menu">
                <a href="<?= BASE_URL ?>administration/users.php" class="dropdown-item" role="menuitem">
                    <i class="bi bi-person me-2" aria-hidden="true"></i> My Profile
                </a>
                <a href="<?= BASE_URL ?>administration/config.php" class="dropdown-item" role="menuitem">
                    <i class="bi bi-gear me-2" aria-hidden="true"></i> Settings
                </a>
                <div class="dropdown-divider" role="separator"></div>
                <a href="<?= BASE_URL ?>logout.php" class="dropdown-item" role="menuitem">
                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</nav>

<!-- Sidebar -->
<aside id="sidebar-nav" class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark" role="navigation" aria-label="Sidebar navigation">
    <div class="sidebar-brand">
        <a href="<?= BASE_URL ?>" class="brand-link">
            <i class="bi bi-graph-up ms-3" aria-hidden="true"></i>
            <span class="brand-text fw-light"><?= htmlspecialchars(SITE_NAME ?? 'MOSAIC') ?></span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block" aria-label="User profile">
                    <i class="bi bi-person-circle me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </a>
            </div>
        </div>
        <nav class="mt-2" aria-label="Administration menu">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/" class="nav-link <?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_dashboard' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-speedometer2" aria-hidden="true"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">TERMS</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/terms.php" class="nav-link <?= ($currentPage ?? '') === 'admin_terms' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_terms' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-calendar-week" aria-hidden="true"></i>
                        <p>Terms</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">OUTCOMES</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-flag" aria-hidden="true"></i>
                        <p>ISLO</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">PROGRAMS</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/programs.php" class="nav-link <?= ($currentPage ?? '') === 'admin_programs' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_programs' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-mortarboard" aria-hidden="true"></i>
                        <p>Programs</p>
                    </a>
                </li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/program_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_program_outcomes' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_program_outcomes' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-bullseye" aria-hidden="true"></i>
                        <p>PSLO</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">COURSES</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/courses.php" class="nav-link <?= ($currentPage ?? '') === 'admin_courses' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_courses' ? 'active' : '' ?>>
                        <i class="nav-icon bi bi-book" aria-hidden="true"></i>
                        <p>Courses</p>
                    </a>
                </li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/student_learning_outcomes.php" class="nav-link <?= ($currentPage  ?? '') === 'admin_slos' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_slos' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-list-check" aria-hidden="true"></i>
                        <p>CSLO</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">STUDENTS</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/students.php" class="nav-link <?= ($currentPage ?? '') === 'admin_students' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_students' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-person" aria-hidden="true"></i>
                        <p>Students</p>
                    </a>
                </li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/enrollment.php" class="nav-link <?= ($currentPage ?? '') === 'admin_enrollment' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_enrollment' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-person-check" aria-hidden="true"></i>
                        <p>Enrollment</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">DATA</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/imports.php" class="nav-link <?= ($currentPage ?? '') === 'admin_imports' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_imports' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-upload" aria-hidden="true"></i>
                        <p>Imports</p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">REPORTS</li>
                <li class="nav-item" role="none">
                    <a href="#" class="nav-link disabled" role="menuitem" aria-disabled="true">
                        <i class="nav-icon bi bi-bar-chart" aria-hidden="true"></i>
                        <p>Reports <span class="badge text-bg-secondary">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-header" role="presentation">SYSTEM</li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/users.php" class="nav-link <?= ($currentPage ?? '') === 'admin_users' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_users' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-people" aria-hidden="true"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>administration/config.php" class="nav-link <?= ($currentPage ?? '') === 'admin_config' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_config' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-gear" aria-hidden="true"></i>
                        <p>Configuration</p>
                    </a>
                </li>
                <li class="nav-item" role="none">
                    <a href="<?= BASE_URL ?>lti/" class="nav-link <?= ($currentPage ?? '') === 'admin_lti' ? 'active' : '' ?>" role="menuitem" <?= ($currentPage ?? '') === 'admin_lti' ? 'aria-current="page"' : '' ?>>
                        <i class="nav-icon bi bi-plug" aria-hidden="true"></i>
                        <p>LTI Integration</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<!-- Content Wrapper -->
<main id="main-content" class="app-main" role="main">
    <?php if (isset($pageTitle)): ?>
    <div class="app-content-header">
        <div class="container-fluid">
            <h1 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </div>
    <?php endif; ?>
    <div class="app-content">
        <div class="container-fluid">
