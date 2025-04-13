<?php
$page_title = "Chat";

// Get all users for chat
$stmt = $pdo->query("
    SELECT id, first_name, last_name, profile_image, 
           (CASE WHEN id = 1 THEN 1 ELSE 0 END) as is_admin
    FROM users
    WHERE id != {$_SESSION['user_id']}
    ORDER BY is_admin DESC, first_name ASC
");
$users = $stmt->fetchAll();

// Define active_user_id first - get it from URL or default to first user
$active_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Get recent messages with proper ordering (oldest first)
$stmt = $pdo->prepare("
    SELECT m.*, 
           sender.id as sender_id, 
           CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
           sender.profile_image as sender_image,
           receiver.id as receiver_id,
           CONCAT(receiver.first_name, ' ', sender.last_name) as receiver_name,
           receiver.profile_image as receiver_image
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at ASC
    LIMIT 100
");

// Update the execute statement to include the active user ID
if ($active_user_id) {
    $stmt->execute([$_SESSION['user_id'], $active_user_id, $active_user_id, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll();
} else {
    $messages = [];
}

// Get all conversations for the sidebar
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END as other_user_id,
        MAX(m.created_at) as last_message_time,
        (
            SELECT message FROM messages 
            WHERE ((sender_id = ? AND receiver_id = other_user_id) OR (sender_id = other_user_id AND receiver_id = ?))
            ORDER BY created_at DESC LIMIT 1
        ) as last_message,
        (
            SELECT COUNT(*) FROM messages 
            WHERE sender_id = other_user_id AND receiver_id = ? AND is_read = 0
        ) as unread_count
    FROM messages m
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_time DESC
");
$stmt->execute([
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id']
]);
$conversation_data = $stmt->fetchAll();

// Build conversations array with user details
$conversations = [];
foreach ($conversation_data as $conv) {
    // Get user details
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$conv['other_user_id']]);
    $other_user = $stmt->fetch();

    if ($other_user) {
        $conversations[$conv['other_user_id']] = [
            'id' => $other_user['id'],
            'name' => $other_user['first_name'] . ' ' . $other_user['last_name'],
            'image' => $other_user['profile_image'],
            'last_message' => $conv['last_message'],
            'last_time' => $conv['last_message_time'],
            'unread' => $conv['unread_count']
        ];
    }
}

// Update active_user_id if we have conversations
if (count($conversations) > 0 && $active_user_id === 0) {
    $active_user_id = array_key_first($conversations);
}

// Get active user details
$active_user = null;
if ($active_user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$active_user_id]);
    $active_user = $stmt->fetch();
}
?>

