<?php
session_start();
require_once 'config/database.php';
require_once 'classes/SmartNotifications.php';
require_once 'classes/IncognitoMode.php';
require_once 'classes/SocialIntegration.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$notifClass = new SmartNotifications($db);
$incognito = new IncognitoMode($db);
$social = new SocialIntegration($db);

$success = '';
$error = '';

// Get current preferences
$notif_prefs = $notifClass->getPreferences($_SESSION['user_id']);
if(!$notif_prefs) {
    // Set defaults
    $notif_prefs = [
        'email_notifications' => true,
        'push_notifications' => true,
        'message_alerts' => true,
        'match_alerts' => true,
        'view_alerts' => false,
        'favorite_alerts' => true,
        'badge_alerts' => true,
        'daily_match_alerts' => true,
        'nearby_alerts' => false,
        'marketing_emails' => false,
        'quiet_hours_start' => null,
        'quiet_hours_end' => null
    ];
}

// Get user settings
$query = "SELECT * FROM user_settings WHERE user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_settings = $stmt->fetch();

if(!$user_settings) {
    // Create default settings
    $query = "INSERT INTO user_settings (user_id) VALUES (:user_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $user_settings = [
        'incognito_mode' => false,
        'allow_analytics' => true,
        'show_in_search' => true,
        'allow_message_requests' => true,
        'read_receipts' => true,
        'typing_indicators' => true,
        'profile_visibility' => 'public'
    ];
}

