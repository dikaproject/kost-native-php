<?php
// This file handles AJAX requests for the profile edit modal

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Return the modal HTML
    ?>
    <div class="modal-overlay" id="profileEditModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <button type="button" class="modal-close" id="closeProfileModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="index.php?page=edit-profile" enctype="multipart/form-data" class="profile-form">
                    <!-- Profile Image Section -->
                    <div class="profile-section">
                        <h3 class="section-title">Profile Image</h3>
                        
                        <div class="profile-image-upload">
                            <div class="current-image">
                                <img src="<?php echo $user['profile_image'] ? 'uploads/profiles/' . $user['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="Profile Image" id="profile-preview">
                            </div>
                            <div class="image-upload-controls">
                                <label for="profile_image" class="upload-btn">
                                    <i class="fas fa-camera"></i>
                                    Change Photo
                                </label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">
                                <p class="upload-hint">Recommended: Square image, at least 200x200 pixels</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personal Information Section -->
                    <div class="profile-section">
                        <h3 class="section-title">Personal Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Address Section -->
                    <div class="profile-section">
                        <h3 class="section-title">Address</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" id="country" name="country" class="form-input" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="city" class="form-label">City</label>
                                <input type="text" id="city" name="city" class="form-input" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="street" class="form-label">Street</label>
                                <input type="text" id="street" name="street" class="form-input" value="<?php echo htmlspecialchars($user['street'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-input" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" id="cancelProfileEdit">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal-container {
            background-color: var(--card-bg);
            border-radius: var(--border-radius-lg);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: var(--transition);
        }
        
        .modal-close:hover {
            color: var(--text-primary);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        /* Profile Form Styles */
        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
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
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 32px;
            height: 2px;
            background-color: var(--accent-color);
        }
        
        /* Profile Image Upload */
        .profile-image-upload {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .current-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 3px solid white;
        }
        
        .current-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-upload-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background-color: var(--sidebar-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .upload-btn:hover {
            background-color: var(--hover-color);
            transform: translateY(-2px);
        }
        
        .upload-hint {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
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
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .profile-image-upload {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
    </style>
    
    <script>
        // Profile image preview
        const profileInput = document.getElementById('profile_image');
        const profilePreview = document.getElementById('profile-preview');
        
        if (profileInput && profilePreview) {
            profileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Close modal handlers
        document.getElementById('closeProfileModal').addEventListener('click', function() {
            document.getElementById('profileEditModal').remove();
        });
        
        document.getElementById('cancelProfileEdit').addEventListener('click', function() {
            document.getElementById('profileEditModal').remove();
        });
        
        // Close modal when clicking outside
        document.getElementById('profileEditModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.remove();
            }
        });
    </script>
    <?php
    exit;
}

// If not an AJAX request, redirect to profile page
header('Location: index.php?page=profile');
exit;
?>

