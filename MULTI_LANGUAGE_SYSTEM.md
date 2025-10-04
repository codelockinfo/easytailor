# Multi-Language Translation System
## Tailoring Management System

A comprehensive multi-language translation system built with PHP and JSON files for storing translations.

## 🚀 Features

### ✅ **Complete Language Support**
- **13 Indian Languages**: Hindi, Gujarati, Marathi, Tamil, Telugu, Kannada, Malayalam, Bengali, Punjabi, Urdu, Odia, Assamese
- **English**: Default language
- **Easy Extension**: Add new languages by creating JSON files

### ✅ **Smart Language Detection**
- **URL Parameter**: `?lang=hi`
- **Session Storage**: Remembers user preference
- **Browser Detection**: Auto-detects from Accept-Language header
- **Fallback System**: Defaults to English if language not found

### ✅ **Performance Optimized**
- **Session Caching**: Translations cached in session
- **Lazy Loading**: Only loads when needed
- **Memory Efficient**: Singleton pattern for loader

### ✅ **SEO Friendly**
- **Dynamic HTML Lang**: `<html lang="hi">`
- **Text Direction**: Supports RTL languages
- **URL Structure**: Language codes in URLs

## 📁 File Structure

```
lang/
├── en.json                 # English translations
├── hi.json                 # Hindi translations
├── gu.json                 # Gujarati translations
├── language_loader.php     # Core translation system
├── language_switcher.php   # UI component
└── save_language.php       # AJAX endpoint
```

## 🔧 Usage

### **Basic Translation**
```php
// Simple translation
echo __t('welcome');                    // "Welcome" / "स्वागत है" / "સ્વાગત છે"

// With fallback
echo __t('custom_key', 'Default Text'); // Returns "Default Text" if key not found
```

### **Language Detection**
```php
// Get current language
$currentLang = getCurrentLanguage();    // "en", "hi", "gu", etc.

// Get language name
$langName = getCurrentLanguageName();   // "English", "हिन्दी", "ગુજરાતી"

// Get supported languages
$languages = getSupportedLanguages();   // Array of all languages
```

### **Language Switching**
```php
// Set language programmatically
setLanguage('hi');                      // Switch to Hindi

// Get language flag
$flag = getLanguageFlag('gu');          // "🇮🇳"
```

### **HTML Integration**
```php
// Dynamic HTML attributes
<html lang="<?php echo getHtmlLang(); ?>" dir="<?php echo getTextDirection(); ?>">
```

## 🎨 UI Components

### **Language Switcher**
```php
// Include the language switcher
<?php include 'lang/language_switcher.php'; ?>
```

**Features:**
- ✅ **Dropdown Interface**: Clean Bootstrap dropdown
- ✅ **Flag Icons**: Visual language identification
- ✅ **Active State**: Shows current language
- ✅ **Keyboard Navigation**: Full accessibility
- ✅ **Mobile Responsive**: Works on all devices

### **Styling**
- **Modern Design**: Bootstrap 5 compatible
- **Hover Effects**: Smooth animations
- **Loading States**: Visual feedback during switching
- **RTL Support**: Right-to-left language support

## 📊 Language Files Structure

### **English (en.json)**
```json
{
  "welcome": "Welcome",
  "login": "Login",
  "dashboard": "Dashboard",
  "total_orders": "Total Orders",
  "manage_customers": "Manage Customers"
}
```

### **Hindi (hi.json)**
```json
{
  "welcome": "स्वागत है",
  "login": "लॉगिन",
  "dashboard": "डैशबोर्ड",
  "total_orders": "कुल आदेश",
  "manage_customers": "ग्राहक प्रबंधन"
}
```

### **Gujarati (gu.json)**
```json
{
  "welcome": "સ્વાગત છે",
  "login": "લોગિન",
  "dashboard": "ડેશબોર્ડ",
  "total_orders": "કુલ ઓર્ડર",
  "manage_customers": "ગ્રાહકોનું સંચાલન"
}
```

## 🔄 Language Detection Priority

1. **URL Parameter**: `?lang=hi` (Highest priority)
2. **Session Storage**: `$_SESSION['language']`
3. **Browser Header**: `Accept-Language`
4. **Default**: English (`en`)

## 🚀 Integration Examples

### **Page Headers**
```php
// Dynamic page title
$page_title = __t('dashboard');

// HTML attributes
<html lang="<?php echo getHtmlLang(); ?>" dir="<?php echo getTextDirection(); ?>">
```

