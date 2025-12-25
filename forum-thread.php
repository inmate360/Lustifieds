<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$slug = $_GET['slug'] ?? '';
$page = (int)($_GET['page'] ?? 1);

$thread = $forum->getThreadBySlug($slug);

if(!$thread) {
    header('Location: /forum.php');
    exit();
}

// Increment views
$forum->incrementViews($thread['id']);

// Get posts
$posts = $forum->getPosts($thread['id'], $page, 20);
$total_posts = $thread['replies_count'];
$total_pages = ceil(($total_posts + 1) / 20); // +1 for original post

// Check if user has reacted
$user_reaction = null;
if(isset($_SESSION['user_id'])) {
    $query = "SELECT reaction_type FROM forum_reactions 
              WHERE user_id = :user_id AND thread_id = :thread_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'thread_id' => $thread['id']
    ]);
    $user_reaction = $stmt->fetchColumn();
}

// Check if subscribed
$is_subscribed = false;
if(isset($_SESSION['user_id'])) {
    $query = "SELECT id FROM forum_subscriptions 
              WHERE user_id = :user_id AND thread_id = :thread_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'thread_id' => $thread['id']
    ]);
    $is_subscribed = $stmt->fetch() ? true : false;
}

include 'views/header.php';
?>

<style>
.thread-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.breadcrumb-nav {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
}

.thread-header {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.thread-title-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.thread-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.thread-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.action-btn:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.action-btn.active {
    border-color: var(--danger-red);
    color: var(--danger-red);
    background: rgba(239, 68, 68, 0.1);
}

.thread-meta {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    font-size: 0.9rem;
    color: var(--text-gray);
}

.post-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: all 0.3s;
}

.post-card:hover {
    border-color: rgba(66, 103, 245, 0.3);
}

.post-card.original-post {
    border-color: var(--primary-blue);
    background: linear-gradient(to right, rgba(66, 103, 245, 0.05), transparent);
}

.post-layout {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 0;
}

.post-author {
    background: rgba(0, 0, 0, 0.2);
    padding: 1.5rem;
    text-align: center;
    border-right: 2px solid var(--border-color);
}

.author-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 1rem;
}

.author-name {
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 0.25rem;
}

.author-role {
    font-size: 0.75rem;
    color: var(--text-gray);
    margin-bottom: 1rem;
}

.author-stats {
    font-size: 0.8rem;
    color: var(--text-gray);
    line-height: 1.8;
}

.post-content-section {
    padding: 1.5rem;
}

.post-content {
    color: var(--text-white);
    line-height: 1.8;
    margin-bottom: 1.5rem;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.post-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    flex-wrap: wrap;
    gap: 1rem;
}

.post-date {
    font-size: 0.85rem;
    color: var(--text-gray);
}

.post-actions {
    display: flex;
    gap: 0.75rem;
}

.post-action-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.post-action-btn:hover {
    color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.1);
}

.reply-form {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-top: 2rem;
}

.reply-form textarea {
    width: 100%;
    min-height: 150px;
    padding: 1rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--background-dark);
    color: var(--text-white);
    font-family: inherit;
    font-size: 1rem;
    resize: vertical;
}

.reply-form textarea:focus {
    outline: none;
    border-color: var(--primary-blue);
}

.badges {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .post-layout {
        grid-template-columns: 1fr;
    }
    
    .post-author {
        border-right: none;
        border-bottom: 2px solid var(--border-color);
    }
    
    .thread-title {
        font-size: 1.5rem;
    }
    
    .thread-title-section {
        flex-direction: column;
    }
}
</style>

