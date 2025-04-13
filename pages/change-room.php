<?php
$page_title = "Change Room";

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if 'type' column exists in notifications table
$typeColumnExists = false;
try {
    $checkTypeColumn = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'type'");
    $typeColumnExists = $checkTypeColumn->rowCount() > 0;
} catch (PDOException $e) {
    // Column doesn't exist
}

// Check if user is tenant
$stmt = $pdo->prepare("
  SELECT t.*, r.name as room_name, r.price, r.id as room_id
  FROM tenants t 
  JOIN rooms r ON t.room_id = r.id 
  WHERE t.user_id = ? AND t.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$current_tenant = $stmt->fetch();

// If not a tenant, redirect to profile
if (!$current_tenant) {
  $_SESSION['error_message'] = "You don't have an active room to change.";
  header("Location: index.php?page=profile");
  exit;
}

// Get available rooms
$stmt = $pdo->query("
  SELECT r.*, 
         (SELECT ri.image_path FROM room_images ri WHERE ri.room_id = r.id AND ri.is_primary = 1 LIMIT 1) as image
  FROM rooms r
  WHERE r.status = 'available'
  ORDER BY r.price ASC
");
$available_rooms = $stmt->fetchAll();

// Process room change request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $new_room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
  $move_date = $_POST['move_date'];
  $reason = trim($_POST['reason']);
  
  // Validate input
  $errors = [];
  
  if (empty($new_room_id)) {
      $errors[] = "Please select a new room";
  }
  
  if (empty($move_date)) {
      $errors[] = "Please select a move date";
  } elseif (strtotime($move_date) < strtotime('tomorrow')) {
      $errors[] = "Move date must be at least tomorrow";
  }
  
  if (empty($reason)) {
      $errors[] = "Please provide a reason for changing rooms";
  }
  
  // Check if selected room exists and is available
  if (!empty($new_room_id)) {
      $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'available'");
      $stmt->execute([$new_room_id]);
      $new_room = $stmt->fetch();
      
      if (!$new_room) {
          $errors[] = "Selected room is not available";
      }
  }
  
  // If no errors, create room change request
  if (empty($errors)) {
      try {
          // Begin transaction
          $pdo->beginTransaction();
          
          // Update the current room to be available
          $stmt = $pdo->prepare("
              UPDATE rooms 
              SET status = 'available'
              WHERE id = ?
          ");
          $stmt->execute([$current_tenant['room_id']]);
          
          // Update the new room to be occupied
          $stmt = $pdo->prepare("
              UPDATE rooms 
              SET status = 'occupied'
              WHERE id = ?
          ");
          $stmt->execute([$new_room_id]);
          
          // Update tenant record
          $stmt = $pdo->prepare("
              UPDATE tenants
              SET room_id = ?, start_date = ?
              WHERE id = ?
          ");
          $stmt->execute([$new_room_id, $move_date, $current_tenant['id']]);
          
          // Create notification for admin
          if ($typeColumnExists) {
              $stmt = $pdo->prepare("
                  INSERT INTO notifications (
                      title, message, recipient_id, is_read, created_by, created_at, type
                  ) VALUES (
                      'Room Change', ?, 1, 0, ?, NOW(), 'room_change'
                  )
              ");
          } else {
              $stmt = $pdo->prepare("
                  INSERT INTO notifications (
                      title, message, recipient_id, is_read, created_by, created_at
                  ) VALUES (
                      'Room Change', ?, 1, 0, ?, NOW()
                  )
              ");
          }

          // Get the name of the new room
          $roomNameStmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
          $roomNameStmt->execute([$new_room_id]);
          $newRoomName = $roomNameStmt->fetchColumn();

          $message = "Room change: " . $user['first_name'] . " " . $user['last_name'] . " has changed from " . 
                     $current_tenant['room_name'] . " to " . $newRoomName;

          $stmt->execute([$message, $_SESSION['user_id']]);
          
          // Commit transaction
          $pdo->commit();
          
          // Set success message and redirect
          $_SESSION['success_message'] = "Room changed successfully! Your new room is now ready.";
          header("Location: index.php?page=profile");
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
  <?php if (isset($errors) && !empty($errors)): ?>
      <div class="alert alert-danger">
          <ul>
              <?php foreach ($errors as $error): ?>
                  <li><?php echo $error; ?></li>
              <?php endforeach; ?>
          </ul>
      </div>
  <?php endif; ?>

  <div class="profile-header-section">
      <h1 class="page-title">Change Room</h1>
      <a href="index.php?page=profile" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to Profile
      </a>
  </div>
  
  <div class="profile-section">
      <h3 class="section-title">Current Room</h3>
      
      <div class="current-room-info">
          <div class="room-details">
              <div class="form-grid">
                  <div class="form-group">
                      <div class="form-label">Room</div>
                      <div class="form-value"><?php echo $current_tenant['room_name']; ?></div>
                  </div>
                  <div class="form-group">
                      <div class="form-label">Monthly Rent</div>
                      <div class="form-value">IDR <?php echo number_format($current_tenant['price'], 0, ',', '.'); ?></div>
                  </div>
                  <div class="form-group">
                      <div class="form-label">Active Since</div>
                      <div class="form-value"><?php echo date('d F Y', strtotime($current_tenant['start_date'])); ?></div>
                  </div>
                  <div class="form-group">
                      <div class="form-label">Status</div>
                      <div class="form-value"><span class="status-badge active">Active</span></div>
                  </div>
              </div>
          </div>
      </div>
  </div>
  
  <div class="profile-section">
      <h3 class="section-title">Available Rooms</h3>
      
      <?php if (empty($available_rooms)): ?>
          <div class="no-rooms-message">
              <i class="fas fa-exclamation-circle"></i>
              <p>No rooms are currently available for change. Please check back later.</p>
          </div>
      <?php else: ?>
          <form method="post" class="change-room-form">
              <div class="available-rooms-grid">
                  <?php foreach ($available_rooms as $room): ?>
                      <div class="room-card">
                          <input type="radio" name="room_id" id="room_<?php echo $room['id']; ?>" value="<?php echo $room['id']; ?>" class="room-radio">
                          <label for="room_<?php echo $room['id']; ?>" class="room-label">
                              <div class="room-image">
                                  <img src="<?php echo $room['image'] ? 'uploads/rooms/' . $room['image'] : 'assets/images/default-room.jpg'; ?>" alt="<?php echo $room['name']; ?>">
                              </div>
                              <div class="room-info">
                                  <div class="room-name"><?php echo $room['name']; ?></div>
                                  <div class="room-price">IDR <?php echo number_format($room['price'], 0, ',', '.'); ?> / month</div>
                                  <div class="room-features">
                                      <?php
                                      // Get room features
                                      $stmt = $pdo->prepare("SELECT feature_name FROM room_features WHERE room_id = ? LIMIT 3");
                                      $stmt->execute([$room['id']]);
                                      $features = $stmt->fetchAll();
                                      
                                      foreach ($features as $feature): ?>
                                          <span class="feature-tag"><?php echo $feature['feature_name']; ?></span>
                                      <?php endforeach; ?>
                                  </div>
                              </div>
                              <div class="room-select">
                                  <span class="select-indicator"></span>
                              </div>
                          </label>
                      </div>
                  <?php endforeach; ?>
              </div>
              
              <div class="profile-section inner-section">
                  <h3 class="section-title">Change Details</h3>
                  
                  <div class="form-grid">
                      <div class="form-group">
                          <label for="move_date" class="form-label">Preferred Move Date</label>
                          <input type="date" id="move_date" name="move_date" class="form-input" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                      </div>
                      
                      <div class="form-group">
                          <label for="reason" class="form-label">Reason for Change</label>
                          <textarea id="reason" name="reason" class="form-input" rows="4" required></textarea>
                      </div>
                  </div>
                  
                  <div class="form-notice">
                      <i class="fas fa-info-circle"></i>
                      <p>Room change will be effective immediately. Your current room will be marked as available for others.</p>
                  </div>
                  
                  <div class="form-actions">
                      <button type="submit" class="btn btn-primary">Confirm Change</button>
                      <a href="index.php?page=profile" class="btn btn-secondary">Cancel</a>
                  </div>
              </div>
          </form>
      <?php endif; ?>
  </div>
</div>

<style>
  /* Page content */
  .page-content {
      margin-bottom: 24px;
  }

  .profile-header-section {
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

  /* Profile sections */
  .profile-section {
      background-color: var(--card-bg);
      border-radius: var(--border-radius-lg);
      border: 1px solid var(--border-color);
      padding: 24px;
      margin-bottom: 24px;
      position: relative;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
  }

  .profile-section:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
  }

  .inner-section {
      border: none;
      box-shadow: none;
      padding: 0;
      margin-top: 24px;
  }

  .inner-section:hover {
      box-shadow: none;
      transform: none;
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
      background-color: #000;
  }

  /* Current Room Info */
  .current-room-info {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
  }

  .room-details {
      flex: 1;
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

  .form-value {
      font-weight: 500;
      font-size: 16px;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--border-color);
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
      border-color: #000;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
  }

  textarea.form-input {
      resize: vertical;
      min-height: 100px;
  }

  .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 50px;
      font-size: 12px;
      font-weight: 500;
  }

  .status-badge.active {
      background-color: rgba(46, 125, 50, 0.1);
      color: #2e7d32;
  }

  /* No Rooms Message */
  .no-rooms-message {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 32px;
      background-color: var(--sidebar-bg);
      border-radius: var(--border-radius-md);
  }

  .no-rooms-message i {
      font-size: 48px;
      color: var(--text-secondary);
      margin-bottom: 16px;
      opacity: 0.5;
  }

  .no-rooms-message p {
      color: var(--text-secondary);
  }

  /* Available Rooms */
  .available-rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 24px;
      margin-bottom: 24px;
  }

  .room-card {
      position: relative;
  }

  .room-radio {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
  }

  .room-label {
      display: flex;
      flex-direction: column;
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius-md);
      overflow: hidden;
      cursor: pointer;
      transition: var(--transition);
      background-color: #fff;
  }

  .room-label:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-sm);
  }

  .room-radio:checked + .room-label {
      border-color: #000;
      box-shadow: 0 0 0 2px #000;
  }

  .room-image {
      height: 160px;
      overflow: hidden;
  }

  .room-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: var(--transition);
  }

  .room-label:hover .room-image img {
      transform: scale(1.05);
  }

  .room-info {
      padding: 16px;
  }

  .room-name {
      font-weight: 600;
      font-size: 16px;
      margin-bottom: 8px;
  }

  .room-price {
      color: #000;
      font-weight: 500;
      margin-bottom: 12px;
  }

  .room-features {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
  }

  .feature-tag {
      display: inline-block;
      padding: 4px 8px;
      background-color: var(--sidebar-bg);
      border-radius: 4px;
      font-size: 12px;
  }

  .room-select {
      display: flex;
      justify-content: flex-end;
      padding: 8px 16px;
      border-top: 1px solid var(--border-color);
  }

  .select-indicator {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 2px solid var(--border-color);
      position: relative;
      transition: var(--transition);
  }

  .room-radio:checked + .room-label .select-indicator {
      border-color: #000;
      background-color: #000;
  }

  .room-radio:checked + .room-label .select-indicator::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: white;
  }

  /* Form Notice */
  .form-notice {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 16px;
      background-color: rgba(33, 150, 243, 0.1);
      border-radius: var(--border-radius-md);
      margin-bottom: 24px;
  }

  .form-notice i {
      color: #2196f3;
      font-size: 18px;
      margin-top: 2px;
  }

  .form-notice p {
      margin: 0;
      font-size: 14px;
      line-height: 1.5;
  }

  /* Form Actions */
  .form-actions {
      display: flex;
      gap: 16px;
      margin-top: 24px;
  }

  /* Button Styles */
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
      background-color: #000;
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
      .profile-header-section {
          flex-direction: column;
          align-items: flex-start;
          gap: 16px;
      }
      
      .form-grid {
          grid-template-columns: 1fr;
          gap: 16px;
      }
      
      .available-rooms-grid {
          grid-template-columns: 1fr;
      }
      
      .current-room-info {
          flex-direction: column;
      }
      
      .form-actions {
          flex-direction: column;
      }
      
      .btn {
          width: 100%;
      }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add subtle hover effects
    const sections = document.querySelectorAll('.profile-section:not(.inner-section)');
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
    
    // Room selection functionality
    const roomRadios = document.querySelectorAll('.room-radio');
    
    roomRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove active class from all room cards
            document.querySelectorAll('.room-label').forEach(label => {
                label.classList.remove('active');
            });
            
            // Add active class to selected room card
            if (this.checked) {
                this.nextElementSibling.classList.add('active');
            }
        });
    });
});
</script>

