<?php
/**
 * Fix User Session Issue
 * This script helps diagnose and fix user session problems
 */

require_once '../config/database.php';

session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix User Session</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="mb-0">
                            <i class="fas fa-user-cog me-2"></i>User Session Diagnostic
                        </h3>
                    </div>
                    <div class="card-body">
                        <h5>Current Session Information</h5>
                        <?php
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-bordered">';
                        echo '<tr><th width="200">Session ID</th><td>' . session_id() . '</td></tr>';
                        echo '<tr><th>User ID in Session</th><td>' . ($_SESSION['user_id'] ?? 'NOT SET') . '</td></tr>';
                        echo '<tr><th>Username</th><td>' . ($_SESSION['username'] ?? 'NOT SET') . '</td></tr>';
                        echo '<tr><th>User Name</th><td>' . ($_SESSION['user_name'] ?? 'NOT SET') . '</td></tr>';
                        echo '<tr><th>User Role</th><td>' . ($_SESSION['user_role'] ?? 'NOT SET') . '</td></tr>';
                        echo '</table>';
                        echo '</div>';
                        
                        // Check users in database
                        try {
                            $db = new Database();
                            $conn = $db->getConnection();
                            
                            echo '<hr>';
                            echo '<h5>Users in Database</h5>';
                            
                            $stmt = $conn->query("SELECT id, username, full_name, role, status FROM users ORDER BY id");
                            $users = $stmt->fetchAll();
                            
                            if (empty($users)) {
                                echo '<div class="alert alert-danger">';
                                echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                                echo '<strong>No users found!</strong> You need to create at least one user.';
                                echo '</div>';
                                
                                echo '<div class="card bg-light">';
                                echo '<div class="card-body">';
                                echo '<h6>Create Default Admin User</h6>';
                                echo '<p class="mb-2">Run this SQL in phpMyAdmin:</p>';
                                echo '<pre class="bg-dark text-light p-3 rounded"><code>';
                                echo "INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`) VALUES\n";
                                echo "('admin', 'admin@tailoring.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active');";
                                echo '</code></pre>';
                                echo '<p class="mb-0"><small>Password: <code>password</code></small></p>';
                                echo '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped">';
                                echo '<thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr></thead>';
                                echo '<tbody>';
                                
                                $sessionUserId = $_SESSION['user_id'] ?? null;
                                $sessionUserExists = false;
                                
                                foreach ($users as $user) {
                                    $highlight = '';
                                    if ($sessionUserId && $user['id'] == $sessionUserId) {
                                        $highlight = 'table-success';
                                        $sessionUserExists = true;
                                    }
                                    
                                    echo '<tr class="' . $highlight . '">';
                                    echo '<td>' . $user['id'] . '</td>';
                                    echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                                    echo '<td>' . htmlspecialchars($user['full_name']) . '</td>';
                                    echo '<td><span class="badge bg-primary">' . $user['role'] . '</span></td>';
                                    echo '<td><span class="badge bg-' . ($user['status'] === 'active' ? 'success' : 'secondary') . '">' . $user['status'] . '</span></td>';
                                    echo '</tr>';
                                }
                                
                                echo '</tbody>';
                                echo '</table>';
                                echo '</div>';
                                
                                // Check if session user exists
                                if ($sessionUserId && !$sessionUserExists) {
                                    echo '<div class="alert alert-danger">';
                                    echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                                    echo '<strong>Problem Found!</strong> Your session user ID (' . $sessionUserId . ') does not exist in the database.';
                                    echo '</div>';
                                    
                                    echo '<div class="alert alert-warning">';
                                    echo '<strong>Solution:</strong> Logout and login again with a valid user account.';
                                    echo '</div>';
                                } else if ($sessionUserId) {
                                    echo '<div class="alert alert-success">';
                                    echo '<i class="fas fa-check-circle me-2"></i>';
                                    echo 'Your session user ID exists in the database. Highlighted in green above.';
                                    echo '</div>';
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                        
                        <hr>
                        
                        <h5>Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="logout.php" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout (Will clear session)
                            </a>
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>Try Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <strong>Common Causes:</strong>
                            <ul class="mb-0 mt-2">
                                <li>User was deleted from database but session still active</li>
                                <li>Database was reset but sessions not cleared</li>
                                <li>No users exist in database</li>
                            </ul>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

