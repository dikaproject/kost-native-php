<?php
$page_title = "Edit Profile";

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Address information
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $postal_code = trim($_POST['postal_code']);
    $country = trim($_POST['country']);
    
    // Validate required fields
    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists (for another user)
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already in use";
        }
    }
    
    // Handle profile photo upload
    $profile_image = $user['profile_image']; // Default to current photo
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['size'] > 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_photo']['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG and GIF are allowed.";
        } elseif ($_FILES['profile_photo']['size'] > $max_size) {
            $errors[] = "File size too large. Maximum size is 5MB.";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                $profile_image = $filename;
                
                // Delete old profile photo if it exists and is not the default
                if (!empty($user['profile_image']) && $user['profile_image'] != 'default.jpg' && file_exists($upload_dir . $user['profile_image'])) {
                    unlink($upload_dir . $user['profile_image']);
                }
            } else {
                $errors[] = "Failed to upload profile photo. Please try again.";
            }
        }
    }
    
    // If no errors, update user profile
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if the users table has address fields
            $stmt = $pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build the SQL query based on available columns
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, profile_image = ?";
            $params = [$first_name, $last_name, $email, $phone, $profile_image];
            
            // Add address fields if they exist in the table
            if (in_array('street', $columns)) {
                $sql .= ", street = ?";
                $params[] = $address;
            }
            if (in_array('city', $columns)) {
                $sql .= ", city = ?";
                $params[] = $city;
            }
            if (in_array('state', $columns)) {
                $sql .= ", state = ?";
                $params[] = $state;
            }
            if (in_array('postal_code', $columns)) {
                $sql .= ", postal_code = ?";
                $params[] = $postal_code;
            }
            if (in_array('country', $columns)) {
                $sql .= ", country = ?";
                $params[] = $country;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $_SESSION['user_id'];
            
            // Execute the update
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // If address fields don't exist in users table, check for a separate addresses table
            if (!in_array('street', $columns)) {
                try {
                    // Check if addresses table exists
                    $stmt = $pdo->query("SHOW TABLES LIKE 'addresses'");
                    $addressTableExists = $stmt->rowCount() > 0;
                    
                    if ($addressTableExists) {
                        // Check if user already has an address
                        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $addressId = $stmt->fetchColumn();
                        
                        if ($addressId) {
                            // Update existing address
                            $stmt = $pdo->prepare("
                                UPDATE addresses 
                                SET address = ?, city = ?, state = ?, postal_code = ?, country = ?
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$address, $city, $state, $postal_code, $country, $_SESSION['user_id']]);
                        } else {
                            // Insert new address
                            $stmt = $pdo->prepare("
                                INSERT INTO addresses (user_id, address, city, state, postal_code, country)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$_SESSION['user_id'], $address, $city, $state, $postal_code, $country]);
                        }
                    }
                } catch (Exception $e) {
                    // Address table doesn't exist or other error - just continue
                    error_log("Error handling address: " . $e->getMessage());
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Update session variables
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Profile updated successfully";
            header("Location: index.php?page=profile");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get user address if it's in a separate table
$address = [
    'address' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => ''
];

// First check if address fields are in the users table
$hasAddressInUserTable = false;
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('street', $columns)) {
        $hasAddressInUserTable = true;
        $address = [
            'address' => $user['street'] ?? '',
            'city' => $user['city'] ?? '',
            'state' => $user['state'] ?? '',
            'postal_code' => $user['postal_code'] ?? '',
            'country' => $user['country'] ?? ''
        ];
    }
} catch (Exception $e) {
    // Table doesn't exist or other error
}

// If address is not in users table, check for a separate addresses table
if (!$hasAddressInUserTable) {
    try {
        // Check if addresses table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'addresses'");
        $addressTableExists = $stmt->rowCount() > 0;
        
        if ($addressTableExists) {
            $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $addressData = $stmt->fetch();
            
            if ($addressData) {
                $address = [
                    'address' => $addressData['address'] ?? '',
                    'city' => $addressData['city'] ?? '',
                    'state' => $addressData['state'] ?? '',
                    'postal_code' => $addressData['postal_code'] ?? '',
                    'country' => $addressData['country'] ?? ''
                ];
            }
        }
    } catch (Exception $e) {
        // Address table doesn't exist or other error - just continue with empty address
    }
}
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Edit Profile</h1>
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
    
    <form method="post" class="profile-form" enctype="multipart/form-data">
        <!-- Profile Photo Section -->
        <div class="profile-section">
            <h3 class="section-title">Profile Photo</h3>
            
            <div class="photo-upload-container">
                <div class="current-photo">
                    <img src="<?php echo !empty($user['profile_image']) ? 'uploads/profiles/' . $user['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" 
                         alt="Profile Photo" id="profile-preview">
                </div>
                <div class="photo-upload-controls">
                    <label for="profile_photo" class="btn btn-outline">
                        <i class="fas fa-upload"></i> Upload New Photo
                    </label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display: none;">
                    <p class="upload-hint">Maximum file size: 5MB. Supported formats: JPG, PNG, GIF</p>
                </div>
            </div>
        </div>
        
        <!-- Personal Information Section -->
        <div class="profile-section">
            <h3 class="section-title">Personal Information</h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-input" 
                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-input" 
                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" id="phone" name="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Address Information Section -->
        <div class="profile-section">
            <h3 class="section-title">Address Information</h3>
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" id="address" name="address" class="form-input" 
                           value="<?php echo htmlspecialchars($address['address']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="city" class="form-label">City</label>
                    <input type="text" id="city" name="city" class="form-input" 
                           value="<?php echo htmlspecialchars($address['city']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="state" class="form-label">State/Province</label>
                    <input type="text" id="state" name="state" class="form-input" 
                           value="<?php echo htmlspecialchars($address['state']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="postal_code" class="form-label">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" class="form-input" 
                           value="<?php echo htmlspecialchars($address['postal_code']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="country" class="form-label">Country</label>
                    <input type="text" id="country" name="country" class="form-input" 
                           value="<?php echo htmlspecialchars($address['country']); ?>">
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="index.php?page=profile" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
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

    /* Profile Form */
    .profile-form {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    /* Profile sections */
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
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 24px;
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

    /* Profile Photo Upload */
    .photo-upload-container {
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .current-photo {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid var(--accent-color);
        box-shadow: var(--shadow-sm);
    }

    .current-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-upload-controls {
        display: flex;
        flex-direction: column;
        gap: 8px;
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

    .form-group.full-width {
        grid-column: 1 / -1;
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

    .btn-outline {
        background-color: transparent;
        color: var(--accent-color);
        border: 1px solid var(--accent-color);
    }

    .btn-outline:hover {
        background-color: var(--accent-color);
        color: white;
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
        
        .photo-upload-container {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<script>
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
        
        // Profile photo preview
        const profileInput = document.getElementById('profile_photo');
        const profilePreview = document.getElementById('profile-preview');
        
        profileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
</script>

