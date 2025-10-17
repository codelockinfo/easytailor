# Forgot Password - Quick Start Guide

## 🚀 Quick Setup (2 Steps)

### Step 1: Create Database Table

**Option A: Using Setup Script (Recommended)**
1. Open your browser and navigate to: `http://your-domain/admin/setup_password_reset.php`
2. Click "Create Password Reset Table"
3. Done! ✓

**Option B: Using SQL**
Run this SQL command in phpMyAdmin or your database tool:

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

### Step 2: Test Email Configuration

1. Navigate to: `http://your-domain/admin/test_email.php`
2. Enter your email address
3. Click "Send Test Email"
4. Check your inbox (and spam folder)

If email doesn't work, see the Email Configuration section below.

---

## 📧 Email Configuration

### For XAMPP/WAMP (Windows)

1. Edit `php.ini` (in XAMPP: `C:\xampp\php\php.ini`):

```ini
[mail function]
SMTP=smtp.gmail.com
smtp_port=587
sendmail_from=your-email@gmail.com
sendmail_path="\"C:\xampp\sendmail\sendmail.exe\" -t"
```

2. Edit `sendmail.ini` (in XAMPP: `C:\xampp\sendmail\sendmail.ini`):

```ini
[sendmail]
smtp_server=smtp.gmail.com
smtp_port=587
auth_username=your-email@gmail.com
auth_password=your-app-password
force_sender=your-email@gmail.com
```

**Note:** For Gmail, you need to generate an "App Password":
1. Go to Google Account settings
2. Security → 2-Step Verification → App passwords
3. Generate a new app password
4. Use this password in `sendmail.ini`

### For Linux Servers

Install sendmail:
```bash
sudo apt-get update
sudo apt-get install sendmail
```

---

## ✅ How to Use

### For End Users:

1. **Go to login page**: `http://your-domain/admin/login.php`
2. **Click**: "Forgot Password?"
3. **Enter**: Your registered email address
4. **Check email**: Look for 6-digit code
5. **Enter code**: Within 15 minutes
6. **Set new password**: Minimum 6 characters
7. **Login**: With your new password

### User Flow Diagram:

```
Login Page
    ↓ (Click "Forgot Password?")
Forgot Password Page (Enter Email)
    ↓ (Email sent with 6-digit code)
Verify Code Page (Enter 6-digit code)
    ↓ (Code verified)
Reset Password Page (Enter new password)
    ↓ (Password updated)
Login Page (Success message)
```

---

## 🔧 Troubleshooting

### Email Not Receiving Code

**Problem**: No email arrives after requesting password reset

**Solutions**:
1. ✓ Check spam/junk folder
2. ✓ Run email test: `admin/test_email.php`
3. ✓ Verify email exists in users table
4. ✓ Check PHP mail configuration
5. ✓ Review server error logs

### Invalid Code Error

**Problem**: Code shows as invalid even though it's correct

**Solutions**:
1. ✓ Code expires after 15 minutes - request a new one
2. ✓ Code can only be used once
3. ✓ Check for typos (use copy-paste)
4. ✓ Ensure database table was created correctly

### Cannot Access Pages

**Problem**: Getting 404 errors

**Solutions**:
1. ✓ Verify files are in `admin/` folder:
   - `forgot-password.php`
   - `verify-code.php`
   - `reset-password.php`
2. ✓ Check file permissions
3. ✓ Ensure web server is running

---

## 📁 Files Created

### Pages (in `admin/` folder):
- ✓ `forgot-password.php` - Email entry page
- ✓ `verify-code.php` - Code verification page
- ✓ `reset-password.php` - New password page
- ✓ `setup_password_reset.php` - Database setup tool
- ✓ `test_email.php` - Email testing tool

### Backend:
- ✓ `controllers/AuthController.php` - Updated with password reset methods
- ✓ `models/PasswordReset.php` - New model for password resets
- ✓ `database/add_password_resets.sql` - SQL migration file

### Documentation:
- ✓ `FORGOT_PASSWORD_SETUP.md` - Complete setup guide
- ✓ `FORGOT_PASSWORD_QUICK_START.md` - This file

---

## 🎯 Testing Checklist

Before going live, test these scenarios:

- [ ] Enter valid email → Receive code
- [ ] Enter invalid email → Show error
- [ ] Enter valid code → Proceed to password reset
- [ ] Enter invalid code → Show error
- [ ] Wait 15+ minutes → Code expires
- [ ] Use code twice → Second attempt fails
- [ ] Set new password → Login successful
- [ ] Test "Resend Code" button
- [ ] Test password strength indicator
- [ ] Test on mobile device

---

## 🔒 Security Features

✓ **6-digit codes** - Randomly generated  
✓ **Secure tokens** - 64-character hash  
✓ **Time-limited** - 15-minute expiration  
✓ **One-time use** - Cannot reuse codes  
✓ **Password hashing** - bcrypt encryption  
✓ **Input validation** - All inputs sanitized  
✓ **Session management** - Secure token storage  

---

## 📞 Support

If you encounter issues:

1. **Check documentation**: `FORGOT_PASSWORD_SETUP.md`
2. **Test email**: Use `admin/test_email.php`
3. **Check logs**: Review PHP error logs
4. **Verify database**: Ensure table exists
5. **Test flow**: Try complete process

---

## 🎨 Customization

### Change Code Expiration (Default: 15 minutes)

Edit `controllers/AuthController.php`, line ~202:

```php
// Change to 30 minutes
$expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
```

### Change Code Length (Default: 6 digits)

Edit `controllers/AuthController.php`, line ~196:

```php
// Change to 4 digits
$code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
```

### Customize Email Template

Edit `sendPasswordResetEmail()` method in `controllers/AuthController.php` starting at line ~306

---

## ✨ Features

- 📧 Email verification with 6-digit code
- ⏱️ 15-minute code expiration
- 🔄 Resend code option
- 🔐 Password strength indicator
- 📱 Fully responsive design
- 🎨 Modern gradient UI
- ⌨️ Auto-focus code inputs
- 📋 Copy-paste support
- 👁️ Show/hide password
- ✅ Real-time validation

---

## 🚀 Ready to Go!

Once setup is complete:

1. ✅ Database table created
2. ✅ Email configuration tested
3. ✅ Password reset flow tested
4. ✅ Users can reset passwords

**That's it! Your forgot password feature is ready to use!** 🎉

---

For advanced configuration and production deployment, see `FORGOT_PASSWORD_SETUP.md`

