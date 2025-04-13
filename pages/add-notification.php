<?php
$page_title = "Send New Notification";

// Check if user is admin
if ($user['role'] !== 'admin') {
   header('Location: index.php?page=dashboard');
   exit;
}

// Get all users for recipient selection
$stmt = $pdo->query("
   SELECT id, first_name, last_name, email, role
   FROM users
   WHERE id != {$_SESSION['user_id']} AND role != 'admin'
   ORDER BY first_name, last_name
");
$users = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // Get form data
   $recipients = isset($_POST['recipients']) ? $_POST['recipients'] : [];
   $send_to_all = isset($_POST['send_to_all']) ? true : false;
   $title = trim($_POST['title']);
   $message = trim($_POST['message']);
   $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
   
   // Validate input
   $errors = [];
   
   if (empty($title)) {
       $errors[] = "Title is required";
   }
   
   if (empty($message)) {
       $errors[] = "Message is required";
   }
   
   if (!$send_to_all && empty($recipients)) {
       $errors[] = "Please select at least one recipient or choose 'Send to all users'";
   }
   
   // If no errors, send notification
   if (empty($errors)) {
       try {
           // Begin transaction
           $pdo->beginTransaction();
           
           if ($send_to_all) {
               // Send to all users except admins
               $stmt = $pdo->prepare("
                   INSERT INTO notifications (
                       recipient_id, created_by, title, message, is_read, created_at, scheduled_at
                   ) 
                   SELECT id, ?, ?, ?, 0, NOW(), ?
                   FROM users
                   WHERE id != ? AND role != 'admin'
               ");
               
               $stmt->execute([$_SESSION['user_id'], $title, $message, $scheduled_at, $_SESSION['user_id']]);
           } else {
               // Send to selected users
               $stmt = $pdo->prepare("
                   INSERT INTO notifications (
                       recipient_id, created_by, title, message, is_read, created_at, scheduled_at
                   ) VALUES (?, ?, ?, ?, 0, NOW(), ?)
               ");
               
               foreach ($recipients as $recipient_id) {
                   $stmt->execute([$recipient_id, $_SESSION['user_id'], $title, $message, $scheduled_at]);
               }
           }
           
           // Commit transaction
           $pdo->commit();
           
           // Set success message and redirect
           $_SESSION['success_message'] = "Notification sent successfully.";
           header("Location: index.php?page=admin-notifications");
           exit;
       } catch (Exception $e) {
           // Rollback transaction on error
           $pdo->rollBack();
           $errors[] = "An error occurred: " . $e->getMessage();
       }
   }
}
?>

<div class="page-content">
   <div class="page-header">
       <h1 class="page-title">Send New Notification</h1>
       <a href="index.php?page=admin-notifications" class="btn btn-secondary">
           <i class="fas fa-arrow-left"></i> Back to Notifications
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
   
   <div class="card">
       <div class="card-header">
           <h2 class="card-title">Notification Details</h2>
       </div>
       <div class="card-body">
           <form method="post" class="notification-form">
               <div class="form-group">
                   <label class="form-label">Recipients</label>
                   <div class="recipients-container">
                       <div class="send-to-all">
                           <label class="checkbox-label">
                               <input type="checkbox" name="send_to_all" id="sendToAll" <?php echo isset($_POST['send_to_all']) ? 'checked' : ''; ?>>
                               <span>Send to all users</span>
                           </label>
                       </div>
                       
                       <div class="recipients-list" id="recipientsList" <?php echo isset($_POST['send_to_all']) ? 'style="display: none;"' : ''; ?>>
                           <div class="recipients-search">
                               <input type="text" id="recipientSearch" placeholder="Search users..." class="form-input">
                           </div>
                           
                           <div class="recipients-grid">
                               <?php foreach ($users as $user_item): ?>
                                   <div class="recipient-item" data-name="<?php echo strtolower($user_item['first_name'] . ' ' . $user_item['last_name']); ?>">
                                       <label class="checkbox-label">
                                           <input type="checkbox" name="recipients[]" value="<?php echo $user_item['id']; ?>" <?php echo isset($_POST['recipients']) && in_array($user_item['id'], $_POST['recipients']) ? 'checked' : ''; ?>>
                                           <span><?php echo $user_item['first_name'] . ' ' . $user_item['last_name']; ?></span>
                                           <small>(<?php echo $user_item['email']; ?>)</small>
                                       </label>
                                   </div>
                               <?php endforeach; ?>
                           </div>
                       </div>
                   </div>
               </div>
               
               <div class="form-group">
                   <label for="title" class="form-label">Notification Title</label>
                   <input type="text" id="title" name="title" class="form-input" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                   <div class="form-hint">Examples: Payment Reminder, Maintenance Notice, Welcome to Aula Kost</div>
               </div>

               <div class="form-group">
                   <label for="message" class="form-label">Message</label>
                   <textarea id="message" name="message" class="form-input" rows="6" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
               </div>

               <div class="form-group">
                   <label for="scheduled_at" class="form-label">Schedule (Optional)</label>
                   <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-input" value="<?php echo isset($_POST['scheduled_at']) ? htmlspecialchars($_POST['scheduled_at']) : ''; ?>">
                   <div class="form-hint">Leave empty to send immediately</div>
               </div>
               
               <div class="form-actions">
                   <button type="submit" class="btn btn-primary">Send Notification</button>
                   <a href="index.php?page=admin-notifications" class="btn btn-secondary">Cancel</a>
               </div>
           </form>
       </div>
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

   /* Card Styles */
   .card {
       background-color: var(--card-bg);
       border-radius: var(--border-radius-lg);
       border: 1px solid var(--border-color);
       margin-bottom: 24px;
       overflow: hidden;
   }

   .card-header {
       padding: 20px 24px;
       border-bottom: 1px solid var(--border-color);
   }

   .card-title {
       font-size: 18px;
       font-weight: 600;
       margin: 0;
   }

   .card-body {
       padding: 24px;
   }

   /* Form Styles */
   .notification-form {
       display: flex;
       flex-direction: column;
       gap: 24px;
   }

   .form-group {
       display: flex;
       flex-direction: column;
       gap: 8px;
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
   }

   .form-input:focus {
       outline: none;
       border-color: var(--accent-color);
       box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
   }

   textarea.form-input {
       resize: vertical;
       min-height: 100px;
   }

   /* Recipients Container */
   .recipients-container {
       display: flex;
       flex-direction: column;
       gap: 16px;
   }

   .send-to-all {
       padding: 12px 16px;
       background-color: var(--sidebar-bg);
       border-radius: var(--border-radius-md);
   }

   .checkbox-label {
       display: flex;
       align-items: center;
       gap: 8px;
       cursor: pointer;
   }

   .checkbox-label input[type="checkbox"] {
       width: 16px;
       height: 16px;
   }

   .recipients-list {
       border: 1px solid var(--border-color);
       border-radius: var(--border-radius-md);
       overflow: hidden;
   }

   .recipients-search {
       padding: 12px 16px;
       border-bottom: 1px solid var(--border-color);
   }

   .recipients-search input {
       width: 100%;
       padding: 8px 12px;
       border: 1px solid var(--border-color);
       border-radius: var(--border-radius-md);
       font-size: 14px;
   }

   .recipients-search input:focus {
       outline: none;
       border-color: var(--accent-color);
   }

   .recipients-grid {
       display: grid;
       grid-template-columns: repeat(2, 1fr);
       gap: 8px;
       padding: 16px;
       max-height: 300px;
       overflow-y: auto;
   }

   .recipient-item {
       padding: 8px 12px;
       border-radius: var(--border-radius-md);
       transition: var(--transition);
   }

   .recipient-item:hover {
       background-color: var(--sidebar-bg);
   }

   .recipient-item small {
       color: var(--text-secondary);
       font-size: 12px;
       margin-left: 4px;
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
       
       .recipients-grid {
           grid-template-columns: 1fr;
       }
   }
</style>

<script>
   document.addEventListener('DOMContentLoaded', function() {
       // Send to all checkbox
       const sendToAllCheckbox = document.getElementById('sendToAll');
       const recipientsList = document.getElementById('recipientsList');
       
       if (sendToAllCheckbox && recipientsList) {
           sendToAllCheckbox.addEventListener('change', function() {
               if (this.checked) {
                   recipientsList.style.display = 'none';
               } else {
                   recipientsList.style.display = 'block';
               }
           });
       }
       
       // Recipients search
       const recipientSearch = document.getElementById('recipientSearch');
       const recipientItems = document.querySelectorAll('.recipient-item');
       
       if (recipientSearch && recipientItems.length > 0) {
           recipientSearch.addEventListener('input', function() {
               const searchTerm = this.value.toLowerCase();
               
               recipientItems.forEach(item => {
                   const name = item.getAttribute('data-name');
                   
                   if (name.includes(searchTerm)) {
                       item.style.display = 'block';
                   } else {
                       item.style.display = 'none';
                   }
               });
           });
       }
   });
</script>

