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
$token_valid = false;
$token = '';

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE reset_token = ? 
        AND reset_expires > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token_valid = true;
    } else {
        $message = "Invalid or expired token. Please request a new password reset link.";
        $message_type = "error";
    }
} else {
    $message = "Invalid or missing token.";
    $message_type = "error";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($password)) {
        $message = "Password is required.";
        $message_type = "error";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password and clear reset token
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_expires = NULL
            WHERE reset_token = ?
        ");
        $result = $stmt->execute([$hashed_password, $token]);
        
        if ($result) {
            $message = "Your password has been reset successfully. You can now login with your new password.";
            $message_type = "success";
            $token_valid = false; // Hide the form after successful reset
        } else {
            $message = "An error occurred. Please try again.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Aula Kost</title>
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
            margin-bottom: 24px;
            color: #333;
        }
        
        .auth-form input {
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
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
        
        .password-requirements {
            margin-bottom: 20px;
            padding: 12px 16px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        
        .password-requirements h4 {
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .password-requirements ul {
            padding-left: 20px;
            font-size: 13px;
            color: #666;
        }
        
        .password-requirements li {
            margin-bottom: 4px;
        }
        
        .password-requirements li.valid {
            color: #2e7d32;
        }
        
        .password-requirements li.valid::before {
            content: '✓ ';
            color: #2e7d32;
        }
        
        .password-strength {
            margin-top: 8px;
            margin-bottom: 16px;
        }
        
        .strength-bar {
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            margin-bottom: 4px;
        }
        
        .strength-indicator {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-text {
            font-size: 12px;
            color: #666;
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
            <img src="assets/images/login-image.jpg" alt="Reset Password Image">
        </div>
        <div class="form-container">
            <h2>Reset Password</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                    <?php if ($message_type === 'success' && !$token_valid): ?>
                        <div style="margin-top: 10px;"><a href="login.php" class="back-to-login">Click here to login</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($token_valid): ?>
                <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="post" class="auth-form">
                    <div class="password-container">
                        <input type="password" id="password" name="password" placeholder="New Password" required>
                        <span class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <div class="password-strength" id="password-strength">
                        <div class="strength-bar">
                            <div class="strength-indicator" id="strength-indicator"></div>
                        </div>
                        <div class="strength-text" id="strength-text">Password strength</div>
                    </div>
                    
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li id="req-length">At least 8 characters</li>
                            <li id="req-uppercase">At least one uppercase letter</li>
                            <li id="req-lowercase">At least one lowercase letter</li>
                            <li id="req-number">At least one number</li>
                            <li id="req-special">At least one special character</li>
                        </ul>
                    </div>
                    
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required>
                        <span class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <button type="submit" class="auth-btn">Reset Password</button>
                </form>
            <?php elseif ($message_type === 'error'): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="forgot-password.php" class="auth-btn" style="display: inline-block;">Request New Reset Link</a>
                </div>
            <?php endif; ?>
            
            <a href="login.php" class="back-to-login">Back to Login</a>
        </div>
    </div>
    
    <script>
        function togglePassword(id) {
            var passwordInput = document.getElementById(id);
            var toggleIcon = document.querySelector('#' + id + ' + .toggle-password i');
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const strengthIndicator = document.getElementById('strength-indicator');
            const strengthText = document.getElementById('strength-text');
            
            // Password requirement elements
            const reqLength = document.getElementById('req-length');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqNumber = document.getElementById('req-number');
            const reqSpecial = document.getElementById('req-special');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    // Check requirements
                    const hasLength = password.length >= 8;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasLowercase = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const hasSpecial = /[^A-Za-z0-9]/.test(password);
                    
                    // Update requirement indicators
                    reqLength.className = hasLength ? 'valid' : '';
                    reqUppercase.className = hasUppercase ? 'valid' : '';
                    reqLowercase.className = hasLowercase ? 'valid' : '';
                    reqNumber.className = hasNumber ? 'valid' : '';
                    reqSpecial.className = hasSpecial ? 'valid' : '';
                    
                    // Calculate strength
                    if (hasLength) strength += 20;
                    if (hasUppercase) strength += 20;
                    if (hasLowercase) strength += 20;
                    if (hasNumber) strength += 20;
                    if (hasSpecial) strength += 20;
                    
                    // Update strength indicator
                    strengthIndicator.style.width = strength + '%';
                    
                    // Set color based on strength
                    if (strength <= 20) {
                        strengthIndicator.style.backgroundColor = '#f44336'; // Very weak
                        strengthText.textContent = 'Very Weak';
                    } else if (strength <= 40) {
                        strengthIndicator.style.backgroundColor = '#ff9800'; // Weak
                        strengthText.textContent = 'Weak';
                    } else if (strength <= 60) {
                        strengthIndicator.style.backgroundColor = '#ffeb3b'; // Medium
                        strengthText.textContent = 'Medium';
                    } else if (strength <= 80) {
                        strengthIndicator.style.backgroundColor = '#8bc34a'; // Strong
                        strengthText.textContent = 'Strong';
                    } else {
                        strengthIndicator.style.backgroundColor = '#4caf50'; // Very strong
                        strengthText.textContent = 'Very Strong';
                    }
                });
            }
        });
    </script>
</body>
</html>

