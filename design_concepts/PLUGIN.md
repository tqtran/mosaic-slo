# Plugin Architecture Documentation

## Overview

SLO Cloud supports a flexible plugin system that allows extending functionality without modifying core code. Plugins can add dashboard widgets, custom reports, themes, and data export formats.

## Plugin Types

### 1. Dashboard Plugins
Add widgets and information panels to user dashboards.

### 2. Report Plugins
Create custom assessment reports and data visualizations.

### 3. Theme Plugins
Customize the look and feel of the application.

### 4. Export Plugins
Add new data export formats (CSV, Excel, PDF, etc.).

---

## Plugin Structure

### Directory Layout

```
plugins/
├── local/                      # Local custom plugins
│   └── example/
│       ├── plugin.json         # Plugin manifest
│       ├── Example.php         # Main plugin class
│       ├── ExampleController.php
│       ├── config.php          # Plugin configuration
│       ├── assets/             # CSS, JS, images
│       └── views/              # Plugin views
└── vendor/                     # Third-party plugins (future)
```

### Plugin Manifest

**File**: `plugin.json`

```json
{
    "name": "Example Plugin",
    "id": "example",
    "version": "1.0.0",
    "description": "An example plugin demonstrating the plugin API",
    "author": "Your Name",
    "type": "dashboard",
    "requires": {
        "mosaic-slo": ">=1.0.0",
        "php": ">=7.4"
    },
    "autoload": {
        "Example": "Example.php",
        "ExampleController": "ExampleController.php"
    },
    "hooks": {
        "dashboard.faculty": "renderDashboardWidget",
        "reports.menu": "addReportMenuItem"
    },
    "routes": [
        {
            "path": "/plugin/example",
            "controller": "ExampleController",
            "action": "index"
        }
    ],
    "permissions": [
        "example.view",
        "example.manage"
    ]
}
```

---

## Plugin Base Class

**Location**: `src/Core/Plugin.php`

```php
abstract class Plugin {
    protected string $id;
    protected string $path;
    protected array $config;
    
    public function __construct(string $id, string $path) {
        $this->id = $id;
        $this->path = $path;
        $this->config = $this->loadConfig();
    }
    
    // Lifecycle hooks
    abstract public function activate(): bool;
    abstract public function deactivate(): bool;
    public function install(): bool { return true; }
    public function uninstall(): bool { return true; }
    
    // Plugin methods
    public function getId(): string
    public function getName(): string
    public function getVersion(): string
    public function getConfig(string $key = null): mixed
    public function setConfig(string $key, mixed $value): void
    
    // Helper methods
    protected function loadConfig(): array
    protected function view(string $view, array $data = []): string
    protected function asset(string $file): string
}
```

---

## Plugin Manager

**Location**: `src/Core/PluginManager.php`

```php
class PluginManager {
    private array $plugins = [];
    private array $hooks = [];
    private string $pluginPath;
    
    public function __construct(string $pluginPath) {
        $this->pluginPath = $pluginPath;
        $this->loadPlugins();
    }
    
    // Plugin management
    public function loadPlugins(): void
    public function getPlugin(string $id): ?Plugin
    public function getAllPlugins(): array
    public function activatePlugin(string $id): bool
    public function deactivatePlugin(string $id): bool
    
    // Hook system
    public function registerHook(string $hook, callable $callback): void
    public function executeHook(string $hook, array $args = []): mixed
    public function hasHook(string $hook): bool
    
    // Route registration
    public function registerRoutes(Router $router): void
}
```

---

## Creating a Plugin

### Example: Dashboard Widget Plugin

#### 1. Create Plugin Class

**File**: `plugins/local/slo-summary/SloSummary.php`

