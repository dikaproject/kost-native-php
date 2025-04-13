<?php
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    // Validate input
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Store token in database
            $stmt = $pdo->prepare("
                UPDATE users 
                SET reset_token = ?, reset_expires = ?
                WHERE id = ?
            ");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // In a real application, you would send an email with the reset link
            // For this example, we'll just show the link
            $reset_link = "index.php?page=reset-password&token={$token}";
            $success = "A password reset link has been sent to your email address. The link will expire in 1 hour.";
            
            // For demonstration purposes only
            $demo_link = "<a href='{$reset_link}'>Click here to reset your password</a> (This is for demonstration only. In a real application, this link would be sent to your email.)";
        } else {
            // Don't reveal that the email doesn't exist
            $success = "If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Aula Kost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="image-container">
            <img src="assets/images/login-image.jpg" alt="Forgot Password Image">
        </div>
        <div class="form-container">
            <h2>Forgot Password</h2>
            <p style="margin-bottom: 20px;">Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <?php if (isset($demo_link)): ?>
                <div style="margin-top: 10px;"><?php echo $demo_link; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <form action="index.php?page=forgot-password" method="post" class="auth-form">
                <input type="email" name="email" placeholder="Email" required>
                
                <button type="submit" class="auth-btn">Send Reset Link</button>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="index.php?page=login" style="text-decoration: none; color: #000;">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

