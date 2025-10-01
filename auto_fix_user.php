<?php
/**
 * Automatic User Fix Script
 * This will automatically fix the user session issue
 */

require_once 'config/database.php';

session_start();

$steps = [];
$allFixed = true;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // STEP 1: Check if users table exists and has data
    $steps['check_users'] = ['status' => 'checking', 'message' => 'Checking users table...'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $userCount = $result['count'];
    
    if ($userCount == 0) {
        $steps['check_users'] = ['status' => 'warning', 'message' => "No users found. Creating default admin user..."];
        
        // Create default admin user
        $sql = "INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`) 
                VALUES ('admin', 'admin@tailoring.com', :password, 'System Administrator', 'admin', 'active')";
        $stmt = $conn->prepare($sql);
        $hashedPassword = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password
        $stmt->bindParam(':password', $hashedPassword);
        
        if ($stmt->execute()) {
            $newUserId = $conn->lastInsertId();
            $steps['create_user'] = ['status' => 'success', 'message' => "✅ Created admin user (ID: $newUserId)"];
        } else {
            $steps['create_user'] = ['status' => 'error', 'message' => "❌ Failed to create admin user"];
            $allFixed = false;
        }
    } else {
        $steps['check_users'] = ['status' => 'success', 'message' => "✅ Found $userCount user(s) in database"];
    }
    
    // STEP 2: Check current session
    $steps['check_session'] = ['status' => 'checking', 'message' => 'Checking current session...'];
    
    $sessionUserId = $_SESSION['user_id'] ?? null;
    
    if ($sessionUserId) {
        $stmt = $conn->prepare("SELECT id, username, full_name, role FROM users WHERE id = ?");
        $stmt->execute([$sessionUserId]);
        $sessionUser = $stmt->fetch();
        
        if ($sessionUser) {
            $steps['check_session'] = ['status' => 'success', 'message' => "✅ Session user exists (ID: $sessionUserId, Username: {$sessionUser['username']})"];
        } else {
            $steps['check_session'] = ['status' => 'error', 'message' => "❌ Session user ID ($sessionUserId) NOT found in database"];
            
            // Clear invalid session
            session_unset();
            session_destroy();
            
            $steps['fix_session'] = ['status' => 'success', 'message' => "✅ Cleared invalid session"];
        }
    } else {
        $steps['check_session'] = ['status' => 'warning', 'message' => "⚠️ No user session found"];
    }
    
    // STEP 3: Get list of valid users
    $stmt = $conn->query("SELECT id, username, full_name, role, status FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    $steps['list_users'] = ['status' => 'info', 'message' => 'Available users:', 'users' => $users];
    
} catch (Exception $e) {
    $steps['error'] = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    $allFixed = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Fix User Issue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .status-info { color: #17a2b8; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header <?php echo $allFixed ? 'bg-success' : 'bg-warning'; ?> text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Auto Fix User Issue
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($steps as $key => $step): ?>
                            <div class="mb-3 p-3 border rounded">
                                <?php if ($key === 'list_users'): ?>
                                    <h5><i class="fas fa-users me-2 status-info"></i><?php echo $step['message']; ?></h5>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Username</th>
                                                    <th>Full Name</th>
                                                    <th>Role</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($step['users'] as $user): ?>
                                                <tr>
                                                    <td><?php echo $user['id']; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $user['role']; ?></span></td>
                                                    <td><span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo $user['status']; ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="status-<?php echo $step['status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $step['status'] === 'success' ? 'check-circle' : 
                                                ($step['status'] === 'error' ? 'times-circle' : 
                                                ($step['status'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
                                        ?> me-2"></i>
                                        <strong><?php echo $step['message']; ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($allFixed): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h4>All Issues Fixed! ✅</h4>
                                <p class="mb-0">Please logout and login again with these credentials:</p>
                            </div>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5>Login Credentials:</h5>
                                    <ul class="mb-0">
                                        <li><strong>Username:</strong> <code>admin</code></li>
                                        <li><strong>Password:</strong> <code>password</code></li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-grid gap-2">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="logout.php" class="btn btn-danger btn-lg">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout Now
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                                </a>
                            <?php endif; ?>
                            
                            <div class="btn-group">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                                <a href="orders.php" class="btn btn-outline-primary">
                                    <i class="fas fa-shopping-bag me-2"></i>Try Orders Again
                                </a>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <strong>What to do next:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Click "Logout Now" button above</li>
                                    <li>Go to login page</li>
                                    <li>Login with: <code>admin</code> / <code>password</code></li>
                                    <li>Try creating an order again</li>
                                    <li>Change password after first login</li>
                                </ol>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

