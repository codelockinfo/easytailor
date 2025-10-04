<?php
/**
 * Force Reset Password
 * Directly resets a user's password
 */

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_POST['user_id'];
    $newPassword = $_POST['new_password'];
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = :password, status = 'active' WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            // Get username
            $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            header('Location: test_login.php?success=1&username=' . urlencode($user['username']) . '&password=' . urlencode($newPassword));
            exit;
        } else {
            header('Location: test_login.php?error=Failed+to+reset+password');
            exit;
        }
    } catch (Exception $e) {
        header('Location: test_login.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: test_login.php');
    exit;
}
?>

