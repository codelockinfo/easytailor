<?php
/**
 * WhatsApp Sticky Button Include
 * Include this file on public pages only (not on admin/siteadmin pages)
 */

// Set image path
$image_path = 'assets/images/whatsapp-icon.png';

// Check if local image exists, otherwise use CDN fallback
$root_path = __DIR__ . '/..';
$local_image = $root_path . '/assets/images/whatsapp-icon.png';
$whatsapp_icon = file_exists($local_image) 
    ? $image_path 
    : 'https://cdn-icons-png.flaticon.com/512/3670/3670051.png';
?>
<!-- Sticky WhatsApp Button -->
<div class="sticky-cta-left">
    <a href="https://wa.me/7600464414" target="_blank" class="btn btn-primary btn-floating">
        <img src="<?php echo $whatsapp_icon; ?>" alt="WhatsApp" class="whatsapp-icon" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/3670/3670051.png';">
        <span></span>
    </a>
</div>

