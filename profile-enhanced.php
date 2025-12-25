<?php
session_start();
require_once 'config/database.php';
require_once 'classes/MediaContent.php';
require_once 'classes/CoinsSystem.php';

if(!isset($_GET['id'])) {
    if(isset($_SESSION['user_id'])) {
        header('Location: profile-enhanced.php?id=' . $_SESSION['user_id']);
    } else {
        header('Location: login.php');
    }
    exit();
}

$database = new Database();
$db = $database->getConnection();
$mediaContent = new MediaContent($db);
$coinsSystem = new CoinsSystem($db);

$profile_user_id = (int)$_GET['id'];

// Get user data
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM media_content WHERE creator_id = u.id AND status = 'published') as content_count,
          (SELECT COUNT(*) FROM creator_subscriptions WHERE creator_id = u.id AND status = 'active') as subscriber_count,
          cs.is_creator, cs.creator_name, cs.subscription_price, cs.allow_tips
          FROM users u
          LEFT JOIN creator_settings cs ON cs.user_id = u.id
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $profile_user_id);
$stmt->execute();
$profile_data = $stmt->fetch();

if(!$profile_data) {
    header('Location: choose-location.php');
    exit();
}

$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id;
$is_creator = $profile_data['is_creator'] ?? false;

// Get creator content
$content_items = [];
if($is_creator) {
    $content_items = $mediaContent->getCreatorContent($profile_user_id, 'published', 12);
}

// Check subscription status
$is_subscribed = false;
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $query = "SELECT id FROM creator_subscriptions 
              WHERE creator_id = :creator_id AND subscriber_id = :subscriber_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':creator_id', $profile_user_id);
    $stmt->bindParam(':subscriber_id', $_SESSION['user_id']);
    $stmt->execute();
    $is_subscribed = $stmt->fetch() !== false;
}

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.profile-cover {
    height: 300px;
    background: linear-gradient(135deg, #4267F5, #1D9BF0, #8B5CF6);
    position: relative;
    border-radius: 0 0 30px 30px;
}

.profile-main {
    max-width: 1200px;
    margin: -150px auto 0;
    padding: 0 20px;
    position: relative;
    z-index: 10;
}

.profile-header-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.profile-avatar-large {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    border: 6px solid var(--card-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 6rem;
    margin: -120px auto 2rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    position: relative;
}

.verified-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: #3b82f6;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    border: 4px solid var(--card-bg);
}

.stats-row {
    display: flex;
    justify-content: center;
    gap: 3rem;
    margin: 2rem 0;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-blue);
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin: 2rem 0;
}

.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.content-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}

.content-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(66, 103, 245, 0.3);
}

.content-thumbnail {
    width: 100%;
    height: 300px;
    object-fit: cover;
    position: relative;
}

.content-thumbnail.blurred {
    filter: blur(20px);
}

.unlock-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}

.price-badge {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-size: 1.3rem;
    font-weight: 800;
    box-shadow: 0 4px 15px rgba(251, 191, 36, 0.5);
}

.content-info {
    padding: 1.5rem;
}

.content-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-white);
    margin-bottom: 0.5rem;
}

.content-stats {
    display: flex;
    gap: 1.5rem;
    color: var(--text-gray);
    font-size: 0.9rem;
}

.tab-nav {
    display: flex;
    gap: 1rem;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 2rem;
    overflow-x: auto;
}

.tab-item {
    padding: 1rem 2rem;
    border-bottom: 3px solid transparent;
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
}

.tab-item:hover {
    color: var(--primary-blue);
}

