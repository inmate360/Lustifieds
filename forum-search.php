<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();
$forum = new Forum($db);

$search_query = trim($_GET['q'] ?? '');
$page = (int)($_GET['page'] ?? 1);

$results = [];
$total_results = 0;

if($search_query) {
    $results = $forum->searchThreads($search_query, $page, 20);
    
    // Get total count
    $query = "SELECT COUNT(*) FROM forum_threads t
              WHERE MATCH(t.title, t.content) AGAINST(:search IN NATURAL LANGUAGE MODE)
              AND t.is_deleted = FALSE";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':search', $search_query);
    $stmt->execute();
    $total_results = $stmt->fetchColumn();
}

$total_pages = ceil($total_results / 20);

include 'views/header.php';
?>

<style>
.search-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.search-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
}

.search-box {
    max-width: 700px;
    margin: 1.5rem auto 0;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 1rem 3.5rem 1rem 1.5rem;
    border-radius: 50px;
    border: none;
    font-size: 1.1rem;
}

.search-box button {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    color: white;
    cursor: pointer;
}

.search-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.result-item {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s;
}

.result-item:hover {
    border-color: var(--primary-blue);
    transform: translateX(5px);
}

.result-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 0.75rem;
    display: block;
    text-decoration: none;
}

.result-title:hover {
    color: var(--primary-blue);
}

.result-excerpt {
    color: var(--text-gray);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.result-meta {
    display: flex;
    gap: 1.5rem;
    font-size: 0.9rem;
    color: var(--text-gray);
    flex-wrap: wrap;
}

.category-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.no-results {
    text-align: center;
    padding: 4rem 2rem;
}

.no-results-icon {
    font-size: 5rem;
    opacity: 0.3;
    margin-bottom: 1rem;
}

.search-suggestions {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-top: 2rem;
}
</style>

<div class="search-container">
    
    <!-- Search Header -->
    <div class="search-header">
        <h1 class="text-center mb-3">
            <i class="bi bi-search"></i> Search Forum
        </h1>
        
        <!-- Search Box -->
        <div class="search-box">
            <form action="/forum-search.php" method="GET">
                <input type="text" 
                       name="q" 
                       placeholder="Search discussions, topics, or keywords..." 
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       required>
                <button type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </form>
        </div>
    </div>

    <?php if($search_query): ?>
        <?php if(!empty($results)): ?>
            
            <!-- Results Header -->
            <div class="search-results-header">
                <h2>
                    Found <?php echo number_format($total_results); ?> result<?php echo $total_results != 1 ? 's' : ''; ?>
                    for "<?php echo htmlspecialchars($search_query); ?>"
                </h2>
                <span class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            </div>

            <!-- Results List -->
            <?php foreach($results as $result): ?>
            <div class="result-item">
                <a href="/forum-thread.php?slug=<?php echo $result['slug']; ?>" class="result-title">
                    <?php echo htmlspecialchars($result['title']); ?>
                </a>
                
                <div class="result-excerpt">
                    <?php 
                    $excerpt = strip_tags($result['content']);
                    echo htmlspecialchars(substr($excerpt, 0, 200));
                    echo strlen($excerpt) > 200 ? '...' : '';
                    ?>
                </div>
                
                <div class="result-meta">
                    <span class="category-badge" style="background: <?php echo $result['color'] ?? '#6B7280'; ?>20; color: <?php echo $result['color'] ?? '#6B7280'; ?>">
                        <i class="bi bi-folder-fill"></i>
                        <?php echo htmlspecialchars($result['category_name']); ?>
                    </span>
                    <span>
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($result['username']); ?>
                    </span>
                    <span>
                        <i class="bi bi-clock"></i>
                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                    </span>
                    <span>
                        <i class="bi bi-chat-dots-fill"></i>
                        <?php echo number_format($result['replies_count']); ?> replies
                    </span>
                    <span>
                        <i class="bi bi-eye-fill"></i>
                        <?php echo number_format($result['views']); ?> views
                    </span>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

        <?php else: ?>
            
            <!-- No Results -->
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="bi bi-search"></i>
                </div>
                <h2>No results found for "<?php echo htmlspecialchars($search_query); ?>"</h2>
                <p class="text-muted mt-3">
                    Try different keywords or browse our categories to find what you're looking for.
                </p>
                <a href="/forum.php" class="btn btn-primary mt-3">
                    <i class="bi bi-house-fill"></i> Back to Forum
                </a>
            </div>

            <!-- Search Suggestions -->
            <div class="search-suggestions">
                <h4 class="mb-3"><i class="bi bi-lightbulb"></i> Search Tips</h4>
                <ul class="text-muted">
                    <li>Try using different keywords</li>
                    <li>Check your spelling</li>
                    <li>Use more general terms</li>
                    <li>Browse categories instead</li>
                </ul>
            </div>

        <?php endif; ?>
    <?php else: ?>
        
        <!-- Empty State -->
        <div class="text-center py-5">
            <i class="bi bi-search" style="font-size: 5rem; opacity: 0.3;"></i>
            <h3 class="mt-3">Search the Forum</h3>
            <p class="text-muted">Enter keywords to find discussions, topics, or posts</p>
        </div>

    <?php endif; ?>
</div>

<?php include 'views/footer.php'; ?>