<?php
/**
 * Bootstrap 5 Theme - Fluid Layout Header
 * Full-width fluid container layout
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MOSAIC') ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --primary-dark: #0D47A1;
            --accent-blue: #1976D2;
            --brand-teal: #1565C0;
        }
        body {
            padding-top: 1rem;
        }
        .page-header {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-dark), var(--brand-teal));
            color: white;
        }
    </style>
    
    <?php if (isset($customCss)): ?>
    <?= $customCss ?>
    <?php endif; ?>
</head>
<body>
<div class="container-fluid">
    <?php if (isset($pageTitle)): ?>
    <header class="page-header rounded">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </header>
    <?php endif; ?>
