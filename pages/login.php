<?php
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30); // 30 days
                
                // Store token in database
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET remember_token = ?, remember_expires = ?
                    WHERE id = ?
                ");
                $stmt->execute([$token, date('Y-m-d H:i:s', $expires), $user['id']]);
                
                // Set cookie
                setcookie('remember_token', $token, $expires, '/');
            }
            
            // Redirect to dashboard
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aula Kost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="image-container">
            <img src="../assets/images/login-images.png" alt="Login Image">
        </div>
        <div class="form-container">
            <h2>Welcome Back !!</h2>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form action="index.php?page=login" method="post" class="auth-form">
                <input type="email" name="email" placeholder="Email" required>
                <div class="password-container">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <span class="toggle-password" onclick="togglePassword('password')"><i class="fas fa-eye"></i></span>
                </div>
                <a href="index.php?page=forgot-password" class="forgot-password">Forgot password?</a>
                
                <button type="submit" class="auth-btn">Login</button>
                
                <div class="TampilanAtau">
                    <hr>
                    <p>or</p>
                    <hr>
                </div>
                
                <p>Don't have an account? <a href="index.php?page=register" style="text-decoration: none; color: black;"><b>Sign Up</b></a></p>
                
                <button type="button" class="social-login google">
                    <img src="assets/images/google.png" alt="Google"> Continue with Google
                </button>
                <button type="button" class="social-login twitter">
                    <img src="assets/images/twitter.png" alt="Twitter"> Continue with Twitter
                </button>
            </form>
        </div>
    </div>
    
    <script>
    function togglePassword(id) {
        var passwordInput = document.getElementById(id);
        var toggleIcon = document.querySelector('.toggle-password i');
        
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
    </script>
</body>
</html>

