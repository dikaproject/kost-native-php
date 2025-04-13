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
           // Generate a unique token
           $token = bin2hex(random_bytes(32));
           $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
           
           // Store token in database
           $stmt = $pdo->prepare("
               UPDATE users 
               SET reset_token = ?, reset_expires = ?
               WHERE id = ?
           ");
           $stmt->execute([$token, $expires, $user['id']]);
           
           // Redirect to reset password page with token
           header("Location: index.php?page=login-reset-password&token={$token}");
           exit;
       } else {
           $error = "No account found with that email address.";
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
           <p style="margin-bottom: 20px;">Enter your email address and we'll help you reset your password.</p>
           
           <?php if (isset($error)): ?>
           <div class="alert alert-danger">
               <?php echo $error; ?>
           </div>
           <?php endif; ?>
           
           <form action="index.php?page=login-forgot-password" method="post" class="auth-form">
               <input type="email" name="email" placeholder="Email" required>
               
               <button type="submit" class="auth-btn">Reset Password</button>
               
               <div style="margin-top: 20px; text-align: center;">
                   <a href="index.php?page=login" style="text-decoration: none; color: #000;">Back to Login</a>
               </div>
           </form>
       </div>
   </div>
</body>
</html>

