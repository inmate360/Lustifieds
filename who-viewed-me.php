<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$viewers = $userProfile->getProfileViews($_SESSION['user_id'], 50);

include 'views/header.php';
?>

<div class="page-content">
    <div class="container">
        <h2>👁️ Who Viewed My Profile</h2>
        
        <?php if(count($viewers) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem;">
            <?php foreach($viewers as $viewer): ?>
            <div class="card">
                <a href="profile.php?id=<?php echo $viewer['viewer_id']; ?>" style="text-decoration: none;">
                    <?php if($viewer['photo']): ?>
                    <img src="<?php echo htmlspecialchars($viewer['photo']); ?>" 
                         alt="<?php echo htmlspecialchars($viewer['username']); ?>" 
                         style="width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem;">
                    <?php else: ?>
                    <div style="width: 100%; height: 200px; background: var(--border-color); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin-bottom: 1rem;">
                        👤
                    </div>
                    <?php endif; ?>
                    
                    <h3 style="margin-bottom: 0.5rem; color: var(--text-white);"><?php echo htmlspecialchars($viewer['username']); ?></h3>
                    
                    <?php if($viewer['bio']): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars(substr($viewer['bio'], 0, 100)); ?><?php echo strlen($viewer['bio']) > 100 ? '...' : ''; ?>
                    </p>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-gray);">
                        <span><?php echo $viewer['view_count']; ?> view<?php echo $viewer['view_count'] > 1 ? 's' : ''; ?></span>
                        <span><?php echo date('M j, g:i A', strtotime($viewer['last_viewed'])); ?></span>
                    </div>
                </a>
                
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <a href="profile.php?id=<?php echo $viewer['viewer_id']; ?>" class="btn-primary btn-small btn-block">View Profile</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">👁️</div>
            <h3>No profile views yet</h3>
            <p style="color: var(--text-gray); margin: 1rem 0;">Complete your profile and be more active to get more views!</p>
            <a href="edit-profile.php" class="btn-primary">Complete Your Profile</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/footer.php'; ?>