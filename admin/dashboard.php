<?php
session_start();
require_once '../config/database.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session timeout check (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verify admin status
try {
    $query = "SELECT id, username, email, is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_user || !$current_user['is_admin']) {
        header('Location: ../index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Admin verification error: " . $e->getMessage());
    die("Database error. Please try again later.");
}

// Helper function for safe queries
function safeQuery($db, $query, $params = []) {
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " | Query: " . $query);
        return false;
    }
}

// Get comprehensive statistics
$stats = [];

// User Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
$stats['new_users_today'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stats['new_users_week'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['active_users_24h'] = $result ? $result->fetch()['count'] : 0;

// Listing Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings");
$stats['total_listings'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE status = 'active'");
$stats['active_listings'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE status = 'pending'");
$stats['pending_listings'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE DATE(created_at) = CURDATE()");
$stats['new_listings_today'] = $result ? $result->fetch()['count'] : 0;

// Message Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM messages");
$stats['total_messages'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()");
$stats['messages_today'] = $result ? $result->fetch()['count'] : 0;

// Premium Membership Statistics
$result = safeQuery($db, "SELECT COUNT(DISTINCT user_id) as count FROM user_subscriptions WHERE status = 'active'");
$stats['premium_members'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'pending'");
$stats['pending_upgrades'] = $result ? $result->fetch()['count'] : 0;

// Revenue Statistics (if available)
$result = safeQuery($db, "SELECT SUM(amount) as total FROM user_subscriptions WHERE status = 'active'");
$stats['total_revenue'] = $result ? ($result->fetch()['total'] ?? 0) : 0;

$result = safeQuery($db, "SELECT SUM(amount) as total FROM user_subscriptions WHERE status = 'active' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats['revenue_30days'] = $result ? ($result->fetch()['total'] ?? 0) : 0;

// Report Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stats['pending_reports'] = $result ? $result->fetch()['count'] : 0;

$result = safeQuery($db, "SELECT COUNT(*) as count FROM reports WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()");
$stats['resolved_reports_today'] = $result ? $result->fetch()['count'] : 0;

// Featured Ads Statistics
$result = safeQuery($db, "SELECT COUNT(*) as count FROM featured_ads WHERE status = 'active' AND end_date > NOW()");
$stats['active_featured'] = $result ? $result->fetch()['count'] : 0;

// System Health Checks
$stats['database_size'] = 0;
$result = safeQuery($db, "SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE()");
if ($result) {
    $stats['database_size'] = round($result->fetch()['size_mb'] ?? 0, 2);
}

// Recent Activity Data
$recent_users = [];
$result = safeQuery($db, "SELECT id, username, email, created_at, last_active FROM users ORDER BY created_at DESC LIMIT 10");
if ($result) {
    $recent_users = $result->fetchAll(PDO::FETCH_ASSOC);
}

$recent_listings = [];
$result = safeQuery($db, "SELECT l.id, l.title, l.status, l.created_at, u.username, c.name as city_name 
    FROM listings l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN cities c ON l.city_id = c.id
    ORDER BY l.created_at DESC LIMIT 10");
if ($result) {
    $recent_listings = $result->fetchAll(PDO::FETCH_ASSOC);
}

$pending_reports_list = [];
$result = safeQuery($db, "SELECT r.id, r.reason, r.created_at, r.report_type, u.username as reporter 
    FROM reports r
    LEFT JOIN users u ON r.reporter_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC LIMIT 10");
if ($result) {
    $pending_reports_list = $result->fetchAll(PDO::FETCH_ASSOC);
}

// Chart data for last 30 days
$chart_data = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_data['dates'][] = date('M j', strtotime($date));
    
    $result = safeQuery($db, "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = :date", [':date' => $date]);
    $chart_data['users'][] = $result ? $result->fetch()['count'] : 0;
    
    $result = safeQuery($db, "SELECT COUNT(*) as count FROM listings WHERE DATE(created_at) = :date", [':date' => $date]);
    $chart_data['listings'][] = $result ? $result->fetch()['count'] : 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hookup</title>
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 1rem;
        }

        .admin-header {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .admin-header h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-header .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
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

        .admin-nav a .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-red);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--info-cyan));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(66, 103, 245, 0.3);
            border-color: var(--primary-blue);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-badge.success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-green);
        }

        .stat-badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-orange);
        }

        .stat-badge.info {
            background: rgba(6, 182, 212, 0.2);
            color: var(--info-cyan);
        }

        .stat-badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-red);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .admin-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .admin-section.full-width {
            grid-column: 1 / -1;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-header h2 {
            color: var(--primary-blue);
            font-size: 1.5rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 103, 245, 0.4);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: rgba(66, 103, 245, 0.1);
            padding: 1rem;
            text-align: left;
            color: var(--text-white);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.9rem;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .data-table tr:hover {
            background: rgba(66, 103, 245, 0.05);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-green);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-orange);
        }

        .status-inactive {
            background: rgba(107, 114, 128, 0.2);
            color: var(--text-gray);
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-block;
            margin-right: 0.5rem;
            transition: all 0.3s;
            font-weight: 500;
        }

        .action-btn.view {
            background: rgba(6, 182, 212, 0.2);
            color: var(--info-cyan);
        }

        .action-btn.edit {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-orange);
        }

        .action-btn.delete {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-red);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.2);
        }

        .chart-container {
            height: 300px;
            margin-top: 1rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action-card {
            background: rgba(66, 103, 245, 0.1);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quick-action-card:hover {
            border-color: var(--primary-blue);
            transform: scale(1.05);
        }

        .quick-action-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .system-health {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .health-item {
            background: rgba(66, 103, 245, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
        }

        .health-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .health-status.good {
            background: var(--success-green);
            box-shadow: 0 0 8px var(--success-green);
        }

        .health-status.warning {
            background: var(--warning-orange);
            box-shadow: 0 0 8px var(--warning-orange);
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .admin-nav {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }

            .data-table {
                font-size: 0.8rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-gray);
        }

        .empty-state .icon {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1>🛡️ Administrator Dashboard</h1>
                <p style="color: var(--text-gray); margin-top: 0.5rem;">
                    Real-time system monitoring and management
                </p>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($current_user['username']); ?></div>
                    <div style="color: var(--text-gray); font-size: 0.85rem;">Administrator</div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="admin-nav">
            <a href="dashboard.php" class="active">📊 Dashboard</a>
            <a href="users.php">👥 Users</a>
            <a href="listings.php">📝 Listings</a>
            <a href="upgrades.php">
                💎 Upgrades
                <?php if ($stats['pending_upgrades'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending_upgrades']; ?></span>
                <?php endif; ?>
            </a>
            <a href="reports.php">
                🚨 Reports
                <?php if ($stats['pending_reports'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending_reports']; ?></span>
                <?php endif; ?>
            </a>
            <a href="moderate-listings.php">⚖️ Moderate</a>
            <a href="announcements.php">📢 Announcements</a>
            <a href="categories.php">📁 Categories</a>
            <a href="settings.php">⚙️ Settings</a>
            <a href="maintenance.php">🔧 Maintenance</a>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-badge success">
                    📈 +<?php echo $stats['new_users_today']; ?> today
                </div>
                <div class="stat-badge info" style="margin-left: 0.5rem;">
                    <?php echo $stats['active_users_24h']; ?> active
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">📝</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_listings']); ?></div>
                <div class="stat-label">Total Listings</div>
                <div class="stat-badge success">
                    ✅ <?php echo $stats['active_listings']; ?> active
                </div>
                <div class="stat-badge info" style="margin-left: 0.5rem;">
                    +<?php echo $stats['new_listings_today']; ?> today
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">⏳</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending_listings']); ?></div>
                <div class="stat-label">Pending Approval</div>
                <?php if ($stats['pending_listings'] > 0): ?>
                    <div class="stat-badge warning">⚠️ Needs attention</div>
                <?php else: ?>
                    <div class="stat-badge success">✓ All clear</div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">💬</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_messages']); ?></div>
                <div class="stat-label">Total Messages</div>
                <div class="stat-badge info">
                    📧 <?php echo $stats['messages_today']; ?> today
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">💎</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['premium_members']); ?></div>
                <div class="stat-label">Premium Members</div>
                <div class="stat-badge success">
                    💰 $<?php echo number_format($stats['revenue_30days'], 2); ?>/mo
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">💵</div>
                </div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'], 0); ?></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-badge success">
                    📊 $<?php echo number_format($stats['revenue_30days'], 0); ?> this month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">🚨</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending_reports']); ?></div>
                <div class="stat-label">Pending Reports</div>
                <?php if ($stats['pending_reports'] > 0): ?>
                    <div class="stat-badge danger">🔴 Action required</div>
                <?php else: ?>
                    <div class="stat-badge success">✓ All resolved</div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">⭐</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_featured']); ?></div>
                <div class="stat-label">Featured Ads</div>
                <div class="stat-badge info">
                    🔥 Currently active
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="admin-section full-width">
            <div class="section-header">
                <h2>⚡ System Health</h2>
                <span style="color: var(--text-gray); font-size: 0.9rem;">
                    Last updated: <?php echo date('g:i A'); ?>
                </span>
            </div>
            <div class="system-health">
                <div class="health-item">
                    <div>
                        <span class="health-status good"></span>
                        <strong>Database</strong>
                    </div>
                    <div style="color: var(--text-gray); font-size: 0.85rem; margin-top: 0.5rem;">
                        <?php echo $stats['database_size']; ?> MB
                    </div>
                </div>
                <div class="health-item">
                    <div>
                        <span class="health-status good"></span>
                        <strong>Server Status</strong>
                    </div>
                    <div style="color: var(--text-gray); font-size: 0.85rem; margin-top: 0.5rem;">
                        Online
                    </div>
                </div>
                <div class="health-item">
                    <div>
                        <span class="health-status good"></span>
                        <strong>PHP Version</strong>
                    </div>
                    <div style="color: var(--text-gray); font-size: 0.85rem; margin-top: 0.5rem;">
                        <?php echo PHP_VERSION; ?>
                    </div>
                </div>
                <div class="health-item">
                    <div>
                        <span class="health-status good"></span>
                        <strong>Disk Space</strong>
                    </div>
                    <div style="color: var(--text-gray); font-size: 0.85rem; margin-top: 0.5rem;">
                        <?php 
                        $free = disk_free_space("/");
                        $total = disk_total_space("/");
                        echo round(($free / $total) * 100, 1) . '% free';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="admin-section full-width">
            <div class="section-header">
                <h2>⚡ Quick Actions</h2>
            </div>
            <div class="quick-actions">
                <a href="moderate-listings.php" class="quick-action-card" style="text-decoration: none; color: var(--text-white);">
                    <div class="icon">✅</div>
                    <div>Approve Listings</div>
                    <?php if ($stats['pending_listings'] > 0): ?>
                        <div class="stat-badge warning" style="margin-top: 0.5rem;">
                            <?php echo $stats['pending_listings']; ?> pending
                        </div>
                    <?php endif; ?>
                </a>
                <a href="reports.php" class="quick-action-card" style="text-decoration: none; color: var(--text-white);">
                    <div class="icon">🚨</div>
                    <div>Review Reports</div>
                    <?php if ($stats['pending_reports'] > 0): ?>
                        <div class="stat-badge danger" style="margin-top: 0.5rem;">
                            <?php echo $stats['pending_reports']; ?> pending
                        </div>
                    <?php endif; ?>
                </a>
                <a href="upgrades.php" class="quick-action-card" style="text-decoration: none; color: var(--text-white);">
                    <div class="icon">💎</div>
                    <div>Manage Upgrades</div>
                    <?php if ($stats['pending_upgrades'] > 0): ?>
                        <div class="stat-badge warning" style="margin-top: 0.5rem;">
                            <?php echo $stats['pending_upgrades']; ?> pending
                        </div>
                    <?php endif; ?>
                </a>
                <a href="announcements.php" class="quick-action-card" style="text-decoration: none; color: var(--text-white);">
                    <div class="icon">📢</div>
                    <div>Send Announcement</div>
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Users -->
            <div class="admin-section">
                <div class="section-header">
                    <h2>👥 Recent Users</h2>
                    <a href="users.php" class="btn btn-primary">View All</a>
                </div>
                <?php if (count($recent_users) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-white);">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                        $time_ago = time() - strtotime($user['created_at']);
                                        if ($time_ago < 3600) {
                                            echo round($time_ago / 60) . ' min ago';
                                        } elseif ($time_ago < 86400) {
                                            echo round($time_ago / 3600) . ' hrs ago';
                                        } else {
                                            echo date('M j, Y', strtotime($user['created_at']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="../profile.php?id=<?php echo $user['id']; ?>" class="action-btn view">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">👥</div>
                        <p>No users yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Listings -->
            <div class="admin-section">
                <div class="section-header">
                    <h2>📝 Recent Listings</h2>
                    <a href="listings.php" class="btn btn-primary">View All</a>
                </div>
                <?php if (count($recent_listings) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_listings as $listing): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--text-white);">
                                            <?php echo htmlspecialchars(substr($listing['title'], 0, 30)); ?>
                                            <?php echo strlen($listing['title']) > 30 ? '...' : ''; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($listing['username'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $listing['status']; ?>">
                                            <?php echo ucfirst($listing['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../listing.php?id=<?php echo $listing['id']; ?>" class="action-btn view">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📝</div>
                        <p>No listings yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Reports -->
        <?php if (count($pending_reports_list) > 0): ?>
        <div class="admin-section full-width">
            <div class="section-header">
                <h2>🚨 Pending Reports</h2>
                <a href="reports.php" class="btn btn-primary">View All Reports</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Reporter</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_reports_list as $report): ?>
                        <tr>
                            <td><?php echo $report['id']; ?></td>
                            <td>
                                <span class="status-badge status-pending">
                                    <?php echo ucfirst($report['report_type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($report['reporter'] ?? 'Anonymous'); ?></td>
                            <td><?php echo htmlspecialchars(substr($report['reason'], 0, 40)); ?>...</td>
                            <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                            <td>
                                <a href="../view-report.php?id=<?php echo $report['id']; ?>" class="action-btn view">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="text-align: center; padding: 2rem; color: var(--text-gray); margin-top: 2rem;">
            <p>Hookup Admin Dashboard v2.0 | Last login: <?php echo date('F j, Y g:i A'); ?></p>
            <p style="margin-top: 0.5rem;">
                <a href="../index.php" style="color: var(--primary-blue); text-decoration: none;">← Back to Site</a> | 
                <a href="../logout.php" style="color: var(--danger-red); text-decoration: none;">Logout</a>
            </p>
        </div>
    </div>

    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>