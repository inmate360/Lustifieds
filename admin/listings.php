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

// Handle listing actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET status = 'active' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing approved!';
        }
    }
    
    if(isset($_POST['reject_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET status = 'rejected' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing rejected!';
        }
    }
    
    if(isset($_POST['delete_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "DELETE FROM listings WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing deleted!';
        }
    }
    
    if(isset($_POST['feature_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET is_featured = TRUE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing featured!';
        }
    }
    
    if(isset($_POST['unfeature_listing'])) {
        $listing_id = $_POST['listing_id'];
        $query = "UPDATE listings SET is_featured = FALSE WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $listing_id);
        if($stmt->execute()) {
            $success = 'Listing unfeatured!';
        }
    }
}

// Pagination and filters
$page = $_GET['page'] ?? 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if($status_filter != 'all') {
    $where_conditions[] = "l.status = :status";
    $params[':status'] = $status_filter;
}

if($search) {
    $where_conditions[] = "(l.title LIKE :search OR l.description LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total listings
$query = "SELECT COUNT(*) as count FROM listings l 
          LEFT JOIN users u ON l.user_id = u.id 
          $where_clause";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_listings = $stmt->fetch()['count'];
$total_pages = ceil($total_listings / $per_page);

// Get listings
$query = "SELECT l.*, u.username, c.name as category_name, ct.name as city_name, s.abbreviation as state_abbr
          FROM listings l
          LEFT JOIN users u ON l.user_id = u.id
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN states s ON ct.state_id = s.id
          $where_clause
          ORDER BY l.created_at DESC
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

// Get status counts
$counts_query = "SELECT status, COUNT(*) as count FROM listings GROUP BY status";
$stmt = $db->prepare($counts_query);
$stmt->execute();
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

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

.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.5rem 1rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-white);
    transition: all 0.3s;
}

.filter-tab.active {
    background: rgba(66, 103, 245, 0.1);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
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
        <h1>📝 Listings Management</h1>
        <p style="color: var(--text-gray);">Manage all classified ads</p>
    </div>

    <div class="admin-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php">👥 Users</a>
        <a href="listings.php" class="active">📝 Listings</a>
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
        <h2>All Listings (<?php echo number_format($total_listings); ?>)</h2>
        
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All (<?php echo array_sum($status_counts); ?>)
            </a>
            <a href="?status=active" class="filter-tab <?php echo $status_filter == 'active' ? 'active' : ''; ?>">
                Active (<?php echo $status_counts['active'] ?? 0; ?>)
            </a>
            <a href="?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
            </a>
            <a href="?status=expired" class="filter-tab <?php echo $status_filter == 'expired' ? 'active' : ''; ?>">
                Expired (<?php echo $status_counts['expired'] ?? 0; ?>)
            </a>
            <a href="?status=rejected" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                Rejected (<?php echo $status_counts['rejected'] ?? 0; ?>)
            </a>
        </div>
        
        <form method="GET" class="search-box">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="text" name="search" placeholder="Search by title, description, or username..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-primary">Search</button>
            <?php if($search): ?>
            <a href="listings.php?status=<?php echo $status_filter; ?>" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($listings as $listing): ?>
                    <tr>
                        <td><?php echo $listing['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(substr($listing['title'], 0, 50)); ?></strong>
                            <?php if(strlen($listing['title']) > 50): ?>...<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($listing['username']); ?></td>
                        <td><?php echo htmlspecialchars($listing['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($listing['city_name']); ?>, <?php echo $listing['state_abbr']; ?></td>
                        <td>
                            <span style="color: <?php 
                                echo $listing['status'] == 'active' ? 'var(--success-green)' : 
                                    ($listing['status'] == 'pending' ? 'var(--warning-orange)' : 'var(--danger-red)'); 
                            ?>">
                                <?php echo ucfirst($listing['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $listing['is_featured'] ? '<span style="color: var(--featured-gold);">⭐ Yes</span>' : 'No'; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
                        <td>
                            <a href="../listing.php?id=<?php echo $listing['id']; ?>" class="action-btn view" target="_blank">View</a>
                            
                            <?php if($listing['status'] == 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="approve_listing" class="action-btn edit">
                                    Approve
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="reject_listing" class="action-btn delete" onclick="return confirm('Reject this listing?');">
                                    Reject
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if($listing['is_featured']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="unfeature_listing" class="action-btn edit">
                                    Unfeature
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="feature_listing" class="action-btn edit">
                                    Feature
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" name="delete_listing" class="action-btn delete" onclick="return confirm('Permanently delete this listing?');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
            <a href="?page=<?php echo ($page - 1); ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">← Previous</a>
            <?php endif; ?>
            
            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo ($page + 1); ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../views/footer.php'; ?>