<div class="thread-container">
    
    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="/forum.php">Forum</a></li>
            <li class="breadcrumb-item"><a href="/forum-category.php?slug=<?php echo $thread['category_slug'] ?? ''; ?>"><?php echo $thread['category_name'] ?? 'Category'; ?></a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars(substr($thread['title'], 0, 50)); ?>...</li>
        </ol>
    </nav>

    <!-- Thread Header -->
    <div class="thread-header">
        <div class="thread-title-section">
            <div>
                <div class="badges">
                    <?php if($thread['is_pinned']): ?>
                    <span class="badge bg-warning"><i class="bi bi-pin-angle-fill"></i> Pinned</span>
                    <?php endif; ?>
                    <?php if($thread['is_featured']): ?>
                    <span class="badge bg-primary"><i class="bi bi-star-fill"></i> Featured</span>
                    <?php endif; ?>
                    <?php if($thread['is_locked']): ?>
                    <span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Locked</span>
                    <?php endif; ?>
                </div>
                <h1 class="thread-title"><?php echo htmlspecialchars($thread['title']); ?></h1>
            </div>
            <div class="thread-actions">
                <?php if(isset($_SESSION['user_id'])): ?>
                <button class="action-btn <?php echo $user_reaction ? 'active' : ''; ?>" 
                        onclick="toggleReaction(<?php echo $thread['id']; ?>)"
                        id="reactionBtn">
                    <i class="bi bi-heart-fill"></i>
                    <span id="reactionCount"><?php echo $thread['reactions_count']; ?></span>
                </button>
                <button class="action-btn <?php echo $is_subscribed ? 'active' : ''; ?>" 
                        onclick="toggleSubscription()"
                        title="<?php echo $is_subscribed ? 'Unsubscribe' : 'Subscribe'; ?>">
                    <i class="bi bi-bell-fill"></i>
                </button>
                <?php if($thread['user_id'] == $_SESSION['user_id']): ?>
                <button class="action-btn" onclick="location.href='/forum-edit-thread.php?id=<?php echo $thread['id']; ?>'">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <?php endif; ?>
                <?php endif; ?>
                <button class="action-btn" onclick="shareThread()">
                    <i class="bi bi-share-fill"></i>
                </button>
            </div>
        </div>
        
        <div class="thread-meta">
            <span>
                <i class="bi bi-person-circle"></i>
                Started by <strong><?php echo htmlspecialchars($thread['username']); ?></strong>
            </span>
            <span>
                <i class="bi bi-clock"></i>
                <?php echo date('M j, Y \a\t g:i A', strtotime($thread['created_at'])); ?>
            </span>
            <span>
                <i class="bi bi-eye-fill"></i>
                <?php echo number_format($thread['views']); ?> views
            </span>
            <span>
                <i class="bi bi-chat-dots-fill"></i>
                <?php echo number_format($thread['replies_count']); ?> replies
            </span>
        </div>
    </div>

    <!-- Original Post -->
    <div class="post-card original-post" id="post-original">
        <div class="post-layout">
            <div class="post-author">
                <div class="author-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="author-name">
                    <?php echo htmlspecialchars($thread['username']); ?>
                    <?php if($thread['is_premium']): ?>
                    <i class="bi bi-patch-check-fill text-warning" title="Premium Member"></i>
                    <?php endif; ?>
                </div>
                <div class="author-role">Thread Starter</div>
                <div class="author-stats">
                    <div><i class="bi bi-chat-dots"></i> <?php echo number_format($thread['user_posts']); ?> posts</div>
                    <div><i class="bi bi-star"></i> <?php echo number_format($thread['user_reputation']); ?> reputation</div>
                    <div><i class="bi bi-calendar"></i> Joined <?php echo date('M Y', strtotime($thread['user_joined'])); ?></div>
                </div>
            </div>
            <div class="post-content-section">
                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($thread['content'])); ?>
                </div>
                <div class="post-footer">
                    <div class="post-date">
                        Posted <?php echo date('M j, Y \a\t g:i A', strtotime($thread['created_at'])); ?>
                    </div>
                    <div class="post-actions">
                        <button class="post-action-btn" onclick="quotePost('<?php echo htmlspecialchars($thread['username']); ?>', 'original')">
                            <i class="bi bi-quote"></i> Quote
                        </button>
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] != $thread['user_id']): ?>
                        <button class="post-action-btn" onclick="reportContent('thread', <?php echo $thread['id']; ?>)">
                            <i class="bi bi-flag"></i> Report
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Replies -->
    <?php foreach($posts as $post): ?>
    <div class="post-card" id="post-<?php echo $post['id']; ?>">
        <div class="post-layout">
            <div class="post-author">
                <div class="author-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="author-name">
                    <?php echo htmlspecialchars($post['username']); ?>
                    <?php if($post['is_premium']): ?>
                    <i class="bi bi-patch-check-fill text-warning" title="Premium Member"></i>
                    <?php endif; ?>
                </div>
                <div class="author-role">Member</div>
                <div class="author-stats">
                    <div><i class="bi bi-chat-dots"></i> <?php echo number_format($post['user_posts']); ?> posts</div>
                    <div><i class="bi bi-star"></i> <?php echo number_format($post['user_reputation']); ?> reputation</div>
                </div>
            </div>
            <div class="post-content-section">
                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                </div>
                <?php if($post['is_edited']): ?>
                <div class="alert alert-info py-2 px-3" style="font-size: 0.85rem;">
                    <i class="bi bi-pencil"></i> Edited <?php echo date('M j, Y \a\t g:i A', strtotime($post['edited_at'])); ?>
                </div>
                <?php endif; ?>
                <div class="post-footer">
                    <div class="post-date">
                        Posted <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                    </div>
                    <div class="post-actions">
                        <button class="post-action-btn" onclick="quotePost('<?php echo htmlspecialchars($post['username']); ?>', <?php echo $post['id']; ?>)">
                            <i class="bi bi-quote"></i> Quote
                        </button>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <?php if($_SESSION['user_id'] == $post['user_id']): ?>
                            <button class="post-action-btn" onclick="editPost(<?php echo $post['id']; ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="post-action-btn" onclick="deletePost(<?php echo $post['id']; ?>)">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                            <?php else: ?>
                            <button class="post-action-btn" onclick="reportContent('post', <?php echo $post['id']; ?>)">
                                <i class="bi bi-flag"></i> Report
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <nav class="my-4">
        <ul class="pagination justify-content-center">
            <?php if($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $page - 1; ?>">Previous</a>
            </li>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= min($total_pages, 10); $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $page + 1; ?>">Next</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Reply Form -->
    <?php if(isset($_SESSION['user_id']) && !$thread['is_locked']): ?>
    <div class="reply-form">
        <h3 class="mb-3"><i class="bi bi-reply-fill"></i> Post a Reply</h3>
        <form onsubmit="postReply(event)">
            <textarea id="replyContent" placeholder="Write your reply..." required></textarea>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> Be respectful and stay on topic
                </small>
                <button type="submit" class="btn btn-primary" id="replyBtn">
                    <i class="bi bi-send-fill"></i> Post Reply
                </button>
            </div>
        </form>
    </div>
    <?php elseif(!isset($_SESSION['user_id'])): ?>
    <div class="alert alert-info text-center">
        <i class="bi bi-info-circle-fill"></i>
        <a href="/login.php" class="alert-link">Login</a> or <a href="/register.php" class="alert-link">Register</a> to reply to this thread.
    </div>
    <?php else: ?>
    <div class="alert alert-warning text-center">
        <i class="bi bi-lock-fill"></i> This thread is locked. No new replies can be posted.
    </div>
    <?php endif; ?>
</div>

<script>
let hasReacted = <?php echo $user_reaction ? 'true' : 'false'; ?>;

function toggleReaction(threadId) {
    <?php if(!isset($_SESSION['user_id'])): ?>
    window.location.href = '/login.php';
    return;
    <?php else: ?>
    
    fetch('/api/forum.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_reaction&thread_id=' + threadId
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const btn = document.getElementById('reactionBtn');
            const count = document.getElementById('reactionCount');
            
            if(data.action === 'added') {
                btn.classList.add('active');
                count.textContent = parseInt(count.textContent) + 1;
                hasReacted = true;
            } else {
                btn.classList.remove('active');
                count.textContent = parseInt(count.textContent) - 1;
                hasReacted = false;
            }
        }
    });
    <?php endif; ?>
}

