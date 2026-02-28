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
    </style>
    
    <?php if (isset($customCss)): ?>
    <?= $customCss ?>
    <?php endif; ?>
</head>
<body class="layout-fixed sidebar-mini">
<div class="app-wrapper">

<!-- Navbar -->
<nav class="app-header navbar navbar-expand bg-body">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= BASE_URL ?>" class="nav-link"><i class="fas fa-home"></i> Home</a>
        </li>
    </ul>
    <ul class="navbar-nav ms-auto">
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
        <li class="nav-item me-3 d-flex align-items-center">
            <span class="me-2"><strong>Term:</strong></span>
            <select id="headerTermSelector" class="form-select form-select-sm" style="min-width: 200px;">
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
        <li class="nav-item dropdown">
            <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-md-inline ms-1">
                    <?php
                    // Get logged in user info from session
                    $loggedInUser = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Administrator';
                    echo htmlspecialchars($loggedInUser);
                    ?>
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-end">
                <a href="<?= BASE_URL ?>administration/users.php" class="dropdown-item">
                    <i class="bi bi-person me-2"></i> My Profile
                </a>
                <a href="<?= BASE_URL ?>administration/config.php" class="dropdown-item">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?= BASE_URL ?>logout.php" class="dropdown-item">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</nav>

<!-- Sidebar -->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="<?= BASE_URL ?>" class="brand-link">
            <i class="bi bi-graph-up ms-3"></i>
            <span class="brand-text fw-light"><?= htmlspecialchars(SITE_NAME ?? 'MOSAIC') ?></span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </a>
            </div>
        </div>
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/" class="nav-link <?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-speedometer2"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-header">TERMS</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/terms.php" class="nav-link <?= ($currentPage ?? '') === 'admin_terms' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-calendar-week"></i>
                        <p>Terms</p>
                    </a>
                </li>
                
                <li class="nav-header">OUTCOMES</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-flag"></i>
                        <p>ISLO</p>
                    </a>
                </li>
                
                <li class="nav-header">PROGRAMS</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/programs.php" class="nav-link <?= ($currentPage ?? '') === 'admin_programs' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-mortarboard"></i>
                        <p>Programs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/program_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_program_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-bullseye"></i>
                        <p>PSLO</p>
                    </a>
                </li>
                
                <li class="nav-header">COURSES</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/courses.php" class="nav-link <?= ($currentPage ?? '') === 'admin_courses' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-book"></i>
                        <p>Courses</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/student_learning_outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_slos' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-list-check"></i>
                        <p>CSLO</p>
                    </a>
                </li>
                
                <li class="nav-header">STUDENTS</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/students.php" class="nav-link <?= ($currentPage ?? '') === 'admin_students' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-person"></i>
                        <p>Students</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/enrollment.php" class="nav-link <?= ($currentPage ?? '') === 'admin_enrollment' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-person-check"></i>
                        <p>Enrollment</p>
                    </a>
                </li>
                
                <li class="nav-header">DATA</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/imports.php" class="nav-link <?= ($currentPage ?? '') === 'admin_imports' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-upload"></i>
                        <p>Imports</p>
                    </a>
                </li>
                
                <li class="nav-header">REPORTS</li>
                <li class="nav-item">
                    <a href="#" class="nav-link disabled">
                        <i class="nav-icon bi bi-bar-chart"></i>
                        <p>Reports <span class="badge text-bg-secondary">Soon</span></p>
                    </a>
                </li>
                
                <li class="nav-header">SYSTEM</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/users.php" class="nav-link <?= ($currentPage ?? '') === 'admin_users' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-people"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/config.php" class="nav-link <?= ($currentPage ?? '') === 'admin_config' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-gear"></i>
                        <p>Configuration</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>lti/" class="nav-link <?= ($currentPage ?? '') === 'admin_lti' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-plug"></i>
                        <p>LTI Integration</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<!-- Content Wrapper -->
<main class="app-main">
    <?php if (isset($pageTitle)): ?>
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
        </div>
    </div>
    <?php endif; ?>
    <div class="app-content">
        <div class="container-fluid">
