<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MediaContent.php';
require_once 'classes/CoinsSystem.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$mediaContent = new MediaContent($db);
$coinsSystem = new CoinsSystem($db);

// Check if user is a creator
$query = "SELECT * FROM creator_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$creator_settings = $stmt->fetch();

if(!$creator_settings || !$creator_settings['is_creator']) {
    header('Location: become-creator.php');
    exit();
}

// Get stats
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM media_content WHERE creator_id = :user_id) as total_content,
    (SELECT COUNT(*) FROM media_content WHERE creator_id = :user_id AND status = 'published') as published_content,
    (SELECT COUNT(*) FROM creator_subscriptions WHERE creator_id = :user_id AND status = 'active') as active_subscribers,
    (SELECT SUM(price_paid) FROM media_purchases WHERE creator_id = :user_id) as total_earnings,
    (SELECT COUNT(*) FROM media_purchases WHERE creator_id = :user_id) as total_sales";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->fetch();

// Get recent content
$recent_content = $mediaContent->getCreatorContent($_SESSION['user_id'], null, 10);

// Get coin balance
$coin_balance = $coinsSystem->getBalance($_SESSION['user_id']);

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.dashboard-container {
    padding: 2rem 0;
}

.dashboard-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-blue);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-blue);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
}

.content-table {
    width: 100%;
    border-collapse: collapse;
}

.content-table th {
    text-align: left;
    padding: 1rem;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-gray);
    font-weight: 600;
}

.content-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-published {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-draft {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

@media (max-width: 768px) {
    .content-table {
        font-size: 0.9rem;
    }
    
    .content-table th,
    .content-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<div class="page-content">
    <div class="container">
        <div class="dashboard-container">
            
            <!-- Header -->
            <div class="dashboard-header">
                <div>
                    <h1 style="margin: 0 0 0.5rem;">📊 Creator Dashboard</h1>
                    <p style="opacity: 0.9; margin: 0;">Manage your content and track your earnings</p>
                </div>
                <a href="upload-content.php" class="btn-light" style="background: white; color: #1D9BF0;">
                    ⬆️ Upload New Content
                </a>
            </div>
            
            <?php if(isset($_GET['uploaded'])): ?>
            <div class="alert alert-success" style="margin-bottom: 2rem;">
                ✅ Content uploaded successfully!
            </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?php echo number_format($coin_balance, 0); ?></div>
                    <div class="stat-label">Coin Balance</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💵</div>
                    <div class="stat-value">$<?php echo number_format($stats['total_earnings'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Earnings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-value"><?php echo number_format($stats['active_subscribers'] ?? 0); ?></div>
                    <div class="stat-label">Subscribers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📸</div>
                    <div class="stat-value"><?php echo number_format($stats['published_content'] ?? 0); ?></div>
                    <div class="stat-label">Published Posts</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-value"><?php echo number_format($stats['total_sales'] ?? 0); ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card" style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1.5rem;">⚡ Quick Actions</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="upload-content.php" class="btn-primary btn-block">
                        ⬆️ Upload Content
                    </a>
                    <a href="creator-analytics.php" class="btn-secondary btn-block">
                        📈 View Analytics
                    </a>
                    <a href="withdraw-earnings.php" class="btn-secondary btn-block">
                        💸 Withdraw Earnings
                    </a>
                    <a href="creator-settings.php" class="btn-secondary btn-block">
                        ⚙️ Creator Settings
                    </a>
                </div>
            </div>
            
            <!-- Recent Content -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">📋 Recent Content</h3>
                
                <?php if(empty($recent_content)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-gray);">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📸</div>
                    <h4>No content yet</h4>
                    <p>Start uploading exclusive content for your fans!</p>
                    <a href="upload-content.php" class="btn-primary" style="margin-top: 1rem;">
                        Upload Your First Content
                    </a>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="content-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Sales</th>
                                <th>Views</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_content as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong><br>
                                    <small style="color: var(--text-gray);">
                                        <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                    </small>
                                </td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $item['content_type'])); ?></td>
                                <td>
                                    <?php if($item['is_free']): ?>
                                    <span style="color: var(--success-green);">Free</span>
                                    <?php else: ?>
                                    <?php echo number_format($item['price']); ?> coins
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($item['purchases']); ?></td>
                                <td><?php echo number_format($item['view_count']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $item['status']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit-content.php?id=<?php echo $item['id']; ?>" style="color: var(--primary-blue); margin-right: 0.5rem;">
                                        Edit
                                    </a>
                                    <a href="view-content.php?id=<?php echo $item['id']; ?>" style="color: var(--primary-blue);">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>