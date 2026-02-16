<?php
/**
 * Theme Loader
 * 
 * Loads and returns the active theme instance.
 * 
 * Usage:
 *   $theme = ThemeLoader::getActiveTheme();
 *   $context = new ThemeContext(['pageTitle' => 'My Page']);
 *   $theme->showHeader($context);
 *   // ... page content ...
 *   $theme->showFooter($context);
 */

declare(strict_types=1);

namespace Mosaic\Core;

// Load dependencies required by ThemeLoader and themes
require_once __DIR__ . '/ThemeContext.php';
require_once __DIR__ . '/Theme.php';

class ThemeLoader
{
    private static ?Theme $activeTheme = null;
    private static ?string $activeThemeId = null;
    
    /**
     * Get the active theme instance
     * 
     * @param string|null $themeId Override theme ID (for demos/testing)
     * @return Theme Active theme instance
     */
    public static function getActiveTheme(?string $themeId = null): Theme
    {
        // Use provided theme ID or determine active theme
        $requestedThemeId = $themeId ?? self::determineActiveTheme();
        
        // Return cached instance if same theme
        if (self::$activeTheme && self::$activeThemeId === $requestedThemeId) {
            return self::$activeTheme;
        }
        
        // Build path to theme plugin
        $pluginPath = self::getPluginPath($requestedThemeId);
        
        // Verify theme exists
        if (!is_dir($pluginPath)) {
            throw new \RuntimeException("Theme not found: {$requestedThemeId} at {$pluginPath}");
        }
        
        // Load theme renderer class (REQUIRED for all themes)
        $themeClass = self::loadThemeClass($requestedThemeId, $pluginPath);
        
        self::$activeTheme = new $themeClass($pluginPath);
        self::$activeThemeId = $requestedThemeId;
        
        return self::$activeTheme;
    }
    
    /**
     * Determine which theme is active
     * 
     * @return string Active theme ID
     */
    /**
     * Determine which theme to use
     * 
     * Priority:
     * 1. config.yaml 'theme.active_theme' setting
     * 2. Database (when PluginManager exists)
     * 3. Fallback to 'theme-default'
     * 
     * @return string Theme plugin ID
     */
    private static function determineActiveTheme(): string
    {
        // Try config.yaml first
        $configPath = dirname(__DIR__, 2) . '/config/config.yaml';
        if (file_exists($configPath)) {
            try {
                $config = Config::getInstance($configPath);
                $activeTheme = $config->get('theme.active_theme');
                if ($activeTheme) {
                    return $activeTheme;
                }
            } catch (\Exception $e) {
                // Config not available or error reading, fall through to default
            }
        }
        
        // TODO: When PluginManager exists, query database:
        // SELECT plugin_id FROM plugins WHERE type='theme' AND is_active=1 LIMIT 1
        
        // Fallback to system default
        return 'theme-default';
    }
    
    /**
     * Get plugin directory path
     * 
     * @param string $themeId Theme plugin ID
     * @return string Absolute path to plugin directory
     */
    private static function getPluginPath(string $themeId): string
    {
        return dirname(__DIR__) . '/plugins/local/' . $themeId;
    }
    
    /**
     * Load theme renderer class (REQUIRED)
     * 
     * Convention: Every theme MUST have ThemeRenderer.php
     * Even if it just extends base Theme with no custom logic.
     * 
     * @param string $themeId Theme plugin ID (for error messages)
     * @param string $pluginPath Plugin directory path
     * @return string Fully qualified class name
     * @throws \RuntimeException If ThemeRenderer.php not found
     */
    private static function loadThemeClass(string $themeId, string $pluginPath): string
    {
        $classFile = $pluginPath . '/ThemeRenderer.php';
        
        if (!file_exists($classFile)) {
            throw new \RuntimeException(
                "Theme {$themeId} is missing required ThemeRenderer.php file. " .
                "Every theme must have this file, even if it just extends the base Theme class."
            );
        }
        
        require_once $classFile;
        
        // Check Mosaic\Theme namespace first
        $fullClassName = "\\Mosaic\\Theme\\ThemeRenderer";
        if (class_exists($fullClassName)) {
            return $fullClassName;
        }
        
        // Try global namespace
        if (class_exists('ThemeRenderer')) {
            return 'ThemeRenderer';
        }
        
        throw new \RuntimeException(
            "Theme {$themeId} has ThemeRenderer.php but class not found. " .
            "Expected class: Mosaic\\Theme\\ThemeRenderer"
        );
    }
    
    /**
     * Get all available themes
     * 
     * @return array Array of theme metadata
     */
    public static function getAvailableThemes(): array
    {
        $pluginsDir = dirname(__DIR__) . '/plugins/local';
        $themes = [];
        
        if (!is_dir($pluginsDir)) {
            return $themes;
        }
        
        $dirs = glob($pluginsDir . '/theme-*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $themeId = basename($dir);
            try {
                $theme = self::getActiveTheme($themeId);
                $themes[$themeId] = $theme->getMetadata();
            } catch (\Exception $e) {
                error_log("Failed to load theme {$themeId}: " . $e->getMessage());
            }
        }
        
        return $themes;
    }
}
