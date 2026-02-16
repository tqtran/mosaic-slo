<?php
/**
 * Theme Loader Helper (Legacy Compatibility)
 * 
 * This file is deprecated. Use the OOP approach instead:
 * 
 * NEW PATTERN:
 *   use Mosaic\Core\ThemeLoader;
 *   use Mosaic\Core\ThemeContext;
 *   
 *   $context = new ThemeContext([
 *       'layout' => 'admin',
 *       'pageTitle' => 'Dashboard',
 *       'currentPage' => 'admin_dashboard',
 *       'customCss' => '<style>...</style>',
 *       'customScripts' => '<script>...</script>'
 *   ]);
 *   
 *   $theme = ThemeLoader::getActiveTheme();
 *   $theme->showHeader($context);
 *   
 *   <!-- Page content -->
 *   
 *   $theme->showFooter($context);
 * 
 * This legacy wrapper is provided for backward compatibility only.
 */

declare(strict_types=1);

// Load core classes
require_once __DIR__ . '/../Core/ThemeContext.php';
require_once __DIR__ . '/../Core/Theme.php';
require_once __DIR__ . '/../Core/ThemeLoader.php';

use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

// Build context from existing page variables
$contextData = get_defined_vars();

// Create theme context
$themeContext = new ThemeContext($contextData);

try {
    // Get active theme
    $activeTheme = ThemeLoader::getActiveTheme();
    
    // Store in globals for closeThemeLayout()
    $GLOBALS['_active_theme'] = $activeTheme;
    $GLOBALS['_theme_context'] = $themeContext;
    
    // Show header
    $activeTheme->showHeader($themeContext);
    
} catch (\Exception $e) {
    error_log("Theme loading failed: " . $e->getMessage());
    
    // Fallback to legacy includes
    if (file_exists(__DIR__ . '/header.php')) {
        require_once __DIR__ . '/header.php';
        $GLOBALS['_theme_fallback'] = true;
    } else {
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<p>Error loading theme: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

/**
 * Close theme layout - include footer
 * Called at end of page content
 * 
 * @deprecated Use $theme->showFooter($context) instead
 */
function closeThemeLayout(): void {
    if ($GLOBALS['_theme_fallback'] ?? false) {
        // Using legacy includes
        if (file_exists(__DIR__ . '/footer.php')) {
            require_once __DIR__ . '/footer.php';
        } else {
            echo "</body></html>";
        }
        return;
    }
    
    $activeTheme = $GLOBALS['_active_theme'] ?? null;
    $themeContext = $GLOBALS['_theme_context'] ?? null;
    
    if ($activeTheme && $themeContext) {
        try {
            $activeTheme->showFooter($themeContext);
        } catch (\Exception $e) {
            error_log("Theme footer failed: " . $e->getMessage());
            echo "</body></html>";
        }
    } else {
        echo "</body></html>";
    }
}
