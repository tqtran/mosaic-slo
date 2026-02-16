<?php
/**
 * DEPRECATED: This file is no longer used.
 * 
 * Pages should use ThemeLoader directly instead:
 * 
 * $theme->showFooter($context);
 * 
 * See header.php for full pattern.
 */

trigger_error(
    'footer.php is deprecated. Use $theme->showFooter($context) instead.',
    E_USER_DEPRECATED
);
