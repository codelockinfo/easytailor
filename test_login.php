<?php
/**
 * Login Test & Diagnostic Tool
 * Tests login credentials and shows what's happening
 */

require_once 'config/database.php';
require_once 'models/User.php';

$testResults = [];
$testUsername = $_POST['test_username'] ?? 'admin';
$testPassword = $_POST['test_password'] ?? 'admin123';

try {
    $db = new Database();
    $conn = $db->getConnection();
    $userModel = new User();
    
    // Get all users
    $stmt = $conn->query("SELECT id, username, email, password, full_name, role, status FROM users ORDER BY id");
    $allUsers = $stmt->fetchAll();
    $testResults['all_users'] = $allUsers;
    
    // If form submitted, test login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $testResults['test_attempted'] = true;
        $testResults['test_username'] = $testUsername;
        $testResults['test_password'] = $testPassword;
        
        // Find user by username or email
        $stmt = $conn->prepare("SELECT * FROM users WHERE (username = :username OR email = :email)");
        $stmt->bindParam(':username', $testUsername);
        $stmt->bindParam(':email', $testUsername);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user) {
            $testResults['user_found'] = true;
            $testResults['user_details'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'status' => $user['status']
            ];
            $testResults['password_hash'] = substr($user['password'], 0, 20) . '...';
            
            // Test password verification
            $passwordValid = password_verify($testPassword, $user['password']);
            $testResults['password_valid'] = $passwordValid;
            
            // Check status
            $testResults['status_active'] = ($user['status'] === 'active');
            
            // Test actual authenticate function
            $authResult = $userModel->authenticate($testUsername, $testPassword);
            $testResults['auth_result'] = $authResult ? 'SUCCESS' : 'FAILED';
            
            if ($authResult) {
                $testResults['can_login'] = true;
            } else {
                $testResults['can_login'] = false;
                if (!$passwordValid) {
                    $testResults['failure_reason'] = 'Password is incorrect';
                } elseif ($user['status'] !== 'active') {
                    $testResults['failure_reason'] = 'User status is not active';
                } else {
                    $testResults['failure_reason'] = 'Unknown reason';
                }
            }
        } else {
            $testResults['user_found'] = false;
            $testResults['failure_reason'] = 'User not found with username or email: ' . $testUsername;
        }
    }
    
} catch (Exception $e) {
    $testResults['error'] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-bug me-2"></i>Login Test & Diagnostic Tool
                        </h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Test Login Form -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Test Login Credentials</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <label class="form-label">Username or Email</label>
                                            <input type="text" name="test_username" class="form-control" value="<?php echo htmlspecialchars($testUsername); ?>" required>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">Password</label>
                                            <input type="text" name="test_password" class="form-control" value="<?php echo htmlspecialchars($testPassword); ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-vial me-2"></i>Test
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Test Results -->
                        <?php if (isset($testResults['test_attempted'])): ?>
                        <div class="card mb-4">
                            <div class="card-header <?php echo isset($testResults['can_login']) && $testResults['can_login'] ? 'bg-success' : 'bg-danger'; ?> text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-<?php echo isset($testResults['can_login']) && $testResults['can_login'] ? 'check-circle' : 'times-circle'; ?> me-2"></i>
                                    Test Results
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered mb-0">
                                    <tr>
                                        <th width="250">Tested Username/Email:</th>
                                        <td><code><?php echo htmlspecialchars($testResults['test_username']); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Tested Password:</th>
                                        <td><code><?php echo htmlspecialchars($testResults['test_password']); ?></code></td>
                                    </tr>
                                    <tr class="<?php echo $testResults['user_found'] ? 'table-success' : 'table-danger'; ?>">
                                        <th>User Found in Database:</th>
                                        <td>
                                            <strong><?php echo $testResults['user_found'] ? '✅ YES' : '❌ NO'; ?></strong>
                                        </td>
                                    </tr>
                                    
                                    <?php if ($testResults['user_found']): ?>
                                    <tr>
                                        <th>User Details:</th>
                                        <td>
                                            <strong><?php echo htmlspecialchars($testResults['user_details']['full_name']); ?></strong><br>
                                            Username: <code><?php echo htmlspecialchars($testResults['user_details']['username']); ?></code><br>
                                            Email: <code><?php echo htmlspecialchars($testResults['user_details']['email']); ?></code><br>
                                            Role: <span class="badge bg-primary"><?php echo $testResults['user_details']['role']; ?></span>
                                        </td>
                                    </tr>
                                    <tr class="<?php echo $testResults['status_active'] ? 'table-success' : 'table-danger'; ?>">
                                        <th>Account Status:</th>
                                        <td>
                                            <strong><?php echo $testResults['status_active'] ? '✅ Active' : '❌ ' . $testResults['user_details']['status']; ?></strong>
                                        </td>
                                    </tr>
                                    <tr class="<?php echo $testResults['password_valid'] ? 'table-success' : 'table-danger'; ?>">
                                        <th>Password Verification:</th>
                                        <td>
                                            <strong><?php echo $testResults['password_valid'] ? '✅ CORRECT' : '❌ INCORRECT'; ?></strong>
                                        </td>
                                    </tr>
                                    <tr class="<?php echo $testResults['can_login'] ? 'table-success' : 'table-danger'; ?>">
                                        <th>Can Login:</th>
                                        <td>
                                            <strong><?php echo $testResults['can_login'] ? '✅ YES' : '❌ NO'; ?></strong>
                                            <?php if (!$testResults['can_login']): ?>
                                                <br><span class="text-danger">Reason: <?php echo htmlspecialchars($testResults['failure_reason']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <tr class="table-danger">
                                        <th>Problem:</th>
                                        <td><strong><?php echo htmlspecialchars($testResults['failure_reason']); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                                
                                <?php if (isset($testResults['can_login']) && $testResults['can_login']): ?>
                                <div class="alert alert-success mt-3 mb-0">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h5>Login Should Work!</h5>
                                    <p class="mb-2">These credentials are valid. Try logging in now:</p>
                                    <a href="login.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <h5>Fix Required</h5>
                                    <p>Use the "Force Reset Password" button below to fix this issue.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- All Users in Database -->
                        <?php if (!empty($testResults['all_users'])): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">All Users in Database</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Full Name</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($testResults['all_users'] as $user): ?>
                                            <tr class="<?php echo $user['role'] === 'admin' ? 'table-success' : ''; ?>">
                                                <td><?php echo $user['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo $user['role']; ?></span></td>
                                                <td><span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo $user['status']; ?></span></td>
                                                <td>
                                                    <form method="POST" action="force_reset_password.php" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="new_password" value="admin123">
                                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Reset password to admin123 for <?php echo htmlspecialchars($user['username']); ?>?')">
                                                            <i class="fas fa-key"></i> Reset to admin123
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                    <div class="card-footer">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                            </a>
                            <a href="reset_admin.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i>Run Reset Script
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

