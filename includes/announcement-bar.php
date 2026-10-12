<?php
$announcement = [
    'message' => 'FREE TO USE, FREE REGISTRATION',
    'link' => null,
    'link_text' => 'Register Now',
    'show_close_button' => true,
    'bg_color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'text_color' => '#ffffff',
    'icon' => 'fa-gift',
    'enabled' => true
];

if (isset($announcement['enabled']) && $announcement['enabled'] === false) {
    return;
}
?>
<div class="announcement-bar" id="announcementBar" style="margin: 0; padding: 0;">
    <div class="container-fluid" style="margin: 0; padding: 0;">
        <div class="row align-items-center" style="margin: 0;">
            <div class="col-12" style="margin: 0; padding: 0;">
                <div class="announcement-content d-flex align-items-center justify-content-center">
                    <?php if (!empty($announcement['icon'])): ?>
                        <i class="fas <?php echo htmlspecialchars($announcement['icon']); ?> announcement-icon me-2"></i>
                    <?php endif; ?>
                    <span class="announcement-text"><?php echo htmlspecialchars($announcement['message']); ?></span>
                    <span class="announcement-click-hint ms-2">Click Here</span>
                    <?php if ($announcement['show_close_button']): ?>
                        <button class="announcement-close ms-auto" aria-label="Close announcement" id="closeAnnouncement">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.announcement-bar {
    background: <?php echo $announcement['bg_color']; ?>; 
    color: <?php echo $announcement['text_color']; ?>;
    padding: 0 !important;
    margin: 0 !important;
    position: fixed;
    top: 0 !important;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 1060;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}


.announcement-bar .container-fluid {
    padding: 0 !important;
    margin: 0 !important;
}

.announcement-bar .row {
    margin: 0 !important;
}

.announcement-bar .col-12 {
    padding: 0 !important;
    margin: 0 !important;
}

.announcement-bar.hidden {
    display: none;
}

.announcement-content {
    position: relative;
    padding: 0.75rem 1rem;
    margin: 0;
    justify-content: center;
    align-items: center;
    width: 100%;
}

.announcement-content > *:not(.announcement-close) {
    flex: 0 0 auto;
}

.announcement-icon {
    font-size: 1.1rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.announcement-text {
    font-size: 0.95rem;
    font-weight: 500;
    margin: 0;
    text-align: center;
}

.announcement-click-hint {
    font-size: 0.85rem;
    font-weight: 600;
    opacity: 0.8;
    text-decoration: underline;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.3s ease;
}

.announcement-click-hint:hover {
    opacity: 1;
    transform: scale(1.05);
}

.announcement-link {
    color: <?php echo $announcement['text_color']; ?> !important;
    text-decoration: none;
    font-weight: 600;
    padding: 0.4rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 5px;
    transition: all 0.3s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
}

.announcement-link:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
}

.announcement-close {
    background: transparent;
    border: none;
    color: <?php echo $announcement['text_color']; ?>;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.3s ease;
    opacity: 0.8;
    margin-left: auto;
    position: absolute;
    right: 1rem;
    z-index: 10;
}

.announcement-close:hover {
    opacity: 1;
}

.announcement-close:hover i {
    transform: rotate(90deg);
}

.announcement-close i {
    display: inline-block;
    transition: transform 0.3s ease;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .announcement-content {
        flex-direction: row;
        gap: 0.5rem;
        text-align: center;
        flex-wrap: wrap;
        justify-content: center;
        padding-right: 3rem; /* Space for close button */
    }
    
    .announcement-text {
        font-size: 0.85rem;
        margin: 0;
    }
    
    .announcement-click-hint {
        font-size: 0.75rem;
    }
    
    .announcement-icon {
        font-size: 1rem;
    }
    
    .announcement-close {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        margin: 0;
    }
    
    .announcement-close:hover i {
        transform: rotate(90deg);
    }
}

@media (max-width: 480px) {
    .announcement-content {
        padding: 0.6rem 0.75rem;
        padding-right: 2.5rem; /* Space for close button */
    }
    
    .announcement-text {
        font-size: 0.8rem;
    }
    
    .announcement-click-hint {
        font-size: 0.7rem;
    }
    
    .announcement-icon {
        font-size: 0.9rem;
    }
    
    .announcement-close {
        right: 0.5rem;
        font-size: 1rem;
        padding: 0.2rem 0.4rem;
    }
}

@media (max-width: 375px) {
    .announcement-content {
        padding: 0.6rem 0.75rem;
        padding-right: 2rem; 
    }
    
    .announcement-text {
        font-size: 0.6rem;
    }
    
    .announcement-click-hint {
        font-size: 0.8rem;
    }
    
    .announcement-icon {
        font-size: 0.8rem;
    }
    
    .announcement-close {
        right: 0.5rem;
        font-size: 1rem;
        padding: 0.2rem 0.4rem;
    }
    .announcement-content{
        gap: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const announcementBar = document.getElementById('announcementBar');
    const body = document.body;
    
    if (announcementBar && !announcementBar.classList.contains('hidden')) {
        body.classList.add('announcement-visible');
        adjustNavbarPosition();
    }
    
    function adjustNavbarPosition() {
        const navbar = document.querySelector('.navbar.fixed-top');
        if (navbar) {
            if (announcementBar && !announcementBar.classList.contains('hidden')) {
                const announcementHeight = announcementBar.offsetHeight;
                navbar.style.top = announcementHeight + 'px';
                navbar.style.position = 'fixed';
                navbar.style.marginTop = '0';
            } else {
                navbar.style.top = '0';
                navbar.style.position = 'fixed';
                navbar.style.marginTop = '0';
            }
        }
    }
    
    adjustNavbarPosition();
    
    const closeButton = document.getElementById('closeAnnouncement');
    if (closeButton) {
        closeButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (announcementBar) {
                announcementBar.style.transition = 'all 0.3s ease';
                announcementBar.style.opacity = '0';
                announcementBar.style.transform = 'translateY(-100%)';
                
                setTimeout(function() {
                    announcementBar.classList.add('hidden');
                    body.classList.remove('announcement-visible');
                    adjustNavbarPosition();
                }, 300);
            }
        });
    }
    
    const clickNowHint = document.querySelector('.announcement-click-hint');
    if (clickNowHint) {
        clickNowHint.style.cursor = 'pointer';
        clickNowHint.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            window.location.href = './admin/register';
        });
    }
    
    window.addEventListener('resize', adjustNavbarPosition);
});
</script>

  