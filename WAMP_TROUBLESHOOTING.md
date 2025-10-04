# WAMP Server Troubleshooting Guide

## Internal Server Error - Solutions

### Issue: Internal Server Error after .htaccess changes

The Internal Server Error is likely caused by incompatible `.htaccess` directives with your WAMP server configuration.

## Quick Fixes

### 1. Test with Minimal .htaccess

If you're still getting errors, try this minimal `.htaccess`:

```apache
# Minimal .htaccess for WAMP
RewriteEngine On
ErrorDocument 404 /tailoring/404.php
```

### 2. Disable .htaccess Temporarily

To test if `.htaccess` is the issue:

1. Rename `.htaccess` to `.htaccess.disabled`
2. Try accessing your site
3. If it works, the issue is with `.htaccess`

### 3. Check WAMP Apache Modules

Ensure these modules are enabled in WAMP:

1. Open WAMP menu
2. Go to Apache → Apache modules
3. Enable these modules:
   - `rewrite_module`
   - `headers_module` (optional)

### 4. Check PHP Configuration

Verify PHP settings in `php.ini`:

```ini
# File uploads
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# Memory and execution time
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# Session settings
session.cookie_httponly = 1
session.use_only_cookies = 1
```

## Step-by-Step Troubleshooting

### Step 1: Check Apache Error Log

1. Open WAMP menu
2. Go to Apache → Apache error log
3. Look for recent errors related to your site

### Step 2: Test Basic PHP

Create a test file `test.php`:

```php
<?php
phpinfo();
?>
```

Access it via `http://localhost/tailoring/test.php`

### Step 3: Test Database Connection

Create a test file `db_test.php`:

```php
<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=tailoring_management', 'root', '');
    echo "Database connection successful!";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
```

### Step 4: Check File Permissions

Ensure your project folder has proper permissions:
- Right-click on your project folder
- Properties → Security
- Ensure "Everyone" has read/execute permissions

## Common WAMP Issues

### Issue 1: mod_rewrite not working

**Solution:**
1. WAMP menu → Apache → Apache modules
2. Check `rewrite_module`
3. Restart Apache

### Issue 2: PHP extensions missing

**Solution:**
1. WAMP menu → PHP → PHP extensions
2. Enable: `php_pdo`, `php_pdo_mysql`, `php_gd2`, `php_mbstring`

### Issue 3: Virtual host issues

**Solution:**
Create a virtual host in `httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "c:/wamp64/www/tailoring"
    ServerName tailoring.local
    <Directory "c:/wamp64/www/tailoring">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Issue 4: Port conflicts

**Solution:**
1. WAMP menu → Apache → Service administration
2. Test port 80
3. If occupied, change to port 8080

## Alternative .htaccess Configurations

### For Apache 2.4+ (WAMP 3.x)

```apache
RewriteEngine On
ErrorDocument 404 /tailoring/404.php

# Protect sensitive files
<Files "*.sql">
    Require all denied
</Files>

<Files "config.php">
    Require all denied
</Files>

<Files "database.php">
    Require all denied
</Files>
```

### For Apache 2.2 (WAMP 2.x)

```apache
RewriteEngine On
ErrorDocument 404 /tailoring/404.php

# Protect sensitive files
<Files "*.sql">
    Order allow,deny
    Deny from all
</Files>

<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

<Files "database.php">
    Order allow,deny
    Deny from all
</Files>
```

## WAMP-Specific Configuration

### Enable Required Modules

1. **rewrite_module** - For URL rewriting
2. **headers_module** - For security headers (optional)
3. **deflate_module** - For compression (optional)
4. **expires_module** - For caching (optional)

### PHP Settings for WAMP

Edit `php.ini` (WAMP menu → PHP → php.ini):

```ini
# Increase limits for file uploads
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# Increase memory and execution time
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# Session security
session.cookie_httponly = 1
session.use_only_cookies = 1
session.cookie_secure = 0

# Error reporting (for development)
display_errors = On
log_errors = On
```

## Testing Your Setup

### 1. Basic Test

Access: `http://localhost/tailoring/`

Should show your application or login page.

### 2. Database Test

Access: `http://localhost/tailoring/db_test.php`

Should show "Database connection successful!"

### 3. PHP Info Test

Access: `http://localhost/tailoring/test.php`

Should show PHP configuration.

## If Problems Persist

### Option 1: Fresh WAMP Installation

1. Backup your project files
2. Uninstall WAMP
3. Download latest WAMP
4. Install and configure
5. Restore project files

### Option 2: Use XAMPP Instead

XAMPP is often more compatible:

1. Download XAMPP
2. Install to `C:\xampp\`
3. Place project in `C:\xampp\htdocs\tailoring\`
4. Start Apache and MySQL

### Option 3: Use Built-in PHP Server

For testing only:

```bash
cd C:\wamp64\www\tailoring
php -S localhost:8000
```

Access via: `http://localhost:8000`

## Getting Help

### Check Logs

1. **Apache Error Log**: WAMP menu → Apache → Apache error log
2. **PHP Error Log**: WAMP menu → PHP → PHP error log
3. **MySQL Error Log**: WAMP menu → MySQL → MySQL error log

### Common Error Messages

- **500 Internal Server Error**: Usually `.htaccess` or PHP syntax error
- **403 Forbidden**: File permission issue
- **404 Not Found**: File doesn't exist or URL rewriting issue
- **Database connection error**: MySQL not running or wrong credentials

### Support Resources

- [WAMP Official Documentation](http://www.wampserver.com/en/)
- [Apache Documentation](https://httpd.apache.org/docs/)
- [PHP Documentation](https://www.php.net/docs.php)
