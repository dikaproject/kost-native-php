<?php
// Start session
session_start();

// Database connection
require_once 'config/database.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Validate input
    if (empty($email)) {
        $message = "Please enter your email address.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = "error";
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
            $reset_link = "reset-password.php?token={$token}";
            
            $message = "A password reset link has been sent to your email address. The link will expire in 1 hour.";
            $message_type = "success";
            
            // For demonstration purposes only
            $demo_link = "<a href='{$reset_link}' class='reset-link'>Click here to reset your password</a> (This is for demonstration only. In a real application, this link would be sent to your email.)";
        } else {
            // Don't reveal that the email doesn't exist
            $message = "If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.";
            $message_type = "success";
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .auth-container {
            display: flex;
            width: 900px;
            max-width: 100%;
            background-color: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .image-container {
            flex: 1;
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.8;
        }
        
        .form-container {
            flex: 1;
            padding: 40px;
        }
        
        h2 {
            font-size: 28px;
            margin-bottom: 12px;
            color: #333;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        
        .auth-form input {
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .auth-btn {
            width: 100%;
            padding: 12px;
            background-color: #000;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-bottom: 20px;
        }
        
        .auth-btn:hover {
            background-color: #333;
        }
        
        .back-to-login {
            display: block;
            text-align: center;
            color: #000;
            text-decoration: none;
            font-size: 14px;
            margin-top: 16px;
        }
        
        .back-to-login:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .reset-link {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 500;
        }
        
        .reset-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                width: 100%;
                height: 100vh;
                border-radius: 0;
            }
            
            .image-container {
                height: 200px;
            }
            
            .form-container {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="image-container">
            <img src="assets/images/login-image.jpg" alt="Forgot Password Image">
        </div>
        <div class="form-container">
            <h2>Forgot Password</h2>
            <p class="subtitle">Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                    <?php if (isset($demo_link)): ?>
                        <div style="margin-top: 10px;"><?php echo $demo_link; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form action="forgot-password.php" method="post" class="auth-form">
                <input type="email" name="email" placeholder="Email" required>
                
                <button type="submit" class="auth-btn">Send Reset Link</button>
                
                <a href="login.php" class="back-to-login">Back to Login</a>
            </form>
        </div>
    </div>
</body>
</html>

