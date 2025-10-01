<?php
/**
 * Reset/Create Admin User
 * One-time use script to create or reset admin credentials
 */

require_once 'config/database.php';

$results = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if admin user exists
    $stmt = $conn->query("SELECT id, username, email, full_name, status FROM users WHERE username = 'admin' OR role = 'admin'");
    $existingAdmins = $stmt->fetchAll();
    
    $results['existing'] = $existingAdmins;
    
    if (empty($existingAdmins)) {
        // No admin found, create new one
        $username = 'admin';
        $email = 'admin@tailoring.com';
        $password = 'admin123'; // Default password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $fullName = 'System Administrator';
        
        $sql = "INSERT INTO users (username, email, password, full_name, role, status) 
                VALUES (:username, :email, :password, :full_name, 'admin', 'active')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $fullName);
        
        if ($stmt->execute()) {
            $results['action'] = 'created';
            $results['username'] = $username;
            $results['password'] = $password;
            $results['message'] = 'Admin user created successfully!';
        } else {
            $results['action'] = 'error';
            $results['message'] = 'Failed to create admin user';
        }
    } else {
        // Admin exists, reset password
        $adminId = $existingAdmins[0]['id'];
        $password = 'admin123'; // Reset password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = :password, status = 'active' WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $adminId);
        
        if ($stmt->execute()) {
            $results['action'] = 'reset';
            $results['username'] = $existingAdmins[0]['username'];
            $results['password'] = $password;
            $results['message'] = 'Admin password reset successfully!';
        } else {
            $results['action'] = 'error';
            $results['message'] = 'Failed to reset password';
        }
    }
    
    // Get all users for verification
    $stmt = $conn->query("SELECT id, username, email, full_name, role, status FROM users ORDER BY id");
    $results['all_users'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    $results['error'] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header <?php echo isset($results['action']) && in_array($results['action'], ['created', 'reset']) ? 'bg-success' : 'bg-warning'; ?> text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-user-shield me-2"></i>Admin User Reset
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($results['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error:</strong> <?php echo htmlspecialchars($results['error']); ?>
                            </div>
                        <?php else: ?>
                            
                            <?php if (isset($results['action'])): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle fa-2x mb-3"></i>
                                    <h4><?php echo htmlspecialchars($results['message']); ?></h4>
                                </div>
                                
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-key me-2"></i>Login Credentials
                                        </h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>Username:</strong></p>
                                                <div class="input-group mb-3">
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($results['username']); ?>" readonly id="username">
                                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('username')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>Password:</strong></p>
                                                <div class="input-group mb-3">
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($results['password']); ?>" readonly id="password">
                                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('password')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Important:</strong> Please change this password after first login!
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($results['all_users'])): ?>
                                <h5 class="mb-3">All Users in Database:</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Full Name</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results['all_users'] as $user): ?>
                                            <tr class="<?php echo $user['role'] === 'admin' ? 'table-success' : ''; ?>">
                                                <td><?php echo $user['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo $user['role']; ?></span></td>
                                                <td><span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo $user['status']; ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-grid gap-2">
                            <a href="login.php" class="btn btn-success btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                            </a>
                            <div class="btn-group">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                                <button class="btn btn-outline-danger" onclick="if(confirm('Delete this script for security?')) window.location.href='delete_reset_script.php'">
                                    <i class="fas fa-trash me-2"></i>Delete This Script
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <strong><i class="fas fa-shield-alt me-2"></i>Security Note:</strong>
                                For security reasons, delete this script (reset_admin.php) after use.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        element.select();
        document.execCommand('copy');
        
        // Show feedback
        const btn = event.currentTarget;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 1000);
    }
    </script>
</body>
</html>

