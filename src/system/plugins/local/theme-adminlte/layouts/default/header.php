<?php
/**
 * AdminLTE 4 Theme - Default Layout Header
 * Basic layout with top navbar, no sidebar
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
        main {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
    </style>
    
    <?php if (isset($customCss)): ?>
    <?= $customCss ?>
    <?php endif; ?>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand navbar-white navbar-light border-bottom">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>">
            <i class="bi bi-graph-up"></i> <strong>MOSAIC</strong>
        </a>
        <ul class="navbar-nav ms-auto">
            <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
                <span class="nav-link">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                </span>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<main class="container">
    <?php if (isset($pageTitle)): ?>
    <div class="mt-4 mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>
    <?php endif; ?>
