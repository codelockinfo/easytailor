<?php
/**
 * Multi-Language Translation System
 * Tailoring Management System
 * 
 * Handles language detection, loading, and caching
 */

class LanguageLoader {
    private static $instance = null;
    private $currentLang = 'en';
    private $translations = [];
    private $supportedLanguages = [
        'en' => 'English',
        'hi' => 'हिन्दी',
        'gu' => 'ગુજરાતી',
        'mr' => 'मराठी',
        'ta' => 'தமிழ்',
        'te' => 'తెలుగు',
        'kn' => 'ಕನ್ನಡ',
        'ml' => 'മലയാളം',
        'bn' => 'বাংলা',
        'pa' => 'ਪੰਜਾਬੀ',
        'ur' => 'اردو',
        'or' => 'ଓଡ଼ିଆ',
        'as' => 'অসমীয়া'
    ];
    
    private function __construct() {
        $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Detect user's language preference
     */
    private function detectLanguage() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Priority order: URL parameter > Session > Browser > Default
        if (isset($_GET['lang']) && $this->isLanguageSupported($_GET['lang'])) {
            $this->currentLang = $_GET['lang'];
            $_SESSION['language'] = $this->currentLang;
        } elseif (isset($_SESSION['language']) && $this->isLanguageSupported($_SESSION['language'])) {
            $this->currentLang = $_SESSION['language'];
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = $this->getBrowserLanguage();
            if ($browserLang && $this->isLanguageSupported($browserLang)) {
                $this->currentLang = $browserLang;
                $_SESSION['language'] = $this->currentLang;
            }
        }
    }
    
    /**
     * Get browser language preference
     */
    private function getBrowserLanguage() {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $languages = [];
        
        // Parse Accept-Language header
        preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptLanguage, $matches);
        
        if (count($matches[1])) {
            $languages = array_combine($matches[1], $matches[4]);
            
            foreach ($languages as $lang => $val) {
                $languages[$lang] = ($val === '') ? 1 : (float)$val;
            }
            
            arsort($languages, SORT_NUMERIC);
        }
        
        // Check for supported languages
        foreach ($languages as $lang => $priority) {
            $langCode = strtolower(substr($lang, 0, 2));
            if ($this->isLanguageSupported($langCode)) {
                return $langCode;
            }
        }
        
        return null;
    }
    
    /**
     * Check if language is supported
     */
    private function isLanguageSupported($lang) {
        return array_key_exists($lang, $this->supportedLanguages);
    }
    
    /**
     * Load translations with caching
     */
    private function loadTranslations() {
        // Check if translations are already cached in session
        if (isset($_SESSION['translations'][$this->currentLang])) {
            $this->translations = $_SESSION['translations'][$this->currentLang];
            return;
        }
        
        // Load from JSON file
        $langFile = __DIR__ . '/' . $this->currentLang . '.json';
        
        if (file_exists($langFile)) {
            $jsonContent = file_get_contents($langFile);
            $this->translations = json_decode($jsonContent, true);
            
            // Cache in session for performance
            if (!isset($_SESSION['translations'])) {
                $_SESSION['translations'] = [];
            }
            $_SESSION['translations'][$this->currentLang] = $this->translations;
        } else {
            // Fallback to English if language file doesn't exist
            if ($this->currentLang !== 'en') {
                $this->currentLang = 'en';
                $this->loadTranslations();
            } else {
                $this->translations = [];
            }
        }
    }
    
    /**
     * Get translation for a key
     */
    public function translate($key, $default = null) {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        
        // Return default value or key itself if not found
        return $default !== null ? $default : $key;
    }
    
    /**
     * Get current language code
     */
    public function getCurrentLanguage() {
        return $this->currentLang;
    }
    
    /**
     * Get current language name
     */
    public function getCurrentLanguageName() {
        return $this->supportedLanguages[$this->currentLang] ?? 'English';
    }
    
    /**
     * Get all supported languages
     */
    public function getSupportedLanguages() {
        return $this->supportedLanguages;
    }
    
    /**
     * Set language programmatically
     */
    public function setLanguage($lang) {
        if ($this->isLanguageSupported($lang)) {
            $this->currentLang = $lang;
            $_SESSION['language'] = $lang;
            
            // Clear cached translations for this language
            if (isset($_SESSION['translations'][$lang])) {
                unset($_SESSION['translations'][$lang]);
            }
            
            // Reload translations
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    /**
     * Clear translation cache
     */
    public function clearCache() {
        if (isset($_SESSION['translations'])) {
            unset($_SESSION['translations']);
        }
        $this->loadTranslations();
    }
    
    /**
     * Get language flag emoji
     */
    public function getLanguageFlag($lang = null) {
        $lang = $lang ?: $this->currentLang;
        
        $flags = [
            'en' => '🇺🇸',
            'hi' => '🇮🇳',
            'gu' => '🇮🇳',
            'mr' => '🇮🇳',
            'ta' => '🇮🇳',
            'te' => '🇮🇳',
            'kn' => '🇮🇳',
            'ml' => '🇮🇳',
            'bn' => '🇮🇳',
            'pa' => '🇮🇳',
            'ur' => '🇮🇳',
            'or' => '🇮🇳',
            'as' => '🇮🇳'
        ];
        
        return $flags[$lang] ?? '🇺🇸';
    }
    
    /**
     * Get HTML lang attribute
     */
    public function getHtmlLang() {
        return $this->currentLang;
    }
    
    /**
     * Get direction (ltr/rtl) for current language
     */
    public function getDirection() {
        $rtlLanguages = ['ur', 'ar', 'he', 'fa'];
        return in_array($this->currentLang, $rtlLanguages) ? 'rtl' : 'ltr';
    }
}

/**
 * Global helper function for translations
 */
function __t($key, $default = null) {
    $loader = LanguageLoader::getInstance();
    return $loader->translate($key, $default);
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    $loader = LanguageLoader::getInstance();
    return $loader->getCurrentLanguage();
}

/**
 * Get current language name
 */
function getCurrentLanguageName() {
    $loader = LanguageLoader::getInstance();
    return $loader->getCurrentLanguageName();
}

/**
 * Get supported languages
 */
function getSupportedLanguages() {
    $loader = LanguageLoader::getInstance();
    return $loader->getSupportedLanguages();
}

/**
 * Set language
 */
function setLanguage($lang) {
    $loader = LanguageLoader::getInstance();
    return $loader->setLanguage($lang);
}

/**
 * Get language flag
 */
function getLanguageFlag($lang = null) {
    $loader = LanguageLoader::getInstance();
    return $loader->getLanguageFlag($lang);
}

/**
 * Get HTML lang attribute
 */
function getHtmlLang() {
    $loader = LanguageLoader::getInstance();
    return $loader->getHtmlLang();
}

/**
 * Get text direction
 */
function getTextDirection() {
    $loader = LanguageLoader::getInstance();
    return $loader->getDirection();
}
?>
