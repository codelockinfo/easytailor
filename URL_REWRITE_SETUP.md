# URL Rewrite Setup - Remove .php Extension

This guide explains how the URL rewrite functionality works and how to verify it's working correctly.

## What Was Implemented

The `.htaccess` file has been updated to:
1. **Redirect** URLs with `.php` extension to clean URLs (301 permanent redirect)
   - Example: `/admin/dashboard.php` → `/admin/dashboard`
2. **Rewrite** clean URLs to include `.php` extension internally
   - Example: `/admin/dashboard` → `/admin/dashboard.php` (internally)

## Requirements

For this to work, you need:

1. **Apache mod_rewrite enabled**
   - Most shared hosting providers have this enabled by default
   - On WAMP/XAMPP, you may need to enable it

2. **AllowOverride All** (or at least `AllowOverride FileInfo`)
   - Most hosting providers allow this
   - On local development, check your Apache `httpd.conf`

## Testing

### Step 1: Check if mod_rewrite is enabled

Visit: `http://localhost/tailoring/test_rewrite.php`

This will show:
- Whether mod_rewrite is enabled
- Server information
- Test links to verify URL rewriting

### Step 2: Test Clean URLs

Try these URLs (without `.php`):
- `http://localhost/tailoring/admin/dashboard`
- `http://localhost/tailoring/admin/login`
- `http://localhost/tailoring/admin/customers`
- `http://localhost/tailoring/admin/profile`

All of these should work **without** the `.php` extension.

### Step 3: Test Redirect

Try accessing with `.php`:
- `http://localhost/tailoring/admin/dashboard.php`

You should be **redirected** to:
- `http://localhost/tailoring/admin/dashboard`

## Local Setup (WAMP/XAMPP)

If it's not working locally:

### Enable mod_rewrite in WAMP:

1. Click WAMP icon in system tray
2. Go to **Apache** → **Apache Modules**
3. Check **rewrite_module** (it should have a checkmark)
4. If it wasn't checked, restart WAMP services

### Check AllowOverride:

1. Open `httpd.conf` (usually in `C:\wamp64\bin\apache\apache2.4.XX\conf\`)
2. Find your `<Directory>` directive for `C:/wamp64/www` or your document root
3. Ensure it has: `AllowOverride All`
4. Restart Apache

Example:
```apache
<Directory "C:/wamp64/www">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

## Live Server Setup

Most shared hosting providers (cPanel, Plesk, etc.) already have:
- ✅ mod_rewrite enabled
- ✅ AllowOverride configured

**No server configuration changes needed** - just upload the `.htaccess` file!

### If it doesn't work on live server:

1. **Check with your host** - ask if mod_rewrite is enabled
2. **Verify .htaccess is uploaded** - some FTP clients hide files starting with "."
3. **Check file permissions** - `.htaccess` should be readable (644 permissions)

## What URLs Are Excluded

These URLs **keep** their `.php` extension (for API compatibility):
- `/ajax/*` - All AJAX endpoints
- `/admin/ajax/*` - Admin AJAX endpoints

Static files (images, CSS, JS) are not affected.

## Troubleshooting

### "Not Found" Error

If you get a 404 error when accessing clean URLs:

1. Check if mod_rewrite is enabled (use `test_rewrite.php`)
2. Check Apache error logs for rewrite errors
3. Verify `.htaccess` file is in the root directory
4. Check if there are syntax errors in `.htaccess`

### Infinite Redirect Loop

If you get a redirect loop:

1. Clear your browser cache
2. Check if there are conflicting rewrite rules
3. Verify the redirect rule matches correctly

### Works Locally but Not on Server

1. Verify `.htaccess` is uploaded to the server
2. Check server error logs
3. Contact your hosting provider to confirm mod_rewrite is enabled

## How It Works

### Redirect Rule (Line 18):
```
RewriteRule ^(.+?)\.php$ /$1 [R=301,L,QSA]
```
- Matches any URL ending in `.php`
- Redirects to the same URL without `.php`
- `R=301` = Permanent redirect
- `QSA` = Preserves query strings

### Rewrite Rule (Line 28):
```
RewriteRule ^(.*)$ $1.php [L]
```
- Matches any URL that doesn't exist as a file/directory
- Internally rewrites to add `.php` extension
- `L` = Last rule (stops processing)

## Benefits

1. ✅ **Cleaner URLs** - No `.php` extension visible
2. ✅ **Better SEO** - Search engines prefer clean URLs
3. ✅ **Technology Hiding** - Doesn't reveal PHP usage
4. ✅ **User-Friendly** - Easier to remember and type
5. ✅ **Backward Compatible** - Old `.php` URLs still work (redirect)

## Notes

- AJAX endpoints intentionally keep `.php` for compatibility
- Query strings are preserved in redirects
- The solution works on both local and live servers
- No code changes needed - all handled by `.htaccess`

