<?php
/**
 * Theme Base Class
 * 
 * Base class for all theme plugins.
 * Handles YAML loading, layout validation, and rendering.
 * 
 * Theme plugins extend this class and provide:
 * - Plugin directory path
 * - Optional custom rendering logic
 */

declare(strict_types=1);

namespace Mosaic\Core;

class Theme
{
    protected string $pluginPath;
    protected array $config = [];
    protected string $defaultLayout;
    
    /**
     * Constructor
     * 
     * @param string $pluginPath Absolute path to plugin directory
     */
    public function __construct(string $pluginPath)
    {
        $this->pluginPath = rtrim($pluginPath, '/\\');
        $this->loadConfig();
    }
    
    /**
     * Load plugin configuration from YAML
     * 
     * @return void
     */
    protected function loadConfig(): void
    {
        $configPath = $this->pluginPath . '/plugin.yaml';
        
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Theme config not found: {$configPath}");
        }
        
        $this->config = $this->parseYAML($configPath);
        $this->defaultLayout = $this->config['theme']['default_layout'] ?? 'simple';
    }
    
    /**
     * Simple YAML parser
     * 
     * @param string $filePath Path to YAML file
     * @return array Parsed YAML data
     */
    protected function parseYAML(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = [];
        $currentKey = null;
        $currentSection = null;
        $currentLayout = null;
        
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            
            preg_match('/^(\s*)(.+)$/', $line, $matches);
            $indent = strlen($matches[1] ?? '');
            $content = $matches[2] ?? '';
            
            if (str_contains($content, ':')) {
                [$key, $value] = array_map('trim', explode(':', $content, 2));
                
                if ($indent === 0) {
                    if ($value === '') {
                        $currentKey = $key;
                        $result[$key] = [];
                    } else {
                        $result[$key] = $value;
                    }
                } elseif ($indent === 2 && $currentKey === 'theme') {
                    if ($value === '') {
                        $currentSection = $key;
                        $result['theme'][$key] = [];
                    } else {
                        $result['theme'][$key] = $value;
                    }
                } elseif ($indent === 4 && $currentSection === 'layouts') {
                    if ($value === '') {
                        $currentLayout = $key;
                        $result['theme']['layouts'][$key] = [];
                    } else {
                        if ($currentLayout && isset($result['theme']['layouts'][$currentLayout])) {
                            $result['theme']['layouts'][$currentLayout][$key] = $value;
                        }
                    }
                } elseif ($indent === 6 && $currentLayout) {
                    $result['theme']['layouts'][$currentLayout][$key] = $value;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Show theme header
     * 
     * Fallback chain: layout-specific → default layout → error
     * 
     * @param ThemeContext $context Rendering context
     * @return void
     */
    public function showHeader(ThemeContext $context): void
    {
        $layout = $context->get('layout') ?? $this->defaultLayout;
        $layoutConfig = $this->getLayoutConfig($layout);
        
        $headerPath = $this->pluginPath . '/' . $layoutConfig['header'];
        
        // Fallback to default layout if specific layout file doesn't exist
        if (!file_exists($headerPath)) {
            $defaultConfig = $this->getLayoutConfig('default');
            $headerPath = $this->pluginPath . '/' . $defaultConfig['header'];
            
            if (!file_exists($headerPath)) {
                throw new \RuntimeException("Header file not found for layout '{$layout}' and no default fallback exists");
            }
        }
        
        // Extract context variables for use in template
        extract($context->toArray());
        
        require $headerPath;
    }
    
    /**
     * Show theme footer
     * 
     * Fallback chain: layout-specific → default layout → error
     * 
     * @param ThemeContext $context Rendering context
     * @return void
     */
    public function showFooter(ThemeContext $context): void
    {
        $layout = $context->get('layout') ?? $this->defaultLayout;
        $layoutConfig = $this->getLayoutConfig($layout);
        
        $footerPath = $this->pluginPath . '/' . $layoutConfig['footer'];
        
        // Fallback to default layout if specific layout file doesn't exist
        if (!file_exists($footerPath)) {
            $defaultConfig = $this->getLayoutConfig('default');
            $footerPath = $this->pluginPath . '/' . $defaultConfig['footer'];
            
            if (!file_exists($footerPath)) {
                throw new \RuntimeException("Footer file not found for layout '{$layout}' and no default fallback exists");
            }
        }
        
        // Extract context variables for use in template
        extract($context->toArray());
        
        require $footerPath;
    }
    
    /**
     * Get layout configuration
     * 
     * @param string $layout Layout name
     * @return array Layout configuration
     */
    protected function getLayoutConfig(string $layout): array
    {
        if (!isset($this->config['theme']['layouts'][$layout])) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        
        return $this->config['theme']['layouts'][$layout];
    }
    
    /**
     * Get available layouts
     * 
     * @return array Array of layout names
     */
    public function getLayouts(): array
    {
        return array_keys($this->config['theme']['layouts'] ?? []);
    }
    
    /**
     * Get theme metadata
     * 
     * @return array Theme metadata (id, name, version, etc.)
     */
    public function getMetadata(): array
    {
        return [
            'id' => $this->config['id'] ?? 'unknown',
            'name' => $this->config['name'] ?? 'Unknown Theme',
            'version' => $this->config['version'] ?? '1.0.0',
            'type' => $this->config['type'] ?? 'theme',
            'description' => $this->config['description'] ?? '',
            'author' => $this->config['author'] ?? '',
            'default_layout' => $this->defaultLayout
        ];
    }
    
    /**
     * Get default layout name
     * 
     * @return string Default layout name
     */
    public function getDefaultLayout(): string
    {
        return $this->defaultLayout;
    }
}
