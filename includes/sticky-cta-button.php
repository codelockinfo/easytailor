<?php
/**
 * Sticky CTA Button Component
 * Reusable "Get Started" sticky button for all user-facing pages
 * Include this file on public pages only (not on admin/siteadmin pages)
 */

// Determine base path for links based on current directory
$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';
?>
<!-- Sticky CTA Button -->
<div class="sticky-cta">
    <a href="<?php echo $basePath; ?>admin/register.php" class="btn btn-primary btn-floating sticky-all-pages-button">
        <i class="fas fa-rocket"></i>
        <span>Get Started</span>
    </a>
</div>

