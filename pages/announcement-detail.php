<?php
// Get announcement ID from URL
$announcement_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get announcement details
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch();

// If announcement not found, redirect to announcements page
if (!$announcement) {
    header('Location: index.php?page=announcements');
    exit;
}

// Get announcement images
$stmt = $pdo->prepare("
    SELECT * FROM announcement_images 
    WHERE announcement_id = ?
");
$stmt->execute([$announcement_id]);
$images = $stmt->fetchAll();

// Get related announcements
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT ai.image_path FROM announcement_images ai WHERE ai.announcement_id = a.id LIMIT 1) as image
    FROM announcements a
    WHERE a.id != ?
    ORDER BY a.created_at DESC
    LIMIT 3
");
$stmt->execute([$announcement_id]);
$related_announcements = $stmt->fetchAll();

$page_title = $announcement['title'];
?>

<div class="page-content">
    <a href="index.php?page=announcements" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Announcements</span>
    </a>
    
    <div class="announcement-detail">
        <div class="announcement-main">
            <div class="announcement-header">
                <img src="<?php echo !empty($images) ? 'uploads/announcements/' . $images[0]['image_path'] : 'assets/images/default-announcement.jpg'; ?>" alt="<?php echo $announcement['title']; ?>" class="announcement-image">
                <div class="announcement-overlay">
                    <div class="announcement-meta">
                        <span><?php echo date('h:i A', strtotime($announcement['created_at'])); ?></span>
                        <span><?php echo date('F j, Y', strtotime($announcement['created_at'])); ?></span>
                    </div>
                    <h1 class="announcement-title"><?php echo $announcement['title']; ?></h1>
                </div>
            </div>
            
            <div class="announcement-content">
                <p><?php echo nl2br($announcement['content']); ?></p>
            </div>
        </div>
        
        <div class="announcement-sidebar">
            <div class="sidebar-card">
                <h3 class="sidebar-card-title">Announcement Details</h3>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Date & Time</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>, <?php echo date('h:i A', strtotime($announcement['created_at'])); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Posted By</div>
                        <div class="info-value"><?php echo $announcement['first_name'] . ' ' . $announcement['last_name']; ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($related_announcements)): ?>
            <div class="sidebar-card">
                <h3 class="sidebar-card-title">Related Announcements</h3>
                
                <div class="related-announcements">
                    <?php foreach ($related_announcements as $related): ?>
                        <a href="index.php?page=announcement-detail&id=<?php echo $related['id']; ?>" class="related-item">
                            <img src="<?php echo $related['image'] ? 'uploads/announcements/' . $related['image'] : 'assets/images/default-announcement.jpg'; ?>" alt="<?php echo $related['title']; ?>" class="related-image">
                            <div class="related-content">
                                <div class="related-title"><?php echo $related['title']; ?></div>
                                <div class="related-date"><?php echo date('F j, Y', strtotime($related['created_at'])); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Back button */
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background-color: var(--sidebar-bg);
        border-radius: 50px;
        color: var(--text-primary);
        font-size: 14px;
        text-decoration: none;
        transition: var(--transition);
        margin-bottom: 24px;
        border: 1px solid transparent;
    }

    .back-button:hover {
        background-color: var(--hover-color);
        transform: translateX(-4px);
        border-color: var(--border-color);
    }

    /* Announcement detail layout */
    .announcement-detail {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
    }

    @media (min-width: 1024px) {
        .announcement-detail {
            grid-template-columns: 1.2fr 0.8fr;
            align-items: start;
        }
    }

    .announcement-main {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .announcement-header {
        position: relative;
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        aspect-ratio: 16 / 9;
        box-shadow: var(--shadow-md);
    }

    .announcement-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .announcement-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
        padding: 32px 24px 24px;
        color: white;
    }

    .announcement-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 14px;
        opacity: 0.9;
    }

    .announcement-title {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -0.5px;
        line-height: 1.3;
    }

    .announcement-content {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 32px;
        box-shadow: var(--shadow-sm);
    }

    .announcement-content p {
        margin-bottom: 20px;
        color: var(--text-primary);
        font-size: 16px;
        line-height: 1.7;
    }

    .announcement-content p:last-child {
        margin-bottom: 0;
    }

    .announcement-sidebar {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .sidebar-card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
    }

    .sidebar-card-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .info-item:last-child {
        margin-bottom: 0;
    }

    .info-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .info-icon i {
        color: var(--text-primary);
        font-size: 16px;
    }

    .info-content {
        flex: 1;
    }

    .info-label {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }

    .info-value {
        font-weight: 500;
    }

    .related-announcements {
        display: grid;
        gap: 16px;
    }

    .related-item {
        display: flex;
        gap: 16px;
        padding: 12px;
        border-radius: var(--border-radius-md);
        transition: var(--transition);
        text-decoration: none;
        color: inherit;
    }

    .related-item:hover {
        background-color: var(--sidebar-bg);
    }

    .related-image {
        width: 64px;
        height: 64px;
        border-radius: var(--border-radius-md);
        object-fit: cover;
        flex-shrink: 0;
    }

    .related-content {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .related-title {
        font-weight: 500;
        font-size: 14px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .related-date {
        font-size: 12px;
        color: var(--text-secondary);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .announcement-title {
            font-size: 24px;
        }
        
        .announcement-overlay {
            padding: 24px 16px 16px;
        }
        
        .announcement-content {
            padding: 24px;
        }
        
        .sidebar-card {
            padding: 20px;
        }
    }

    @media (max-width: 576px) {
        .announcement-title {
            font-size: 20px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle hover effects
        const cards = document.querySelectorAll('.sidebar-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'var(--shadow-sm)';
            });
        });
    });
</script>

