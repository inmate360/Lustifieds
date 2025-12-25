<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$slug = $_GET['slug'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$sort = $_GET['sort'] ?? 'latest';

$category = $forum->getCategoryBySlug($slug);

if(!$category) {
    header('Location: /forum.php');
    exit();
}

$threads = $forum->getThreads($category['id'], $page, 20, $sort);
$total_threads = $forum->getThreadCount($category['id']);
$total_pages = ceil($total_threads / 20);

include 'views/header.php';
?>

<style>
.category-header {
    background: linear-gradient(135deg, <?php echo $category['color']; ?>, <?php echo $category['color']; ?>dd);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
}

.thread-list {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    overflow: hidden;
}

.thread-item {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s;
}

.thread-item:last-child {
    border-bottom: none;
}

.thread-item:hover {
    background: rgba(66, 103, 245, 0.05);
}

.thread-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(66, 103, 245, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary-blue);
    flex-shrink: 0;
}

.thread-icon.pinned {
    background: rgba(251, 191, 36, 0.1);
    color: var(--featured-gold);
}

.thread-content {
    flex: 1;
    min-width: 0;
}

.thread-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 0.5rem;
    display: block;
    text-decoration: none;
}

.thread-title:hover {
    color: var(--primary-blue);
}

.thread-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-gray);
    flex-wrap: wrap;
}

.thread-stats {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    font-size: 0.9rem;
    color: var(--text-gray);
}

.stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.stat-value {
    font-weight: 700;
    color: var(--text-white);
    font-size: 1.1rem;
}

.sort-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.sort-buttons {
    display: flex;
    gap: 0.5rem;
}

.sort-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.2s;
}

.sort-btn.active {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.1);
    color: var(--primary-blue);
}
</style>

<div class="container my-4">
    
    <!-- Category Header -->
    <div class="category-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="mb-2">
                    <i class="bi bi-<?php echo $category['icon']; ?>"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </h1>
                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($category['description']); ?></p>
            </div>
            <?php if(isset($_SESSION['user_id']) && !$category['is_locked']): ?>
            <a href="/forum-create-thread.php?category=<?php echo $category['id']; ?>" class="btn btn-light">
                <i class="bi bi-plus-circle-fill"></i> New Thread
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sort Bar -->
    <div class="sort-bar">
        <div class="sort-buttons">
            <a href="?slug=<?php echo $slug; ?>&sort=latest" class="sort-btn <?php echo $sort === 'latest' ? 'active' : ''; ?>">
                <i class="bi bi-clock"></i> Latest
            </a>
            <a href="?slug=<?php echo $slug; ?>&sort=popular" class="sort-btn <?php echo $sort === 'popular' ? 'active' : ''; ?>">
                <i class="bi bi-fire"></i> Popular
            </a>
            <a href="?slug=<?php echo $slug; ?>&sort=replies" class="sort-btn <?php echo $sort === 'replies' ? 'active' : ''; ?>">
                <i class="bi bi-chat-dots"></i> Most Replies
            </a>
        </div>
        <span class="text-muted">
            <?php echo number_format($total_threads); ?> threads
        </span>
    </div>

    <!-- Thread List -->
    <div class="thread-list">
        <?php if(empty($threads)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3;"></i>
            <p class="text-muted mt-3">No threads yet. Be the first to start a discussion!</p>
            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="/forum-create-thread.php?category=<?php echo $category['id']; ?>" class="btn btn-primary mt-3">
                <i class="bi bi-plus-circle-fill"></i> Create First Thread
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <?php foreach($threads as $thread): ?>
            <div class="thread-item">
                <div class="thread-icon <?php echo $thread['is_pinned'] ? 'pinned' : ''; ?>">
                    <?php if($thread['is_pinned']): ?>
                        <i class="bi bi-pin-angle-fill"></i>
                    <?php elseif($thread['is_locked']): ?>
                        <i class="bi bi-lock-fill"></i>
                    <?php else: ?>
                        <i class="bi bi-chat-left-text-fill"></i>
                    <?php endif; ?>
                </div>
                
                <div class="thread-content">
                    <a href="/forum-thread.php?slug=<?php echo $thread['slug']; ?>" class="thread-title">
                        <?php if($thread['is_featured']): ?>
                        <span class="badge bg-warning text-dark me-2">
                            <i class="bi bi-star-fill"></i> Featured
                        </span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($thread['title']); ?>
                    </a>
                    <div class="thread-meta">
                        <span>
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($thread['username']); ?>
                            <?php if($thread['is_premium']): ?>
                            <i class="bi bi-patch-check-fill text-primary" title="Premium Member"></i>
                            <?php endif; ?>
                        </span>
                        <span>
                            <i class="bi bi-clock"></i>
                            <?php echo date('M j, Y', strtotime($thread['created_at'])); ?>
                        </span>
                        <?php if($thread['reactions_count'] > 0): ?>
                        <span>
                            <i class="bi bi-heart-fill text-danger"></i>
                            <?php echo $thread['reactions_count']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="thread-stats d-none d-md-flex">
                    <div class="stat">
                        <span class="stat-value"><?php echo number_format($thread['views']); ?></span>
                        <span class="stat-label">Views</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?php echo number_format($thread['replies_count']); ?></span>
                        <span class="stat-label">Replies</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include 'views/footer.php'; ?>