```php
<?php

class SloSummary extends Plugin {
    
    public function activate(): bool {
        // Run when plugin is activated
        return true;
    }
    
    public function deactivate(): bool {
        // Run when plugin is deactivated
        return true;
    }
    
    /**
     * Render dashboard widget
     * Hook: dashboard.faculty
     */
    public function renderDashboardWidget(array $data): string {
        $userId = $data['user_id'];
        
        // Get instructor's sections
        $sectionModel = new CourseSection();
        $sections = $sectionModel->findByInstructor($userId);
        
        // Calculate summary stats
        $stats = $this->calculateStats($sections);
        
        // Render view
        return $this->view('widget', [
            'stats' => $stats,
            'sections' => $sections
        ]);
    }
    
    private function calculateStats(array $sections): array {
        $assessmentModel = new Assessment();
        $totalAssessments = 0;
        $completionRate = 0;
        
        foreach ($sections as $section) {
            $assessments = $assessmentModel->findBySection($section['course_sections_pk']);
            $totalAssessments += count($assessments);
            $completionRate += $this->getCompletionRate($assessments);
        }
        
        return [
            'total_sections' => count($sections),
            'total_assessments' => $totalAssessments,
            'avg_completion' => $completionRate / max(count($sections), 1)
        ];
    }
    
    private function getCompletionRate(array $assessments): float {
        if (empty($assessments)) return 0;
        
        $finalized = array_filter($assessments, fn($a) => $a['is_finalized']);
        return (count($finalized) / count($assessments)) * 100;
    }
}
```

#### 2. Create View

**File**: `plugins/local/slo-summary/views/widget.php`

```php
<div class="card plugin-widget">
    <div class="card-header">
        <h3>SLO Assessment Summary</h3>
    </div>
    <div class="card-body">
        <div class="stat-grid">
            <div class="stat-item">
                <div class="stat-value"><?= $stats['total_sections'] ?></div>
                <div class="stat-label">Active Sections</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $stats['total_assessments'] ?></div>
                <div class="stat-label">Total Assessments</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($stats['avg_completion'], 1) ?>%</div>
                <div class="stat-label">Avg Completion</div>
            </div>
        </div>
        
        <h4>Recent Sections</h4>
        <ul class="section-list">
            <?php foreach (array_slice($sections, 0, 5) as $section): ?>
            <li>
                <a href="/sections/<?= $section['course_sections_pk'] ?>">
                    <?= htmlspecialchars($section['course_code']) ?> - 
                    <?= htmlspecialchars($section['section_code']) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
```

#### 3. Create Manifest

**File**: `plugins/local/slo-summary/plugin.json`

```json
{
    "name": "SLO Assessment Summary",
    "id": "slo-summary",
    "version": "1.0.0",
    "description": "Dashboard widget showing SLO assessment summary for instructors",
    "author": "SLO Cloud",
    "type": "dashboard",
    "requires": {
        "mosaic-slo": ">=1.0.0"
    },
    "autoload": {
        "SloSummary": "SloSummary.php"
    },
    "hooks": {
        "dashboard.faculty": "renderDashboardWidget"
    }
}
```

---

## Available Hooks

### Dashboard Hooks

| Hook | Description | Parameters | Expected Return |
|------|-------------|------------|-----------------|
| `dashboard.faculty` | Faculty dashboard | `['user_id' => int]` | HTML string |
| `dashboard.admin` | Admin dashboard | `['user_id' => int]` | HTML string |
| `dashboard.before_content` | Before main content | `['user_id' => int]` | HTML string |
| `dashboard.after_content` | After main content | `['user_id' => int]` | HTML string |

### Report Hooks

| Hook | Description | Parameters | Expected Return |
|------|-------------|------------|-----------------|
| `reports.menu` | Add report menu items | `[]` | Array of menu items |
| `reports.export` | Add export format | `['data' => array, 'format' => string]` | File content |
| `reports.filter` | Modify report filters | `['filters' => array]` | Modified filters array |

### Assessment Hooks

| Hook | Description | Parameters | Expected Return |
|------|-------------|------------|-----------------|
| `assessment.before_save` | Before saving assessment | `['assessment' => array]` | Modified assessment array |
| `assessment.after_save` | After saving assessment | `['assessment_id' => int]` | void |
| `assessment.finalize` | When assessment finalized | `['assessment_id' => int]` | void |

### Menu Hooks

| Hook | Description | Parameters | Expected Return |
|------|-------------|------------|-----------------|
| `nav.main` | Main navigation menu | `['user_id' => int]` | Array of menu items |
| `nav.user` | User dropdown menu | `['user_id' => int]` | Array of menu items |

---

## Plugin Configuration

### Config File

**File**: `plugins/local/example/config.php`

```php
<?php

return [
    'enabled' => true,
    'cache_duration' => 3600,
    'display_limit' => 10,
    'colors' => [
        'primary' => '#007bff',
        'success' => '#28a745',
        'warning' => '#ffc107'
    ],
    'features' => [
        'show_charts' => true,
        'export_enabled' => true
    ]
];
```

### Accessing Config in Plugin

