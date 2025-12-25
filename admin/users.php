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

// Handle user actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['suspend_user'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_suspended = TRUE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User suspended successfully!';
        }
    }
    
    if(isset($_POST['unsuspend_user'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_suspended = FALSE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User unsuspended!';
        }
    }
    
    if(isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User deleted!';
        }
    }
    
    if(isset($_POST['make_admin'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_admin = TRUE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'User promoted to admin!';
        }
    }
    
    if(isset($_POST['remove_admin'])) {
        $user_id = $_POST['user_id'];
        $query = "UPDATE users SET is_admin = FALSE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id);
        if($stmt->execute()) {
            $success = 'Admin privileges removed!';
        }
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Search
$search = $_GET['search'] ?? '';
$where_clause = '';
if($search) {
    $where_clause = "WHERE username LIKE :search OR email LIKE :search";
}

// Get total users
$query = "SELECT COUNT(*) as count FROM users $where_clause";
$stmt = $db->prepare($query);
if($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$total_users = $stmt->fetch()['count'];
$total_pages = ceil($total_users / $per_page);

// Get users
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM listings WHERE user_id = u.id) as listing_count,
          (SELECT COUNT(*) FROM messages WHERE sender_id = u.id) as message_count
          FROM users u
          $where_clause
          ORDER BY u.created_at DESC
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
if($search) {
    $stmt->bindParam(':search', $search_param);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

include '../views/header.php';
?>

<link rel="stylesheet" href="../assets/css/dark-blue-theme.css">

<style>
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 20px;
}

.admin-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.admin-nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.admin-nav a {
    padding: 0.75rem 1.5rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-white);
    transition: all 0.3s;
}

.admin-nav a:hover, .admin-nav a.active {
    background: rgba(66, 103, 245, 0.1);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
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
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-gray);
}

.data-table tr:hover {
    background: rgba(66, 103, 245, 0.05);
}

.action-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-block;
    margin-right: 0.5rem;
    border: none;
    cursor: pointer;
    background: none;
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

.user-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
}

.user-badge.admin {
    background: rgba(251, 191, 36, 0.2);
    color: var(--featured-gold);
}

.user-badge.suspended {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

.user-badge.verified {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-green);
}

.search-box {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.search-box input {
    flex: 1;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 2rem;
}

.pagination a {
    padding: 0.5rem 1rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-white);
}

.pagination a.active {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
}

.pagination a:hover {
    background: rgba(66, 103, 245, 0.1);
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <h1>👥 User Management</h1>
        <p style="color: var(--text-gray);">Manage all registered users</p>
    </div>

    <div class="admin-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php" class="active">👥 Users</a>
        <a href="listings.php">📝 Listings</a>
        <a href="upgrades.php">💎 Upgrades</a>
        <a href="reports.php">🚨 Reports</a>
        <a href="announcements.php">📢 Announcements</a>
        <a href="categories.php">📁 Categories</a>
        <a href="settings.php">⚙️ Settings</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>All Users (<?php echo number_format($total_users); ?>)</h2>
        
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-primary">Search</button>
            <?php if($search): ?>
            <a href="users.php" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Listings</th>
                        <th>Messages</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user_item): ?>
                    <tr>
                        <td><?php echo $user_item['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($user_item['username']); ?></strong>
                            <?php if($user_item['is_admin']): ?>
                            <span class="user-badge admin">Admin</span>
                            <?php endif; ?>
                            <?php if($user_item['verified']): ?>
                            <span class="user-badge verified">✓ Verified</span>
                            <?php endif; ?>
                            <?php if($user_item['is_suspended'] ?? false): ?>
                            <span class="user-badge suspended">Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                        <td>
                            <?php if($user_item['is_online']): ?>
                            <span style="color: var(--success-green);">● Online</span>
                            <?php else: ?>
                            <span style="color: var(--text-gray);">○ Offline</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user_item['listing_count']; ?></td>
                        <td><?php echo $user_item['message_count']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user_item['created_at'])); ?></td>
                        <td>
                            <a href="../profile.php?id=<?php echo $user_item['id']; ?>" class="action-btn view" target="_blank">View</a>
                            
                            <?php if(!$user_item['is_admin']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="make_admin" class="action-btn edit" onclick="return confirm('Make this user an admin?');">
                                    Make Admin
                                </button>
                            </form>
                            <?php elseif($user_item['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="remove_admin" class="action-btn edit" onclick="return confirm('Remove admin privileges?');">
                                    Remove Admin
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if($user_item['id'] != $_SESSION['user_id']): ?>
                            <?php if($user_item['is_suspended'] ?? false): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="unsuspend_user" class="action-btn edit">
                                    Unsuspend
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="suspend_user" class="action-btn delete" onclick="return confirm('Suspend this user?');">
                                    Suspend
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="delete_user" class="action-btn delete" onclick="return confirm('Permanently delete this user? This cannot be undone!');">
                                    Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>">← Previous</a>
            <?php endif; ?>
            
            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../views/footer.php'; ?>