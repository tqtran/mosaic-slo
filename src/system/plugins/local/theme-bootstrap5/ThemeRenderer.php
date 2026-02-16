<?php
/**
 * Bootstrap 5 Theme Renderer
 * 
 * Custom theme class example with helper methods.
 * Demonstrates how to extend base Theme class with custom functionality.
 */

declare(strict_types=1);

namespace Mosaic\Theme;

use Mosaic\Core\Theme;
use Mosaic\Core\ThemeContext;

class ThemeRenderer extends Theme
{
    /**
     * Render a simple alert box
     * 
     * Example of custom theme helper method.
     * 
     * @param string $message Alert message
     * @param string $type Alert type (primary, success, warning, danger, info)
     * @return string HTML for alert
     */
    public function renderAlert(string $message, string $type = 'info'): string
    {
        $escapedMessage = htmlspecialchars($message);
        return <<<HTML
<div class="alert alert-{$type} alert-dismissible fade show" role="alert">
    {$escapedMessage}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
HTML;
    }
    
    /**
     * Render a card component
     * 
     * @param string $title Card title
     * @param string $content Card content (HTML)
     * @param string|null $footer Optional footer content
     * @return string HTML for card
     */
    public function renderCard(string $title, string $content, ?string $footer = null): string
    {
        $escapedTitle = htmlspecialchars($title);
        $footerHtml = $footer ? "<div class=\"card-footer\">{$footer}</div>" : '';
        
        return <<<HTML
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">{$escapedTitle}</h5>
    </div>
    <div class="card-body">
        {$content}
    </div>
    {$footerHtml}
</div>
HTML;
    }
    
    /**
     * Render a breadcrumb navigation
     * 
     * @param array $items Array of ['label' => 'text', 'url' => 'link'] items
     * @return string HTML for breadcrumb
     */
    public function renderBreadcrumb(array $items): string
    {
        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        
        $lastIndex = count($items) - 1;
        foreach ($items as $index => $item) {
            $label = htmlspecialchars($item['label'] ?? '');
            $isActive = $index === $lastIndex;
            
            if ($isActive) {
                $html .= "<li class=\"breadcrumb-item active\" aria-current=\"page\">{$label}</li>";
            } else {
                $url = htmlspecialchars($item['url'] ?? '#');
                $html .= "<li class=\"breadcrumb-item\"><a href=\"{$url}\">{$label}</a></li>";
            }
        }
        
        $html .= '</ol></nav>';
        return $html;
    }
}
