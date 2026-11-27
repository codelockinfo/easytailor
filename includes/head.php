<?php
/**
 * Global Head Partial for EasyTailor Frontend
 * Renders Dynamic Favicon Links and Custom Head Scripts from Site Settings.
 * Include this file inside the <head> tag of any public page.
 */

if (!function_exists('render_favicon_links')) {
    if (file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    } elseif (file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    }
}

// 1. Dynamic Favicon Links (configured in Site Admin -> Global Settings -> SEO & Identity)
if (function_exists('render_favicon_links')) {
    echo render_favicon_links();
}

// 2. Dynamic Custom Head Scripts (configured in Site Admin -> Global Settings -> Scripts & Advanced)
if (function_exists('render_custom_head_scripts')) {
    echo render_custom_head_scripts();
}
?>
