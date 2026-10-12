<?php
$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';
?>
<div class="sticky-cta">
    <a href="<?php echo $basePath; ?>admin/register.php" class="btn btn-primary btn-floating sticky-all-pages-button">
        <i class="fas fa-rocket"></i>
        <span>Get Started</span>
    </a>
</div>