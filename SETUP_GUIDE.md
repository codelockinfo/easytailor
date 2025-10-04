# Tailoring Management System - Setup Guide

This guide will help you set up the Tailoring Management System on any server or local environment.

## Quick Setup (Recommended)

### Option 1: Using the Installation Script

1. **Upload Files**: Upload all project files to your web server
2. **Run Installer**: Navigate to `http://yourdomain.com/tailoring/install.php`
3. **Follow Steps**: Complete the 3-step installation process
4. **Done**: Your system is ready to use!

### Option 2: Manual Setup

Follow the detailed manual setup instructions below.

## System Requirements

- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7+ or MariaDB 10.3+
- **PHP Extensions**: PDO, PDO_MySQL, GD, JSON, MBString, OpenSSL

## Manual Setup Instructions

### Step 1: File Upload

1. Download or clone the project files
2. Upload to your web server directory:
   - **Apache**: `/var/www/html/tailoring/` or `/var/www/tailoring/`
   - **XAMPP**: `C:\xampp\htdocs\tailoring\`
   - **WAMP**: `C:\wamp64\www\tailoring\`

### Step 2: Database Setup

#### Method A: Using the Complete SQL File (Recommended)

1. Create a new database in your MySQL/MariaDB:
   ```sql
   CREATE DATABASE tailoring_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the complete setup file:
   ```bash
   mysql -u username -p tailoring_management < database/complete_setup.sql
   ```

   Or using phpMyAdmin:
   - Open phpMyAdmin
   - Select your database
   - Go to Import tab
   - Choose `database/complete_setup.sql`
   - Click Go

#### Method B: Using Individual SQL Files

1. Import the base schema:
   ```bash
   mysql -u username -p tailoring_management < database/schema.sql
   ```

2. Apply additional features:
   ```bash
   mysql -u username -p tailoring_management < database/add_last_login.sql
   mysql -u username -p tailoring_management < database/add_measurement_charts.sql
   mysql -u username -p tailoring_management < database/add_multi_tenant.sql
   ```

### Step 3: Configuration

#### Database Configuration

1. Copy the example file:
   ```bash
   cp config/database.example.php config/database.php
   ```

2. Edit `config/database.php` with your database details:
   ```php
   class Database {
       private $host = 'localhost';           // Your database host
       private $db_name = 'tailoring_management'; // Database name
       private $username = 'your_db_user';    // Your database username
       private $password = 'your_db_pass';    // Your database password
       private $port = 3306;                  // Database port
       // ... rest of the configuration
   }
   ```

#### Application Configuration

1. Copy the example file:
   ```bash
   cp config/config.example.php config/config.php
   ```

2. Edit `config/config.php` with your application settings:
   ```php
   define('APP_URL', 'http://yourdomain.com/tailoring'); // Your domain
   date_default_timezone_set('Your/Timezone'); // Your timezone
   ```

### Step 4: File Permissions

Set proper permissions for upload directories:

```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 uploads/customers/
chmod 755 uploads/orders/
chmod 755 uploads/measurements/
chmod 755 uploads/receipts/
chmod 755 uploads/logos/
chmod 755 uploads/measurement-charts/

# Set ownership (if using Apache)
chown -R www-data:www-data uploads/
```

For Windows (XAMPP/WAMP), ensure the web server has write permissions to the upload directories.

### Step 5: Web Server Configuration

#### Apache Configuration

The `.htaccess` file is already included with the project. Ensure mod_rewrite is enabled:

```bash
# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
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

Ensure these settings in your `php.ini`:

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

# Memory and execution time
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# Timezone
date.timezone = "Your/Timezone"
```

## Initial Setup

### Default Login Credentials

- **Username**: admin
- **Password**: admin123

**⚠️ IMPORTANT**: Change the default password immediately after first login!

### First Steps After Installation

1. **Access the System**
   - Navigate to your application URL
   - Login with default credentials

2. **Change Admin Password**
   - Go to Profile → Change Password
   - Set a strong password

3. **Configure Company Settings**
   - Go to Settings
   - Update company information
   - Set currency and tax rates
   - Upload company logo

4. **Add Initial Data**
   - Add cloth types (Shirt, Pants, Suit, etc.)
   - Create additional users with appropriate roles
   - Start adding customers

## Environment-Specific Setup

### Local Development (XAMPP/WAMP)

1. **XAMPP Setup**:
   - Install XAMPP
   - Place files in `C:\xampp\htdocs\tailoring\`
   - Start Apache and MySQL
   - Access via `http://localhost/tailoring`

