<?php
/**
 * Common Header Include
 * Loads all framework assets and theme structure
 * 
 * Usage:
 *   $pageTitle = 'My Page Title';
 *   $pageIcon = 'fas fa-dashboard'; // optional
 *   $breadcrumbs = [['url' => BASE_URL, 'label' => 'Home'], ['label' => 'Current Page']]; // optional
 *   $bodyClass = 'hold-transition sidebar-mini layout-fixed'; // for admin pages
 *   $currentPage = 'admin_dashboard'; // for sidebar active state
 *   require_once __DIR__ . '/../system/includes/header.php';
 */

$isAdminLayout = isset($bodyClass) && strpos($bodyClass, 'sidebar-mini') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? (defined('SITE_NAME') ? SITE_NAME : 'MOSAIC')) ?></title>
    
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
    
    <?php if (isset($customStyles)): ?>
    <?= $customStyles ?>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
<?php if ($isAdminLayout): ?>
<div class="wrapper">
<?php require_once __DIR__ . '/navbar.php'; ?>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <?php if (isset($pageIcon)): ?><i class="<?= htmlspecialchars($pageIcon) ?>"></i> <?php endif; ?>
                        <?= htmlspecialchars($pageTitle ?? 'Page') ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <?php if (isset($crumb['url'])): ?>
                                    <li class="breadcrumb-item"><a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['label']) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
<?php endif; ?>
