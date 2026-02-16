<?php
/**
 * DEPRECATED: This file is no longer used.
 * 
 * Pages should use ThemeLoader directly instead:
 * 
 * require_once __DIR__ . '/../system/Core/ThemeLoader.php';
 * require_once __DIR__ . '/../system/Core/ThemeContext.php';
 * 
 * use Mosaic\Core\ThemeLoader;
 * use Mosaic\Core\ThemeContext;
 * 
 * $context = new ThemeContext([
 *     'layout' => 'admin',
 *     'pageTitle' => 'My Page Title',
 *     'currentPage' => 'page_id',
 *     'breadcrumbs' => [...]
 * ]);
 * 
 * $theme = ThemeLoader::getActiveTheme();
 * $theme->showHeader($context);
 */

trigger_error(
    'header.php is deprecated. Use ThemeLoader::getActiveTheme()->showHeader($context) instead.',
    E_USER_DEPRECATED
);