$incognito_session = $incognito->getSession($_SESSION['user_id']);
$connected_accounts = $social->getConnectedAccounts($_SESSION['user_id']);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['save_notifications'])) {
        $prefs = [
            'email_notifications' => isset($_POST['email_notifications']),
            'push_notifications' => isset($_POST['push_notifications']),
            'message_alerts' => isset($_POST['message_alerts']),
            'match_alerts' => isset($_POST['match_alerts']),
            'view_alerts' => isset($_POST['view_alerts']),
            'favorite_alerts' => isset($_POST['favorite_alerts']),
            'badge_alerts' => isset($_POST['badge_alerts']),
            'daily_match_alerts' => isset($_POST['daily_match_alerts']),
            'nearby_alerts' => isset($_POST['nearby_alerts']),
            'marketing_emails' => isset($_POST['marketing_emails']),
            'quiet_hours_start' => $_POST['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $_POST['quiet_hours_end'] ?? null
        ];
        
        if($notifClass->savePreferences($_SESSION['user_id'], $prefs)) {
            $success = 'Notification preferences saved successfully!';
            $notif_prefs = $prefs;
        } else {
            $error = 'Failed to save preferences';
        }
    }
    
    if(isset($_POST['save_privacy'])) {
        $query = "UPDATE user_settings SET 
                  show_in_search = :show_search,
                  allow_message_requests = :allow_messages,
                  read_receipts = :read_receipts,
                  typing_indicators = :typing,
                  profile_visibility = :visibility,
                  allow_analytics = :analytics
                  WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $show_search = isset($_POST['show_in_search']) ? 1 : 0;
        $allow_messages = isset($_POST['allow_message_requests']) ? 1 : 0;
        $read_receipts = isset($_POST['read_receipts']) ? 1 : 0;
        $typing = isset($_POST['typing_indicators']) ? 1 : 0;
        $analytics = isset($_POST['allow_analytics']) ? 1 : 0;
        
        $stmt->bindParam(':show_search', $show_search, PDO::PARAM_INT);
        $stmt->bindParam(':allow_messages', $allow_messages, PDO::PARAM_INT);
        $stmt->bindParam(':read_receipts', $read_receipts, PDO::PARAM_INT);
        $stmt->bindParam(':typing', $typing, PDO::PARAM_INT);
        $stmt->bindParam(':visibility', $_POST['profile_visibility']);
        $stmt->bindParam(':analytics', $analytics, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if($stmt->execute()) {
            $success = 'Privacy settings saved successfully!';
            // Refresh settings
            $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = :user_id LIMIT 1");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $user_settings = $stmt->fetch();
        } else {
            $error = 'Failed to save settings';
        }
    }
    
    if(isset($_POST['enable_incognito'])) {
        $result = $incognito->enable($_SESSION['user_id'], 24);
        if($result['success']) {
            $success = 'Incognito mode enabled for 24 hours!';
            $incognito_session = $incognito->getSession($_SESSION['user_id']);
        } else {
            $error = $result['error'];
        }
    }
    
    if(isset($_POST['disable_incognito'])) {
        if($incognito->disable($_SESSION['user_id'])) {
            $success = 'Incognito mode disabled';
            $incognito_session = null;
        } else {
            $error = 'Failed to disable incognito mode';
        }
    }
}

include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <h1>⚙️ Settings</h1>
        
        <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Notification Settings -->
        <div class="card">
            <h2>🔔 Notification Preferences</h2>
            <form method="POST" action="settings.php">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white); margin-bottom: 1rem;">
                        <input type="checkbox" name="email_notifications" <?php echo $notif_prefs['email_notifications'] ? 'checked' : ''; ?>>
                        📧 Email Notifications
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white); margin-bottom: 1rem;">
                        <input type="checkbox" name="push_notifications" <?php echo $notif_prefs['push_notifications'] ? 'checked' : ''; ?>>
                        📱 Push Notifications
                    </label>
                </div>
                
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem;">Alert Types</h3>
                
                <div style="display: grid; gap: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                        <input type="checkbox" name="message_alerts" <?php echo $notif_prefs['message_alerts'] ? 'checked' : ''; ?>>
                        💬 New Message Alerts
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                        <input type="checkbox" name="marketing_emails" <?php echo $notif_prefs['marketing_emails'] ? 'checked' : ''; ?>>
                        📬 Marketing & Newsletter Emails
                    </label>
                </div>
                
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem;">Quiet Hours</h3>
                <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 1rem;">
                    Don't receive notifications during these hours
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="quiet_hours_start" value="<?php echo $notif_prefs['quiet_hours_start'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="quiet_hours_end" value="<?php echo $notif_prefs['quiet_hours_end'] ?? ''; ?>">
                    </div>
                </div>
                
                <button type="submit" name="save_notifications" class="btn-primary btn-block">
                    Save Notification Settings
                </button>
            </form>
        </div>
        
        <!-- Privacy Settings -->
        <div class="card">
            <h2>🔒 Privacy Settings</h2>
            <form method="POST" action="settings.php">
                <div class="form-group">
                    <label>Profile Visibility</label>
                    <select name="profile_visibility">
                        <option value="public" <?php echo ($user_settings['profile_visibility'] ?? 'public') == 'public' ? 'selected' : ''; ?>>
                            Public - Everyone can see
                        </option>
                        <option value="members_only" <?php echo ($user_settings['profile_visibility'] ?? '') == 'members_only' ? 'selected' : ''; ?>>
                            Members Only
                        </option>
                        <option value="favorites_only" <?php echo ($user_settings['profile_visibility'] ?? '') == 'favorites_only' ? 'selected' : ''; ?>>
                            Favorites Only
                        </option>
                    </select>
                </div>
                
                <div style="display: grid; gap: 1rem; margin-top: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                        <input type="checkbox" name="allow_message_requests" value="1" <?php echo ($user_settings['allow_message_requests'] ?? true) ? 'checked' : ''; ?>>
                        Allow message requests from anyone
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                        <input type="checkbox" name="read_receipts" value="1" <?php echo ($user_settings['read_receipts'] ?? true) ? 'checked' : ''; ?>>
                        Send read receipts for messages
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                        <input type="checkbox" name="typing_indicators" value="1" <?php echo ($user_settings['typing_indicators'] ?? true) ? 'checked' : ''; ?>>
                        Show typing indicators
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-white);">
                        <input type="checkbox" name="allow_analytics" value="1" <?php echo ($user_settings['allow_analytics'] ?? true) ? 'checked' : ''; ?>>
                        Allow analytics to improve site experience
                    </label>
                </div>
                
                <button type="submit" name="save_privacy" class="btn-primary btn-block" style="margin-top: 1.5rem;">
                    Save Privacy Settings
                </button>
            </form>
        </div>
        
        <!-- Incognito Mode -->
        <div class="card">
            <h2>🕶️ Incognito Mode</h2>
            <div class="alert alert-info">
                <strong>Premium Feature</strong><br>
                Browse ads anonymously without leaving a trace. Your profile views won't be recorded.
            </div>
            
            <?php if($incognito_session): ?>
            <div style="background: rgba(155, 89, 182, 0.2); padding: 1.5rem; border-radius: 15px; margin: 1rem 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: var(--purple-accent);">✓ Incognito Mode Active</strong>
                        <p style="color: var(--text-gray); margin-top: 0.5rem;">
                            Expires: <?php echo date('M j, Y g:i A', strtotime($incognito_session['expires_at'])); ?>
                        </p>
                    </div>
                    <form method="POST" action="settings.php" style="margin: 0;">
                        <button type="submit" name="disable_incognito" class="btn-secondary">
                            Disable
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <form method="POST" action="settings.php">
                <button type="submit" name="enable_incognito" class="btn-primary btn-block">
                    Enable Incognito Mode (24 hours)
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <!-- Social Connections -->
        <div class="card">
            <h2>🔗 Social Media Connections</h2>
            <p style="color: var(--text-gray); margin-bottom: 1.5rem;">
                Connect your social media accounts for quick login
            </p>
            
            <div style="display: grid; gap: 1rem;">
                <?php 
                $providers = [
                    'google' => ['name' => 'Google', 'icon' => '🔍', 'color' => '#DB4437'],
                    'facebook' => ['name' => 'Facebook', 'icon' => '📘', 'color' => '#1877F2']
                ];
                
                foreach($providers as $provider => $info):
                    $is_connected = false;
                    foreach($connected_accounts as $account) {
                        if($account['provider'] == $provider) {
                            $is_connected = true;
                            break;
                        }
                    }
                ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: rgba(66, 103, 245, 0.05); border-radius: 15px;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 2rem;"><?php echo $info['icon']; ?></span>
                        <div>
                            <strong style="color: var(--text-white);"><?php echo $info['name']; ?></strong>
                            <?php if($is_connected): ?>
                            <p style="color: var(--success-green); font-size: 0.85rem; margin-top: 0.2rem;">
                                ✓ Connected
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if($is_connected): ?>
                    <a href="disconnect-social.php?provider=<?php echo $provider; ?>" class="btn-danger btn-small">
                        Disconnect
                    </a>
                    <?php else: ?>
                    <a href="connect-social.php?provider=<?php echo $provider; ?>" class="btn-primary btn-small">
                        Connect
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Account Management -->
        <div class="card">
            <h2>👤 Account Management</h2>
            <div style="display: grid; gap: 1rem;">
                <a href="edit-profile.php" class="btn-secondary btn-block">
                    Edit Profile
                </a>
                <a href="profile-photos.php" class="btn-secondary btn-block">
                    Manage Photos
                </a>
                <a href="change-password.php" class="btn-secondary btn-block">
                    Change Password
                </a>
                <a href="delete-account.php" class="btn-danger btn-block" onclick="return confirm('Are you sure? This action cannot be undone!')">
                    Delete Account
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>