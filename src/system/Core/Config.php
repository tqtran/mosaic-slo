<?php
declare(strict_types=1);

namespace Mosaic\Core;

use RuntimeException;

/**
 * YAML Configuration Parser
 * 
 * Simple YAML parser for configuration files.
 * Supports basic YAML syntax (key-value pairs, nested objects, arrays).
 * 
 * @package Mosaic\Core
 */
class Config
{
    private static ?Config $instance = null;
    private array $config = [];
    private string $configPath;
    
    /**
     * Private constructor
     * 
     * @param string $configPath Path to config.yml
     * @throws RuntimeException If config file not found or invalid
     */
    private function __construct(string $configPath)
    {
        $this->configPath = $configPath;
        $this->load();
    }
    
    /**
     * Get singleton instance
     * 
     * @param string|null $configPath Path to config file (required on first call)
     * @return Config
     */
    public static function getInstance(?string $configPath = null): Config
    {
        if (self::$instance === null) {
            if ($configPath === null) {
                throw new RuntimeException('Config path required on first instantiation');
            }
            self::$instance = new self($configPath);
        }
        
        return self::$instance;
    }
    
    /**
     * Check if configuration file exists
     * 
     * @param string $configPath Path to config file
     * @return bool
     */
    public static function exists(string $configPath): bool
    {
        return file_exists($configPath);
    }
    
    /**
     * Load configuration from YAML file
     * 
     * @throws RuntimeException If file cannot be read or parsed
     */
    private function load(): void
    {
        if (!file_exists($this->configPath)) {
            throw new RuntimeException("Configuration file not found: {$this->configPath}");
        }
        
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            throw new RuntimeException("Cannot read configuration file: {$this->configPath}");
        }
        
        $this->config = $this->parseYaml($content);
    }
    
    /**
     * Simple YAML parser
     * 
     * @param string $yaml YAML content
     * @return array Parsed configuration
     */
    private function parseYaml(string $yaml): array
    {
        $lines = explode("\n", $yaml);
        $config = [];
        $currentPath = [];
        $lastIndent = 0;
        
        foreach ($lines as $line) {
            // Skip empty lines and comments
            if (trim($line) === '' || preg_match('/^\s*#/', $line)) {
                continue;
            }
            
            // Get indentation level
            preg_match('/^(\s*)/', $line, $matches);
            $indent = strlen($matches[1]);
            $line = ltrim($line);
            
            // Handle indent changes
            $indentChange = ($indent - $lastIndent) / 2;
            if ($indentChange < 0) {
                // Going back up the tree
                for ($i = 0; $i < abs($indentChange); $i++) {
                    array_pop($currentPath);
                }
            }
            
            // Parse key-value pair
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                
                if ($value === '') {
                    // This is a parent key
                    $currentPath[] = $key;
                } else {
                    // This is a key-value pair
                    $value = $this->parseValue($value);
                    $this->setNestedValue($config, array_merge($currentPath, [$key]), $value);
                }
            }
            
            $lastIndent = $indent;
        }
        
        return $config;
    }
    
    /**
     * Parse a YAML value
     * 
     * @param string $value Raw value string
     * @return mixed Parsed value
     */
    private function parseValue(string $value): mixed
    {
        // Remove quotes if present
        if (preg_match('/^(["\'])(.+)\1$/', $value, $matches)) {
            return $matches[2];
        }
        
        // Boolean values
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        
        // Null values
        if ($value === 'null' || $value === '~') return null;
        
        // Numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }
        
        return $value;
    }
    
    /**
     * Set a nested array value using a path
     * 
     * @param array $array Array to modify
     * @param array $path Path to the value
     * @param mixed $value Value to set
     */
    private function setNestedValue(array &$array, array $path, mixed $value): void
    {
        $current = &$array;
        
        foreach ($path as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        
        $current = $value;
    }
    
    /**
     * Get a configuration value
     * 
     * @param string $key Dot-notation key (e.g., 'database.host')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Get all configuration
     * 
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * Check if a key exists
     * 
     * @param string $key Dot-notation key
     * @return bool
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
    
    /**
     * Set a configuration value
     * 
     * @param string $key Dot-notation key (e.g., 'database.host')
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $this->setNestedValue($this->config, $keys, $value);
    }
    
    /**
     * Save configuration to YAML file
     * 
     * @throws RuntimeException If file cannot be written
     * @return bool
     */
    public function save(): bool
    {
        $yaml = $this->arrayToYaml($this->config);
        
        $result = file_put_contents($this->configPath, $yaml);
        if ($result === false) {
            throw new RuntimeException("Cannot write configuration file: {$this->configPath}");
        }
        
        return true;
    }
    
    /**
     * Convert array to YAML format
     * 
     * @param array $array Array to convert
     * @param int $indent Current indentation level
     * @return string YAML string
     */
    private function arrayToYaml(array $array, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);
        
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                // Check if it's an associative array
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $yaml .= $indentStr . $key . ":\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                } else {
                    // Sequential array - treat as list
                    $yaml .= $indentStr . $key . ":\n";
                    foreach ($value as $item) {
                        $yaml .= $indentStr . "  - " . $this->formatYamlValue($item) . "\n";
                    }
                }
            } else {
                $yaml .= $indentStr . $key . ': ' . $this->formatYamlValue($value) . "\n";
            }
        }
        
        return $yaml;
    }
    
    /**
     * Format a value for YAML output
     * 
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function formatYamlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        // String - quote if contains special characters
        if (is_string($value)) {
            if (preg_match('/[:\{\}\[\],&*#?|\-<>=!%@`\']/', $value) || trim($value) !== $value) {
                return '"' . str_replace('"', '\\"', $value) . '"';
            }
            return $value;
        }
        
        return (string)$value;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }
}
