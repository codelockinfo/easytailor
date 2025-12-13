<?php
/**
 * Promotional Popup Component
 * Reusable popup offering 1 year free Professional subscription
 * Valid until next December
 */

// Determine base path for links based on current directory
$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

// Check if user is logged in - if yes, don't show popup
// Start session if not already started and headers not sent
$isLoggedIn = false;
$sessionActive = false;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Try to start session only if headers haven't been sent
    @session_start();
}

// Check if session is actually active
$sessionActive = (session_status() === PHP_SESSION_ACTIVE);

// Check if user is logged in - check multiple session variables to be sure
// Only check if session is active AND we can access session data
if ($sessionActive) {
    // Check for regular user login
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $isLoggedIn = true;
    }
    // Also check for site admin
    if (isset($_SESSION['site_admin_logged_in']) && $_SESSION['site_admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}

// Check if user has already claimed the offer (even if not logged in on this device)
$offerClaimed = isset($_COOKIE['offer_claimed']) && $_COOKIE['offer_claimed'] === '1';

// Only block popup if we're CERTAIN user is logged in (session active + user_id exists)
// If session is not active or can't be checked, allow popup to show
if ($isLoggedIn && $sessionActive) {
    return; // Exit early, don't render popup - user is definitely logged in
}

// If offer was claimed, also don't show
if ($offerClaimed) {
    return; // Exit early, don't render popup - offer already claimed
}

// Pass PHP variables to JavaScript for additional client-side check
$jsIsLoggedIn = $isLoggedIn ? 'true' : 'false';
$jsOfferClaimed = $offerClaimed ? 'true' : 'false';

// Calculate next December date
$currentYear = date('Y');
$currentMonth = date('n');
$nextDecember = ($currentMonth >= 12) ? ($currentYear + 1) . '-12-31' : $currentYear . '-12-31';
?>
<!-- Promotional Popup Modal -->
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
    
    // PHP variables passed to JavaScript
    const PHP_IS_LOGGED_IN = <?php echo $jsIsLoggedIn; ?>;
    const PHP_OFFER_CLAIMED = <?php echo $jsOfferClaimed; ?>;
    
    // Cookie helper functions
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
    
    // Cookie keys
    const POPUP_DISMISSED_COOKIE = 'promo_popup_dismissed';
    const OFFER_SOURCE_COOKIE = 'promo_offer_source';
    const OFFER_CLAIMED_COOKIE = 'offer_claimed';
    const COOKIE_EXPIRY_DAYS = 1; // 24 hours = 1 day
    
    function shouldShowPopup() {
        console.log('Checking if popup should show...');
        console.log('PHP_IS_LOGGED_IN:', PHP_IS_LOGGED_IN);
        console.log('PHP_OFFER_CLAIMED:', PHP_OFFER_CLAIMED);
        console.log('offer_claimed cookie:', getCookie(OFFER_CLAIMED_COOKIE));
        console.log('dismissed cookie:', getCookie(POPUP_DISMISSED_COOKIE));
        
        // First check: If user is logged in (from PHP), don't show
        // Only block if PHP definitely says user is logged in
        if (PHP_IS_LOGGED_IN === true) {
            console.log('Popup blocked: User is logged in (from PHP)');
            return false;
        }
        
        // Second check: If offer was claimed (from PHP or cookie), don't show
        if (PHP_OFFER_CLAIMED === true || getCookie(OFFER_CLAIMED_COOKIE) === '1') {
            console.log('Popup blocked: Offer already claimed');
            return false;
        }
        
        // Third check: If popup was dismissed, don't show (for 24 hours)
        const dismissed = getCookie(POPUP_DISMISSED_COOKIE);
        if (dismissed) {
            console.log('Popup blocked: Popup was dismissed (cookie exists)');
            return false;
        }
        
        console.log('Popup should show - all checks passed');
        return true;
    }
    
    function dismissPopup() {
        // Set cookie to expire in 24 hours (1 day)
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
        // Set cookie when user clicks registration from offer
        // This cookie will be available on registration page
        setCookie(OFFER_SOURCE_COOKIE, 'promo_popup', 7); // Valid for 7 days
    }
    
    function showPopup() {
        const popup = document.getElementById('promoPopup');
        if (!popup) {
            console.log('Popup element not found in showPopup()');
            return;
        }
        
        console.log('showPopup() called');
        
        // Double-check before showing
        if (!shouldShowPopup()) {
            console.log('Popup blocked by shouldShowPopup() check');
            popup.style.display = 'none';
            popup.classList.remove('show');
            return;
        }
        
        console.log('All checks passed, showing popup...');
        
        // Small delay for better UX
        setTimeout(function() {
            // Check one more time before showing (in case something changed)
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
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('promoPopup');
        
        if (!popup) {
            console.log('Popup element not found in DOM');
            return;
        }
        
        console.log('Popup element found, initializing...');
        
        // Immediate check: If user is logged in or offer claimed, hide popup immediately
        if (PHP_IS_LOGGED_IN === true || PHP_OFFER_CLAIMED === true || getCookie(OFFER_CLAIMED_COOKIE) === '1') {
            popup.style.display = 'none';
            popup.style.visibility = 'hidden';
            popup.classList.remove('show');
            console.log('Popup hidden immediately: User logged in or offer claimed');
            console.log('PHP_IS_LOGGED_IN:', PHP_IS_LOGGED_IN, 'PHP_OFFER_CLAIMED:', PHP_OFFER_CLAIMED, 'cookie:', getCookie(OFFER_CLAIMED_COOKIE));
            return; // Exit early, don't set up event listeners
        }
        
        const closeBtn = document.getElementById('closePromoPopup');
        const dismissBtn = document.getElementById('dismissPromoPopup');
        const registerBtn = document.querySelector('.btn-promo-primary');
        
        // Close button (X) - set cookie when clicked
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                dismissPopup();
            });
        }
        
        // Dismiss button (Maybe Later) - set cookie when clicked
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                dismissPopup();
            });
        }
        
        // Track when user clicks registration button from offer
        if (registerBtn) {
            registerBtn.addEventListener('click', function() {
                setOfferSourceCookie();
                // Allow navigation to continue
            });
        }
        
        // Close on overlay click - set cookie when clicked
        if (popup) {
            popup.addEventListener('click', function(e) {
                if (e.target === popup) {
                    dismissPopup();
                }
            });
        }
        
        // Show popup (only if all checks pass)
        showPopup();
    });
    
    // Close on Escape key - set cookie when pressed
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

