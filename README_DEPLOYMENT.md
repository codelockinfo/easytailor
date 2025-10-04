# Tailoring Management System - Deployment Guide

## Quick Start

### 🚀 One-Click Installation

1. **Upload Files**: Upload all project files to your web server
2. **Run Installer**: Navigate to `http://yourdomain.com/tailoring/install.php`
3. **Follow Steps**: Complete the 3-step installation process
4. **Done**: Your system is ready to use!

**Default Login**: admin / admin123 (change immediately!)

---

## 📋 System Requirements

| Component | Minimum Version | Recommended |
|-----------|----------------|-------------|
| **Web Server** | Apache 2.4+ or Nginx 1.18+ | Latest stable |
| **PHP** | 7.4+ | 8.0+ |
| **Database** | MySQL 5.7+ or MariaDB 10.3+ | MySQL 8.0+ |
| **PHP Extensions** | PDO, PDO_MySQL, GD, JSON, MBString | All + OpenSSL |

---

## 🗂️ Files Included

### Database Files
- `database/complete_setup.sql` - **Complete database setup (recommended)**
- `database/schema.sql` - Base schema
- `database/add_last_login.sql` - User login tracking
- `database/add_measurement_charts.sql` - Measurement chart support
- `database/add_multi_tenant.sql` - Multi-company support

### Configuration Files
- `config/database.example.php` - Database configuration template
- `config/config.example.php` - Application configuration template
- `install.php` - **Automated installation script**

### Documentation
- `SETUP_GUIDE.md` - Detailed setup instructions
- `DEPLOYMENT_CHECKLIST.md` - Deployment checklist
- `INSTALLATION.md` - Original installation guide

---

## 🛠️ Installation Methods

### Method 1: Automated Installation (Recommended)

```bash
# 1. Upload files to your web server
# 2. Navigate to: http://yourdomain.com/tailoring/install.php
# 3. Follow the 3-step wizard
```

### Method 2: Manual Installation

```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE tailoring_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import complete setup
mysql -u root -p tailoring_management < database/complete_setup.sql

# 3. Configure files
cp config/database.example.php config/database.php
cp config/config.example.php config/config.php

# 4. Edit configuration files with your settings
```

### Method 3: Docker Installation

```bash
# 1. Create docker-compose.yml (see SETUP_GUIDE.md)
# 2. Run containers
docker-compose up -d

# 3. Import database
docker exec -i container_name mysql -u root -p tailoring_management < database/complete_setup.sql
```

---

## ⚙️ Configuration

### Database Configuration (`config/database.php`)

```php
class Database {
    private $host = 'localhost';           // Your database host
    private $db_name = 'tailoring_management'; // Database name
    private $username = 'your_db_user';    // Database username
    private $password = 'your_db_pass';    // Database password
    private $port = 3306;                  // Database port
}
```

### Application Configuration (`config/config.php`)

```php
define('APP_URL', 'http://yourdomain.com/tailoring'); // Your domain
date_default_timezone_set('Your/Timezone'); // Your timezone
```

---

## 🔒 Security Setup

### Post-Installation Security

```bash
# Remove installation files
rm install.php
rm config/database.example.php
rm config/config.example.php

# Set proper permissions
chmod 755 uploads/
chmod 644 config/*.php
```

### Production Security

- [ ] Change default admin password
- [ ] Enable HTTPS
- [ ] Set secure session cookies
- [ ] Configure firewall rules
- [ ] Regular security updates

---

## 🚀 Environment-Specific Setup

### Local Development

#### XAMPP
```bash
# Place files in: C:\xampp\htdocs\tailoring\
# Access via: http://localhost/tailoring
```

#### WAMP
```bash
# Place files in: C:\wamp64\www\tailoring\
# Access via: http://localhost/tailoring
```

### Production Server

#### Apache
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

#### Nginx
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

---

## 🔧 Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| **Database Connection Error** | Check credentials in `config/database.php` |
| **File Permission Errors** | Set `chmod 755 uploads/` and `chown www-data:www-data uploads/` |
| **URL Rewriting Not Working** | Enable mod_rewrite in Apache |
| **Session Issues** | Check PHP session configuration |
| **Upload Issues** | Increase PHP upload limits in php.ini |

### Debug Mode

```php
// In config/config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

## 📊 Features Included

### Core Features
- ✅ Customer Management
- ✅ Order Management
- ✅ Invoice Generation
- ✅ Payment Tracking
- ✅ Measurement Management
- ✅ User Management
- ✅ Multi-company Support
- ✅ Measurement Charts
- ✅ Expense Tracking
- ✅ Contact Management

### Advanced Features
- ✅ Multi-language Support
- ✅ Subscription Management
- ✅ Coupon System
- ✅ PDF Generation
- ✅ File Upload System
- ✅ Session Management
- ✅ Security Features

---

## 📞 Support

### Getting Help

1. **Check Documentation**: Review `SETUP_GUIDE.md` and `DEPLOYMENT_CHECKLIST.md`
2. **Check Logs**: Review error logs in your web server
3. **Verify Requirements**: Ensure all system requirements are met
4. **Test Configuration**: Use the installation script to test setup

### Resources

- [PHP Documentation](https://www.php.net/docs.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Apache Documentation](https://httpd.apache.org/docs/)
- [Nginx Documentation](https://nginx.org/en/docs/)

---

## 📝 License

This project is licensed under the MIT License. See the LICENSE file for details.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

**Happy Tailoring! 🧵✂️**
