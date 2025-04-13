<?php
$page_title = "Edit Room";

// Check if user is admin
if ($user['role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

// Get room ID from URL
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get room details
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

// If room not found, redirect to rooms page
if (!$room) {
    $_SESSION['error_message'] = "Room not found";
    header('Location: index.php?page=admin-rooms');
    exit;
}

// Get room images
$stmt = $pdo->prepare("SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC");
$stmt->execute([$room_id]);
$room_images = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $room_number = trim($_POST['room_number']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $primary_image = isset($_POST['primary_image']) ? intval($_POST['primary_image']) : 0;
    $delete_images = isset($_POST['delete_images']) ? $_POST['delete_images'] : [];
    
    // Validate input
    $errors = [];
    
    if (empty($room_number)) {
        $errors[] = "Room number is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    // Check if room number already exists (excluding current room)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE name = ? AND id != ?");
    $stmt->execute([$room_number, $room_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Room number already exists";
    }
    
    // If no errors, update room
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update room
            $stmt = $pdo->prepare("
                UPDATE rooms 
                SET name = ?, price = ?, description = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$room_number, $price, $description, $status, $room_id]);
            
            // Handle primary image
            if ($primary_image > 0) {
                // First, reset all images to non-primary
                $stmt = $pdo->prepare("UPDATE room_images SET is_primary = 0 WHERE room_id = ?");
                $stmt->execute([$room_id]);
                
                // Then, set the selected image as primary
                $stmt = $pdo->prepare("UPDATE room_images SET is_primary = 1 WHERE id = ? AND room_id = ?");
                $stmt->execute([$primary_image, $room_id]);
            }
            
            // Handle image deletion
            if (!empty($delete_images)) {
                foreach ($delete_images as $image_id) {
                    // Get image path
                    $stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE id = ? AND room_id = ?");
                    $stmt->execute([$image_id, $room_id]);
                    $image_path = $stmt->fetchColumn();
                    
                    if ($image_path) {
                        // Delete file
                        $file_path = 'uploads/rooms/' . $image_path;
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                        
                        // Delete from database
                        $stmt = $pdo->prepare("DELETE FROM room_images WHERE id = ? AND room_id = ?");
                        $stmt->execute([$image_id, $room_id]);
                    }
                }
            }
            
            // Handle new image uploads
            if (isset($_FILES['room_images']) && !empty($_FILES['room_images']['name'][0])) {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/rooms/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                $image_stmt = $pdo->prepare("
                    INSERT INTO room_images (room_id, image_path, is_primary, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                
                foreach ($_FILES['room_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['room_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_type = $_FILES['room_images']['type'][$key];
                        $file_size = $_FILES['room_images']['size'][$key];
                        $file_name = $_FILES['room_images']['name'][$key];
                        
                        // Validate file type and size
                        if (!in_array($file_type, $allowed_types)) {
                            $errors[] = "File '{$file_name}' is not a valid image type";
                            continue;
                        }
                        
                        if ($file_size > $max_size) {
                            $errors[] = "File '{$file_name}' exceeds the maximum size of 5MB";
                            continue;
                        }
                        
                        // Generate unique filename
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $new_filename = 'room_' . $room_id . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            // Set as primary if no images exist
                            $is_primary = (count($room_images) === 0 && count($delete_images) === count($room_images)) ? 1 : 0;
                            $image_stmt->execute([$room_id, $new_filename, $is_primary]);
                        } else {
                            $errors[] = "Failed to upload file '{$file_name}'";
                        }
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Room updated successfully";
            header("Location: index.php?page=admin-rooms");
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
        <h1 class="page-title">Edit Room</h1>
        <a href="index.php?page=admin-rooms" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Rooms
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
            <h2 class="card-title">Room Details</h2>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="room-form">
                <div class="form-group">
                    <label for="room_number" class="form-label">Room Number</label>
                    <input type="text" id="room_number" name="room_number" class="form-input" value="<?php echo htmlspecialchars($room['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price" class="form-label">Price (IDR)</label>
                    <input type="number" id="price" name="price" class="form-input" value="<?php echo htmlspecialchars($room['price']); ?>" min="0" step="10000" required>
                </div>
                
                <div class="form-group">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="available" <?php echo $room['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="occupied" <?php echo $room['status'] === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-input" rows="4"><?php echo htmlspecialchars($room['description']); ?></textarea>
                </div>
                
                <?php if (!empty($room_images)): ?>
                <div class="form-group">
                    <label class="form-label">Current Images</label>
                    <div class="current-images">
                        <?php foreach ($room_images as $image): ?>
                            <div class="current-image-item">
                                <div class="image-container">
                                    <img src="uploads/rooms/<?php echo $image['image_path']; ?>" alt="Room Image">
                                    <?php if ($image['is_primary']): ?>
                                        <div class="primary-badge">Primary</div>
                                    <?php endif; ?>
                                </div>
                                <div class="image-actions">
                                    <div class="image-action">
                                        <input type="radio" id="primary_<?php echo $image['id']; ?>" name="primary_image" value="<?php echo $image['id']; ?>" <?php echo $image['is_primary'] ? 'checked' : ''; ?>>
                                        <label for="primary_<?php echo $image['id']; ?>">Set as Primary</label>
                                    </div>
                                    <div class="image-action">
                                        <input type="checkbox" id="delete_<?php echo $image['id']; ?>" name="delete_images[]" value="<?php echo $image['id']; ?>">
                                        <label for="delete_<?php echo $image['id']; ?>">Delete</label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="room_images" class="form-label">Add New Images</label>
                    <div class="file-upload-container">
                        <input type="file" id="room_images" name="room_images[]" class="file-upload-input" multiple accept="image/*">
                        <label for="room_images" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose files</span>
                        </label>
                        <div class="file-upload-preview" id="imagePreview"></div>
                    </div>
                    <div class="form-hint">You can upload multiple images. If no images exist, the first image will be used as the primary image.</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Room</button>
                    <a href="index.php?page=admin-rooms" class="btn btn-secondary">Cancel</a>
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
    .room-form {
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

    .form-hint {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 4px;
    }

    /* Current Images */
    .current-images {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }

    .current-image-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        overflow: hidden;
        transition: var(--transition);
    }

    .current-image-item:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-sm);
    }

    .image-container {
        position: relative;
        height: 150px;
    }

    .image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .primary-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 4px 8px;
        background-color: var(--accent-color);
        color: white;
        font-size: 12px;
        border-radius: 4px;
    }

    .image-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 12px;
    }

    .image-action {
        display: flex;
        align-items: center;
        gap: 8px;
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
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 16px;
    }

    .preview-item {
        position: relative;
        width: 100px;
        height: 100px;
        border-radius: var(--border-radius-md);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .preview-remove {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
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
        
        .current-images {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Image preview functionality
        const imageInput = document.getElementById('room_images');
        const imagePreview = document.getElementById('imagePreview');
        
        imageInput.addEventListener('change', function() {
            imagePreview.innerHTML = '';
            
            if (this.files) {
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    
                    if (!file.type.match('image.*')) {
                        continue;
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
                        removeBtn.addEventListener('click', function() {
                            previewItem.remove();
                        });
                        
                        previewItem.appendChild(img);
                        previewItem.appendChild(removeBtn);
                        imagePreview.appendChild(previewItem);
                    }
                    
                    reader.readAsDataURL(file);
                }
            }
        });
    });
</script>

