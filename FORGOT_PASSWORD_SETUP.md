# Forgot Password Functionality - Setup Guide

This guide explains the complete forgot password functionality that has been implemented in the Tailoring Management System.

## 📋 Overview

The forgot password feature allows users to reset their password through a secure 3-step process:

1. **Email Verification** - User enters their email address
2. **Code Verification** - User receives a 6-digit code via email and enters it
3. **Password Reset** - User creates a new password

## 🗄️ Database Setup

### Step 1: Run the SQL Migration

Execute the SQL migration file to create the `password_resets` table:

```bash
mysql -u your_username -p your_database < database/add_password_resets.sql
```

Or manually run the SQL in phpMyAdmin or your database management tool:

```sql
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(6) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_email_code ON password_resets(email, code, used);
```

## 📧 Email Configuration

### Option 1: Using PHP mail() Function (Default)

The system uses PHP's built-in `mail()` function. Make sure your server has mail support enabled.

**For XAMPP/WAMP on Windows:**

1. Open `php.ini` file
2. Configure SMTP settings:

```ini
[mail function]
SMTP=smtp.gmail.com
smtp_port=587
sendmail_from=your-email@gmail.com
sendmail_path="\"C:\xampp\sendmail\sendmail.exe\" -t"
```

3. Configure `sendmail.ini`:

```ini
[sendmail]
smtp_server=smtp.gmail.com
smtp_port=587
auth_username=your-email@gmail.com
auth_password=your-app-password
force_sender=your-email@gmail.com
```

**For Linux Servers:**

1. Install sendmail or postfix:

```bash
sudo apt-get install sendmail
# OR
sudo apt-get install postfix
```

2. PHP mail() should work automatically

### Option 2: Using SMTP (Recommended for Production)

For better email delivery in production, consider using a third-party email service:

1. Install PHPMailer (optional, for better email handling):

```bash
composer require phpmailer/phpmailer
```

2. Update the `sendPasswordResetEmail()` method in `controllers/AuthController.php` to use SMTP

## 🎨 Features Implemented

### 1. Login Page Enhancement
- Added "Forgot Password?" link on the login page
- File: `admin/login.php`

### 2. Email Entry Page
- Modern, user-friendly interface
- Email validation
- File: `admin/forgot-password.php`

### 3. Code Verification Page
- 6-digit code input with auto-focus
- Countdown timer (15 minutes)
- Resend code functionality
- Copy-paste support
- File: `admin/verify-code.php`

### 4. Password Reset Page
- Password strength indicator
- Real-time password requirements checker
- Password match validation
- Show/hide password toggle
- File: `admin/reset-password.php`

### 5. Backend Logic
- Secure token generation
- Code expiration handling (15 minutes)
- Token-based password reset
- Email sending functionality
- File: `controllers/AuthController.php`

## 🔒 Security Features

1. **6-Digit Random Code** - Generated using secure random functions
2. **Token-Based Authentication** - 64-character secure token
3. **Time-Limited Codes** - Codes expire after 15 minutes
4. **One-Time Use** - Codes can only be used once
5. **Session Management** - Proper session handling throughout the process
6. **Password Hashing** - Passwords are hashed using PHP's password_hash()
7. **Input Validation** - All inputs are validated and sanitized

## 📝 Usage Flow

### For Users:

1. Click "Forgot Password?" on the login page
2. Enter your registered email address
3. Check your email for the 6-digit code
4. Enter the code within 15 minutes
5. Create a new password (minimum 6 characters)
6. Login with your new password

### For Administrators:

Monitor password reset requests in the `password_resets` table:

```sql
-- View recent password reset requests
SELECT * FROM password_resets 
ORDER BY created_at DESC 
LIMIT 10;

-- Clean up old/expired requests (run periodically)
DELETE FROM password_resets 
WHERE expires_at < NOW() 
OR used = 1;
```

## 🧪 Testing

### Test the Flow:

1. **Navigate to Login Page**: `http://your-domain/admin/login.php`
2. **Click "Forgot Password?"**
3. **Enter Email**: Use an email that exists in your `users` table
4. **Check Email**: Look for the 6-digit code
5. **Enter Code**: Input the 6-digit code
6. **Reset Password**: Enter and confirm new password
7. **Login**: Use the new password to login

### Test Cases:

- ✅ Valid email with account
- ❌ Email not in system
- ❌ Invalid email format
- ❌ Expired code (wait 15+ minutes)
- ❌ Invalid code
- ❌ Mismatched passwords
- ❌ Weak password (< 6 characters)
- ✅ Resend code functionality

## 🛠️ Troubleshooting

### Email Not Sending

**Problem**: Verification code email is not received

**Solutions**:
1. Check spam/junk folder
2. Verify PHP mail configuration
3. Check server mail logs
4. Test with `mail()` function directly
5. Consider using SMTP instead

### Code Not Working

**Problem**: Valid code shows as invalid

**Solutions**:
1. Check database connection
2. Verify `password_resets` table exists
3. Check for timezone issues
4. Ensure code hasn't expired (15 minutes)

### Session Issues

**Problem**: Getting redirected unexpectedly

**Solutions**:
1. Ensure sessions are started in `config.php`
2. Check session configuration
3. Clear browser cookies
4. Check session timeout settings

## 📱 Responsive Design

All pages are fully responsive and work seamlessly on:
- Desktop computers
- Tablets
- Mobile phones

## 🎨 Customization

### Change Code Expiration Time

Edit `controllers/AuthController.php`:

```php
// Change from 15 minutes to 30 minutes
$expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
```

### Change Code Length

Edit `controllers/AuthController.php`:

```php
// Change from 6 digits to 4 digits
$code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
```

### Customize Email Template

Edit the `sendPasswordResetEmail()` method in `controllers/AuthController.php` to customize:
- Email subject
- Email design/styling
- Email content
- Sender information

### Change Password Requirements

Edit `admin/reset-password.php` to modify password strength requirements.

## 📊 Database Maintenance

Add a cron job to clean up expired/used tokens:

```sql
-- Run daily at midnight
DELETE FROM password_resets 
WHERE expires_at < NOW() - INTERVAL 1 DAY;
```

## 🔄 Future Enhancements

Potential improvements you could add:

1. SMS verification option
2. Two-factor authentication
3. Password history (prevent reusing old passwords)
4. Rate limiting (prevent abuse)
5. CAPTCHA on forgot password form
6. Email templates with company branding
7. Admin notification of password resets
8. Login attempt logging

## 📞 Support

If you encounter any issues or need assistance:

1. Check the troubleshooting section
2. Review server error logs
3. Verify database tables exist
4. Test email configuration
5. Check PHP version compatibility (PHP 7.4+)

## ✅ Checklist

- [ ] Database table created (`password_resets`)
- [ ] Email configuration tested
- [ ] All pages accessible
- [ ] Test complete flow end-to-end
- [ ] Email delivery working
- [ ] Password reset successful
- [ ] Login with new password works

---

**Note**: For production environments, always use HTTPS to ensure secure password reset process and protect user data.

