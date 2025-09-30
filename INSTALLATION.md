# Installation Guide - Tailoring Management System

This guide will help you install and set up the Tailoring Management System on your server.

## Prerequisites

Before installing the system, ensure you have:

- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7+ or MariaDB 10.3+
- **PHP Extensions**: PDO, PDO_MySQL, GD, JSON, MBString

### Checking Requirements

#### PHP Version Check
```bash
php -v
```

#### PHP Extensions Check
```bash
php -m | grep -E "(pdo|mysql|gd|json|mbstring)"
```

## Installation Steps

### Step 1: Download and Extract

1. Download the latest version of the system
2. Extract the files to your web server directory:
   ```bash
   # For Apache (typical locations)
   /var/www/html/tailoring/
   # or
   /var/www/tailoring/
   
   # For XAMPP/WAMP
   C:\xampp\htdocs\tailoring\
   # or
   C:\wamp64\www\tailoring\
   ```

### Step 2: Database Setup

1. **Create Database**
   ```sql
   CREATE DATABASE tailoring_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**
   ```bash
   mysql -u root -p tailoring_management < database/schema.sql
   ```

   Or using phpMyAdmin:
   - Open phpMyAdmin
   - Select the `tailoring_management` database
   - Go to Import tab
   - Choose the `database/schema.sql` file
   - Click Go

### Step 3: Configuration

1. **Database Configuration**
   
   Edit `config/database.php`:
   ```php
   <?php
   class Database {
       private $host = 'localhost';        // Your database host
       private $db_name = 'tailoring_management';  // Database name
       private $username = 'your_db_user'; // Your database username
       private $password = 'your_db_pass'; // Your database password
       // ... rest of the configuration
   }
   ```

2. **Application Configuration**
   
   Edit `config/config.php`:
   ```php
   // Update the application URL
   define('APP_URL', 'http://localhost/tailoring');
   
   // For production, use HTTPS
   // define('APP_URL', 'https://yourdomain.com/tailoring');
   ```

### Step 4: File Permissions

Set appropriate permissions for the upload directories:

```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 uploads/customers/
chmod 755 uploads/orders/
chmod 755 uploads/measurements/
chmod 755 uploads/receipts/
chmod 755 uploads/logos/

# If using Apache, ensure proper ownership
chown -R www-data:www-data uploads/
```

For Windows (XAMPP/WAMP), ensure the web server has write permissions to the upload directories.

### Step 5: Web Server Configuration

#### Apache Configuration

Create a `.htaccess` file in the root directory:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# File upload limits
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
```

#### Nginx Configuration

Add to your server block:
```nginx
location /tailoring {
    try_files $uri $uri/ /tailoring/index.php?$query_string;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Step 6: PHP Configuration

Ensure these PHP settings in your `php.ini`:

```ini
# File uploads
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# Session settings
session.cookie_httponly = 1
session.use_only_cookies = 1
session.cookie_secure = 0  # Set to 1 for HTTPS

# Error reporting (disable in production)
display_errors = Off
log_errors = On

# Timezone
date.timezone = "UTC"
```

### Step 7: Initial Setup

1. **Access the System**
   
   Open your browser and navigate to:
   ```
   http://localhost/tailoring
   ```

2. **Default Login**
   
   Use these credentials for the first login:
   - **Username**: admin
   - **Password**: admin123

3. **Change Default Password**
   
   Immediately change the admin password after first login:
   - Go to Profile → Change Password
   - Set a strong password

4. **Configure Company Settings**
   
   - Go to Settings
   - Update company information
   - Set currency and tax rates
   - Upload company logo

## Post-Installation

### Adding Initial Data

1. **Cloth Types**
   - Go to Cloth Types
   - Add common cloth types (Shirt, Pants, Suit, etc.)
   - Set standard rates for each type

2. **Users**
   - Go to User Management
   - Create additional users with appropriate roles
   - Assign users to different roles (Staff, Tailor, Cashier)

3. **Customers**
   - Start adding your existing customers
   - Import customer data if available

### Security Considerations

1. **Change Default Credentials**
   - Change admin password immediately
   - Create strong passwords for all users

2. **Database Security**
   - Use strong database passwords
   - Limit database user permissions
   - Regular database backups

3. **File Permissions**
   - Ensure proper file permissions
   - Restrict access to sensitive directories

4. **HTTPS (Production)**
   - Use SSL certificates in production
   - Update APP_URL to use HTTPS
   - Set secure session cookies

## Troubleshooting

### Common Installation Issues

#### 1. Database Connection Error
```
Error: Connection error: SQLSTATE[HY000] [1045] Access denied
```

**Solutions:**
- Verify database credentials in `config/database.php`
- Check if MySQL service is running
- Ensure database user has proper permissions

#### 2. File Permission Errors
```
Warning: mkdir(): Permission denied
```

**Solutions:**
```bash
# Set proper permissions
chmod 755 uploads/
chown -R www-data:www-data uploads/
```

#### 3. Session Issues
```
Warning: session_start(): Cannot send session cookie
```

**Solutions:**
- Check PHP session configuration
- Ensure session directory is writable
- Clear browser cookies

#### 4. Upload Issues
```
Error: File upload failed
```

**Solutions:**
- Check PHP upload settings
- Verify upload directory permissions
- Increase upload limits in php.ini

### Debug Mode

Enable debug mode for troubleshooting:

```php
// In config/config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Log Files

Check these locations for error logs:
- **Apache**: `/var/log/apache2/error.log`
- **Nginx**: `/var/log/nginx/error.log`
- **PHP**: Check `php.ini` for `error_log` setting

## Backup and Maintenance

### Database Backup

```bash
# Create backup
mysqldump -u username -p tailoring_management > backup_$(date +%Y%m%d).sql

# Restore backup
mysql -u username -p tailoring_management < backup_20231201.sql
```

### File Backup

```bash
# Backup entire application
tar -czf tailoring_backup_$(date +%Y%m%d).tar.gz /path/to/tailoring/
```

### Regular Maintenance

1. **Database Optimization**
   ```sql
   OPTIMIZE TABLE customers, orders, invoices, expenses;
   ```

2. **Log Cleanup**
   - Regularly clean old log files
   - Archive old session data

3. **Security Updates**
   - Keep PHP and MySQL updated
   - Monitor security advisories

## Production Deployment

### Environment Setup

1. **Use HTTPS**
   ```php
   define('APP_URL', 'https://yourdomain.com/tailoring');
   ```

2. **Disable Debug Mode**
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

3. **Secure Session Settings**
   ```php
   ini_set('session.cookie_secure', 1);
   ini_set('session.cookie_httponly', 1);
   ```

4. **Database Security**
   - Use dedicated database user with limited permissions
   - Enable SSL for database connections
   - Regular security updates

### Performance Optimization

1. **Enable Caching**
   - Use Redis or Memcached for session storage
   - Implement page caching

2. **Database Optimization**
   - Add appropriate indexes
   - Regular database maintenance

3. **CDN Integration**
   - Use CDN for static assets
   - Optimize images and CSS

## Support

If you encounter issues during installation:

1. Check the troubleshooting section
2. Review error logs
3. Verify all requirements are met
4. Create an issue in the repository

---

**Note**: This installation guide covers the basic setup. For advanced configurations or specific server environments, additional configuration may be required.

