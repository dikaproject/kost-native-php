<?php
// This is an AJAX modal page, so we don't need the header/footer
// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if rooms table exists
$roomsExist = false;
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'rooms'");
    $roomsExist = $checkTable->rowCount() > 0;
} catch (PDOException $e) {
    // Table doesn't exist
    echo '<div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Room Management Not Available</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> The room management system is not yet set up. Please import the room tables SQL file to enable room management.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>';
    exit;
}

// Get current room information if user has a room
$currentRoom = null;
if (isset($user['room_id']) && $user['room_id']) {
    $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $roomStmt->execute([$user['room_id']]);
    $currentRoom = $roomStmt->fetch();
}

// Get available rooms - FIXED: Removed ORDER BY room_type
$availableRoomsStmt = $pdo->prepare("
    SELECT * FROM rooms 
    WHERE status = 'available' OR id = ? 
    ORDER BY id
");
$availableRoomsStmt->execute([$user['room_id'] ?? 0]);
$availableRooms = $availableRoomsStmt->fetchAll();

// Check if there are any available rooms
if (count($availableRooms) == 0 && !$currentRoom) {
    echo '<div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">No Rooms Available</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> There are no rooms available at the moment. Please check back later.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>';
    exit;
}
?>

<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-door-open"></i> Change Room</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <div class="modal-body">
        <?php if ($currentRoom): ?>
        <div class="dashboard-card current-room">
            <div class="card-header">
                <h2 class="card-title">Your Current Room</h2>
                <div class="card-icon">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
            <div class="room-number"><?php echo isset($currentRoom['room_number']) ? $currentRoom['room_number'] : 'Room '.$currentRoom['id']; ?></div>
            <div class="room-status">Active since <?php echo date('d M Y'); ?></div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> You don't have a room assigned yet. Please select a room below.
        </div>
        <?php endif; ?>
        
        <form id="changeRoomForm">
            <div class="form-section-title">
                <h2>Select New Room</h2>
            </div>
            
            <div class="form-group">
                <select class="form-control custom-select" id="newRoom" name="new_room_id" required>
                    <option value="">-- Select a Room --</option>
                    <?php foreach ($availableRooms as $room): ?>
                        <?php if ($currentRoom && $room['id'] == $currentRoom['id']): ?>
                            <option value="<?php echo $room['id']; ?>" 
                                    data-type="<?php echo isset($room['room_type']) ? $room['room_type'] : 'Standard Room'; ?>" 
                                    data-price="<?php echo isset($room['price']) ? $room['price'] : '0'; ?>" selected>
                                <?php echo isset($room['room_number']) ? $room['room_number'] : 'Room '.$room['id']; ?> (Your Current Room)
                            </option>
                        <?php else: ?>
                            <option value="<?php echo $room['id']; ?>" 
                                    data-type="<?php echo isset($room['room_type']) ? $room['room_type'] : 'Standard Room'; ?>" 
                                    data-price="<?php echo isset($room['price']) ? $room['price'] : '0'; ?>">
                                <?php echo isset($room['room_number']) ? $room['room_number'] : 'Room '.$room['id']; ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="selectedRoomDetails" class="dashboard-card selected-room" style="display: none;">
                <div class="card-header">
                    <h2 class="card-title">Selected Room</h2>
                    <div class="card-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                <div class="room-number" id="selectedRoomNumber"></div>
                <div class="room-price" id="selectedRoomPrice"></div>
            </div>
            
            <div class="form-section-title">
                <h2>Reason for Change</h2>
            </div>
            
            <div class="form-group">
                <textarea class="form-control" id="changeReason" name="change_reason" rows="3" placeholder="Why are you changing rooms? (Optional)"></textarea>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Changing your room will be effective immediately. Your current room will be marked as available for others.
            </div>
            
            <input type="hidden" name="current_room_id" value="<?php echo $currentRoom ? $currentRoom['id'] : ''; ?>">
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-dismiss="modal">
            <i class="fas fa-times"></i> Cancel
        </button>
        <button type="button" class="btn btn-primary" id="confirmRoomChange">
            <i class="fas fa-check"></i> Confirm Change
        </button>
    </div>
</div>

<style>
    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        background-color: #f8f9fa;
    }
    
    .modal-header {
        background-color: #6c5ce7;
        color: white;
        padding: 16px 24px;
        border-bottom: none;
        align-items: center;
    }
    
    .modal-title {
        font-weight: 600;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #e9ecef;
        background-color: white;
    }
    
    .close {
        color: white;
        opacity: 0.8;
        font-size: 24px;
        transition: all 0.2s;
    }
    
    .close:hover {
        color: white;
        opacity: 1;
    }
    
    /* Dashboard Card Styles */
    .dashboard-card {
        background-color: #fff;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    }
    
    .dashboard-card.current-room {
        background-color: #000;
        color: white;
    }
    
    .dashboard-card.selected-room {
        background-color: #fff;
        border: 1px solid #e9ecef;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .card-title {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }
    
    .card-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .selected-room .card-icon {
        background-color: rgba(0, 0, 0, 0.05);
    }
    
    .room-number {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    
    .room-status, .room-price {
        font-size: 14px;
        opacity: 0.8;
    }
    
    /* Form Styles */
    .form-section-title {
        margin: 24px 0 16px;
    }
    
    .form-section-title h2 {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin: 0;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        background-color: white;
        color: #2d3436;
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }
    
    .form-control:focus {
        border-color: #6c5ce7;
        box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        outline: none;
    }
    
    .custom-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c5ce7' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        background-size: 16px;
        padding-right: 40px;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
        cursor: pointer;
        gap: 8px;
    }
    
    .btn-primary {
        background-color: #6c5ce7;
        color: white;
        border: none;
    }
    
    .btn-primary:hover {
        background-color: #5649c0;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(108, 92, 231, 0.2);
    }
    
    .btn-outline {
        background-color: transparent;
        color: #6c5ce7;
        border: 1px solid #6c5ce7;
    }
    
    .btn-outline:hover {
        background-color: rgba(108, 92, 231, 0.05);
    }
    
    /* Alerts */
    .alert {
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    
    .alert i {
        font-size: 18px;
        margin-top: 2px;
    }
    
    .alert-info {
        background-color: rgba(85, 171, 255, 0.1);
        color: #0984e3;
        border: 1px solid rgba(85, 171, 255, 0.2);
    }
    
    .alert-warning {
        background-color: rgba(253, 203, 110, 0.1);
        color: #e17055;
        border: 1px solid rgba(253, 203, 110, 0.2);
    }
    
    /* Selected Room Details */
    .selected-room-details {
        margin-top: 24px;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<script>
    $(document).ready(function() {
        // Show selected room details when a room is selected
        $('#newRoom').change(function() {
            var selectedOption = $(this).find('option:selected');
            if (selectedOption.val()) {
                var roomNumber = selectedOption.text().split(' ')[0]; // Extract room number
                var roomPrice = selectedOption.data('price');
                
                $('#selectedRoomNumber').text(roomNumber);
                $('#selectedRoomPrice').text('IDR ' + formatNumber(roomPrice));
                $('#selectedRoomDetails').fadeIn(300);
            } else {
                $('#selectedRoomDetails').fadeOut(300);
            }
        });
        
        // Trigger change event to show current room details if selected
        $('#newRoom').trigger('change');
        
        // Handle room change confirmation
        $('#confirmRoomChange').click(function() {
            // Validate form
            if (!$('#newRoom').val()) {
                alert('Please select a room first.');
                return;
            }
            
            var formData = $('#changeRoomForm').serialize();
            
            // Disable button and show loading state
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            // Send AJAX request to process room change
            $.ajax({
                url: 'index.php?page=process-room-change',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message with animation
                        showSuccessMessage(response.message);
                        
                        // Reload page after delay
                        setTimeout(function() {
                            window.location.href = 'index.php?page=profile';
                        }, 1500);
                    } else {
                        // Show error message
                        showErrorMessage(response.message || 'An error occurred. Please try again.');
                        $('#confirmRoomChange').prop('disabled', false).html('<i class="fas fa-check"></i> Confirm Change');
                    }
                },
                error: function() {
                    showErrorMessage('An error occurred. Please try again.');
                    $('#confirmRoomChange').prop('disabled', false).html('<i class="fas fa-check"></i> Confirm Change');
                }
            });
        });
        
        // Helper function to format numbers with commas
        function formatNumber(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Helper function to show success message
        function showSuccessMessage(message) {
            // Create success message element
            var successMessage = $('<div class="success-message">' +
                '<div class="success-icon"><i class="fas fa-check-circle"></i></div>' +
                '<div class="success-text">' + message + '</div>' +
                '</div>');
            
            // Add success message styles
            $('<style>').text(`
                .success-message {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background-color: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                    text-align: center;
                    z-index: 9999;
                    animation: fadeInScale 0.3s ease;
                }
                .success-icon {
                    font-size: 48px;
                    color: #00b894;
                    margin-bottom: 16px;
                }
                .success-text {
                    font-size: 18px;
                    font-weight: 600;
                    color: #2d3436;
                }
                @keyframes fadeInScale {
                    from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
                    to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
                }
            `).appendTo('head');
            
            // Add to body
            $('body').append(successMessage);
            
            // Remove modal
            $('.modal-content').fadeOut(300);
        }
        
        // Helper function to show error message
        function showErrorMessage(message) {
            // Create error alert if it doesn't exist
            if ($('#errorAlert').length === 0) {
                var errorAlert = $('<div id="errorAlert" class="alert alert-danger">' +
                    '<i class="fas fa-exclamation-circle"></i>' +
                    '<span id="errorMessage">' + message + '</span>' +
                    '</div>');
                
                // Add error alert styles
                $('<style>').text(`
                    .alert-danger {
                        background-color: rgba(255, 107, 107, 0.1);
                        color: #ff6b6b;
                        border: 1px solid rgba(255, 107, 107, 0.2);
                        animation: shake 0.5s ease;
                    }
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                        20%, 40%, 60%, 80% { transform: translateX(5px); }
                    }
                `).appendTo('head');
                
                // Add to form
                $('#changeRoomForm').prepend(errorAlert);
            } else {
                // Update existing error message
                $('#errorMessage').text(message);
                $('#errorAlert').show().css('animation', 'none').outerHeight(); // Force reflow
                $('#errorAlert').css('animation', 'shake 0.5s ease');
            }
            
            // Scroll to top of form
            $('.modal-body').scrollTop(0);
        }
    });
</script>

