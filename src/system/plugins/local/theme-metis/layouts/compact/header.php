<?php
/**
 * Metis Theme - Compact Layout Header
 * Collapsed sidebar for more content space
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MOSAIC') ?></title>
    
    <!-- Google Font: Roboto -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap">
    <!-- Material Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --primary-dark: #0D47A1;
            --accent-blue: #1976D2;
            --brand-teal: #1565C0;
            --sidebar-width: 70px;
        }
        * {
            font-family: 'Roboto', sans-serif;
        }
        body {
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .metis-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .metis-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-dark), var(--brand-teal));
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .metis-sidebar .brand {
            padding: 1.5rem;
            text-align: center;
            font-size: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .metis-sidebar .nav-menu {
            padding: 1rem 0;
        }
        .metis-sidebar .nav-menu a {
            color: rgba(255,255,255,0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        .metis-sidebar .nav-menu a:hover,
        .metis-sidebar .nav-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .metis-sidebar .nav-menu i {
            font-size: 1.5rem;
        }
        .metis-main {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .metis-topbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .metis-content {
            padding: 2rem;
            flex: 1;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
    </style>
    
    <?php if (isset($customCss)): ?>
    <?= $customCss ?>
    <?php endif; ?>
</head>
<body>
<div class="metis-wrapper">
    <!-- Compact Sidebar -->
    <aside class="metis-sidebar">
        <div class="brand">
            <i class="bi bi-graph-up"></i>
        </div>
        <nav class="nav-menu">
            <a href="<?= BASE_URL ?>administration/" class="<?= ($currentPage ?? '') === 'admin_dashboard' ? 'active' : '' ?>" title="Dashboard">
                <i class="bi bi-speedometer2"></i>
            </a>
            <a href="<?= BASE_URL ?>administration/institution.php" class="<?= ($currentPage ?? '') === 'admin_institution' ? 'active' : '' ?>" title="Institution">
                <i class="bi bi-building"></i>
            </a>
            <a href="<?= BASE_URL ?>administration/institutional_outcomes.php" class="<?= ($currentPage ?? '') === 'admin_institutional_outcomes' ? 'active' : '' ?>" title="Outcomes">
                <i class="bi bi-diagram-3"></i>
            </a>
            <a href="<?= BASE_URL ?>administration/config.php" class="<?= ($currentPage ?? '') === 'admin_config' ? 'active' : '' ?>" title="Configuration">
                <i class="bi bi-gear"></i>
            </a>
            <a href="<?= BASE_URL ?>lti/" class="<?= ($currentPage ?? '') === 'admin_lti' ? 'active' : '' ?>" title="LTI">
                <i class="bi bi-plug"></i>
            </a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="metis-main">
        <div class="metis-topbar">
            <h1 class="h5 mb-0"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            <div>
                <span class="text-muted"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
            </div>
        </div>
        <div class="metis-content">
