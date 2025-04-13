<?php
$page_title = "Change Password";

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // Validate form data
   $current_password = $_POST['current_password'];
   $new_password = $_POST['new_password'];
   $confirm_password = $_POST['confirm_password'];
   
   // Validate required fields
   $errors = [];
   if (empty($current_password)) {
       $errors[] = "Current password is required";
   }
   
   if (empty($new_password)) {
       $errors[] = "New password is required";
   } elseif (strlen($new_password) < 6) {
       $errors[] = "New password must be at least 6 characters";
   }
   
   if ($new_password !== $confirm_password) {
       $errors[] = "New passwords do not match";
   }
   
   // Verify current password using password_verify
   if (empty($errors)) {
       // Use password_verify to check hashed password
       if (!password_verify($current_password, $user['password'])) {
           $errors[] = "Current password is incorrect";
       }
   }
   
   // If no errors, update password
   if (empty($errors)) {
       // Hash the new password before storing it
       $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
       
       $stmt = $pdo->prepare("
           UPDATE users 
           SET password = ?
           WHERE id = ?
       ");
       
       $stmt->execute([$hashed_password, $_SESSION['user_id']]);
       
       // Set success message and redirect
       $_SESSION['success_message'] = "Password updated successfully";
       header("Location: index.php?page=profile");
       exit;
   }
}
?>

<div class="page-content">
   <div class="page-header">
       <h1 class="page-title">Change Password</h1>
       <a href="index.php?page=profile" class="btn btn-secondary">
           <i class="fas fa-arrow-left"></i> Back to Profile
       </a>
   </div>
   
   <?php if (isset($errors) && !empty($errors)): ?>
       <div class="alert alert-danger">
           <ul>
               <?php foreach ($errors as $error): ?>
                   <li><?php echo $error; ?></li>
               <?php endforeach; ?>
           </ul>
       </div>
   <?php endif; ?>
   
   <div class="profile-section">
       <form method="post" class="password-form">
           <div class="form-group">
               <label for="current_password" class="form-label">Current Password</label>
               <div class="password-input-container">
                   <input type="password" id="current_password" name="current_password" class="form-input" required>
                   <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                       <i class="fas fa-eye"></i>
                   </button>
               </div>
           </div>
           
           <div class="form-group">
               <label for="new_password" class="form-label">New Password</label>
               <div class="password-input-container">
                   <input type="password" id="new_password" name="new_password" class="form-input" required>
                   <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                       <i class="fas fa-eye"></i>
                   </button>
               </div>
           </div>
           
           <div class="form-group">
               <label for="confirm_password" class="form-label">Confirm New Password</label>
               <div class="password-input-container">
                   <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                   <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                       <i class="fas fa-eye"></i>
                   </button>
               </div>
           </div>
           
           <div class="form-actions">
               <button type="submit" class="btn btn-primary">Update Password</button>
               <a href="index.php?page=profile" class="btn btn-secondary">Cancel</a>
           </div>
       </form>
   </div>
</div>

<style>
   /* Page content */
   .page-content {
       margin-bottom: 24px;
   }

   .page-header {
       display: flex;
       justify-content: space-between;
       align-items: center;
       margin-bottom: 24px;
   }

   .page-title {
       font-size: 28px;
       font-weight: 700;
       margin-bottom: 0;
       letter-spacing: -0.5px;
   }

   /* Alert Styles */
   .alert {
       padding: 16px;
       border-radius: var(--border-radius-md);
       margin-bottom: 24px;
   }

   .alert-danger {
       background-color: rgba(211, 47, 47, 0.1);
       color: #d32f2f;
       border: 1px solid rgba(211, 47, 47, 0.2);
   }

   .alert ul {
       margin: 0;
       padding-left: 20px;
   }

   /* Profile Section */
   .profile-section {
       background-color: var(--card-bg);
       border-radius: var(--border-radius-lg);
       border: 1px solid var(--border-color);
       padding: 24px;
       position: relative;
       box-shadow: var(--shadow-sm);
       transition: var(--transition);
   }

   .profile-section:hover {
       box-shadow: var(--shadow-md);
       transform: translateY(-2px);
   }

   /* Form Styles */
   .password-form {
       max-width: 600px;
       margin: 0 auto;
   }

   .form-group {
       display: flex;
       flex-direction: column;
       gap: 8px;
       margin-bottom: 24px;
   }

   .form-label {
       font-size: 14px;
       color: var(--text-secondary);
       font-weight: 500;
   }

   .form-input {
       padding: 12px 16px;
       border: 1px solid var(--border-color);
       border-radius: var(--border-radius-md);
       font-size: 14px;
       transition: var(--transition);
       width: 100%;
   }

   .form-input:focus {
       outline: none;
       border-color: var(--accent-color);
       box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
   }

   /* Password Input */
   .password-input-container {
       position: relative;
       display: flex;
       align-items: center;
   }

   .password-toggle {
       position: absolute;
       right: 12px;
       background: none;
       border: none;
       color: var(--text-secondary);
       cursor: pointer;
       font-size: 16px;
       padding: 0;
   }

   .password-toggle:hover {
       color: var(--text-primary);
   }

   /* Form Actions */
   .form-actions {
       display: flex;
       gap: 16px;
       margin-top: 16px;
   }

   .btn {
       display: inline-flex;
       align-items: center;
       justify-content: center;
       padding: 12px 24px;
       border-radius: var(--border-radius-md);
       font-weight: 500;
       cursor: pointer;
       transition: var(--transition);
       border: none;
       text-decoration: none;
   }

   .btn i {
       margin-right: 8px;
   }

   .btn-primary {
       background-color: var(--accent-color);
       color: white;
   }

   .btn-primary:hover {
       background-color: #333;
       transform: translateY(-2px);
       box-shadow: var(--shadow-sm);
   }

   .btn-secondary {
       background-color: var(--sidebar-bg);
       color: var(--text-primary);
       border: 1px solid var(--border-color);
   }

   .btn-secondary:hover {
       background-color: var(--hover-color);
       transform: translateY(-2px);
   }

   @media (max-width: 768px) {
       .page-header {
           flex-direction: column;
           align-items: flex-start;
           gap: 16px;
       }
   }
</style>

<script>
   function togglePassword(id) {
       const passwordInput = document.getElementById(id);
       const toggleButton = passwordInput.nextElementSibling.querySelector('i');
       
       if (passwordInput.type === 'password') {
           passwordInput.type = 'text';
           toggleButton.classList.remove('fa-eye');
           toggleButton.classList.add('fa-eye-slash');
       } else {
           passwordInput.type = 'password';
           toggleButton.classList.remove('fa-eye-slash');
           toggleButton.classList.add('fa-eye');
       }
   }
   
   document.addEventListener('DOMContentLoaded', function() {
       // Add subtle hover effects
       const sections = document.querySelectorAll('.profile-section');
       sections.forEach(section => {
           section.addEventListener('mouseenter', function() {
               this.style.transform = 'translateY(-5px)';
               this.style.boxShadow = 'var(--shadow-md)';
           });
           section.addEventListener('mouseleave', function() {
               this.style.transform = 'translateY(0)';
               this.style.boxShadow = 'var(--shadow-sm)';
           });
       });
   });
</script>

