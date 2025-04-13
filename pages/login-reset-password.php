<?php
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
   header('Location: index.php?page=dashboard');
   exit;
}

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
   $error = "Invalid or missing token.";
} else {
   $token = $_GET['token'];
   
   // Check if token exists and is valid
   $stmt = $pdo->prepare("
       SELECT id FROM users 
       WHERE reset_token = ? 
       AND reset_expires > NOW()
   ");
   $stmt->execute([$token]);
   $user = $stmt->fetch();
   
   if (!$user) {
       $error = "Invalid or expired token. Please request a new password reset.";
   }
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
   $password = $_POST['password'];
   $confirm_password = $_POST['confirm_password'];
   
   // Validate input
   if (empty($password)) {
       $error = "Password is required.";
   } elseif (strlen($password) < 8) {
       $error = "Password must be at least 8 characters.";
   } elseif ($password !== $confirm_password) {
       $error = "Passwords do not match.";
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
           $success = "Your password has been reset successfully. You can now login with your new password.";
       } else {
           $error = "An error occurred. Please try again.";
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
   <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
   <div class="auth-container">
       <div class="image-container">
           <img src="assets/images/login-image.jpg" alt="Reset Password Image">
       </div>
       <div class="form-container">
           <h2>Create New Password</h2>
           
           <?php if (isset($error)): ?>
           <div class="alert alert-danger">
               <?php echo $error; ?>
           </div>
           <?php endif; ?>
           
           <?php if (isset($success)): ?>
           <div class="alert alert-success">
               <?php echo $success; ?>
               <div style="margin-top: 10px;"><a href="index.php?page=login">Click here to login</a></div>
           </div>
           <?php else: ?>
               <?php if (!isset($error) || strpos($error, "Invalid or expired token") === false): ?>
               <form action="index.php?page=login-reset-password&token=<?php echo htmlspecialchars($token); ?>" method="post" class="auth-form">
                   <div class="password-container">
                       <input type="password" id="password" name="password" placeholder="New Password" required>
                       <span class="toggle-password" onclick="togglePassword('password')"><i class="fas fa-eye"></i></span>
                   </div>
                   
                   <div class="password-container">
                       <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required>
                       <span class="toggle-password" onclick="togglePassword('confirm_password')"><i class="fas fa-eye"></i></span>
                   </div>
                   
                   <div class="password-requirements">
                       <p>Password must be at least 8 characters long.</p>
                   </div>
                   
                   <button type="submit" class="auth-btn">Set New Password</button>
               </form>
               <?php else: ?>
               <div style="margin-top: 20px; text-align: center;">
                   <a href="index.php?page=login-forgot-password" class="auth-btn" style="display: inline-block;">Request New Reset Link</a>
               </div>
               <?php endif; ?>
           <?php endif; ?>
           
           <div style="margin-top: 20px; text-align: center;">
               <a href="index.php?page=login" style="text-decoration: none; color: #000;">Back to Login</a>
           </div>
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
   </script>
</body>
</html>

