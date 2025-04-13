<?php
// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    // Not an AJAX request, redirect to profile
    header('Location: index.php?page=profile');
    exit;
}

// Initialize response array
$response = [
    'success' => false,
    'message' => 'An error occurred while processing your request.'
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (!isset($_POST['new_room_id']) || empty($_POST['new_room_id'])) {
        $response['message'] = 'Please select a room.';
        echo json_encode($response);
        exit;
    }
    
    $newRoomId = $_POST['new_room_id'];
    $currentRoomId = $_POST['current_room_id'] ?? null;
    $changeReason = $_POST['change_reason'] ?? '';
    
    // If current room is the same as new room, no change needed
    if ($currentRoomId && $currentRoomId == $newRoomId) {
        $response['success'] = true;
        $response['message'] = 'You are already assigned to this room.';
        echo json_encode($response);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if the new room exists and is available
        $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
        $roomStmt->execute([$newRoomId]);
        $newRoom = $roomStmt->fetch();
        
        if (!$newRoom) {
            throw new Exception('The selected room does not exist.');
        }
        
        if ($newRoom['status'] !== 'available' && $newRoom['id'] != $currentRoomId) {
            throw new Exception('The selected room is not available.');
        }
        
        // Update the current room to be available (if exists)
        if ($currentRoomId) {
            $updateCurrentRoomStmt = $pdo->prepare("
                UPDATE rooms 
                SET status = 'available'
                WHERE id = ?
            ");
            $updateCurrentRoomStmt->execute([$currentRoomId]);
        }
        
        // Update the new room to be occupied
        $updateNewRoomStmt = $pdo->prepare("
            UPDATE rooms 
            SET status = 'occupied'
            WHERE id = ?
        ");
        $updateNewRoomStmt->execute([$newRoomId]);
        
        // Update the user's room_id
        $updateUserStmt = $pdo->prepare("
            UPDATE users 
            SET room_id = ?
            WHERE id = ?
        ");
        $updateUserStmt->execute([$newRoomId, $_SESSION['user_id']]);
        
        // Log the room change
        $logStmt = $pdo->prepare("
            INSERT INTO room_change_logs 
            (user_id, old_room_id, new_room_id, change_reason, change_date) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$_SESSION['user_id'], $currentRoomId, $newRoomId, $changeReason]);
        
        // Commit transaction
        $pdo->commit();
        
        // Set success response
        $response['success'] = true;
        $response['message'] = 'Room changed successfully! You are now in Room ' . ($newRoom['room_number'] ?? $newRoom['id']) . '.';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $response['message'] = $e->getMessage();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

