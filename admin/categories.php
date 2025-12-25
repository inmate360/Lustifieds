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

// Handle category actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['create_category'])) {
        $query = "INSERT INTO categories (name, slug, description, icon, display_order) 
                  VALUES (:name, :slug, :description, :icon, :display_order)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':slug', $_POST['slug']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':icon', $_POST['icon']);
        $stmt->bindParam(':display_order', $_POST['display_order']);
        
        if($stmt->execute()) {
            $success = 'Category created successfully!';
        } else {
            $error = 'Failed to create category';
        }
    }
    
    if(isset($_POST['update_category'])) {
        $query = "UPDATE categories 
                  SET name = :name, slug = :slug, description = :description, 
                      icon = :icon, display_order = :display_order
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':slug', $_POST['slug']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':icon', $_POST['icon']);
        $stmt->bindParam(':display_order', $_POST['display_order']);
        $stmt->bindParam(':id', $_POST['category_id']);
        
        if($stmt->execute()) {
            $success = 'Category updated!';
        }
    }
    
    if(isset($_POST['delete_category'])) {
        $query = "DELETE FROM categories WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_POST['category_id']);
        
        if($stmt->execute()) {
            $success = 'Category deleted!';
        }
    }
}

// Get all categories with listing counts
$query = "SELECT c.*, COUNT(l.id) as listing_count 
          FROM categories c
          LEFT JOIN listings l ON c.id = l.category_id
          GROUP BY c.id
          ORDER BY c.display_order ASC, c.name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll();

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

.action-btn.edit {
    background: rgba(245, 158, 11, 0.2);
    color: var(--warning-orange);
}

.action-btn.delete {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <h1>📁 Categories Management</h1>
        <p style="color: var(--text-gray);">Manage listing categories</p>
    </div>

    <div class="admin-nav">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="users.php">👥 Users</a>
        <a href="listings.php">📝 Listings</a>
        <a href="upgrades.php">💎 Upgrades</a>
        <a href="reports.php">🚨 Reports</a>
        <a href="announcements.php">📢 Announcements</a>
        <a href="categories.php" class="active">📁 Categories</a>
        <a href="settings.php">⚙️ Settings</a>
    </div>

    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>Create New Category</h2>
        <form method="POST" action="categories.php">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" required placeholder="e.g., Men Seeking Women">
                </div>
                
                <div class="form-group">
                    <label>Slug (URL friendly)</label>
                    <input type="text" name="slug" required placeholder="e.g., men-seeking-women">
                </div>
                
                <div class="form-group">
                    <label>Icon (emoji)</label>
                    <input type="text" name="icon" placeholder="👨" maxlength="2">
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="0" min="0">
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Brief description of this category"></textarea>
            </div>
            
            <button type="submit" name="create_category" class="btn-primary">Create Category</button>
        </form>
    </div>

    <div class="admin-section">
        <h2>All Categories (<?php echo count($categories); ?>)</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Listings</th>
                        <th>Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($categories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td style="font-size: 1.5rem;"><?php echo $category['icon']; ?></td>
                        <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                        <td><?php echo $category['listing_count']; ?></td>
                        <td><?php echo $category['display_order']; ?></td>
                        <td>
                            <button class="action-btn edit" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                Edit
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                <button type="submit" name="delete_category" class="action-btn delete" onclick="return confirm('Delete this category? All listings will remain but category will be removed.');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div class="card" style="max-width: 600px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Edit Category</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="category_id" id="editCategoryId">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" id="editName" required>
                </div>
                
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" id="editSlug" required>
                </div>
                
                <div class="form-group">
                    <label>Icon</label>
                    <input type="text" name="icon" id="editIcon" maxlength="2">
                </div>
                
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="editOrder" min="0">
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editDescription" rows="3"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <button type="button" class="btn-secondary btn-block" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="update_category" class="btn-primary btn-block">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('editCategoryId').value = category.id;
    document.getElementById('editName').value = category.name;
    document.getElementById('editSlug').value = category.slug;
    document.getElementById('editIcon').value = category.icon || '';
    document.getElementById('editOrder').value = category.display_order;
    document.getElementById('editDescription').value = category.description || '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if(e.target === this) {
        closeEditModal();
    }
});
</script>

<?php include '../views/footer.php'; ?>