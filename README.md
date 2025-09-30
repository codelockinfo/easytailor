# Tailoring Management System

A comprehensive web-based application for managing tailoring businesses, built with PHP, MySQL, Bootstrap, and jQuery. This system provides complete business management functionality including customer management, order tracking, invoice generation, expense tracking, and more.

## Features

### Core Modules

- **Dashboard**: Informative overview with charts, statistics, and alerts
- **Customer Management**: Complete customer database with contact details and history
- **Order Management**: Order creation, tracking, and status management
- **Invoice Management**: Invoice generation, payment tracking, and printing
- **Measurement Management**: Store and manage customer measurements for different cloth types
- **Cloth Type Management**: Manage different types of garments and their standard rates
- **Expense Management**: Track business expenses with categorization and reporting
- **User Management**: Role-based access control (Admin, Staff, Tailor, Cashier)
- **Reports & Analytics**: Comprehensive reporting with charts and statistics

### Advanced Features

- **Multi-language Support**: English, Spanish, French, Hindi with easy language switching
- **Subscription Management**: Different subscription packages with feature restrictions
- **Coupon System**: Discount coupons with usage tracking
- **Responsive Design**: Mobile-first approach with Bootstrap 5
- **Real-time Updates**: AJAX-powered interface for smooth user experience
- **Security**: CSRF protection, password hashing, and role-based access control
- **File Management**: Receipt and image upload functionality

## Technical Requirements

- **Web Server**: Apache/Nginx with PHP 7.4+ support
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **PHP Extensions**: PDO, PDO_MySQL, GD, JSON
- **Browser**: Modern browsers with JavaScript enabled

## Installation

### 1. Download and Setup

1. Download or clone this repository to your web server directory
2. Ensure your web server has PHP 7.4+ and MySQL 5.7+ installed

### 2. Database Setup

1. Create a MySQL database named `tailoring_management`
2. Import the database schema:
   ```sql
   mysql -u username -p tailoring_management < database/schema.sql
   ```

### 3. Configuration

1. Edit `config/database.php` and update database credentials:
   ```php
   private $host = 'localhost';
   private $db_name = 'tailoring_management';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

2. Update `config/config.php` with your application URL:
   ```php
   define('APP_URL', 'http://your-domain.com/tailoring');
   ```

### 4. File Permissions

Set appropriate permissions for upload directories:
```bash
chmod 755 uploads/
chmod 755 uploads/customers/
chmod 755 uploads/orders/
chmod 755 uploads/measurements/
chmod 755 uploads/receipts/
chmod 755 uploads/logos/
```

### 5. Default Login

After installation, you can login with:
- **Username**: admin
- **Password**: admin123

**Important**: Change the default password immediately after first login.

## Usage Guide

### Getting Started

1. **Login**: Access the system using your credentials
2. **Dashboard**: View business overview and key metrics
3. **Setup**: Configure company settings and add basic data

### Customer Management

1. **Add Customers**: Click "Add Customer" to create new customer profiles
2. **Customer Details**: Store contact information, addresses, and notes
3. **Customer History**: View all orders and interactions for each customer

### Order Management

1. **Create Orders**: Select customer, cloth type, and set delivery dates
2. **Assign Tailors**: Assign orders to specific tailors
3. **Track Progress**: Update order status as work progresses
4. **Measurements**: Link customer measurements to orders

### Invoice Management

1. **Generate Invoices**: Create invoices from completed orders
2. **Payment Tracking**: Record payments and track outstanding amounts
3. **Print Invoices**: Generate printable invoice PDFs

### Expense Tracking

1. **Record Expenses**: Categorize and record business expenses
2. **Receipt Management**: Upload and attach receipt images
3. **Expense Reports**: View categorized expense reports

## User Roles

- **Admin**: Full system access, user management, settings
- **Staff**: Customer and order management, basic reporting
- **Tailor**: Order management, status updates, measurements
- **Cashier**: Invoice and payment management

## Security Features

- **Password Hashing**: Secure password storage using PHP password_hash()
- **CSRF Protection**: Cross-site request forgery protection
- **Session Management**: Secure session handling with timeout
- **Input Validation**: All user inputs are sanitized and validated
- **Role-based Access**: Different access levels based on user roles

## Customization

### Adding New Languages

1. Add language entries to the `languages` table
2. Create translation files in `lang/` directory
3. Update language switcher in header

### Customizing UI

- Modify CSS in `includes/header.php`
- Update Bootstrap theme variables
- Customize dashboard charts and statistics

### Adding New Features

- Follow MVC pattern for new modules
- Create models in `models/` directory
- Add controllers in `controllers/` directory
- Update navigation in `includes/header.php`

## API Endpoints

The system includes AJAX endpoints for dynamic functionality:

- `ajax/get_customer_measurements.php` - Get customer measurements
- Additional endpoints can be added following the same pattern

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Check database exists and is accessible

2. **Permission Errors**
   - Set correct file permissions for upload directories
   - Ensure web server can write to upload folders

3. **Session Issues**
   - Check PHP session configuration
   - Verify session directory is writable

4. **File Upload Issues**
   - Check PHP upload settings (upload_max_filesize, post_max_size)
   - Verify upload directory permissions

### Debug Mode

Enable debug mode by setting in `config/config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue in the repository
- Check the troubleshooting section
- Review the code documentation

## Changelog

### Version 1.0.0
- Initial release
- Core functionality implementation
- Dashboard with charts and statistics
- Customer, order, and invoice management
- Expense tracking
- User management with roles
- Multi-language support
- Responsive design

## Future Enhancements

- Mobile app development
- Advanced reporting and analytics
- Integration with payment gateways
- Inventory management
- Customer portal
- Automated notifications
- Backup and restore functionality

---

**Note**: This is a comprehensive tailoring management system designed for small to medium-sized tailoring businesses. The system is built with modern web technologies and follows best practices for security, performance, and maintainability.