<!-- Chat Layout -->
<div class="chat-layout">
    <!-- Chat Sidebar -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <div class="chat-sidebar-title">Messages</div>
            <div class="chat-sidebar-actions">
                <div class="chat-sidebar-action">
                    <i class="fas fa-edit"></i>
                </div>
            </div>
        </div>

        <div class="chat-tabs">
            <div class="chat-tab active" data-tab="users">Users</div>
            <div class="chat-tab" data-tab="aulia">Aulia Kost</div>
        </div>

        <div class="chat-search">
            <input type="text" placeholder="Search" id="chatSearch">
        </div>

        <div class="chat-list" id="usersList">
            <?php foreach ($conversations as $conversation): ?>
                <a href="index.php?page=chat&user=<?php echo $conversation['id']; ?>" class="chat-item <?php echo $active_user_id == $conversation['id'] ? 'active' : ''; ?>">
                    <div class="chat-item-avatar">
                        <img src="<?php echo $conversation['image'] ? 'uploads/profiles/' . $conversation['image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $conversation['name']; ?>">
                    </div>
                    <div class="chat-item-content">
                        <div class="chat-item-header">
                            <div class="chat-item-name"><?php echo $conversation['name']; ?></div>
                            <div class="chat-item-time"><?php echo date('H:i', strtotime($conversation['last_time'])); ?></div>
                        </div>
                        <div class="chat-item-message"><?php echo substr($conversation['last_message'], 0, 30) . (strlen($conversation['last_message']) > 30 ? '...' : ''); ?></div>
                    </div>
                    <?php if ($conversation['unread'] > 0): ?>
                        <div class="chat-item-badge"><?php echo $conversation['unread']; ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <?php foreach ($users as $user): ?>
                <?php if (!isset($conversations[$user['id']])): ?>
                    <a href="index.php?page=chat&user=<?php echo $user['id']; ?>" class="chat-item <?php echo $active_user_id == $user['id'] ? 'active' : ''; ?>">
                        <div class="chat-item-avatar">
                            <img src="<?php echo $user['profile_image'] ? 'uploads/profiles/' . $user['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>">
                        </div>
                        <div class="chat-item-content">
                            <div class="chat-item-header">
                                <div class="chat-item-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></div>
                                <div class="chat-item-time">New</div>
                            </div>
                            <div class="chat-item-message">Start a conversation</div>
                        </div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="chat-list" id="auliaList" style="display: none;">
            <div class="chat-item">
                <div class="chat-item-avatar letter">G</div>
                <div class="chat-item-content">
                    <div class="chat-item-header">
                        <div class="chat-item-name">General</div>
                        <div class="chat-item-time">12:30</div>
                    </div>
                    <div class="chat-item-message">Welcome to Aulia Kost! Feel free to ask any questions here.</div>
                </div>
            </div>

            <div class="chat-item">
                <div class="chat-item-avatar letter">S</div>
                <div class="chat-item-content">
                    <div class="chat-item-header">
                        <div class="chat-item-name">Support</div>
                        <div class="chat-item-time">10:15</div>
                    </div>
                    <div class="chat-item-message">If you need any assistance with your room or facilities, please let us know.</div>
                </div>
                <div class="chat-item-badge">3</div>
            </div>

            <div class="chat-item">
                <div class="chat-item-avatar letter">M</div>
                <div class="chat-item-content">
                    <div class="chat-item-header">
                        <div class="chat-item-name">Maintenance</div>
                        <div class="chat-item-time">Yesterday</div>
                    </div>
                    <div class="chat-item-message">Scheduled water maintenance on Friday from 10 AM to 2 PM.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Main -->
    <div class="chat-main">
        <?php if ($active_user): ?>
            <div class="chat-header">
                <div class="chat-header-info">
                    <div class="chat-header-avatar">
                        <img src="<?php echo $active_user['profile_image'] ? 'uploads/profiles/' . $active_user['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $active_user['first_name'] . ' ' . $active_user['last_name']; ?>">
                    </div>
                    <div class="chat-header-details">
                        <div class="chat-header-name"><?php echo $active_user['first_name'] . ' ' . $active_user['last_name']; ?></div>
                        <div class="chat-header-status"><?php echo $active_user['role'] === 'admin' ? 'Admin' : 'Tenant'; ?></div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <div class="chat-header-action mobile-sidebar-toggle" id="mobileSidebarToggle">
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="chat-header-action">
                        <i class="fas fa-ellipsis-v"></i>
                    </div>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php if (!empty($messages)): ?>
                    <div class="date-divider">Today</div>

                    <?php foreach ($messages as $message):
                        $isOutgoing = $message['sender_id'] == $_SESSION['user_id'];
                    ?>
                        <div class="message <?php echo $isOutgoing ? 'outgoing' : ''; ?>" data-message-id="<?php echo $message['id']; ?>">
                            <?php if (!$isOutgoing): ?>
                                <div class="message-avatar">
                                    <img src="<?php echo $message['sender_image'] ? 'uploads/profiles/' . $message['sender_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $message['sender_name']; ?>">
                                </div>
                            <?php endif; ?>
                            <div class="message-content">
                                <div class="message-sender"><?php echo $isOutgoing ? 'You' : $message['sender_name']; ?></div>
                                <div class="message-bubble"><?php echo $message['message']; ?></div>
                                <div class="message-time"><?php echo date('H:i', strtotime($message['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-chat">
                        <div class="empty-chat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="empty-chat-text">No messages yet. Start a conversation!</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chat-input-container">
                <form id="messageForm" method="post" action="index.php?page=send-message">
                    <input type="hidden" name="receiver_id" value="<?php echo $active_user_id; ?>">
                    <div class="chat-input">
                        <button type="button" class="chat-input-action">
                            <i class="far fa-smile"></i>
                        </button>
                        <input type="text" name="message" placeholder="Send your Message" id="messageInput" required>
                        <div class="chat-input-actions">
                            <button type="button" class="chat-input-action">
                                <i class="far fa-image"></i>
                            </button>
                            <button type="button" class="chat-input-action">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                        <button type="submit" class="chat-input-send" id="sendButton">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-chat-container">
                <div class="empty-chat">
                    <div class="empty-chat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="empty-chat-text">Select a conversation to start chatting</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chat sidebar overlay for mobile -->
<div class="chat-sidebar-overlay" id="chatSidebarOverlay"></div>

<style>
    /* Chat layout */
    .chat-layout {
        display: flex;
        flex: 1;
        overflow: hidden;
        margin: 0;
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-sm);
        height: calc(100vh - 120px);
    }

    /* Chat sidebar */
    .chat-sidebar {
        width: 280px;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        height: 100%;
        background-color: var(--card-bg);
    }

    .chat-sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chat-sidebar-title {
        font-weight: 600;
        font-size: 16px;
    }

    .chat-sidebar-actions {
        display: flex;
        gap: 8px;
    }

    .chat-sidebar-action {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--sidebar-bg);
        color: var(--text-primary);
        cursor: pointer;
        transition: var(--transition);
        border: 1px solid transparent;
    }

    .chat-sidebar-action:hover {
        background-color: var(--hover-color);
        border-color: var(--border-color);
        transform: translateY(-2px);
    }

    .chat-tabs {
        display: flex;
        border-bottom: 1px solid var(--border-color);
    }

    .chat-tab {
        flex: 1;
        padding: 12px 0;
        text-align: center;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: var(--transition);
        border-bottom: 2px solid transparent;
    }

    .chat-tab.active {
        color: var(--text-primary);
        border-bottom-color: var(--accent-color);
    }

    .chat-search {
        padding: 12px 16px;
        position: relative;
    }

    .chat-search input {
        width: 100%;
        padding: 10px 12px 10px 32px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--sidebar-bg);
        font-size: 14px;
    }

    .chat-search input:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    .chat-search::before {
        content: '';
        position: absolute;
        left: 24px;
        top: 50%;
        transform: translateY(-50%);
        width: 14px;
        height: 14px;
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>');
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
    }

    .chat-list {
        flex: 1;
        overflow-y: auto;
        padding: 8px 0;
    }

    .chat-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: var(--transition);
        border-left: 3px solid transparent;
        text-decoration: none;
        color: inherit;
    }

    .chat-item:hover {
        background-color: var(--sidebar-bg);
    }

    .chat-item.active {
        background-color: var(--sidebar-bg);
        border-left-color: var(--accent-color);
    }

    .chat-item-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        box-shadow: var(--shadow-sm);
        border: 2px solid white;
    }

    .chat-item-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-item-avatar.letter {
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--text-primary);
    }

    .chat-item-content {
        flex: 1;
        min-width: 0;
    }

    .chat-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    .chat-item-name {
        font-weight: 500;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-item-time {
        font-size: 12px;
        color: var(--text-secondary);
        flex-shrink: 0;
    }

    .chat-item-message {
        font-size: 12px;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-item-badge {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: var(--accent-color);
        color: white;
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 8px;
    }

    /* Chat main */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .chat-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: var(--card-bg);
    }

    .chat-header-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .chat-header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        border: 2px solid white;
    }

    .chat-header-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-header-avatar.letter {
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--text-primary);
    }

    .chat-header-details {
        display: flex;
        flex-direction: column;
    }

    .chat-header-name {
        font-weight: 600;
        font-size: 14px;
    }

    .chat-header-status {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .chat-header-actions {
        display: flex;
        gap: 12px;
    }

    .chat-header-action {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--sidebar-bg);
        color: var(--text-primary);
        cursor: pointer;
        transition: var(--transition);
        border: 1px solid transparent;
    }

    .chat-header-action:hover {
        background-color: var(--hover-color);
        border-color: var(--border-color);
        transform: translateY(-2px);
    }

    /* Chat messages */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        background-color: var(--primary-bg);
    }

    .message {
        display: flex;
        margin-bottom: 24px;
        max-width: 80%;
    }

    .message.outgoing {
        margin-left: auto;
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 12px;
        flex-shrink: 0;
        box-shadow: var(--shadow-sm);
        border: 2px solid white;
    }

    .message.outgoing .message-avatar {
        display: none;
    }

    .message-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .message-avatar.letter {
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--text-primary);
    }

    .message-content {
        display: flex;
        flex-direction: column;
    }

    .message-sender {
        font-weight: 500;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .message.outgoing .message-sender {
        text-align: right;
    }

    .message-bubble {
        background-color: var(--card-bg);
        padding: 12px 16px;
        border-radius: 16px;
        border-top-left-radius: 4px;
        font-size: 14px;
        position: relative;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .message.outgoing .message-bubble {
        background-color: var(--accent-color);
        color: white;
        border-radius: 16px;
        border-top-right-radius: 4px;
        border: none;
    }

    .message-time {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 4px;
        align-self: flex-end;
    }

    .message.outgoing .message-time {
        align-self: flex-start;
    }

    /* Date divider */
    .date-divider {
        display: flex;
        align-items: center;
        margin: 24px 0;
        color: var(--text-secondary);
        font-size: 12px;
    }

    .date-divider::before,
    .date-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background-color: var(--border-color);
    }

    .date-divider::before {
        margin-right: 12px;
    }

    .date-divider::after {
        margin-left: 12px;
    }

    /* Empty chat */
    .empty-chat-container {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        background-color: var(--primary-bg);
    }

    .empty-chat {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px;
        text-align: center;
    }

    .empty-chat-icon {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-chat-text {
        color: var(--text-secondary);
        font-size: 16px;
    }

    /* Chat input */
    .chat-input-container {
        border-top: 1px solid var(--border-color);
        padding: 16px 24px;
        background-color: var(--card-bg);
    }

    .chat-input {
        display: flex;
        align-items: center;
        background-color: var(--sidebar-bg);
        border: 1px solid var(--border-color);
        border-radius: 50px;
        padding: 8px 12px;
    }

    .chat-input-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-right: 8px;
    }

    .chat-input-action {
        color: var(--text-secondary);
        cursor: pointer;
        font-size: 18px;
        transition: color 0.2s ease;
        background: none;
        border: none;
    }

    .chat-input-action:hover {
        color: var(--text-primary);
    }

    .chat-input input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 8px;
        font-size: 14px;
        outline: none;
    }

    .chat-input-send {
        background: none;
        border: none;
        color: var(--accent-color);
        cursor: pointer;
        font-size: 18px;
        transition: transform 0.2s ease;
        padding: 4px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chat-input-send:hover {
        transform: scale(1.1);
        background-color: var(--hover-color);
    }

    /* Chat sidebar overlay */
    .chat-sidebar-overlay {
        position: fixed;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 90;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    .chat-sidebar-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .chat-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 95;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            width: 280px;
        }

        .chat-sidebar.active {
            transform: translateX(0);
        }

        .chat-header-action.mobile-sidebar-toggle {
            display: flex;
        }

        .message {
            max-width: 90%;
        }
    }

    @media (min-width: 769px) {
        .chat-header-action.mobile-sidebar-toggle {
            display: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chat tabs
        const chatTabs = document.querySelectorAll('.chat-tab');
        const usersList = document.getElementById('usersList');
        const auliaList = document.getElementById('auliaList');

        chatTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                chatTabs.forEach(t => t.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Show/hide appropriate list
                if (this.dataset.tab === 'users') {
                    usersList.style.display = 'block';
                    auliaList.style.display = 'none';
                } else {
                    usersList.style.display = 'none';
                    auliaList.style.display = 'block';
                }
            });
        });

        // Mobile sidebar toggle
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const chatSidebar = document.getElementById('chatSidebar');
        const chatSidebarOverlay = document.getElementById('chatSidebarOverlay');

        function openMobileSidebar() {
            chatSidebar.classList.add('active');
            chatSidebarOverlay.classList.add('active');
        }

        function closeMobileSidebar() {
            chatSidebar.classList.remove('active');
            chatSidebarOverlay.classList.remove('active');
        }

        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', openMobileSidebar);
        }

        if (chatSidebarOverlay) {
            chatSidebarOverlay.addEventListener('click', closeMobileSidebar);
        }

        // Scroll to bottom of chat on load
        const chatMessages = document.querySelector('.chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Chat search functionality
        const chatSearch = document.getElementById('chatSearch');
        if (chatSearch) {
            chatSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const chatItems = document.querySelectorAll('.chat-item');

                chatItems.forEach(item => {
                    const name = item.querySelector('.chat-item-name').textContent.toLowerCase();
                    const message = item.querySelector('.chat-item-message').textContent.toLowerCase();

                    if (name.includes(searchTerm) || message.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Auto-scroll to bottom of chat on load and after sending a message
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Call scrollToBottom on page load
        scrollToBottom();

        // Handle form submission with AJAX
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');

        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (messageInput.value.trim() === '') {
                    return;
                }

                const formData = new FormData(this);
                const messageText = messageInput.value;

                // Create a new message element
                const chatMessages = document.getElementById('chatMessages');
                const newMessage = document.createElement('div');
                newMessage.className = 'message outgoing';
                newMessage.setAttribute('data-message-pending', 'true');

                // Format the time
                const now = new Date();
                const hours = now.getHours();
                const minutes = String(now.getMinutes()).padStart(2, '0');

                newMessage.innerHTML = `
                    <div class="message-content">
                        <div class="message-sender">You</div>
                        <div class="message-bubble">${messageText}</div>
                        <div class="message-time">${hours}:${minutes}</div>
                    </div>
                `;

                // Add the message to the chat
                if (chatMessages.querySelector('.empty-chat')) {
                    // Remove the empty chat message and add date divider
                    chatMessages.innerHTML = '<div class="date-divider">Today</div>';
                }

                chatMessages.appendChild(newMessage);
                scrollToBottom();

                // Clear the input
                messageInput.value = '';

                // Send the message to the server
                fetch('pages/send-messages-ajax.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
    if (data.success) {
        // Update the message with the proper ID
        newMessage.setAttribute('data-message-id', data.data.id);
        newMessage.removeAttribute('data-message-pending');
        
        // Update last timestamp to include this message
        lastTimestamp = data.data.created_at;

        // Show success toast notification
        if (typeof window.showToast === 'function') {
            window.showToast("Message sent successfully", "success");
        }
                        } else {
                            console.error('Error:', data.message);
                            // Show error toast notification
                            if (typeof window.showToast === 'function') {
                                window.showToast(data.message, "error");
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Show error toast notification
                        if (typeof window.showToast === 'function') {
                            window.showToast("Failed to send message. Please try again.", "error");
                        }
                    });
            });
        }

        // Set up polling for new messages
        const activeUserId = <?php echo $active_user_id ?: 'null'; ?>;
        let lastTimestamp = "<?php echo !empty($messages) ? end($messages)['created_at'] : ''; ?>";

        function pollMessages() {
            if (!activeUserId) return;

            fetch(`pages/chat-ajax.php?user=${activeUserId}&last_timestamp=${encodeURIComponent(lastTimestamp)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        // Update last timestamp
                        lastTimestamp = data.last_timestamp;

                        // Add new messages to chat
                        const chatMessages = document.getElementById('chatMessages');

                        // If this is the first message, clear the empty chat message
                        if (chatMessages.querySelector('.empty-chat')) {
                            chatMessages.innerHTML = '<div class="date-divider">Today</div>';
                        }

                        // Track existing message IDs to prevent duplicates
                        const existingMessageIds = new Set();
                        document.querySelectorAll('.message[data-message-id]').forEach(msg => {
                            existingMessageIds.add(msg.dataset.messageId);
                        });

                        // Add each new message to the chat
                        data.messages.forEach(msg => {
                            // Skip if message is already displayed
                            if (existingMessageIds.has(msg.id.toString())) {
                                return;
                            }

                            const newMessage = document.createElement('div');
                            newMessage.className = `message ${msg.is_outgoing ? 'outgoing' : ''}`;
                            newMessage.setAttribute('data-message-id', msg.id);

                            if (!msg.is_outgoing) {
                                newMessage.innerHTML = `
                        <div class="message-avatar">
                            <img src="${msg.sender_image}" alt="${msg.sender_name}">
                        </div>
                        <div class="message-content">
                            <div class="message-sender">${msg.sender_name}</div>
                            <div class="message-bubble">${msg.message}</div>
                            <div class="message-time">${msg.time}</div>
                        </div>
                    `;
                            } else {
                                newMessage.innerHTML = `
                        <div class="message-content">
                            <div class="message-sender">You</div>
                            <div class="message-bubble">${msg.message}</div>
                            <div class="message-time">${msg.time}</div>
                        </div>
                    `;
                            }

                            chatMessages.appendChild(newMessage);

                            // Update unread badges in the sidebar
                            if (!msg.is_outgoing) {
                                const userItem = document.querySelector(`.chat-item[href*="user=${msg.sender_id}"]`);
                                if (userItem) {
                                    // Update latest message preview
                                    const messagePreview = userItem.querySelector('.chat-item-message');
                                    if (messagePreview) {
                                        messagePreview.textContent = msg.message.length > 30 ?
                                            msg.message.substring(0, 30) + '...' :
                                            msg.message;
                                    }

                                    // Update timestamp
                                    const timeDisplay = userItem.querySelector('.chat-item-time');
                                    if (timeDisplay) {
                                        timeDisplay.textContent = msg.time;
                                    }
                                }
                            }
                        });

                        // Scroll to bottom if user was already at bottom
                        scrollToBottom();
                    }
                })
                .catch(error => {
                    console.error('Error polling messages:', error);
                });
        }

        // Poll every 5 seconds if there is an active user
        if (activeUserId) {
            // Initial poll when page loads
            pollMessages();

            // Set up interval for polling
            const pollInterval = setInterval(pollMessages, 5000);

            // Clean up interval when page unloads
            window.addEventListener('beforeunload', () => {
                clearInterval(pollInterval);
            });
        }
    });
</script>