### **Form Labels**
```php
<label><?php echo __t('email'); ?></label>
<input type="email" placeholder="<?php echo __t('email'); ?>">
```

### **Buttons**
```php
<button type="submit"><?php echo __t('save'); ?></button>
<button type="button"><?php echo __t('cancel'); ?></button>
```

### **Messages**
```php
<div class="alert alert-success">
    <?php echo __t('item_created_successfully'); ?>
</div>
```

## 🎯 Supported Languages

| Code | Language | Native Name | Flag |
|------|----------|-------------|------|
| `en` | English | English | 🇺🇸 |
| `hi` | Hindi | हिन्दी | 🇮🇳 |
| `gu` | Gujarati | ગુજરાતી | 🇮🇳 |
| `mr` | Marathi | मराठी | 🇮🇳 |
| `ta` | Tamil | தமிழ் | 🇮🇳 |
| `te` | Telugu | తెలుగు | 🇮🇳 |
| `kn` | Kannada | ಕನ್ನಡ | 🇮🇳 |
| `ml` | Malayalam | മലയാളം | 🇮🇳 |
| `bn` | Bengali | বাংলা | 🇮🇳 |
| `pa` | Punjabi | ਪੰਜਾਬੀ | 🇮🇳 |
| `ur` | Urdu | اردو | 🇮🇳 |
| `or` | Odia | ଓଡ଼ିଆ | 🇮🇳 |
| `as` | Assamese | অসমীয়া | 🇮🇳 |

## 🔧 Adding New Languages

### **Step 1: Create JSON File**
```bash
# Create new language file
touch lang/ne.json  # For Nepali
```

### **Step 2: Add Translations**
```json
{
  "welcome": "स्वागतम्",
  "login": "लगइन",
  "dashboard": "ड्यासबोर्ड"
}
```

### **Step 3: Update Language Loader**
```php
// Add to $supportedLanguages array in language_loader.php
'ne' => 'नेपाली'
```

### **Step 4: Update Database**
```sql
-- Add to languages table
INSERT INTO `languages` (`code`, `name`, `flag`, `is_default`, `status`) 
VALUES ('ne', 'Nepali', '🇳🇵', 0, 'active');
```

## 🎨 Customization

### **Styling the Language Switcher**
```css
.language-switcher {
    /* Custom styles */
}

.language-btn {
    /* Button styles */
}

.language-dropdown {
    /* Dropdown styles */
}
```

### **Adding Custom Languages**
```php
// In language_loader.php
private $supportedLanguages = [
    'en' => 'English',
    'hi' => 'हिन्दी',
    'custom' => 'Custom Language'  // Add here
];
```

## 🚀 Performance Features

### **Caching System**
- **Session Caching**: Translations stored in `$_SESSION['translations']`
- **Lazy Loading**: Only loads when language changes
- **Memory Efficient**: Singleton pattern prevents multiple instances

### **Optimization Tips**
```php
// Clear cache when needed
$loader = LanguageLoader::getInstance();
$loader->clearCache();

// Preload common translations
$loader->loadTranslations();
```

## 🔍 Debugging

### **Check Current Language**
```php
echo "Current Language: " . getCurrentLanguage();
echo "Language Name: " . getCurrentLanguageName();
```

### **Check Available Translations**
```php
$loader = LanguageLoader::getInstance();
$translations = $loader->translations;
print_r($translations);
```

### **Test Translation**
```php
echo __t('test_key', 'Fallback Text');
```

## 📱 Mobile Support

- **Responsive Design**: Works on all screen sizes
- **Touch Friendly**: Large touch targets
- **Fast Loading**: Optimized for mobile networks
- **Offline Support**: Cached translations work offline

## 🔒 Security Features

- **Input Validation**: Language codes validated
- **File Security**: Only JSON files allowed
- **Session Protection**: Secure session handling
- **XSS Prevention**: All output escaped

## 🌟 Future Enhancements

### **Planned Features**
- [ ] **Admin Panel**: Dynamic translation management
- [ ] **Translation Editor**: In-browser translation editing
- [ ] **Auto-Translation**: Google Translate integration
- [ ] **Pluralization**: Handle plural forms
- [ ] **Context Support**: Context-aware translations
- [ ] **Translation Memory**: Reuse existing translations

### **API Endpoints**
- [ ] **Translation API**: RESTful translation service
- [ ] **Language Detection API**: Automatic language detection
- [ ] **Translation Sync**: Sync translations across instances

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review the language files
3. Test with different browsers
4. Check server logs for errors

---

**The multi-language system is now fully integrated and ready for use!** 🎉
