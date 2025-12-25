<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
$query = "SELECT is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

if(!$user || !$user['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';
$log = [];

// Initialize settings
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['initialize'])) {
    try {
        // First, create the table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_by INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_key (setting_key)
        )";
        $db->exec($create_table);
        $log[] = "✓ Table created/verified";
        
        // Clear existing settings
        $db->exec("DELETE FROM site_settings");
        $log[] = "✓ Cleared existing settings";
        
        // Insert default settings
        $default_settings = [
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'description' => 'Enable/disable maintenance mode (0=disabled, 1=enabled)'
            ],
            [
                'key' => 'maintenance_title',
                'value' => 'Site Maintenance',
                'description' => 'Title for maintenance page'
            ],
            [
                'key' => 'maintenance_message',
                'value' => 'We are currently performing scheduled maintenance. Please check back soon!',
                'description' => 'Message to display during maintenance'
            ],
            [
                'key' => 'coming_soon_mode',
                'value' => '0',
                'description' => 'Enable/disable coming soon mode (0=disabled, 1=enabled)'
            ],
            [
                'key' => 'coming_soon_message',
                'value' => 'Turnpage is coming soon! We are working hard to bring you the best local hookup classifieds experience.',
                'description' => 'Message for coming soon page'
            ],
            [
                'key' => 'coming_soon_launch_date',
                'value' => NULL,
                'description' => 'Expected launch date for coming soon page'
            ],
            [
                'key' => 'allow_admin_access',
                'value' => '1',
                'description' => 'Allow admin access during maintenance (0=no, 1=yes)'
            ],
            [
                'key' => 'free_message_limit',
                'value' => '5',
                'description' => 'Number of messages free users can send per day'
            ]
        ];
        
        $query = "INSERT INTO site_settings (setting_key, setting_value, description, updated_by, updated_at) 
                  VALUES (:key, :value, :description, :user_id, NOW())";
        $stmt = $db->prepare($query);
        
        foreach($default_settings as $setting) {
            $stmt->bindParam(':key', $setting['key']);
            $stmt->bindParam(':value', $setting['value']);
            $stmt->bindParam(':description', $setting['description']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $log[] = "✓ Inserted: {$setting['key']} = " . ($setting['value'] ?? 'NULL');
        }
        
        $success = "Settings initialized successfully!";
        
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $log[] = "✗ Error: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = [];
try {
    $query = "SELECT * FROM site_settings ORDER BY setting_key";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Could not fetch settings: " . $e->getMessage();
}

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.admin-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.admin-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.admin-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.admin-section h2 {
    margin-bottom: 1.5rem;
    color: var(--primary-blue);
}

.log-entry {
    padding: 0.5rem;
    margin: 0.25rem 0;
    background: rgba(66, 103, 245, 0.05);
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.data-table th {
    background: rgba(66, 103, 245, 0.1);
    padding: 0.75rem;
    text-align: left;
    color: var(--text-white);
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
}

.data-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-gray);
}

.data-table tr:hover {
    background: rgba(66, 103, 245, 0.05);
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <h1>🔧 Initialize Site Settings</h1>
        <p style="color: var(--text-gray);">Set up default configuration values</p>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if(count($log) > 0): ?>
    <div class="admin-section">
        <h2>📋 Initialization Log</h2>
        <?php foreach($log as $entry): ?>
        <div class="log-entry"><?php echo htmlspecialchars($entry); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>🗄️ Current Settings in Database</h2>
        
        <?php if(count($current_settings) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Description</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($current_settings as $setting): ?>
                <tr>
                    <td><?php echo $setting['id']; ?></td>
                    <td><code><?php echo htmlspecialchars($setting['setting_key']); ?></code></td>
                    <td>
                        <strong style="color: var(--primary-blue);">
                            <?php echo $setting['setting_value'] !== null ? htmlspecialchars($setting['setting_value']) : 'NULL'; ?>
                        </strong>
                    </td>
                    <td><?php echo htmlspecialchars($setting['description']); ?></td>
                    <td><?php echo $setting['updated_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: rgba(239, 68, 68, 0.1); border-radius: 10px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
            <h3 style="color: var(--danger-red);">No Settings Found</h3>
            <p style="color: var(--text-gray);">The site_settings table is empty. Click below to initialize default settings.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="admin-section">
        <h2>⚡ Initialize Settings</h2>
        <p style="color: var(--text-gray); margin-bottom: 1.5rem;">
            This will create the site_settings table (if it doesn't exist) and populate it with default values. 
            Any existing settings will be cleared and replaced.
        </p>
        
        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
            <strong>⚠️ Warning:</strong> This will delete all existing settings and create new default ones.
        </div>
        
        <form method="POST" action="">
            <button type="submit" name="initialize" class="btn-primary" 
                    onclick="return confirm('Are you sure? This will reset all settings to defaults.');">
                🔄 Initialize Default Settings
            </button>
        </form>
    </div>

    <div class="admin-section">
        <h2>📚 Default Settings That Will Be Created</h2>
        <ul style="color: var(--text-gray); line-height: 2;">
            <li><code>maintenance_mode</code> = 0 (disabled)</li>
            <li><code>maintenance_title</code> = "Site Maintenance"</li>
            <li><code>maintenance_message</code> = Default maintenance message</li>
            <li><code>coming_soon_mode</code> = 0 (disabled)</li>
            <li><code>coming_soon_message</code> = Default coming soon message</li>
            <li><code>coming_soon_launch_date</code> = NULL</li>
            <li><code>allow_admin_access</code> = 1 (enabled)</li>
            <li><code>free_message_limit</code> = 5</li>
        </ul>
    </div>

    <div style="margin-top: 2rem; text-align: center;">
        <a href="settings.php" class="btn-secondary">
            ← Back to Settings
        </a>
    </div>
</div>

<?php include '../views/footer.php'; ?>