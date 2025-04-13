<?php
$page_title = "Edit Announcement";

// Check if user is admin
if ($user['role'] !== 'admin') {
   header('Location: index.php?page=dashboard');
   exit;
}

// Get announcement ID from URL
$announcement_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get announcement details with image
$stmt = $pdo->prepare("
    SELECT a.*, 
    (SELECT image_path FROM announcement_images WHERE announcement_id = a.id LIMIT 1) as image
    FROM announcements a
    WHERE a.id = ?
");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch();

// If announcement not found, redirect to announcements page
if (!$announcement) {
   $_SESSION['error_message'] = "Announcement not found";
   header('Location: index.php?page=admin-announcements');
   exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // Get form data
   $title = trim($_POST['title']);
   $content = trim($_POST['content']);
   
   // Additional form fields - these should be handled only if they exist in your database
   // Uncomment if these fields are in your announcements table
   /*
   $date = $_POST['date'] ?? null;
   $time = $_POST['time'] ?? '';
   $location = trim($_POST['location'] ?? '');
   $category = $_POST['category'] ?? null;
   $contact = trim($_POST['contact'] ?? '');
   */
   
   $delete_image = isset($_POST['delete_image']) ? true : false;
   
   // Validate input
   $errors = [];
   
   if (empty($title)) {
       $errors[] = "Title is required";
   }
   
   if (empty($content)) {
       $errors[] = "Content is required";
   }
   
   // If no errors, update announcement
   if (empty($errors)) {
       try {
           // Begin transaction
           $pdo->beginTransaction();
           
           // Get current image
           $stmt = $pdo->prepare("SELECT image_path FROM announcement_images WHERE announcement_id = ? LIMIT 1");
           $stmt->execute([$announcement_id]);
           $currentImage = $stmt->fetchColumn();
           
           // Delete existing image if requested
           if ($delete_image && $currentImage) {
               // Delete the image file
               $image_path = 'uploads/announcements/' . $currentImage;
               if (file_exists($image_path)) {
                   unlink($image_path);
               }
               
               // Delete the image record
               $stmt = $pdo->prepare("DELETE FROM announcement_images WHERE announcement_id = ?");
               $stmt->execute([$announcement_id]);
           }
           
           // Handle new image upload
           $new_image_uploaded = false;
           $new_filename = null;
           
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
                       $new_image_uploaded = true;
                       
                       // Delete old image file if exists
                       if ($currentImage && !$delete_image) {
                           $old_image_path = 'uploads/announcements/' . $currentImage;
                           if (file_exists($old_image_path)) {
                               unlink($old_image_path);
                           }
                       }
                   } else {
                       $errors[] = "Failed to upload image.";
                   }
               }
           }
           
           if (empty($errors)) {
               // Update announcement
               $stmt = $pdo->prepare("
                   UPDATE announcements 
                   SET title = ?, content = ?, updated_at = NOW()
                   WHERE id = ?
               ");
               
               $stmt->execute([
                   $title, $content, $announcement_id
               ]);
               
               // Handle image in announcement_images table
               if ($new_image_uploaded) {
                   if ($currentImage && !$delete_image) {
                       // Update existing image record
                       $stmt = $pdo->prepare("
                           UPDATE announcement_images 
                           SET image_path = ? 
                           WHERE announcement_id = ?
                       ");
                       $stmt->execute([$new_filename, $announcement_id]);
                   } else {
                       // Insert new image record
                       $stmt = $pdo->prepare("
                           INSERT INTO announcement_images (announcement_id, image_path)
                           VALUES (?, ?)
                       ");
                       $stmt->execute([$announcement_id, $new_filename]);
                   }
               }
               
               // Commit transaction
               $pdo->commit();
               
               // Set success message and redirect
               $_SESSION['success_message'] = "Announcement updated successfully.";
               header("Location: index.php?page=admin-announcements");
               exit;
           }
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
       <h1 class="page-title">Edit Announcement</h1>
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
                   <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
               </div>
               
               <div class="form-group">
                   <label for="content" class="form-label">Content</label>
                   <textarea id="content" name="content" class="form-input" rows="6" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
               </div>
               
               <?php
               // Uncomment this section if these fields exist in your database
               /*
               <div class="form-grid">
                   <div class="form-group">
                       <label for="date" class="form-label">Date</label>
                       <input type="date" id="date" name="date" class="form-input" value="<?php echo htmlspecialchars($announcement['date'] ?? ''); ?>" required>
                   </div>
                   
                   <div class="form-group">
                       <label for="time" class="form-label">Time (optional)</label>
                       <input type="text" id="time" name="time" class="form-input" value="<?php echo htmlspecialchars($announcement['time'] ?? ''); ?>" placeholder="e.g., 09:00 AM - 06:00 PM">
                   </div>
                   
                   <div class="form-group">
                       <label for="location" class="form-label">Location (optional)</label>
                       <input type="text" id="location" name="location" class="form-input" value="<?php echo htmlspecialchars($announcement['location'] ?? ''); ?>" placeholder="e.g., Building A & B">
                   </div>
                   
                   <div class="form-group">
                       <label for="category" class="form-label">Category</label>
                       <select id="category" name="category" class="form-input" required>
                           <option value="Policy Update" <?php echo ($announcement['category'] ?? '') === 'Policy Update' ? 'selected' : ''; ?>>Policy Update</option>
                           <option value="Maintenance" <?php echo ($announcement['category'] ?? '') === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                           <option value="Event" <?php echo ($announcement['category'] ?? '') === 'Event' ? 'selected' : ''; ?>>Event</option>
                           <option value="Upgrade" <?php echo ($announcement['category'] ?? '') === 'Upgrade' ? 'selected' : ''; ?>>Upgrade</option>
                           <option value="Other" <?php echo ($announcement['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                       </select>
                   </div>
                   
                   <div class="form-group">
                       <label for="contact" class="form-label">Contact (optional)</label>
                       <input type="text" id="contact" name="contact" class="form-input" value="<?php echo htmlspecialchars($announcement['contact'] ?? ''); ?>" placeholder="e.g., Management Office">
                   </div>
               </div>
               */
               ?>
               
               <div class="form-group">
                   <label class="form-label">Current Image</label>
                   <?php if ($announcement['image']): ?>
                       <div class="current-image">
                           <img src="uploads/announcements/<?php echo $announcement['image']; ?>" alt="<?php echo $announcement['title']; ?>">
                           <div class="image-actions">
                               <label class="checkbox-label">
                                   <input type="checkbox" name="delete_image" id="delete_image">
                                   <span>Delete current image</span>
                               </label>
                           </div>
                       </div>
                   <?php else: ?>
                       <p>No image uploaded</p>
                   <?php endif; ?>
               </div>
               
               <div class="form-group">
                   <label for="image" class="form-label">Upload New Image (optional)</label>
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
                   <button type="submit" class="btn btn-primary">Update Announcement</button>
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

   /* Current Image */
   .current-image {
       max-width: 300px;
       border-radius: var(--border-radius-md);
       overflow: hidden;
       box-shadow: var(--shadow-sm);
       margin-bottom: 16px;
   }

   .current-image img {
       width: 100%;
       height: auto;
       display: block;
   }

   .image-actions {
       padding: 12px;
       background-color: var(--sidebar-bg);
   }

   .checkbox-label {
       display: flex;
       align-items: center;
       gap: 8px;
       cursor: pointer;
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
       const deleteImageCheckbox = document.getElementById('delete_image');
       
       imageInput.addEventListener('change', function() {
           imagePreview.innerHTML = '';
           
           if (this.files && this.files[0]) {
               const file = this.files[0];
               
               if (!file.type.match('image.*')) {
                   return;
               }
               
               // If a new image is selected, uncheck the delete checkbox
               if (deleteImageCheckbox) {
                   deleteImageCheckbox.checked = false;
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

