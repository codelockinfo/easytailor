<?php
/**
 * Language Switcher UI Component
 * Tailoring Management System
 */

// Include language loader if not already included
if (!class_exists('LanguageLoader')) {
    require_once __DIR__ . '/language_loader.php';
}

$loader = LanguageLoader::getInstance();
$currentLang = $loader->getCurrentLanguage();
$supportedLanguages = $loader->getSupportedLanguages();
$currentLangName = $loader->getCurrentLanguageName();
$currentFlag = $loader->getLanguageFlag();
?>

<!-- Language Switcher Component -->
<div class="language-switcher">
    <div class="dropdown">
        <button class="btn btn-outline-primary dropdown-toggle language-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-globe me-1"></i>
            <span class="current-language">
                <?php echo $currentFlag . ' ' . strtoupper($currentLang); ?>
            </span>
        </button>
        <ul class="dropdown-menu language-dropdown">
            <?php foreach ($supportedLanguages as $langCode => $langName): ?>
                <?php 
                $flag = $loader->getLanguageFlag($langCode);
                $isActive = ($langCode === $currentLang) ? 'active' : '';
                ?>
                <li>
                    <a class="dropdown-item language-option <?php echo $isActive; ?>" 
                       href="#" 
                       data-lang="<?php echo $langCode; ?>"
                       onclick="switchLanguage('<?php echo $langCode; ?>')">
                        <span class="language-flag"><?php echo $flag; ?></span>
                        <span class="language-name"><?php echo $langName; ?></span>
                        <?php if ($isActive): ?>
                            <i class="fas fa-check ms-auto text-success"></i>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
.language-switcher {
    position: relative;
}

.language-btn {
    min-width: 80px;
    font-size: 0.9rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.language-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.language-dropdown {
    min-width: 200px;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border: 1px solid rgba(0, 0, 0, 0.08);
    padding: 8px 0;
}

.language-option {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    transition: all 0.2s ease;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    color: #333;
    text-decoration: none;
}

.language-option:hover {
    background-color: #f8f9fa;
    color: #007bff;
    transform: translateX(4px);
}

.language-option.active {
    background-color: #e3f2fd;
    color: #1976d2;
    font-weight: 500;
}

.language-flag {
    font-size: 1.2rem;
    margin-right: 10px;
    width: 24px;
    text-align: center;
}

.language-name {
    flex: 1;
    font-size: 0.9rem;
}

.current-language {
    font-weight: 500;
}

/* RTL Support */
[dir="rtl"] .language-option:hover {
    transform: translateX(-4px);
}

[dir="rtl"] .language-flag {
    margin-right: 0;
    margin-left: 10px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .language-btn {
        min-width: 60px;
        font-size: 0.8rem;
        padding: 6px 10px;
    }
    
    .language-dropdown {
        min-width: 180px;
    }
    
    .language-option {
        padding: 8px 12px;
    }
    
    .language-name {
        font-size: 0.85rem;
    }
}

/* Animation for language switch */
.language-switching {
    opacity: 0.7;
    pointer-events: none;
}

.language-switching .language-btn::after {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
/**
 * Switch language function
 */
function switchLanguage(langCode) {
    // Add loading state
    const switcher = document.querySelector('.language-switcher');
    switcher.classList.add('language-switching');
    
    // Get current URL and add/update lang parameter
    const url = new URL(window.location);
    url.searchParams.set('lang', langCode);
    
    // Redirect to new URL with language parameter
    window.location.href = url.toString();
}

/**
 * Initialize language switcher
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for language options
    const languageOptions = document.querySelectorAll('.language-option');
    
    languageOptions.forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const langCode = this.getAttribute('data-lang');
            switchLanguage(langCode);
        });
    });
    
    // Add keyboard navigation
    const languageBtn = document.querySelector('.language-btn');
    const languageDropdown = document.querySelector('.language-dropdown');
    
    if (languageBtn && languageDropdown) {
        languageBtn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
        
        // Handle arrow key navigation in dropdown
        languageDropdown.addEventListener('keydown', function(e) {
            const options = Array.from(this.querySelectorAll('.language-option'));
            const currentIndex = options.indexOf(document.activeElement);
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    const nextIndex = (currentIndex + 1) % options.length;
                    options[nextIndex].focus();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    const prevIndex = currentIndex === 0 ? options.length - 1 : currentIndex - 1;
                    options[prevIndex].focus();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (document.activeElement.classList.contains('language-option')) {
                        document.activeElement.click();
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    languageBtn.focus();
                    break;
            }
        });
    }
});

/**
 * Auto-save language preference
 */
function saveLanguagePreference(langCode) {
    // Save to localStorage for persistence
    localStorage.setItem('preferred_language', langCode);
    
    // Also save to session via AJAX if needed
    fetch('lang/save_language.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            language: langCode
        })
    }).catch(error => {
        console.log('Language preference saved locally');
    });
}

/**
 * Get current language from URL or default
 */
function getCurrentLanguage() {
    const urlParams = new URLSearchParams(window.location.search);
    const langFromUrl = urlParams.get('lang');
    if (langFromUrl) {
        return langFromUrl;
    }
    
    // Check if there's a language indicator in the current language display
    const currentLangElement = document.querySelector('.current-language');
    if (currentLangElement) {
        const text = currentLangElement.textContent.trim();
        const match = text.match(/[A-Z]{2}/);
        if (match) {
            return match[0].toLowerCase();
        }
    }
    
    // Default fallback
    return 'en';
}

/**
 * Load saved language preference
 */
function loadLanguagePreference() {
    const savedLang = localStorage.getItem('preferred_language');
    if (savedLang && savedLang !== getCurrentLanguage()) {
        // Only switch if different from current
        switchLanguage(savedLang);
    }
}

// Load preference on page load
document.addEventListener('DOMContentLoaded', loadLanguagePreference);
</script>