2. **WAMP Setup**:
   - Install WAMP
   - Place files in `C:\wamp64\www\tailoring\`
   - Start all services
   - Access via `http://localhost/tailoring`

### Production Server

1. **Upload Files**:
   ```bash
   # Using SCP
   scp -r tailoring/ user@server:/var/www/html/
   
   # Using FTP/SFTP
   # Upload all files to your web directory
   ```

2. **Set Permissions**:
   ```bash
   chmod -R 755 /var/www/html/tailoring/
   chmod -R 777 /var/www/html/tailoring/uploads/
   chown -R www-data:www-data /var/www/html/tailoring/
   ```

3. **Configure Virtual Host** (if needed):
   ```apache
   <VirtualHost *:80>
       ServerName yourdomain.com
       DocumentRoot /var/www/html/tailoring
       
       <Directory /var/www/html/tailoring>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

### Docker Setup

Create a `docker-compose.yml` file:

```yaml
version: '3.8'
services:
  web:
    image: php:7.4-apache
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
    depends_on:
      - db
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html
    command: >
      bash -c "docker-php-ext-install pdo pdo_mysql
      && a2enmod rewrite
      && apache2-foreground"

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: tailoring_management
      MYSQL_USER: tailoring_user
      MYSQL_PASSWORD: tailoring_pass
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

Run with:
```bash
docker-compose up -d
```

## Troubleshooting

### Common Issues

#### 1. Database Connection Error
```
Error: Connection error: SQLSTATE[HY000] [1045] Access denied
```

**Solutions**:
- Verify database credentials in `config/database.php`
- Check if MySQL service is running
- Ensure database user has proper permissions
- Test connection manually

#### 2. File Permission Errors
```
Warning: mkdir(): Permission denied
```

**Solutions**:
```bash
# Set proper permissions
chmod 755 uploads/
chown -R www-data:www-data uploads/
```

#### 3. URL Rewriting Not Working
```
404 errors on all pages except index.php
```

**Solutions**:
- Ensure mod_rewrite is enabled in Apache
- Check `.htaccess` file exists and is readable
- Verify AllowOverride is set to All in Apache config

#### 4. Session Issues
```
Warning: session_start(): Cannot send session cookie
```

**Solutions**:
- Check PHP session configuration
- Ensure session directory is writable
- Clear browser cookies
- Check session.save_path in php.ini

#### 5. Upload Issues
```
Error: File upload failed
```

**Solutions**:
- Check PHP upload settings in php.ini
- Verify upload directory permissions
- Increase upload limits
- Check file size and type restrictions

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

## Security Considerations

### Post-Installation Security

1. **Delete Installation Files**:
   ```bash
   rm install.php
   rm config/database.example.php
   rm config/config.example.php
   ```

2. **Change Default Credentials**:
   - Change admin password immediately
   - Create strong passwords for all users

3. **Database Security**:
   - Use strong database passwords
   - Limit database user permissions
   - Regular database backups

4. **File Permissions**:
   - Ensure proper file permissions
   - Restrict access to sensitive directories

5. **HTTPS (Production)**:
   - Use SSL certificates in production
   - Update APP_URL to use HTTPS
   - Set secure session cookies

### Backup Strategy

#### Database Backup
```bash
# Create backup
mysqldump -u username -p tailoring_management > backup_$(date +%Y%m%d).sql

# Restore backup
mysql -u username -p tailoring_management < backup_20231201.sql
```

#### File Backup
```bash
# Backup entire application
tar -czf tailoring_backup_$(date +%Y%m%d).tar.gz /path/to/tailoring/
```

## Performance Optimization

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_customers_phone ON customers (phone);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_invoices_payment_status ON invoices (payment_status);
```

### Caching
- Enable OPcache in PHP
- Use Redis or Memcached for session storage
- Implement page caching for static content

### CDN Integration
- Use CDN for static assets
- Optimize images and CSS
- Enable gzip compression

## Support

If you encounter issues during setup:

1. Check this troubleshooting guide
2. Review error logs
3. Verify all requirements are met
4. Check the project documentation
5. Create an issue in the repository

## Additional Resources

- [PHP Documentation](https://www.php.net/docs.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Apache Documentation](https://httpd.apache.org/docs/)
- [Nginx Documentation](https://nginx.org/en/docs/)

---

**Note**: This setup guide covers the basic installation. For advanced configurations or specific server environments, additional configuration may be required.
