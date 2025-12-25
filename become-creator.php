<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if already a creator
$query = "SELECT is_creator FROM creator_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$existing = $stmt->fetch();

if($existing && $existing['is_creator']) {
    header('Location: creator-dashboard.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $creator_name = trim($_POST['creator_name']);
    $subscription_price = floatval($_POST['subscription_price']);
    $about_me = trim($_POST['about_me']);
    
    if(empty($creator_name)) {
        $error = 'Creator name is required';
    } else {
        try {
            // Create creator account
            $query = "INSERT INTO creator_settings 
                      (user_id, is_creator, creator_name, subscription_price, allow_tips, allow_custom_requests) 
                      VALUES (:user_id, TRUE, :creator_name, :subscription_price, TRUE, TRUE)
                      ON DUPLICATE KEY UPDATE 
                      is_creator = TRUE,
                      creator_name = :creator_name2,
                      subscription_price = :subscription_price2";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':creator_name', $creator_name);
            $stmt->bindParam(':subscription_price', $subscription_price);
            $stmt->bindParam(':creator_name2', $creator_name);
            $stmt->bindParam(':subscription_price2', $subscription_price);
            $stmt->execute();
            
            // Update user about_me
            if(!empty($about_me)) {
                $query = "UPDATE users SET about_me = :about_me WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':about_me', $about_me);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            header('Location: creator-dashboard.php?welcome=1');
            exit();
            
        } catch(Exception $e) {
            $error = 'Failed to create creator account';
        }
    }
}

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.creator-signup-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.hero-section {
    background: linear-gradient(135deg, #4267F5, #1D9BF0, #8B5CF6);
    color: white;
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    margin-bottom: 3rem;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin: 3rem 0;
}

.benefit-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
}

.benefit-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-blue);
}

.benefit-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}
</style>

<div class="page-content">
    <div class="creator-signup-container">
        
        <!-- Hero Section -->
        <div class="hero-section">
            <div style="font-size: 5rem; margin-bottom: 1rem;">⭐</div>
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">Become a Creator</h1>
            <p style="font-size: 1.3rem; opacity: 0.9; max-width: 600px; margin: 0 auto;">
                Share exclusive content, build your fanbase, and earn money from your creativity
            </p>
        </div>
        
        <!-- Benefits -->
        <div class="benefits-grid">
            <div class="benefit-card">
                <div class="benefit-icon">💰</div>
                <h3 style="margin-bottom: 0.5rem;">Earn Money</h3>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    Keep 80% of all earnings. Get paid for content sales, subscriptions, and tips.
                </p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">👥</div>
                <h3 style="margin-bottom: 0.5rem;">Build Fanbase</h3>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    Connect directly with your subscribers and grow your community.
                </p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">🎨</div>
                <h3 style="margin-bottom: 0.5rem;">Full Control</h3>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    Set your own prices, manage your content, and control your brand.
                </p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">📊</div>
                <h3 style="margin-bottom: 0.5rem;">Analytics</h3>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    Track your performance with detailed analytics and insights.
                </p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">🔒</div>
                <h3 style="margin-bottom: 0.5rem;">Secure Platform</h3>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    Your content is protected with advanced security measures.
                </p>
            </div>
            
            <div class="benefit-card">
                <div class="benefit-icon">⚡</div>
                <h3 style="margin-bottom: 0.5rem;">Instant Payouts</h3>
                <p style="color: var(--text-gray); line-height: 1.6;">
                    Withdraw your earnings anytime with Bitcoin payments.
                </p>
            </div>
        </div>
        
        <?php if($error): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <!-- Sign Up Form -->
        <div class="card">
            <h2 style="text-align: center; margin-bottom: 2rem;">Start Your Creator Journey</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>Creator Name *</label>
                    <input type="text" 
                           name="creator_name" 
                           class="form-control" 
                           placeholder="Your stage/creator name"
                           required>
                    <small style="color: var(--text-gray);">This is how you'll be known to your subscribers</small>
                </div>
                
                <div class="form-group">
                    <label>Monthly Subscription Price *</label>
                    <input type="number" 
                           name="subscription_price" 
                           class="form-control" 
                           min="4.99"
                           step="0.01"
                           value="9.99"
                           required>
                    <small style="color: var(--text-gray);">Recommended: $9.99 - $29.99 per month</small>
                </div>
                
                <div class="form-group">
                    <label>About You</label>
                    <textarea name="about_me" 
                              class="form-control" 
                              rows="6"
                              placeholder="Tell your subscribers about yourself and what content you'll create..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: start; gap: 0.75rem;">
                        <input type="checkbox" required style="margin-top: 0.25rem;">
                        <span style="color: var(--text-gray); line-height: 1.6;">
                            I agree to the <a href="/creator-terms.php" style="color: var(--primary-blue);">Creator Terms</a> and understand that I will receive 80% of all earnings (20% platform fee).
                        </span>
                    </label>
                </div>
                
                <button type="submit" class="btn-primary btn-block" style="font-size: 1.2rem; padding: 1rem;">
                    🚀 Start Creating
                </button>
            </form>
        </div>
        
        <!-- FAQ -->
        <div class="card" style="margin-top: 2rem;">
            <h3 style="text-align: center; margin-bottom: 2rem;">Frequently Asked Questions</h3>
            
            <div style="max-width: 700px; margin: 0 auto;">
                <div style="margin-bottom: 2rem;">
                    <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">How do I get paid?</h4>
                    <p style="color: var(--text-gray); line-height: 1.8;">
                        You earn coins from content sales, subscriptions, and tips. Convert your coins to Bitcoin and withdraw anytime.
                    </p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">What's the platform fee?</h4>
                    <p style="color: var(--text-gray); line-height: 1.8;">
                        We charge 20%, meaning you keep 80% of all earnings. This covers platform costs and payment processing.
                    </p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">What content can I post?</h4>
                    <p style="color: var(--text-gray); line-height: 1.8;">
                        Photos, videos, and photo/video sets. Content must comply with our guidelines and legal requirements.
                    </p>
                </div>
                
                <div>
                    <h4 style="color: var(--primary-blue); margin-bottom: 0.5rem;">Can I change my prices?</h4>
                    <p style="color: var(--text-gray); line-height: 1.8;">
                        Yes! You can adjust your subscription price and content prices anytime from your creator dashboard.
                    </p>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php include 'views/footer.php'; ?>