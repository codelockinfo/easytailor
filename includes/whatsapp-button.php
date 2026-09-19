<?php

$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

$whatsapp_phone = "917600464414";
$whatsapp_url = "https://wa.me/" . $whatsapp_phone;
?>
<div class="sticky-whatsapp-btn sticky-cta-left">
    <a href="<?php echo $whatsapp_url; ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-float-btn" aria-label="Chat on WhatsApp" title="Chat with us on WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
</div>


