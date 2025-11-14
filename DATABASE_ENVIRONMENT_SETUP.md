# Database Environment Auto-Detection Setup

## Overview

The database configuration now automatically detects whether your application is running on a **local/development** server or a **live/production** server and uses the appropriate database credentials accordingly.

## 🎯 Benefits

- ✅ **No Manual Switching**: No need to manually change database credentials when deploying
- ✅ **Automatic Detection**: System intelligently detects the environment
- ✅ **Secure**: Live credentials stay secure in the codebase
- ✅ **Developer Friendly**: Each developer can use their local setup without conflicts

## 🔧 How It Works

The `Database` class in `config/database.php` automatically detects the environment by checking:

1. **Server Name/Host**: 
   - `localhost`, `127.0.0.1`, `::1`
   - Domains containing `.local` or `local.test`
   - Server names containing `wamp` or `xampp`

2. **IP Address**:
   - Local IP ranges: `192.168.x.x`, `10.x.x.x`
   - Loopback addresses: `127.0.0.1`, `::1`

## 📋 Current Configuration

### Local/Development Environment
```
Host:     localhost
Database: tailoring_management
Username: root
Password: (empty)
```

### Live/Production Environment
```
Host:     localhost
Database: u402017191_tailorpro
Username: u402017191_tailorpro
Password: Tailorpro@99
```

## 🧪 Testing the Configuration

### Method 1: Using Test File
1. Visit: `http://localhost/tailoring/test_db_connection.php` (local)
2. The page will show:
   - Detected environment (LOCAL or LIVE)
   - Server information
   - Connection status
   - Connected database name

### Method 2: Add Debug Code
Add this to any page temporarily:
```php
$database = new Database();
echo "Environment: " . $database->getEnvironment(); // Returns 'local' or 'live'
```

## 🚀 Deployment Process

### Step 1: Local Development
- Work on your local WAMP/XAMPP server
- System automatically uses local database credentials
- No configuration changes needed

### Step 2: Upload to Live Server
- Upload files via FTP/cPanel/SSH
- System automatically detects live environment
- Uses live database credentials automatically

### Step 3: Verify (Important!)
- Visit your live site
- Check that application connects successfully
- Use test file if needed: `yourdomain.com/test_db_connection.php`

## ⚠️ Important Notes

1. **Security**: The `config/database.php` file contains sensitive credentials. Never share it publicly or commit it to public repositories.

2. **Custom Domains**: If you use a custom local domain (e.g., `myproject.test`), make sure it contains one of the local indicators, or add it to the `$localIndicators` array in `config/database.php`.

3. **Delete Test File**: After verifying the connection, delete `test_db_connection.php` from your live server for security.

4. **Backup**: Always backup your database before deploying to live server.

## 🔄 Updating Credentials

### To Update Local Credentials
Edit `config/database.php`, find the local section (around line 23-27):
```php
if ($isLocal) {
    // Local/Development Environment
    $this->host = 'localhost';
    $this->db_name = 'tailoring_management';
    $this->username = 'root';
    $this->password = '';
}
```

### To Update Live Credentials
Edit `config/database.php`, find the live section (around line 28-33):
```php
else {
    // Live/Production Environment
    $this->host = 'localhost';
    $this->db_name = 'u402017191_tailorpro';
    $this->username = 'u402017191_tailorpro';
    $this->password = 'Tailorpro@99';
}
```

## 🐛 Troubleshooting

### Issue: Wrong environment detected
**Solution**: Add debugging code to check server variables:
```php
echo "Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'not set');
echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'not set');
echo "Server IP: " . ($_SERVER['SERVER_ADDR'] ?? 'not set');
```

### Issue: Connection fails on live server
**Solutions**:
1. Verify live database credentials are correct
2. Check if database user has proper permissions
3. Confirm database exists on live server
4. Check if live server allows `localhost` as host (some require `127.0.0.1`)

### Issue: Want to force a specific environment
**Solution**: Add manual override at the top of the constructor:
```php
public function __construct() {
    // Uncomment to force environment:
    // $isLocal = true;  // Force local
    // $isLocal = false; // Force live
    
    $isLocal = $this->isLocalEnvironment();
    // ... rest of code
}
```

## 📝 Adding Custom Environment Detection

If you need to add custom detection logic, edit the `isLocalEnvironment()` method in `config/database.php`:

```php
private function isLocalEnvironment() {
    // Add your custom logic here
    if ($_SERVER['SERVER_NAME'] === 'your-custom-domain.com') {
        return true; // Treat as local
    }
    
    // ... existing detection code
}
```

## 🔐 Best Practices

1. **Version Control**: Add `config/database.php` to `.gitignore` to prevent committing credentials
2. **Environment Variables**: For enhanced security, consider using environment variables
3. **Regular Testing**: Test both environments regularly to catch issues early
4. **Documentation**: Keep this documentation updated when credentials change
5. **Access Control**: Limit who has access to production credentials

## 📞 Support

If you encounter issues with the environment detection:
1. Check the test file results
2. Review server variables
3. Verify database credentials
4. Check database user permissions
5. Review error logs in your hosting control panel

---

**Last Updated**: November 3, 2025
**Configuration Version**: 1.0






