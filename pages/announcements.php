<?php
$page_title = "Announcements";

// Get all announcements
$stmt = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, 
           (SELECT ai.image_path FROM announcement_images ai WHERE ai.announcement_id = a.id LIMIT 1) as image
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
");
$announcements = $stmt->fetchAll();
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Announcements</h1>
        <p class="page-subtitle">Stay updated with the latest information</p>
    </div>
    
    <div class="filter-actions">
        <div class="filter-btn">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </div>
        <div class="filter-btn">
            <i class="fas fa-filter"></i>
            <span>Filter</span>
        </div>
    </div>
    
    <div class="announcements-grid">
        <?php foreach ($announcements as $announcement): ?>
            <a href="index.php?page=announcement-detail&id=<?php echo $announcement['id']; ?>" class="announcement-card">
                <img src="<?php echo $announcement['image'] ? 'uploads/announcements/' . $announcement['image'] : 'assets/images/default-announcement.jpg'; ?>" alt="<?php echo $announcement['title']; ?>" class="announcement-image">
                <div class="announcement-content">
                    <div class="announcement-meta">
                        <span><?php echo date('h:i A', strtotime($announcement['created_at'])); ?></span>
                        <span><?php echo date('F j, Y', strtotime($announcement['created_at'])); ?></span>
                    </div>
                    <h3 class="announcement-title"><?php echo $announcement['title']; ?></h3>
                    <p class="announcement-description"><?php echo substr($announcement['content'], 0, 150) . (strlen($announcement['content']) > 150 ? '...' : ''); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<style>
    /* Page content */
    .page-content {
        margin-bottom: 24px;
    }

    .page-header {
        margin-bottom: 24px;
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .page-subtitle {
        color: var(--text-secondary);
        font-size: 16px;
    }

    /* Filter actions */
    .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-bottom: 24px;
    }

    .filter-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border-radius: 50px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        cursor: pointer;
        transition: var(--transition);
        font-size: 14px;
    }

    .filter-btn:hover {
        background-color: var(--hover-color);
        border-color: #ccc;
    }

    .filter-btn i {
        margin-right: 8px;
    }

    /* Announcements Grid */
    .announcements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
    }

    .announcement-card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        height: 100%;
        border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .announcement-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }

    .announcement-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        transition: var(--transition);
    }

    .announcement-card:hover .announcement-image {
        transform: scale(1.05);
    }

    .announcement-content {
        padding: 24px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .announcement-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 16px;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .announcement-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 12px;
        line-height: 1.4;
        color: var(--text-primary);
    }

    .announcement-description {
        color: var(--text-secondary);
        font-size: 14px;
        line-height: 1.6;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .announcements-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle hover effects
        const cards = document.querySelectorAll('.announcement-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
</script>

