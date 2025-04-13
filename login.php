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

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $email = $_POST['email'];
   $password = $_POST['password'];
   
   // Validate input
   if (empty($email) || empty($password)) {
       $error = "Please enter both email and password.";
   } else {
       // Check if user exists
       $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
       $stmt->execute([$email]);
       $user = $stmt->fetch();
       
       if ($user) {
           // For the admin account with plain text password in the database
           if ($user['id'] == 1 && $password === $user['password']) {
               // Set session variables
               $_SESSION['user_id'] = $user['id'];
               $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
               $_SESSION['user_role'] = $user['role'];
               
               // Redirect based on role
               if ($user['role'] === 'admin') {
                   header('Location: index.php?page=admin-dashboard');
               } else {
                   header('Location: index.php?page=dashboard');
               }
               exit;
           }
           // For regular users with hashed passwords
           else if (password_verify($password, $user['password'])) {
               // Set session variables
               $_SESSION['user_id'] = $user['id'];
               $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
               $_SESSION['user_role'] = $user['role'];
               
               // Redirect based on role
               if ($user['role'] === 'admin') {
                   header('Location: index.php?page=admin-dashboard');
               } else {
                   header('Location: index.php?page=dashboard');
               }
               exit;
           } else {
               $error = "Invalid email or password.";
           }
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
       
       .forgot-password {
           display: block;
           text-align: right;
           margin-bottom: 24px;
           color: #666;
           text-decoration: none;
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
       }
       
       .auth-btn:hover {
           background-color: #333;
       }
       
       .TampilanAtau {
           display: flex;
           align-items: center;
           margin: 24px 0;
       }
       
       .TampilanAtau hr {
           flex: 1;
           border: none;
           height: 1px;
           background-color: #ddd;
       }
       
       .TampilanAtau p {
           margin: 0 16px;
           color: #666;
       }
       
       .social-login, .social-register {
           display: flex;
           align-items: center;
           justify-content: center;
           width: 100%;
           padding: 12px;
           margin-bottom: 12px;
           border: 1px solid #ddd;
           border-radius: 8px;
           background-color: #fff;
           font-size: 14px;
           cursor: pointer;
           transition: background-color 0.3s;
       }
       
       .social-login:hover, .social-register:hover {
           background-color: #f5f5f5;
       }
       
       .social-login img, .social-register img {
           width: 20px;
           height: 20px;
           margin-right: 12px;
       }
       
       .alert {
           padding: 12px 16px;
           margin-bottom: 16px;
           border-radius: 8px;
           font-size: 14px;
       }
       
       .alert-danger {
           background-color: #f8d7da;
           color: #721c24;
           border: 1px solid #f5c6cb;
       }
       
       .alert-success {
           background-color: #d4edda;
           color: #155724;
           border: 1px solid #c3e6cb;
       }
       
       .password-requirements {
           margin-bottom: 16px;
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
           <img src="./assets/images/login-images.png" alt="Login Image">
       </div>
       <div class="form-container">
           <h2>Welcome Back!</h2>
           
           <?php if (!empty($error)): ?>
           <div class="alert alert-danger">
               <?php echo $error; ?>
           </div>
           <?php endif; ?>
           
           <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
           <div class="alert alert-success">
               Your password has been reset successfully. You can now login with your new password.
           </div>
           <?php endif; ?>
           
           <form action="login.php" method="post" class="auth-form">
               <input type="email" name="email" placeholder="Email" required>
               <div class="password-container">
                   <input type="password" id="password" name="password" placeholder="Password" required>
                   <span class="toggle-password" onclick="togglePassword('password')"><i class="fas fa-eye"></i></span>
               </div>
               <a href="index.php?page=login-forgot-password" class="forgot-password">Forgot password?</a>
               
               <button type="submit" class="auth-btn">Login</button>
               
               <div class="TampilanAtau">
                   <hr>
                   <p>or</p>
                   <hr>
               </div>
               
               <p style="text-align: center; margin-bottom: 16px;">Don't have an account? <a href="register.php" style="text-decoration: none; color: black;"><b>Sign Up</b></a></p>
               
               <p style="text-align: center; margin-top: 12px;">
                   <a href="landing.php" style="text-decoration: none; color: #666;">Back to Home</a>
               </p>
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