```php
class Example extends Plugin {
    public function someMethod() {
        $cacheTime = $this->getConfig('cache_duration');
        $showCharts = $this->getConfig('features.show_charts');
    }
}
```

---

## Plugin API Reference

### Database Access

Plugins can use core models or create their own:

```php
class Example extends Plugin {
    public function getData() {
        // Use core models
        $courseModel = new Course();
        $courses = $courseModel->all();
        
        // Or use direct database access
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM custom_plugin_table");
        
        return $result;
    }
}
```

### Creating Plugin Tables

```php
class Example extends Plugin {
    public function install(): bool {
        $db = Database::getInstance();
        
        $sql = "
            CREATE TABLE IF NOT EXISTS plugin_example_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        return $db->execute($sql);
    }
    
    public function uninstall(): bool {
        $db = Database::getInstance();
        return $db->execute("DROP TABLE IF EXISTS plugin_example_data");
    }
}
```

### Accessing Current User

```php
class Example extends Plugin {
    public function renderWidget(array $data) {
        $userId = Auth::userId();
        $user = Auth::user();
        
        if (Auth::hasRole('admin')) {
            // Admin-specific content
        }
    }
}
```

---

## Plugin Assets

### Loading CSS/JS

**In View File**:

```php
<link rel="stylesheet" href="<?= $this->asset('style.css') ?>">
<script src="<?= $this->asset('script.js')?>"></script>
```

### Asset Directory

```
plugins/local/example/
└── assets/
    ├── css/
    │   └── style.css
    ├── js/
    │   └── script.js
    └── images/
        └── icon.png
```

---

## Plugin Security

### Permissions

Define plugin-specific permissions in manifest:

```json
{
    "permissions": [
        "example.view",
        "example.edit",
        "example.delete"
    ]
}
```

Check permissions in plugin:

```php
class Example extends Plugin {
    public function someAction() {
        if (!Auth::hasPermission('example.edit')) {
            throw new ForbiddenException('Access denied');
        }
        
        // Perform action
    }
}
```

### Input Validation

Always validate and sanitize user input:

```php
class ExampleController extends Controller {
    public function save() {
        $data = $this->request->post();
        
        // Validate
        $validator = new Validator($data, [
            'title' => 'required|max:255',
            'content' => 'required'
        ]);
        
        if (!$validator->passes()) {
            return $this->json(['errors' => $validator->errors()], 400);
        }
        
        // Save data
    }
}
```

---

## Example Plugins

### 1. CSV Export Plugin

Adds CSV export functionality to reports:

```php
class CsvExport extends Plugin {
    public function exportReport(array $data): string {
        $csv = $data['format'];
        
        if ($csv !== 'csv') {
            return null; // Not handling this format
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write header
        fputcsv($output, array_keys($data['data'][0]));
        
        // Write data
        foreach ($data['data'] as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }
}
```

### 2. Dark Theme Plugin

Adds dark theme option:

```php
class DarkTheme extends Plugin {
    public function activate(): bool {
        // Copy theme assets
        copy(
            $this->path . '/assets/dark-theme.css',
            PUBLIC_PATH . '/assets/themes/dark.css'
        );
        return true;
    }
    
    public function addThemeOption(array $data): array {
        $data['themes'][] = [
            'id' => 'dark',
            'name' => 'Dark Theme',
            'css' => '/assets/themes/dark.css'
        ];
        return $data;
    }
}
```

---

## Plugin Best Practices

1. **Namespace properly** - Prefix all classes, tables, and config keys with plugin ID
2. **Clean up resources** - Remove assets, tables, and config on uninstall
3. **Handle errors gracefully** - Don't break core functionality if plugin fails
4. **Document hooks and APIs** - Provide clear documentation for plugin users
5. **Version dependencies** - Specify minimum required versions
6. **Test thoroughly** - Test with different configurations and user roles
7. **Follow core conventions** - Use same coding style and patterns as core
8. **Optimize performance** - Cache expensive operations, lazy-load resources
9. **Security first** - Validate all input, escape all output, check permissions
10. **Provide settings UI** - Allow configuration through admin interface

---

## Plugin Development Workflow

1. Create plugin directory in `plugins/local/`
2. Create `plugin.json` manifest
3. Create main plugin class extending `Plugin`
4. Implement required methods (`activate`, `deactivate`)
5. Register hooks in manifest
6. Create views in `views/` subdirectory
7. Add assets in `assets/` subdirectory
8. Test plugin functionality
9. Document plugin usage
10. Package for distribution (if public)
