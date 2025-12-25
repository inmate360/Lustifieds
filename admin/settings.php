<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=/admin/settings.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
try {
    $query = "SELECT id, username, is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user || !$user['is_admin']) {
        header('Location: ../index.php');
        exit();
    }
} catch(PDOException $e) {
    error_log("Admin verification error: " . $e->getMessage());
    die("Database error. Please try again later.");
}

$success = '';
$error = '';

// Ensure site_settings table exists
try {
    $query = "CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        updated_by INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_key (setting_key)
    )";
    $db->exec($query);
} catch(PDOException $e) {
    error_log("Error creating site_settings table: " . $e->getMessage());
}

// Get current settings
$current_settings = [];
try {
    $query = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['save_general'])) {
        $site_name = $_POST['site_name'] ?? 'Hookup';
        $site_tagline = $_POST['site_tagline'] ?? '';
        $admin_email = $_POST['admin_email'] ?? '';
        $posts_per_page = $_POST['posts_per_page'] ?? '20';
        $allow_registration = isset($_POST['allow_registration']) ? '1' : '0';
        
        try {
            $settings_to_save = [
                'site_name' => $site_name,
                'site_tagline' => $site_tagline,
                'admin_email' => $admin_email,
                'posts_per_page' => $posts_per_page,
                'allow_registration' => $allow_registration
            ];
            
            foreach($settings_to_save as $key => $value) {
                $query = "INSERT INTO site_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :user_id2, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':user_id2', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'General settings saved successfully!';
            
            // Refresh settings
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            $stmt->execute();
            $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
        } catch(PDOException $e) {
            error_log("Error saving general settings: " . $e->getMessage());
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
    
    if(isset($_POST['save_maintenance'])) {
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        $maintenance_title = $_POST['maintenance_title'] ?? 'Site Maintenance';
        $maintenance_message = $_POST['maintenance_message'] ?? '';
        $allow_admin_access = isset($_POST['allow_admin_access']) ? '1' : '0';
        
        try {
            $settings_to_save = [
                'maintenance_mode' => $maintenance_mode,
                'maintenance_title' => $maintenance_title,
                'maintenance_message' => $maintenance_message,
                'allow_admin_access' => $allow_admin_access
            ];
            
            foreach($settings_to_save as $key => $value) {
                $query = "INSERT INTO site_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :user_id2, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':user_id2', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'Maintenance settings saved successfully!';
            
            // Refresh settings
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            $stmt->execute();
            $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
        } catch(PDOException $e) {
            error_log("Error saving maintenance settings: " . $e->getMessage());
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
    
    if(isset($_POST['save_coming_soon'])) {
        $coming_soon_mode = isset($_POST['coming_soon_mode']) ? '1' : '0';
        $coming_soon_message = $_POST['coming_soon_message'] ?? '';
        $coming_soon_launch_date = $_POST['coming_soon_launch_date'] ?? '';
        
        try {
            $settings_to_save = [
                'coming_soon_mode' => $coming_soon_mode,
                'coming_soon_message' => $coming_soon_message,
                'coming_soon_launch_date' => $coming_soon_launch_date
            ];
            
            foreach($settings_to_save as $key => $value) {
                $query = "INSERT INTO site_settings (setting_key, setting_value, updated_by) 
                          VALUES (:key, :value, :user_id)
                          ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :user_id2, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':user_id2', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $success = 'Coming soon settings saved successfully!';
            
            // Refresh settings
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            $stmt->execute();
            $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
        } catch(PDOException $e) {
            error_log("Error saving coming soon settings: " . $e->getMessage());
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Admin Dashboard</title>
    <style>
        :root {
            --bg-dark: #0a0a0f;
            --bg-card: #1a1a2e;
            --border-color: #2d2d44;
            --primary-blue: #4267f5;
            --text-white: #ffffff;
            --text-gray: #a0a0b0;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --info-cyan: #06b6d4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        .admin-header {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .admin-header h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .admin-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .admin-nav a {
            padding: 1rem;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-white);
            text-align: center;
            transition: all 0.3s;
            font-weight: 500;
        }

        .admin-nav a:hover {
            background: rgba(66, 103, 245, 0.1);
            border-color: var(--primary-blue);
            transform: translateY(-2px);
        }

        .admin-nav a.active {
            background: linear-gradient(135deg, rgba(66, 103, 245, 0.2), rgba(6, 182, 212, 0.2));
            border-color: var(--primary-blue);
        }

        .admin-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .admin-section h2 {
            color: var(--primary-blue);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-white);
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="datetime-local"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.875rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-white);
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-blue);
            background: rgba(66, 103, 245, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 0.5rem;
        }

        .form-group .helper-text {
            color: var(--text-gray);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 103, 245, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-green), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: rgba(107, 114, 128, 0.2);
            color: var(--text-white);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: rgba(107, 114, 128, 0.3);
            border-color: var(--text-gray);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--success-green);
            color: var(--success-green);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid var(--danger-red);
            color: var(--danger-red);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid var(--warning-orange);
            color: var(--warning-orange);
        }

        .alert-info {
            background: rgba(6, 182, 212, 0.2);
            border: 1px solid var(--info-cyan);
            color: var(--info-cyan);
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: rgba(66, 103, 245, 0.05);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--primary-blue);
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.95rem;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .admin-nav {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .button-group .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>⚙️ Site Settings</h1>
            <p style="color: var(--text-gray);">Configure site-wide settings, maintenance mode, and system preferences</p>
        </div>

        <!-- Navigation -->
        <div class="admin-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="users.php">👥 Users</a>
            <a href="listings.php">📝 Listings</a>
            <a href="upgrades.php">💎 Upgrades</a>
            <a href="reports.php">🚨 Reports</a>
            <a href="moderate-listings.php">⚖️ Moderate</a>
            <a href="announcements.php">📢 Announcements</a>
            <a href="categories.php">📁 Categories</a>
            <a href="settings.php" class="active">⚙️ Settings</a>
        </div>

        <!-- Alerts -->
        <?php if($success): ?>
        <div class="alert alert-success">
            <span style="font-size: 1.5rem;">✓</span>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error">
            <span style="font-size: 1.5rem;">✕</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div class="admin-section">
            <h2>📊 Current System Status</h2>
            <div class="status-grid">
                <div class="stat-card">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-value" style="color: <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'var(--danger-red)' : 'var(--success-green)'; ?>">
                        <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'ACTIVE' : 'INACTIVE'; ?>
                    </div>
                    <div class="stat-label">Maintenance Mode</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🚀</div>
                    <div class="stat-value" style="color: <?php echo ($current_settings['coming_soon_mode'] ?? '0') == '1' ? 'var(--warning-orange)' : 'var(--success-green)'; ?>">
                        <?php echo ($current_settings['coming_soon_mode'] ?? '0') == '1' ? 'ACTIVE' : 'INACTIVE'; ?>
                    </div>
                    <div class="stat-label">Coming Soon Mode</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🛡️</div>
                    <div class="stat-value" style="color: var(--info-cyan);">
                        <?php echo ($current_settings['allow_admin_access'] ?? '1') == '1' ? 'ENABLED' : 'DISABLED'; ?>
                    </div>
                    <div class="stat-label">Admin Access During Maintenance</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value" style="color: <?php echo ($current_settings['allow_registration'] ?? '1') == '1' ? 'var(--success-green)' : 'var(--danger-red)'; ?>">
                        <?php echo ($current_settings['allow_registration'] ?? '1') == '1' ? 'OPEN' : 'CLOSED'; ?>
                    </div>
                    <div class="stat-label">User Registration</div>
                </div>
            </div>
        </div>

        <!-- General Settings -->
        <div class="admin-section">
            <h2>🌐 General Settings</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($current_settings['site_name'] ?? 'Hookup'); ?>" required>
                    <div class="helper-text">The name of your website</div>
                </div>
                
                <div class="form-group">
                    <label>Site Tagline</label>
                    <input type="text" name="site_tagline" value="<?php echo htmlspecialchars($current_settings['site_tagline'] ?? 'Local Classifieds & Connections'); ?>">
                    <div class="helper-text">A short description of your site</div>
                </div>
                
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>" required>
                    <div class="helper-text">Email for system notifications</div>
                </div>
                
                <div class="form-group">
                    <label>Posts Per Page</label>
                    <input type="number" name="posts_per_page" value="<?php echo htmlspecialchars($current_settings['posts_per_page'] ?? '20'); ?>" min="5" max="100" required>
                    <div class="helper-text">Number of listings to show per page</div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" name="allow_registration" value="1" <?php echo ($current_settings['allow_registration'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        Allow new user registrations
                    </label>
                    <div class="helper-text">Turn off to close registration</div>
                </div>
                
                <button type="submit" name="save_general" class="btn btn-primary">
                    💾 Save General Settings
                </button>
            </form>
        </div>

        <!-- Maintenance Mode -->
        <div class="admin-section">
            <h2>🔧 Maintenance Mode</h2>
            <div class="alert alert-warning">
                <span style="font-size: 1.5rem;">⚠️</span>
                <span><strong>Warning:</strong> When enabled, the site will be inaccessible to all users except admins.</span>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label style="display: flex; align-items: center; font-size: 1.1rem;">
                        <input type="checkbox" name="maintenance_mode" value="1" 
                               <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>
                               style="width: 24px; height: 24px; margin-right: 0.75rem;">
                        <strong>Enable Maintenance Mode</strong>
                    </label>
                    <div class="helper-text">Turn this on to show a maintenance page to all visitors</div>
                </div>
                
                <div class="form-group">
                    <label>Maintenance Page Title</label>
                    <input type="text" name="maintenance_title" value="<?php echo htmlspecialchars($current_settings['maintenance_title'] ?? 'Site Maintenance'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Maintenance Message</label>
                    <textarea name="maintenance_message" rows="5" required><?php echo htmlspecialchars($current_settings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back soon!'); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" name="allow_admin_access" value="1" 
                               <?php echo ($current_settings['allow_admin_access'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        Allow admin access during maintenance
                    </label>
                    <div class="helper-text">Admins can access the site normally when enabled</div>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="save_maintenance" class="btn btn-primary">
                        💾 Save Maintenance Settings
                    </button>
                    <a href="../maintenance.php" target="_blank" class="btn btn-secondary">
                        👁️ Preview Maintenance Page
                    </a>
                </div>
            </form>
        </div>

        <!-- Coming Soon Mode -->
        <div class="admin-section">
            <h2>🚀 Coming Soon Mode</h2>
            <div class="alert alert-info">
                <span style="font-size: 1.5rem;">ℹ️</span>
                <span><strong>Info:</strong> Coming soon mode shows a launch countdown page. Use this before your official launch.</span>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label style="display: flex; align-items: center; font-size: 1.1rem;">
                        <input type="checkbox" name="coming_soon_mode" value="1" 
                               <?php echo ($current_settings['coming_soon_mode'] ?? '0') == '1' ? 'checked' : ''; ?>
                               style="width: 24px; height: 24px; margin-right: 0.75rem;">
                        <strong>Enable Coming Soon Mode</strong>
                    </label>
                    <div class="helper-text">Show a "coming soon" page with countdown timer</div>
                </div>
                
                <div class="form-group">
                    <label>Coming Soon Message</label>
                    <textarea name="coming_soon_message" rows="5" required><?php echo htmlspecialchars($current_settings['coming_soon_message'] ?? 'We are working hard to bring you the best experience. Stay tuned!'); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Launch Date & Time (optional)</label>
                    <input type="datetime-local" name="coming_soon_launch_date" value="<?php echo htmlspecialchars($current_settings['coming_soon_launch_date'] ?? ''); ?>">
                    <div class="helper-text">Set a launch date to show a countdown timer on the coming soon page</div>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="save_coming_soon" class="btn btn-success">
                        💾 Save Coming Soon Settings
                    </button>
                    <a href="../maintenance.php" target="_blank" class="btn btn-secondary">
                        👁️ Preview Coming Soon Page
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 2rem; color: var(--text-gray); margin-top: 2rem;">
            <p>Last updated by: <?php echo htmlspecialchars($user['username']); ?> | <?php echo date('F j, Y g:i A'); ?></p>
            <p style="margin-top: 0.5rem;">
                <a href="dashboard.php" style="color: var(--primary-blue); text-decoration: none;">← Back to Dashboard</a> | 
                <a href="../index.php" style="color: var(--info-cyan); text-decoration: none;">View Site</a>
            </p>
        </div>
    </div>
</body>
</html>