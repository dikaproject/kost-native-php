<?php
// Check if user is already logged in
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE remember_token = ? 
        AND remember_expires > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        // Renew token if needed
        $expires = time() + (86400 * 30); // 30 days
        
        // Update token in database
        $stmt = $pdo->prepare("
            UPDATE users 
            SET remember_expires = ?
            WHERE id = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s', $expires), $user['id']]);
        
        // Renew cookie
        setcookie('remember_token', $token, $expires, '/');
    }
}
?>

