<?php
/**
 * Global Page Hero Banner Component
 * Standardized across all admin pages matching Category & Cloth Type design.
 *
 * Usage Example:
 *   $pageHeaderTitle = 'Cloth Type';
 *   $pageHeaderSubtitle = 'Manage your shop cloth types<br>organize and track your tailoring garments.';
 *   include 'includes/page-header.php';
 *
 * Or using the helper:
 *   render_page_header('Cloth Type', 'Manage your shop cloth types<br>organize and track your tailoring garments.');
 */

$bannerTitle = $pageHeaderTitle ?? $heroTitle ?? $page_hero_title ?? (isset($page_title) ? str_ireplace([' Management', ' management'], '', $page_title) : 'Overview');
$bannerSubtitle = $pageHeaderSubtitle ?? $heroSubtitle ?? $page_hero_subtitle ?? 'Manage and organize your tailoring business with ease.';
?>
<!-- Standard Page Hero Header Banner -->
<div class="cat-hero-card mb-3">
    <!-- Tailoring workshop & mannequin full width background image -->
    <div class="position-absolute top-0 start-0 w-100 h-100"
         style="background: url('../assets/images/dashboard/welcome_banner_bg.png') no-repeat right center / cover; z-index: 1;"></div>
    <!-- Soft lavender gradient overlay for text readability on left while full image shows across -->
    <div class="position-absolute top-0 start-0 w-100 h-100"
         style="background: linear-gradient(90deg, #fdf4ff 0%, #f5f3ff 20%, rgba(245, 243, 255, 0.88) 35%, rgba(245, 243, 255, 0.45) 55%, rgba(245, 243, 255, 0.10) 75%, transparent 90%); z-index: 2;"></div>
    <!-- Content text -->
    <div class="position-relative p-3 p-md-4" style="z-index: 3;">
        <h2 class="fw-bold mb-1" style="color: #0f172a; font-size: 1.38rem; letter-spacing: -0.4px;"><?php echo htmlspecialchars($bannerTitle); ?></h2>
        <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.35; max-width: 250px;">
            <?php echo $bannerSubtitle; ?>
        </p>
    </div>
</div>