function toggleSubscription() {
    alert('Subscription feature coming soon!');
}

function shareThread() {
    if(navigator.share) {
        navigator.share({
            title: <?php echo json_encode($thread['title']); ?>,
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Link copied to clipboard!');
    }
}

function quotePost(username, postId) {
    const textarea = document.getElementById('replyContent');
    textarea.value = `@${username} said:\n> \n\n`;
    textarea.focus();
    textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function postReply(event) {
    event.preventDefault();
    
    const content = document.getElementById('replyContent').value.trim();
    const btn = document.getElementById('replyBtn');
    
    if(!content) return;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Posting...';
    
    fetch('/api/forum.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=create_post&thread_id=<?php echo $thread['id']; ?>&content=' + encodeURIComponent(content)
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to post reply');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Post Reply';
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Post Reply';
    });
}

function editPost(postId) {
    alert('Edit post feature coming soon!');
}

function deletePost(postId) {
    if(confirm('Are you sure you want to delete this post?')) {
        fetch('/api/forum.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_post&post_id=' + postId
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to delete post');
            }
        });
    }
}

function reportContent(type, id) {
    const reason = prompt('Please select a reason:\n1. Spam\n2. Offensive\n3. Harassment\n4. Inappropriate\n5. Other');
    if(reason) {
        alert('Report submitted. Thank you!');
    }
}
</script>

<?php include 'views/footer.php'; ?>