<?php
/**
 * WhatsApp Sticky Button Include
 * Include this file on public pages only (not on admin/siteadmin pages)
 */

// Get the calling file's directory to determine correct image path
$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
$calling_file = $backtrace[0]['file'] ?? '';
$calling_dir = dirname($calling_file);
$calling_dir_normalized = str_replace('\\', '/', $calling_dir);

// Check if calling from admin directory
$is_admin = (strpos($calling_dir_normalized, '/admin/') !== false || 
             strpos($calling_dir_normalized, '\\admin\\') !== false);

// Set image path based on location
$image_path = $is_admin ? '../assets/images/whatsapp-icon.png' : 'assets/images/whatsapp-icon.png';

// Check if local image exists, otherwise use CDN fallback
$root_path = $is_admin ? dirname(__DIR__) : __DIR__ . '/..';
$local_image = $root_path . '/assets/images/whatsapp-icon.png';
$whatsapp_icon = file_exists($local_image) 
    ? $image_path 
    : 'https://cdn-icons-png.flaticon.com/512/3670/3670051.png';
?>
<!-- Sticky WhatsApp Button -->
<div class="sticky-cta-left">
    <a href="https://wa.me/7600464414" target="_blank" class="btn btn-primary btn-floating">
        <img src="<?php echo $whatsapp_icon; ?>" alt="WhatsApp" class="whatsapp-icon">
        <span></span>
    </a>
</div>

