<?php
$page_title = "Add New Announcement";

// Check if user is admin
if ($user['role'] !== 'admin') {
   header('Location: index.php?page=dashboard');
   exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // Get form data
   $title = trim($_POST['title']);
   $content = trim($_POST['content']);
   
   // Validate input
   $errors = [];
   
   if (empty($title)) {
       $errors[] = "Title is required";
   }
   
   if (empty($content)) {
       $errors[] = "Content is required";
   }
   
   // If no errors, create announcement
   if (empty($errors)) {
       try {
           // Begin transaction
           $pdo->beginTransaction();
           
           // Insert announcement - simplified to match database structure
           $stmt = $pdo->prepare("
               INSERT INTO announcements (
                   title, content, created_by, created_at
               ) VALUES (
                   ?, ?, ?, NOW()
               )
           ");
           
           $stmt->execute([
               $title, $content, $_SESSION['user_id']
           ]);
           
           $announcement_id = $pdo->lastInsertId();
           
           // Handle image upload for announcement_images table
           if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
               $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
               $file_type = $_FILES['image']['type'];
               $file_size = $_FILES['image']['size'];
               $max_size = 5 * 1024 * 1024; // 5MB
               
               // Validate file type and size
               if (!in_array($file_type, $allowed_types)) {
                   $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
               } elseif ($file_size > $max_size) {
                   $errors[] = "File size exceeds the maximum limit of 5MB.";
               } else {
                   // Create uploads directory if it doesn't exist
                   $upload_dir = 'uploads/announcements/';
                   if (!is_dir($upload_dir)) {
                       mkdir($upload_dir, 0755, true);
                   }
                   
                   // Generate unique filename
                   $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                   $new_filename = 'announcement_' . uniqid() . '.' . $file_extension;
                   $upload_path = $upload_dir . $new_filename;
                   
                   // Move uploaded file
                   if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                       // Insert into announcement_images table
                       $img_stmt = $pdo->prepare("
                           INSERT INTO announcement_images (
                               announcement_id, image_path
                           ) VALUES (?, ?)
                       ");
                       $img_stmt->execute([$announcement_id, $new_filename]);
                   } else {
                       $errors[] = "Failed to upload image.";
                   }
               }
           }
           
           // Create notification for all users
           $stmt = $pdo->prepare("
               INSERT INTO notifications (
                   recipient_id, created_by, title, message, is_read, created_at
               ) 
               SELECT id, ?, 'New Announcement', ?, 0, NOW()
               FROM users
               WHERE id != ? AND role != 'admin'
           ");

           $notification_message = "New announcement: {$title}";
           $stmt->execute([$_SESSION['user_id'], $notification_message, $_SESSION['user_id']]);
           
           // Commit transaction
           $pdo->commit();
           
           // Set success message and redirect
           $_SESSION['success_message'] = "Announcement created successfully.";
           header("Location: index.php?page=admin-announcements");
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
       <h1 class="page-title">Add New Announcement</h1>
       <a href="index.php?page=admin-announcements" class="btn btn-secondary">
           <i class="fas fa-arrow-left"></i> Back to Announcements
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
           <h2 class="card-title">Announcement Details</h2>
       </div>
       <div class="card-body">
           <form method="post" enctype="multipart/form-data" class="announcement-form">
               <div class="form-group">
                   <label for="title" class="form-label">Title</label>
                   <input type="text" id="title" name="title" class="form-input" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
               </div>
               
               <div class="form-group">
                   <label for="content" class="form-label">Content</label>
                   <textarea id="content" name="content" class="form-input" rows="6" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
               </div>
               
               <div class="form-group">
                   <label for="image" class="form-label">Image (optional)</label>
                   <div class="file-upload-container">
                       <input type="file" id="image" name="image" class="file-upload-input" accept="image/*">
                       <label for="image" class="file-upload-label">
                           <i class="fas fa-cloud-upload-alt"></i>
                           <span>Choose file</span>
                       </label>
                       <div class="file-upload-preview" id="imagePreview"></div>
                   </div>
                   <div class="form-hint">Recommended image size: 1200x600 pixels. Maximum file size: 5MB.</div>
               </div>
               
               <div class="form-actions">
                   <button type="submit" class="btn btn-primary">Create Announcement</button>
                   <a href="index.php?page=admin-announcements" class="btn btn-secondary">Cancel</a>
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
   .announcement-form {
       display: flex;
       flex-direction: column;
       gap: 24px;
   }

   .form-grid {
       display: grid;
       grid-template-columns: repeat(2, 1fr);
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

   .form-hint {
       font-size: 12px;
       color: var(--text-secondary);
       margin-top: 4px;
   }

   /* File Upload */
   .file-upload-container {
       position: relative;
       margin-bottom: 8px;
   }

   .file-upload-input {
       position: absolute;
       width: 0.1px;
       height: 0.1px;
       opacity: 0;
       overflow: hidden;
       z-index: -1;
   }

   .file-upload-label {
       display: flex;
       flex-direction: column;
       align-items: center;
       justify-content: center;
       padding: 32px;
       background-color: var(--sidebar-bg);
       border: 2px dashed var(--border-color);
       border-radius: var(--border-radius-md);
       cursor: pointer;
       transition: var(--transition);
   }

   .file-upload-label:hover {
       background-color: var(--hover-color);
   }

   .file-upload-label i {
       font-size: 32px;
       margin-bottom: 8px;
       color: var(--text-secondary);
   }

   .file-upload-preview {
       margin-top: 16px;
       max-width: 300px;
   }

   .preview-item {
       position: relative;
       border-radius: var(--border-radius-md);
       overflow: hidden;
       box-shadow: var(--shadow-sm);
   }

   .preview-item img {
       width: 100%;
       height: auto;
       display: block;
   }

   .preview-remove {
       position: absolute;
       top: 8px;
       right: 8px;
       width: 24px;
       height: 24px;
       border-radius: 50%;
       background-color: rgba(0, 0, 0, 0.5);
       color: white;
       display: flex;
       align-items: center;
       justify-content: center;
       font-size: 12px;
       cursor: pointer;
       transition: var(--transition);
   }

   .preview-remove:hover {
       background-color: rgba(211, 47, 47, 0.8);
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
       
       .form-grid {
           grid-template-columns: 1fr;
           gap: 16px;
       }
   }
</style>

<script>
   document.addEventListener('DOMContentLoaded', function() {
       // Image preview functionality
       const imageInput = document.getElementById('image');
       const imagePreview = document.getElementById('imagePreview');
       
       imageInput.addEventListener('change', function() {
           imagePreview.innerHTML = '';
           
           if (this.files && this.files[0]) {
               const file = this.files[0];
               
               if (!file.type.match('image.*')) {
                   return;
               }
               
               const reader = new FileReader();
               
               reader.onload = function(e) {
                   const previewItem = document.createElement('div');
                   previewItem.className = 'preview-item';
                   
                   const img = document.createElement('img');
                   img.src = e.target.result;
                   
                   const removeBtn = document.createElement('div');
                   removeBtn.className = 'preview-remove';
                   removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                   removeBtn.addEventListener('click', function(e) {
                       e.preventDefault();
                       previewItem.remove();
                       imageInput.value = '';
                   });
                   
                   previewItem.appendChild(img);
                   previewItem.appendChild(removeBtn);
                   imagePreview.appendChild(previewItem);
               }
               
               reader.readAsDataURL(file);
           }
       });
   });
</script>

