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
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= BASE_URL ?>" class="nav-link"><i class="fas fa-home"></i> Home</a>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <span class="nav-link"><strong><?= htmlspecialchars(SITE_NAME ?? 'MOSAIC') ?></strong></span>
        </li>
    </ul>
</nav>

<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?= BASE_URL ?>" class="brand-link">
        <i class="bi bi-graph-up ms-3"></i>
        <span class="brand-text fw-light">MOSAIC</span>
    </a>
    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </a>
            </div>
        </div>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/" class="nav-link <?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-speedometer2"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-header">ADMINISTRATION</li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/institution.php" class="nav-link <?= ($currentPage ?? '') === 'admin_institution' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-building"></i>
                        <p>Institution</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>administration/outcomes.php" class="nav-link <?= ($currentPage ?? '') === 'admin_outcomes' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-diagram-3"></i>
                        <p>Outcome Hierarchy</p>
                    </a>
                </li>
                <li class="nav-header">SYSTEM</li>
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
<div class="content-wrapper">
    <?php if (isset($pageTitle)): ?>
    <section class="content-header">
        <div class="container-fluid">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </section>
    <?php endif; ?>
    <section class="content">
        <div class="container-fluid">
