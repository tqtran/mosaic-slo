<?php
/**
 * Common Header Include
 * Loads all framework assets (Bootstrap 5, jQuery, AdminLTE 4, Font Awesome)
 * 
 * Usage:
 *   $pageTitle = 'My Page Title';
 *   $bodyClass = 'custom-class'; // optional
 *   require_once __DIR__ . '/../system/includes/header.php';
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? (defined('SITE_NAME') ? SITE_NAME : 'MOSAIC')) ?></title>
    
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
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
