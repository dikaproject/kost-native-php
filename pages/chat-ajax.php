<?php
// Halaman ini untuk mendapatkan pesan terbaru melalui AJAX
session_start();

// Database connection
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Ambil ID pengguna yang sedang chat
$active_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;

if (empty($active_user_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'No active user specified']);
    exit;
}

// Ambil timestamp terakhir jika ada
$last_timestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : null;

// Query untuk mendapatkan pesan baru
$query = "
    SELECT m.*, 
           sender.id as sender_id, 
           CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
           sender.profile_image as sender_image,
           receiver.id as receiver_id,
           CONCAT(receiver.first_name, ' ', receiver.last_name) as receiver_name,
           receiver.profile_image as receiver_image
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
";

// Tambahkan filter timestamp jika ada
if ($last_timestamp) {
    $query .= " AND m.created_at > ?";
    $params = [$_SESSION['user_id'], $active_user_id, $active_user_id, $_SESSION['user_id'], $last_timestamp];
} else {
    $params = [$_SESSION['user_id'], $active_user_id, $active_user_id, $_SESSION['user_id']];
}

$query .= " ORDER BY m.created_at ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tandai pesan sebagai telah dibaca
if (!empty($messages)) {
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$active_user_id, $_SESSION['user_id']]);
}

// Format pesan untuk respons JSON
$formatted_messages = [];
foreach ($messages as $message) {
    $isOutgoing = $message['sender_id'] == $_SESSION['user_id'];
    $formatted_messages[] = [
        'id' => $message['id'],
        'message' => $message['message'],
        'sender_id' => $message['sender_id'],
        'sender_name' => $message['sender_name'],
        'sender_image' => $message['sender_image'] ? 'uploads/profiles/' . $message['sender_image'] : 'assets/images/default-avatar.jpg',
        'is_outgoing' => $isOutgoing,
        'time' => date('H:i', strtotime($message['created_at'])),
        'timestamp' => $message['created_at']
    ];
}

// Kirim respons
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $formatted_messages,
    'last_timestamp' => !empty($messages) ? end($messages)['created_at'] : $last_timestamp
]);
exit;
?>