.tab-item.active {
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

@media (max-width: 768px) {
    .profile-cover {
        height: 200px;
    }
    
    .profile-avatar-large {
        width: 120px;
        height: 120px;
        font-size: 4rem;
        margin-top: -80px;
    }
    
    .stats-row {
        gap: 1.5rem;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn-primary,
    .action-buttons .btn-secondary {
        width: 100%;
    }
}
</style>

<div class="profile-cover"></div>

<div class="profile-main">
    
    <!-- Profile Header -->
    <div class="profile-header-card">
        <div class="profile-avatar-large">
            👤
            <?php if($profile_data['is_verified']): ?>
            <div class="verified-badge" title="Verified Creator">✓</div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center;">
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                <?php echo htmlspecialchars($profile_data['creator_name'] ?? $profile_data['username']); ?>
            </h1>
            <p style="color: var(--text-gray); font-size: 1.1rem; margin-bottom: 0;">
                @<?php echo htmlspecialchars($profile_data['username']); ?>
            </p>
            
            <?php if($profile_data['about_me']): ?>
            <p style="color: var(--text-gray); max-width: 600px; margin: 1.5rem auto; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($profile_data['about_me'])); ?>
            </p>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($profile_data['content_count']); ?></div>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($profile_data['subscriber_count']); ?></div>
                    <div class="stat-label">Subscribers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($profile_data['profile_views'] ?? 0); ?></div>
                    <div class="stat-label">Views</div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if($is_own_profile): ?>
                    <a href="creator-dashboard.php" class="btn-primary">
                        📊 Creator Dashboard
                    </a>
                    <a href="upload-content.php" class="btn-primary">
                        ⬆️ Upload Content
                    </a>
                    <a href="edit-profile.php" class="btn-secondary">
                        ✏️ Edit Profile
                    </a>
                <?php else: ?>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($is_creator): ?>
                            <?php if($is_subscribed): ?>
                                <button class="btn-success" disabled style="cursor: not-allowed;">
                                    ✓ Subscribed
                                </button>
                            <?php else: ?>
                                <button onclick="subscribe(<?php echo $profile_user_id; ?>, <?php echo $profile_data['subscription_price']; ?>)" class="btn-primary">
                                    ⭐ Subscribe - $<?php echo $profile_data['subscription_price']; ?>/month
                                </button>
                            <?php endif; ?>
                            
                            <?php if($profile_data['allow_tips']): ?>
                            <button onclick="showTipModal()" class="btn-secondary">
                                💰 Send Tip
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- MESSAGE CREATOR BUTTON -->
                        <a href="messages-compose.php?to=<?php echo $profile_user_id; ?>" class="btn-secondary">
                            💬 Message <?php echo $is_creator ? 'Creator' : 'User'; ?>
                        </a>
                    <?php else: ?>
                        <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-primary">
                            Login to Subscribe
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Content Tabs -->
    <?php if($is_creator): ?>
    <div class="card">
        <div class="tab-nav">
            <div class="tab-item active" onclick="showTab('content')">
                📸 Content
            </div>
            <div class="tab-item" onclick="showTab('about')">
                ℹ️ About
            </div>
        </div>
        
        <!-- Content Tab -->
        <div id="contentTab" class="tab-content">
            <?php if(empty($content_items)): ?>
            <div style="text-align: center; padding: 4rem 2rem; color: var(--text-gray);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📸</div>
                <h3>No content yet</h3>
                <p><?php echo $is_own_profile ? 'Start uploading exclusive content!' : 'This creator hasn\'t posted any content yet'; ?></p>
            </div>
            <?php else: ?>
            <div class="content-grid">
                <?php foreach($content_items as $item): ?>
                <div class="content-card" onclick="window.location.href='/view-content.php?id=<?php echo $item['id']; ?>'">
                    <div style="position: relative;">
                        <?php if($item['thumbnail']): ?>
                        <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                             class="content-thumbnail <?php echo $item['blur_preview'] && !$is_own_profile ? 'blurred' : ''; ?>">
                        <?php else: ?>
                        <div class="content-thumbnail" style="background: linear-gradient(135deg, #4267F5, #8B5CF6); display: flex; align-items: center; justify-content: center; font-size: 4rem;">
                            <?php echo $item['content_type'] == 'video' ? '🎥' : '📷'; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!$item['is_free'] && !$is_own_profile): ?>
                        <div class="unlock-overlay">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
                            <div class="price-badge">
                                💰 <?php echo number_format($item['price']); ?> coins
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="content-info">
                        <div class="content-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="content-stats">
                            <span>👁️ <?php echo number_format($item['view_count']); ?></span>
                            <span>❤️ <?php echo number_format($item['like_count']); ?></span>
                            <span>🛒 <?php echo number_format($item['purchases']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- About Tab -->
        <div id="aboutTab" class="tab-content" style="display: none;">
            <div style="max-width: 800px; margin: 0 auto; padding: 2rem;">
                <h3 style="margin-bottom: 1rem;">About This Creator</h3>
                <p style="color: var(--text-gray); line-height: 1.8; margin-bottom: 2rem;">
                    <?php echo nl2br(htmlspecialchars($profile_data['about_me'] ?? 'No bio available')); ?>
                </p>
                
                <h4 style="margin-bottom: 1rem;">Creator Stats</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div style="background: rgba(66, 103, 245, 0.1); padding: 1.5rem; border-radius: 15px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary-blue);">
                            <?php echo number_format($profile_data['content_count']); ?>
                        </div>
                        <div style="color: var(--text-gray);">Total Posts</div>
                    </div>
                    <div style="background: rgba(66, 103, 245, 0.1); padding: 1.5rem; border-radius: 15px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary-blue);">
                            <?php echo number_format($profile_data['subscriber_count']); ?>
                        </div>
                        <div style="color: var(--text-gray);">Subscribers</div>
                    </div>
                    <div style="background: rgba(66, 103, 245, 0.1); padding: 1.5rem; border-radius: 15px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary-blue);">
                            <?php echo date('M Y', strtotime($profile_data['created_at'])); ?>
                        </div>
                        <div style="color: var(--text-gray);">Joined</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<!-- Tip Modal -->
<div id="tipModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 500px; width: 90%; margin: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">💰 Send a Tip</h3>
        <p style="color: var(--text-gray); margin-bottom: 2rem;">
            Show your appreciation to <?php echo htmlspecialchars($profile_data['username']); ?>
        </p>
        
        <div class="form-group">
            <label>Amount (coins)</label>
            <input type="number" id="tipAmount" class="form-control" min="10" placeholder="Enter amount...">
        </div>
        
        <div class="form-group">
            <label>Message (optional)</label>
            <textarea id="tipMessage" class="form-control" rows="3" placeholder="Add a message..."></textarea>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <button onclick="closeTipModal()" class="btn-secondary btn-block">Cancel</button>
            <button onclick="sendTip()" class="btn-primary btn-block">Send Tip</button>
        </div>
    </div>
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    
    event.target.classList.add('active');
    document.getElementById(tab + 'Tab').style.display = 'block';
}

function subscribe(creatorId, price) {
    if(confirm(`Subscribe for $${price}/month?`)) {
        window.location.href = `/subscribe.php?creator=${creatorId}`;
    }
}

function showTipModal() {
    document.getElementById('tipModal').style.display = 'flex';
}

function closeTipModal() {
    document.getElementById('tipModal').style.display = 'none';
}

function sendTip() {
    const amount = document.getElementById('tipAmount').value;
    const message = document.getElementById('tipMessage').value;
    
    if(!amount || amount < 10) {
        alert('Minimum tip is 10 coins');
        return;
    }
    
    fetch('/api/send-tip.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            to_user_id: <?php echo $profile_user_id; ?>,
            amount: amount,
            message: message
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('✅ Tip sent successfully!');
            closeTipModal();
            location.reload();
        } else {
            alert('❌ ' + (data.error || 'Failed to send tip'));
        }
    });
}

document.getElementById('tipModal')?.addEventListener('click', function(e) {
    if(e.target === this) closeTipModal();
});
</script>

<?php include 'views/footer.php'; ?>