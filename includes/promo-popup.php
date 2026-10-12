<?php
return;

$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

$isLoggedIn = false;
$sessionActive = false;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

$sessionActive = (session_status() === PHP_SESSION_ACTIVE);

if ($sessionActive) {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $isLoggedIn = true;
    }
    if (isset($_SESSION['site_admin_logged_in']) && $_SESSION['site_admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}

if ($isLoggedIn && $sessionActive) {
    return;
}

$jsIsLoggedIn = $isLoggedIn ? 'true' : 'false';

$currentYear = date('Y');
$currentMonth = date('n');
$nextDecember = ($currentMonth >= 12) ? ($currentYear + 1) . '-12-31' : $currentYear . '-12-31';
?>
<div id="promoPopup" class="promo-popup-overlay" style="display: none;">
    <div class="promo-popup-container">
        <button class="promo-popup-close" id="closePromoPopup" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="promo-popup-content">
            <div class="promo-popup-icon">
                <i class="fas fa-gift"></i>
            </div>
            <h2 class="promo-popup-title">Special Offer!</h2>
            <p class="promo-popup-subtitle">Get 1 Year Free Professional Subscription</p>
            <div class="promo-popup-highlight">
                <span class="promo-price">₹199</span>
                <span class="promo-free">FREE</span>
            </div>
            <p class="promo-popup-description">
                Register now and get the <strong>Professional Plan</strong> absolutely free for 1 year! 
                This exclusive offer is valid until <strong>December <?php echo date('Y', strtotime($nextDecember)); ?></strong>.
            </p>
            <div class="promo-popup-cta">
                <a href="<?php echo $basePath; ?>admin/register.php" class="btn btn-promo-primary">
                    <i class="fas fa-rocket me-2"></i>Claim Your Free Year
                </a>
                <button class="btn btn-promo-secondary" id="dismissPromoPopup">
                    Maybe Later
                </button>
            </div>
            <p class="promo-popup-note">
                <small>Limited time offer. Terms and conditions apply.</small>
            </p>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const PHP_IS_LOGGED_IN = <?php echo $jsIsLoggedIn; ?>;
    
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = "expires=" + date.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";
        console.log('Cookie set:', name, '=', value, 'expires in', days, 'days');
    }
    
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    function deleteCookie(name) {
        document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    }
    
    const POPUP_DISMISSED_COOKIE = 'promo_popup_dismissed';
    const OFFER_SOURCE_COOKIE = 'promo_offer_source';
    const COOKIE_EXPIRY_DAYS = 1;
    
    function shouldShowPopup() {
        console.log('Checking if popup should show...');
        console.log('PHP_IS_LOGGED_IN:', PHP_IS_LOGGED_IN);
        console.log('dismissed cookie:', getCookie(POPUP_DISMISSED_COOKIE));
        
        if (PHP_IS_LOGGED_IN === true) {
            console.log('Popup blocked: User is logged in (from PHP)');
            return false;
        }
        
        const dismissed = getCookie(POPUP_DISMISSED_COOKIE);
        if (dismissed) {
            console.log('Popup blocked: Popup was dismissed (cookie exists)');
            return false;
        }
        
        console.log('Popup should show - all checks passed');
        return true;
    }
    
    function dismissPopup() {
        const timestamp = Date.now().toString();
        setCookie(POPUP_DISMISSED_COOKIE, timestamp, COOKIE_EXPIRY_DAYS);
        console.log('Popup dismissed - Cookie set:', POPUP_DISMISSED_COOKIE, '=', timestamp);
        console.log('All cookies:', document.cookie);
        const popup = document.getElementById('promoPopup');
        if (popup) {
            popup.style.display = 'none';
            popup.classList.remove('show');
        }
    }
    
    function setOfferSourceCookie() {
        setCookie(OFFER_SOURCE_COOKIE, 'promo_popup', 7);
    }
    
    function showPopup() {
        const popup = document.getElementById('promoPopup');
        if (!popup) {
            console.log('Popup element not found in showPopup()');
            return;
        }
        
        console.log('showPopup() called');
        
        if (!shouldShowPopup()) {
            console.log('Popup blocked by shouldShowPopup() check');
            popup.style.display = 'none';
            popup.classList.remove('show');
            return;
        }
        
        console.log('All checks passed, showing popup...');
        
        setTimeout(function() {
            if (shouldShowPopup()) {
                console.log('Final check passed, displaying popup');
                popup.style.display = 'flex';
                popup.style.visibility = 'visible';
                setTimeout(function() {
                    popup.classList.add('show');
                    console.log('Popup should now be visible');
                }, 10);
            } else {
                console.log('Final check failed, hiding popup');
                popup.style.display = 'none';
                popup.classList.remove('show');
            }
        }, 1000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('promoPopup');
        
        if (!popup) {
            console.log('Popup element not found in DOM');
            return;
        }
        
        console.log('Popup element found, initializing...');
        
        if (PHP_IS_LOGGED_IN === true) {
            popup.style.display = 'none';
            popup.style.visibility = 'hidden';
            popup.classList.remove('show');
            console.log('Popup hidden immediately: User logged in');
            console.log('PHP_IS_LOGGED_IN:', PHP_IS_LOGGED_IN);
            return;
        }
        
        const closeBtn = document.getElementById('closePromoPopup');
        const dismissBtn = document.getElementById('dismissPromoPopup');
        const registerBtn = document.querySelector('.btn-promo-primary');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                dismissPopup();
            });
        }
        
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                dismissPopup();
            });
        }
        
        if (registerBtn) {
            registerBtn.addEventListener('click', function() {
                setOfferSourceCookie();
            });
        }
        
        if (popup) {
            popup.addEventListener('click', function(e) {
                if (e.target === popup) {
                    dismissPopup();
                }
            });
        }
        
        showPopup();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const popup = document.getElementById('promoPopup');
            if (popup && popup.classList.contains('show')) {
                dismissPopup();
            }
        }
    });
})();
</script>